<?php

namespace App\Enums;

/**
 * فهرست مجوزهای سامانه.
 *
 * دسترسی‌ها **مجوزمحور** هستند نه نقش‌محور — نقش فقط یک قالب پیش‌فرض است.
 * مدیر می‌تواند برای هر کاربر مجوزها را جداگانه تغییر دهد.
 */
enum Permission: string
{
    // مشتریان
    case ViewCustomers   = 'customers.view';
    case ManageCustomers = 'customers.manage';

    // پروژه‌های مشتری
    case ManageProjects = 'projects.manage';

    // کاربران — فقط مدیر پشتیبان
    case ViewUsers   = 'users.view';
    case ManageUsers = 'users.manage';

    // تیکت‌ها
    case ViewTickets     = 'tickets.view';
    case CreateTickets   = 'tickets.create';
    case ManageTickets   = 'tickets.manage';
    case AssignTickets   = 'tickets.assign';
    case InternalNotes   = 'tickets.internal_notes';

    // قراردادها
    case ViewContracts   = 'contracts.view';
    case ManageContracts = 'contracts.manage';

    // فاکتورها
    case ViewInvoices   = 'invoices.view';
    case ManageInvoices = 'invoices.manage';
    case PrintInvoices  = 'invoices.print';
    case ManagePayments = 'payments.manage';

    // تنظیمات
    case ManageSettings = 'settings.manage';
    case ViewActivity   = 'activity.view';
    case ViewReports    = 'reports.view';

    public function label(): string
    {
        return match ($this) {
            self::ViewCustomers   => 'مشاهده مشتریان',
            self::ManageCustomers => 'مدیریت مشتریان',
            self::ManageProjects  => 'مدیریت پروژه‌های مشتری',
            self::ViewUsers       => 'مشاهده کاربران',
            self::ManageUsers     => 'مدیریت کاربران و ساخت حساب',
            self::ViewTickets     => 'مشاهده تیکت‌ها',
            self::CreateTickets   => 'ثبت تیکت',
            self::ManageTickets   => 'مدیریت تیکت‌ها',
            self::AssignTickets   => 'تخصیص کارشناس',
            self::InternalNotes   => 'یادداشت داخلی',
            self::ViewContracts   => 'مشاهده قراردادها',
            self::ManageContracts => 'مدیریت قراردادها',
            self::ViewInvoices    => 'مشاهده فاکتورها',
            self::ManageInvoices  => 'صدور و ویرایش فاکتور',
            self::PrintInvoices   => 'چاپ فاکتور',
            self::ManagePayments  => 'ثبت پرداخت',
            self::ManageSettings  => 'تنظیمات سامانه',
            self::ViewActivity    => 'مشاهده تاریخچه تغییرات',
            self::ViewReports     => 'مشاهده گزارش‌ها',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * مجوزهای پیش‌فرض هر نقش.
     *
     * توجه: ساخت حساب کاربری فقط در اختیار مدیر پشتیبان است.
     *
     * @return array<string, array<string>>
     */
    public static function defaultsByRole(): array
    {
        $supportStaff = [
            self::ViewCustomers, self::ViewTickets, self::CreateTickets,
            self::ManageTickets, self::AssignTickets, self::InternalNotes,
            self::ViewContracts, self::ViewInvoices, self::ManageInvoices,
            self::PrintInvoices, self::ManagePayments, self::ViewReports,
        ];

        return [
            // مدیر پشتیبان: همه‌چیز
            'support_admin'  => self::values(),
            'support_staff'  => array_map(fn (self $c) => $c->value, $supportStaff),
            // کاربران مشتری از مجوز استفاده نمی‌کنند؛ دسترسی آن‌ها از
            // فیلدهای مشتری و حساب خودشان خوانده می‌شود (User::canCreateTicket و ...)
            'customer_admin' => [],
            'customer_staff' => [],
        ];
    }
}
