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
