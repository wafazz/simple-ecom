<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** REQ-007 — Planning §6.2. */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'order_status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $orders = Order::query()
            ->when($filters['order_status'] ?? null, fn ($q, $v) => $q->where('order_status', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(
                fn ($sub) => $sub->where('order_no', 'like', "%{$v}%")
                    ->orWhere('customer_email', 'like', "%{$v}%")
                    ->orWhere('customer_name', 'like', "%{$v}%")
            ))
            ->withCount('items')
            // The row actions ask each order whether it can be booked and
            // whether it has an AWB, and both read the shipment. Without this
            // that is one query per row.
            ->with('shipment')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            // Orders where money was taken but stock could not satisfy the line
            // need a human before anything else (Planning §7.5).
            'needsReviewCount' => Order::query()
                ->where('order_status', OrderStatus::NeedsReview->value)
                ->count(),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items', 'payment', 'shipment']),
        ]);
    }

    /**
     * Advances the fulfilment status. Spec §14: no workflow engine — plain
     * application logic and database state.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            // only() excludes NeedsReview: the system sets that, an admin does
            // not choose it (Planning §7.5).
            'order_status' => ['required', Rule::enum(OrderStatus::class)->only(OrderStatus::selectable())],
        ]);

        $before = $order->order_status;
        $order->forceFill(['order_status' => $data['order_status']])->save();

        Log::info('Admin changed order status', [
            'order_no' => $order->order_no,
            'from' => $before->value,
            'to' => $order->order_status->value,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Order status updated.');
    }

    /**
     * Move one or many orders to Processing — REQ-007.
     *
     * The single row button and the bulk bar post to THIS action, one with an
     * array of one. Two endpoints would be two sets of rules to keep in step,
     * and the one that gets less use is the one that drifts.
     *
     * The status filter is a guarded UPDATE, not a check-then-write: an order
     * that changed underneath us (a refund, a cancellation, another admin in
     * the next tab) is simply not matched, and the affected-row count tells us
     * exactly how many really moved.
     */
    public function process(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'max:200'],
            'order_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['order_ids']));

        $moved = Order::query()
            ->whereIn('id', $ids)
            ->where('order_status', OrderStatus::NewOrder->value)
            ->update([
                'order_status' => OrderStatus::Processing->value,
                'updated_at' => now(),
            ]);

        $skipped = count($ids) - $moved;

        Log::info('Admin moved orders to processing', [
            'requested' => count($ids),
            'moved' => $moved,
            'skipped' => $skipped,
            'user_id' => $request->user()?->id,
        ]);

        if ($moved === 0) {
            return back()->with('error', $skipped === 1
                ? 'That order is not a new order, so it was not moved.'
                : 'None of those orders were new orders, so nothing was moved.');
        }

        $message = $moved === 1
            ? '1 order moved to Processing.'
            : "{$moved} orders moved to Processing.";

        if ($skipped > 0) {
            // Never silent. A partial result an admin cannot see is a partial
            // result they will assume was total.
            $message .= " {$skipped} skipped — only a New Order can be moved.";
        }

        return back()->with('status', $message);
    }

    /**
     * The bulk bar's single entry point — REQ-007, REQ-013.
     *
     * One POST form for three actions. The alternative, a button carrying
     * `formmethod="get"`, would put every field in the query string — the CSRF
     * token included, straight into the access log and the Referer header.
     *
     * So: everything arrives by POST, and the two read-only destinations are
     * reached by redirect. They stay GET, which keeps the booking screen
     * refreshable and linkable without ever being the thing that charges.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulk_action' => ['required', 'in:process,book,awb'],
            'order_ids' => ['required', 'array', 'max:200'],
            'order_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['order_ids']));

        return match ($data['bulk_action']) {
            'process' => $this->process($request),
            'book' => redirect()->route('admin.orders.book', ['order_ids' => $ids]),
            'awb' => redirect()->route('admin.orders.awb', ['order_ids' => $ids]),
        };
    }

    /**
     * Refunds are recorded, never processed — spec §14 lists `Refunded` as a
     * payment STATUS only, and Planning §3.2 keeps gateway refunds out of scope.
     *
     * This is the ONLY payment-status change an admin may make. Paid and failed
     * are gateway-driven and set solely by server-side verification; allowing an
     * admin to mark an order paid by hand would bypass §11.A.5 entirely.
     */
    public function markRefunded(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            return back()->with('error', 'Only a paid order can be marked refunded.');
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::Refunded,
            'order_status' => OrderStatus::Cancelled,
        ])->save();

        Log::warning('Admin marked order refunded', [
            'order_no' => $order->order_no,
            'amount_minor' => $order->grand_total_minor,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Order marked as refunded. Issue the refund in the ToyyibPay dashboard.');
    }
}
