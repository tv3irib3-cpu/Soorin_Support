<?php

namespace App\Filament\Resources\CustomerAccessLogs;

use App\Enums\Permission;
use App\Filament\Resources\CustomerAccessLogs\Pages\ListCustomerAccessLogs;
use App\Models\CustomerAccessLog;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * فعالیتِ مشتریان — سندِ ورود/خروج/بازدیدِ کاربرانِ مشتری از پرتال، همراه با
 * IP، مرورگر، سیستم‌عامل و دستگاه. فقط مدیرِ پشتیبان می‌بیند و می‌تواند پاک کند
 * (دادهٔ حساسِ ردیابی است). ثبت‌شونده نیست و از اینجا ویرایش نمی‌شود.
 */
class CustomerAccessLogResource extends Resource
{
    protected static ?string $model = CustomerAccessLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 91;

    public static function getModelLabel(): string
    {
        return __('access.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('access.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('access.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('access.nav_group');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('access.date'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state))
                    ->sortable(),

                TextColumn::make('username')
                    ->label(__('access.username'))
                    ->searchable()
                    ->placeholder('—')
                    ->weight('medium'),

                TextColumn::make('customer.name')
                    ->label(__('access.customer'))
                    ->searchable()
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('event')
                    ->label(__('access.event'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("access.events.$state") ?: $state)
                    ->color(fn (string $state) => match ($state) {
                        CustomerAccessLog::EVENT_LOGIN        => 'success',
                        CustomerAccessLog::EVENT_LOGOUT       => 'gray',
                        CustomerAccessLog::EVENT_LOGIN_FAILED => 'danger',
                        CustomerAccessLog::EVENT_VISIT        => 'info',
                        default                               => 'gray',
                    }),

                TextColumn::make('ip_address')
                    ->label(__('access.ip_address'))
                    ->searchable()
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('browser')
                    ->label(__('access.browser'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('platform')
                    ->label(__('access.platform'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('device')
                    ->label(__('access.device'))
                    ->badge()
                    ->color('gray')
                    ->icon(fn (?string $state) => match ($state) {
                        'mobile' => 'heroicon-o-device-phone-mobile',
                        'tablet' => 'heroicon-o-device-tablet',
                        'bot'    => 'heroicon-o-cpu-chip',
                        'desktop'=> 'heroicon-o-computer-desktop',
                        default  => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? (__("access.devices.$state") ?: $state) : '—')
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('url')
                    ->label(__('access.url'))
                    ->prefix('/')
                    ->limit(32)
                    ->tooltip(fn ($record) => $record->url ? '/' . $record->url : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ستون‌های تکمیلی — پیش‌فرض پنهان، با منویِ «ستون‌ها» قابلِ نمایش.
                TextColumn::make('referer')
                    ->label(__('access.referer'))
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->referer)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('languages')
                    ->label(__('access.languages'))
                    ->limit(24)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user_agent')
                    ->label(__('access.user_agent'))
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->user_agent)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('session_id')
                    ->label(__('access.session'))
                    ->fontFamily('mono')
                    ->limit(12)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('created_at')
                    ->label(__('access.date'))
                    ->date()
                    ->getTitleFromRecordUsing(fn (CustomerAccessLog $record) => Jalali::format($record->created_at)),
                Group::make('username')
                    ->label(__('access.username')),
                Group::make('customer.name')
                    ->label(__('access.customer')),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('access.event'))
                    ->options(__('access.events')),

                SelectFilter::make('device')
                    ->label(__('access.device'))
                    ->options(__('access.devices')),

                SelectFilter::make('customer_id')
                    ->label(__('access.customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('period')
                    ->schema([
                        \Filament\Forms\Components\Select::make('days')
                            ->label(__('access.filter_from'))
                            ->options(__('access.ranges'))
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => filled($data['days'] ?? null)
                        ? $query->where('created_at', '>=', now()->subDays((int) $data['days']))
                        : $query)
                    ->indicateUsing(fn (array $data) => filled($data['days'] ?? null)
                        ? __('access.older_than') . ': ' . (__('access.ranges')[$data['days']] ?? $data['days'])
                        : null),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('access.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('access.empty_heading'))
            ->emptyStateDescription(__('access.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedGlobeAlt);
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerAccessLogs::route('/')];
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

    /** حذفِ سوابق فقط در اختیارِ مدیرِ پشتیبان. */
    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->isSupportAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSupportAdmin() ?? false;
    }
}
