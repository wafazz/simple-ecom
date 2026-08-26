<?php

namespace App\Support;

/** One courier option returned by a quotation — Planning §11.B.1. */
final class ShippingQuote
{
    public function __construct(
        public readonly string $serviceId,
        public readonly string $courierName,
        public readonly string $serviceName,
        public readonly int $priceMinor,
        public readonly ?string $deliveryDuration = null,
        /** 'api' when EasyParcel quoted it, 'flat' when the fallback fired. */
        public readonly string $source = 'api',
    ) {}

    public static function flat(int $priceMinor): self
    {
        return new self(
            serviceId: 'flat',
            courierName: 'Standard Delivery',
            serviceName: 'Flat rate',
            priceMinor: $priceMinor,
            source: 'flat',
        );
    }

    public function isFlat(): bool
    {
        return $this->source === 'flat';
    }

    public function label(): string
    {
        return trim($this->courierName.' — '.$this->serviceName, ' —');
    }
}
