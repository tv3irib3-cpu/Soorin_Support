<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * فاکتورهای اپِ مشتری — با دامنهٔ دیدِ Invoice::visibleToCustomer (مدیر همه،
 * کارشناس فقط فاکتورهای تیکت‌های خودش).
 */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canViewInvoices(), 403, __('portal.no_access_invoices'));

        $invoices = Invoice::visibleToCustomer($user)->with('ticket')->latest('issue_date')->paginate(20);

        return response()->json([
            'data' => collect($invoices->items())->map(fn (Invoice $inv) => [
                'id'            => $inv->id,
                'number'        => $inv->number,
                'status'        => $inv->status,
                'status_label'  => __("invoices.statuses.$inv->status"),
                'issue_date'    => Jalali::format($inv->issue_date),
                'payable'       => (int) $inv->payable_amount,
                'paid'          => (int) $inv->paid_amount,
                'remaining'     => max((int) $inv->payable_amount - (int) $inv->paid_amount, 0),
                'payable_fa'    => Jalali::money((int) $inv->payable_amount),
                'ticket_number' => $inv->ticket?->number,
                'can_print'     => $user->canPrintInvoices(),
            ])->all(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }
}
