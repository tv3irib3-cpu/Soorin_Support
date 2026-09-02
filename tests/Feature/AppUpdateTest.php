<?php

namespace Tests\Feature;

use App\Services\AppUpdateService;
use App\Support\AppVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * به‌روزرسانیِ داخلِ برنامه (مثلِ وردپرس، تک‌کلیکی) — فقط ادمینِ پشتیبان.
 */
class AppUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // کشِ «به‌روز» تا رندرِ صفحه سراغِ گیت/شبکه نرود.
        Cache::forever(AppUpdateService::CACHE_KEY, [
            'method' => 'git', 'current' => AppVersion::current(),
            'latest' => AppVersion::current(), 'available' => false,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    private function staff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 'staff@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $u->assignRole(User::TYPE_SUPPORT_STAFF);

        return $u;
    }

    public function test_version_is_read_from_the_version_file(): void
    {
        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), AppVersion::current());
    }

    public function test_update_page_loads_for_support_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/app-update')->assertOk();
    }

    public function test_update_page_is_forbidden_for_support_staff(): void
    {
        $this->actingAs($this->staff())->get('/admin/app-update')->assertForbidden();
    }
}
