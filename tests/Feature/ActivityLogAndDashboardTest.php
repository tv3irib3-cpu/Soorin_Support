<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function supportAdmin(): User
    {
        $u = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    public function test_activity_log_records_ticket_creation(): void
    {
        $admin = $this->supportAdmin();
        $this->actingAs($admin);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        Ticket::create(['customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created', 'subject_type' => Ticket::class,
        ]);
    }

    public function test_activity_log_page_loads_for_support_admin(): void
    {
        $this->actingAs($this->supportAdmin())
            ->get('/admin/activity-logs')
            ->assertOk();
    }

    public function test_activity_log_hidden_from_support_staff(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);

        $this->actingAs($staff)->get('/admin/activity-logs')->assertForbidden();
    }

    public function test_dashboard_shows_stats_widget(): void
    {
        $admin = $this->supportAdmin();
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        Ticket::create(['customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee(__('portal.open_tickets'));
        $response->assertSee(__('tickets.sla_breached'));
    }
}
