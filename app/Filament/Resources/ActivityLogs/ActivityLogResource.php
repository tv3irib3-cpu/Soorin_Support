<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\Permission;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Support\Jalali;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * تاریخچه تغییرات — فقط خواندنی. هیچ رکوردی از اینجا قابل ویرایش یا حذف
 * نیست، چون کل هدفش سند تغییرناپذیر رویدادهای سامانه است.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('activity.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('activity.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('activity.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('activity.nav_group');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('activity.date'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state))
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('activity.user'))
                    ->placeholder(__('activity.system'))
                    ->searchable(),

                TextColumn::make('action')
                    ->label(__('activity.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("activity.actions.$state") ?? $state),

                TextColumn::make('subject_type')
                    ->label(__('activity.subject_type'))
                    ->formatStateUsing(fn (?string $state) => $state ? (__("activity.subjects.$state") ?: class_basename($state)) : '—'),

                TextColumn::make('subject_id')
                    ->label(__('activity.subject_id'))
                    ->placeholder('—'),

                TextColumn::make('changes')
                    ->label(__('activity.changes'))
                    // type-safe: مقدارِ changes ممکن است آرایه، رشته، عدد یا null باشد
                    // (اگر JSONِ ذخیره‌شده اسکالر باشد، cast آن را int/string می‌کند) — پس
                    // type-hintِ سخت نمی‌گذاریم تا صفحهٔ تاریخچه ۵۰۰ ندهد.
                    ->formatStateUsing(fn ($state) => filled($state)
                        ? (is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) $state)
                        : '—')
                    ->limit(50)
                    ->tooltip(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null)
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('ip_address')
                    ->label(__('activity.ip_address'))
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('activity.action'))
                    ->options(__('activity.actions')),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('activity.empty_heading'));
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewActivity->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
