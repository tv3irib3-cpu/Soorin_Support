<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Support\Jalali;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('contracts.number'))
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label(__('contracts.customer'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan.name')
                    ->label(__('contracts.plan'))
                    ->badge()
                    ->color(fn ($record) => $record->plan?->color),

                TextColumn::make('end_date')
                    ->label(__('contracts.end_date'))
                    ->formatStateUsing(fn ($state) => Jalali::format($state))
                    ->sortable(),

                TextColumn::make('used_amount')
                    ->label(__('contracts.used_amount'))
                    ->formatStateUsing(fn ($state, $record) => Jalali::money($state)
                        . ($record->plan?->ceiling_amount ? ' / ' . Jalali::money($record->plan->ceiling_amount) : ''))
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('status')
                    ->label(__('contracts.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("contracts.statuses.$state"))
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'expired'   => 'gray',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('contracts.status'))
                    ->options(__('contracts.statuses')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('contracts.empty_heading'))
            ->emptyStateDescription(__('contracts.empty_body'));
    }
}
