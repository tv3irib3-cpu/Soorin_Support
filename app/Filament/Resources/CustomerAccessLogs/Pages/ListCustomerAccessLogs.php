<?php

namespace App\Filament\Resources\CustomerAccessLogs\Pages;

use App\Filament\Resources\CustomerAccessLogs\CustomerAccessLogResource;
use App\Models\CustomerAccessLog;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCustomerAccessLogs extends ListRecords
{
    protected static string $resource = CustomerAccessLogResource::class;

    /**
     * دو دکمهٔ حذف — فقط برای مدیرِ پشتیبان:
     *  • پاک‌کردنِ همهٔ سوابق.
     *  • حذفِ سوابقِ قدیمی‌تر از یک بازه (نگه‌داریِ فقط دادهٔ اخیر).
     */
    protected function getHeaderActions(): array
    {
        $isAdmin = fn () => auth()->user()?->isSupportAdmin() ?? false;

        return [
            Action::make('clearOlder')
                ->label(__('access.clear_older'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible($isAdmin)
                ->schema([
                    Select::make('days')
                        ->label(__('access.older_than'))
                        ->options(__('access.ranges'))
                        ->default('90')
                        ->native(false)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading(__('access.clear_older'))
                ->modalDescription(__('access.clear_older_confirm'))
                ->action(function (array $data): void {
                    $count = CustomerAccessLog::query()
                        ->where('created_at', '<', now()->subDays((int) $data['days']))
                        ->delete();

                    Notification::make()
                        ->success()
                        ->title(__('access.clear_older_done'))
                        ->body(__('access.deleted_count', ['count' => Jalali::digits((string) $count)]))
                        ->send();
                }),

            Action::make('clearAll')
                ->label(__('access.clear'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible($isAdmin)
                ->requiresConfirmation()
                ->modalHeading(__('access.clear'))
                ->modalDescription(__('access.clear_confirm'))
                ->modalSubmitActionLabel(__('access.clear'))
                ->action(function (): void {
                    CustomerAccessLog::query()->delete();

                    Notification::make()
                        ->success()
                        ->title(__('access.cleared'))
                        ->send();
                }),
        ];
    }
}
