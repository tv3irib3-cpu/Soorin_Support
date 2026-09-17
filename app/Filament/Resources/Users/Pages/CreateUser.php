<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * پس از ساختِ کاربرِ پشتیبان، مجوزهای انتخاب‌شده به‌صورتِ «مستقیم» روی خودِ
     * کاربر ذخیره و پرچمِ سفارشی‌سازی روشن می‌شود؛ از این پس دسترسیِ او از همین
     * مجوزها خوانده می‌شود (نه نقش). چون تیک‌ها پیش‌فرضِ نقش‌اند، اگر مدیر چیزی
     * تغییر ندهد دسترسی دقیقاً همان پیش‌فرض می‌ماند.
     */
    protected function afterCreate(): void
    {
        $user = $this->record;

        if (! $user->isSupportUser()) {
            return;
        }

        $permissions = collect($this->data['permissions'] ?? [])
            ->intersect(Permission::values())
            ->values()
            ->all();

        $user->forceFill(['permissions_customized' => true])->save();
        $user->syncPermissions($permissions);
    }
}
