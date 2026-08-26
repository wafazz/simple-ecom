<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShipmentBookingFailed;
use App\Exceptions\ShipmentOutcomeUnknown;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EasyParcelService;
use App\Services\ShipmentBookingService;
use App\Support\ShipmentPayload;
use App\Support\ShippingQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** REQ-013 — courier booking, AWB and labels. Planning §11.B.5. */
class ShipmentController extends Controller
{
    /** One click must never book more than this. */
    private const MAX_PER_BOOKING = 50;

    public function __construct(private readonly EasyParcelService $easyparcel) {}

    /**
     * The booking screen — quote first, choose a service, THEN spend money.
     *
     * Booking is the only action in the application that spends real courier
     * credit, and in bulk it does so many times over. It therefore gets a
     * confirmation step rather than a one-click bulk button: the admin sees
     * every parcel, its destination, its weight and its price before anything
     * is charged.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $orders = $this->selected($request);

        if ($orders->isEmpty()) {
            return redirect()
                ->route('admin.orders.index', ['order_status' => 'processing'])
                ->with('error', 'None of those orders can be booked. An order needs to be paid, and not already have an AWB.');
        }

        // One quotation per order: each goes to its own address, so there is
        // no single quote that covers the selection.
        $rows = $orders->map(function (Order $order): array {
            $missing = ShipmentPayload::missingFor($order);
            $weightG = ShipmentPayload::totalWeightG($order);

            return [
                'order' => $order,
                'weight_g' => $weightG,
                'missing' => $missing,
                'quotes' => $missing === [] ? $this->easyparcel->courierQuotes(
                    (string) $order->postcode,
                    (string) $order->state,
                    $weightG,
                ) : [],
            ];
        });

        return view('admin.orders.book', [
            'rows' => $rows,
            // Only a service EVERY bookable order can use. Offering one that
            // covers three of five would fail on the other two after the first
            // three had already been charged.
            'services' => $this->commonServices($rows),
            'bookable' => $rows->filter(fn (array $r): bool => $r['missing'] === [] && $r['quotes'] !== []),
        ]);
    }

    /**
     * Book the selected orders with the chosen service.
     *
     * Each order is booked on its own, in its own try/catch. One rejection
     * must not abandon the rest — and one AMBIGUOUS outcome must not be
     * retried by anything, which is why it is counted separately and reported
     * in its own words.
     */
    public function store(Request $request, ShipmentBookingService $booking): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'string', 'max:64'],
            'order_ids' => ['required', 'array', 'max:'.self::MAX_PER_BOOKING],
            'order_ids.*' => ['integer'],
        ]);

        $orders = $this->selected($request);
        $booked = [];
        $failed = [];
        $unknown = [];

        foreach ($orders as $order) {
            try {
                $shipment = $booking->book($order, $data['service_id']);
                $booked[] = $order->order_no.($shipment->awb_no ? " (AWB {$shipment->awb_no})" : '');
            } catch (ShipmentOutcomeUnknown $e) {
                // May already have been charged. Never retried, by anything.
                $unknown[] = $order->order_no;
            } catch (ShipmentBookingFailed $e) {
                $failed[] = $order->order_no.' — '.$e->getMessage();
            }
        }

        Log::info('Admin booked shipments', [
            'service_id' => $data['service_id'],
            'booked' => count($booked),
            'failed' => count($failed),
            'unknown' => count($unknown),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.orders.index', ['order_status' => 'processing'])
            ->with($this->outcome($booked, $failed, $unknown));
    }

    /**
     * A print sheet for the selected orders' airway bills.
     *
     * The labels themselves are PDFs on EasyParcel's own domain — this page
     * cannot render or bundle them, and pretending otherwise would produce a
     * button that silently prints nothing. It lists each AWB with a direct
     * link, and opens them together on request.
     */
    public function labels(Request $request): View|RedirectResponse
    {
        $orders = Order::query()
            ->whereIn('id', $this->ids($request))
            ->with('shipment')
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $o): bool => $o->hasAwb());

        if ($orders->isEmpty()) {
            return redirect()
                ->route('admin.orders.index', ['order_status' => 'processing'])
                ->with('error', 'None of those orders have an AWB yet. Book the courier first.');
        }

        return view('admin.orders.labels', ['orders' => $orders]);
    }

    /**
     * @param  array<int, string>  $booked
     * @param  array<int, string>  $failed
     * @param  array<int, string>  $unknown
     * @return array<string, string>
     */
    private function outcome(array $booked, array $failed, array $unknown): array
    {
        // An unknown outcome outranks everything else on the screen: it is the
        // only one where the store may have paid for nothing.
        if ($unknown !== []) {
            return ['error' => 'Outcome UNKNOWN for '.implode(', ', $unknown)
                .' — the courier may already have been paid. Check the EasyParcel dashboard '
                .'before trying again; these will not retry on their own.'
                .($booked !== [] ? ' '.count($booked).' other order(s) booked successfully.' : ''), ];
        }

        if ($booked === []) {
            return ['error' => 'Nothing was booked. '.implode(' · ', $failed)];
        }

        $message = count($booked).' shipment(s) booked: '.implode(', ', $booked).'.';

        if ($failed !== []) {
            $message .= ' '.count($failed).' failed — '.implode(' · ', $failed);
        }

        return $failed === [] ? ['status' => $message] : ['error' => $message];
    }

    /**
     * The selected orders that can actually be booked.
     *
     * Filtered here rather than trusted from the request: an id that arrives
     * for an order already carrying an AWB is simply dropped, so a stale page
     * or a hand-made form cannot double-book (§17).
     *
     * @return Collection<int, Order>
     */
    private function selected(Request $request): Collection
    {
        return Order::query()
            ->whereIn('id', $this->ids($request))
            ->with(['items.variant', 'shipment'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $o): bool => $o->canBookShipment())
            ->values();
    }

    /** @return array<int, int> */
    private function ids(Request $request): array
    {
        return array_slice(
            array_values(array_unique(array_map(
                'intval',
                (array) $request->input('order_ids', []),
            ))),
            0,
            self::MAX_PER_BOOKING,
        );
    }

    /**
     * Services every bookable order in the selection can use.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string> service_id => label
     */
    private function commonServices(Collection $rows): array
    {
        $bookable = $rows->filter(fn (array $r): bool => $r['missing'] === [] && $r['quotes'] !== []);

        if ($bookable->isEmpty()) {
            return [];
        }

        $common = null;

        foreach ($bookable as $row) {
            $ids = array_map(fn (ShippingQuote $q): string => $q->serviceId, $row['quotes']);
            $common = $common === null ? $ids : array_intersect($common, $ids);
        }

        $labels = [];

        foreach ($bookable->first()['quotes'] as $quote) {
            if (in_array($quote->serviceId, (array) $common, true)) {
                $labels[$quote->serviceId] = $quote->label();
            }
        }

        return $labels;
    }
}
