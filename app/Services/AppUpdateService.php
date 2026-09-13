<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\AppVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * به‌روزرسانی خودِ برنامه از داخل پنل.
 *
 * دو راه: آنلاین از گیت‌هاب (git pull) برای استقرارهایی که با git کلون شده‌اند،
 * و آفلاین با آپلود فایل زیپ نسخهٔ جدید. پیش از هر به‌روزرسانی یک پشتیبان
 * دیتابیس گرفته می‌شود تا راه برگشت باز بماند.
 *
 * ملاک «نسخهٔ جدید»: فایل VERSION در ریشهٔ پروژه (مقایسهٔ semver).
 */
class AppUpdateService
{
    /**
     * وضعیت به‌روزرسانی: نسخهٔ فعلی، نسخهٔ موجود، و اینکه آیا جدیدتری هست.
     *
     * @return array{method: string, current: string, latest: ?string, available: bool, error?: string}
     */
    public function status(): array
    {
        $current = AppVersion::current();

        // اگر «مانیفستِ به‌روزرسانی» تنظیم شده باشد، کانالِ به‌روزرسانی از همان است
        // (روشِ کاملاً PHPایِ بدونِ SSH، مثلِ وردپرس) — روی هاستِ اشتراکی هم کار می‌کند.
        if ($this->manifestConfigured()) {
            return $this->packageStatus();
        }

        if (! AppVersion::isGitRepo()) {
            // بدون git و بدون مانیفست، فقط به‌روزرسانیِ دستی با آپلودِ زیپ ممکن است
            return ['method' => 'offline', 'current' => $current, 'latest' => null, 'available' => false];
        }

        try {
            $root = base_path();

            // اگر fetch شکست بخورد (مثلاً مخزن private بدون توکن در remote)، نباید
            // بی‌صدا با نسخهٔ کش‌شدهٔ زمان کلون مقایسه کنیم و «به‌روز» بگوییم؛
            // خطا را برمی‌گردانیم تا کاربر علت واقعی را ببیند.
            $fetch = Process::path($root)->timeout(90)->env($this->processEnv())->run('git fetch --quiet');

            if (! $fetch->successful()) {
                return [
                    'method'    => 'git',
                    'current'   => $current,
                    'latest'    => null,
                    'available' => false,
                    'error'     => 'ارتباط با گیت‌هاب ناموفق بود (احتمالاً توکن یا دسترسی remote). '
                        . trim($fetch->errorOutput() ?: $fetch->output()),
                ];
            }

            $env = $this->processEnv();
            $upstream = trim(Process::path($root)->env($env)->run('git rev-parse --abbrev-ref --symbolic-full-name @{u}')->output());

            if ($upstream === '') {
                $upstream = 'origin/main';
            }

            $latest = trim(Process::path($root)->env($env)->run('git show ' . escapeshellarg($upstream . ':VERSION'))->output());

            if ($latest === '') {
                $latest = $current;
            }

            return [
                'method'    => 'git',
                'current'   => $current,
                'latest'    => $latest,
                'available' => version_compare($latest, $current, '>'),
            ];
        } catch (\Throwable $e) {
            return [
                'method'    => 'git',
                'current'   => $current,
                'latest'    => null,
                'available' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /** آیا آدرسِ «مانیفستِ به‌روزرسانی» تنظیم شده؟ (روشِ بدونِ SSH، مثلِ وردپرس) */
    public function manifestConfigured(): bool
    {
        return filled(config('branding.update.manifest'));
    }

    /**
     * وضعیت از روی «مانیفست» — یک JSONِ ساده در یک URL که نسخهٔ جدید و آدرسِ ZIPِ
     * آماده (همراهِ vendor) را می‌دهد. همه‌چیز با PHP انجام می‌شود، بدونِ SSH/شل.
     *
     * قالبِ مانیفست: {"version":"0.4.5","zip":"https://.../full.zip","sha256":"...","notes":"..."}
     *
     * @return array{method: string, current: string, latest: ?string, available: bool, zip?: string, sha256?: ?string, notes?: ?string, error?: string}
     */
    public function packageStatus(): array
    {
        $current = AppVersion::current();
        $url = trim((string) config('branding.update.manifest'));

        if ($url === '') {
            return ['method' => 'package', 'current' => $current, 'latest' => null, 'available' => false,
                'error' => 'آدرسِ مانیفستِ به‌روزرسانی تنظیم نشده است.'];
        }

        try {
            $res = Http::timeout(30)->acceptJson()->get($url);

            if (! $res->successful()) {
                return ['method' => 'package', 'current' => $current, 'latest' => null, 'available' => false,
                    'error' => 'ارتباط با سرورِ به‌روزرسانی ناموفق بود (کد ' . $res->status() . ').'];
            }

            $data = (array) $res->json();
            $latest = trim((string) ($data['version'] ?? ''));
            $zip = trim((string) ($data['zip'] ?? ''));

            if ($latest === '' || $zip === '') {
                return ['method' => 'package', 'current' => $current, 'latest' => null, 'available' => false,
                    'error' => 'مانیفستِ به‌روزرسانی نامعتبر است (version یا zip ندارد).'];
            }

            return [
                'method'    => 'package',
                'current'   => $current,
                'latest'    => $latest,
                'zip'       => $zip,
                'sha256'    => $data['sha256'] ?? null,
                'notes'     => $data['notes'] ?? null,
                'available' => version_compare($latest, $current, '>'),
            ];
        } catch (\Throwable $e) {
            return ['method' => 'package', 'current' => $current, 'latest' => null, 'available' => false,
                'error' => $e->getMessage()];
        }
    }

    /**
     * به‌روزرسانیِ تک‌کلیکیِ بدونِ SSH — مثلِ وردپرس.
     *
     * ZIPِ آماده (همراهِ vendor) را با PHP دانلود می‌کند، چک‌سام را می‌سنجد، با
     * ZipArchive باز می‌کند، فایل‌ها را با PHP کپی می‌کند و مهاجرت/پاک‌سازی را
     * «درجا» با Artisan::call اجرا می‌کند. هیچ git/composer/شلی لازم نیست.
     *
     * @return array{backup: ?string, version: string}
     */
    public function updateFromPackage(): array
    {
        // کپیِ vendor چند ده‌هزار فایل است؛ روی هاستِ اشتراکی نباید با مهلتِ اجرا
        // یا قطعِ مرورگر نیمه‌کاره بماند.
        @set_time_limit(0);
        @ignore_user_abort(true);

        $status = $this->packageStatus();

        if (! empty($status['error'])) {
            throw new RuntimeException($status['error']);
        }

        if (empty($status['available'])) {
            throw new RuntimeException('نسخهٔ جدیدی در دسترس نیست.');
        }

        $tmpZip = storage_path('app/update-' . uniqid() . '.zip');

        $res = Http::timeout(600)->sink($tmpZip)->get($status['zip']);

        if (! $res->successful() || ! is_file($tmpZip) || filesize($tmpZip) === 0) {
            @unlink($tmpZip);

            throw new RuntimeException('دانلودِ بستهٔ به‌روزرسانی ناموفق بود.');
        }

        // اگر چک‌سام داده شده، صحتِ فایل را بررسی کن (جلوگیری از فایلِ خراب/دستکاری‌شده).
        if (filled($status['sha256'] ?? null)) {
            $actual = hash_file('sha256', $tmpZip);

            if (! hash_equals(strtolower((string) $status['sha256']), strtolower((string) $actual))) {
                @unlink($tmpZip);

                throw new RuntimeException('چک‌سامِ بستهٔ دانلودشده نادرست است (فایل خراب یا دستکاری‌شده).');
            }
        }

        try {
            return $this->applyPackageZip($tmpZip);
        } finally {
            @unlink($tmpZip);
        }
    }

    /**
     * بازکردن و اعمالِ یک بستهٔ کاملِ ZIP (همراهِ vendor) — کاملاً با PHP.
     *
     * بسته باید «کامل» باشد (شاملِ vendor)، پس نیازی به composer نیست و مهاجرت
     * هم درجا با Artisan اجرا می‌شود. فقط `.env` و `storage` و `.git` دست‌نخورده
     * می‌مانند تا داده و تنظیماتِ محلی حفظ شود.
     *
     * @return array{backup: ?string, version: string}
     */
    private function applyPackageZip(string $zipPath): array
    {
        $tmp = storage_path('app/pkg-' . uniqid());

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('باز کردنِ فایلِ بسته ناموفق بود.');
        }

        @mkdir($tmp, 0775, true);
        $zip->extractTo($tmp);
        $zip->close();

        // اگر همه‌چیز داخلِ یک پوشهٔ تک قرار گرفته، همان را ریشه بگیر
        $entries = array_values(array_diff(scandir($tmp) ?: [], ['.', '..']));
        $source = (count($entries) === 1 && is_dir($tmp . '/' . $entries[0])) ? $tmp . '/' . $entries[0] : $tmp;

        // بسته باید artisan داشته باشد. vendor «اختیاری» است: بسته‌های «فقط-کد» (سبک و
        // سریع) vendor ندارند و vendorِ فعلیِ نصب حفظ می‌شود؛ فقط وقتی وابستگی‌ها عوض
        // شده باشند، بستهٔ کامل (همراه vendor) منتشر می‌شود.
        if (! is_file($source . '/artisan')) {
            $this->rrmdir($tmp);

            throw new RuntimeException('این بسته معتبر نیست (artisan ندارد).');
        }

        if (! is_dir($source . '/vendor') && ! is_dir(base_path('vendor'))) {
            $this->rrmdir($tmp);

            throw new RuntimeException('این بستهٔ «فقط-کد» است ولی نصبِ فعلی هم vendor ندارد — از بستهٔ کامل استفاده کن.');
        }

        // نسخهٔ فعلی (پیش از بازنویسی) و نسخهٔ بسته — برای ثبتِ نقطهٔ بازگشت.
        $fromVersion = AppVersion::current();
        $newVersion  = trim((string) @file_get_contents($source . '/VERSION')) ?: $fromVersion;

        $backup = $this->safetyBackup();

        // عکسِ کدِ فعلی را پیش از بازنویسی بگیر تا بتوان دقیقاً به همین نسخه برگشت
        // (روی هاستِ اشتراکی که git نیست، تنها راهِ بازگشت همین است).
        $snapshot = $newVersion !== $fromVersion ? $this->captureCodeSnapshot($fromVersion) : null;

        // وب‌روتِ واقعی: روی هاستِ اشتراکی «public_html» است (APP_PUBLIC_PATH)، نه «public».
        $publicName = filled(env('APP_PUBLIC_PATH')) ? (string) env('APP_PUBLIC_PATH') : 'public';

        // بسته کامل است؛ همه‌چیز جز داده، تنظیماتِ محلی و پوشهٔ public بازنویسی می‌شود.
        // «public» را جدا مدیریت می‌کنیم تا asset‌های تازه به وب‌روتِ واقعی برسند و
        // پوشهٔ اضافیِ «public» کنارِ «public_html» ساخته نشود.
        $this->copyOver($source, base_path(), ['.env', 'storage', '.git', 'public']);

        if (is_dir($source . '/public')) {
            @mkdir(base_path($publicName), 0775, true);
            $this->copyOver($source . '/public', base_path($publicName), []);
        }

        // پاک‌سازیِ پوشهٔ اضافیِ «public» که آپدیت‌های بیمارِ قبلی کنارِ «public_html» ساخته بودند.
        if ($publicName !== 'public' && is_dir(base_path('public'))) {
            $this->rrmdir(base_path('public'));
        }

        $this->rrmdir($tmp);

        // مهاجرت و پاک‌سازیِ کش «درجا» با PHP — بدونِ شل، composer یا git.
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize:clear');

        $version = AppVersion::current();
        $this->markUpToDate($version);

        // نقطهٔ بازگشت را فقط وقتی ثبت کن که نسخه واقعاً عوض شده و عکسِ کد گرفته شده باشد.
        if ($version !== $fromVersion && filled($snapshot)) {
            $this->storeRollbackPoint($this->currentMethod(), $fromVersion, $backup, snapshot: $snapshot);
        }

        return ['backup' => $backup, 'version' => $version];
    }

    /** کلید کشِ نتیجهٔ آخرین بررسی روزانهٔ به‌روزرسانی. */
    public const CACHE_KEY = 'soorin.app_update';

    /** آخرین وضعیتِ کش‌شدهٔ به‌روزرسانی (بدون هیچ محاسبه/شبکه). */
    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    /** بررسی واقعی را انجام می‌دهد و نتیجه را در کش می‌گذارد (برای دستور روزانه). */
    public function refreshCache(): array
    {
        $status = $this->status();
        $status['checked_at'] = now()->toIso8601String();

        Cache::forever(self::CACHE_KEY, $status);

        return $status;
    }

    /**
     * نسخهٔ جدیدِ موجود برای نشانِ قرمزِ منو — یا null اگر به‌روز است.
     *
     * از کش می‌خواند تا فوری و بدون معطلیِ شبکه باشد. اگر کش کهنه است (بیش از ۲۰
     * ساعت) یک تازه‌سازی را با defer() زمان‌بندی می‌کند تا «پس از ارسال پاسخ به
     * مرورگر» در پس‌زمینهٔ همین درخواست اجرا شود — پس بدون cron و بدون معطل‌کردنِ
     * صفحه، روزی یک‌بار خودش را به‌روز می‌کند. قفل ساده جلوی اجرای هم‌زمان را می‌گیرد.
     */
    public function availableUpdate(): ?string
    {
        $status = $this->cached();

        $checkedAt = $status['checked_at'] ?? null;
        $stale = blank($checkedAt) || Carbon::parse($checkedAt)->addHours(20)->isPast();

        if ($stale && Cache::add(self::CACHE_KEY . '.refreshing', 1, now()->addMinutes(10))) {
            defer(function (): void {
                try {
                    $this->refreshCache();
                } finally {
                    Cache::forget(self::CACHE_KEY . '.refreshing');
                }
            });
        }

        return ! empty($status['available']) ? ($status['latest'] ?? null) : null;
    }

    /**
     * روشِ فعلیِ به‌روزرسانی بر پایهٔ وضعیتِ واقعیِ همین لحظه:
     *  - «package» اگر مانیفست تنظیم شده باشد (آپدیتِ آنلاین/تک‌کلیکی، حتی روی هاستِ اشتراکی)،
     *  - «git» اگر با گیت کلون شده باشد،
     *  - «offline» فقط وقتی هیچ‌کدام نباشد (تنها آپلودِ فایل).
     */
    public function currentMethod(): string
    {
        if ($this->manifestConfigured()) {
            return 'package';
        }

        return AppVersion::isGitRepo() ? 'git' : 'offline';
    }

    /** پس از به‌روزرسانیِ موفق، کش را «به‌روز» علامت می‌زند تا نشان قرمز فوراً برود. */
    private function markUpToDate(string $version): void
    {
        Cache::forever(self::CACHE_KEY, [
            'method'     => $this->currentMethod(),
            'current'    => $version,
            'latest'     => $version,
            'available'  => false,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * به‌روزرسانی از گیت‌هاب: پشتیبان، git pull، نصب وابستگی‌ها، مهاجرت، پاک‌سازی کش.
     *
     * @return array{backup: ?string, version: string}
     */
    public function updateFromGit(): array
    {
        if (! AppVersion::isGitRepo()) {
            throw new RuntimeException('این استقرار یک مخزن گیت نیست؛ از به‌روزرسانی با فایل استفاده کن.');
        }

        $root = base_path();

        // نقطهٔ بازگشت: کامیت و نسخهٔ فعلی را پیش از pull نگه می‌داریم تا اگر
        // نسخهٔ جدید مشکل داشت، دقیقاً به همین‌جا برگردیم.
        $fromCommit  = trim(Process::path($root)->env($this->processEnv())->run('git rev-parse HEAD')->output());
        $fromVersion = AppVersion::current();

        $backup = $this->safetyBackup();

        $this->run($root, 'git pull --ff-only', 180);
        $this->run($root, 'composer install --no-dev --optimize-autoloader --no-interaction', 600);

        // مهاجرت و پاک‌سازی در پروسهٔ تازه تا کدِ به‌روزشده اجرا شود
        $this->run($root, 'php artisan migrate --force', 300);
        $this->run($root, 'php artisan optimize:clear', 120);

        $version = AppVersion::current();
        $this->markUpToDate($version);

        // فقط وقتی نقطهٔ بازگشت را ثبت کن که کامیت واقعاً جابه‌جا شده باشد.
        $toCommit = trim(Process::path($root)->env($this->processEnv())->run('git rev-parse HEAD')->output());

        if ($fromCommit !== '' && $fromCommit !== $toCommit) {
            $this->storeRollbackPoint('git', $fromVersion, $backup, commit: $fromCommit);
        }

        return ['backup' => $backup, 'version' => $version];
    }

    /**
     * به‌روزرسانی با آپلودِ فایلِ زیپِ نسخهٔ جدید — کاملاً با PHP، بدونِ شل.
     *
     * زیپ باید «بستهٔ کامل» باشد (همراهِ پوشهٔ vendor)، تا روی هاستِ اشتراکیِ بدونِ
     * SSH هم کار کند (نه composer لازم است، نه اجرای دستور). مهاجرت درجا انجام می‌شود.
     *
     * @return array{backup: ?string, version: string}
     */
    public function updateFromZip(string $zipPath): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        return $this->applyPackageZip($zipPath);
    }

    /**
     * «اتصال به گیت‌هاب» برای نصب‌هایی که با فایل زیپ نصب شده‌اند (بدون .git).
     *
     * داخل همان پوشه یک مخزن گیت می‌سازد و به origin وصلش می‌کند تا از آن پس
     * «به‌روزرسانی از گیت‌هاب» هم ممکن شود. فایل‌های رهگیری‌نشده (‎.env، vendor،
     * storage، public/branding) دست‌نخورده می‌مانند؛ فقط فایل‌های خودِ برنامه با
     * نسخهٔ مخزن هم‌تراز می‌شوند.
     *
     * @return array{version: string, backup: ?string}
     */
    public function linkToGit(string $url): array
    {
        if (AppVersion::isGitRepo()) {
            throw new RuntimeException('این استقرار همین حالا هم مخزن گیت است؛ نیازی به اتصال دوباره نیست.');
        }

        if (! preg_match('#^https?://#i', trim($url))) {
            throw new RuntimeException('آدرس مخزن باید با http:// یا https:// آغاز شود.');
        }

        // چون فایل‌های برنامه با نسخهٔ مخزن هم‌تراز می‌شوند، اول پشتیبان بگیر.
        $backup = $this->safetyBackup();
        $root = base_path();

        $this->run($root, 'git init', 60);
        $this->run($root, 'git remote add origin ' . escapeshellarg(trim($url)), 60);
        $this->run($root, 'git fetch origin --quiet', 240);
        // -f فایل‌های رهگیری‌شده را با نسخهٔ مخزن هماهنگ می‌کند؛ رهگیری‌نشده‌ها می‌مانند.
        $this->run($root, 'git checkout -f -B main origin/main', 180);
        $this->run($root, 'git branch --set-upstream-to=origin/main main', 60);

        return ['version' => AppVersion::current(), 'backup' => $backup];
    }

    // ==================================================== بازگشت به نسخهٔ قبلی

    /** گروهِ تنظیماتِ نقطهٔ بازگشت در جدولِ settings. */
    private const ROLLBACK_GROUP = 'update';

    /**
     * ورودی‌های سطحِ‌بالای «کد» که در بسته‌ها هستند — همان‌هایی که آپدیت بازنویسی
     * می‌کند (بدونِ vendor، storage، ‎.env، ‎.git). عکسِ بازگشت از همین‌ها گرفته می‌شود.
     */
    private const CODE_ENTRIES = [
        'app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes',
        'deploy', 'docker', 'docs', 'Logo', 'scripts',
        'artisan', 'composer.json', 'composer.lock', 'VERSION',
        'phpunit.xml', 'package.json', 'vite.config.js', 'docker-compose.yml',
    ];

    private function rollbackDir(): string
    {
        return storage_path('app/rollback');
    }

    /**
     * اطلاعاتِ نقطهٔ بازگشت (نسخه‌ای که آخرین آپدیت از آن آمده)، یا null اگر ثبت نشده
     * یا فایلِ عکسِ آن دیگر موجود نیست.
     *
     * @return array{method: string, version: string, commit: string, backup: string, snapshot: string, at: ?string}|null
     */
    public function rollbackInfo(): ?array
    {
        $version = (string) Setting::get('update.rollback_version', '');

        if ($version === '') {
            return null;
        }

        $info = [
            'method'   => (string) Setting::get('update.rollback_method', 'package'),
            'version'  => $version,
            'commit'   => (string) Setting::get('update.rollback_commit', ''),
            'backup'   => (string) Setting::get('update.rollback_backup', ''),
            'snapshot' => (string) Setting::get('update.rollback_snapshot', ''),
            'at'       => Setting::get('update.rollback_at'),
        ];

        // بازگشتِ غیرِ گیت به عکسِ کد نیاز دارد؛ اگر فایلش رفته، نقطهٔ بازگشت بی‌اعتبار است.
        if ($info['method'] !== 'git' && (blank($info['snapshot']) || ! is_file($info['snapshot']))) {
            return null;
        }

        return $info;
    }

    /** آیا نقطهٔ بازگشتِ معتبری هست؟ (برای نمایشِ دکمهٔ دانگرید). */
    public function canRollback(): bool
    {
        return $this->rollbackInfo() !== null;
    }

    private function storeRollbackPoint(string $method, string $version, ?string $backup, string $commit = '', ?string $snapshot = null): void
    {
        // نقطهٔ بازگشتِ قبلی مصرف/جایگزین می‌شود؛ عکسِ کدِ قدیمی‌اش پاک شود.
        $old = (string) Setting::get('update.rollback_snapshot', '');

        if (filled($old) && $old !== $snapshot && is_file($old)) {
            @unlink($old);
        }

        Setting::set('update.rollback_method', $method, self::ROLLBACK_GROUP, 'string');
        Setting::set('update.rollback_version', $version, self::ROLLBACK_GROUP, 'string');
        Setting::set('update.rollback_commit', $commit, self::ROLLBACK_GROUP, 'string');
        Setting::set('update.rollback_backup', (string) $backup, self::ROLLBACK_GROUP, 'string');
        Setting::set('update.rollback_snapshot', (string) $snapshot, self::ROLLBACK_GROUP, 'string');
        Setting::set('update.rollback_at', now()->toIso8601String(), self::ROLLBACK_GROUP, 'string');
    }

    private function clearRollbackPoint(): void
    {
        $snapshot = (string) Setting::get('update.rollback_snapshot', '');

        if (filled($snapshot) && is_file($snapshot)) {
            @unlink($snapshot);
        }

        foreach (['method', 'version', 'commit', 'backup', 'snapshot', 'at'] as $key) {
            Setting::set('update.rollback_' . $key, '', self::ROLLBACK_GROUP, 'string');
        }
    }

    /**
     * عکسِ zip از کدِ فعلیِ نصب (پیش از بازنویسیِ آپدیت) — همان مجموعهٔ فایلی که
     * آپدیت لمس می‌کند، به‌علاوهٔ وب‌روتِ واقعی (public/public_html). فقط یک عکس نگه
     * داشته می‌شود. null اگر ساخت ناموفق بود (نبودِ عکس یعنی بازگشتِ غیرِگیت ممکن نیست).
     */
    private function captureCodeSnapshot(string $version): ?string
    {
        try {
            $dir = $this->rollbackDir();
            @mkdir($dir, 0775, true);

            // فقط یک نقطهٔ بازگشت نگه می‌داریم؛ عکس‌های قبلی را پاک کن.
            foreach (glob($dir . '/code-*.zip') ?: [] as $old) {
                @unlink($old);
            }

            $path = $dir . '/code-' . preg_replace('/[^0-9.]/', '', $version) . '-' . date('YmdHis') . '.zip';

            $zip = new ZipArchive();

            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return null;
            }

            foreach (self::CODE_ENTRIES as $entry) {
                $abs = base_path($entry);

                if (! file_exists($abs)) {
                    continue;
                }

                is_dir($abs) ? $this->zipDir($zip, $abs, $entry) : $zip->addFile($abs, $entry);
            }

            // وب‌روتِ واقعی را به‌صورتِ public/ داخلِ عکس نگه دار (asset‌های هماهنگ با کد).
            $publicName = filled(env('APP_PUBLIC_PATH')) ? (string) env('APP_PUBLIC_PATH') : 'public';
            $pub = base_path($publicName);

            if (is_dir($pub)) {
                $this->zipDir($zip, $pub, 'public');
            }

            $zip->close();

            return is_file($path) ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function zipDir(ZipArchive $zip, string $absDir, string $localBase): void
    {
        $zip->addEmptyDir($localBase);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($it as $file) {
            $abs   = $file->getPathname();
            $local = $localBase . '/' . str_replace('\\', '/', substr($abs, strlen($absDir) + 1));

            $file->isDir() ? $zip->addEmptyDir($local) : $zip->addFile($abs, $local);
        }
    }

    private function restoreCodeSnapshot(string $zipPath): void
    {
        $tmp = storage_path('app/rb-' . uniqid());

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('بازکردنِ عکسِ نسخهٔ قبلی ناموفق بود.');
        }

        @mkdir($tmp, 0775, true);
        $zip->extractTo($tmp);
        $zip->close();

        $publicName = filled(env('APP_PUBLIC_PATH')) ? (string) env('APP_PUBLIC_PATH') : 'public';

        // همه‌چیز جز public روی ریشه؛ public جدا به وب‌روتِ واقعی.
        $this->copyOver($tmp, base_path(), ['public']);

        if (is_dir($tmp . '/public')) {
            @mkdir(base_path($publicName), 0775, true);
            $this->copyOver($tmp . '/public', base_path($publicName), []);
        }

        $this->rrmdir($tmp);
    }

    /**
     * بازگشت به نسخهٔ قبلی (دانگرید).
     *
     * کد به نسخهٔ پیش از آخرین آپدیت برمی‌گردد — روی نصبِ گیت با `git reset --hard`
     * و در غیرِ آن با بازگرداندنِ عکسِ کد. برای دیتابیس دو حالت است:
     *   - $restoreDatabase = true: دیتابیس از پشتیبانِ پیش‌از‌آپدیت بازیابی می‌شود
     *     (کاملاً هماهنگ؛ داده‌های بعد از آپدیت می‌رود ولی در پشتیبان محفوظ است).
     *   - false: دیتابیس دست‌نخورده می‌ماند (فقط وقتی امن است که آپدیت مهاجرتِ ویرانگر نداشته).
     *
     * پیش از هر کاری یک پشتیبانِ تازه گرفته می‌شود تا خودِ بازگشت هم قابلِ برگشت باشد.
     *
     * @return array{version: string, backup: ?string, restored_db: bool}
     */
    public function rollback(bool $restoreDatabase): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $info = $this->rollbackInfo();

        if ($info === null) {
            throw new RuntimeException(__('updates.rollback_none'));
        }

        // پشتیبانِ تازه از وضعیتِ فعلی تا خودِ بازگشت هم قابلِ برگشت باشد.
        $preRollbackBackup = null;

        try {
            $preRollbackBackup = app(DatabaseBackupService::class)->create('پشتیبان پیش از بازگشت به نسخهٔ قبلی', 'PreRb');
        } catch (\Throwable) {
            // نبودِ پشتیبان نباید جلوی بازگشت را بگیرد.
        }

        if ($info['method'] === 'git' && AppVersion::isGitRepo() && filled($info['commit'])) {
            $root = base_path();
            $this->run($root, 'git reset --hard ' . escapeshellarg($info['commit']), 180);
            $this->run($root, 'composer install --no-dev --optimize-autoloader --no-interaction', 600);
        } else {
            // نصبِ بسته/زیپ (هاستِ اشتراکی): از عکسِ کد بازگردان.
            if (blank($info['snapshot']) || ! is_file($info['snapshot'])) {
                throw new RuntimeException(__('updates.rollback_no_snapshot'));
            }

            $this->restoreCodeSnapshot($info['snapshot']);
        }

        // بازیابیِ دیتابیس فقط اگر کاربر خواسته و پشتیبانِ پیش‌از‌آپدیت موجود باشد.
        if ($restoreDatabase && filled($info['backup'])) {
            $service = app(DatabaseBackupService::class);

            if ($service->exists($info['backup'])) {
                $service->restore($service->absolutePath($info['backup']));
            }
        }

        Artisan::call('optimize:clear');

        // نقطهٔ بازگشت مصرف شد؛ نسخهٔ جدید دوباره «موجود» می‌شود.
        $this->clearRollbackPoint();
        Cache::forget(self::CACHE_KEY);

        return [
            'version'     => AppVersion::current(),
            'backup'      => $preRollbackBackup,
            'restored_db' => $restoreDatabase,
        ];
    }

    private function safetyBackup(): ?string
    {
        try {
            return app(DatabaseBackupService::class)->create('پشتیبان خودکار پیش از به‌روزرسانی برنامه', 'PreUp');
        } catch (\Throwable) {
            return null;
        }
    }

    private function run(string $path, string $command, int $timeout): void
    {
        $result = Process::path($path)->timeout($timeout)->env($this->processEnv())->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(
                "دستور ناموفق بود: {$command}\n" . trim($result->errorOutput() ?: $result->output()),
            );
        }
    }

    /**
     * محیطِ اجرای دستورهای بیرونی (git/composer/php).
     *
     * روی دبیان، PHP-FPM با محیطِ پاک‌شده اجرا می‌شود (clear_env=yes پیش‌فرض است)،
     * پس www-data نه HOME دارد نه PATH کاملی. بدون این تنظیم:
     *   - composer پیدا نمی‌شود (چون در /usr/local/bin است، نه در PATH پیش‌فرضِ sh)
     *   - composer با «HOME or COMPOSER_HOME must be set» خطا می‌دهد
     * پس آپدیت از داخل مرورگر شکست می‌خورد. اینجا محیطِ سالمی می‌دهیم.
     *
     * @return array<string, string>
     */
    private function processEnv(): array
    {
        $path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $inherited = getenv('PATH');

        return [
            'HOME'                => storage_path('app'),
            'COMPOSER_HOME'       => storage_path('app/composer'),
            'PATH'                => $inherited ? $path . ':' . $inherited : $path,
            // اگر مخزن private باشد، git به‌جای معطل‌ماندن برای رمز، سریع خطا بدهد.
            'GIT_TERMINAL_PROMPT' => '0',
        ];
    }

    /**
     * کپی بازگشتی محتوای $src روی $dst، با نادیده‌گرفتن نام‌های سطح‌بالای $skip.
     *
     * @param  array<int, string>  $skip
     */
    private function copyOver(string $src, string $dst, array $skip): void
    {
        foreach (array_diff(scandir($src) ?: [], ['.', '..']) as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            $from = $src . '/' . $name;
            $to = $dst . '/' . $name;

            if (is_dir($from)) {
                @mkdir($to, 0775, true);
                $this->copyDir($from, $to);
            } else {
                @copy($from, $to);
            }
        }
    }

    private function copyDir(string $from, string $to): void
    {
        @mkdir($to, 0775, true);

        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $name) {
            $f = $from . '/' . $name;
            $t = $to . '/' . $name;

            if (is_dir($f)) {
                $this->copyDir($f, $t);
            } else {
                @copy($f, $t);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $path = $dir . '/' . $name;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
