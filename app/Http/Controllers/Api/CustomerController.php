<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مشتریان برای اپِ پشتیبان — فهرست با جستجو و صفحهٔ جزئیات (اطلاعاتِ تماس،
 * وضعیتِ سرویس، پروژه‌ها، و تیکت‌ها/فاکتورهای اخیرِ همان مشتری). فقط با مجوزِ
 * «مشاهده مشتریان».
 */
class CustomerController extends Controller
{
    /** وضعیت‌هایی که «تیکتِ باز» شمرده می‌شوند. */
    private const OPEN_STATUSES = [
        Ticket::STATUS_WAITING_SUPPORT,
        Ticket::STATUS_IN_PROGRESS,
        Ticket::STATUS_WAITING_CUSTOMER,
        Ticket::STATUS_WAITING_PAYMENT,
    ];

    private const UNPAID_STATUSES_EXCLUDE = ['paid', 'cancelled', 'draft'];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewCustomers->value), 403);

        $query = Customer::query()->withCount([
            'tickets as open_tickets_count' => fn (Builder $q) => $q->whereIn('status', self::OPEN_STATUSES),
            'invoices as unpaid_invoices_count' => fn (Builder $q) => $q->whereNotIn('status', self::UNPAID_STATUSES_EXCLUDE),
        ]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search): void {
                foreach (['name', 'code', 'phone', 'mobile', 'city'] as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        $customers = $query->orderBy('name')->paginate(20);

        return response()->json([
            'data' => collect($customers->items())->map(fn (Customer $c) => [
                'id'              => $c->id,
                'name'            => $c->name,
                'code'            => $c->code,
                'city'            => $c->city,
                'color'          => $c->displayColor(),
                'service_status'  => $c->service_status,
                'is_active'       => $c->canReceiveService(),
                'mobile'          => $c->mobile,
                'phone'           => $c->phone,
                'open_tickets'    => (int) $c->open_tickets_count,
                'unpaid_invoices' => (int) $c->unpaid_invoices_count,
            ])->all(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'total'        => $customers->total(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewCustomers->value), 403);

        $tickets = $customer->tickets()->with('project')->latest()->limit(10)->get()
            ->map(fn (Ticket $t) => [
                'id'           => $t->id,
                'number'       => $t->number,
                'subject'      => $t->subject,
                'status'       => $t->status,
                'status_label' => __("tickets.statuses.$t->status"),
                'priority'     => $t->priority,
                'created_at_jalali' => Jalali::format($t->created_at),
            ]);

        $invoices = $customer->invoices()->latest('issue_date')->limit(10)->get()
            ->map(fn (Invoice $inv) => [
                'id'           => $inv->id,
                'number'       => $inv->number,
                'status'       => $inv->status,
                'status_label' => __("invoices.statuses.$inv->status"),
                'payable_fa'   => Jalali::money((int) $inv->payable_amount),
                'issue_date'   => Jalali::format($inv->issue_date),
            ]);

        return response()->json([
            'customer' => [
                'id'                 => $customer->id,
                'name'               => $customer->name,
                'code'               => $customer->code,
                'entity_type'        => $customer->entity_type,
                'service_status'     => $customer->service_status,
                'is_active'          => $customer->canReceiveService(),
                'suspension_message' => $customer->canReceiveService() ? null : $customer->suspensionNotice(),
                'phone'              => $customer->phone,
                'mobile'             => $customer->mobile,
                'email'              => $customer->email,
                'city'               => $customer->city,
                'address'            => $customer->address,
                'national_id'        => $customer->national_id,
                'economic_code'      => $customer->economic_code,
                'notes'              => $customer->notes,
                'can_create_ticket'  => (bool) $customer->can_create_ticket,
                'can_view_invoices'  => (bool) $customer->can_view_invoices,
            ],
            'projects' => $customer->projects()->orderBy('name')->get(['id', 'name'])->all(),
            'tickets'  => $tickets,
            'invoices' => $invoices,
        ]);
    }
}
