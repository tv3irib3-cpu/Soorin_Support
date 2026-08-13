<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * تیکت‌های پرتال مشتری.
 *
 * دسترسی مشاهده از Ticket::visibleTo (همان منطق تست‌شده در پنل) خوانده
 * می‌شود؛ ثبت تیکت جدید از User::canCreateTicket که هر دو لایه دسترسی
 * (سازمان + حساب) و وضعیت خدمات‌دهی مشتری را بررسی می‌کند.
 */
class TicketController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $tickets = Ticket::visibleTo($user)
            ->with(['category', 'project'])
            ->latest()
            ->paginate(15);

        return view('portal.tickets.index', compact('tickets'));
    }

    public function create(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->canCreateTicket()) {
            return redirect()->route('portal.dashboard');
        }

        $categories = TicketCategory::whereNotNull('parent_id')->with('parent')->where('is_active', true)->get();
        $projects   = $user->customer->projects()->whereIn('id', $user->accessibleProjectIds())->get();

        return view('portal.tickets.create', compact('categories', 'projects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user->canCreateTicket(), 403, $user->customer?->suspensionNotice() ?? __('portal.no_access_new_ticket'));

        $data = $request->validate([
            'subject'              => ['required', 'string', 'max:255'],
            'description'          => ['required', 'string'],
            'ticket_category_id'   => ['nullable', 'exists:ticket_categories,id'],
            'customer_project_id'  => ['nullable', 'exists:customer_projects,id'],
            'priority'             => ['nullable', 'in:low,normal,high,critical'],
        ]);

        // پروژه انتخابی باید واقعاً در دسترس همین کاربر باشد
        if (! empty($data['customer_project_id'])
            && ! in_array((int) $data['customer_project_id'], $user->accessibleProjectIds(), true)) {
            abort(403);
        }

        $category = $data['ticket_category_id'] ?? null
            ? TicketCategory::find($data['ticket_category_id'])
            : null;

        $ticket = Ticket::create([
            'customer_id'         => $user->customer_id,
            'customer_project_id' => $data['customer_project_id'] ?? null,
            'ticket_category_id'  => $data['ticket_category_id'] ?? null,
            'subject'             => $data['subject'],
            'description'         => $data['description'],
            'service_type'        => $category?->service_type ?? 'hardware',
            'priority'            => $data['priority'] ?? 'normal',
            'created_by'          => $user->id,
        ]);

        return redirect()->route('portal.tickets.show', $ticket)
            ->with('status', __('portal.ticket_submitted', ['number' => $ticket->number]));
    }

    public function show(Ticket $ticket): View
    {
        $user = auth()->user();

        // اگر تیکت در دامنه دسترسی کاربر نباشد، ۴۰۴ می‌دهیم نه ۴۰۳ —
        // تا حتی وجود تیکت مشتری دیگر هم فاش نشود
        abort_unless(
            Ticket::visibleTo($user)->whereKey($ticket->id)->exists(),
            404,
        );

        $ticket->load(['publicMessages.user', 'category', 'project']);

        return view('portal.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_if($ticket->is_locked, 403, __('tickets.locked_notice'));

        $data = $request->validate(['body' => ['required', 'string']]);

        $ticket->messages()->create([
            'user_id'     => $user->id,
            'body'        => $data['body'],
            'is_internal' => false,
        ]);

        ActivityLog::record('portal_reply', $ticket);

        return back();
    }
}
