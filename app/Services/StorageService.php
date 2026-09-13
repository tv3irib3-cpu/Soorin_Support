<?php

namespace App\Services;

use App\Models\TicketAttachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * مدیریتِ فایل‌های ذخیره‌شدهٔ سامانه (استوریج).
 *
 * همهٔ داده‌ای که سامانه روی دیسک نگه می‌دارد و هنگامِ جابه‌جاییِ هاست باید همراه
 * برده شود، اینجا در قالبِ چند «دسته» تعریف شده تا بشود آدرس، حجم و تعدادشان را
 * دید، همه را ZIP و دانلود کرد، از ZIP بازگرداند، و پیوست‌های قدیمی را پاک کرد.
 *
 * دسته‌ها:
 *   - attachments    : پیوستِ تیکت‌ها            → storage/app/private/ticket-attachments
 *   - customer_logos : لوگوی مشتریان            → public/branding/customers
 *   - brand_logos    : لوگوهای برند/شخصی‌سازی    → public/branding/logos
 *   - backups        : پشتیبان‌های دیتابیس (‎.sql) → storage/app/private/backups
 */
class StorageService
{
    /** پوشهٔ ساختِ فایل‌های ZIP خروجی (موقت). */
    private const EXPORT_DIR = 'storage-exports';

    /**
     * تعریفِ دسته‌ها: دیسک، پوشه، و توانمندی‌ها.
     *
     * @return array<string, array{disk: string, dir: string, cleanup: bool}>
     */
    public static function categories(): array
    {
        return [
            'attachments'    => ['disk' => 'local',    'dir' => 'ticket-attachments', 'cleanup' => true],
            'customer_logos' => ['disk' => 'branding', 'dir' => 'customers',          'cleanup' => false],
            'brand_logos'    => ['disk' => 'branding', 'dir' => 'logos',              'cleanup' => false],
            'backups'        => ['disk' => 'local',    'dir' => 'backups',            'cleanup' => false],
        ];
    }

    private function config(string $key): array
    {
        $cats = self::categories();

        if (! isset($cats[$key])) {
            throw new RuntimeException("دستهٔ استوریجِ نامعتبر: {$key}");
        }

        return $cats[$key];
    }

    /** مسیرِ مطلقِ پوشهٔ یک دسته روی سرور. */
    public function absoluteDir(string $key): string
    {
        $c = $this->config($key);

        return rtrim(Storage::disk($c['disk'])->path($c['dir']), '/\\');
    }

    /** مسیرِ نسبی به ریشهٔ نصب (برای نمایش، خواناتر از مسیرِ مطلق). */
    public function relativeDir(string $key): string
    {
        $abs  = str_replace('\\', '/', $this->absoluteDir($key));
        $base = str_replace('\\', '/', rtrim(base_path(), '/\\')) . '/';

        return str_starts_with($abs, $base) ? substr($abs, strlen($base)) : $abs;
    }

