<?php

namespace App\Support;

/**
 * تحلیل‌گرِ سبکِ User-Agent — بدونِ هیچ وابستگیِ خارجی (بستهٔ composer)، چون
 * بسته‌های به‌روزرسانیِ سامانه فقط کد هستند و روی هاست `composer install`
 * اجرا نمی‌شود. مرورگر، سیستم‌عامل و نوعِ دستگاه را با تطبیقِ الگو تشخیص می‌دهد.
 *
 * هدف پوششِ عمدهٔ کاربران است، نه صد‌درصدِ رشته‌های ممکن؛ ناشناخته‌ها به
 * «نامشخص» برمی‌گردند.
 */
class UserAgent
{
    /**
     * @return array{browser: ?string, platform: ?string, device: string, is_robot: bool}
     */
    public static function parse(?string $ua): array
    {
        $ua = trim((string) $ua);

        if ($ua === '') {
            return ['browser' => null, 'platform' => null, 'device' => 'unknown', 'is_robot' => false];
        }

        return [
            'browser'  => self::browser($ua),
            'platform' => self::platform($ua),
            'device'   => self::device($ua),
            'is_robot' => self::isRobot($ua),
        ];
    }

    /** نامِ مرورگر + نسخهٔ اصلی (مثلاً «Chrome 128»). ترتیب مهم است: خاص قبل از عام. */
    public static function browser(string $ua): ?string
    {
        // نگاشتِ [برچسبِ نمایشی => الگوی نام در UA]. نسخه از گروهِ عددیِ بعدش خوانده می‌شود.
        $map = [
            'Edge'              => 'Edg(?:e|A|iOS)?',
            'Samsung Internet'  => 'SamsungBrowser',
            'Opera'             => 'OPR|Opera',
            'Yandex'            => 'YaBrowser',
            'Brave'             => 'Brave',
            'Vivaldi'           => 'Vivaldi',
            'UC Browser'        => 'UCBrowser',
            'Firefox'           => 'Firefox|FxiOS',
            'Chrome'            => 'CriOS|Chrome|Chromium',
            'Safari'            => 'Version',   // سافاری نسخه را در Version/x می‌گذارد و بعد Safari دارد
            'Internet Explorer' => 'MSIE|Trident',
        ];

        foreach ($map as $label => $pattern) {
            // سافاری فقط وقتی که کروم/کروم‌بیس نباشد
            if ($label === 'Safari' && ! preg_match('#Safari#i', $ua)) {
                continue;
            }

            if (preg_match('#(?:' . $pattern . ')[/ ]?(\d+)?#i', $ua, $m)) {
                return isset($m[1]) && $m[1] !== '' ? $label . ' ' . $m[1] : $label;
            }
        }

        return null;
    }

    /** سیستم‌عاملِ کاربر. */
    public static function platform(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('#Windows NT 10\.0#i', $ua)          => 'Windows 10/11',
            (bool) preg_match('#Windows NT 6\.3#i', $ua)           => 'Windows 8.1',
            (bool) preg_match('#Windows NT 6\.2#i', $ua)           => 'Windows 8',
            (bool) preg_match('#Windows NT 6\.1#i', $ua)           => 'Windows 7',
            (bool) preg_match('#Windows#i', $ua)                   => 'Windows',
            (bool) preg_match('#iPhone|iPad|iPod#i', $ua)          => 'iOS',
            (bool) preg_match('#Android#i', $ua)                   => 'Android',
            (bool) preg_match('#Mac OS X|Macintosh#i', $ua)        => 'macOS',
            (bool) preg_match('#CrOS#i', $ua)                      => 'ChromeOS',
            (bool) preg_match('#Ubuntu#i', $ua)                    => 'Ubuntu',
            (bool) preg_match('#Linux#i', $ua)                     => 'Linux',
            default                                                => null,
        };
    }

    /** نوعِ دستگاه: mobile | tablet | desktop | bot | unknown. */
    public static function device(string $ua): string
    {
        if (self::isRobot($ua)) {
            return 'bot';
        }

        if (preg_match('#iPad|Tablet|PlayBook|Nexus (?:7|9|10)#i', $ua)
            || (preg_match('#Android#i', $ua) && ! preg_match('#Mobile#i', $ua))) {
            return 'tablet';
        }

        if (preg_match('#Mobile|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile#i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /** آیا خزندهٔ موتورِ جست‌وجو یا ربات است؟ */
    public static function isRobot(string $ua): bool
    {
        return (bool) preg_match(
            '#bot|crawl|slurp|spider|mediapartners|facebookexternalhit|WhatsApp|Telegram|curl|wget|python-requests|Go-http|HeadlessChrome#i',
            $ua
        );
    }
}
