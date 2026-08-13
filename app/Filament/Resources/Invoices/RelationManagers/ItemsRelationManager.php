<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ردیف‌های فاکتور.
 *
 * درصد پوشش قرارداد برای هر ردیف به‌طور خودکار محاسبه می‌شود:
 *   - ردیف «قطعه» همیشه از نرخ cover_parts استفاده می‌کند
 *   - ردیف «خدمت» از نوع خدمت **تیکت مرتبط** استفاده می‌کند
 *     (اگر فاکتور مستقل بدون تیکت باشد، پیش‌فرض سخت‌افزاری در نظر گرفته
 *     می‌شود — فاکتور مستقل معمولاً برای مشتری بدون قرارداد صادر می‌شود)
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('invoices.items');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('item_type')
                ->label(__('invoices.item_type'))
                ->options(__('invoices.item_types'))
                ->default('service')
                ->required()
                ->native(false)
                ->live(),

            TextInput::make('title')
                ->label(__('invoices.item_title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('part_code')
                ->label(__('customers.code'))
                ->helperText(__('invoices.part_code_hint'))
                ->visible(fn ($get) => $get('item_type') === 'part')
                ->maxLength(40),

            TextInput::make('quantity')
                ->label(__('invoices.quantity'))
                ->numeric()
                ->default(1)
                ->required(),

            TextInput::make('unit_price')
                ->label(__('invoices.unit_price'))
                ->numeric()
                ->required()
                ->suffix(__('common.currency')),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('item_type')
                    ->label(__('invoices.item_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("invoices.item_types.$state")),

                TextColumn::make('title')->label(__('invoices.item_title'))->weight(FontWeight::Medium),

                TextColumn::make('quantity')->label(__('invoices.quantity')),

                TextColumn::make('unit_price')
                    ->label(__('invoices.unit_price'))
                    ->formatStateUsing(fn ($state) => \App\Support\Jalali::money($state)),

                TextColumn::make('contract_cover_percent')
                    ->label(__('invoices.cover_percent'))
                    ->suffix('٪'),

                TextColumn::make('line_total')
                    ->label(__('invoices.line_total'))
                    ->formatStateUsing(fn ($state) => \App\Support\Jalali::money($state))
                    ->weight(FontWeight::Bold),
            ])
            ->headerActions([
                CreateAction::make()
                    ->disabled(fn () => $this->getOwnerRecord()->status !== Invoice::STATUS_DRAFT
                        && $this->getOwnerRecord()->status !== Invoice::STATUS_ISSUED)
                    ->after(fn (InvoiceItem $record) => $this->recalculateChain($record)),
            ])
            ->recordActions([
                EditAction::make()->after(fn (InvoiceItem $record) => $this->recalculateChain($record)),
                DeleteAction::make()->after(fn () => $this->recalculateInvoiceOnly()),
            ])
            ->emptyStateHeading(__('common.empty_state'));
    }

    /** بعد از ساخت/ویرایش ردیف، هم خودِ ردیف و هم جمع فاکتور محاسبه می‌شود. */
    private function recalculateChain(InvoiceItem $item): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getOwnerRecord();
        $ticket  = $invoice->ticket;

        $item->recalculate(
            plan: $invoice->contract?->plan,
            serviceType: $ticket->service_type ?? 'hardware',
            method: $ticket->method ?? null,
        );

        $invoice->recalculate();
    }

    private function recalculateInvoiceOnly(): void
    {
        $this->getOwnerRecord()->recalculate();
    }
}
