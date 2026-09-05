<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $openTickets = Ticket::visibleTo($user)->whereNotIn('status', ['closed', 'cancelled'])->count();
        $closedTickets = Ticket::visibleTo($user)->whereIn('status', ['closed', 'cancelled'])->count();

        $unpaidInvoices = $user->canViewInvoices()
            ? $user->customer->invoices()
                ->whereNotIn('status', ['paid', 'cancelled', 'draft'])
                ->count()
            : 0;

        $recentTickets = Ticket::visibleTo($user)->latest()->limit(5)->get();

        return view('portal.dashboard', compact('openTickets', 'closedTickets', 'unpaidInvoices', 'recentTickets'));
    }
}
