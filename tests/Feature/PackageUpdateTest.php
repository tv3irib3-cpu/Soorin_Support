<?php

namespace Tests\Feature;

use App\Services\AppUpdateService;
use App\Support\AppVersion;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * به‌روزرسانیِ تک‌کلیکیِ بدونِ SSH (مثلِ وردپرس) — بررسیِ «مانیفست».
 * خودِ اعمالِ بسته (دانلود/extract) به فایلِ واقعی نیاز دارد و اینجا تست نمی‌شود؛
 * منطقِ تشخیصِ نسخهٔ جدید که مغزِ کار است، تست می‌شود.
 */
class PackageUpdateTest extends TestCase
{
    public function test_manifest_not_configured_by_default(): void
    {
        Config::set('branding.update.manifest', null);

        $this->assertFalse(app(AppUpdateService::class)->manifestConfigured());
    }

    public function test_package_status_reports_available_for_newer_version(): void
    {
        Config::set('branding.update.manifest', 'https://updates.test/latest.json');
        Http::fake(['updates.test/*' => Http::response([
            'version' => '999.0.0',
            'zip'     => 'https://updates.test/soorin-support-999.0.0.zip',
            'sha256'  => 'deadbeef',
        ], 200)]);

        $s = app(AppUpdateService::class)->packageStatus();

        $this->assertSame('package', $s['method']);
        $this->assertTrue($s['available']);
        $this->assertSame('999.0.0', $s['latest']);
        $this->assertSame('https://updates.test/soorin-support-999.0.0.zip', $s['zip']);
    }

    public function test_package_status_up_to_date_for_same_version(): void
    {
        Config::set('branding.update.manifest', 'https://updates.test/latest.json');
        Http::fake(['updates.test/*' => Http::response([
            'version' => AppVersion::current(),
            'zip'     => 'https://updates.test/x.zip',
        ], 200)]);

        $this->assertFalse(app(AppUpdateService::class)->packageStatus()['available']);
    }

    public function test_package_status_surfaces_a_connection_error(): void
    {
        Config::set('branding.update.manifest', 'https://updates.test/latest.json');
        Http::fake(['updates.test/*' => Http::response('', 500)]);

        $s = app(AppUpdateService::class)->packageStatus();

        $this->assertFalse($s['available']);
        $this->assertNotEmpty($s['error']);
    }
}
