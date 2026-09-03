<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * نصب‌کننده‌ی وب (مثلِ وردپرس) — برای هاستِ بدونِ SSH.
 *
 * پس از اکسترکتِ فایل‌ها و تنظیمِ .env، کاربر یک‌بار /install را باز می‌کند؛ جدول‌ها
 * ساخته و مدیرِ اولیه seed می‌شود و یک رمزِ تصادفیِ امن برای «admin» نمایش داده می‌شود.
 * پس از نصب، این مسیر دیگر کاری نمی‌کند (بازگشتِ ایمن).
 */
class InstallController extends Controller
{
    public function run(Request $request)
    {
        if ($this->isInstalled()) {
            return response()->view('install', [
                'done'     => false,
                'already'  => true,
                'adminUrl' => url('/admin'),
            ]);
        }

        try {
            Artisan::call('migrate', ['--force' => true, '--seed' => true]);
        } catch (\Throwable $e) {
            return response()->view('install', [
                'done'    => false,
                'error'   => $e->getMessage(),
            ], 500);
        }

        // رمزِ ادمینِ پیش‌فرض («password») در کدِ عمومی دیده می‌شود؛ اینجا یک رمزِ
        // تصادفیِ امن جایگزین و یک‌بار نمایش داده می‌شود (مثلِ وردپرس).
        $password = Str::password(14, symbols: false);

        $admin = User::where('email', 'admin')->first();
        $admin?->forceFill(['password' => $password])->save();

        return response()->view('install', [
            'done'     => true,
            'username' => 'admin',
            'password' => $password,
            'adminUrl' => url('/admin'),
        ]);
    }

    private function isInstalled(): bool
    {
        try {
            return Schema::hasTable('users') && User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
