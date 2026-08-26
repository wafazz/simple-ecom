<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\ShippingRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The weight table — REQ-006.
 *
 * This is what every customer is charged for delivery, so the arithmetic is
 * pinned exactly rather than sampled. The rounding rule is the part worth
 * guarding: a courier bills the next whole kilo, and charging the exact
 * fraction instead would quietly lose money on every part-kilo parcel.
 */
class ShippingRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('ship_west_first_minor', '800');   // RM8.00
        Setting::put('ship_west_next_minor', '300');    // RM3.00
        Setting::put('ship_east_first_minor', '1500');  // RM15.00
        Setting::put('ship_east_next_minor', '1200');   // RM12.00
    }

    /** @return array<string, array{int, int}> */
    public static function westWeights(): array
    {
        return [
            'a featherweight parcel still pays for one kilo' => [50, 800],
            '400 g rounds up to 1 kg' => [400, 800],
            'exactly 1 kg is one kilo, not two' => [1000, 800],
            '1 g over a kilo costs a whole extra kilo' => [1001, 1100],
            '2.3 kg rounds up to 3' => [2300, 1400],
            'exactly 5 kg' => [5000, 2000],
        ];
    }

    #[Test]
    #[DataProvider('westWeights')]
    public function west_malaysia_is_priced_by_rounded_up_kilos(int $weightG, int $expectedMinor): void
    {
        $this->assertSame($expectedMinor, ShippingRate::forState('MY-14', $weightG));
    }

    #[Test]
    public function east_malaysia_uses_its_own_two_figures(): void
    {
        // 1 kg → 1500. 2 kg → 1500 + 1200. 3 kg → 1500 + 2400.
        $this->assertSame(1500, ShippingRate::forState('MY-12', 900));
        $this->assertSame(2700, ShippingRate::forState('MY-13', 1500));
        $this->assertSame(3900, ShippingRate::forState('MY-15', 2100));
    }

    #[Test]
    public function only_sabah_sarawak_and_labuan_are_east(): void
    {
        foreach (['MY-12', 'MY-13', 'MY-15'] as $code) {
            $this->assertSame(ShippingRate::ZONE_EAST, ShippingRate::zoneFor($code), $code);
        }

        // Every other state on the list, including the three federal
        // territories that are NOT Labuan.
        foreach (array_keys(config('shop.states')) as $code) {
            if (in_array($code, ShippingRate::EAST_STATES, true)) {
                continue;
            }

            $this->assertSame(ShippingRate::ZONE_WEST, ShippingRate::zoneFor($code), $code);
        }
    }

    #[Test]
    public function an_unknown_state_code_is_treated_as_west_rather_than_free(): void
    {
        // Validation should never let one through, but if one ever did, the
        // safe failure is a charge — not free delivery.
        $this->assertSame(800, ShippingRate::forState('XX-99', 500));
    }

    #[Test]
    public function the_quote_carries_the_zone_and_kilos_it_was_priced_on(): void
    {
        $quote = ShippingRate::quoteFor('MY-12', 2300);

        $this->assertSame(3900, $quote->priceMinor);      // 1500 + 2×1200
        $this->assertSame('weight-east', $quote->serviceId);
        $this->assertSame('weight', $quote->source);
        $this->assertTrue($quote->isWeightBased());
        $this->assertStringContainsString('East Malaysia', $quote->serviceName);
        $this->assertStringContainsString('3 kg', $quote->serviceName);
    }

    #[Test]
    public function the_admin_figures_are_what_is_actually_charged(): void
    {
        // The whole point of the feature: change the setting, change the price.
        Setting::put('ship_west_first_minor', '1250');
        Setting::put('ship_west_next_minor', '450');

        $this->assertSame(1250, ShippingRate::forState('MY-14', 800));
        $this->assertSame(1700, ShippingRate::forState('MY-14', 1800));
        $this->assertSame(2150, ShippingRate::forState('MY-14', 2900));
    }

    #[Test]
    public function a_zero_weight_cart_is_never_free_to_deliver(): void
    {
        $this->assertSame(1, ShippingRate::billableKilos(0));
        $this->assertSame(800, ShippingRate::forState('MY-14', 0));
    }
}
