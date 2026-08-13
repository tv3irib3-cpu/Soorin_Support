<?php

/*
| تیکت‌های پشتیبانی — چرخه وضعیت، گفتگو، یادداشت داخلی
*/

return [
    'label'     => 'تیکت',
    'plural'    => 'تیکت‌ها',
    'nav_group' => 'پشتیبانی',

    'number'        => 'شماره تیکت',
    'subject'       => 'موضوع',
    'description'   => 'شرح مشکل',
    'customer'      => 'مشتری',
    'category'      => 'دسته‌بندی',
    'parent_category' => 'دسته اصلی',
    'child_category'  => 'زیردسته',
    'contract'      => 'قرارداد',
    'project'       => 'پروژه',
    'system'        => 'سامانه مرتبط',
    'system_name'   => 'نام سامانه',

    'service_type'  => 'نوع خدمت',
    'service_types' => [
        'software' => 'نرم‌افزاری',
        'hardware' => 'سخت‌افزاری',
    ],

    'method'    => 'روش انجام',
    'methods'   => [
        'phone'  => 'تلفنی',
        'remote' => 'ریموت',
        'onsite' => 'حضوری',
    ],

    'priority'      => 'اولویت',
    'priorities'    => [
        'low'      => 'کم',
        'normal'   => 'عادی',
        'high'     => 'زیاد',
        'critical' => 'بحرانی',
    ],

    'status'    => 'وضعیت',
    'statuses'  => [
        'new'              => 'جدید',
        'in_progress'      => 'در حال بررسی',
        'waiting_customer' => 'منتظر پاسخ مشتری',
        'waiting_payment'  => 'منتظر پرداخت',
        'resolved'         => 'حل‌شده',
        'closed'           => 'بسته‌شده',
        'cancelled'        => 'لغوشده',
    ],

    'assigned_to'   => 'کارشناس مسئول',
    'unassigned'    => 'تخصیص داده نشده',
    'created_by'    => 'ثبت‌کننده',
    'work_minutes'  => 'زمان کارکرد (دقیقه)',
    'resolution'    => 'شرح راه‌حل',
    'first_response_at' => 'زمان اولین پاسخ',
    'resolved_at'   => 'زمان حل',
    'closed_at'     => 'زمان بستن',
    'is_locked'     => 'قفل‌شده',

    // گفتگو
    'conversation'      => 'گفتگو',
    'messages'          => 'پیام‌ها',
    'reply'             => 'پاسخ',
    'reply_placeholder' => 'پاسخ خود را بنویسید…',
    'internal_note'     => 'یادداشت داخلی',
    'internal_note_hint'=> 'یادداشت داخلی برای مشتری نمایش داده نمی‌شود.',
    'is_internal'       => 'یادداشت داخلی',
    'no_messages'       => 'هنوز پیامی ثبت نشده است.',

    // اقدام‌ها و پیام‌ها
    'change_status'     => 'تغییر وضعیت',
    'status_history'    => 'تاریخچه وضعیت',
    'status_changed'    => 'وضعیت تیکت از :from به :to تغییر کرد.',
    'assign'            => 'تخصیص کارشناس',
    'create_invoice'    => 'صدور فاکتور',
    'locked_notice'     => 'این تیکت بسته و قفل شده است. امکان ویرایش یا افزودن پیام وجود ندارد.',
    'invalid_transition'=> 'تغییر وضعیت از «:from» به «:to» مجاز نیست.',
    'rating'            => 'امتیاز رضایت',
    'rating_comment'    => 'نظر مشتری',

    // حالت خالی
    'empty_heading'     => 'تیکتی وجود ندارد',
    'empty_body'        => 'هنوز هیچ تیکتی ثبت نشده است. اولین تیکت را ثبت کنید.',
    'empty_portal'      => 'شما هنوز تیکتی ثبت نکرده‌اید.',

    // دسته‌بندی
    'categories'        => 'دسته‌بندی تیکت',
    'category_parent'   => 'دسته والد',
    'category_hint'     => 'دسته‌بندی دولایه است: ابتدا دسته اصلی (مثلاً سخت‌افزار) و سپس زیردسته (مثلاً هارد).',
    'sort_order'        => 'ترتیب نمایش',
];
