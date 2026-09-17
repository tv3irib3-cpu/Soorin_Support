<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * تیک‌های مجوز را با دسترسیِ فعلیِ کاربر پر می‌کند: اگر سفارشی‌شده باشد مجوزهای
     * مستقیمِ خودش، وگرنه مجوزهای مؤثرِ نقش (تا هیچ دسترسی‌ای عوض‌شده به‌نظر نرسد).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record;

        if ($user->isSupportUser()) {
            $data['permissions'] = $user->permissions_customized
                ? $user->permissions->pluck('name')->all()
                : $user->getAllPermissions()->pluck('name')->all();
        }

        return $data;
    }

    /**
     * ذخیرهٔ مجوزهای انتخاب‌شده به‌صورتِ مستقیم + روشن‌کردنِ پرچمِ سفارشی‌سازی.
     * حسابِ خودِ مدیرِ واردشده سفارشی نمی‌شود تا دسترسی‌اش را از خودش نگیرد.
     */
    protected function afterSave(): void
    {
        $user = $this->record;

        if ($user->id === auth()->id()) {
            return;
        }

        if ($user->isSupportUser()) {
            $permissions = collect($this->data['permissions'] ?? [])
                ->intersect(Permission::values())
                ->values()
                ->all();

            $user->forceFill(['permissions_customized' => true])->save();
            $user->syncPermissions($permissions);
        } elseif ($user->permissions_customized) {
            // نوعِ حساب به مشتری تغییر کرده: سفارشی‌سازی و مجوزهای مستقیم پاک شوند.
            $user->forceFill(['permissions_customized' => false])->save();
            $user->syncPermissions([]);
        }
    }
}
