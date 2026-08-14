<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * تک نقطه ورودی ارسال پیامک در کل سامانه.
 *
 * کلید سوییچ روشن/خاموش از جدول settings خوانده می‌شود (نه .env) تا مدیر
 * پشتیبان بتواند از داخل پنل، بدون دسترسی به سرور، پیامک را فعال یا
 * غیرفعال کند. خطای ارسال هرگز به بیرون پرتاب نمی‌شود — یک پیامک ناموفق
 * نباید ثبت تیکت یا تغییر وضعیت را متوقف کند.
 */
class SmsService
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public static function isEnabled(): bool
    {
        return (bool) Setting::get('sms.enabled', false);
    }

    /** @param  array<string|null>  $mobiles */
    public function sendToMany(array $mobiles, string $message): void
    {
        foreach (array_unique(array_filter($mobiles)) as $mobile) {
            $this->send($mobile, $message);
        }
    }

    public function send(string $toMobile, string $message): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        try {
            return $this->gateway->send($toMobile, $message);
        } catch (Throwable $e) {
            Log::warning('ارسال پیامک ناموفق بود', ['to' => $toMobile, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
