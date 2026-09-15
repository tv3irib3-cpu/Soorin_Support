<?php

/*
| تیکت‌های پشتیبانی — چرخه وضعیت، گفتگو، یادداشت داخلی
*/

return [
    'label'     => 'تیکت',
    'plural'    => 'تیکت‌ها',
    'nav_group' => 'پشتیبانی',

    // تفکیکِ ورودی (ساختهٔ مشتری) / خروجی (ساختهٔ پشتیبان)
    'incoming'        => 'تیکت‌های ورودی',
    'incoming_label'  => 'تیکت ورودی',
    'outgoing'        => 'تیکت‌های خروجی',
    'outgoing_label'  => 'تیکت خروجی',
    'create_outgoing' => 'ایجاد تیکت خروجی',

    'number'        => 'شماره تیکت',
    'created_at'    => 'تاریخ ثبت',
    'last_message_at' => 'آخرین پیام',
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
    'method_hint' => 'چطور مشکل حل شد؟ می‌توانی چند مورد را با هم انتخاب کنی.',
    'methods'   => [
        'remote' => 'ریموت',
        'onsite' => 'حضوری',
        'phone'  => 'تلفنی',
        'chat'   => 'چت',
    ],
    'resolve_action' => 'مشکل حل شد',
    'resolved_done'  => 'تیکت «حل‌شده» شد.',

    'priority'      => 'اولویت',
    'priorities'    => [
        'low'      => 'کم',
        'normal'   => 'عادی',
        'high'     => 'زیاد',
        'critical' => 'بحرانی',
    ],

    'date'      => 'تاریخ',
    'status'    => 'وضعیت',
    'statuses'  => [
        'new'              => 'جدید',
        'in_progress'      => 'در حال بررسی',
        'waiting_customer' => 'منتظر پاسخ مشتری',
        'waiting_support'  => 'در انتظار پاسخ پشتیبان',
        'waiting_payment'  => 'منتظر پرداخت',
        'resolved'         => 'حل‌شده',
        'cancelled'        => 'لغوشده',
    ],

    'assigned_to'   => 'کارشناس مسئول',
    'unassigned'    => 'تخصیص داده نشده',
    'created_by'    => 'ثبت‌کننده',
    'creator'       => 'سازنده تیکت',
    'work_minutes'  => 'زمان کارکرد (دقیقه)',
    'work_minutes_hint' => 'مجموعِ زمانی که صرف این تیکت شده. در «گزارش‌ها» برای محاسبهٔ کارکرد هر کارشناس و هر مشتری استفاده می‌شود.',
    'reply_work_minutes' => 'زمان کارکرد این پاسخ (دقیقه)',
    'reply_work_minutes_hint' => 'چند دقیقه صرفِ این پاسخ/اقدام شد؟ به مجموعِ کارکردِ تیکت اضافه می‌شود. اگر صفر بود، ۰ بگذار.',
    'resolution'    => 'شرح راه‌حل',
    'first_response_at' => 'زمان اولین پاسخ',
    'resolved_at'   => 'زمان حل',
    'closed_at'     => 'تاریخ بسته شدن',
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
    'messages_count'    => 'پیام',
    'reset_rating'         => 'نظرخواهی مجدد',
    'reset_rating_confirm' => 'نظر و امتیازِ فعلیِ مشتری پاک می‌شود تا دوباره امکانِ ثبتِ نظر داشته باشد. ادامه می‌دهید؟',
    'reset_rating_done'    => 'نظر پاک شد؛ اکنون مشتری می‌تواند دوباره امتیاز دهد.',
    'attach_hint'       => 'می‌توانید عکس، فیلم یا PDF پیوست کنید (هر فایل تا ۵۰ مگابایت).',
    'attachments'       => 'پیوست‌ها',
    'unread'            => 'خوانده‌نشده',

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

    // SLA
    'sla_breached'      => 'پاسخ معطل',
    'sla_breached_hint' => 'مهلت پاسخ تعهدشده قرارداد گذشته و هنوز کارشناسی پاسخ نداده است.',
    'sla_deadline'      => 'مهلت پاسخ',
    'sla_ok'            => 'در مهلت',
];
