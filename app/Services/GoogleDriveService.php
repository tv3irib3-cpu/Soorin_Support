<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * پشتیبان‌گیری روی گوگل‌درایو — بدونِ هیچ بستهٔ composer (چون هاستِ اشتراکی
 * `composer install` نمی‌کند). مستقیم با REST APIِ گوگل کار می‌کند.
 *
 * روشِ اتصال: OAuth با «حسابِ خودت» تا فایل‌ها روی فضای ذخیرهٔ خودِ گوگل‌درایوِ تو
 * بروند (نه سرویس‌اکانت با سهمیهٔ محدود). اسکوپِ drive.file یعنی برنامه فقط به
 * فایل‌هایی که خودش ساخته دسترسی دارد — کمترین دسترسیِ ممکن.
 *
 * تنظیمات در جدولِ settings (گروهِ gdrive) نگه داشته می‌شوند:
 *   client_id, client_secret, refresh_token, folder_id, email, enabled, include_files
 */
class GoogleDriveService
{
    public const GROUP = 'gdrive';

    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const FILES_URL    = 'https://www.googleapis.com/drive/v3/files';
    private const UPLOAD_URL   = 'https://www.googleapis.com/upload/drive/v3/files';

    private const SCOPE = 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/userinfo.email';

    private const ACCESS_TOKEN_CACHE = 'gdrive.access_token';

