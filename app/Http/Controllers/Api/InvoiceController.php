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
}
