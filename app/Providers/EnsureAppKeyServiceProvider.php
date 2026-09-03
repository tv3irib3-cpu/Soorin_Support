<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * تضمینِ وجودِ APP_KEY پیش از هر درخواستِ وب — برای نصبِ تازهٔ بدونِ SSH (مثلِ وردپرس).
 *
 * روی هاستِ اشتراکی، کاربر فقط فایل‌ها را اکسترکت می‌کند و /install را باز می‌کند؛ اما
 * گروهِ میدلورِ «web» شاملِ EncryptCookies است و بدونِ APP_KEY هر صفحه — حتی خودِ فرمِ
 * نصب — خطای ۵۰۰ (MissingAppKeyException) می‌دهد. در نتیجه key:generate که داخلِ
 * InstallController صدا زده می‌شود هرگز اجرا نمی‌شود، چون کاربر اصلاً به فرم نمی‌رسد.
 *
 * این Provider در مرحلهٔ register (پیش از رزولوِ رمزنگار) اجرا می‌شود: اگر کلید خالی بود
 * یک کلیدِ یکتا می‌سازد، در .env می‌نویسد و برای همین درخواست هم در config می‌گذارد تا
 * EncryptCookies همین حالا کار کند. وقتی کلید موجود باشد هیچ کاری نمی‌کند (idempotent)،
 * پس هر نصب کلیدِ اختصاصیِ ماندگارِ خودش را دارد و کلید در هر درخواست عوض نمی‌شود.
 */
class EnsureAppKeyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        try {
            if (filled(config('app.key'))) {
                return;
            }

            $key = 'base64:' . base64_encode(
                random_bytes($this->keyLength((string) config('app.cipher', 'AES-256-CBC')))
            );

            // برای همین درخواست فعالش کن تا رمزنگار/کوکی همین حالا بالا بیاید.
            config(['app.key' => $key]);

            // برای درخواست‌های بعدی، ماندگارش کن.
            $this->persistToEnvFile($key);
        } catch (\Throwable) {
            // این Provider هرگز نباید بوت را بشکند؛ اگر نوشتن ممکن نبود، دستِ‌کم
            // درخواستِ جاری با کلیدِ درون‌حافظه‌ای بالا می‌آید.
        }
    }

    /** طولِ کلید بر پایهٔ cipher: ۱۶ بایت برای AES-128 و ۳۲ بایت برای AES-256. */
    private function keyLength(string $cipher): int
    {
        return str_contains($cipher, '128') ? 16 : 32;
    }

    /** نوشتنِ APP_KEY در فایلِ .env (در صورتِ نبودِ فایل، از نمونهٔ هاستِ اشتراکی می‌سازد). */
    private function persistToEnvFile(string $key): void
    {
        $path = $this->app->environmentFilePath();

        if (! is_file($path)) {
            $example = base_path('.env.shared-host.example');
            if (is_file($example)) {
                @copy($example, $path);
            }
        }

        if (! is_file($path) || ! is_writable($path)) {
            return;
        }

        $env = (string) file_get_contents($path);
        $line = 'APP_KEY=' . $key;
        $pattern = '/^APP_KEY=.*/m';

        $env = preg_match($pattern, $env)
            ? (string) preg_replace($pattern, $line, $env)
            : rtrim($env, "\n") . "\n" . $line . "\n";

        file_put_contents($path, $env, LOCK_EX);
    }
}
