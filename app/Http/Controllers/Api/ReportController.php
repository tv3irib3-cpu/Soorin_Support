<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * گزارش‌های مدیریتی برای اپِ پشتیبان — همان اعدادِ صفحهٔ گزارشِ سایت (از
 * ReportService، منبعِ واحد). بازهٔ زمانی با from/to (میلادی) یا پیش‌فرضِ ۳۰ روز.
 * فقط با مجوزِ «مشاهده گزارش‌ها».
 */
class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        $to   = $this->parseDate($request->query('to')) ?? Carbon::now();
        $from = $this->parseDate($request->query('from')) ?? (clone $to)->subDays(30);
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $data = $reports->generate($from->startOfDay(), $to->endOfDay());
        $s = $data['summary'];

        return response()->json([
            'from' => Jalali::format($from),
            'to'   => Jalali::format($to),
            'summary' => [
                'revenue_fa'        => Jalali::money((int) $s['revenue']),
                'paid_fa'           => Jalali::money((int) $s['paid']),
                'debt_fa'           => Jalali::money((int) $s['debt']),
                'service_value_fa'  => Jalali::money((int) $s['service_value']),
                'warranty_value_fa' => Jalali::money((int) $s['warranty_value']),
                'invoice_count'     => $s['invoice_count'],
                'service_count'     => $s['service_count'],
                'tickets_created'   => $s['tickets_created'],
                'tickets_still_open'=> $s['tickets_still_open'],
                'work_minutes'      => $s['work_minutes'],
                'avg_resolution_hours' => $s['avg_resolution_hours'],
                'sla_breaches'      => $s['sla_breaches'],
                'avg_rating'        => $s['avg_rating'] !== null ? round((float) $s['avg_rating'], 1) : null,
            ],
            'by_customer' => collect($data['by_customer'])->take(15)->map(fn ($r) => [
                'customer' => $r['customer'],
                'color'    => $r['color'],
                'created'  => $r['created'],
                'resolved' => $r['tickets'],
                'total_fa' => Jalali::money((int) $r['total']),
                'debt_fa'  => Jalali::money((int) $r['debt']),
            ])->all(),
            'by_status'   => $data['by_status'],
            'by_priority' => $data['by_priority'],
            'by_category' => collect($data['by_category'])->take(15)->all(),
            'by_staff'    => $data['by_staff'],
        ]);
    }

    private function parseDate(?string $v): ?Carbon
    {
        if (blank($v)) {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }
}
