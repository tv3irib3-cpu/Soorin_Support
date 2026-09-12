<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /**
     * تاریخچهٔ تغییرات فقط‌خواندنی است (دکمهٔ «ایجاد رویداد» حذف شده). فقط یک
     * دکمهٔ «پاک‌کردنِ تاریخچه» دارد که تنها برای **مدیرِ پشتیبان** دیده می‌شود.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearLog')
                ->label(__('activity.clear'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => auth()->user()?->isSupportAdmin() ?? false)
                ->requiresConfirmation()
                ->modalHeading(__('activity.clear'))
                ->modalDescription(__('activity.clear_confirm'))
                ->modalSubmitActionLabel(__('activity.clear'))
                ->action(function () {
                    $count = ActivityLog::count();

                    ActivityLog::query()->delete();

                    // یک ردیف باقی می‌ماند تا معلوم باشد چه کسی و کِی تاریخچه را پاک کرد.
                    ActivityLog::record('log_cleared', null, ['deleted' => $count]);

                    Notification::make()
                        ->success()
                        ->title(__('activity.cleared'))
                        ->send();
                }),
        ];
    }
}
