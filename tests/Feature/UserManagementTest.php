<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_users_page_loads_for_support_admin(): void
    {
        $this->actingAs($this->supportAdmin())->get('/admin/users')->assertOk();
    }

    public function test_support_staff_cannot_create_users(): void
    {
        $this->actingAs($this->supportStaff())->get('/admin/users/create')->assertForbidden();
    }

    public function test_create_user_page_loads_for_admin(): void
    {
        $this->actingAs($this->supportAdmin())->get('/admin/users/create')->assertOk();
    }

    public function test_edit_customer_admin_user_page_loads(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $customerUser = User::create([
            'name' => 'مدیر آریا', 'email' => 'owner@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id,
        ]);

        // برچسب مشتری با جاوااسکریپت نمایش داده می‌شود؛ مقدار واقعی را در
        // state لایوایر بررسی می‌کنیم (همان الگوی InvoicePagesTest)
        $response = $this->actingAs($this->supportAdmin())
            ->get("/admin/users/{$customerUser->id}/edit");

        $response->assertOk();
        $response->assertSee('&quot;customer_id&quot;:&quot;' . $customer->id . '&quot;', false);
    }
}
