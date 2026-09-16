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

        // همان سه‌باکسِ داشبوردِ پشتیبان، بدونِ هم‌پوشانی:
        //   نیازمندِ رسیدگی = در انتظار پاسخ پشتیبان
        //   باز            = منتظر پاسخ مشتری یا در حال بررسی
        //   حل‌شده         = فقط حل‌شده
        $needsAttention = Ticket::visibleTo($user)->where('status', Ticket::STATUS_WAITING_SUPPORT)->count();
        $openTickets    = Ticket::visibleTo($user)->whereIn('status', [Ticket::STATUS_WAITING_CUSTOMER, Ticket::STATUS_IN_PROGRESS])->count();
        $resolvedCount  = Ticket::visibleTo($user)->where('status', Ticket::STATUS_RESOLVED)->count();

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

        $recentTickets = Ticket::visibleTo($user)->with('creator')->latest()->limit(5)->get();

        $unreadCount = \App\Models\TicketRead::unreadCountFor($user);

        return view('portal.dashboard', compact('needsAttention', 'openTickets', 'resolvedCount', 'resolvedUnrated', 'unpaidInvoices', 'recentTickets', 'unreadCount'));
    }
}
