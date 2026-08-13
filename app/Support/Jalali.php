<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Hekmatinasser\Verta\Verta;
use InvalidArgumentException;

/**
 * تبدیل و نمایش تاریخ شمسی.
 *
 * قاعده پروژه: تاریخ در دیتابیس **میلادی** ذخیره می‌شود و فقط در نمایش
 * شمسی می‌شود. هیچ‌جا تاریخ شمسی در دیتابیس نوشته نمی‌شود.
 */
class Jalali
{
    /** نمایش تاریخ: ۱۴۰۵/۰۵/۲۲ */
    public static function format(mixed $date, string $format = 'Y/m/d'): ?string
    {
        if (blank($date)) {
            return null;
        }

        return self::digits(Verta::instance($date)->format($format));
    }

    /** نمایش تاریخ و ساعت: ۱۴۰۵/۰۵/۲۲ ‏۱۴:۳۰ */
    public static function formatDateTime(mixed $date): ?string
    {
        return self::format($date, 'Y/m/d H:i');
    }

    /** نمایش با نام ماه: ۲۲ مرداد ۱۴۰۵ */
    public static function formatLong(mixed $date): ?string
    {
        return self::format($date, 'd F Y');
    }

    /** سال جاری شمسی — برای فوتر و شماره‌گذاری اسناد. */
    public static function currentYear(): int
    {
        return (int) Verta::now()->format('Y');
    }

    /** فاصله زمانی خوانا: «۳ روز پیش» */
    public static function diffForHumans(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return self::digits(Verta::instance($date)->formatDifference());
    }

    /**
     * تبدیل تاریخ شمسی واردشده توسط کاربر به میلادی.
     * ورودی می‌تواند با اعداد فارسی و جداکننده / یا - باشد.
     */
    public static function toGregorian(?string $jalaliDate): ?CarbonInterface
    {
        if (blank($jalaliDate)) {
            return null;
        }

        $normalized = str_replace(['-', '.'], '/', self::englishDigits($jalaliDate));
        $parts      = array_map('intval', explode('/', trim($normalized)));

        if (count($parts) !== 3) {
            return null;
        }

        [$year, $month, $day] = $parts;

        // createJalali روی تاریخ نامعتبر استثنا پرتاب می‌کند؛ اینجا به null تبدیل
        // می‌شود تا ورودی اشتباه کاربر باعث خطای ۵۰۰ نشود.
        // فقط استثنای اعتبارسنجی گرفته می‌شود، نه هر خطایی — وگرنه اشکال‌های
        // واقعی کد بی‌صدا پنهان می‌مانند.
        try {
            $datetime = Verta::createJalali($year, $month, $day, 0, 0, 0)->datetime();
        } catch (InvalidArgumentException) {
            return null;
        }

        return Carbon::instance($datetime);
    }

    /** تبدیل ارقام انگلیسی به فارسی — فقط برای نمایش. */
    public static function digits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                              '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /** تبدیل ارقام فارسی و عربی به انگلیسی — قبل از ذخیره در دیتابیس. */
    public static function englishDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** مبلغ ریالی با جداکننده هزارگان و ارقام فارسی: ۵٬۰۰۰٬۰۰۰ */
    public static function money(?int $amount): string
    {
        return self::digits(number_format((int) $amount, 0, '.', '٬'));
    }
}
