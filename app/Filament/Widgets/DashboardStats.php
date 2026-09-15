<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketRead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * آمار کلی داشبورد پنل مدیریت — طبق بریف فاز ۱:
 * تیکت باز، حل‌شده این ماه، فاکتور پرداخت‌نشده، به‌علاوه تیکت‌های معطل SLA.
 */
class DashboardStats extends StatsOverviewWidget
{
    // کوئری‌ها سبک‌اند (چند COUNT ساده)؛ بدون تأخیر AJAX نمایش داده شوند
    protected static bool $isLazy = false;

    // بالای داشبورد، پیش از نمودار و جدول
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();

        // آمارِ تیکت‌ها «به‌ازای همین کاربر» است: مدیرِ پشتیبان همه، کارشناس فقط
        // تیکت‌های خودش. (وگرنه اعداد بینِ کارشناسان مشترک می‌شد.)
        $mine = fn () => $user ? Ticket::visibleTo($user) : Ticket::query()->whereRaw('1=0');

        // سه باکس بدونِ هم‌پوشانی تعریف می‌شوند تا محتوای یکسان نشان ندهند:
        //   نیازمندِ رسیدگی = توپ در زمینِ پشتیبان (جدید یا منتظرِ پاسخِ پشتیبان)
        //   باز            = در جریان ولی توپ در زمینِ پشتیبان نیست (بررسی/منتظرِ مشتری)
        //   حل‌شده         = حل‌شده یا بسته‌شده (در این ماه)

        // «نیازمندِ رسیدگی» = تیکت‌های جدید یا در انتظارِ پاسخِ پشتیبان.
        $needsAttention = $mine()->whereIn('status', [
            \App\Models\Ticket::STATUS_NEW,
            \App\Models\Ticket::STATUS_WAITING_SUPPORT,
        ])->count();

        // «باز» = در حال بررسی یا در انتظارِ پاسخِ مشتری (نه جدید/منتظرِ پشتیبان،
        // تا با باکسِ نیازمندِ رسیدگی هم‌پوشانی نداشته باشد).
        $openTickets = $mine()->whereIn('status', [
            \App\Models\Ticket::STATUS_IN_PROGRESS,
            \App\Models\Ticket::STATUS_WAITING_CUSTOMER,
        ])->count();

        $resolvedThisMonth = $mine()->whereIn('status', ['resolved', 'closed'])
            ->whereMonth('resolved_at', now()->month)
            ->whereYear('resolved_at', now()->year)
            ->count();

        $unpaidInvoices = Invoice::whereNotIn('status', ['paid', 'cancelled', 'draft'])->count();

        $totalDebt = (int) Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->selectRaw('COALESCE(SUM(GREATEST(payable_amount - paid_amount, 0)), 0) as d')
            ->value('d');

        $ticketsUrl = TicketResource::getUrl('index');
        $fa = fn (int $n) => \App\Support\Jalali::digits((string) $n);

        $stats = [];

        // نیازمندِ رسیدگی (جدید/منتظر پشتیبان) — همیشه نمایش، قرمز اگر بزرگ‌تر از صفر.
        $stats[] = Stat::make(__('dashboard.needs_attention'), $fa($needsAttention))
            ->icon('heroicon-o-bell-alert')
            ->description(__('dashboard.needs_attention_hint'))
            ->color($needsAttention > 0 ? 'danger' : 'success')
            ->url($ticketsUrl);

        $stats[] = Stat::make(__('portal.open_tickets'), $fa($openTickets))
            ->icon('heroicon-o-ticket')
            ->color('info')
            ->url($ticketsUrl);

        $stats[] = Stat::make(__('tickets.statuses.resolved'), $fa($resolvedThisMonth))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->url($ticketsUrl);

        $stats[] = Stat::make(__('invoices.plural'), $fa($unpaidInvoices))
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->url(InvoiceResource::getUrl('index'));

        $stats[] = Stat::make(__('dashboard.total_debt'), \App\Support\Jalali::money($totalDebt) . ' ' . __('common.currency'))
            ->description(__('dashboard.total_debt_hint'))
            ->icon('heroicon-o-exclamation-circle')
            ->color($totalDebt > 0 ? 'danger' : 'success')
            ->url(InvoiceResource::getUrl('index'));

        // امتیازِ رضایت در ویجتِ اختصاصیِ AgentRatingsWidget نمایش داده می‌شود
        // (به‌تفکیکِ کارشناس + امتیازِ کلیِ شرکت برای مدیر).

        return $stats;
    }
}
