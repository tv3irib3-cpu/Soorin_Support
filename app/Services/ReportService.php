<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * محاسبه گزارش‌های مدیریتی برای یک بازه تاریخی.
 *
 * یک منبع واحد برای صفحه گزارش‌ها، خروجی اکسل و خروجی PDF — هر سه از
 * همین کلاس داده می‌گیرند تا اعداد همیشه یکی باشند.
 *
 * دو نگاه به تیکت داریم و نباید قاطی شوند:
 *   - «حجمِ خدماتِ ارائه‌شده» بر مبنای تیکت‌های **حل‌شده** (resolved_at) در بازه
 *     است — یعنی کاری که واقعاً تحویل داده شده.
 *   - «تعدادِ تیکت‌های ثبت‌شده» بر مبنای تیکت‌های **ساخته‌شده** (created_at) در
 *     بازه است — یعنی حجمِ ورودیِ درخواست‌ها. کاربر برای «تعداد تیکت‌های این
 *     ماه/هفته/سال» این نگاه را می‌خواهد.
 *
 * «درآمد» بر مبنای مبلغِ قابل‌پرداختِ فاکتورهای صادرشده (نه پیش‌نویس/لغوشده) با
 * تاریخِ صدورِ داخلِ بازه است.
 */
class ReportService
{
    public function generate(CarbonInterface $from, CarbonInterface $to): array
    {
        $invoices = Invoice::whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->with('customer')
            ->get();

        // تیکت‌های حل‌شده در بازه — «خدماتِ تحویل‌شده»
        $resolved = Ticket::whereBetween('resolved_at', [$from, $to])
            ->with(['customer', 'category.parent', 'assignee'])
            ->get();

        // تیکت‌های ثبت‌شده در بازه — «حجمِ ورودی»
        $created = Ticket::whereBetween('created_at', [$from, $to])
            ->with(['customer', 'project', 'category.parent', 'assignee'])
            ->get();

        return [
            'from'        => $from,
            'to'          => $to,
            'summary'     => $this->summary($invoices, $resolved, $created),
            'by_customer' => $this->byCustomer($invoices, $resolved, $created),
            'by_project'  => $this->byProject($created, $resolved),
            'by_category' => $this->byCategory($resolved),
            'by_status'   => $this->byStatus($created),
            'by_priority' => $this->byPriority($created),
            'by_staff'    => $this->byStaff($resolved),
        ];
    }

    private function summary(Collection $invoices, Collection $resolved, Collection $created): array
    {
        // میانگینِ زمانِ حل (از ثبت تا حل) به ساعت
        $resolutionHours = $resolved
            ->filter(fn (Ticket $t) => $t->resolved_at !== null)
            ->map(fn (Ticket $t) => $t->created_at->diffInMinutes($t->resolved_at) / 60);

        $payable = (int) $invoices->sum('payable_amount');
        $paid    = (int) $invoices->sum('paid_amount');

        return [
            'revenue'              => $payable,                 // مبلغِ کلِ فاکتورها (قابل‌پرداخت)
            'paid'                 => $paid,                    // مجموعِ پرداختِ واقعی
            'debt'                 => max(0, $payable - $paid), // بدهیِ باقی‌مانده
            'warranty_value'       => (int) $invoices->sum('contract_amount'),
            'service_value'        => (int) $invoices->sum('service_amount'),
            'invoice_count'        => $invoices->count(),
            'service_count'        => $resolved->count(),       // تیکت‌های حل‌شده
            'tickets_created'      => $created->count(),         // تیکت‌های ثبت‌شده
            'tickets_still_open'   => $created->filter(fn (Ticket $t) => $t->isOpen())->count(),
            'work_minutes'         => (int) $resolved->sum('work_minutes'),
            'avg_resolution_hours' => $resolutionHours->isNotEmpty() ? round($resolutionHours->avg(), 1) : null,
            'sla_breaches'         => $resolved->filter(fn (Ticket $t) => $this->wasSlaBreached($t))->count(),
            'avg_rating'           => $resolved->whereNotNull('rating')->avg('rating'),
        ];
    }

    /** آیا اولین پاسخ به این تیکت دیرتر از مهلتِ SLA بوده؟ */
    private function wasSlaBreached(Ticket $ticket): bool
    {
        $deadline = $ticket->slaDeadline();

        if ($deadline === null) {
            return false;
        }

        // یا اصلاً پاسخی نبوده، یا پاسخ بعد از مهلت آمده
        return $ticket->first_response_at === null
            || $ticket->first_response_at->greaterThan($deadline);
    }

