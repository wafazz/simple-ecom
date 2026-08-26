<?php

namespace App\Services;

use App\Exceptions\ShipmentBookingFailed;
use App\Exceptions\ShipmentOutcomeUnknown;
use App\Models\IntegrationToken;
use App\Models\Setting;
use App\Support\IntegrationConfig;
use App\Support\Money;
use App\Support\ShippingQuote;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SensitiveParameter;

/**
 * REQ-006 / REQ-013 — Planning §11.B.
 *
 * One service for the whole vendor: quotations, the OAuth token lifecycle and
 * (from Phase 8b) booking. Not split by operation — it is one vendor, one
 * credential lifecycle, one client, and §22 forbids the abstraction.
 *
 * Every failure path falls back to a flat rate rather than blocking checkout:
 * losing a sale to a courier platform's downtime is not acceptable (§11.B.6).
 */
class EasyParcelService
{
    private const PROVIDER = 'easyparcel';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $oauthUrl,
        #[SensitiveParameter] private readonly ?string $clientId,
        #[SensitiveParameter] private readonly ?string $clientSecret,
        private readonly string $weightUnit = 'kg',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.easyparcel.base_url'),
            (string) config('services.easyparcel.oauth_url'),
            IntegrationConfig::get('easyparcel.client_id'),
            IntegrationConfig::get('easyparcel.client_secret'),
            (string) config('services.easyparcel.weight_unit', 'kg'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->clientSecret);
    }

    public function isConnected(): bool
    {
        return $this->token()?->refresh_token !== null;
    }

    public function token(): ?IntegrationToken
    {
        return IntegrationToken::query()->where('provider', self::PROVIDER)->first();
    }

    // ---------------------------------------------------------------- OAuth

    /**
     * The `state` nonce is mandatory. Without it an attacker can feed the
     * callback an authorization code for THEIR EasyParcel account and the store
     * would ship on their credit (Planning §11.B.3).
     */
    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return rtrim($this->oauthUrl, '/').'/login?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
        ]);
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUri): void
    {
        $this->storeTokens($this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]));

        Log::info('EasyParcel connected');
    }

    public function disconnect(): void
    {
        IntegrationToken::query()->where('provider', self::PROVIDER)->delete();

        Log::info('EasyParcel disconnected');
    }

    /**
     * Returns a live access token, refreshing if needed.
     *
     * The refresh token ROTATES on every use, so two concurrent refreshes would
     * invalidate one another. The lock serialises them, and the row is re-read
     * INSIDE the lock — without that, the second waiter would refresh a token
     * the first already rotated (Planning §11.B.3).
     */
    public function accessToken(): ?string
    {
        $token = $this->token();

        if ($token === null || $token->refresh_token === null) {
            return null;
        }

        if (! $token->isExpired()) {
            return $token->access_token;
        }

        return Cache::lock('easyparcel:refresh', 10)->block(5, function (): ?string {
            $fresh = $this->token();

            if ($fresh === null || $fresh->refresh_token === null) {
                return null;
            }

            // Another request may have refreshed while we waited.
            if (! $fresh->isExpired()) {
                return $fresh->access_token;
            }

            try {
                $this->storeTokens($this->requestToken([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $fresh->refresh_token,
                ]));
            } catch (\Throwable $e) {
                Log::error('EasyParcel token refresh failed', ['error' => $e->getMessage()]);

                return null;
            }

            Log::info('EasyParcel token refreshed and rotated');

            return $this->token()?->access_token;
        });
    }

    private function requestToken(array $payload): array
    {
        $response = Http::connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->withBasicAuth((string) $this->clientId, (string) $this->clientSecret)
            ->asForm()
            ->post(rtrim($this->oauthUrl, '/').'/token', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Token endpoint returned HTTP '.$response->status());
        }

        $body = $response->json();

        if (! is_array($body) || ! isset($body['access_token'])) {
            throw new RuntimeException('Token endpoint returned no access token.');
        }

        return $body;
    }

    /** The NEW refresh token must be persisted — keeping the old one kills the integration. */
    private function storeTokens(array $body): void
    {
        IntegrationToken::updateOrCreate(
            ['provider' => self::PROVIDER],
            [
                'access_token' => $body['access_token'],
                'refresh_token' => $body['refresh_token'] ?? $this->token()?->refresh_token,
                'expires_at' => now()->addSeconds((int) ($body['expires_in'] ?? 36000)),
                'connected_at' => $this->token()?->connected_at ?? now(),
            ],
        );
    }

    /**
     * A live quotation using the store's own pickup origin.
     *
     * A real end-to-end check: it exercises the stored credentials, the OAuth
     * token (refreshing it if needed) and the quotation endpoint.
     *
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'summary' => 'No client ID or client secret is set.'];
        }

        if (! $this->isConnected()) {
            return ['ok' => false, 'summary' => 'Not connected yet — authorise EasyParcel first.'];
        }

        if ($this->accessToken() === null) {
            return ['ok' => false, 'summary' => 'The stored token could not be refreshed. Reconnect.'];
        }

        try {
            // A fixed, well-known destination so the result depends on the
            // connection rather than on whatever is in the cart.
            $quotes = $this->requestQuotations('11900', 'MY-07', 1000);
        } catch (\Throwable $e) {
            return ['ok' => false, 'summary' => 'Quotation failed: '.$e->getMessage()];
        }

        if ($quotes === []) {
            return [
                'ok' => false,
                'summary' => 'Connected, but no couriers were returned for the test route. '
                    .'Check the pickup origin in Settings.',
            ];
        }

        $cheapest = $quotes[0];

        return [
            'ok' => true,
            'summary' => count($quotes).' courier option(s) returned for a 1 kg test parcel.',
            'endpoint' => rtrim($this->baseUrl, '/').'/shipment/quotations',
            'detail' => 'Cheapest: '.$cheapest->label().' at '
                .Money::displayGrouped($cheapest->priceMinor, config('shop.currency_symbol')),
        ];
    }

    // ----------------------------------------------------------- Quotations

    /**
     * @return array<int, ShippingQuote> never empty — falls back to the flat rate
     */
    public function quote(string $postcode, string $state, int $weightG): array
    {
        try {
            $quotes = $this->requestQuotations($postcode, $state, $weightG);

            if ($quotes !== []) {
                return $quotes;
            }

            Log::warning('EasyParcel returned no quotations; using flat rate', [
                'postcode' => $postcode, 'state' => $state,
            ]);
        } catch (\Throwable $e) {
            Log::warning('EasyParcel quotation failed; using flat rate', ['error' => $e->getMessage()]);
        }

        return [$this->flatQuote()];
    }

    /**
     * Real courier services only — for the BOOKING screen.
     *
     * quote() never comes back empty: it falls back to the flat rate so a
     * customer always sees a price. That fallback is worse than useless here,
     * because 'flat' is not a service EasyParcel can book. An empty array is
     * the honest answer, and the screen says so rather than offering a service
     * that would be rejected.
     *
     * @return array<int, ShippingQuote>
     */
    public function courierQuotes(string $postcode, string $state, int $weightG): array
    {
        try {
            return array_values(array_filter(
                $this->requestQuotations($postcode, $state, $weightG),
                fn (ShippingQuote $q): bool => ! $q->isFlat(),
            ));
        } catch (\Throwable $e) {
            Log::warning('Courier quotation failed for booking', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function flatQuote(): ShippingQuote
    {
        return ShippingQuote::flat(Setting::getInt('flat_shipping_fee_minor', 1000));
    }

    /**
     * Submit ONE shipment order. Planning §11.B.5, REQ-013.
     *
     * ⚠ In the 2026-06 API this single call also PAYS — the response carries
     * `total_paid_amount`. There is no separate pay step to hold back on, so
     * every safeguard has to sit around this one request:
     *
     *  - No retry. `client()` retries once; that is right for a read-only
     *    quotation and catastrophic here. A retried submit is a second charge.
     *  - A timeout or an unreadable body raises ShipmentOutcomeUnknown, never
     *    a plain failure, because the request may have been processed after we
     *    stopped listening.
     *  - A 200 can still carry a per-shipment `"status": "error"`; the
     *    documented error example is exactly that.
     *
     * @param  array<string, mixed>  $payload  from ShipmentPayload::for()
     * @return array<string, mixed> the one successful shipment object
     *
     * @throws ShipmentOutcomeUnknown when it is not known whether credit was spent
     * @throws ShipmentBookingFailed when nothing was charged
     */
    public function submitOrder(array $payload): array
    {
        $accessToken = $this->accessToken();

        if ($accessToken === null) {
            throw new ShipmentBookingFailed('EasyParcel is not connected.');
        }

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->acceptJson()
                ->withToken($accessToken)
                ->post(rtrim($this->baseUrl, '/').'/shipment/submit_orders', $payload);
        } catch (ConnectionException $e) {
            // The request may have reached the courier and been processed.
            throw new ShipmentOutcomeUnknown('EasyParcel did not answer in time: '.$e->getMessage());
        }

        if ($response->serverError()) {
            throw new ShipmentOutcomeUnknown('EasyParcel returned HTTP '.$response->status().'.');
        }

        if (! $response->successful()) {
            throw new ShipmentBookingFailed('EasyParcel rejected the booking (HTTP '.$response->status().').');
        }

        if (! json_validate($response->body())) {
            // A 200 with an unreadable body could still be a processed order.
            throw new ShipmentOutcomeUnknown('EasyParcel returned a non-JSON body.');
        }

        return $this->firstShipment((array) $response->json());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function firstShipment(array $body): array
    {
        $shipment = (array) (data_get($body, 'data.0.shipments.0') ?? []);

        if ($shipment === []) {
            throw new ShipmentBookingFailed(
                'EasyParcel accepted the request but returned no shipment. '
                .(string) ($body['message'] ?? '')
            );
        }

        if (($shipment['status'] ?? null) !== 'success') {
            // Documented: a 200 whose message reads "1 request success, 1
            // request error". Nothing was charged for THIS parcel.
            throw new ShipmentBookingFailed(
                'EasyParcel rejected this parcel: '
                .(string) ($shipment['remarks'] ?? $shipment['message'] ?? $body['message'] ?? 'no reason given')
            );
        }

        // The order number identifies the batch this parcel was billed under —
        // it is what an admin searches for in the EasyParcel dashboard when
        // reconciling, so it travels with the shipment.
        $shipment['_order_number'] = data_get($body, 'data.0.order_details.order_number');

        return $shipment;
    }

    /** @return array<int, ShippingQuote> */
    private function requestQuotations(string $postcode, string $state, int $weightG): array
    {
        $accessToken = $this->accessToken();

        if ($accessToken === null) {
            throw new RuntimeException('EasyParcel is not connected.');
        }

        $response = $this->client()
            ->withToken($accessToken)
            ->post(rtrim($this->baseUrl, '/').'/shipment/quotations', [
                'shipment' => [[
                    'sender' => [
                        'postcode' => (string) Setting::get('pickup_postcode'),
                        // ISO 3166-2, never a free-text state name (§11.B.1).
                        'subdivision_code' => (string) Setting::get('pickup_state'),
                        'country' => (string) Setting::get('pickup_country', 'MY'),
                    ],
                    'receiver' => [
                        'postcode' => $postcode,
                        'subdivision_code' => $state,
                        'country' => 'MY',
                    ],
                    'weight' => $this->weightForApi($weightG),
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Quotation endpoint returned HTTP '.$response->status());
        }

        if (! json_validate($response->body())) {
            throw new RuntimeException('Quotation endpoint returned a non-JSON body.');
        }

        return $this->parseQuotations($response->json());
    }

    /** @return array<int, ShippingQuote> */
    private function parseQuotations(mixed $body): array
    {
        $quotes = [];

        foreach ((array) ($body['data'] ?? []) as $shipment) {
            foreach ((array) ($shipment['quotations'] ?? []) as $row) {
                $courier = (array) ($row['courier'] ?? []);
                $pricing = (array) ($row['pricing'] ?? []);
                $serviceId = $courier['service_id'] ?? null;
                $amount = $pricing['total_amount'] ?? null;

                if ($serviceId === null || $amount === null) {
                    continue;
                }

                try {
                    // total_amount is a DECIMAL STRING in pricing.currency, not
                    // minor units. Converted to sen exactly once, here.
                    $priceMinor = Money::fromDecimalString((string) $amount);
                } catch (\InvalidArgumentException) {
                    continue;
                }

                $quotes[] = new ShippingQuote(
                    serviceId: (string) $serviceId,
                    courierName: (string) ($courier['courier_name'] ?? 'Courier'),
                    serviceName: (string) ($courier['service_name'] ?? ''),
                    priceMinor: $priceMinor,
                    // null in the spec's own example — treated as optional.
                    deliveryDuration: isset($row['delivery_duration']) ? (string) $row['delivery_duration'] : null,
                );
            }
        }

        usort($quotes, fn (ShippingQuote $a, ShippingQuote $b): int => $a->priceMinor <=> $b->priceMinor);

        return $quotes;
    }

    /**
     * KG — confirmed against the official quotation reference on 2026-08-27
     * ("Parcel weight in KG"), which closed OQ-13. EASYPARCEL_WEIGHT_UNIT is
     * kept only as an escape hatch if a future API version changes it.
     */
    private function weightForApi(int $weightG): float|int
    {
        $grams = max($weightG, Setting::getInt('default_weight_g', 500));

        return $this->weightUnit === 'g' ? $grams : round($grams / 1000, 3);
    }

    private function client(): PendingRequest
    {
        return Http::connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->retry(1, 200, throw: false)
            ->acceptJson();
    }

    private function connectTimeout(): int
    {
        return (int) config('services.easyparcel.connect_timeout', 5);
    }

    private function timeout(): int
    {
        return (int) config('services.easyparcel.timeout', 10);
    }
}
