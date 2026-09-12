<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Jalali;
use Filament\Actions\DeleteAction;
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
                    ->alignCenter()
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label(__('invoices.customer'))
                    ->alignCenter()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payable_amount')
                    ->label(__('invoices.payable_amount'))
                    ->formatStateUsing(fn ($state) => Jalali::money($state))
                    ->alignCenter()
                    ->weight('bold'),

                // ماندهٔ بدهیِ هر فاکتور = قابل‌پرداخت منهای پرداخت‌شده.
                TextColumn::make('balance')
                    ->label(__('invoices.balance'))
                    ->getStateUsing(fn (Invoice $record) => $record->balance())
                    ->formatStateUsing(fn ($state) => Jalali::money($state))
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                // پوشش: نامِ پلنِ قرارداد با رنگِ خودش (به‌جای تیک/ضربدرِ گمراه‌کننده).
                TextColumn::make('coverage')
                    ->label(__('invoices.coverage'))
                    ->alignCenter()
                    ->html()
                    ->getStateUsing(function (Invoice $record) {
                        $plan = $record->effectiveContractPlan();

                        if (! $plan) {
                            return '<span style="color:#94a3b8">—</span>';
                        }

                        $c = e($plan->color ?: '#14b8a6');

                        return '<span style="display:inline-block;padding:2px 12px;border-radius:999px;'
                            . 'background:' . $c . '22;color:' . $c . ';border:1px solid ' . $c . ';font-weight:700;font-size:12px;">'
                            . e($plan->name) . '</span>';
                    })
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('status')
                    ->label(__('invoices.status'))
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn (string $state) => __("invoices.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),

                TextColumn::make('issue_date')
                    ->label(__('invoices.issue_date'))
                    ->formatStateUsing(fn ($state) => Jalali::format($state))
                    ->alignCenter()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('invoices.status'))
                    ->options(__('invoices.statuses')),
            ])
            ->recordActions([
                // حذفِ فاکتور فقط برای مدیرِ پشتیبان.
                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isSupportAdmin() ?? false),
            ])
            ->defaultSort('issue_date', 'desc')
            ->emptyStateHeading(__('invoices.empty_heading'))
            ->emptyStateDescription(__('invoices.empty_body'));
    }
}
