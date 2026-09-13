<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveService;
use App\Services\StorageService;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * پشتیبان‌گیری روی گوگل‌درایو — اتصالِ حساب، کپیِ خودکارِ پشتیبانِ دیتابیس روی درایو،
 * آپلود/بازگردانیِ فایل‌ها (پیوست/لوگو). فقط مدیر (مجوزِ تنظیمات).
 */
class GoogleDriveBackup extends Page
{
    protected string $view = 'filament.pages.google-drive-backup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static ?int $navigationSort = 98;

    /** @var array<int, array<string, mixed>> فایل‌های روی درایو (با «تازه‌سازیِ فهرست» پر می‌شود) */
    public array $files = [];

    public bool $listed = false;

    public static function getNavigationLabel(): string
    {
        return __('gdrive.label');
    }

    public function getTitle(): string
    {
        return __('gdrive.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('backups.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (session('gdrive_status') === 'connected') {
            Notification::make()->success()->title(__('gdrive.connected_ok'))->send();
        }

        if ($err = session('gdrive_error')) {
            Notification::make()->danger()->title(__('gdrive.connect_failed'))->body($err)->send();
        }
    }

    protected function service(): GoogleDriveService
    {
        return app(GoogleDriveService::class);
    }

    // برای نما
    public function isConfigured(): bool { return $this->service()->isConfigured(); }
    public function isConnected(): bool { return $this->service()->isConnected(); }
    public function isEnabled(): bool { return $this->service()->isEnabled(); }
    public function includesFiles(): bool { return $this->service()->includesFiles(); }
    public function connectedEmail(): ?string { return $this->service()->connectedEmail(); }
    public function redirectUri(): string { return route('google-drive.callback'); }

    protected function getHeaderActions(): array
    {
        $svc = $this->service();

        return [
            $this->credentialsAction(),
            $this->connectAction(),
            $this->settingsAction(),
            $this->pushDbAction(),
            $this->pushFilesAction(),
            $this->refreshAction(),
            $this->disconnectAction(),
        ];
    }

    /** ذخیرهٔ client_id/secret (از Google Cloud Console). */
    public function credentialsAction(): Action
    {
        return Action::make('credentials')
            ->label(__('gdrive.set_credentials'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->fillForm(fn () => [
                'client_id'     => (string) $this->service()->get('client_id'),
                'client_secret' => (string) $this->service()->get('client_secret'),
            ])
            ->schema([
                TextInput::make('client_id')->label(__('gdrive.client_id'))->required(),
                TextInput::make('client_secret')->label(__('gdrive.client_secret'))->required()->password()->revealable(),
            ])
            ->action(function (array $data): void {
                $this->service()->set('client_id', trim($data['client_id']));
                $this->service()->set('client_secret', trim($data['client_secret']));

                Notification::make()->success()->title(__('gdrive.credentials_saved'))->send();
            });
    }

    /** شروعِ جریانِ OAuth — به صفحهٔ گوگل می‌رود. */
    public function connectAction(): Action
    {
        return Action::make('connect')
            ->label(__('gdrive.connect'))
            ->icon(Heroicon::OutlinedLink)
            ->color('primary')
            ->visible(fn () => $this->service()->isConfigured())
            ->action(function () {
                return redirect()->away($this->service()->authUrl($this->redirectUri()));
            });
    }

    /** روشن/خاموشِ کپیِ خودکار و شاملِ فایل‌ها. */
    public function settingsAction(): Action
    {
        return Action::make('settings')
            ->label(__('gdrive.settings'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->visible(fn () => $this->service()->isConnected())
            ->fillForm(fn () => [
                'enabled'       => $this->service()->isEnabled(),
                'include_files' => $this->service()->includesFiles(),
            ])
            ->schema([
                Toggle::make('enabled')->label(__('gdrive.enabled'))->helperText(__('gdrive.enabled_hint')),
                Toggle::make('include_files')->label(__('gdrive.include_files'))->helperText(__('gdrive.include_files_hint')),
            ])
            ->action(function (array $data): void {
                $this->service()->set('enabled', ! empty($data['enabled']) ? '1' : '');
                $this->service()->set('include_files', ! empty($data['include_files']) ? '1' : '');

                Notification::make()->success()->title(__('common.saved'))->send();
            });
    }

    /** کپیِ آخرین (یا یک) پشتیبانِ دیتابیس روی درایو — یا ساختِ تازه و آپلود. */
    public function pushDbAction(): Action
    {
        return Action::make('pushDb')
            ->label(__('gdrive.push_db'))
            ->icon(Heroicon::OutlinedCircleStack)
            ->color('gray')
            ->visible(fn () => $this->service()->isConnected())
            ->requiresConfirmation()
            ->modalDescription(__('gdrive.push_db_hint'))
            ->action(function (): void {
                try {
                    // اگر پشتیبانی نیست، یکی بساز؛ وگرنه آخرین را بفرست.
                    $backupService = app(DatabaseBackupService::class);
                    $list = $backupService->list();
                    $name = $list[0]['name'] ?? $backupService->create('پشتیبان برای گوگل‌درایو', 'GDrive');

                    $this->service()->pushDatabaseBackup($name);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('gdrive.push_failed'))->body($e->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title(__('gdrive.pushed'))->send();
            });
    }

    /** آپلودِ باندلِ یک دستهٔ فایل روی درایو. */
    public function pushFilesAction(): Action
    {
        return Action::make('pushFiles')
            ->label(__('gdrive.push_files'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn () => $this->service()->isConnected())
            ->schema([
                Select::make('category')
                    ->label(__('storage.category'))
                    ->options(collect(StorageService::categories())
                        ->keys()
                        ->reject(fn ($k) => $k === 'backups')   // پشتیبان‌ها از دکمهٔ اختصاصیِ خودشان
                        ->mapWithKeys(fn ($k) => [$k => __("storage.categories.$k")])
                        ->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                try {
                    $this->service()->pushFilesBundle($data['category']);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('gdrive.push_failed'))->body($e->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title(__('gdrive.pushed'))->send();
            });
    }

    /** تازه‌سازیِ فهرستِ فایل‌های روی درایو. */
    public function refreshAction(): Action
    {
        return Action::make('refresh')
            ->label(__('gdrive.refresh'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('info')
            ->visible(fn () => $this->service()->isConnected())
            ->action(function (): void {
                try {
                    $this->files = $this->service()->listFiles();
                    $this->listed = true;
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('gdrive.list_failed'))->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public function disconnectAction(): Action
    {
        return Action::make('disconnect')
            ->label(__('gdrive.disconnect'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn () => $this->service()->isConnected())
            ->requiresConfirmation()
            ->modalDescription(__('gdrive.disconnect_hint'))
            ->action(function (): void {
                $this->service()->disconnect();
                $this->files = [];
                $this->listed = false;

                Notification::make()->success()->title(__('gdrive.disconnected'))->send();
            });
    }

    /** بازگردانیِ یک فایل از درایو (از فهرست). */
    public function restoreFromDrive(string $fileId): void
    {
        $file = collect($this->files)->firstWhere('id', $fileId);

        if (! $file) {
            return;
        }

        try {
            $result = $this->service()->restoreFromDrive($fileId, $file['name']);
        } catch (\Throwable $e) {
            Notification::make()->danger()->title(__('gdrive.restore_failed'))->body($e->getMessage())->persistent()->send();

            return;
        }

        $body = $result['type'] === 'db'
            ? __('gdrive.restore_db_done')
            : __('gdrive.restore_files_done', ['detail' => Jalali::digits($result['detail'])]);

        Notification::make()->success()->title(__('gdrive.restored'))->body($body)->persistent()->send();
    }

    public function humanSize(int $bytes): string
    {
        return Jalali::digits(StorageService::humanBytes($bytes));
    }
}
