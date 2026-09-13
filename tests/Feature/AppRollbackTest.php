<?php

namespace Tests\Feature;

use App\Filament\Pages\AppUpdate;
use App\Models\Setting;
use App\Models\User;
use App\Services\AppUpdateService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * قابلیتِ «بازگشت به نسخهٔ قبلی» (دانگرید).
 *
 * توجه: خودِ rollback() اجراکنندهٔ git reset / بازگردانیِ فایل روی base_path است و
 * نباید در تست اجرا شود؛ اینجا فقط منطقِ نقطهٔ بازگشت و نمایانیِ دکمه آزموده می‌شود.
 */
class AppRollbackTest extends TestCase
{
    use RefreshDatabase;

    private function seedGitRollbackPoint(): void
    {
        Setting::set('update.rollback_method', 'git', 'update', 'string');
        Setting::set('update.rollback_version', '1.5.20', 'update', 'string');
        Setting::set('update.rollback_commit', 'abc123', 'update', 'string');
        Setting::set('update.rollback_backup', 'PreUp-x.sql', 'update', 'string');
        Setting::set('update.rollback_snapshot', '', 'update', 'string');
        Setting::set('update.rollback_at', now()->toIso8601String(), 'update', 'string');
    }

    public function test_no_rollback_point_means_cannot_rollback(): void
    {
        $this->assertNull(app(AppUpdateService::class)->rollbackInfo());
        $this->assertFalse(app(AppUpdateService::class)->canRollback());
    }

    public function test_git_rollback_point_is_valid_without_snapshot_file(): void
    {
        $this->seedGitRollbackPoint();

        $info = app(AppUpdateService::class)->rollbackInfo();

        $this->assertNotNull($info);
        $this->assertSame('git', $info['method']);
        $this->assertSame('1.5.20', $info['version']);
        $this->assertTrue(app(AppUpdateService::class)->canRollback());
    }

    public function test_package_rollback_point_is_invalid_when_snapshot_missing(): void
    {
        Setting::set('update.rollback_method', 'package', 'update', 'string');
        Setting::set('update.rollback_version', '1.5.20', 'update', 'string');
        Setting::set('update.rollback_snapshot', storage_path('app/rollback/does-not-exist.zip'), 'update', 'string');

        // بدونِ فایلِ عکس، نقطهٔ بازگشتِ بسته بی‌اعتبار است.
        $this->assertNull(app(AppUpdateService::class)->rollbackInfo());
        $this->assertFalse(app(AppUpdateService::class)->canRollback());
    }

    public function test_package_rollback_point_is_valid_when_snapshot_exists(): void
    {
        $dir = storage_path('app/rollback');
        @mkdir($dir, 0775, true);
        $snap = $dir . '/code-test-' . uniqid() . '.zip';
        file_put_contents($snap, 'PK');   // فایلِ ساختگی، فقط برای وجودِ مسیر

        try {
            Setting::set('update.rollback_method', 'package', 'update', 'string');
            Setting::set('update.rollback_version', '1.5.20', 'update', 'string');
            Setting::set('update.rollback_snapshot', $snap, 'update', 'string');

            $this->assertTrue(app(AppUpdateService::class)->canRollback());
            $this->assertSame('1.5.20', app(AppUpdateService::class)->rollbackInfo()['version']);
        } finally {
            @unlink($snap);
        }
    }

    public function test_rollback_button_hidden_without_point_and_visible_with_point(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        // بدونِ نقطهٔ بازگشت → دکمه پنهان
        Livewire::test(AppUpdate::class)->assertActionHidden('rollback');

        // با نقطهٔ بازگشتِ گیت → دکمه نمایان
        $this->seedGitRollbackPoint();
        Livewire::test(AppUpdate::class)->assertActionVisible('rollback');
    }
}
