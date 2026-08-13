<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        abort_unless($user->canViewInvoices(), 403, __('portal.no_access_invoices'));

        $invoices = $user->customer->invoices()->latest('issue_date')->paginate(15);

        return view('portal.invoices.index', [
            'invoices'   => $invoices,
            'canPrint'   => $user->canPrintInvoices(),
        ]);
    }
}
