<?php

namespace App\Support;

/**
 * The result of a server-side payment re-query — Planning §11.A.5.
 *
 * `unverified` is not an error state: it is the DELIBERATE outcome whenever the
 * gateway response cannot be positively recognised. An order in that state stays
 * pending. Guessing a field name and settling on it would risk marking an
 * unpaid order paid, which is strictly worse than failing to settle a paid one.
 */
final class PaymentVerification
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const UNVERIFIED = 'unverified';

    private function __construct(
        public readonly string $status,
        public readonly ?int $amountMinor = null,
        public readonly ?string $externalReference = null,
        public readonly ?string $providerRef = null,
        public readonly array $raw = [],
        public readonly ?string $reason = null,
    ) {}

    public static function paid(int $amountMinor, ?string $externalReference, ?string $providerRef, array $raw): self
    {
        return new self(self::PAID, $amountMinor, $externalReference, $providerRef, $raw);
    }

    public static function pending(array $raw): self
    {
        return new self(self::PENDING, raw: $raw);
    }

    public static function failed(array $raw, ?string $reason = null): self
    {
        return new self(self::FAILED, raw: $raw, reason: $reason);
    }

    public static function unverified(string $reason, array $raw = []): self
    {
        return new self(self::UNVERIFIED, raw: $raw, reason: $reason);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function isUnverified(): bool
    {
        return $this->status === self::UNVERIFIED;
    }
}
