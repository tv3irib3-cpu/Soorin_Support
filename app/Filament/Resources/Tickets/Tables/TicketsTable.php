<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\User;
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
        'waiting_payment'  => 'danger',
        'resolved'         => 'success',
        'closed'           => 'gray',
        'cancelled'        => 'gray',
    ];

    private const PRIORITY_COLORS = [
        'low' => 'gray', 'normal' => 'info', 'high' => 'warning', 'critical' => 'danger',
    ];

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

                TextColumn::make('customer.name')
                    ->label(__('tickets.customer'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('project.name')
                    ->label(__('tickets.project'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('category.name')
                    ->label(__('tickets.category'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('priority')
                    ->label(__('tickets.priority'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("tickets.priorities.$state"))
                    ->color(fn (string $state) => self::PRIORITY_COLORS[$state] ?? 'gray')
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('assignee.name')
                    ->label(__('tickets.assigned_to'))
                    ->placeholder(__('tickets.unassigned'))
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('status')
                    ->label(__('tickets.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("tickets.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),

                IconColumn::make('sla')
                    ->label(__('tickets.sla_breached'))
                    ->getStateUsing(fn (Ticket $record) => $record->isSlaBreached())
                    ->icon(fn (bool $state) => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (bool $state) => $state ? 'danger' : 'gray')
                    ->tooltip(fn (bool $state) => $state ? __('tickets.sla_breached_hint') : null),

                TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->formatStateUsing(fn ($state) => \App\Support\Jalali::format($state))
                    ->sortable()
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
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('tickets.empty_heading'))
            ->emptyStateDescription(__('tickets.empty_body'));
    }
}
