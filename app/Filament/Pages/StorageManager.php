<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\DatabaseBackupService;
use App\Services\DataResetService;
use App\Services\StorageService;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * مدیریتِ استوریج — دیدنِ آدرس/حجمِ فایل‌های سامانه، دانلودِ گروهیِ هر دسته (ZIP)،
 * بازگردانی از ZIP (برای جابه‌جاییِ هاست) و پاک‌سازیِ پیوست‌های قدیمی. فقط مدیر
 * (مجوزِ تنظیماتِ سامانه).
 */
class StorageManager extends Page
{
    protected string $view = 'filament.pages.storage-manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 92;

    /** @var array<int, array<string, mixed>> */
    public array $summary = [];

    public static function getNavigationLabel(): string
    {
        return __('storage.label');
    }

    public function getTitle(): string
    {
        return __('storage.label');
    }

    public static function getNavigationGroup(): ?string
    {
        // همان گروهِ «مدیریت» که پشتیبان‌گیری و به‌روزرسانی در آن هستند.
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

        $this->loadSummary();
    }

    public function loadSummary(): void
    {
        $this->summary = app(StorageService::class)->summary();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->restoreAction(),
            $this->cleanupAttachmentsAction(),
        ];
        // resetDataAction به‌صورتِ اینلاین کنارِ توضیحاتش در نما رندر می‌شود
        // ({{ $this->resetDataAction }}) تا کاربر دقیقاً ببیند چه پاک/نگه می‌شود.
    }

    /**
     * پاک‌سازیِ کاملِ دادهٔ عملیاتی/تستی بدونِ درنظرگرفتنِ تاریخ — برای رفتن به
     * بهره‌برداری. پیکربندی و کاربران می‌مانند. پیش از کار، پشتیبانِ کامل گرفته و
     * برای جلوگیری از اشتباه، عبارتِ تأیید خواسته می‌شود.
     */
    public function resetDataAction(): Action
    {
        return Action::make('resetData')
            ->label(__('storage.reset_label'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->modalHeading(__('storage.reset_label'))
            ->modalDescription(__('storage.reset_modal'))
            ->schema([
                TextInput::make('confirm')
                    ->label(__('storage.reset_confirm_field'))
                    ->helperText(__('storage.reset_confirm_hint', ['word' => __('storage.reset_keyword')]))
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('storage.reset_label'))
            ->action(function (array $data): void {
                if (trim((string) ($data['confirm'] ?? '')) !== __('storage.reset_keyword')) {
                    Notification::make()->danger()->title(__('storage.reset_bad_keyword'))->send();

                    return;
                }

                // پشتیبانِ کامل پیش از پاک‌سازی — تا خودِ این کار هم قابلِ برگشت باشد.
                $backup = null;
                try {
                    $backup = app(DatabaseBackupService::class)->create('پشتیبان پیش از پاک‌سازیِ کاملِ داده', 'PreWipe');
                } catch (\Throwable) {
                    // نبودِ پشتیبان نباید جلوی کار را بگیرد، ولی در پیام هشدارش هست.
                }

                $counts = app(DataResetService::class)->purge();

                $this->loadSummary();

                Notification::make()->success()
                    ->title(__('storage.reset_done'))
                    ->body(__('storage.reset_result', [
                        'tickets'     => Jalali::digits((string) ($counts['tickets'] ?? 0)),
                        'invoices'    => Jalali::digits((string) ($counts['invoices'] ?? 0)),
                        'payments'    => Jalali::digits((string) ($counts['payments'] ?? 0)),
                        'attachments' => Jalali::digits((string) ($counts['attachments'] ?? 0)),
                        'backup'      => $backup ?? '—',
                    ]))
                    ->persistent()->send();
            });
    }

    /**
     * بازگردانیِ فایل‌ها از یک ZIP به دستهٔ انتخابی — برای وقتی هاست عوض شده و
     * می‌خواهی فایل‌ها را (جدا از دیتابیس) روی سرورِ تازه برگردانی.
     */
    private function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('storage.restore'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('primary')
            ->modalHeading(__('storage.restore'))
            ->modalDescription(__('storage.restore_hint'))
            ->schema([
                Select::make('category')
                    ->label(__('storage.category'))
                    ->options(fn () => collect(StorageService::categories())
                        ->keys()
                        ->mapWithKeys(fn ($k) => [$k => __("storage.categories.$k")])
                        ->all())
                    ->required()
                    ->native(false),

                FileUpload::make('file')
                    ->label(__('storage.zip_file'))
                    ->helperText(__('storage.zip_hint'))
                    ->storeFiles(false)
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'])
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $upload */
                $upload = $data['file'];

                if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
                    Notification::make()->danger()->title(__('storage.zip_bad_type'))->send();

                    return;
                }

                try {
                    $count = app(StorageService::class)->restore($data['category'], $upload->getRealPath());
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('storage.restore_failed'))
                        ->body($e->getMessage())->persistent()->send();

                    return;
                }

                $this->loadSummary();

                Notification::make()->success()
                    ->title(__('storage.restored'))
                    ->body(__('storage.restored_count', ['count' => Jalali::digits((string) $count)]))
                    ->persistent()->send();
            });
    }

    /**
     * پاک‌سازیِ پیوست‌های قدیمی‌تر از یک بازه — هم فایل، هم ردیفِ دیتابیس.
     */
    private function cleanupAttachmentsAction(): Action
    {
        return Action::make('cleanupAttachments')
            ->label(__('storage.cleanup'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->schema([
                Select::make('months')
                    ->label(__('storage.cleanup_age'))
                    ->options(array_flip(StorageService::ageOptions()))
                    ->required()
                    ->native(false),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('storage.cleanup'))
            ->modalDescription(__('storage.cleanup_confirm'))
            ->modalSubmitActionLabel(__('storage.cleanup'))
            ->action(function (array $data): void {
                $months = (int) $data['months'];
                $cutoff = now()->subMonths($months);

                $result = app(StorageService::class)->deleteAttachmentsOlderThan($cutoff);

                $this->loadSummary();

                Notification::make()->success()
                    ->title(__('storage.cleanup_done'))
                    ->body(__('storage.cleanup_result', [
                        'rows' => Jalali::digits((string) $result['rows']),
                        'size' => StorageService::humanBytes($result['bytes']),
                    ]))
                    ->persistent()->send();
            });
    }
}
