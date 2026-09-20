<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
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
