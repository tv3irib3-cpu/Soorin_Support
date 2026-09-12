<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('customers.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('customers.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    // نامِ هر مشتری با لوگوی اختصاصی‌اش؛ اگر لوگو نداشت، دایرهٔ رنگی
                    // با حرفِ اولِ نام به‌عنوانِ آواتارِ جایگزین.
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $color = e($record->displayColor());

                        if ($record->hasLogo() && ($data = $record->logoData())) {
                            $avatar = '<img src="' . e($data) . '" alt="" '
                                . 'style="width:26px;height:26px;border-radius:7px;object-fit:contain;'
                                . 'background:#fff;border:1px solid rgba(0,0,0,.08);flex:none;">';
                        } else {
                            $initial = e(mb_substr((string) $state, 0, 1));
                            $avatar = '<span style="width:26px;height:26px;border-radius:7px;flex:none;'
                                . 'display:grid;place-items:center;color:#fff;font-size:12px;font-weight:700;'
                                . 'background:' . $color . ';">' . $initial . '</span>';
                        }

                        return '<span style="display:inline-flex;align-items:center;gap:9px;">'
                            . $avatar . e($state) . '</span>';
                    }),

                TextColumn::make('projects_count')
                    ->label(__('projects.plural'))
                    ->counts('projects')
                    ->badge()
                    // روی موبایل جا نمی‌شود
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('open_tickets_count')
                    ->label(__('customers.open_tickets'))
                    ->counts([
                        'tickets' => fn ($query) => $query->whereNotIn('status', ['closed', 'cancelled']),
                    ])
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('service_status')
                    ->label(__('customers.service_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("customers.service_statuses.$state"))
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'suspended' => 'danger',
                        default     => 'gray',
                    }),

                IconColumn::make('can_create_ticket')
                    ->label(__('customers.can_create_ticket'))
                    ->boolean()
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('city')
                    ->label(__('common.city'))
                    ->searchable()
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
            ])
            ->filters([
                SelectFilter::make('service_status')
                    ->label(__('customers.service_status'))
                    ->options(__('customers.service_statuses')),

                TrashedFilter::make()
                    ->label(__('common.trashed')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(__('customers.empty_heading'))
            ->emptyStateDescription(__('customers.empty_body'));
    }
}
