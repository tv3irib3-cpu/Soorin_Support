<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketRead;
use App\Models\User;
use App\Services\TicketAttachmentService;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * صفحه کار روی تیکت — اطلاعات کلی + اکشن‌های تغییر وضعیت و تخصیص کارشناس.
 * گفتگو و ضمیمه‌ها در RelationManagerها زیر همین صفحه نشان داده می‌شوند.
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($user = auth()->user()) {
            // باز کردنِ تیکت = خوانده‌شدنِ گفتگو برای این کاربر (شمارندهٔ خوانده‌نشده صفر شود).
            TicketRead::markRead($this->getRecord(), $user);

            // بازکردنِ تیکتِ «جدید» توسطِ پشتیبان → خودکار «در حال بررسی». (وضعیت‌ها
            // خودکار عوض می‌شوند؛ کارشناس تغییرِ وضعیتِ دستی ندارد.)
            $ticket = $this->getRecord();
            if ($user->isSupportUser() && $ticket->status === Ticket::STATUS_NEW) {
                $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
            }
        }
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->label(__('tickets.number'))->fontFamily('mono'),
                    TextEntry::make('customer.name')->label(__('tickets.customer')),
                    TextEntry::make('project.name')->label(__('tickets.project'))->placeholder('—'),

                    TextEntry::make('status')
                        ->label(__('tickets.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state) => __("tickets.statuses.$state")),
                    TextEntry::make('priority')
                        ->label(__('tickets.priority'))
                        ->badge()
                        ->formatStateUsing(fn (string $state) => __("tickets.priorities.$state")),
                    TextEntry::make('assignee.name')
                        ->label(__('tickets.assigned_to'))
                        ->placeholder(__('tickets.unassigned')),

                    TextEntry::make('category.name')->label(__('tickets.category'))->placeholder('—'),
                    TextEntry::make('created_at')
                        ->label(__('common.created_at'))
                        ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state)),
                    TextEntry::make('work_minutes')
                        ->label(__('tickets.work_minutes'))
                        ->suffix(' ' . __('common.minutes')),
                ]),

            Section::make(__('tickets.description'))
                ->schema([
                    TextEntry::make('description')->hiddenLabel(),
                ]),

            // گفتگو با مشتری — حباب‌های چت + دکمهٔ «پاسخ» در هدرِ همین بخش
            Section::make(__('tickets.conversation'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->headerActions([
                    $this->replyAction('replyInline'),
                ])
                ->schema([
                    ViewEntry::make('conversation')
                        ->hiddenLabel()
                        ->view('filament.tickets.conversation'),
                ]),

            Section::make(__('tickets.resolution'))
                ->visible(fn () => filled($ticket->resolution) || filled($ticket->method))
                ->schema([
                    TextEntry::make('resolution')
                        ->label(__('tickets.resolution'))
                        ->placeholder('—'),
                    TextEntry::make('method')
                        ->label(__('tickets.method'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => __("tickets.methods.$state") ?: $state)
                        ->visible(fn () => filled($ticket->method)),
                ]),

            Section::make(__('tickets.rating'))
                ->visible(fn () => $ticket->rating !== null)
                ->columns(2)
                ->schema([
                    TextEntry::make('rating')
                        ->label(__('tickets.rating'))
                        ->formatStateUsing(fn (?int $state) => $state
                            ? str_repeat('★', $state) . ' (' . Jalali::digits((string) $state) . '/۵)'
                            : '—')
                        ->color('warning'),
                    TextEntry::make('rating_comment')
                        ->label(__('tickets.rating_comment'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make()
                ->visible(fn () => $ticket->is_locked)
                ->icon('heroicon-o-lock-closed')
                ->schema([
                    TextEntry::make('locked_notice')
                        ->hiddenLabel()
                        ->state(__('tickets.locked_notice'))
                        ->color('warning'),
                ]),

            Section::make()
                ->visible(fn () => $ticket->isSlaBreached())
                ->icon('heroicon-o-exclamation-triangle')
                ->schema([
                    TextEntry::make('sla_notice')
                        ->hiddenLabel()
                        ->state(__('tickets.sla_breached_hint'))
                        ->color('danger'),
                ]),
        ]);
    }

    /**
     * اکشنِ «پاسخ به مشتری» — هم مدیرِ پشتیبان و هم کارشناسِ پشتیبان می‌توانند
     * پاسخ عمومی بدهند یا یادداشتِ داخلی بگذارند. ساختِ پیام، ثبتِ first_response_at
     * و ایمیل به مشتری در TicketMessageObserver متمرکز است (هر مسیر یکسان).
     */
    protected function replyAction(string $name = 'reply'): Action
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        return Action::make($name)
            ->label(__('tickets.reply'))
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn () => $ticket->canReceiveMessages()
                && (auth()->user()?->can(Permission::ViewTickets->value) ?? false))
            ->modalHeading(__('tickets.reply'))
            ->modalSubmitActionLabel(__('tickets.reply'))
            ->schema([
                Textarea::make('body')
                    ->label(__('tickets.reply'))
                    ->placeholder(__('tickets.reply_placeholder'))
                    ->required()
                    ->rows(4),

                // مدتِ زمانِ کارکردِ این پاسخ — اجباری؛ به مجموعِ کارکردِ تیکت اضافه می‌شود.
                TextInput::make('work_minutes')
                    ->label(__('tickets.reply_work_minutes'))
                    ->helperText(__('tickets.reply_work_minutes_hint'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                FileUpload::make('attachments')
                    ->label(__('tickets.attachments'))
                    ->helperText(__('tickets.attach_hint'))
                    ->multiple()
                    ->storeFiles(false)   // به‌جای ذخیرهٔ خودکار، خودمان با کدِ اختصاصی ذخیره می‌کنیم
                    ->maxSize(TicketAttachmentService::MAX_KB)
                    ->acceptedFileTypes(['image/*', 'video/*', 'application/pdf']),

                Toggle::make('is_internal')
                    ->label(__('tickets.internal_note'))
                    ->helperText(__('tickets.internal_note_hint'))
                    ->default(false),
            ])
            ->action(function (array $data) use ($ticket) {
                if (! $ticket->canReceiveMessages()) {
                    Notification::make()->danger()->title(__('tickets.locked_notice'))->send();

                    return;
                }

                app(\App\Services\TicketReplyService::class)->reply($ticket, auth()->user(), $data);

                Notification::make()->success()->title(__('common.saved'))->send();
            });
    }

    protected function getHeaderActions(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        return [
            // «مشکل حل شد» — برای کارشناس و مدیر. روشِ انجام را می‌پرسد و وضعیت را
            // «حل‌شده» می‌کند. (کارشناس تغییرِ وضعیتِ دستیِ دیگری ندارد؛ بقیهٔ وضعیت‌ها
            // خودکارند: بازکردنِ تیکتِ جدید→در حال بررسی، پاسخ→منتظر پاسخ مشتری.)
            Action::make('resolve')
                ->label(__('tickets.resolve_action'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->button()
                ->visible(fn () => $ticket->canReceiveMessages()
                    && (auth()->user()?->isSupportUser() ?? false))
                ->modalHeading(__('tickets.resolve_action'))
                ->modalSubmitActionLabel(__('tickets.resolve_action'))
                ->schema([
                    \Filament\Forms\Components\CheckboxList::make('method')
                        ->label(__('tickets.method'))
                        ->helperText(__('tickets.method_hint'))
                        ->options(__('tickets.methods'))
                        ->columns(2)
                        ->required(),

                    Textarea::make('resolution')
                        ->label(__('tickets.resolution'))
                        ->rows(3),
                ])
                ->action(function (array $data) use ($ticket) {
                    if (! $ticket->canReceiveMessages()) {
                        Notification::make()->danger()->title(__('tickets.locked_notice'))->send();

                        return;
                    }

                    $ticket->update([
                        'status'     => Ticket::STATUS_RESOLVED,
                        'method'     => array_values((array) $data['method']),
                        'resolution' => $data['resolution'] ?? $ticket->resolution,
                    ]);

                    Notification::make()->success()->title(__('tickets.resolved_done'))->send();
                }),

            Action::make('changeStatus')
                ->label(__('tickets.change_status'))
                ->icon('heroicon-o-arrow-path')
                // تغییرِ وضعیتِ دستیِ کامل فقط برای مدیرِ پشتیبان (کارشناس ندارد).
                ->visible(fn () => auth()->user()?->isSupportAdmin() ?? false)
                ->schema(fn () => [
                    Select::make('status')
                        ->label(__('tickets.status'))
                        // همهٔ وضعیت‌ها به‌جز وضعیتِ فعلی و «جدید» — «جدید» فقط هنگامِ
                        // ثبتِ تیکت توسطِ مشتری معنی دارد و پشتیبان نباید تیکت را دوباره
                        // «جدید» کند.
                        ->options(collect(__('tickets.statuses'))
                            ->except([$this->getRecord()->status, \App\Models\Ticket::STATUS_NEW])
                            ->all())
                        ->required()
                        ->native(false)
                        ->live(),

                    Textarea::make('resolution')
                        ->label(__('tickets.resolution'))
                        ->rows(3)
                        // فقط وقتی مقصد «حل‌شده» است شرح راه‌حل لازم است
                        ->visible(fn ($get) => $get('status') === \App\Models\Ticket::STATUS_RESOLVED),

                    // روش انجام — چندانتخابی، فقط هنگامِ حل‌شدنِ تیکت پرسیده می‌شود و
                    // اجباری است (حداقل یک مورد). یک تیکت ممکن است ترکیبی حل شود.
                    \Filament\Forms\Components\CheckboxList::make('method')
                        ->label(__('tickets.method'))
                        ->options(__('tickets.methods'))
                        ->columns(2)
                        ->visible(fn ($get) => $get('status') === \App\Models\Ticket::STATUS_RESOLVED)
                        ->required(fn ($get) => $get('status') === \App\Models\Ticket::STATUS_RESOLVED),
                ])
                ->action(function (array $data) use ($ticket) {
                    // بازخوانی برای جلوگیری از رقابت با تغییری که همزمان توسط کاربر دیگر ثبت شده
                    $ticket->refresh();
                    $from = $ticket->status;

                    // فقط باید یک وضعیتِ معتبر و متفاوت با وضعیتِ فعلی باشد (بدونِ محدودیتِ
                    // نقشهٔ گذار — مدیر آزادیِ کامل دارد). TicketObserver خودش قفل/تاریخ‌ها
                    // را بر اساس وضعیتِ جدید تنظیم می‌کند.
                    // «جدید» توسطِ پشتیبان قابلِ تنظیم نیست (فقط هنگامِ ثبتِ مشتری).
                    if (! array_key_exists($data['status'], __('tickets.statuses'))
                        || $data['status'] === $from
                        || $data['status'] === \App\Models\Ticket::STATUS_NEW) {
                        Notification::make()
                            ->danger()
                            ->title(__('tickets.invalid_transition', ['from' => $from, 'to' => $data['status']]))
                            ->send();

                        return;
                    }

                    $payload = [
                        'status'     => $data['status'],
                        'resolution' => $data['resolution'] ?? $ticket->resolution,
                    ];

                    // روش انجام فقط هنگامِ حل‌شدن ثبت می‌شود.
                    if ($data['status'] === \App\Models\Ticket::STATUS_RESOLVED && filled($data['method'] ?? null)) {
                        $payload['method'] = array_values((array) $data['method']);
                    }

                    $ticket->update($payload);

                    Notification::make()
                        ->success()
                        ->title(__('tickets.status_changed', [
                            'from' => __("tickets.statuses.$from"),
                            'to'   => __("tickets.statuses.{$data['status']}"),
                        ]))
                        ->send();
                }),

            Action::make('assign')
                ->label(__('tickets.assign'))
                ->icon('heroicon-o-user-plus')
                // تخصیص/بازتخصیص با مجوزِ «تخصیص کارشناس» کنترل می‌شود — پیش‌فرض فقط
                // مدیر آن را دارد؛ ولی مدیر می‌تواند به کارشناسِ خاصی هم بدهد.
                ->visible(fn () => ! $ticket->is_locked
                    && (auth()->user()?->can(\App\Enums\Permission::AssignTickets->value) ?? false))
                ->schema([
                    Select::make('assigned_to')
                        ->label(__('tickets.assigned_to'))
                        ->options(fn () => User::whereIn('user_type', [User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF])
                            ->pluck('name', 'id'))
                        ->native(false)
                        ->required(),
                ])
                ->fillForm(fn () => ['assigned_to' => $ticket->assigned_to])
                ->action(function (array $data) use ($ticket) {
                    $ticket->update(['assigned_to' => $data['assigned_to']]);
                    ActivityLog::record('assigned', $ticket, ['assigned_to' => $data['assigned_to']]);

                    Notification::make()->success()->title(__('common.saved'))->send();
                }),

            Action::make('createInvoice')
                ->label(__('tickets.create_invoice'))
                ->icon('heroicon-o-receipt-percent')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can(Permission::ManageInvoices->value) ?? false)
                ->url(fn () => InvoiceResource::getUrl('create', ['ticket' => $ticket->id])),

            // نظرخواهی مجدد — نظرِ قبلیِ مشتری را پاک می‌کند تا دوباره امکانِ ثبت باشد
            // (اگر مشتری اشتباهی امتیاز داد و خواست اصلاح شود). فقط مدیرِ پشتیبان.
            Action::make('resetRating')
                ->label(__('tickets.reset_rating'))
                ->icon('heroicon-o-star')
                ->color('warning')
                ->visible(fn () => $ticket->rating !== null
                    && (auth()->user()?->can(Permission::ManageTickets->value) ?? false))
                ->requiresConfirmation()
                ->modalHeading(__('tickets.reset_rating'))
                ->modalDescription(__('tickets.reset_rating_confirm'))
                ->action(function () use ($ticket) {
                    $ticket->update(['rating' => null, 'rating_comment' => null]);
                    ActivityLog::record('rating_reset', $ticket);

                    Notification::make()->success()->title(__('tickets.reset_rating_done'))->send();
                }),

            EditAction::make(),
        ];
    }
}
