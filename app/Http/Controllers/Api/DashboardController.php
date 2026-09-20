<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * داشبوردِ اپ — همان سه‌باکسِ پنل (بدونِ هم‌پوشانی) + شمارندهٔ خوانده‌نشده.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mine = fn () => Ticket::visibleTo($user);

        $needs    = (clone $mine())->where('status', Ticket::STATUS_WAITING_SUPPORT)->count();
        $open     = (clone $mine())->whereIn('status', [Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_WAITING_CUSTOMER])->count();
        $resolved = (clone $mine())->where('status', Ticket::STATUS_RESOLVED)->count();

        $canViewInvoices = $user->can(Permission::ViewInvoices->value);

        $unpaid = 0;
        if ($canViewInvoices) {
            $unpaid = Invoice::whereNotIn('status', ['paid', 'cancelled', 'draft'])->count();
        }

        return response()->json([
            'needs_attention'   => $needs,
            'open'              => $open,
            'resolved'          => $resolved,
            'unpaid_invoices'   => $unpaid,
            'unread'            => TicketRead::unreadCountFor($user),
            'can_view_invoices' => $canViewInvoices,
            'can_view_customers'=> $user->can(Permission::ViewCustomers->value),
            'can_create_ticket' => $user->can(Permission::CreateTickets->value),
        ]);
    }
}
