<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_strings_convert_to_minor_units_without_floating_point(): void
    {
        $this->assertSame(0, Money::toMinor('0'));
        $this->assertSame(1, Money::toMinor('0.01'));
        $this->assertSame(105, Money::toMinor('1.05'));
        $this->assertSame(1_000_099, Money::toMinor('10000.99'));
        $this->assertSame('10000.99', Money::fromMinor(1_000_099));
    }

    public function test_basis_point_percentage_is_integer_and_deterministic(): void
    {
        $this->assertSame(1500, Money::toMinor('15.00'));
        $this->assertSame(15000, Money::percentage(100000, 1500));
        $this->assertSame(333, Money::percentage(1000, 3333));
    }

    public function test_invalid_money_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toMinor('1.234');
    }
    public function test_high_precision_provider_cost_rounds_half_up_without_float(): void
    {
        $this->assertSame(123457, Money::toMinorRounded('0.1234567', 6));
        $this->assertSame(123456, Money::toMinorRounded('0.1234564', 6));
        $this->assertSame(1, Money::toMinorRounded('0.0000005', 6));
        $this->assertSame(-1, Money::toMinorRounded('-0.0000005', 6));
    }

}
