<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
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
                TextColumn::make('email')->label(__('users.email_or_username'))->searchable(),
                TextColumn::make('user_type')
                    ->label(__('users.user_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("auth.types.$state"))
                    // مدیرِ پشتیبان آبی، کارشناسِ پشتیبان سبز؛ مدیر/کارشناسِ مشتری
                    // از یک خانواده (کهربایی/نارنجی) ولی متمایز از هم.
                    ->color(fn (string $state) => match ($state) {
                        User::TYPE_SUPPORT_ADMIN  => Color::Blue,
                        User::TYPE_SUPPORT_STAFF  => Color::Green,
                        User::TYPE_CUSTOMER_ADMIN => Color::Amber,
                        User::TYPE_CUSTOMER_STAFF => Color::Orange,
                        default                   => 'gray',
                    }),
                TextColumn::make('customer.name')
                    ->label(__('users.customer'))
                    ->placeholder('—')
                    // نامِ مشتری با رنگِ اختصاصیِ خودش.
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->customer) {
                            return '—';
                        }

                        $color = e($record->customer->displayColor());

                        return '<span style="display:inline-flex;align-items:center;gap:6px;">'
                            . '<span style="width:10px;height:10px;border-radius:50%;background:' . $color . ';flex:none;"></span>'
                            . e($state) . '</span>';
                    }),
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
