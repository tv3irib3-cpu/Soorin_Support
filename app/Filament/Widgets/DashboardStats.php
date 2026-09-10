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
        // «باز» یعنی هنوز در جریان است — حل‌شده/بسته/لغو دیگر باز حساب نمی‌شود.
        $openTickets = Ticket::whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count();

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

        $unread     = auth()->user() ? TicketRead::unreadCountFor(auth()->user()) : 0;
        $ticketsUrl = TicketResource::getUrl('index');

        // اعداد به فارسی نمایش داده می‌شوند (قاعدهٔ پروژه: اعداد فارسی در نمایش).
        $fa = fn (int $n) => \App\Support\Jalali::digits((string) $n);

        $stats = [];

        // نشانِ پیام‌های خوانده‌نشده — فقط وقتی بزرگ‌تر از صفر است، با رنگِ قرمز و کلیک‌پذیر.
        if ($unread > 0) {
            $stats[] = Stat::make(__('portal.unread_messages'), $fa($unread))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->description(__('tickets.unread'))
                ->color('danger')
                ->url($ticketsUrl);
        }

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

        $stats[] = Stat::make(__('tickets.sla_breached'), $fa($slaBreached))
            ->icon('heroicon-o-exclamation-triangle')
            ->color($slaBreached > 0 ? 'danger' : 'gray')
            ->url($ticketsUrl);

        $stats[] = Stat::make(
            __('tickets.rating'),
            $avgRating ? \App\Support\Jalali::digits(number_format($avgRating, 1)) . ' / ۵' : '—'
        )
            ->icon('heroicon-o-star')
            ->color('warning');

        return $stats;
    }
}
