<?php

namespace App\Filament\Resources\ContractPlans;

use App\Enums\Permission;
use App\Filament\Resources\ContractPlans\Pages\ListContractPlans;
use App\Models\ContractPlan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * نوع قرارداد — طلایی، نقره‌ای، برنزی و هر نوع دلخواه دیگر.
 * درصد پوشش هر حوزه اینجا تعریف می‌شود و در محاسبه فاکتور استفاده می‌شود.
 */
class ContractPlanResource extends Resource
{
    protected static ?string $model = ContractPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('contracts.plan_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('contracts.plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('contracts.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('contracts.plan_name'))
                        ->required()
                        ->maxLength(80),

                    ColorPicker::make('color')
                        ->label(__('contracts.plan_color'))
                        ->default('#14b8a6'),

                    Textarea::make('description')
                        ->label(__('contracts.description'))
                        ->columnSpanFull()
                        ->rows(2),
                ]),

            Section::make(__('contracts.coverage'))
                ->description(__('contracts.coverage_hint'))
                ->columns(4)
                ->schema([
                    TextInput::make('cover_software')
                        ->label(__('contracts.cover_software'))
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),

                    TextInput::make('cover_hardware')
                        ->label(__('contracts.cover_hardware'))
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),

                    TextInput::make('cover_parts')
                        ->label(__('contracts.cover_parts'))
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),

                    TextInput::make('cover_onsite')
                        ->label(__('contracts.cover_onsite'))
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                ]),

            Section::make()
                ->columns(3)
                ->schema([
                    TextInput::make('ceiling_amount')
                        ->label(__('contracts.ceiling_amount'))
                        ->helperText(__('contracts.ceiling_hint'))
                        ->numeric()
                        ->suffix(__('common.currency')),

                    TextInput::make('included_tickets')
                        ->label(__('contracts.included_tickets'))
                        ->numeric(),

                    TextInput::make('response_hours')
                        ->label(__('contracts.response_hours'))
                        ->numeric(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')->label(''),

                TextColumn::make('name')
                    ->label(__('contracts.plan_name'))
                    ->weight('medium'),

                TextColumn::make('cover_software')->label(__('contracts.cover_software'))->suffix('%'),
                TextColumn::make('cover_hardware')->label(__('contracts.cover_hardware'))->suffix('%'),
                TextColumn::make('cover_parts')->label(__('contracts.cover_parts'))->suffix('%')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),
                TextColumn::make('cover_onsite')->label(__('contracts.cover_onsite'))->suffix('%')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('ceiling_amount')
                    ->label(__('contracts.ceiling_amount'))
                    ->formatStateUsing(fn ($state) => $state ? \App\Support\Jalali::money($state) : '—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('contracts_count')
                    ->label(__('contracts.plural'))
                    ->counts('contracts')
                    ->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('contracts.empty_plans'));
    }

    public static function getPages(): array
    {
        return ['index' => ListContractPlans::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageContracts->value) ?? false;
    }
}
