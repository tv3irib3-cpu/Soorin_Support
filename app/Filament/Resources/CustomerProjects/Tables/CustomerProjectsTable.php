<?php

namespace App\Filament\Resources\CustomerProjects\Tables;

use App\Support\Jalali;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('projects.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    // لوگوی مشتریِ همین پروژه کنارِ نام، و نامِ پروژه با رنگِ مشتری هایلایت.
                    ->html()
                    ->formatStateUsing(fn ($state, $record) => \App\Support\CustomerBadge::nameWithColor(
                        (string) $state,
                        $record->customer?->displayColor() ?? '#94a3b8',
                        $record->customer?->hasLogo() ? $record->customer->logoData() : null,
                        $record->customer?->name,   // حرفِ اولِ آواتار از نامِ مشتری
                    )),

                TextColumn::make('customer.name')
                    ->label(__('projects.customer'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('code')
                    ->label(__('projects.code'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('city')
                    ->label(__('projects.city'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                // tickets_count از withCount در getEloquentQuery می‌آید (نه counts روی ستون).
                TextColumn::make('tickets_count')
                    ->label(__('projects.tickets_count'))
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => \App\Support\Jalali::digits((string) ((int) $state)))
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label(__('projects.assigned_users'))
                    ->counts('users')
                    ->badge()
                    ->color('gray')
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('start_date')
                    ->label(__('projects.start_date'))
                    ->formatStateUsing(fn ($state) => $state ? Jalali::format($state) : '—')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                IconColumn::make('is_active')
                    ->label(__('projects.is_active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label(__('projects.customer'))
                    ->relationship('customer', 'name')->multiple()
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label(__('projects.is_active')),

                TrashedFilter::make()
                    ->label(__('common.trashed')),
            ])
            // پیش‌فرض: پروژه‌های هر مشتری زیرِ هم (اول بر پایهٔ مشتری، بعد نامِ پروژه).
            // با کلیک روی هر ستونِ قابلِ‌سورت، این ترتیب کنار می‌رود.
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('customer_id')->orderBy('name'))
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('projects.empty_heading'))
            ->emptyStateDescription(__('projects.empty_body'));
    }
}
