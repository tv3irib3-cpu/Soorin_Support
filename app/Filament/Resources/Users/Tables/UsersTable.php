<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('users.name'))->searchable()->weight('medium'),
                TextColumn::make('email')->label(__('users.email'))->searchable(),
                TextColumn::make('user_type')
                    ->label(__('users.user_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("auth.types.$state"))
                    ->color(fn (string $state) => str_starts_with($state, 'support_') ? 'info' : 'gray'),
                TextColumn::make('customer.name')
                    ->label(__('users.customer'))
                    ->placeholder('—'),
                TextColumn::make('last_login_at')
                    ->label(__('auth.last_login_at'))
                    ->formatStateUsing(fn ($state) => $state ? \App\Support\Jalali::formatDateTime($state) : '—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
                IconColumn::make('is_active')->label(__('users.active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('user_type')->label(__('users.user_type'))->options(__('auth.types')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('users.empty'));
    }
}
