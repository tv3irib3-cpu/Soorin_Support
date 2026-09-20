<?php

namespace App\Http\Controllers\Api;

use App\Actions\IssueInvoice;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * فهرستِ فاکتورها برای اپِ پشتیبان — پشتیبان همهٔ فاکتورها را می‌بیند (با مجوزِ
 * «مشاهده فاکتورها»). جستجو بر پایهٔ شماره فاکتور یا نامِ مشتری + فیلترِ وضعیت.
 * چاپ/PDF با مجوزِ «چاپ فاکتور» کنترل می‌شود (can_print).
 */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ViewInvoices->value), 403);

        $query = Invoice::with(['customer', 'ticket']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $statuses = array_values(array_intersect(
            (array) $request->input('status', []),
            [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED, Invoice::STATUS_PAID,
                Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_CANCELLED],
        ));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $invoices = $query->latest('issue_date')->latest('id')->paginate(20);
        $canPrint = $user->canPrintInvoices();

        return response()->json([
            'data' => collect($invoices->items())->map(fn (Invoice $inv) => [
                'id'            => $inv->id,
                'number'        => $inv->number,
                'status'        => $inv->status,
                'status_label'  => __("invoices.statuses.$inv->status"),
                'issue_date'    => Jalali::format($inv->issue_date),
                'customer'      => $inv->customer?->name,
                'payable'       => (int) $inv->payable_amount,
                'paid'          => (int) $inv->paid_amount,
                'remaining'     => max((int) $inv->payable_amount - (int) $inv->paid_amount, 0),
                'payable_fa'    => Jalali::money((int) $inv->payable_amount),
                'ticket_number' => $inv->ticket?->number,
                'can_print'     => $canPrint,
            ])->all(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }

    /** دادهٔ فرمِ صدور فاکتور — اطلاعاتِ تیکت/مشتری + قراردادِ فعال + نوعِ ردیف‌ها. */
    public function createFormData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ManageInvoices->value), 403);

        $ticket = $request->integer('ticket_id') ? Ticket::with('customer')->find($request->integer('ticket_id')) : null;
        $customer = $ticket?->customer
            ?? ($request->integer('customer_id') ? Customer::find($request->integer('customer_id')) : null);
        abort_unless($customer, 422, 'ابتدا مشتری یا تیکت را انتخاب کنید.');

        $contract = $ticket?->contract_id ? Contract::find($ticket->contract_id) : $customer->activeContract();

        return response()->json([
            'customer'     => ['id' => $customer->id, 'name' => $customer->name],
            'ticket'       => $ticket ? ['id' => $ticket->id, 'number' => $ticket->number, 'subject' => $ticket->subject, 'service_type' => $ticket->service_type] : null,
            'contract'     => $contract ? [
                'id' => $contract->id, 'number' => $contract->number, 'plan' => $contract->plan?->name,
                'valid' => $contract->isValidOn(now()->toDateString()),
            ] : null,
            'item_types'   => __('invoices.item_types'),
            'default_title'=> __('invoices.default_service_title'),
        ]);
    }

    /** صدور فاکتور با ردیف‌ها. محاسبهٔ سهمِ قرارداد و مبلغِ قابل‌پرداخت خودکار است. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ManageInvoices->value), 403);

        $data = $request->validate([
            'customer_id'     => ['required', 'exists:customers,id'],
            'ticket_id'       => ['nullable', 'exists:tickets,id'],
            'contract_id'     => ['nullable', 'exists:contracts,id'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'issue'           => ['nullable', 'boolean'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.item_type'    => ['required', 'in:service,part,other'],
            'items.*.title'        => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_price'   => ['required', 'integer', 'min:0'],
            'items.*.part_code'    => ['nullable', 'string', 'max:100'],
        ]);

        $ticket   = ! empty($data['ticket_id']) ? Ticket::find($data['ticket_id']) : null;
        $contractId = $data['contract_id'] ?? $ticket?->contract_id
            ?? Customer::find($data['customer_id'])?->activeContract()?->id;
        $contract = $contractId ? Contract::find($contractId) : null;

        $invoice = Invoice::create([
            'number'          => $this->nextNumber(),
            'customer_id'     => $data['customer_id'],
            'ticket_id'       => $data['ticket_id'] ?? null,
            'contract_id'     => $contractId,
            'issue_date'      => now(),
            'discount_amount' => $data['discount_amount'] ?? 0,
            'status'          => Invoice::STATUS_DRAFT,
            'created_by'      => $user->id,
        ]);

        $plan = ($contract && $contract->isValidOn(now()->toDateString())) ? $contract->plan : null;
        $serviceType = $ticket?->service_type ?? 'other';

        foreach ($data['items'] as $row) {
            $item = $invoice->items()->create([
                'item_type'  => $row['item_type'],
                'title'      => $row['title'],
                'part_code'  => $row['part_code'] ?? null,
                'quantity'   => $row['quantity'] ?? 1,
                'unit_price' => $row['unit_price'],
            ]);
            $item->recalculate($plan, $serviceType, null);
        }

        $invoice->recalculate();

        if (! empty($data['issue'])) {
            app(IssueInvoice::class)($invoice->refresh());
        }

        $invoice->refresh();

        return response()->json([
            'id' => $invoice->id, 'number' => $invoice->number, 'status' => $invoice->status,
            'payable_fa' => Jalali::money((int) $invoice->payable_amount),
        ], 201);
    }

    /** صدورِ یک فاکتورِ پیش‌نویس (draft → issued). */
    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ManageInvoices->value), 403);
        abort_unless($invoice->status === Invoice::STATUS_DRAFT, 422, 'این فاکتور قبلاً صادر شده است.');

        app(IssueInvoice::class)($invoice);

        return response()->json(['message' => 'ok', 'status' => $invoice->fresh()->status]);
    }

    /** شمارهٔ فاکتورِ بعدی — ادامهٔ بزرگ‌ترین شمارهٔ عددیِ موجود. */
    private function nextNumber(): string
    {
        $max = (int) Invoice::max('id');

        return 'INV-' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /** جزئیاتِ فاکتور + سه عددِ کلیدی + فهرستِ پرداخت‌ها. */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ViewInvoices->value), 403);

        $invoice->load(['customer', 'ticket', 'payments.registrar']);
        $balance = $invoice->balance();

        return response()->json([
            'invoice' => [
                'id'             => $invoice->id,
                'number'         => $invoice->number,
                'status'         => $invoice->status,
                'status_label'   => __("invoices.statuses.$invoice->status"),
                'issue_date'     => Jalali::format($invoice->issue_date),
                'customer'       => $invoice->customer?->name,
                'ticket_number'  => $invoice->ticket?->number,
                'service_amount' => (int) $invoice->service_amount,
                'contract_amount'=> (int) $invoice->contract_amount,
                'payable'        => (int) $invoice->payable_amount,
                'paid'           => (int) $invoice->paid_amount,
                'remaining'      => $balance,
                'service_fa'     => Jalali::money((int) $invoice->service_amount),
                'contract_fa'    => Jalali::money((int) $invoice->contract_amount),
                'payable_fa'     => Jalali::money((int) $invoice->payable_amount),
                'paid_fa'        => Jalali::money((int) $invoice->paid_amount),
                'remaining_fa'   => Jalali::money($balance),
                'is_warranty'    => (bool) $invoice->is_warranty,
                'notes'          => $invoice->notes,
                'can_print'      => $user->canPrintInvoices(),
                'can_pay'        => $user->can(Permission::ManagePayments->value)
                    && $balance > 0
                    && ! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true),
            ],
            'payment_methods' => __('invoices.methods'),
            'payments' => $invoice->payments->map(fn ($p) => [
                'id'         => $p->id,
                'amount'     => (int) $p->amount,
                'amount_fa'  => Jalali::money((int) $p->amount),
                'method'     => $p->method,
                'method_label' => __("invoices.methods.$p->method"),
                'reference'  => $p->reference,
                'paid_at'    => Jalali::format($p->paid_at),
                'registrar'  => $p->registrar?->name,
            ])->all(),
        ]);
    }

    /** ثبتِ پرداخت روی فاکتور — با مجوزِ «ثبت پرداخت». مبلغ از ماندهٔ فعلی بیشتر نمی‌شود. */
    public function pay(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ManagePayments->value), 403);
        abort_if(in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true),
            422, __('invoices.no_balance'));

        $data = $request->validate([
            'amount'    => ['required', 'integer', 'min:1'],
            'paid_at'   => ['nullable', 'date'],
            'method'    => ['required', 'in:' . implode(',', array_keys(__('invoices.methods')))],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $amount = min((int) $data['amount'], $invoice->balance());
        abort_if($amount <= 0, 422, __('invoices.no_balance'));

        $invoice->payments()->create([
            'amount'        => $amount,
            'paid_at'       => $data['paid_at'] ?? now(),
            'method'        => $data['method'],
            'reference'     => $data['reference'] ?? null,
            'registered_by' => $user->id,
        ]);

        $invoice->refresh();

        return response()->json([
            'message'   => 'ok',
            'status'    => $invoice->status,
            'paid_fa'   => Jalali::money((int) $invoice->paid_amount),
            'remaining_fa' => Jalali::money($invoice->balance()),
        ]);
    }
}
