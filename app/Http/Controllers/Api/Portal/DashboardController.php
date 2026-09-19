<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * داشبوردِ اپِ مشتری — دو باکس (نیازمند رسیدگی/حل‌شده) + خوانده‌نشده + فاکتورِ
 * پرداخت‌نشده + حل‌شدهٔ بدونِ امتیاز (هم‌راستا با پرتالِ وب).
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $active = [
            Ticket::STATUS_WAITING_SUPPORT,
            Ticket::STATUS_WAITING_CUSTOMER,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_WAITING_PAYMENT,
        ];

        $needs    = Ticket::visibleTo($user)->whereIn('status', $active)->count();
        $resolved = Ticket::visibleTo($user)->where('status', Ticket::STATUS_RESOLVED)->count();
        $unrated  = Ticket::visibleTo($user)->where('status', Ticket::STATUS_RESOLVED)->whereNull('rating')->count();

        $unpaid = $user->canViewInvoices()
            ? Invoice::visibleToCustomer($user)->whereNotIn('status', ['paid', 'cancelled', 'draft'])->count()
            : 0;

        return response()->json([
            'needs_attention'  => $needs,
            'resolved'         => $resolved,
            'resolved_unrated' => $unrated,
            'unpaid_invoices'  => $unpaid,
            'unread'           => TicketRead::unreadCountFor($user),
            'can_view_invoices' => $user->canViewInvoices(),
            'can_create_ticket' => $user->canCreateTicket(),
        ]);
    }
}
