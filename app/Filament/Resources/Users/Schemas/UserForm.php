<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label(__('users.name'))->required()->maxLength(255),
                    TextInput::make('email')
                        ->label(__('users.email_or_username'))
                        ->helperText(__('users.email_or_username_hint'))
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('mobile')->label(__('users.mobile'))->maxLength(20)->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->label(__('users.password'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? __('users.password_hint') : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                    Select::make('user_type')
                        ->label(__('users.user_type'))
                        ->options(__('auth.types'))
                        ->default(User::TYPE_SUPPORT_STAFF)
                        ->required()
                        ->native(false)
                        ->live()
                        // با تغییرِ نوعِ حساب، تیک‌های مجوز به پیش‌فرضِ همان نقش برمی‌گردند.
                        ->afterStateUpdated(fn ($state, callable $set) => $set(
                            'permissions',
                            Permission::defaultsByRole()[$state] ?? [],
                        )),

                    Select::make('customer_id')
                        ->label(__('users.customer'))
                        ->helperText(__('users.customer_hint'))
                        ->options(fn () => Customer::pluck('name', 'id'))
                        ->searchable()
                        ->required(fn ($get) => in_array($get('user_type'), [User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF]))
                        ->visible(fn ($get) => in_array($get('user_type'), [User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF])),

                    Select::make('theme')
                        ->label(__('users.theme'))
                        ->options(['ocean' => __('common.theme_ocean'), 'night' => __('common.theme_night')])
                        ->default('ocean')
                        ->native(false),

                    Toggle::make('is_active')->label(__('users.active'))->default(true),
                ]),

            Section::make(__('users.account_overrides'))
                ->description(__('users.account_overrides_hint'))
                ->columns(2)
                ->visible(fn ($get) => in_array($get('user_type'), [User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF]))
                ->schema([
                    Select::make('can_create_ticket')
                        ->label(__('customers.can_create_ticket'))
                        ->options([1 => __('common.yes'), 0 => __('common.no')])
                        ->placeholder(__('users.follow_default'))
                        ->native(false),

                    Select::make('can_view_invoices')
                        ->label(__('customers.can_view_invoices'))
                        ->options([1 => __('common.yes'), 0 => __('common.no')])
                        ->placeholder(__('users.follow_default'))
                        ->native(false),

                    Select::make('can_print_invoices')
                        ->label(__('customers.can_print_invoices'))
                        ->options([1 => __('common.yes'), 0 => __('common.no')])
                        ->placeholder(__('users.follow_default'))
                        ->native(false),

                    Select::make('history_scope')
                        ->label(__('users.history_scope'))
                        ->options(__('users.history_scope_options'))
                        ->placeholder(__('users.follow_default'))
                        ->native(false),
                ]),

            // ------ دسترسی‌های کاربرِ پشتیبان (مدیر/کارشناس) ------
            // فهرستِ کاملِ مجوزها با نوشتنِ «وضعیتِ پیش‌فرض» کنارِ هرکدام. تیک‌ها روی
            // پیش‌فرضِ نقش تنظیم‌اند و مدیر می‌تواند تغییرشان دهد. برای جلوگیری از
            // قفل‌شدنِ خودِ مدیر، این بخش هنگام ویرایشِ حسابِ خودِ او پنهان است.
            Section::make(__('users.permissions_section'))
                ->description(__('users.permissions_hint'))
                ->columns(2)
                ->visible(fn (callable $get, ?User $record) => in_array($get('user_type'), [
                    User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF,
                ], true) && (! $record || $record->id !== auth()->id()))
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->columns(2)
                        ->bulkToggleable()
                        ->dehydrated(false)
                        ->options(function (callable $get): array {
                            $defaults = Permission::defaultsByRole()[$get('user_type')] ?? [];
                            $options = [];

                            foreach (Permission::cases() as $perm) {
                                $mark = in_array($perm->value, $defaults, true)
                                    ? __('users.perm_default_on')
                                    : __('users.perm_default_off');

                                $options[$perm->value] = $perm->label() . ' — ' . $mark;
                            }

                            return $options;
                        })
                        ->default(fn (callable $get): array => Permission::defaultsByRole()[$get('user_type')] ?? []),
                ]),
        ]);
    }
}