    /**
     * خدماتِ هر مشتری — تعدادِ تیکتِ ثبت‌شده و حل‌شده، زمانِ کارکرد، مبلغِ
     * فاکتورشده و سهمِ قرارداد. برای «تعدادِ کلِ تیکت‌های یک مشتری».
     */
    private function byCustomer(Collection $invoices, Collection $resolved, Collection $created): Collection
    {
        $byInvoice  = $invoices->groupBy('customer_id');
        $byResolved = $resolved->groupBy('customer_id');
        $byCreated  = $created->groupBy('customer_id');

        $ids = $byInvoice->keys()
            ->merge($byResolved->keys())
            ->merge($byCreated->keys())
            ->unique();

        return $ids->map(function ($customerId) use ($byInvoice, $byResolved, $byCreated) {
            $cInvoices = $byInvoice->get($customerId, collect());
            $cResolved = $byResolved->get($customerId, collect());
            $cCreated  = $byCreated->get($customerId, collect());
            $customer  = $cInvoices->first()?->customer
                ?? $cResolved->first()?->customer
                ?? $cCreated->first()?->customer;

            return [
                'customer' => $customer?->name ?? '—',
                'color'    => $customer?->displayColor() ?? '#94a3b8',
                'created'  => $cCreated->count(),
                'tickets'  => $cResolved->count(),   // حل‌شده (کلیدِ سازگار با اکسل)
                'minutes'  => (int) $cResolved->sum('work_minutes'),
                'service'  => (int) $cInvoices->sum('service_amount'),   // ارزشِ واقعیِ خدمت
                'total'    => (int) $cInvoices->sum('payable_amount'),   // مبلغِ کلِ فاکتور (قابل‌پرداخت)
                'paid'     => (int) $cInvoices->sum('paid_amount'),      // پرداختِ واقعیِ مشتری
                'debt'     => max(0, (int) $cInvoices->sum('payable_amount') - (int) $cInvoices->sum('paid_amount')), // بدهی
                'invoiced' => (int) $cInvoices->sum('payable_amount'),   // (سازگاری با اکسل قدیمی)
                'warranty' => (int) $cInvoices->sum('contract_amount'),  // سهمِ گارانتی/قرارداد
            ];
        })
            ->sortByDesc('created')
            ->values();
    }

    /** تعدادِ تیکت به تفکیکِ پروژهٔ مشتری — برای «تعدادِ تیکت‌های یک پروژهٔ خاص». */
    private function byProject(Collection $created, Collection $resolved): Collection
    {
        $resolvedByProject = $resolved->groupBy('customer_project_id');

        return $created
            ->filter(fn (Ticket $t) => $t->customer_project_id !== null)
            ->groupBy('customer_project_id')
            ->map(function (Collection $group, $projectId) use ($resolvedByProject) {
                $first = $group->first();

                return [
                    'project'  => $first->project?->name ?? '—',
                    'customer' => $first->customer?->name ?? '—',
                    'created'  => $group->count(),
                    'resolved' => $resolvedByProject->get($projectId, collect())->count(),
                ];
            })
            ->sortByDesc('created')
            ->values();
    }

    /** آمار خرابی به تفکیکِ دسته‌بندیِ دولایهٔ تیکت (تیکت‌های حل‌شده). */
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

    /** توزیعِ تیکت‌های ثبت‌شده روی وضعیت‌ها. */
    private function byStatus(Collection $created): Collection
    {
        return $created
            ->groupBy('status')
            ->map(fn (Collection $group, $status) => [
                'status' => $status,
                'label'  => __("tickets.statuses.$status"),
                'count'  => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /** توزیعِ تیکت‌های ثبت‌شده روی اولویت‌ها. */
    private function byPriority(Collection $created): Collection
    {
        return $created
            ->groupBy('priority')
            ->map(fn (Collection $group, $priority) => [
                'priority' => $priority,
                'label'    => __("tickets.priorities.$priority"),
                'count'    => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /** عملکردِ کارشناسان — تعدادِ تیکتِ حل‌شده و میانگینِ زمانِ پاسخِ اولیه به ساعت. */
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
