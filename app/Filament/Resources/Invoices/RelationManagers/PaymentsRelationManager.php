<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * پرداخت‌های ثبت‌شده روی فاکتور.
 * وضعیت فاکتور (paid / partially_paid) خودکار در Payment::booted به‌روز می‌شود.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('invoices.payments');
    }

    public function form(Schema $schema): Schema
    {
        $invoice = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('amount')
                ->label(__('invoices.amount'))
                ->numeric()
                ->required()
                ->maxValue($invoice->balance())
                ->helperText(__('invoices.balance') . ': ' . Jalali::money($invoice->balance()))
                ->suffix(__('common.currency')),

            DatePicker::make('paid_at')
                ->label(__('invoices.paid_at'))
                ->default(now())
                ->required(),

            Select::make('method')
                ->label(__('invoices.method'))
                ->options(__('invoices.methods'))
                ->default('transfer')
                ->required()
                ->native(false),

            TextInput::make('reference')
                ->label(__('invoices.reference'))
                ->maxLength(100),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('amount')
                    ->label(__('invoices.amount'))
                    ->formatStateUsing(fn ($state) => Jalali::money($state)),

                TextColumn::make('paid_at')
                    ->label(__('invoices.paid_at'))
                    ->formatStateUsing(fn ($state) => Jalali::format($state)),

                TextColumn::make('method')
                    ->label(__('invoices.method'))
                    ->formatStateUsing(fn (string $state) => __("invoices.methods.$state")),

                TextColumn::make('reference')->label(__('invoices.reference'))->placeholder('—'),

                TextColumn::make('registrar.name')->label(__('invoices.registered_by'))->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) {
                        $data['registered_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([DeleteAction::make()])
            ->emptyStateHeading(__('common.empty_state'));
    }
}