    /**
     * تعداد و حجمِ فایل‌های یک دسته.
     *
     * @return array{count: int, bytes: int}
     */
    public function stats(string $key): array
    {
        $c    = $this->config($key);
        $disk = Storage::disk($c['disk']);

        $count = 0;
        $bytes = 0;

        foreach ($disk->files($c['dir']) as $file) {
            $count++;
            $bytes += (int) $disk->size($file);
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * خلاصهٔ همهٔ دسته‌ها برای نمایش در صفحه.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(): array
    {
        $rows = [];

        foreach (array_keys(self::categories()) as $key) {
            $c     = $this->config($key);
            $stats = $this->stats($key);

            $rows[] = [
                'key'         => $key,
                'label'       => __("storage.categories.$key"),
                'description' => __("storage.descriptions.$key"),
                'absolute'    => $this->absoluteDir($key),
                'relative'    => $this->relativeDir($key),
                'count'       => $stats['count'],
                'bytes'       => $stats['bytes'],
                'human'       => self::humanBytes($stats['bytes']),
                'in_webroot'  => $c['disk'] === 'branding',
                'cleanup'     => $c['cleanup'],
            ];
        }

        return $rows;
    }

    /**
     * ساختِ ZIP از همهٔ فایل‌های یک دسته. فقط یک خروجیِ هر دسته نگه داشته می‌شود.
     * ورودی‌های ZIP «تخت» (فقط نامِ فایل) هستند تا بازگردانی ساده بماند.
     *
     * @return string مسیرِ مطلقِ فایلِ ZIP
     */
    public function zip(string $key): string
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $c    = $this->config($key);
        $disk = Storage::disk($c['disk']);

        $exportDir = storage_path('app/private/' . self::EXPORT_DIR);
        @mkdir($exportDir, 0775, true);

        // خروجی‌های قبلیِ همین دسته را پاک کن تا فضا انباشته نشود.
        foreach (glob($exportDir . '/soorin-' . $key . '-*.zip') ?: [] as $old) {
            @unlink($old);
        }

        $zipPath = $exportDir . '/soorin-' . $key . '-' . now()->format('Ymd-His') . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساختِ فایلِ ZIP ممکن نشد.');
        }

        foreach ($disk->files($c['dir']) as $file) {
            $abs = $disk->path($file);

            if (is_file($abs)) {
                $zip->addFile($abs, basename($file));
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * بازگردانیِ فایل‌ها از یک ZIP به پوشهٔ دسته. ورودی‌ها با basename (تخت) نوشته
     * می‌شوند تا از path traversal جلوگیری شود؛ ساختارِ تودرتوی احتمالی هم صاف می‌شود.
     *
     * @return int تعدادِ فایلِ بازگردانده‌شده
     */
    public function restore(string $key, string $zipPath): int
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $c    = $this->config($key);
        $disk = Storage::disk($c['disk']);

        if (! $disk->exists($c['dir'])) {
            $disk->makeDirectory($c['dir']);
        }

        $targetDir = $this->absoluteDir($key);

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('بازکردنِ فایلِ ZIP ممکن نشد.');
        }

        $tmp = storage_path('app/private/' . self::EXPORT_DIR . '/restore-' . uniqid());
        @mkdir($tmp, 0775, true);
        $zip->extractTo($tmp);
        $zip->close();

        $restored = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = basename($file->getPathname());

            // نام‌های خطرناک/پنهان را رد کن.
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }

            if (@copy($file->getPathname(), $targetDir . DIRECTORY_SEPARATOR . $name)) {
                $restored++;
            }
        }

        $this->rrmdir($tmp);

        return $restored;
    }

    /**
     * حذفِ پیوست‌های قدیمی‌تر از یک تاریخ — هم فایل روی دیسک، هم ردیفِ دیتابیس
     * (تا ارجاعِ شکسته نماند). فقط دستهٔ attachments.
     *
     * @return array{rows: int, bytes: int}
     */
    public function deleteAttachmentsOlderThan(Carbon $cutoff): array
    {
        $disk = Storage::disk('local');
        $rows = 0;
        $bytes = 0;

        TicketAttachment::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($chunk) use ($disk, &$rows, &$bytes): void {
                foreach ($chunk as $att) {
                    if ($att->path && $disk->exists($att->path)) {
                        $bytes += (int) $disk->size($att->path);
                        $disk->delete($att->path);
                    }

                    $att->delete();
                    $rows++;
                }
            });

        return ['rows' => $rows, 'bytes' => $bytes];
    }

    /**
     * گزینه‌های بازهٔ حذف: برچسب => تعدادِ ماه.
     *
     * @return array<string, int>
     */
    public static function ageOptions(): array
    {
        return [
            __('storage.ages.1m')  => 1,
            __('storage.ages.3m')  => 3,
            __('storage.ages.6m')  => 6,
            __('storage.ages.1y')  => 12,
            __('storage.ages.2y')  => 24,
            __('storage.ages.3y')  => 36,
            __('storage.ages.5y')  => 60,
            __('storage.ages.10y') => 120,
        ];
    }

    /** تبدیلِ بایت به رشتهٔ خوانا (فارسی‌رقم در نما جدا اعمال می‌شود). */
    public static function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 2) . ' ' . $units[$i];
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
