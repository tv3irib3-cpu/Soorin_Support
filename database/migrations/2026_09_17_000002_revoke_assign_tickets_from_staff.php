<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * حذفِ مجوزِ «تخصیص کارشناس» (tickets.assign) از کارشناسانِ پشتیبان.
 *
 * تخصیص/بازتخصیصِ تیکت کارِ مدیر است. این مجوز از پیش‌فرضِ نقشِ کارشناس برداشته
 * شد و دکمهٔ تخصیص هم حالا همین مجوز را چک می‌کند؛ پس روی نصب‌های قدیمی باید از
 * نقشِ کارشناس و از مجوزهای مستقیمِ کارشناسان برداشته شود تا رفتار عوض نشود
 * (کارشناس همچنان نتواند تخصیص دهد، مگر مدیر عمداً بعداً به او بدهد).
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = 'tickets.assign';

        if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
            return;
        }

        // از نقشِ کارشناسِ پشتیبان
        $role = Role::where('name', 'support_staff')->where('guard_name', 'web')->first();
        if ($role) {
            $role->revokePermissionTo($name);
        }

        // از مجوزهای مستقیمِ کارشناسانِ پشتیبان (کاربرانِ سفارشی‌شده)
        User::where('user_type', User::TYPE_SUPPORT_STAFF)->get()->each(function (User $user) use ($name) {
            try {
                if ($user->permissions->contains('name', $name)) {
                    $user->revokePermissionTo($name);
                }
            } catch (\Throwable) {
                // بی‌صدا رد شود؛ نبودِ مجوز مشکلی نیست.
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // بازگردانی معنا ندارد.
    }
};
