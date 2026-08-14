<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * محاسبه گزارش‌های فاز ۲ برای یک بازه تاریخی.
 *
 * یک منبع واحد برای صفحه گزارش‌ها، خروجی اکسل و خروجی PDF — هر سه از
 * همین کلاس داده می‌گیرند تا اعداد همیشه یکی باشند.
 *
 * قرارداد اعداد:
 *   - «درآمد» بر مبنای مبلغ قابل‌پرداخت فاکتورهای صادرشده (نه پیش‌نویس/لغوشده)
 *     با تاریخ صدور داخل بازه است.
 *   - «حجم خدمات» بر مبنای تیکت‌های حل‌شده (resolved_at) داخل بازه است،
 *     نه تیکت‌های ثبت‌شده — چون «ارائه‌شده» یعنی تحویل داده شده.
 */
class ReportService
{
    public function generate(CarbonInterface $from, CarbonInterface $to): array
    {
        $invoices = Invoice::whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->with('customer')
            ->get();

        $tickets = Ticket::whereBetween('resolved_at', [$from, $to])
            ->with(['customer', 'category.parent', 'assignee'])
            ->get();

        return [
            'from'        => $from,
            'to'          => $to,
            'summary'     => $this->summary($invoices, $tickets),
            'by_customer' => $this->byCustomer($invoices, $tickets),
            'by_category' => $this->byCategory($tickets),
            'by_staff'    => $this->byStaff($tickets),
        ];
    }

    private function summary(Collection $invoices, Collection $tickets): array
    {
        return [
            'revenue'         => (int) $invoices->sum('payable_amount'),
            'warranty_value'  => (int) $invoices->sum('contract_amount'),
            'service_count'   => $tickets->count(),
            'work_minutes'    => (int) $tickets->sum('work_minutes'),
            'avg_rating'      => $tickets->whereNotNull('rating')->avg('rating'),
        ];
    }

    /** خدمات دریافتی هر مشتری — تعداد تیکت، زمان کارکرد، مبلغ فاکتورشده، سهم قرارداد. */
    private function byCustomer(Collection $invoices, Collection $tickets): Collection
    {
        $byInvoice = $invoices->groupBy('customer_id');
        $byTicket  = $tickets->groupBy('customer_id');

        $customerIds = $byInvoice->keys()->merge($byTicket->keys())->unique();

        return $customerIds->map(function ($customerId) use ($byInvoice, $byTicket) {
            $customerInvoices = $byInvoice->get($customerId, collect());
            $customerTickets  = $byTicket->get($customerId, collect());
            $customer         = $customerInvoices->first()?->customer ?? $customerTickets->first()?->customer;

            return [
                'customer'  => $customer?->name ?? '—',
                'tickets'   => $customerTickets->count(),
                'minutes'   => (int) $customerTickets->sum('work_minutes'),
                'invoiced'  => (int) $customerInvoices->sum('payable_amount'),
                'warranty'  => (int) $customerInvoices->sum('contract_amount'),
            ];
        })
            ->sortByDesc('invoiced')
            ->values();
    }

    /** آمار خرابی به تفکیک دسته‌بندی دولایه تیکت. */
    private function byCategory(Collection $tickets): Collection
    {
        return $tickets
            ->groupBy('ticket_category_id')
            ->map(function (Collection $group) {
                $category = $group->first()->category;

                return [
                    'category' => $category?->fullName() ?? '—',
                    'count'    => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /** عملکرد کارشناسان — تعداد تیکت حل‌شده و میانگین زمان پاسخ اولیه به ساعت. */
    private function byStaff(Collection $tickets): Collection
    {
        return $tickets
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->map(function (Collection $group) {
                $responseTimes = $group
                    ->filter(fn (Ticket $t) => $t->first_response_at !== null)
                    ->map(fn (Ticket $t) => $t->created_at->diffInMinutes($t->first_response_at) / 60);

                return [
                    'staff'           => $group->first()->assignee?->name ?? '—',
                    'resolved'        => $group->count(),
                    'avg_response_hr' => $responseTimes->isNotEmpty() ? round($responseTimes->avg(), 1) : null,
                ];
            })
            ->sortByDesc('resolved')
            ->values();
    }
}
