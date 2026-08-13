<?php

namespace Tests\Unit;

use App\Support\Jalali;
use Carbon\Carbon;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_gregorian_date_is_displayed_as_jalali(): void
    {
        // ۱۴۰۵/۰۵/۲۲ برابر است با 2026-08-13
        $this->assertSame('۱۴۰۵/۰۵/۲۲', Jalali::format(Carbon::parse('2026-08-13')));
    }

    public function test_jalali_input_converts_back_to_gregorian(): void
    {
        $this->assertSame(
            '2026-08-13',
            Jalali::toGregorian('1405/05/22')?->format('Y-m-d'),
        );
    }

    public function test_persian_digits_in_input_are_accepted(): void
    {
        $this->assertSame(
            '2026-08-13',
            Jalali::toGregorian('۱۴۰۵/۰۵/۲۲')?->format('Y-m-d'),
        );
    }

    public function test_dash_separator_is_accepted(): void
    {
        $this->assertSame(
            '2026-08-13',
            Jalali::toGregorian('1405-05-22')?->format('Y-m-d'),
        );
    }

    public function test_round_trip_keeps_the_same_date(): void
    {
        $original  = Carbon::parse('2025-03-21');
        $jalali    = Jalali::format($original);
        $backAgain = Jalali::toGregorian(Jalali::englishDigits($jalali));

        $this->assertSame($original->format('Y-m-d'), $backAgain?->format('Y-m-d'));
    }

    public function test_invalid_input_returns_null_instead_of_crashing(): void
    {
        $this->assertNull(Jalali::toGregorian(''));
        $this->assertNull(Jalali::toGregorian('چیز نامعتبر'));
        $this->assertNull(Jalali::toGregorian('1405/13/45'));
    }

    public function test_money_is_formatted_with_persian_digits(): void
    {
        $this->assertSame('۵٬۰۰۰٬۰۰۰', Jalali::money(5_000_000));
        $this->assertSame('۰', Jalali::money(0));
    }

    public function test_null_date_returns_null(): void
    {
        $this->assertNull(Jalali::format(null));
    }
}
