<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\User;
use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * پروژه‌های یک مشتری — مثال: آریا با پروژه‌های بندرعباس، چابهار و بوشهر.
 *
 * کارشناسان مسئول همین‌جا تخصیص داده می‌شوند و فقط تیکت‌های همان پروژه
 * را می‌بینند. مدیر مشتری خودکار به همه پروژه‌ها دسترسی دارد.
 */
class ProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('projects.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('projects.code'))
                ->required()
                ->maxLength(30),

            TextInput::make('name')
                ->label(__('projects.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('city')
                ->label(__('projects.city'))
                ->maxLength(80),

            TextInput::make('location')
                ->label(__('projects.location'))
                ->maxLength(255),

            DatePicker::make('start_date')
                ->label(__('projects.start_date'))
                // تاریخ در دیتابیس میلادی ذخیره می‌شود و معادل شمسی زیر فیلد نمایش داده می‌شود
                ->helperText(fn ($state) => $state ? Jalali::format($state) : null)
                ->live(onBlur: true),

            Toggle::make('is_active')
                ->label(__('projects.is_active'))
                ->default(true),

            Select::make('users')
                ->label(__('projects.assigned_users'))
                ->helperText(__('projects.assigned_hint'))
                ->relationship(
                    name: 'users',
                    titleAttribute: 'name',
                    // فقط کارشناسان همین مشتری قابل انتخاب‌اند
                    modifyQueryUsing: fn ($query) => $query
                        ->where('customer_id', $this->getOwnerRecord()->getKey())
                        ->where('user_type', User::TYPE_CUSTOMER_STAFF),
                )
                ->multiple()
                ->preload()
                ->columnSpanFull(),

            Textarea::make('notes')
                ->label(__('projects.notes'))
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('projects.code'))
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('projects.name'))
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('city')
                    ->label(__('projects.city'))
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('tickets_count')
                    ->label(__('projects.tickets_count'))
                    ->counts('tickets')
                    ->badge(),

                TextColumn::make('users_count')
                    ->label(__('projects.assigned_users'))
                    ->counts('users')
                    ->badge()
                    ->color('gray')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                IconColumn::make('is_active')
                    ->label(__('projects.is_active'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label(__('common.create')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('projects.empty_heading'))
            ->emptyStateDescription(__('projects.empty_body'));
    }
}