    // ----------------------------------------------------------- تنظیمات/وضعیت

    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get(self::GROUP . '.' . $key, $default);
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::set(self::GROUP . '.' . $key, $value, self::GROUP, $type);
    }

    /** آیا client_id/secret تنظیم شده؟ (پیش‌نیازِ اتصال) */
    public function isConfigured(): bool
    {
        return filled($this->get('client_id')) && filled($this->get('client_secret'));
    }

    /** آیا حساب متصل است؟ (refresh_token داریم) */
    public function isConnected(): bool
    {
        return filled($this->get('refresh_token'));
    }

    public function isEnabled(): bool
    {
        return $this->isConnected() && (string) $this->get('enabled') === '1';
    }

    public function includesFiles(): bool
    {
        return (string) $this->get('include_files') === '1';
    }

    public function connectedEmail(): ?string
    {
        $v = $this->get('email');

        return filled($v) ? (string) $v : null;
    }

    // ----------------------------------------------------------- جریانِ OAuth

    /** آدرسِ صفحهٔ رضایتِ گوگل؛ کاربر آنجا حساب را انتخاب و اجازه می‌دهد. */
    public function authUrl(string $redirectUri, string $state = ''): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => (string) $this->get('client_id'),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',   // تا حتماً refresh_token بدهد
            'state'         => $state,
        ]);
    }

    /** تبدیلِ code به توکن‌ها؛ refresh_token و ایمیل را ذخیره می‌کند. */
    public function exchangeCode(string $code, string $redirectUri): void
    {
        $res = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => (string) $this->get('client_id'),
            'client_secret' => (string) $this->get('client_secret'),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (! $res->successful()) {
            throw new RuntimeException('دریافتِ توکن از گوگل ناموفق بود: ' . trim($res->body()));
        }

        $data = $res->json();

        if (filled($data['refresh_token'] ?? null)) {
            $this->set('refresh_token', $data['refresh_token']);
        }

        if (filled($data['access_token'] ?? null)) {
            Cache::put(self::ACCESS_TOKEN_CACHE, $data['access_token'], now()->addMinutes(50));
        }

        // ایمیلِ حساب برای نمایش
        try {
            $me = Http::withToken($data['access_token'] ?? '')->get(self::USERINFO_URL);
            if ($me->successful() && filled($me->json('email'))) {
                $this->set('email', $me->json('email'));
            }
        } catch (\Throwable) {
            // نمایشِ ایمیل حیاتی نیست
        }

        // پوشهٔ مقصد را همان اول بساز.
        $this->ensureFolder();
    }

    /** قطعِ اتصال — توکن و شناسه‌ها پاک می‌شوند (فایل‌های روی درایو دست‌نخورده می‌مانند). */
    public function disconnect(): void
    {
        foreach (['refresh_token', 'folder_id', 'email', 'enabled', 'include_files'] as $k) {
            $this->set($k, '');
        }

        Cache::forget(self::ACCESS_TOKEN_CACHE);
    }

    /** access_token معتبر (از کش یا با refresh_token تازه می‌شود). */
    public function accessToken(): ?string
    {
        if ($cached = Cache::get(self::ACCESS_TOKEN_CACHE)) {
            return $cached;
        }

        if (! $this->isConnected() || ! $this->isConfigured()) {
            return null;
        }

        $res = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => (string) $this->get('client_id'),
            'client_secret' => (string) $this->get('client_secret'),
            'refresh_token' => (string) $this->get('refresh_token'),
            'grant_type'    => 'refresh_token',
        ]);

        if (! $res->successful()) {
            throw new RuntimeException('تازه‌سازیِ توکنِ گوگل ناموفق بود (شاید دسترسی لغو شده): ' . trim($res->body()));
        }

        $token = (string) $res->json('access_token');
        $ttl   = (int) ($res->json('expires_in') ?? 3600);
        Cache::put(self::ACCESS_TOKEN_CACHE, $token, now()->addSeconds(max(60, $ttl - 60)));

        return $token;
    }

    // ----------------------------------------------------------- پوشه/آپلود/دانلود

    /** پوشهٔ مقصد را می‌یابد یا می‌سازد و شناسه‌اش را ذخیره می‌کند. */
    public function ensureFolder(): ?string
    {
        $existing = (string) $this->get('folder_id');

        if ($existing !== '') {
            return $existing;
        }

        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        $name = 'Soorin Support Backups (' . parse_url(config('app.url'), PHP_URL_HOST) . ')';

        $res = Http::withToken($token)->post(self::FILES_URL, [
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if (! $res->successful()) {
            throw new RuntimeException('ساختِ پوشه روی گوگل‌درایو ناموفق بود: ' . trim($res->body()));
        }

        $id = (string) $res->json('id');
        $this->set('folder_id', $id);

        return $id;
    }

    /**
     * آپلودِ یک فایلِ محلی روی درایو (resumable — مناسبِ فایلِ بزرگ، بدونِ بارگذاریِ
     * کاملِ فایل در حافظه). نامِ روی درایو = $remoteName.
     *
     * @return string شناسهٔ فایل روی درایو
     */
    public function upload(string $localPath, string $remoteName): string
    {
        if (! is_file($localPath)) {
            throw new RuntimeException("فایل برای آپلود یافت نشد: {$localPath}");
        }

        $token  = $this->accessToken() ?? throw new RuntimeException('اتصالِ گوگل‌درایو برقرار نیست.');
        $folder = $this->ensureFolder();

        // ۱) آغازِ نشستِ resumable با متادیتا
        $init = Http::withToken($token)
            ->post(self::UPLOAD_URL . '?uploadType=resumable', [
                'name'    => $remoteName,
                'parents' => $folder ? [$folder] : [],
            ]);

        if (! $init->successful()) {
            throw new RuntimeException('آغازِ آپلود روی گوگل‌درایو ناموفق بود: ' . trim($init->body()));
        }

        $session = $init->header('Location');

        if (blank($session)) {
            throw new RuntimeException('گوگل‌درایو نشانیِ نشستِ آپلود را برنگرداند.');
        }

        // ۲) ارسالِ بایت‌ها به‌صورتِ جریان (بدونِ file_get_contents)
        $handle = fopen($localPath, 'r');

        try {
            $put = Http::withToken($token)
                ->withBody($handle, 'application/octet-stream')
                ->put($session);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $put->successful()) {
            throw new RuntimeException('آپلودِ فایل روی گوگل‌درایو ناموفق بود: ' . trim($put->body()));
        }

        return (string) $put->json('id');
    }

    /**
     * فهرستِ فایل‌های پوشهٔ پشتیبان روی درایو.
     *
     * @return array<int, array{id: string, name: string, size: int, modified: ?string}>
     */
    public function listFiles(): array
    {
        $token  = $this->accessToken();
        $folder = (string) $this->get('folder_id');

        if ($token === null || $folder === '') {
            return [];
        }

        $res = Http::withToken($token)->get(self::FILES_URL, [
            'q'        => "'{$folder}' in parents and trashed = false",
            'fields'   => 'files(id,name,size,modifiedTime)',
            'orderBy'  => 'modifiedTime desc',
            'pageSize' => 200,
        ]);

        if (! $res->successful()) {
            throw new RuntimeException('دریافتِ فهرستِ فایل‌های گوگل‌درایو ناموفق بود: ' . trim($res->body()));
        }

        return collect($res->json('files', []))
            ->map(fn ($f) => [
                'id'       => (string) $f['id'],
                'name'     => (string) $f['name'],
                'size'     => (int) ($f['size'] ?? 0),
                'modified' => $f['modifiedTime'] ?? null,
            ])
            ->all();
    }

    // ----------------------------------------------------------- هماهنگیِ پشتیبان/فایل

    /** آپلودِ یک فایلِ پشتیبانِ دیتابیس (‎.sql) از پوشهٔ backups روی درایو. */
    public function pushDatabaseBackup(string $backupName): string
    {
        $path = app(DatabaseBackupService::class)->absolutePath($backupName);

        return $this->upload($path, $backupName);
    }

    /** ساختِ ZIPِ یک دستهٔ فایل (پیوست/لوگو) و آپلودش روی درایو. */
    public function pushFilesBundle(string $category): string
    {
        $zip  = app(StorageService::class)->zip($category);
        $name = 'files-' . $category . '-' . now()->format('Ymd-His') . '.zip';

        try {
            return $this->upload($zip, $name);
        } finally {
            @unlink($zip);
        }
    }

    /**
     * بازگردانیِ یک فایل از درایو بر پایهٔ نامش:
     *   - ‎.sql → در پوشهٔ backups می‌نشیند تا از صفحهٔ «پشتیبان‌گیری» بازیابی شود.
     *   - files-<category>-*.zip → فایل‌های همان دسته بازگردانده می‌شوند.
     *
     * @return array{type: string, detail: string}
     */
    public function restoreFromDrive(string $fileId, string $name): array
    {
        if (str_ends_with(strtolower($name), '.sql')) {
            $dest = app(DatabaseBackupService::class)->absolutePath($name);
            $this->download($fileId, $dest);

            return ['type' => 'db', 'detail' => $name];
        }

        if (preg_match('/^files-([a-z_]+)-.*\.zip$/i', $name, $m)) {
            $category = $m[1];
            $tmp = storage_path('app/private/gdrive-restore-' . uniqid() . '.zip');
            $this->download($fileId, $tmp);

            try {
                $count = app(StorageService::class)->restore($category, $tmp);
            } finally {
                @unlink($tmp);
            }

            return ['type' => 'files', 'detail' => $category . ':' . $count];
        }

        throw new RuntimeException('نوعِ فایل برای بازگردانی شناخته نشد: ' . $name);
    }

    /** دانلودِ یک فایل از درایو به مسیرِ محلی. */
    public function download(string $fileId, string $toLocalPath): void
    {
        $token = $this->accessToken() ?? throw new RuntimeException('اتصالِ گوگل‌درایو برقرار نیست.');

        @mkdir(dirname($toLocalPath), 0775, true);

        $res = Http::withToken($token)
            ->sink($toLocalPath)
            ->get(self::FILES_URL . '/' . $fileId . '?alt=media');

        if (! $res->successful()) {
            @unlink($toLocalPath);

            throw new RuntimeException('دانلود از گوگل‌درایو ناموفق بود: ' . trim($res->body()));
        }
    }
}
