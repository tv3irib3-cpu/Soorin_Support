<?php

/*
| نسخهٔ اپ‌های موبایل و آدرسِ APK — برای بررسیِ به‌روزرسانیِ درون‌اپ.
|
| اپ هنگامِ باز شدن از /api/app-version می‌پرسد. اگر «version» از نسخهٔ نصب‌شده
| بالاتر باشد، پیامِ «نسخهٔ جدید» می‌دهد؛ اگر نسخهٔ اپ از «min_supported» پایین‌تر
| باشد، آپدیت اجباری می‌شود (تا تغییرِ API اپِ قدیمی را خراب نکند).
|
| APK را GitHub Actions می‌سازد و در «آخرین Release» می‌گذارد؛ آدرسِ latest همیشه
| به تازه‌ترین فایل اشاره می‌کند.
*/

$repo = 'tv3irib3-cpu/Soorin_Support';

return [
    'support' => [
        'version'       => '1.0.2',
        'min_supported' => '1.0.0',
        'apk_url'       => "https://github.com/{$repo}/releases/latest/download/soorin-support.apk",
        'notes'         => 'مشتریان، جستجوی تیکت، جزئیات و ثبتِ پرداختِ فاکتور اضافه شد.',
    ],

    'portal' => [
        'version'       => '1.0.2',
        'min_supported' => '1.0.0',
        'apk_url'       => "https://github.com/{$repo}/releases/latest/download/soorin-portal.apk",
        'notes'         => 'امضای پایدار برای به‌روزرسانیِ بی‌دردسرِ نسخه‌های بعد.',
    ],
];
