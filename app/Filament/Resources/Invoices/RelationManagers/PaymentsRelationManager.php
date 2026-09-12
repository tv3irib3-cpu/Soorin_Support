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

    /**
     * به‌صورتِ پیش‌فرض، RelationManagerها روی صفحهٔ «مشاهده» فقط‌خواندنی‌اند، برای
     * همین دکمهٔ «ثبت پرداخت» دیده نمی‌شد. اینجا برای کاربرِ دارای مجوزِ ثبتِ پرداخت
     * قابلِ‌ویرایش می‌شود تا پرداخت‌ها از همان صفحهٔ فاکتور ثبت شوند.
     */
    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can(\App\Enums\Permission::ManagePayments->value) ?? false);
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
                    ->label(__('invoices.add_payment'))
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading(__('invoices.add_payment'))
                    ->mutateDataUsing(function (array $data) {
                        $data['registered_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([DeleteAction::make()])
            ->emptyStateHeading(__('invoices.no_payments'))
            ->emptyStateDescription(__('invoices.no_payments_hint'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
