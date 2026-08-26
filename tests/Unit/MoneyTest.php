<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Planning §12.1 — no float touches money, including at the API boundary. */
class MoneyTest extends TestCase
{
    #[Test]
    #[DataProvider('formatCases')]
    public function it_formats_sen_without_floats(int $minor, string $expected): void
    {
        $this->assertSame($expected, Money::format($minor));
    }

    public static function formatCases(): array
    {
        return [
            'zero' => [0, '0.00'],
            'sub-ringgit' => [5, '0.05'],
            'ten sen' => [10, '0.10'],
            'whole' => [3000, '30.00'],
            'with sen' => [3250, '32.50'],
            'large' => [123456789, '1234567.89'],
            'negative' => [-1250, '-12.50'],
        ];
    }

    #[Test]
    #[DataProvider('decimalStringCases')]
    public function it_converts_the_easyparcel_decimal_string_to_sen(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::fromDecimalString($input));
    }

    public static function decimalStringCases(): array
    {
        return [
            'spec example' => ['10.84', 1084],
            'whole number' => ['7', 700],
            'one decimal' => ['7.5', 750],
            'zero' => ['0', 0],
            'rounds up on third decimal' => ['10.999', 1100],
            'rounds half up' => ['1.005', 101],
            'rounds down' => ['1.004', 100],
            'whitespace tolerated' => ['  12.30  ', 1230],
            'large' => ['1234.56', 123456],
        ];
    }

    #[Test]
    public function it_rejects_anything_that_is_not_a_decimal_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('RM10.84');
    }

    #[Test]
    public function a_returned_sen_value_is_always_an_integer(): void
    {
        $this->assertIsInt(Money::fromDecimalString('10.84'));
        $this->assertIsInt(Money::lineTotal(3000, 3));
        $this->assertIsInt(Money::sum([1000, 250]));
    }

    #[Test]
    public function line_totals_stay_exact_at_quantities_that_break_floats(): void
    {
        // 0.1 * 3 in float arithmetic is 0.30000000000000004.
        $this->assertSame(30, Money::lineTotal(10, 3));
        $this->assertSame(2999997, Money::lineTotal(3, 999999));
    }

    #[Test]
    #[DataProvider('groupedCases')]
    public function it_groups_large_amounts_without_floats(int $minor, string $expected): void
    {
        $this->assertSame($expected, Money::displayGrouped($minor));
    }

    public static function groupedCases(): array
    {
        return [
            'zero' => [0, 'RM 0.00'],
            'small' => [3000, 'RM 30.00'],
            'thousands' => [2445109, 'RM 24,451.09'],
            'millions' => [210784794, 'RM 2,107,847.94'],
            'negative' => [-125050, '-RM 1,250.50'],
        ];
    }

    #[Test]
    #[DataProvider('wholeCases')]
    public function it_rounds_headline_figures_to_the_ringgit(int $minor, string $expected): void
    {
        $this->assertSame($expected, Money::displayWhole($minor));
    }

    public static function wholeCases(): array
    {
        return [
            'rounds down' => [210784749, 'RM 2,107,847'],
            'rounds up' => [210784794, 'RM 2,107,848'],
            'half rounds up' => [150, 'RM 2'],
            'zero' => [0, 'RM 0'],
        ];
    }

    #[Test]
    public function percent_change_is_null_when_there_is_no_baseline(): void
    {
        // "Up from zero" is not a percentage. Rendering one would be a
        // fabricated figure on a business dashboard.
        $this->assertNull(Money::percentChange(0, 5000));
        $this->assertNull(Money::percentChange(0, 0));
    }

    #[Test]
    public function percent_change_reports_direction_and_magnitude(): void
    {
        $this->assertSame(-100.0, Money::percentChange(5000, 0));
        $this->assertSame(100.0, Money::percentChange(5000, 10000));
        $this->assertEqualsWithDelta(-41.7, Money::percentChange(41938, 24451), 0.1);
    }

    #[Test]
    public function it_rejects_a_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::lineTotal(1000, -1);
    }
}
