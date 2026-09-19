<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\TicketRead;
use App\Models\User;
use App\Services\TicketAttachmentService;
use App\Services\TicketReplyService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تیکت‌ها برای اپِ موبایل. دامنهٔ دید و قواعد دقیقاً مثلِ پنل (Ticket::visibleTo،
 * TicketReplyService، همان قواعدِ وضعیت) تا رفتار یکسان بماند.
 */
class TicketController extends Controller
{
    /** فهرست — با فیلترِ اختیاریِ وضعیت و صفحه‌بندی. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $statuses = array_values(array_intersect(
            (array) $request->input('status', []),
            array_keys(__('tickets.statuses')),
        ));

        $query = Ticket::visibleTo($user)->with(['customer', 'project', 'creator']);

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        // مرتب‌سازی بر پایهٔ آخرین فعالیت — زیرکوئریِ مقاوم به پیشوندِ جدول (مثلِ پنل)؛
        // نامِ جدول را دستی ننوشتیم تا با DB_TABLE_PREFIX (soorin_) نشکند.
        $query->addSelect(['last_message_at' => TicketMessage::select('created_at')
            ->whereColumn('ticket_id', (new Ticket)->getQualifiedKeyName())
            ->latest()
            ->limit(1)])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC');

        $tickets = $query->paginate(20);

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

    /** جزئیاتِ یک تیکت + گفتگو. */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);

        // بازکردنِ تیکتِ «جدید» توسطِ پشتیبان → «در حال بررسی» (مثلِ mountِ پنل).
        if ($user->isSupportUser() && $ticket->status === Ticket::STATUS_NEW) {
            $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        }

        $canSeeInternal = $user->isSupportUser();

        $messages = $ticket->messages()
            ->when(! $canSeeInternal, fn ($q) => $q->where('is_internal', false))
            ->with(['user', 'attachments'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketMessage $m) => [
                'id'          => $m->id,
                'body'        => $m->body,
                'is_internal' => (bool) $m->is_internal,
                'author'      => $m->user?->name ?? __('customers.label'),
                'is_support'  => $m->user?->isSupportUser() ?? true,
                'created_at'  => optional($m->created_at)->toIso8601String(),
                'created_at_jalali' => Jalali::formatDateTime($m->created_at),
                'attachments' => $m->attachments->map(fn ($a) => $this->attachmentItem($a))->all(),
            ]);

        TicketRead::markRead($ticket, $user);

        return response()->json([
            'ticket'   => $this->detail($ticket),
            'messages' => $messages,
            'ticket_attachments' => $ticket->attachments()->whereNull('ticket_message_id')->get()
                ->map(fn ($a) => $this->attachmentItem($a))->all(),
            'abilities' => [
                'reply'         => $ticket->canReceiveMessages() && $user->can(Permission::ViewTickets->value),
                'internal_note' => $user->can(Permission::InternalNotes->value),
                'resolve'       => $ticket->canReceiveMessages() && $user->isSupportUser(),
                'change_status' => $user->isSupportAdmin(),
                'assign'        => $user->can(Permission::AssignTickets->value),
            ],
        ]);
    }

    /** پاسخ به تیکت (متن + مدتِ کارکرد + پیوست‌ها). */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_unless($user->can(Permission::ViewTickets->value), 403);
        abort_unless($ticket->canReceiveMessages(), 422, __('tickets.locked_notice'));

        $data = $request->validate([
            'body'          => ['required', 'string'],
            'work_minutes'  => ['required', 'integer', 'min:0'],
            'is_internal'   => ['nullable', 'boolean'],
            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => TicketAttachmentService::validationRule(),
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false) && $user->can(Permission::InternalNotes->value);

        app(TicketReplyService::class)->reply($ticket, $user, [
            'body'         => $data['body'],
            'work_minutes' => $data['work_minutes'],
            'is_internal'  => $isInternal,
            'attachments'  => $request->file('attachments', []),
        ]);

        return response()->json(['message' => 'ok']);
    }

    /** ساختِ تیکتِ تازه توسطِ پشتیبان. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can(Permission::CreateTickets->value), 403);

        $data = $request->validate([
            'customer_id'         => ['required', 'exists:customers,id'],
            'subject'             => ['required', 'string', 'max:255'],
            'description'         => ['required', 'string'],
            'customer_project_id' => ['nullable', 'exists:customer_projects,id'],
            'ticket_category_id'  => ['nullable', 'exists:ticket_categories,id'],
            'priority'            => ['nullable', 'in:low,normal,high,critical'],
            'attachments'         => ['nullable', 'array', 'max:10'],
            'attachments.*'       => TicketAttachmentService::validationRule(),
        ]);

        // پروژه باید متعلق به همان مشتری باشد.
        if (! empty($data['customer_project_id'])) {
            $ok = \App\Models\CustomerProject::whereKey($data['customer_project_id'])
                ->where('customer_id', $data['customer_id'])->exists();
            abort_unless($ok, 422, __('tickets.project_customer_mismatch'));
        }

        $category = ! empty($data['ticket_category_id']) ? TicketCategory::find($data['ticket_category_id']) : null;

        $ticket = Ticket::create([
            'customer_id'         => $data['customer_id'],
            'subject'             => $data['subject'],
            'description'         => $data['description'],
            'customer_project_id' => $data['customer_project_id'] ?? null,
            'ticket_category_id'  => $data['ticket_category_id'] ?? null,
            'service_type'        => $category?->service_type ?? 'hardware',
            'priority'            => $data['priority'] ?? 'normal',
            'created_by'          => $user->id,
        ]);

        if ($request->hasFile('attachments')) {
            $service = app(TicketAttachmentService::class);
            foreach ($request->file('attachments') as $file) {
                $service->store($file, $ticket, null, $user);
            }
        }

        return response()->json(['id' => $ticket->id, 'number' => $ticket->number], 201);
    }

    /** تخصیص/بازتخصیصِ تیکت به کارشناسِ پشتیبان — با مجوزِ «تخصیص کارشناس». */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can(Permission::AssignTickets->value), 403);
        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_if($ticket->is_locked, 422, __('tickets.locked_notice'));

        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);

        if (! empty($data['assigned_to'])) {
            $ok = User::whereKey($data['assigned_to'])
                ->whereIn('user_type', [User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF])
                ->where('is_active', true)->exists();
            abort_unless($ok, 422);
        }

        $ticket->update(['assigned_to' => $data['assigned_to'] ?: null]);
        ActivityLog::record('assigned', $ticket, ['assigned_to' => $data['assigned_to'] ?: null]);

        return response()->json(['message' => 'ok']);
    }

    /** کارشناسانِ پشتیبانِ فعال — برای فرمِ تخصیص (فقط دارندهٔ مجوزِ تخصیص). */
    public function staff(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can(Permission::AssignTickets->value), 403);

        return response()->json([
            'staff' => User::whereIn('user_type', [User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF])
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** «مشکل حل شد» — روش‌های انجام (اجباری) + شرحِ راه‌حل. */
    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        abort_unless($user->isSupportUser(), 403);
        abort_unless($ticket->canReceiveMessages(), 422, __('tickets.locked_notice'));

        $data = $request->validate([
            'method'     => ['required', 'array', 'min:1'],
            'method.*'   => ['in:' . implode(',', array_keys(__('tickets.methods')))],
            'resolution' => ['nullable', 'string'],
        ]);

        $ticket->update([
            'status'     => Ticket::STATUS_RESOLVED,
            'method'     => array_values($data['method']),
            'resolution' => $data['resolution'] ?? $ticket->resolution,
        ]);

        return response()->json(['message' => 'ok']);
    }

    /** تغییرِ وضعیتِ دستی — فقط مدیرِ پشتیبان. */
    public function changeStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSupportAdmin(), 403);
        abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);

        $data = $request->validate([
            'status'     => ['required', 'string'],
            'resolution' => ['nullable', 'string'],
            'method'     => ['nullable', 'array'],
            'method.*'   => ['in:' . implode(',', array_keys(__('tickets.methods')))],
        ]);

        $ticket->refresh();
        $from = $ticket->status;

        if (! array_key_exists($data['status'], __('tickets.statuses'))
            || $data['status'] === $from
            || $data['status'] === Ticket::STATUS_NEW) {
            return response()->json([
                'message' => __('tickets.invalid_transition', ['from' => $from, 'to' => $data['status']]),
            ], 422);
        }

        $payload = [
            'status'     => $data['status'],
            'resolution' => $data['resolution'] ?? $ticket->resolution,
        ];

        if ($data['status'] === Ticket::STATUS_RESOLVED && filled($data['method'] ?? null)) {
            $payload['method'] = array_values($data['method']);
        }

        $ticket->update($payload);

        return response()->json(['message' => 'ok']);
    }

    /** گزینه‌های وضعیت/اولویت/روش — برای فرمِ پاسخ و تغییر وضعیت. */
    public function meta(Request $request): JsonResponse
    {
        return response()->json([
            'statuses'   => __('tickets.statuses'),
            'priorities' => __('tickets.priorities'),
            'methods'    => __('tickets.methods'),
        ]);
    }

    /** دادهٔ فرمِ ساختِ تیکت — فهرستِ مشتریان (برای پشتیبان) + اولویت‌ها. */
    public function formData(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can(Permission::CreateTickets->value), 403);

        // پروژه‌های یک مشتری فقط وقتی خواسته شود (پس از انتخابِ مشتری در فرم).
        $projects = [];
        if ($customerId = $request->integer('customer_id')) {
            $projects = \App\Models\CustomerProject::where('customer_id', $customerId)
                ->orderBy('name')->get(['id', 'name']);
        }

        return response()->json([
            'customers'  => Customer::orderBy('name')->get(['id', 'name']),
            'categories' => TicketCategory::whereNotNull('parent_id')->where('is_active', true)->with('parent')->get()
                ->map(fn (TicketCategory $c) => ['id' => $c->id, 'name' => $c->fullName()]),
            'projects'   => $projects,
            'priorities' => __('tickets.priorities'),
        ]);
    }

    // ------------------------------------------------------------ presenters

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
            'customer'     => $t->customer?->name,
            'customer_color' => $t->customer?->displayColor(),
            'project'      => $t->project?->name,
            'by_support'   => $t->isCreatedBySupport(),
            'unread'       => $unread,
            'created_at'   => optional($t->created_at)->toIso8601String(),
            'created_at_jalali' => Jalali::format($t->created_at),
        ];
    }

    private function detail(Ticket $t): array
    {
        return array_merge($this->listItem($t, 0), [
            'description'  => $t->description,
            'creator'      => $t->creator?->name,
            'assignee'     => $t->assignee?->name,
            'category'     => $t->category?->name,
            'work_minutes' => (int) $t->work_minutes,
            'method'       => (array) $t->method,
            'resolution'   => $t->resolution,
        ]);
    }

    private function attachmentItem($a): array
    {
        return [
            'id'        => $a->id,
            'name'      => $a->original_name,
            'size'      => (int) $a->size,
            'mime'      => $a->mime,
            'is_image'  => str_starts_with((string) $a->mime, 'image/'),
            'url'       => route('api.attachments.show', $a->id),
        ];
    }
}
