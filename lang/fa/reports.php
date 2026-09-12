<?php

/*
| گزارش‌ها — فاز ۲
*/

return [
    'label'      => 'گزارش‌ها',
    'nav_group'  => 'سامانه',

    'period_label' => 'بازهٔ زمانی',
    'select_year'  => 'انتخاب سال',
    'from_date'  => 'از تاریخ',
    'to_date'    => 'تا تاریخ',
    'apply'      => 'اعمال بازه',

    'presets' => [
        'this_week'     => 'این هفته',
        'this_month'    => 'این ماه',
        'last_month'    => 'ماه گذشته',
        'last_3_months' => 'سه ماه اخیر',
        'this_year'     => 'امسال',
        'last_year'     => 'سال گذشته',
        'year'          => 'سال مشخص',
        'all_time'      => 'از ابتدا تا کنون',
        'custom'        => 'بازه دلخواه',
    ],

    // کارت‌های خلاصه
    'revenue'              => 'مبلغ کل فاکتورها',
    'paid'                 => 'پرداختی مشتریان',
    'debt'                 => 'بدهی مشتریان',
    'warranty_value'       => 'ارزش خدمات رایگان (قرارداد)',
    'service_value'        => 'ارزش کل خدمات صادرشده',
    'invoice_count'        => 'تعداد فاکتور',
    'service_count'        => 'تیکت‌های حل‌شده',
    'tickets_created'      => 'تیکت‌های ثبت‌شده',
    'tickets_still_open'   => 'تیکت‌های هنوز باز',
    'work_minutes'         => 'مجموع زمان کارکرد',
    'avg_resolution_hours' => 'میانگین زمان حل (ساعت)',
    'sla_breaches'         => 'نقض تعهد پاسخ (SLA)',
    'avg_rating'           => 'میانگین رضایت',

    // بخش‌ها
    'summary_heading' => 'خلاصهٔ دوره',
    'by_customer'     => 'خدمات و تیکت هر مشتری',
    'by_project'      => 'تیکت به تفکیک پروژه',
    'by_category'     => 'آمار خرابی به تفکیک دسته‌بندی',
    'by_status'       => 'توزیع تیکت بر اساس وضعیت',
    'by_priority'     => 'توزیع تیکت بر اساس اولویت',
    'by_staff'        => 'عملکرد کارشناسان',

    'col_row'         => 'ردیف',
    'generated_at'    => 'تاریخِ تهیهٔ گزارش',
    'col_customer'    => 'مشتری',
    'col_project'     => 'پروژه',
    'col_created'     => 'ثبت‌شده',
    'col_tickets'     => 'حل‌شده',
    'col_minutes'     => 'زمان کارکرد (دقیقه)',
    'col_service'     => 'ارزش خدمت',
    'col_total'       => 'مبلغ کل فاکتور',
    'col_paid'        => 'پرداختی مشتری',
    'col_debt'        => 'بدهی مشتری',
    'col_invoiced'    => 'پرداختی مشتری',
    'col_warranty'    => 'سهم قرارداد/گارانتی',
    'col_category'    => 'دسته‌بندی',
    'col_status'      => 'وضعیت',
    'col_priority'    => 'اولویت',
    'col_count'       => 'تعداد',
    'col_staff'       => 'کارشناس',
    'col_resolved'    => 'تیکت حل‌شده',
    'col_response'    => 'میانگین زمان پاسخ (ساعت)',

    'export_excel'    => 'خروجی اکسل',
    'export_pdf'      => 'خروجی PDF',

    'empty' => 'داده‌ای در این بازه ثبت نشده است.',

    'pdf_title' => 'گزارش عملکرد',
    'period'    => 'بازه: :from تا :to',
];
