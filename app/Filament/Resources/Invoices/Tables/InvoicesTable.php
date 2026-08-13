<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Jalali;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    private const STATUS_COLORS = [
        'draft' => 'gray', 'issued' => 'info', 'paid' => 'success',
        'partially_paid' => 'warning', 'cancelled' => 'danger',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Invoice $record) => InvoiceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('invoices.number'))
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label(__('invoices.customer'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service_amount')
                    ->label(__('invoices.service_amount'))
                    ->formatStateUsing(fn ($state) => Jalali::money($state))
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('payable_amount')
                    ->label(__('invoices.payable_amount'))
                    ->formatStateUsing(fn ($state) => Jalali::money($state))
                    ->weight('bold'),

                IconColumn::make('is_warranty')
                    ->label(__('invoices.is_warranty'))
                    ->boolean()
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('status')
                    ->label(__('invoices.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("invoices.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),

                TextColumn::make('issue_date')
                    ->label(__('invoices.issue_date'))
                    ->formatStateUsing(fn ($state) => Jalali::format($state))
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('invoices.status'))
                    ->options(__('invoices.statuses')),
            ])
            ->defaultSort('issue_date', 'desc')
            ->emptyStateHeading(__('invoices.empty_heading'))
            ->emptyStateDescription(__('invoices.empty_body'));
    }
}
