<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * دامنهٔ دیدِ فاکتور برای کاربرانِ مشتری:
 *   مدیرِ مشتری   → همهٔ فاکتورهای شرکت (مستقل از تیکت)
 *   کارشناسِ مشتری → فقط فاکتورهای تیکت‌هایی که خودش ساخته یا به او تخصیص یافته
 */
class CustomerInvoiceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $admin;
    private User $staff;
    private User $otherStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا', 'can_view_invoices' => true, 'can_print_invoices' => true]);

        $this->admin = User::create(['name' => 'مدیرِ مشتری', 'email' => 'ca@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);
        $this->staff = User::create(['name' => 'کارشناسِ مشتری', 'email' => 'cs@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $this->customer->id, 'is_active' => true]);
        $this->otherStaff = User::create(['name' => 'کارشناسِ دیگر', 'email' => 'cs2@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $this->customer->id, 'is_active' => true]);
    }

    private function invoiceForTicket(?Ticket $ticket, string $number): Invoice
    {
        return Invoice::create([
            'number' => $number,
            'customer_id' => $this->customer->id,
            'ticket_id' => $ticket?->id,
            'issue_date' => now(),
            'status' => Invoice::STATUS_ISSUED,
        ]);
    }

    private function ticket(array $attrs): Ticket
    {
        $t = Ticket::create(array_merge(['customer_id' => $this->customer->id, 'subject' => 's', 'description' => 'd'], $attrs));

        return $t;
    }

    public function test_staff_sees_only_invoices_of_own_tickets(): void
    {
        $mineCreated  = $this->ticket(['created_by' => $this->staff->id]);
        $mineAssigned = $this->ticket(['customer_assigned_to' => $this->staff->id]);
        $othersTicket = $this->ticket(['created_by' => $this->otherStaff->id]);

        $invMineA   = $this->invoiceForTicket($mineCreated, 'F-A');
        $invMineB   = $this->invoiceForTicket($mineAssigned, 'F-B');
        $invOthers  = $this->invoiceForTicket($othersTicket, 'F-C');
        $invNoTicket = $this->invoiceForTicket(null, 'F-D');   // بدونِ تیکت

        $visible = Invoice::visibleToCustomer($this->staff)->pluck('number')->all();

        $this->assertContains('F-A', $visible);
        $this->assertContains('F-B', $visible);
        $this->assertNotContains('F-C', $visible);   // مالِ کارشناسِ دیگر
        $this->assertNotContains('F-D', $visible);   // بدونِ تیکت
    }

    public function test_admin_sees_all_company_invoices(): void
    {
        $t = $this->ticket(['created_by' => $this->otherStaff->id]);
        $this->invoiceForTicket($t, 'F-A');
        $this->invoiceForTicket(null, 'F-B');   // بدونِ تیکت

        $visible = Invoice::visibleToCustomer($this->admin)->pluck('number')->all();

        $this->assertEqualsCanonicalizing(['F-A', 'F-B'], $visible);
    }

    public function test_staff_can_view_own_invoice_pdf_but_not_others(): void
    {
        $mine   = $this->invoiceForTicket($this->ticket(['created_by' => $this->staff->id]), 'F-MINE');
        $others = $this->invoiceForTicket($this->ticket(['created_by' => $this->otherStaff->id]), 'F-OTHER');

        $this->actingAs($this->staff)->get(route('invoices.pdf.view', $mine))->assertOk();
        $this->actingAs($this->staff)->get(route('invoices.pdf.view', $others))->assertForbidden();
    }

    public function test_staff_can_view_invoices_by_default(): void
    {
        // بدونِ هیچ تنظیمِ صریحی، کارشناسِ مشتری باید دسترسیِ دیدنِ فاکتور داشته باشد.
        $this->assertTrue($this->staff->canViewInvoices());
    }

    public function test_admin_can_revoke_staff_invoice_access(): void
    {
        $this->staff->forceFill(['can_view_invoices' => false])->save();

        $this->assertFalse($this->staff->fresh()->canViewInvoices());
    }
}
