<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Courier;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualShipmentRequest;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fulfilment by hand, for while the EasyParcel integration is on hold: the
 * admin books with the courier themselves and records what they got back.
 *
 * Nothing here talks to a courier or spends money, which is the whole reason
 * it can be a single form rather than the quote-then-confirm dance booking
 * needs.
 */
class ManualShipmentController extends Controller
{
    /**
     * Record (or correct) the courier, AWB number and label for one order.
     *
     * updateOrCreate keyed on order_id, because UNIQUE(shipments.order_id)
     * allows exactly one shipment per order — a second insert would be a
     * duplicate-key error rather than a second row.
     */
    public function store(ManualShipmentRequest $request, Order $order): RedirectResponse
    {
        // Re-checked here, not merely hidden in the view. The rule that matters
        // is that a paid EasyParcel booking can never be overwritten by hand
        // (§17 — never trust that the form the admin saw is the form that was
        // submitted).
        abort_unless($order->canEnterAwbManually(), 403);

        $data = $request->validated();
        $courier = Courier::from($data['courier']);

        // Stored BEFORE the row is written. A file with no row is an orphan an
        // admin never sees; a row pointing at a file that was never saved is a
        // broken Print AWB link on a parcel that has already gone out.
        $path = $this->storeLabel($request, $order);

        $previous = $order->shipment;

        DB::transaction(function () use ($order, $courier, $data, $path, $previous): void {
            Shipment::updateOrCreate(
                ['order_id' => $order->id],
                array_filter([
                    'provider' => Shipment::PROVIDER_MANUAL,
                    'courier_name' => $courier->label(),
                    'awb_no' => $data['awb_no'],

                    // For a hand-booked parcel the AWB *is* the tracking
                    // number. Both are recomputed on every save, so correcting
                    // a typo cannot leave a link pointing at the old number.
                    'tracking_no' => $data['awb_no'],
                    'tracking_url' => $courier->trackingUrl($data['awb_no']),

                    'status' => ShipmentStatus::Booked->value,
                    'booked_at' => $previous?->booked_at ?? now(),

                    // Only overwrite the stored file when a new one arrived —
                    // re-saving to fix a typo must not wipe the label.
                    'label_path' => $path,
                ], fn ($v): bool => $v !== null) + $this->clearedApiFields($previous)
            );
        });

        // Deleted only after the new row is committed, so a failure here costs
        // an orphaned file rather than a label the order still points at.
        if ($path !== null && filled($previous?->label_path)) {
            Storage::disk('awb')->delete($previous->label_path);
        }

        Log::info('Admin recorded a manual shipment', [
            'order_no' => $order->order_no,
            'courier' => $courier->value,
            'replaced' => $previous !== null,
            'has_label' => $path !== null || filled($previous?->label_path),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', "AWB {$data['awb_no']} recorded for {$courier->label()}.");
    }

    /**
     * Fields that described a courier API booking, when this row is being
     * taken over by a manual one.
     *
     * Only reachable for an attempt that provably charged nothing — a real
     * booking is refused by canEnterAwbManually(). What is left behind is the
     * wreckage of a failed submit: an error body, a service id nobody bought,
     * and possibly a label URL. Left in place, `labelUrl()` would happily hand
     * an admin EasyParcel's label for a parcel EasyParcel never shipped.
     *
     * @return array<string, mixed>
     */
    private function clearedApiFields(?Shipment $previous): array
    {
        if ($previous === null || $previous->isManual()) {
            return [];
        }

        // tracking_no and tracking_url are absent on purpose: every save sets
        // them from the chosen courier and AWB, so nulling them here would be
        // overwritten in the same statement and only mislead the next reader.
        return [
            'provider_shipment_ref' => null,
            'service_id' => null,
            'service_name' => null,
            'cost_minor' => null,
            'label_url' => null,
            'raw_response' => null,
        ];
    }

    /**
     * Stream an uploaded label to the admin.
     *
     * An airway bill carries the customer's name, full address and phone, so
     * the file lives on a private disk and is never reachable by URL. This
     * route sits inside the admin group, which is what makes it safe to link.
     */
    public function label(Order $order): StreamedResponse
    {
        $path = $order->shipment?->label_path;

        abort_if(blank($path) || ! Storage::disk('awb')->exists($path), 404);

        // Inline, so a PDF opens in the browser's viewer and prints from there
        // rather than landing in Downloads. The filename is ours, never the
        // one the admin uploaded.
        return Storage::disk('awb')->response(
            $path,
            'AWB-'.$order->order_no.'.'.pathinfo($path, PATHINFO_EXTENSION),
            ['Content-Disposition' => 'inline'],
        );
    }

    /**
     * Save the uploaded label under a framework-generated name.
     *
     * The 'awb' disk is 'throw' => true precisely so this cannot fail quietly.
     * The uploads disk's opposite setting once turned a directory-permission
     * problem into a bare 500 on a live VPS (ProductController::storeImage);
     * here the same cause surfaces on the field the admin touched.
     */
    private function storeLabel(ManualShipmentRequest $request, Order $order): ?string
    {
        if (! $request->hasFile('awb_file')) {
            return null;
        }

        try {
            $path = Storage::disk('awb')->putFile('labels', $request->file('awb_file'));
        } catch (FilesystemException $e) {
            $this->failUpload($order, $e->getMessage());
        }

        if (! is_string($path) || $path === '') {
            $this->failUpload($order, 'putFile() returned no path.');
        }

        return $path;
    }

    /**
     * The detail goes to the log, not the screen: the message carries absolute
     * server paths, which do not belong in a rendered response (§17).
     */
    private function failUpload(Order $order, string $reason): never
    {
        Log::error('AWB upload failed.', [
            'order_no' => $order->order_no,
            'disk_root' => config('filesystems.disks.awb.root'),
            'reason' => $reason,
        ]);

        throw ValidationException::withMessages([
            'awb_file' => 'The AWB file could not be saved. storage/app/private is most '
                .'likely not writable by the web server — see DEPLOYMENT.md. '
                .'Nothing was recorded for this order.',
        ]);
    }
}
