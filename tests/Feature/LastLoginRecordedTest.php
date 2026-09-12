<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LastLoginRecordedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_support_user_login_records_last_login(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'admin', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->assertNull($admin->last_login_at);

        Auth::login($admin);   // رویدادِ Login را می‌زند (مثلِ ورودِ پنلِ ادمین)

        $this->assertNotNull($admin->fresh()->last_login_at, 'آخرین ورودِ کاربرِ پشتیبان باید ثبت شود');
        $this->assertTrue(ActivityLog::where('action', 'login')->where('user_id', $admin->id)->exists());
    }

    public function test_inactive_user_login_is_not_recorded(): void
    {
        $u = User::create(['name' => 'X', 'email' => 'inactive', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_STAFF, 'is_active' => false]);

        Auth::login($u);

        $this->assertNull($u->fresh()->last_login_at);
    }
}
