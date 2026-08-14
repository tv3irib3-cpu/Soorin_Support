<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_support_admin_can_open_sms_settings_page(): void
    {
        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->actingAs($admin)->get('/admin/sms-settings')->assertOk();
    }

    public function test_support_staff_cannot_open_sms_settings_page(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);

        $this->actingAs($staff)->get('/admin/sms-settings')->assertForbidden();
    }

    public function test_setting_persists_via_model(): void
    {
        Setting::set('sms.enabled', true, 'sms', 'bool');

        $this->assertTrue((bool) Setting::get('sms.enabled'));
    }
}
