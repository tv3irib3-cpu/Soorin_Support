<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست دسترسی به پنل مدیریت.
 * پنل فقط برای کاربران داخلی است — کاربر مشتری حتی با آدرس مستقیم راه ندارد.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUser(string $type, ?Customer $customer = null): User
    {
        $user = User::create([
            'name'        => 'کاربر',
            'email'       => fake()->unique()->safeEmail(),
            'password'    => 'secret123',
            'user_type'   => $type,
            'customer_id' => $customer?->id,
        ]);

        $user->assignRole($type);

        return $user;
    }

    public function test_login_page_is_reachable_and_rtl(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('Vazirmatn', false);
    }

    public function test_support_admin_can_open_dashboard(): void
    {
        $this->actingAs($this->makeUser(User::TYPE_SUPPORT_ADMIN))
            ->get('/admin')
            ->assertOk();
    }

    public function test_support_staff_can_open_dashboard(): void
    {
        $this->actingAs($this->makeUser(User::TYPE_SUPPORT_STAFF))
            ->get('/admin')
            ->assertOk();
    }

    public function test_customer_user_is_blocked_from_admin_panel(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);

        foreach ([User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF] as $type) {
            $response = $this->actingAs($this->makeUser($type, $customer))->get('/admin');

            // یا به پرتال هدایت می‌شود یا دسترسی رد می‌شود — ولی هرگز پنل را نمی‌بیند
            $this->assertContains(
                $response->getStatusCode(),
                [302, 403],
                "کاربر {$type} نباید به پنل دسترسی داشته باشد",
            );
        }
    }

    public function test_customers_list_is_visible_to_support_admin(): void
    {
        Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);

        $this->actingAs($this->makeUser(User::TYPE_SUPPORT_ADMIN))
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('شرکت آریا');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
