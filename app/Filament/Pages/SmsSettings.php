<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * تنظیمات پیامک — یک سوییچ کل سامانه + اطلاعات اتصال به سرویس پیامک.
 *
 * تا سرویس پیامک واقعی انتخاب و در کد وصل نشده (App\Services\Sms)،
 * این صفحه فقط مقادیر را ذخیره می‌کند؛ ارسال واقعی هنوز اتفاق نمی‌افتد
 * (App\Services\Sms\LogSmsGateway فقط در لاگ می‌نویسد).
 */
class SmsSettings extends Page
{
    protected string $view = 'filament.pages.sms-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?int $navigationSort = 91;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('sms.label');
    }

    public function getTitle(): string
    {
        return __('sms.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('activity.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can(Permission::ManageSettings->value), 403);

        $this->form->fill([
            'enabled'  => (bool) Setting::get('sms.enabled', false),
            'provider' => Setting::get('sms.provider', ''),
            'api_key'  => Setting::get('sms.api_key', ''),
            'sender'   => Setting::get('sms.sender', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('sms.label'))
                    ->description(__('sms.description'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('sms.enabled'))
                            ->helperText(__('sms.enabled_hint')),

                        TextInput::make('provider')
                            ->label(__('sms.provider'))
                            ->helperText(__('sms.provider_hint'))
                            ->maxLength(80),

                        TextInput::make('api_key')
                            ->label(__('sms.api_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),

                        TextInput::make('sender')
                            ->label(__('sms.sender'))
                            ->helperText(__('sms.sender_hint'))
                            ->maxLength(30),
                    ]),

                Section::make(__('sms.triggers'))
                    ->schema([
                        Textarea::make('triggers_note')
                            ->label('')
                            ->disabled()
                            ->default(__('sms.triggers_list'))
                            ->rows(3),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('sms.enabled', $state['enabled'] ?? false, 'sms', 'bool');
        Setting::set('sms.provider', $state['provider'] ?? '', 'sms');
        Setting::set('sms.api_key', $state['api_key'] ?? '', 'sms');
        Setting::set('sms.sender', $state['sender'] ?? '', 'sms');

        Notification::make()->success()->title(__('common.saved'))->send();
    }
}
