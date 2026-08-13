<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);
    }

    public function test_contract_number_is_generated_automatically(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $plan     = ContractPlan::create(['name' => 'طلایی']);

        $contract = Contract::create([
            'customer_id' => $customer->id,
            'contract_plan_id' => $plan->id,
            'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        $this->assertNotEmpty($contract->number);
        $this->assertStringStartsWith('C-', $contract->number);
    }

    public function test_expired_command_marks_past_contracts(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $plan     = ContractPlan::create(['name' => 'طلایی']);

        $expired = Contract::create([
            'customer_id' => $customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subYear(), 'end_date' => now()->subDay(),
        ]);

        $stillActive = Contract::create([
            'customer_id' => $customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(),
        ]);

        $this->artisan('contracts:expire')->assertSuccessful();

        $this->assertSame(Contract::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertSame(Contract::STATUS_ACTIVE, $stillActive->fresh()->status);
    }

    public function test_contract_plans_page_loads(): void
    {
        $this->get('/admin/contract-plans')->assertOk();
    }

    public function test_contracts_list_page_loads(): void
    {
        $this->get('/admin/contracts')->assertOk();
    }

    public function test_create_contract_page_loads(): void
    {
        $this->get('/admin/contracts/create')->assertOk();
    }
}
