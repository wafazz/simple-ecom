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
        /**
         * Where the price came from:
         *  'weight' — the store's own weight table (what customers are charged)
         *  'api'    — an EasyParcel quotation (used for booking cost, not price)
         *  'flat'   — the legacy single flat rate, kept for existing orders
         */
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

    /** Priced from the store's own weight table rather than a courier. */
    public function isWeightBased(): bool
    {
        return $this->source === 'weight';
    }

    public function label(): string
    {
        return trim($this->courierName.' — '.$this->serviceName, ' —');
    }
}
