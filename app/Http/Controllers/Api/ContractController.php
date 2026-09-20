<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قراردادها برای اپِ پشتیبان — فهرست با جستجو/فیلترِ وضعیت و صفحهٔ جزئیات
 * (نوعِ قرارداد، درصدهای پوشش، سقف و مانده، تیکت‌ها و فاکتورهای مرتبط).
 */
class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewContracts->value), 403);

        $query = Contract::with(['customer', 'plan']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $contracts = $query->latest('start_date')->paginate(20);

        return response()->json([
            'data' => collect($contracts->items())->map(fn (Contract $c) => $this->row($c))->all(),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page'    => $contracts->lastPage(),
                'total'        => $contracts->total(),
            ],
        ]);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewContracts->value), 403);

        return response()->json($this->detail($contract));
    }

    /** ردیفِ فهرست — مشترک بینِ پشتیبان و مشتری. */
    public static function row(Contract $c): array
    {
        $status = $c->effectiveStatus();
        $remaining = $c->remainingCeiling();

        return [
            'id'            => $c->id,
            'number'        => $c->number,
            'customer'      => $c->customer?->name,
            'plan'          => $c->plan?->name,
            'plan_color'    => $c->plan?->color,
            'status'        => $status,
            'status_label'  => __("contracts.statuses.$status"),
            'start_date'    => Jalali::format($c->start_date),
            'end_date'      => Jalali::format($c->end_date),
            'amount_fa'     => Jalali::money((int) $c->amount),
            'remaining_ceiling_fa' => $remaining === null ? null : Jalali::money($remaining),
        ];
    }

    /** جزئیاتِ کامل — مشترک. */
    public static function detail(Contract $c): array
    {
        $c->load(['customer', 'plan']);
        $plan = $c->plan;

        return [
            'contract' => array_merge(self::row($c), [
                'used_amount_fa'  => Jalali::money((int) $c->used_amount),
                'ceiling_fa'      => $plan?->ceiling_amount === null ? null : Jalali::money((int) $plan->ceiling_amount),
                'included_tickets'=> $plan?->included_tickets,
                'response_hours'  => $plan?->response_hours,
                'notes'           => $c->notes,
                'coverage' => $plan ? [
                    ['label' => __('contracts.cover_software'), 'percent' => (int) $plan->cover_software],
                    ['label' => __('contracts.cover_hardware'), 'percent' => (int) $plan->cover_hardware],
                    ['label' => __('contracts.cover_parts'),    'percent' => (int) $plan->cover_parts],
                    ['label' => __('contracts.cover_onsite'),   'percent' => (int) $plan->cover_onsite],
                ] : [],
            ]),
            'tickets_count'  => $c->tickets()->count(),
            'invoices_count' => $c->invoices()->count(),
        ];
    }
}
