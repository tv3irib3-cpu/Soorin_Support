<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * پیاده‌سازی پیش‌فرض — پیامک واقعی ارسال نمی‌کند، فقط در لاگ ثبت می‌کند.
 * تا وقتی سرویس پیامک واقعی انتخاب و در AppServiceProvider جایگزین نشده،
 * سامانه از همین استفاده می‌کند — یعنی کاملاً بی‌خطر برای تست است.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $toMobile, string $message): bool
    {
        Log::info('SMS (شبیه‌سازی‌شده — سرویس واقعی متصل نیست)', [
            'to' => $toMobile, 'message' => $message,
        ]);

        return true;
    }
}
