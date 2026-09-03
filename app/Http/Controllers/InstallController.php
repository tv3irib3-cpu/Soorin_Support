<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ویزاردِ نصبِ وب — مثلِ وردپرس، برای هاستِ بدونِ SSH.
 *
 * کاربر پس از اکسترکتِ فایل‌ها، /install را باز می‌کند و در یک فرم:
 *   - اطلاعاتِ دیتابیس (میزبان/نام/کاربر/رمز)،
 *   - و نام‌کاربری و رمزِ مدیرِ سامانه،
 * را وارد می‌کند. سپس .env نوشته می‌شود، APP_KEY ساخته، اتصال تست، جدول‌ها ساخته و
 * مدیر با همان اطلاعات ایجاد می‌شود. پس از نصب، این صفحه بی‌اثر است.
 */
class InstallController extends Controller
{
    /** نمایشِ فرمِ نصب (یا پیامِ «نصب‌شده»). */
    public function show()
    {
        if ($this->isInstalled()) {
            return view('install', ['state' => 'already', 'adminUrl' => url('/admin')]);
        }

        return view('install', ['state' => 'form']);
    }

    /** پردازشِ فرمِ نصب. */
    public function store(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/admin');
        }

        $data = $request->validate([
            'db_host'        => ['required', 'string'],
            'db_port'        => ['required', 'string'],
            'db_database'    => ['required', 'string'],
            'db_username'    => ['required', 'string'],
            'db_password'    => ['nullable', 'string'],
            'db_prefix'      => ['nullable', 'string'],
            'admin_name'     => ['required', 'string', 'max:100'],
            'admin_username' => ['required', 'string', 'max:100', 'regex:/^\S+$/'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'admin_username' => 'نام کاربری',
            'admin_password' => 'رمز عبور',
        ]);

        // اتصالِ آزمایشی با اطلاعاتِ واردشده — پیش از نوشتنِ چیزی.
        config([
            'database.connections.mysql.host'     => $data['db_host'],
            'database.connections.mysql.port'     => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'] ?? '',
            'database.connections.mysql.prefix'   => $data['db_prefix'] ?? '',
        ]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            return view('install', [
                'state' => 'form',
                'error' => 'اتصال به دیتابیس ناموفق بود: ' . $e->getMessage(),
                'old'   => $data,
            ]);
        }

        // نوشتنِ اطلاعاتِ دیتابیس و آدرسِ سایت در .env
        $this->setEnv([
            'DB_HOST'         => $data['db_host'],
            'DB_PORT'         => $data['db_port'],
            'DB_DATABASE'     => $data['db_database'],
            'DB_USERNAME'     => $data['db_username'],
            'DB_PASSWORD'     => $data['db_password'] ?? '',
            'DB_TABLE_PREFIX' => $data['db_prefix'] ?? '',
            'APP_URL'         => $request->getSchemeAndHttpHost(),
        ]);

        try {
            if (blank(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            Artisan::call('migrate', ['--force' => true, '--seed' => true]);
        } catch (\Throwable $e) {
            return view('install', [
                'state' => 'form',
                'error' => 'ساختِ جدول‌ها ناموفق بود: ' . $e->getMessage(),
                'old'   => $data,
            ]);
        }

        // مدیر را با اطلاعاتِ واردشده بساز/جایگزین کن — و مدیرِ پیش‌فرضِ seeder
        // («admin»/«password») را حذف کن تا هیچ ورودِ پیش‌فرضی باقی نماند.
        $this->createAdmin($data['admin_username'], $data['admin_name'], $data['admin_password']);

        return view('install', [
            'state'    => 'done',
            'username' => $data['admin_username'],
            'adminUrl' => url('/admin'),
        ]);
    }

    private function createAdmin(string $username, string $name, string $password): void
    {
        $seeded = User::where('email', 'admin')->first();

        if ($username === 'admin' && $seeded) {
            $seeded->forceFill(['name' => $name, 'password' => $password])->save();
            $seeded->syncRoles(User::TYPE_SUPPORT_ADMIN);

            return;
        }

        // نام‌کاربریِ دلخواه: مدیرِ پیش‌فرض حذف و مدیرِ تازه ساخته می‌شود.
        $seeded?->forceDelete();

        $admin = User::create([
            'name'      => $name,
            'email'     => $username,
            'password'  => $password,
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->syncRoles(User::TYPE_SUPPORT_ADMIN);
    }

    /**
     * نوشتن/به‌روزرسانیِ کلیدها در فایلِ .env.
     *
     * @param  array<string, string>  $values
     */
    private function setEnv(array $values): void
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            // نقل‌قولِ دوگانه با فرارِ \ " $ تا رمزهای دارای کاراکترِ خاص هم درست بمانند.
            $quoted = '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], (string) $value) . '"';
            $line = $key . '=' . $quoted;
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

            $env = preg_match($pattern, $env)
                ? preg_replace($pattern, $line, $env)
                : rtrim($env, "\n") . "\n" . $line . "\n";
        }

        file_put_contents($path, $env);
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
