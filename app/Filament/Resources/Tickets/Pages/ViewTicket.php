<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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

            Section::make(__('tickets.resolution'))
                ->visible(fn () => filled($ticket->resolution))
                ->schema([
                    TextEntry::make('resolution')->hiddenLabel(),
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
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        return [
            Action::make('changeStatus')
                ->label(__('tickets.change_status'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => ! empty($ticket->availableTransitions())
                    && (auth()->user()?->can(Permission::ManageTickets->value) ?? false))
                ->schema(fn () => [
                    Select::make('status')
                        ->label(__('tickets.status'))
                        ->options(collect($ticket->availableTransitions())
                            ->mapWithKeys(fn ($s) => [$s => __("tickets.statuses.$s")]))
                        ->required()
                        ->native(false),

                    Textarea::make('resolution')
                        ->label(__('tickets.resolution'))
                        ->rows(3)
                        // فقط وقتی مقصد «حل‌شده» است شرح راه‌حل لازم است
                        ->visible(fn ($get) => $get('status') === \App\Models\Ticket::STATUS_RESOLVED),

                    TextInput::make('work_minutes')
                        ->label(__('tickets.work_minutes'))
                        ->numeric()
                        ->default($ticket->work_minutes),
                ])
                ->action(function (array $data) use ($ticket) {
                    // بازخوانی برای جلوگیری از رقابت با تغییری که همزمان توسط کاربر دیگر ثبت شده
                    $ticket->refresh();
                    $from = $ticket->status;

                    if (! $ticket->canTransitionTo($data['status'])) {
                        Notification::make()
                            ->danger()
                            ->title(__('tickets.invalid_transition', ['from' => $from, 'to' => $data['status']]))
                            ->send();

                        return;
                    }

                    $ticket->update([
                        'status'       => $data['status'],
                        'resolution'   => $data['resolution'] ?? $ticket->resolution,
                        'work_minutes' => $data['work_minutes'] ?? $ticket->work_minutes,
                    ]);

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
                ->visible(fn () => ! $ticket->is_locked
                    && (auth()->user()?->can(Permission::AssignTickets->value) ?? false))
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

            EditAction::make(),
        ];
    }
}
