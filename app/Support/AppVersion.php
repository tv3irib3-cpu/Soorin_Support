<?php

namespace App\Support;

/**
 * نسخهٔ فعلی برنامه — از فایل VERSION در ریشهٔ پروژه خوانده می‌شود.
 *
 * با هر تغییر برنامه این فایل یک پله بالا می‌رود (۱٫۰٫۰ ← ۱٫۰٫۱ …)؛ نصب‌های
 * زنده با مقایسهٔ همین عدد می‌فهمند نسخهٔ جدید آمده یا نه.
 */
class AppVersion
{
    public static function current(): string
    {
        $file = base_path('VERSION');

        if (is_file($file)) {
            $value = trim((string) file_get_contents($file));

            if ($value !== '') {
                return $value;
            }
        }

        return '0.0.0';
    }

    /** آیا این استقرار یک مخزن گیت است (پس به‌روزرسانی از گیت‌هاب ممکن است)؟ */
    public static function isGitRepo(): bool
    {
        return is_dir(base_path('.git'));
    }

    /**
     * آیا اجرای دستورِ سیستمی (git/composer/شل/SSL-helper) ممکن است؟
     *
     * روی هاستِ اشتراکی معمولاً `proc_open` غیرفعال است؛ پس هر قابلیتی که به شل نیاز
     * دارد باید پیش از تلاش این را چک کند تا به‌جای خطای زشت، پیامِ مناسب بدهد.
     */
    public static function hasShell(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('proc_open', $disabled, true);
    }
}
