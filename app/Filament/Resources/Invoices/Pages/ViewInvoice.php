<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\IssueInvoice;
use App\Enums\Permission;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->label(__('invoices.number'))->fontFamily('mono'),
                    TextEntry::make('customer.name')->label(__('invoices.customer')),
                    TextEntry::make('status')
                        ->label(__('invoices.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state) => __("invoices.statuses.$state")),

                    TextEntry::make('issue_date')
                        ->label(__('invoices.issue_date'))
                        ->formatStateUsing(fn ($state) => Jalali::format($state)),
                    TextEntry::make('ticket.number')->label(__('invoices.ticket'))->placeholder('—'),
                    TextEntry::make('contract.number')->label(__('invoices.contract'))->placeholder('—'),
                ]),

            // سه عدد کلیدی — قاعده ثابت پروژه
            Section::make(__('invoices.summary'))
                ->columns(3)
                ->schema([
                    TextEntry::make('service_amount')
                        ->label(__('invoices.service_amount'))
                        ->formatStateUsing(fn ($state) => Jalali::money($state) . ' ' . __('common.currency')),

                    TextEntry::make('contract_amount')
                        ->label(__('invoices.contract_amount'))
                        ->formatStateUsing(fn ($state) => Jalali::money($state) . ' ' . __('common.currency')),

                    TextEntry::make('payable_amount')
                        ->label(__('invoices.payable_amount'))
                        ->formatStateUsing(fn ($state) => Jalali::money($state) . ' ' . __('common.currency'))
                        ->weight('bold')
                        ->color('success'),

                    TextEntry::make('paid_amount')
                        ->label(__('invoices.paid_amount'))
                        ->formatStateUsing(fn ($state) => Jalali::money($state) . ' ' . __('common.currency')),

                    TextEntry::make('balance')
                        ->label(__('invoices.balance'))
                        ->state(fn () => Jalali::money($invoice->balance()) . ' ' . __('common.currency')),

                    TextEntry::make('is_warranty')
                        ->label(__('invoices.is_warranty'))
                        ->formatStateUsing(fn ($state) => $state ? __('invoices.is_warranty_badge') : '—'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return [
            Action::make('issue')
                ->label(__('invoices.statuses.issued'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $invoice->status === Invoice::STATUS_DRAFT
                    && (auth()->user()?->can(Permission::ManageInvoices->value) ?? false))
                ->requiresConfirmation()
                ->action(function () use ($invoice) {
                    app(IssueInvoice::class)($invoice);

                    Notification::make()->success()->title(__('common.saved'))->send();
                }),

            Action::make('viewPdf')
                ->label(__('invoices.pdf'))
                ->icon('heroicon-o-document-text')
                ->visible(fn () => $invoice->status !== Invoice::STATUS_DRAFT)
                ->url(fn () => route('invoices.pdf.view', $invoice))
                ->openUrlInNewTab(),

            Action::make('downloadPdf')
                ->label(__('invoices.print'))
                ->icon('heroicon-o-printer')
                ->visible(fn () => $invoice->status !== Invoice::STATUS_DRAFT)
                ->url(fn () => route('invoices.pdf.download', $invoice))
                ->openUrlInNewTab(),

            Action::make('cancel')
                ->label(__('invoices.statuses.cancelled'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)
                    && (auth()->user()?->can(Permission::ManageInvoices->value) ?? false))
                ->requiresConfirmation()
                ->action(function () use ($invoice) {
                    $invoice->cancel();

                    Notification::make()->success()->title(__('common.saved'))->send();
                }),

            EditAction::make(),
        ];
    }
}
