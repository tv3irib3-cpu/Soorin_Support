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

        // داشبوردِ مشتری دو باکس دارد (بدونِ هم‌پوشانی):
        //   نیازمندِ رسیدگی = هر تیکتِ فعال (منتظر پشتیبان/مشتری، در حال بررسی، منتظر پرداخت)
        //   حل‌شده         = فقط حل‌شده
        $activeStatuses = [
            Ticket::STATUS_WAITING_SUPPORT,
            Ticket::STATUS_WAITING_CUSTOMER,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_WAITING_PAYMENT,
        ];
        $needsAttention = Ticket::visibleTo($user)->whereIn('status', $activeStatuses)->count();
        $resolvedCount  = Ticket::visibleTo($user)->where('status', Ticket::STATUS_RESOLVED)->count();

        // تیکت‌های حل‌شده‌ای که مشتری هنوز به آن‌ها امتیاز نداده — برای یادآوریِ نظرسنجی.
        $resolvedUnrated = Ticket::visibleTo($user)
            ->where('status', Ticket::STATUS_RESOLVED)
            ->whereNull('rating')
            ->count();

        $unpaidInvoices = $user->canViewInvoices()
            ? \App\Models\Invoice::visibleToCustomer($user)
                ->whereNotIn('status', ['paid', 'cancelled', 'draft'])
                ->count()
            : 0;

        $recentTickets = Ticket::visibleTo($user)->with('creator')->latest()->limit(5)->get();

        $unreadCount = \App\Models\TicketRead::unreadCountFor($user);

        return view('portal.dashboard', compact('needsAttention', 'resolvedCount', 'resolvedUnrated', 'unpaidInvoices', 'recentTickets', 'unreadCount'));
    }
}
