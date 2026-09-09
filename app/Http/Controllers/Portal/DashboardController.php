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

        // «باز» = هنوز در جریان (حل‌شده/بسته/لغو باز نیست، هم‌راستا با داشبوردِ پشتیبان).
        $openTickets = Ticket::visibleTo($user)->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count();
        $closedTickets = Ticket::visibleTo($user)->whereIn('status', ['closed', 'cancelled'])->count();

        // تیکت‌های حل‌شده‌ای که مشتری هنوز به آن‌ها امتیاز نداده — برای یادآوریِ نظرسنجی.
        $resolvedUnrated = Ticket::visibleTo($user)
            ->where('status', Ticket::STATUS_RESOLVED)
            ->whereNull('rating')
            ->count();

        $unpaidInvoices = $user->canViewInvoices()
            ? $user->customer->invoices()
                ->whereNotIn('status', ['paid', 'cancelled', 'draft'])
                ->count()
            : 0;

        $recentTickets = Ticket::visibleTo($user)->latest()->limit(5)->get();

        $unreadCount = \App\Models\TicketRead::unreadCountFor($user);

        return view('portal.dashboard', compact('openTickets', 'closedTickets', 'resolvedUnrated', 'unpaidInvoices', 'recentTickets', 'unreadCount'));
    }
}
