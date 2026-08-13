<?php

namespace Database\Seeders;

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ساخت نقش‌ها و مجوزها.
 *
 * این seeder چندبار قابل اجراست و مجوزهای دستی‌ای که مدیر به یک کاربر
 * خاص داده را پاک نمی‌کند — فقط نقش‌ها را با فهرست پیش‌فرض هم‌راستا می‌کند.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Perm::values() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Perm::defaultsByRole() as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        // هر کاربر موجود باید نقشی متناسب با نوع حسابش داشته باشد
        User::query()->whereNotNull('user_type')->each(function (User $user) {
            if (! $user->hasRole($user->user_type)) {
                $user->assignRole($user->user_type);
            }
        });
    }
}
