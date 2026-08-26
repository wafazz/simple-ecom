<?php

namespace App\Services;

use App\Models\Order;
use App\Support\IntegrationConfig;
use App\Support\Money;
use App\Support\PaymentVerification;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SensitiveParameter;

/**
 * REQ-005 — Planning §11.A.
 *
 * ⚠ FAILS CLOSED BY DESIGN. The exact field names of the getBillTransactions
 * response are `NEEDS VERIFICATION` (Planning §11.A.6, OQ-11): the official
 * reference returns 403 to automated fetch and the community sources disagree.
 *
 * Rather than guess one name, this reads a set of documented candidates and
 * returns `unverified` when none is present. An unverified result leaves the
 * order pending. That is the intended trade-off — refusing to settle a real
 * payment is recoverable; marking an unpaid order paid is not.
 */
class ToyyibPayService
{
    /** Candidate keys, most-documented first. Absence => unverified. */
    private const STATUS_KEYS = ['billpaymentStatus', 'billPaymentStatus', 'status'];

    private const AMOUNT_KEYS = ['billpaymentAmount', 'billPaymentAmount', 'amount'];

    private const REFERENCE_KEYS = ['billExternalReferenceNo', 'billexternalreferenceno', 'order_id'];

    private const PROVIDER_REF_KEYS = ['billpaymentInvoiceNo', 'refno', 'billpaymentTransactionId', 'transaction_id'];

    public function __construct(
        private readonly string $baseUrl,
        #[SensitiveParameter] private readonly ?string $secretKey,
        #[SensitiveParameter] private readonly ?string $categoryCode,
        private readonly string $amountFormat = 'decimal',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.toyyibpay.base_url'),
            // Admin-set value wins over .env (Planning §5.4).
            IntegrationConfig::get('toyyibpay.secret_key'),
            IntegrationConfig::get('toyyibpay.category_code'),
            (string) config('services.toyyibpay.amount_format', 'decimal'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey) && filled($this->categoryCode);
    }

    /**
     * Creates the bill and returns the bill code.
     *
     * billAmount is in CENTS, which is exactly how we store money — the grand
     * total passes straight through with no float conversion (Planning §11.A.3).
     */
    public function createBill(Order $order, string $returnUrl, string $callbackUrl): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ToyyibPay is not configured.');
        }

        $response = $this->client()->asForm()->post($this->endpoint('createBill'), [
            'userSecretKey' => $this->secretKey,
            'categoryCode' => $this->categoryCode,
            // Hard gateway limits: 30 and 100 characters.
            'billName' => mb_substr($order->order_no, 0, 30),
            'billDescription' => mb_substr($this->describe($order), 0, 100),
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $order->grand_total_minor,
            'billReturnUrl' => $returnUrl,
            'billCallbackUrl' => $callbackUrl,
            'billExternalReferenceNo' => $order->order_no,
            'billTo' => $order->customer_name,
            'billEmail' => $order->customer_email,
            'billPhone' => $order->customer_phone,
        ]);

        $body = $this->decode($response->body());
        $billCode = $this->firstString($body[0] ?? $body, ['BillCode', 'billcode', 'billCode']);

        if ($billCode === null) {
            Log::error('ToyyibPay createBill returned no bill code', [
                'order_no' => $order->order_no,
                'http_status' => $response->status(),
            ]);

            throw new RuntimeException('ToyyibPay did not return a bill code.');
        }

        Log::info('ToyyibPay bill created', ['order_no' => $order->order_no, 'bill_code' => $billCode]);

        return $billCode;
    }

    public function paymentUrl(string $billCode): string
    {
        return rtrim($this->baseUrl, '/').'/'.$billCode;
    }

    /**
     * Re-queries the gateway. NEVER trusts the callback or return payload —
     * only the bill code is taken from them (Planning §11.A.5).
     */
    public function verifyPayment(string $billCode): PaymentVerification
    {
        if (! $this->isConfigured()) {
            return PaymentVerification::unverified('ToyyibPay is not configured.');
        }

        try {
            $response = $this->client()->asForm()->post($this->endpoint('getBillTransactions'), [
                'userSecretKey' => $this->secretKey,
                'billCode' => $billCode,
            ]);
        } catch (\Throwable $e) {
            // A network failure is not evidence of non-payment.
            return PaymentVerification::unverified('Gateway unreachable: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return PaymentVerification::unverified('Gateway returned HTTP '.$response->status());
        }

        try {
            $body = $this->decode($response->body());
        } catch (RuntimeException $e) {
            return PaymentVerification::unverified($e->getMessage());
        }

        $row = $body[0] ?? $body;

        if (! is_array($row) || $row === []) {
            return PaymentVerification::unverified('Empty transaction list.', (array) $body);
        }

        $status = $this->firstString($row, self::STATUS_KEYS);

        if ($status === null) {
            // The OQ-11 case: we do not recognise the shape, so we refuse.
            return PaymentVerification::unverified(
                'No recognised status field. Confirm the response shape (Planning §11.A.6).',
                $row
            );
        }

        return match ($status) {
            '1' => $this->paidResult($row),
            '2', '4' => PaymentVerification::pending($row),
            '3' => PaymentVerification::failed($row, $this->firstString($row, ['reason', 'billpaymentReason'])),
            default => PaymentVerification::unverified("Unrecognised status '{$status}'.", $row),
        };
    }

    private function paidResult(array $row): PaymentVerification
    {
        $rawAmount = $this->firstString($row, self::AMOUNT_KEYS);

        if ($rawAmount === null) {
            return PaymentVerification::unverified('Paid status with no recognised amount field.', $row);
        }

        $amountMinor = $this->toMinor($rawAmount);

        if ($amountMinor === null) {
            return PaymentVerification::unverified("Unparseable amount '{$rawAmount}'.", $row);
        }

        return PaymentVerification::paid(
            $amountMinor,
            $this->firstString($row, self::REFERENCE_KEYS),
            $this->firstString($row, self::PROVIDER_REF_KEYS),
            $row
        );
    }

    /**
     * ⚠ The unit of the returned amount is part of OQ-11. `decimal` ("10.00" =
     * RM10) is the documented reading; `cents` is configurable for when the
     * live response is confirmed. A wrong setting causes an amount MISMATCH,
     * which refuses to settle — it never over- or under-charges silently.
     */
    private function toMinor(string $amount): ?int
    {
        try {
            return $this->amountFormat === 'cents'
                ? (int) $amount
                : Money::fromDecimalString($amount);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** Both interpretations, so a mismatch log tells the operator which is right. */
    public function describeAmountInterpretations(string $amount): array
    {
        $decimal = null;

        try {
            $decimal = Money::fromDecimalString($amount);
        } catch (\InvalidArgumentException) {
        }

        return ['as_decimal_minor' => $decimal, 'as_cents_minor' => (int) $amount];
    }

    private function describe(Order $order): string
    {
        $count = $order->items()->count();

        return "Order {$order->order_no} — {$count} item".($count === 1 ? '' : 's');
    }

    private function client(): PendingRequest
    {
        return Http::connectTimeout((int) config('services.toyyibpay.connect_timeout', 5))
            ->timeout((int) config('services.toyyibpay.timeout', 10))
            ->retry(1, 200, throw: false);
    }

    private function endpoint(string $action): string
    {
        return rtrim($this->baseUrl, '/').'/index.php/api/'.$action;
    }

    /** json_validate() first (PHP 8.3), so an HTML error page is a clean refusal. */
    private function decode(string $body): array
    {
        if (! json_validate($body)) {
            throw new RuntimeException('Gateway returned a non-JSON body.');
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return null;
    }
}
