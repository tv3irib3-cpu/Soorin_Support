<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketsTable
{
    private const STATUS_COLORS = [
        'new'              => 'info',
        'in_progress'      => 'warning',
        'waiting_customer' => 'gray',
        'waiting_support'  => 'warning',
        'waiting_payment'  => 'danger',
        'resolved'         => 'success',
        'closed'           => 'gray',
        'cancelled'        => 'gray',
    ];

    private const PRIORITY_COLORS = [
        'low' => 'gray', 'normal' => 'info', 'high' => 'warning', 'critical' => 'danger',
    ];

    /** ترتیبِ منطقیِ اولویت (کم→بحرانی) برای سورت؛ نزولی = بحرانی بالاتر. */
    private const PRIORITY_ORDER = "'low','normal','high','critical'";

    /** ترتیبِ منطقیِ وضعیت در چرخهٔ کار برای سورت. */
    private const STATUS_ORDER = "'new','in_progress','waiting_support','waiting_customer','waiting_payment','resolved','cancelled','closed'";

    /**
     * زیرکوئریِ «تاریخِ آخرین پیامِ هر تیکت» — مقاوم به پیشوندِ جدول
     * (getQualifiedKeyName نامِ واقعیِ soorin_tickets.id را می‌دهد و مدلِ
     * TicketMessage جدولِ پیشونددارِ خودش را). هم برای نمایش و هم برای سورت.
     */
    private static function lastMessageSubquery(Builder $query)
    {
        return TicketMessage::select('created_at')
            ->whereColumn('ticket_id', $query->getModel()->getQualifiedKeyName())
            ->latest()
            ->limit(1);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('tickets.number'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('subject')
                    ->label(__('tickets.subject'))
                    ->searchable()
                    ->limit(40)
                    ->weight('medium'),

                // پیام‌های خوانده‌نشدهٔ همین تیکت برای کاربرِ فعلی — تا معلوم باشد
                // کدام تیکت پاسخِ تازه دارد، نه فقط مجموعِ کلیِ نشانِ منو.
                TextColumn::make('unread')
                    ->label(__('tickets.unread'))
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(fn (Ticket $record) => TicketRead::unreadForTicket($record, auth()->user()) ?: null)
                    ->formatStateUsing(fn ($state) => $state ? \App\Support\Jalali::digits((string) $state) : null),

                TextColumn::make('customer.name')
                    ->label(__('tickets.customer'))
                    ->searchable()
                    ->sortable()
                    // نقطهٔ رنگیِ مشتری برای تشخیصِ سریع در جدول.
                    ->html()
                    ->formatStateUsing(function ($state, Ticket $record) {
                        $color = e($record->customer?->displayColor() ?? '#94a3b8');
                        $name  = e($state ?? '—');

                        return '<span style="display:inline-flex;align-items:center;gap:7px;">'
                            . '<span style="width:10px;height:10px;border-radius:50%;background:' . $color . ';flex:none;"></span>'
                            . $name . '</span>';
                    }),

                TextColumn::make('project.name')
                    ->label(__('tickets.project'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                // سازندهٔ تیکت — بینِ «پروژه» و «دسته‌بندی». برای تفکیکِ تیکتِ مشتری
                // از تیکتِ ساخته‌شدهٔ پشتیبان (ورودی/خروجی).
                TextColumn::make('creator.name')
                    ->label(__('tickets.creator'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('category.name')
                    ->label(__('tickets.category'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                // اولویت همیشه دیده شود (نشانِ رنگیِ بحرانی/زیاد/...). سورت با ترتیبِ
                // منطقیِ اولویت: نزولی = بحرانی بالاتر، صعودی = کم بالاتر.
                TextColumn::make('priority')
                    ->label(__('tickets.priority'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("tickets.priorities.$state"))
                    ->color(fn (string $state) => self::PRIORITY_COLORS[$state] ?? 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw('FIELD(priority, ' . self::PRIORITY_ORDER . ') ' . ($direction === 'desc' ? 'DESC' : 'ASC'))),

                TextColumn::make('assignee.name')
                    ->label(__('tickets.assigned_to'))
                    ->placeholder(__('tickets.unassigned'))
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('status')
                    ->label(__('tickets.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("tickets.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw('FIELD(status, ' . self::STATUS_ORDER . ') ' . ($direction === 'desc' ? 'DESC' : 'ASC'))),

                IconColumn::make('sla')
                    ->label(__('tickets.sla_breached'))
                    ->getStateUsing(fn (Ticket $record) => $record->isSlaBreached())
                    // فقط وقتی واقعاً پاسخ معطل است علامتِ قرمز بخورد؛ در حالتِ عادی
                    // خالی می‌ماند تا با «چکِ» قبلی به‌اشتباه «معطل = بله» خوانده نشود.
                    ->icon(fn (bool $state) => $state ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('danger')
                    ->tooltip(fn (bool $state) => $state ? __('tickets.sla_breached_hint') : null),

                TextColumn::make('rating')
                    ->label(__('tickets.rating'))
                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state) : '—')
                    ->color('warning')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->formatStateUsing(fn ($state) => \App\Support\Jalali::format($state))
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                // تاریخِ آخرین پیام — از زیرکوئریِ last_message_at (که در
                // modifyQueryUsing انتخاب شده) خوانده می‌شود؛ سورت مستقیماً روی
                // همان زیرکوئری تا به اسم مستعار وابسته نباشد.
                TextColumn::make('last_message_at')
                    ->label(__('tickets.last_message_at'))
                    ->formatStateUsing(fn ($state) => $state
                        ? \App\Support\Jalali::formatDateTime(\Illuminate\Support\Carbon::parse($state))
                        : '—')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(self::lastMessageSubquery($query), $direction))
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('closed_at')
                    ->label(__('tickets.closed_at'))
                    // تیکتِ «حل‌شده» یعنی تمام‌شده؛ اگر هنوز رسماً بسته نشده، تاریخِ حل‌شدن
                    // را نشان می‌دهیم تا ستون برای تیکتِ حل‌شده خالی (—) نماند.
                    ->getStateUsing(fn (Ticket $record) => $record->closed_at ?? $record->resolved_at)
                    ->formatStateUsing(fn ($state) => $state ? \App\Support\Jalali::format($state) : '—')
                    ->placeholder('—')
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderByRaw('COALESCE(closed_at, resolved_at) ' . $direction))
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('tickets.status'))
                    ->options(__('tickets.statuses')),

                SelectFilter::make('priority')
                    ->label(__('tickets.priority'))
                    ->options(__('tickets.priorities')),

                SelectFilter::make('assigned_to')
                    ->label(__('tickets.assigned_to'))
                    ->options(fn () => User::whereIn('user_type', [User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF])
                        ->pluck('name', 'id')),

                Filter::make('sla_breached')
                    ->label(__('tickets.sla_breached'))
                    // محاسبه در PHP انجام می‌شود نه SQL خام — چون تاریخ (created_at +
                    // ساعت SLA) به شمارش ردیف کمی وابسته نیست و از خطای پیشوند جدول
                    // (DB_TABLE_PREFIX روی دیتابیس مشترک با وردپرس) در امان می‌ماند
                    ->query(function ($query) {
                        $ids = Ticket::whereNull('first_response_at')
                            ->whereNotIn('status', ['closed', 'cancelled'])
                            ->whereHas('contract.plan', fn ($q) => $q->whereNotNull('response_hours'))
                            ->with('contract.plan')
                            ->get()
                            ->filter(fn (Ticket $t) => $t->isSlaBreached())
                            ->pluck('id');

                        return $query->whereIn('id', $ids);
                    }),
            ])
            // زیرکوئریِ «تاریخِ آخرین پیام» را برای نمایش به کوئری می‌افزاییم تا هر
            // ردیف یک کوئریِ جدا نزند (بدونِ N+1).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->addSelect(['last_message_at' => self::lastMessageSubquery($query)]))
            // پیش‌فرض: تازه‌ترین تیکت بالا. سایرِ سورت‌ها (اولویت، وضعیت، شرکت،
            // تاریخِ آخرین پیام، …) با کلیک روی سرستون در دسترس است.
            ->defaultSort('created_at', 'desc')
            // رنگ‌بندیِ ردیف‌ها بر پایهٔ اولویت: بحرانی→قرمز، زیاد→نارنجی، عادی→آبی،
            // کم→بی‌رنگ. جداکنندهٔ خاکستریِ هر ردیف با کلاسِ پایهٔ ticket-row.
            ->recordClasses(fn (Ticket $record): string => 'ticket-row ticket-row-' . $record->priority)
            ->emptyStateHeading(__('tickets.empty_heading'))
            ->emptyStateDescription(__('tickets.empty_body'));
    }
}
