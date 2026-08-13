<?php

/*
|--------------------------------------------------------------------------
| هویت برند — شرکت دریاپردازشگران سورین طبرستان
|--------------------------------------------------------------------------
| این مقادیر در فوتر، سربرگ فاکتور PDF، عنوان مرورگر و ایمیل‌ها استفاده
| می‌شوند. مقادیر قابل تغییر توسط مدیر، از جدول settings خوانده می‌شوند و
| این فایل فقط مقدار پیش‌فرض را تعیین می‌کند.
*/

return [
    'company' => [
        'name'         => env('COMPANY_NAME', 'شرکت دریاپردازشگران سورین طبرستان'),
        'name_en'      => env('COMPANY_NAME_EN', 'Soorin Tabarestan'),
        'website'      => env('COMPANY_WEBSITE', 'https://dpst.ir'),
        'website_label'=> 'dpst.ir',
        'founded_year' => (int) env('COMPANY_FOUNDED_YEAR', 1400),
        'phone'        => env('COMPANY_PHONE'),
        'address'      => env('COMPANY_ADDRESS'),
    ],

    'app' => [
        'title'   => 'سامانه خدمات و پشتیبانی',
        'version' => '0.2.0',        // نسخه سامانه — در فوتر نمایش داده می‌شود
    ],

    'logo' => [
        'light'  => 'images/logo-navy.svg',   // روی پس‌زمینه روشن و فاکتور
        'dark'   => 'images/logo-white.svg',  // روی منوی سرمه‌ای و تم شب
        'mark'   => 'images/logo-mark.svg',   // نشان مربعی — favicon و منوی جمع‌شده
    ],

    /*
    | پالت رنگ — تم اصلی «آبی نفتی و فیروزه‌ای» و تم «شب»
    | سرمه‌ای منو با سرمه‌ای لوگو یکسان است تا لوگو بدون حاشیه بنشیند.
    */
    'themes' => [
        'ocean' => [
            'label'      => 'آبی نفتی',
            'nav'        => '#0f2d4d',
            'nav_text'   => '#93b4c9',
            'nav_active' => '#14b8a6',
            'background' => '#eef4f6',
            'card'       => '#ffffff',
            'border'     => '#dde8ec',
            'text'       => '#0b2b3f',
            'muted'      => '#5f7d8c',
            'accent'     => '#14b8a6',
            'accent_soft'=> '#ccfbf1',
            'accent_text'=> '#0f766e',
        ],
        'night' => [
            'label'      => 'شب',
            'nav'        => '#0b1220',
            'nav_text'   => '#7b8ca3',
            'nav_active' => '#34d399',
            'background' => '#111a2b',
            'card'       => '#182338',
            'border'     => '#25314a',
            'text'       => '#e6edf7',
            'muted'      => '#8ea0b8',
            'accent'     => '#34d399',
            'accent_soft'=> '#12372c',
            'accent_text'=> '#6ee7b7',
        ],
    ],

    'default_theme' => 'ocean',
];
