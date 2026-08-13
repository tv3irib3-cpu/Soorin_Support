<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoicePdfService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست خروجی PDF فاکتور و کنترل دسترسی مسیرهای PDF.
 */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customerA;
    private Customer $customerB;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customerA = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->customerB = Customer::create(['code' => 'PARS', 'name' => 'پارس دریا']);

        $this->invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $this->customerA->id,
            'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED,
        ]);
        $item = $this->invoice->items()->create([
            'item_type' => 'service', 'title' => 'خدمت تست', 'quantity' => 1, 'unit_price' => 1_000_000,
        ]);
        $item->recalculate(null, 'hardware');
        $this->invoice->recalculate();
    }

    public function test_pdf_service_generates_valid_pdf_bytes(): void
    {
        $output = app(InvoicePdfService::class)->render($this->invoice->fresh())->Output('', 'S');

        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_support_user_can_view_invoice_pdf(): void
    {
        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $response = $this->actingAs($admin)->get(route('invoices.pdf.view', $this->invoice));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_customer_from_other_company_cannot_view_invoice(): void
    {
        $outsider = User::create([
            'name' => 'کاربر دیگر', 'email' => 'outsider@pars.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customerB->id,
        ]);
        $outsider->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $this->actingAs($outsider)
            ->get(route('invoices.pdf.view', $this->invoice))
            ->assertForbidden();
    }

    public function test_customer_admin_of_same_company_can_view_if_permitted(): void
    {
        $this->customerA->update(['can_view_invoices' => true]);

        $owner = User::create([
            'name' => 'مدیر آریا', 'email' => 'owner@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customerA->id,
        ]);
        $owner->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $this->actingAs($owner)
            ->get(route('invoices.pdf.view', $this->invoice))
            ->assertOk();
    }

    public function test_customer_without_invoice_permission_is_forbidden(): void
    {
        $this->customerA->update(['can_view_invoices' => false]);

        $owner = User::create([
            'name' => 'مدیر آریا', 'email' => 'owner2@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customerA->id,
        ]);
        $owner->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $this->actingAs($owner)
            ->get(route('invoices.pdf.view', $this->invoice))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('invoices.pdf.view', $this->invoice))->assertRedirect('/login');
    }
}
