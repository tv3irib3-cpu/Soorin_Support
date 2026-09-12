<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentLoginRecordsLastLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_login_via_panel_records_last_login(): void
    {
        $admin = User::create([
            'name' => 'مدیر', 'email' => 'boss', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->assertNull($admin->last_login_at);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'boss', 'password' => 'secret123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertNotNull($admin->fresh()->last_login_at, 'ورودِ پنلِ ادمین باید آخرین ورود را ثبت کند');
    }

    public function test_staff_login_via_panel_records_last_login(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'agent', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_STAFF, 'is_active' => true,
        ]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'agent', 'password' => 'secret123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertNotNull($staff->fresh()->last_login_at, 'ورودِ کارشناس باید آخرین ورود را ثبت کند');
    }

    /**
     * نشستِ زنده‌ای که پیش از افزودنِ ثبتِ آخرین ورود ساخته شده (رویدادِ Login
     * دیگر برایش اجرا نمی‌شود): نخستین درخواستِ پنل باید آخرین ورود را پر کند.
     */
    public function test_existing_support_session_gets_last_login_backfilled(): void
    {
        $admin = User::create([
            'name' => 'مدیر قدیمی', 'email' => 'veteran', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->assertNull($admin->last_login_at);

        // کاربر از قبل احراز شده و صرفاً صفحه‌ای از پنل را باز می‌کند (بدون ورودِ دوباره)
        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertNotNull($admin->fresh()->last_login_at, 'نشستِ قدیمی باید در نخستین بازدید پر شود');
    }
}
