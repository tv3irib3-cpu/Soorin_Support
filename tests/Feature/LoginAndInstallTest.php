<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ورودِ پنل با نام‌کاربریِ ساده (admin، نه ایمیل) + نصب‌کننده‌ی وب.
 */
class LoginAndInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_log_in_with_a_plain_username(): void
    {
        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin', 'password' => 'secret123'])
            ->call('authenticate');

        $this->assertAuthenticated();
        $this->assertSame($admin->id, auth()->id());
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin', 'password' => 'WRONG'])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_install_form_is_shown_on_a_fresh_install(): void
    {
        // جدول‌ها هست ولی هیچ کاربری نیست → فرمِ نصب نمایش داده می‌شود.
        $this->get('/install')
            ->assertOk()
            ->assertSee('نصبِ سامانه', false)
            ->assertSee('نام دیتابیس', false)
            ->assertSee('حساب مدیر', false);
    }

    public function test_install_route_reports_already_installed_when_users_exist(): void
    {
        User::create([
            'name' => 'مدیر', 'email' => 'admin', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);

        $this->get('/install')->assertOk()->assertSee('از قبل نصب شده', false);
    }
}
