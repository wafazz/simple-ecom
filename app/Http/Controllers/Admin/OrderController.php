<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Courier;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderDetailsRequest;
use App\Models\Order;
use App\Services\EasyParcelService;
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

            // One lookup for the page, not one per row.
            'easyparcelConnected' => EasyParcelService::fromConfig()->isConnected(),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items', 'payment', 'shipment']),

            // Booking is offered only when EasyParcel could actually carry it
            // out. While the integration is on hold the button is visibly
            // disabled rather than removed, so the admin can see that the path
            // exists and why it is unavailable.
            'easyparcelConnected' => EasyParcelService::fromConfig()->isConnected(),
            'couriers' => Courier::options(),
        ]);
    }

    /**
     * Correct the delivery and contact details of a New Order.
     *
     * Contents and money are not editable here and are not meant to be: the
     * amount has already been collected, so changing a line would leave the
     * order disagreeing with what the customer actually paid.
     */
    public function edit(Order $order): View
    {
        abort_unless($order->canEditDetails(), 403);

        return view('admin.orders.edit', [
            'order' => $order,
            'states' => config('shop.states'),
        ]);
    }

    public function updateDetails(OrderDetailsRequest $request, Order $order): RedirectResponse
    {
        // Guarded UPDATE, not check-then-write — the same reason as approve().
        // The order can move between this form being opened and submitted: a
        // status change in another tab, or a shipment booked, either of which
        // must beat the edit rather than be silently overwritten by it.
        $saved = Order::query()
            ->whereKey($order->id)
            ->where('order_status', OrderStatus::NewOrder->value)
            ->whereDoesntHave('shipment')
            ->update($request->validated() + ['updated_at' => now()]);

        if ($saved !== 1) {
            return back()
                ->withInput()
                ->with('error', 'That order has moved on or has a shipment booked, so the details were not changed.');
        }

        Log::info('Admin edited order delivery details', [
            'order_no' => $order->order_no,
            'user_id' => $request->user()?->id,
            'fields' => array_keys($request->validated()),
        ]);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', "Delivery details updated for {$order->order_no}. The shipping fee was not re-quoted.");
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
     * Accept an unpaid order by hand — the counterpart to a gateway payment.
     *
     * Moves Pending to New Order and DELIBERATELY leaves payment_status alone.
     * Marking an order paid from the admin would bypass the server-side
     * verification in §11.A.5, which is the same rule markRefunded() states: an
     * admin may record a refund and nothing else about payment. An order
     * approved here is accepted for fulfilment while the money is still
     * outstanding — a bank transfer or cash on delivery — and the payment badge
     * keeps saying so until the gateway, or a refund, says otherwise.
     */
    public function approve(Request $request, Order $order): RedirectResponse
    {
        // Guarded UPDATE, not check-then-write: an order that moved underneath
        // us — a callback landing, another admin in the next tab — is simply
        // not matched.
        $moved = Order::query()
            ->whereKey($order->id)
            ->where('order_status', OrderStatus::Pending->value)
            ->update(['order_status' => OrderStatus::NewOrder->value, 'updated_at' => now()]);

        if ($moved !== 1) {
            return back()->with('error', 'That order is no longer pending, so it was not approved.');
        }

        Log::info('Admin approved a pending order', [
            'order_no' => $order->order_no,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', "Order {$order->order_no} approved. Payment is still outstanding.");
    }

    /**
     * Cancel an order that was never paid for.
     *
     * Paid orders are NOT cancelled here — money that arrived is settled with
     * markRefunded(), which records the refund alongside the cancellation
     * instead of quietly dropping the order while the customer is out of pocket.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return back()->with('error', 'That order is paid — record a refund instead of cancelling it.');
        }

        $cancelled = Order::query()
            ->whereKey($order->id)
            ->where('order_status', OrderStatus::Pending->value)
            ->update(['order_status' => OrderStatus::Cancelled->value, 'updated_at' => now()]);

        if ($cancelled !== 1) {
            return back()->with('error', 'That order is no longer pending, so it was not cancelled.');
        }

        Log::info('Admin cancelled a pending order', [
            'order_no' => $order->order_no,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', "Order {$order->order_no} cancelled.");
    }

    /**
     * Take an unpaid order off the list without destroying it.
     *
     * Soft: the row, its items and its number all survive, so the record of
     * what happened is intact and it can be restored. A PAID order is never
     * deletable — hiding an order the customer has money in is how a shop loses
     * track of what it owes.
     */
    public function destroy(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return back()->with('error', 'A paid order cannot be deleted. Record a refund instead.');
        }

        $order->delete();

        Log::info('Admin deleted an order', [
            'order_no' => $order->order_no,
            'order_status' => $order->order_status->value,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', "Order {$order->order_no} deleted.");
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
