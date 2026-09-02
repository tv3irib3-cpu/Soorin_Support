<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Ticket;
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
        $openTickets = Ticket::whereNotIn('status', ['closed', 'cancelled'])->count();

        $resolvedThisMonth = Ticket::whereIn('status', ['resolved', 'closed'])
            ->whereMonth('resolved_at', now()->month)
            ->whereYear('resolved_at', now()->year)
            ->count();

        $unpaidInvoices = Invoice::whereNotIn('status', ['paid', 'cancelled', 'draft'])->count();

        $avgRating = Ticket::whereNotNull('rating')->avg('rating');

        $slaBreached = Ticket::whereNull('first_response_at')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->whereHas('contract.plan', fn ($q) => $q->whereNotNull('response_hours'))
            ->with('contract.plan')
            ->get()
            ->filter(fn (Ticket $t) => $t->isSlaBreached())
            ->count();

        return [
            Stat::make(__('portal.open_tickets'), (string) $openTickets)
                ->icon('heroicon-o-ticket')
                ->color('info'),

            Stat::make(__('tickets.statuses.resolved'), (string) $resolvedThisMonth)
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('invoices.plural'), (string) $unpaidInvoices)
                ->icon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make(__('tickets.sla_breached'), (string) $slaBreached)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($slaBreached > 0 ? 'danger' : 'gray'),

            Stat::make(__('tickets.rating'), $avgRating ? number_format($avgRating, 1) . ' / ۵' : '—')
                ->icon('heroicon-o-star')
                ->color('warning'),
        ];
    }
}
