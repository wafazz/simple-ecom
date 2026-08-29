<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands a settled order to an endpoint on another host.
 *
 * This VPS blocks outbound SMTP, so anything that has to leave the building
 * goes out from somewhere else. The order details are POSTed as JSON over 443
 * and the far end takes it from there. Deliberately knows nothing about what
 * it does with them — this is a payload, not a mail transport, and there is no
 * subject line, no rendered body and no recipient logic on this side.
 *
 * NEVER throws. The money has already arrived and the order is recorded; a
 * failure here must not reach ToyyibPay, which would retry a callback that
 * already succeeded.
 */
final class OrderRelayService
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $token,
        private readonly int $connectTimeout,
        private readonly int $timeout,
    ) {}

    /** Built by hand, like the other services — the constructor takes strings. */
    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.order_relay.url'),
            config('services.order_relay.token'),
            (int) config('services.order_relay.connect_timeout', 5),
            (int) config('services.order_relay.timeout', 15),
        );
    }

    public function isConfigured(): bool
    {
        return $this->url !== '';
    }

    /** @return bool Whether the endpoint accepted it. */
    public function send(Order $order): bool
    {
        if (! $this->isConfigured()) {
            // Says so out loud. Returning silently made an unset ORDER_RELAY_URL
            // indistinguishable from a relay that ran and worked: the order
            // settled, the log went quiet, and no email arrived — with nothing
            // anywhere to say why. The SMTP path next to this one logs its
            // reason for the same purpose.
            Log::warning('Order not relayed — no endpoint configured', [
                'order_no' => $order->order_no,
                'hint' => 'Set ORDER_RELAY_URL in .env, then php artisan config:clear.',
            ]);

            return false;
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                // Sent as a header rather than a query parameter: a query
                // string is written to the receiving host's access log and to
                // every proxy in between, and this is a credential. Repeated
                // in the body because some shared-hosting PHP handlers drop
                // unrecognised X- headers before the script ever sees them.
                ->withHeaders(array_filter(['X-Relay-Token' => $this->token]))
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post($this->url, $this->payload($order));
        } catch (Throwable $e) {
            Log::error('Order relay failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        // A 2xx is not on its own an acceptance: the endpoint answers 200 with
        // success:false for anything it validated and refused.
        $accepted = $response->successful() && $response->json('success') === true;

        if (! $accepted) {
            Log::error('Order relay refused', [
                'order_no' => $order->order_no,
                'status' => $response->status(),
                // The endpoint's own words. Without them a failure is
                // unactionable, and truncated because it may be an HTML error
                // page rather than the JSON we asked for.
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return false;
        }

        Log::info('Order relayed', ['order_no' => $order->order_no]);

        return true;
    }

    /**
     * The order, as flat as it can reasonably be.
     *
     * Amounts are pre-formatted decimal STRINGS, not the integer sen this
     * system stores. The receiving script is a standalone PHP file with no
     * knowledge of minor units, and a float over JSON is exactly the
     * arithmetic this project avoids everywhere else — so the conversion
     * happens once, here, at the boundary.
     *
     * @return array<string, mixed>
     */
    public function payload(Order $order): array
    {
        // loadMissing rather than assuming: Model::shouldBeStrict() turns a
        // lazy load into an exception outside production, and this is reached
        // from a callback that has not necessarily loaded the items.
        $order->loadMissing('items');

        return [
            'customer_name' => $order->customer_name,
            // Not in the requested list, but the far end has to have somewhere
            // to send. Nothing on this side depends on what it does with it.
            'customer_email' => $order->customer_email,
            'order_no' => $order->order_no,
            'purchase_date' => $order->created_at?->format('j M Y, H:i'),
            'currency' => (string) config('shop.currency_symbol'),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'name' => $item->product_name,
                // Size, colour and so on. Kept separate from the name so the
                // far end can lay it out however it likes.
                'variation' => $item->variation_label !== '' ? $item->variation_label : null,
                // "AZLAN 10". Printed to order, so the buyer needs to see it
                // spelled out while a mistake is still worth reporting.
                'nameset' => $item->hasNameset() ? $item->namesetLabel() : null,
                'qty' => $item->qty,
                'unit_price' => Money::format($item->unit_price_minor),
                'total' => Money::format($item->line_total_minor),
            ])->values()->all(),
            // The items total, before delivery.
            'total' => Money::format($order->subtotal_minor),
            'delivery_cost' => Money::format($order->shipping_fee_minor),
            'grand_total' => Money::format($order->grand_total_minor),
        ];
    }
}
