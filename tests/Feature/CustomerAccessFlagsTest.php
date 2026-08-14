<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * دسترسی‌های سطح سازمان مشتری باید واقعاً اعمال شوند — نه اینکه فقط
 * کلید در فرم مدیر وجود داشته باشد.
 *
 * سناریوی صریح مالک پروژه: «دسترسی یک مشتری را به تاریخچه ببندم ولی
 * فقط بتواند تیکت جدید بزند».
 */
class CustomerAccessFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function customerAdminFor(Customer $customer): User
    {
        return User::create([
            'name' => 'مدیر مشتری', 'email' => fake()->unique()->safeEmail(),
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_can_view_history_off_hides_all_tickets_even_from_customer_admin(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'can_view_history' => false]);
        Ticket::create(['customer_id' => $customer->id, 'subject' => 'قدیمی', 'description' => 'شرح']);

        $admin = $this->customerAdminFor($customer);

        $this->assertSame('none', $admin->historyScope());
        $this->assertSame(0, Ticket::visibleTo($admin)->count());
    }

    public function test_can_view_history_off_still_allows_creating_tickets(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'can_view_history' => false]);
        $admin = $this->customerAdminFor($customer);

        // این دقیقاً سناریوی مالک پروژه است: تاریخچه بسته، ثبت تیکت باز
        $this->assertTrue($admin->canCreateTicket());
    }

    public function test_can_view_history_on_restores_visibility(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'can_view_history' => true]);
        Ticket::create(['customer_id' => $customer->id, 'subject' => 'قدیمی', 'description' => 'شرح']);

        $admin = $this->customerAdminFor($customer);

        $this->assertSame('customer', $admin->historyScope());
        $this->assertSame(1, Ticket::visibleTo($admin)->count());
    }

    public function test_org_level_off_overrides_account_level_setting(): void
    {
        // سازمان بسته، ولی حساب صریحاً «همه تیکت‌های مشتری» دارد →
        // سقف سازمان باید برنده شود
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'can_view_history' => false]);
        $user = User::create([
            'name' => 'کاربر', 'email' => 'u@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id,
            'history_scope' => 'customer',
        ]);

        $this->assertSame('none', $user->historyScope());
    }

    public function test_portal_ticket_submission_redirects_safely_when_history_hidden(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'can_view_history' => false]);
        $admin = $this->customerAdminFor($customer);
        $admin->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $response = $this->actingAs($admin)->post(route('portal.tickets.store'), [
            'subject' => 'مشکل جدید', 'description' => 'شرح مشکل',
        ]);

        // نباید به صفحه‌ای هدایت شود که برایش ۴۰۴ می‌دهد
        $response->assertRedirect(route('portal.dashboard'));
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_deleting_invoice_releases_contract_ceiling(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $plan = ContractPlan::create(['name' => 'طلایی', 'cover_software' => 100, 'ceiling_amount' => 10_000_000]);
        $contract = Contract::create([
            'number' => 'C-1', 'customer_id' => $customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(),
        ]);

        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $customer->id, 'contract_id' => $contract->id, 'issue_date' => now(),
        ]);
        $item = $invoice->items()->create(['item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => 3_000_000]);
        $item->recalculate($plan, 'software');
        $invoice->recalculate();

        $this->assertSame(3_000_000, $contract->fresh()->used_amount);

        $invoice->delete();

        $this->assertSame(0, $contract->fresh()->used_amount);
    }

    public function test_cancelled_then_deleted_invoice_does_not_double_release(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $plan = ContractPlan::create(['name' => 'طلایی', 'cover_software' => 100, 'ceiling_amount' => 10_000_000]);
        $contract = Contract::create([
            'number' => 'C-1', 'customer_id' => $customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(),
        ]);

        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $customer->id, 'contract_id' => $contract->id,
            'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED,
        ]);
        $item = $invoice->items()->create(['item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => 3_000_000]);
        $item->recalculate($plan, 'software');
        $invoice->recalculate();

        $invoice->cancel();
        $this->assertSame(0, $contract->fresh()->used_amount);

        $invoice->delete();

        // نباید منفی شود
        $this->assertSame(0, $contract->fresh()->used_amount);
    }
}
