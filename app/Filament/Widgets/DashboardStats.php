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

        // سه باکس بدونِ هم‌پوشانی، هرکدام دقیقاً یک مجموعهٔ وضعیت. با کلیک روی هر
        // باکس، فهرستِ تیکت‌ها با همان فیلترِ وضعیت باز می‌شود (نه فقط صفحهٔ تیکت‌ها).
        $needsStatuses = [\App\Models\Ticket::STATUS_WAITING_SUPPORT];
        $openStatuses  = [\App\Models\Ticket::STATUS_WAITING_CUSTOMER, \App\Models\Ticket::STATUS_IN_PROGRESS];
        $doneStatuses  = [\App\Models\Ticket::STATUS_RESOLVED];

        // «نیازمندِ رسیدگی» = فقط «در انتظار پاسخ پشتیبان».
        $needsAttention = $mine()->whereIn('status', $needsStatuses)->count();

        // «باز» = «منتظر پاسخ مشتری» یا «در حال بررسی».
        $openTickets = $mine()->whereIn('status', $openStatuses)->count();

        // «حل‌شده» = فقط وضعیتِ «حل‌شده».
        $resolvedCount = $mine()->whereIn('status', $doneStatuses)->count();

        $unpaidInvoices = Invoice::whereNotIn('status', ['paid', 'cancelled', 'draft'])->count();

        $totalDebt = (int) Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->selectRaw('COALESCE(SUM(GREATEST(payable_amount - paid_amount, 0)), 0) as d')
            ->value('d');

        // آدرسِ فهرستِ تیکت‌ها با پارامترِ ساده‌ی status[]؛ صفحهٔ ListTickets در mount
        // این را می‌خواند و فیلترِ وضعیت را ست می‌کند (tableFilters خودش به URL بند
        // نیست، پس این‌طور فیلتر هم اعمال و هم در UI دیده می‌شود).
        $statusUrl = fn (array $statuses) => TicketResource::getUrl('index', ['status' => array_values($statuses)]);
        $fa = fn (int $n) => \App\Support\Jalali::digits((string) $n);

        $stats = [];

        // نیازمندِ رسیدگی (منتظر پشتیبان) — قرمز اگر بزرگ‌تر از صفر.
        $stats[] = Stat::make(__('dashboard.needs_attention'), $fa($needsAttention))
            ->icon('heroicon-o-bell-alert')
            ->description(__('dashboard.needs_attention_hint'))
            ->color($needsAttention > 0 ? 'danger' : 'success')
            ->url($statusUrl($needsStatuses));

        $stats[] = Stat::make(__('portal.open_tickets'), $fa($openTickets))
            ->icon('heroicon-o-ticket')
            ->color('info')
            ->url($statusUrl($openStatuses));

        $stats[] = Stat::make(__('tickets.statuses.resolved'), $fa($resolvedCount))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->url($statusUrl($doneStatuses));

        $stats[] = Stat::make(__('invoices.plural'), $fa($unpaidInvoices))
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->url(InvoiceResource::getUrl('index'));

        // مقدارِ بدهی رشتهٔ بلندی است (عدد + «ریال»)؛ با کلاسِ stat-debt فونتش
        // در theme.css ریزتر می‌شود تا مثلِ بقیه بیش‌ازحد درشت نباشد.
        $stats[] = Stat::make(__('dashboard.total_debt'), \App\Support\Jalali::money($totalDebt) . ' ' . __('common.currency'))
            ->description(__('dashboard.total_debt_hint'))
            ->icon('heroicon-o-exclamation-circle')
            ->color($totalDebt > 0 ? 'danger' : 'success')
            ->extraAttributes(['class' => 'stat-debt'])
            ->url(InvoiceResource::getUrl('index'));

        // امتیازِ رضایت در ویجتِ اختصاصیِ AgentRatingsWidget نمایش داده می‌شود
        // (به‌تفکیکِ کارشناس + امتیازِ کلیِ شرکت برای مدیر).

        return $stats;
    }
}
