<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\TicketRead;
use App\Models\User;
use App\Services\TicketAttachmentService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تیکت‌های اپِ مشتری — دامنه و قواعد دقیقاً مثلِ پرتالِ وب (visibleTo،
 * canCreateTicket، canReceiveMessages، canBeRated، اختصاص به کارشناسِ خودِ مشتری).
 * کاربرِ مشتری «مدتِ کارکرد» و «یادداشت داخلی» ندارد.
 */
class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $statuses = array_values(array_intersect(
            (array) $request->input('status', []),
            array_keys(__('tickets.statuses')),
        ));

        $query = Ticket::visibleTo($user)->with(['category', 'project', 'creator']);

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $tickets = $query->latest()->paginate(20);
        $unread = TicketRead::unreadCountsFor($user, $tickets->pluck('id'));

        return response()->json([
            'data' => collect($tickets->items())->map(fn (Ticket $t) => $this->listItem($t, (int) ($unread[$t->id] ?? 0)))->all(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
            ],
        ]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);

        $messages = $ticket->publicMessages()->with(['user', 'attachments'])->orderBy('created_at')->get()
            ->map(fn (TicketMessage $m) => [
                'id'          => $m->id,
                'body'        => $m->body,
                'author'      => $m->user?->name ?? __('customers.label'),
                'is_support'  => $m->user?->isSupportUser() ?? true,
                'created_at_jalali' => Jalali::formatDateTime($m->created_at),
                'attachments' => $m->attachments->map(fn ($a) => $this->attachmentItem($a))->all(),
            ]);

        TicketRead::markRead($ticket, $user);

        return response()->json([
            'ticket'   => $this->detail($ticket),
            'messages' => $messages,
            'ticket_attachments' => $ticket->attachments()->whereNull('ticket_message_id')->get()
                ->map(fn ($a) => $this->attachmentItem($a))->all(),
            'assignee' => $ticket->customerAssignee?->name,
            'abilities' => [
                'reply'  => $ticket->canReceiveMessages(),
                'rate'   => $ticket->canBeRated(),
                'assign' => $user->isCustomerAdmin(),
            ],
            'rating' => $ticket->rating,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canCreateTicket(), 403, $user->customer?->suspensionNotice() ?? __('portal.no_access_new_ticket'));

        $data = $request->validate([
            'subject'             => ['required', 'string', 'max:255'],
            'description'         => ['required', 'string'],
            'ticket_category_id'  => ['nullable', 'exists:ticket_categories,id'],
            'customer_project_id' => ['nullable', 'exists:customer_projects,id'],
            'priority'            => ['nullable', 'in:low,normal,high,critical'],
            'attachments'         => ['nullable', 'array', 'max:10'],
            'attachments.*'       => TicketAttachmentService::validationRule(),
        ]);

        if (! empty($data['customer_project_id'])
            && ! in_array((int) $data['customer_project_id'], $user->accessibleProjectIds(), true)) {
            abort(403);
        }

        $category = ! empty($data['ticket_category_id']) ? TicketCategory::find($data['ticket_category_id']) : null;

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

        $this->storeFiles($request, $ticket, null, $user);

        return response()->json(['id' => $ticket->id, 'number' => $ticket->number], 201);
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_unless($ticket->canReceiveMessages(), 422, __('portal.ticket_resolved_notice'));

        $data = $request->validate([
            'body'          => ['required', 'string'],
            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => TicketAttachmentService::validationRule(),
        ]);

        $message = $ticket->messages()->create(['user_id' => $user->id, 'body' => $data['body'], 'is_internal' => false]);
        $this->storeFiles($request, $ticket, $message, $user);
        ActivityLog::record('portal_reply', $ticket);

        return response()->json(['message' => 'ok']);
    }

    public function rate(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_unless($ticket->canBeRated(), 403, __('portal.rating_closed'));

        $data = $request->validate([
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'rating_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket->update(['rating' => $data['rating'], 'rating_comment' => $data['rating_comment'] ?? null]);
        ActivityLog::record('ticket_rated', $ticket);

        return response()->json(['message' => 'ok']);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isCustomerAdmin(), 403);
        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);

        $data = $request->validate(['customer_assigned_to' => ['nullable', 'exists:users,id']]);

        if (! empty($data['customer_assigned_to'])) {
            $ok = User::whereKey($data['customer_assigned_to'])
                ->where('customer_id', $user->customer_id)
                ->where('user_type', User::TYPE_CUSTOMER_STAFF)
                ->where('is_active', true)->exists();
            abort_unless($ok, 422);
        }

        $ticket->update(['customer_assigned_to' => $data['customer_assigned_to'] ?: null]);
        ActivityLog::record('ticket_customer_assigned', $ticket);

        return response()->json(['message' => 'ok']);
    }

    /** دادهٔ فرمِ ساختِ تیکت: پروژه‌های در دسترس + دسته‌بندی + اولویت‌ها. */
    public function formData(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'projects' => $user->customer
                ? $user->customer->projects()->whereIn('id', $user->accessibleProjectIds())->get(['id', 'name'])
                : [],
            'categories' => TicketCategory::whereNotNull('parent_id')->where('is_active', true)->with('parent')->get()
                ->map(fn (TicketCategory $c) => ['id' => $c->id, 'name' => $c->fullName()]),
            'priorities' => __('tickets.priorities'),
        ]);
    }

    /** کارشناسانِ فعالِ همین مشتری — برای اختصاص (فقط مدیرِ مشتری). */
    public function staff(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isCustomerAdmin(), 403);

        return response()->json([
            'staff' => User::where('customer_id', $user->customer_id)
                ->where('user_type', User::TYPE_CUSTOMER_STAFF)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ------------------------------------------------------------ helpers

    private function storeFiles(Request $request, Ticket $ticket, ?TicketMessage $message, User $user): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }
        $service = app(TicketAttachmentService::class);
        foreach ($request->file('attachments') as $file) {
            $service->store($file, $ticket, $message, $user);
        }
    }

    private function listItem(Ticket $t, int $unread): array
    {
        return [
            'id'           => $t->id,
            'number'       => $t->number,
            'subject'      => $t->subject,
            'status'       => $t->status,
            'status_label' => __("tickets.statuses.$t->status"),
            'priority'     => $t->priority,
            'priority_label' => __("tickets.priorities.$t->priority"),
            'project'      => $t->project?->name,
            'creator'      => $t->creator?->name,
            'by_support'   => $t->isCreatedBySupport(),
            'unread'       => $unread,
            'created_at_jalali' => Jalali::format($t->created_at),
        ];
    }

    private function detail(Ticket $t): array
    {
        return array_merge($this->listItem($t, 0), [
            'description' => $t->description,
            'category'    => $t->category?->name,
        ]);
    }

    private function attachmentItem($a): array
    {
        return [
            'id'       => $a->id,
            'name'     => $a->original_name,
            'is_image' => str_starts_with((string) $a->mime, 'image/'),
            'url'      => route('api.attachments.show', $a->id),
        ];
    }
}
