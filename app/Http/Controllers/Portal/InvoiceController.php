<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceRead;
use Illuminate\Contracts\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        abort_unless($user->canViewInvoices(), 403, __('portal.no_access_invoices'));

        // زمانِ آخرین بازدید را پیش از به‌روزرسانی می‌گیریم تا همین صفحه بتواند
        // فاکتورهای تازه را «جدید» علامت بزند؛ سپس بازدید را ثبت می‌کنیم تا دفعهٔ
        // بعد نشانِ منو صفر شود (مثلِ خوانده‌شدنِ تیکت).
        $lastSeen = InvoiceRead::lastSeenAt($user);

        // مدیرِ مشتری همهٔ فاکتورهای شرکت را می‌بیند؛ کارشناس فقط فاکتورهای تیکت‌های خودش.
        $invoices = Invoice::visibleToCustomer($user)->latest('issue_date')->paginate(15);

        InvoiceRead::markSeen($user);

        return view('portal.invoices.index', [
            'invoices' => $invoices,
            'canPrint' => $user->canPrintInvoices(),
            'lastSeen' => $lastSeen,
        ]);
    }
}
