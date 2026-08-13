<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePagesTest extends TestCase
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

    public function test_invoices_list_page_loads(): void
    {
        $this->get('/admin/invoices')->assertOk();
    }

    public function test_create_invoice_page_loads(): void
    {
        $this->get('/admin/invoices/create')->assertOk();
    }

    public function test_create_invoice_from_ticket_prefills_customer(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $ticket   = Ticket::create([
            'customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح',
        ]);

        // برچسب مشتری با جاوااسکریپت نمایش داده می‌شود، نه در HTML اولیه —
        // پس مقدار پیش‌پرشده را در state لایوایر بررسی می‌کنیم، نه در متن صفحه.
        $response = $this->get('/admin/invoices/create?ticket=' . $ticket->id);

        $response->assertOk();
        $response->assertSee('&quot;customer_id&quot;:&quot;' . $customer->id . '&quot;', false);
        $response->assertSee('&quot;ticket_id&quot;:&quot;' . $ticket->id . '&quot;', false);
    }

    public function test_invoice_view_page_loads(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $invoice  = Invoice::create([
            'number' => 'F-1', 'customer_id' => $customer->id, 'issue_date' => now(),
        ]);

        $this->get("/admin/invoices/{$invoice->id}")->assertOk();
    }

    public function test_ticket_view_shows_create_invoice_action(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $ticket   = Ticket::create([
            'customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح',
        ]);

        $this->get("/admin/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee(__('tickets.create_invoice'));
    }
}
