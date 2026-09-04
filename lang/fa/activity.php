<?php

/*
| تاریخچه تغییرات — چه کسی، کِی، چه چیزی را تغییر داد.
*/

return [
    'label'      => 'رویداد',
    'plural'     => 'تاریخچه تغییرات',
    'nav_group'  => 'سامانه',

    'user'         => 'کاربر',
    'action'       => 'اقدام',
    'subject_type' => 'نوع مورد',
    'subject_id'   => 'شناسه',
    'changes'      => 'جزئیات',
    'ip_address'   => 'آی‌پی',
    'date'         => 'تاریخ',

    'system' => 'سامانه',

    'actions' => [
        'created'        => 'ایجاد',
        'updated'        => 'ویرایش',
        'deleted'        => 'حذف',
        'login'          => 'ورود',
        'status_changed' => 'تغییر وضعیت',
        'assigned'       => 'تخصیص کارشناس',
        'portal_reply'   => 'پاسخ در پرتال',
        'backup_created' => 'ساخت پشتیبان',
        'backup_restored'=> 'بازیابی پشتیبان',
        'backup_deleted' => 'حذف پشتیبان',
    ],

    'subjects' => [
        'App\\Models\\Ticket'   => 'تیکت',
        'App\\Models\\Invoice'  => 'فاکتور',
        'App\\Models\\Customer' => 'مشتری',
        'App\\Models\\User'     => 'کاربر',
        'App\\Models\\Contract' => 'قرارداد',
        'App\\Models\\Payment'  => 'پرداخت',
    ],

    'empty_heading' => 'هنوز رویدادی ثبت نشده',
];
