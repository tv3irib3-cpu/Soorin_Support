<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * فوتر (نام شرکت + لینک dpst.ir + نسخه) باید در پنل مدیریت و پرتال دیده شود.
 */
class FooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_appears_on_admin_panel(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('dpst.ir');
        $response->assertSee(config('branding.app.version'));
    }

    public function test_footer_appears_on_portal_login(): void
    {
        $response = $this->get('/portal/login');

        $response->assertOk();
        $response->assertSee('dpst.ir');
    }
}
