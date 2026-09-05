<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_pages_render_for_customer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $customer = Customer::create(['code' => 'C1', 'name' => 'مشتری تست', 'can_view_invoices' => true]);
        $user = User::create([
            'name' => 'کاربر مشتری', 'email' => 'cust@test', 'password' => 'password',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id,
        ]);
        $user->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $this->actingAs($user);
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('کاربر مشتری');
        $this->get(route('portal.tickets.index'))->assertOk();
        $this->get(route('portal.tickets.create'))->assertOk();
        $this->get(route('portal.invoices.index'))->assertOk();
    }
}
