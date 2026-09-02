<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * شخصی‌سازیِ برند — فقط ادمینِ پشتیبان. مقدارِ ذخیره‌شده باید جایگزینِ پیش‌فرضِ
 * config شود تا همه‌جا (از جمله هدرِ فایلِ پشتیبان و نوارِ بالا) دیده شود.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function supportAdmin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    private function supportStaff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 'staff@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $u->assignRole(User::TYPE_SUPPORT_STAFF);

        return $u;
    }

    public function test_branding_page_loads_for_support_admin(): void
    {
        $this->actingAs($this->supportAdmin())->get('/admin/branding-settings')->assertOk();
    }

    public function test_branding_page_is_forbidden_for_support_staff(): void
    {
        $this->actingAs($this->supportStaff())->get('/admin/branding-settings')->assertForbidden();
    }

    public function test_app_title_falls_back_to_config_then_uses_saved_value(): void
    {
        // پیش‌فرض از config
        $this->assertSame(config('branding.app.title'), Branding::appTitle());

        // پس از ذخیره، مقدارِ سفارشی
        Setting::set('branding.app_title', 'سامانهٔ سفارشی', Branding::GROUP);

        $this->assertSame('سامانهٔ سفارشی', Branding::appTitle());
    }
}
