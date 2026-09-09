<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketAttachmentService;
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

    /** شمارندهٔ پیام‌های خوانده‌نشده — برای به‌روزرسانیِ زندهٔ نشانِ منو (JSON). */
    public function unreadCount(): \Illuminate\Http\JsonResponse
    {
        $count = \App\Models\TicketRead::unreadCountFor(auth()->user());

        return response()->json([
            'count'   => $count,
            'display' => \App\Support\Jalali::digits((string) $count),
        ]);
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

        $request->validate([
            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => TicketAttachmentService::validationRule(),
        ]);

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

        // پیوست‌های تیکتِ جدید مستقیم به تیکت وصل می‌شوند (نه به پیام).
        $this->storeAttachments($request, $ticket, null, $user);

        $message = __('portal.ticket_submitted', ['number' => $ticket->number]);

        // اگر کاربر اجازه دیدن سوابق ندارد، هدایت به صفحه تیکت ۴۰۴ می‌دهد؛
        // به‌جایش با همان پیام موفقیت به صفحه اصلی پرتال برمی‌گردد.
        $canSeeIt = Ticket::visibleTo($user)->whereKey($ticket->id)->exists();

        return $canSeeIt
            ? redirect()->route('portal.tickets.show', $ticket)->with('status', $message)
            : redirect()->route('portal.dashboard')->with('status', $message);
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

        $ticket->load(['publicMessages.user', 'publicMessages.attachments', 'category', 'project']);

        // پیوست‌هایی که مستقیم به خودِ تیکت وصل‌اند (هنگام ثبتِ تیکت آپلود شده‌اند)
        $ticketAttachments = $ticket->attachments()->whereNull('ticket_message_id')->get();

        // علامت‌گذاریِ خواندهٔ گفتگو برای این کاربر (شمارندهٔ خوانده‌نشده صفر شود)
        \App\Models\TicketRead::markRead($ticket, $user);

        return view('portal.tickets.show', compact('ticket', 'ticketAttachments'));
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        // تیکتِ حل‌شده/بسته‌شده دیگر پیام نمی‌پذیرد — برای موضوعِ جدید تیکتِ تازه.
        abort_unless($ticket->canReceiveMessages(), 403, __('portal.ticket_resolved_notice'));

        $data = $request->validate([
            'body'          => ['required', 'string'],
            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => TicketAttachmentService::validationRule(),
        ]);

        // اطلاع‌رسانی ایمیل به کارشناس مسئول در App\Observers\TicketMessageObserver
        // متمرکز است — همان مسیری که پنل مدیریت هم از آن استفاده می‌کند.
        $message = $ticket->messages()->create([
            'user_id'     => $user->id,
            'body'        => $data['body'],
            'is_internal' => false,
        ]);

        $this->storeAttachments($request, $ticket, $message, $user);

        ActivityLog::record('portal_reply', $ticket);

        return back();
    }

    /**
     * ثبتِ امتیاز و نظرِ مشتری روی تیکتِ حل‌شده. بعد از ثبت دیگر قابلِ ویرایش
     * نیست (canBeRated فقط وقتی امتیاز خالی است true می‌شود). هم مدیرِ مشتری و
     * هم کارشناسِ مشتری اگر تیکت را ببینند می‌توانند ثبت کنند.
     */
    public function rate(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_unless($ticket->canBeRated(), 403, __('portal.rating_closed'));

        $data = $request->validate([
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'rating_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket->update([
            'rating'         => $data['rating'],
            'rating_comment' => $data['rating_comment'] ?? null,
        ]);

        ActivityLog::record('ticket_rated', $ticket);

        return back()->with('status', __('portal.rating_thanks'));
    }

    /** ذخیرهٔ فایل‌های آپلودشده (اگر باشند) با کدِ اختصاصی. */
    private function storeAttachments(Request $request, Ticket $ticket, ?TicketMessage $message, User $user): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $service = app(TicketAttachmentService::class);

        foreach ($request->file('attachments') as $file) {
            $service->store($file, $ticket, $message, $user);
        }
    }
}
