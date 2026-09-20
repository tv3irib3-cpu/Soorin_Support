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
 * API اپِ موبایل (پشتیبان): ورودِ ماندگار با توکن، داشبورد، و چرخهٔ کاملِ تیکت.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $this->admin = User::create(['name' => 'مدیر', 'email' => 'admin', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true]);
        $this->admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->staff = User::create(['name' => 'کارشناس', 'email' => 'staff', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF, 'is_active' => true]);
        $this->staff->assignRole(User::TYPE_SUPPORT_STAFF);
    }

    private function tokenFor(string $identifier): string
    {
        $res = $this->postJson('/api/support/login', [
            'identifier' => $identifier, 'password' => 'secret123', 'device_name' => 'test',
        ])->assertOk();

        return $res->json('token');
    }

    private function ticketAssignedTo(User $user): Ticket
    {
        $t = Ticket::create(['customer_id' => $this->customer->id, 'subject' => 's', 'description' => 'd']);
        $t->forceFill(['assigned_to' => $user->id, 'status' => Ticket::STATUS_WAITING_SUPPORT])->save();

        return $t;
    }

    public function test_login_returns_token_and_wrong_password_fails(): void
    {
        $this->postJson('/api/support/login', ['identifier' => 'admin', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'user_type']]);

        $this->postJson('/api/support/login', ['identifier' => 'admin', 'password' => 'WRONG'])
            ->assertStatus(422);
    }

    public function test_customer_cannot_login_to_support_app(): void
    {
        $cust = User::create(['name' => 'مشتری', 'email' => 'cust', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);

        $this->postJson('/api/support/login', ['identifier' => 'cust', 'password' => 'secret123'])
            ->assertStatus(403);
    }

    public function test_token_is_persistent_and_logout_revokes_it(): void
    {
        // بدونِ توکن: ۴۰۱ (پیش از تنظیمِ هر هدرِ Authorization)
        $this->getJson('/api/me')->assertStatus(401);

        $token = $this->tokenFor('admin');

        // با توکن: me کار می‌کند (ماندگاری — همان توکن دفعاتِ بعد هم کار می‌کند)
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.name', 'مدیر');
        $this->withToken($token)->getJson('/api/me')->assertOk();

        // خروج → توکن باطل می‌شود و دیگر کار نمی‌کند
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_dashboard_returns_counts(): void
    {
        $this->ticketAssignedTo($this->admin);   // waiting_support → needs

        $this->withToken($this->tokenFor('admin'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['needs_attention', 'open', 'resolved', 'unpaid_invoices', 'unread'])
            ->assertJsonPath('needs_attention', 1);
    }

    public function test_staff_sees_only_own_tickets_in_list(): void
    {
        $mine   = $this->ticketAssignedTo($this->staff);
        $others = $this->ticketAssignedTo($this->admin);

        $ids = collect($this->withToken($this->tokenFor('staff'))->getJson('/api/tickets')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($others->id, $ids);
    }

    public function test_show_reply_and_resolve_flow(): void
    {
        $ticket = $this->ticketAssignedTo($this->staff);
        $token  = $this->tokenFor('staff');

        // نمایش
        $this->withToken($token)->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonStructure(['ticket', 'messages', 'abilities']);

        // پاسخ
        $this->withToken($token)->postJson("/api/tickets/{$ticket->id}/reply", [
            'body' => 'پاسخِ تست', 'work_minutes' => 15,
        ])->assertOk();

        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'پاسخِ تست']);
        $this->assertSame(15, (int) $ticket->fresh()->work_minutes);

        // حل شد
        $this->withToken($token)->postJson("/api/tickets/{$ticket->id}/resolve", [
            'method' => ['remote'], 'resolution' => 'انجام شد',
        ])->assertOk();

        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    public function test_change_status_is_admin_only(): void
    {
        $ticket = $this->ticketAssignedTo($this->staff);

        // کارشناس: ۴۰۳
        $this->withToken($this->tokenFor('staff'))
            ->postJson("/api/tickets/{$ticket->id}/status", ['status' => Ticket::STATUS_WAITING_CUSTOMER])
            ->assertStatus(403);

        // مدیر: موفق
        $this->withToken($this->tokenFor('admin'))
            ->postJson("/api/tickets/{$ticket->id}/status", ['status' => Ticket::STATUS_WAITING_CUSTOMER])
            ->assertOk();

        $this->assertSame(Ticket::STATUS_WAITING_CUSTOMER, $ticket->fresh()->status);
    }

    public function test_app_version_endpoint_is_public(): void
    {
        $this->getJson('/api/app-version?platform=support')
            ->assertOk()
            ->assertJsonStructure(['platform', 'version', 'min_supported', 'apk_url'])
            ->assertJsonPath('platform', 'support');
    }

    public function test_form_data_returns_customers(): void
    {
        $this->withToken($this->tokenFor('admin'))->getJson('/api/tickets/form-data')
            ->assertOk()
            ->assertJsonStructure(['customers', 'priorities'])
            ->assertJsonFragment(['name' => 'آریا']);
    }

    public function test_support_can_create_ticket(): void
    {
        $res = $this->withToken($this->tokenFor('admin'))->postJson('/api/tickets', [
            'customer_id' => $this->customer->id, 'subject' => 'تیکتِ اپ', 'description' => 'd', 'priority' => 'high',
        ])->assertStatus(201);

        $this->assertDatabaseHas('tickets', ['id' => $res->json('id'), 'subject' => 'تیکتِ اپ', 'created_by' => $this->admin->id]);
    }

    public function test_assign_requires_permission(): void
    {
        $ticket = $this->ticketAssignedTo($this->admin);

        // کارشناس پیش‌فرض مجوزِ «تخصیص کارشناس» ندارد → ۴۰۳
        $this->withToken($this->tokenFor('staff'))
            ->postJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $this->staff->id])
            ->assertStatus(403);

        // مدیر دارد → موفق و assigned_to ثبت می‌شود
        $this->withToken($this->tokenFor('admin'))
            ->postJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $this->staff->id])
            ->assertOk();
        $this->assertSame($this->staff->id, $ticket->fresh()->assigned_to);

        // بازتخصیص به «بدونِ کارشناس»
        $this->withToken($this->tokenFor('admin'))
            ->postJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => null])
            ->assertOk();
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_staff_list_is_permission_gated(): void
    {
        $this->withToken($this->tokenFor('staff'))->getJson('/api/tickets/staff')->assertStatus(403);

        $this->withToken($this->tokenFor('admin'))->getJson('/api/tickets/staff')
            ->assertOk()->assertJsonStructure(['staff' => [['id', 'name']]])
            ->assertJsonFragment(['name' => 'کارشناس']);
    }

    public function test_form_data_includes_categories(): void
    {
        $this->withToken($this->tokenFor('admin'))->getJson('/api/tickets/form-data')
            ->assertOk()->assertJsonStructure(['customers', 'categories', 'projects', 'priorities']);
    }

    public function test_invoice_list_is_visible_to_support_with_search(): void
    {
        $ticket = $this->ticketAssignedTo($this->admin);
        Invoice::create(['number' => 'INV-9', 'customer_id' => $this->customer->id, 'ticket_id' => $ticket->id,
            'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED]);

        $token = $this->tokenFor('admin');

        $numbers = collect($this->withToken($token)->getJson('/api/invoices')->assertOk()->json('data'))->pluck('number');
        $this->assertContains('INV-9', $numbers);

        // جستجو بر پایهٔ نامِ مشتری
        $byName = collect($this->withToken($token)->getJson('/api/invoices?search=آریا')->json('data'))->pluck('number');
        $this->assertContains('INV-9', $byName);

        // جستجویِ بی‌نتیجه
        $none = $this->withToken($token)->getJson('/api/invoices?search=ناموجود')->json('data');
        $this->assertCount(0, $none);
    }

    public function test_ticket_search_filters_by_subject_and_customer(): void
    {
        $bahar = Customer::create(['code' => 'BAHR', 'name' => 'بهار']);
        $t1 = Ticket::create(['customer_id' => $this->customer->id, 'subject' => 'پرینتر خراب', 'description' => 'd']);
        $t2 = Ticket::create(['customer_id' => $bahar->id, 'subject' => 'شبکه قطع', 'description' => 'd']);
        $token = $this->tokenFor('admin');

        $bySubject = collect($this->withToken($token)->getJson('/api/tickets?search=پرینتر')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($t1->id, $bySubject);
        $this->assertNotContains($t2->id, $bySubject);

        $byCustomer = collect($this->withToken($token)->getJson('/api/tickets?search=بهار')->json('data'))->pluck('id');
        $this->assertContains($t2->id, $byCustomer);
        $this->assertNotContains($t1->id, $byCustomer);
    }

    public function test_customers_list_with_counts_and_detail(): void
    {
        $ticket = Ticket::create(['customer_id' => $this->customer->id, 'subject' => 's', 'description' => 'd']);
        $ticket->forceFill(['status' => Ticket::STATUS_WAITING_SUPPORT])->save();
        Invoice::create(['number' => 'INV-C', 'customer_id' => $this->customer->id, 'ticket_id' => $ticket->id,
            'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED, 'payable_amount' => 50000]);
        $token = $this->tokenFor('admin');

        $row = collect($this->withToken($token)->getJson('/api/customers?search=آریا')->assertOk()->json('data'))
            ->firstWhere('id', $this->customer->id);
        $this->assertSame(1, $row['open_tickets']);
        $this->assertSame(1, $row['unpaid_invoices']);

        $this->withToken($token)->getJson("/api/customers/{$this->customer->id}")->assertOk()
            ->assertJsonPath('customer.name', 'آریا')
            ->assertJsonStructure(['customer' => ['phone', 'mobile', 'service_status'], 'projects', 'tickets', 'invoices']);
    }

    public function test_invoice_show_and_pay(): void
    {
        $ticket = $this->ticketAssignedTo($this->admin);
        $invoice = Invoice::create(['number' => 'INV-P', 'customer_id' => $this->customer->id, 'ticket_id' => $ticket->id,
            'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED, 'payable_amount' => 100000]);

        // کارشناس مجوزِ ثبت پرداخت را دارد؟ پیش‌فرضِ support_staff دارد (ManagePayments).
        // اینجا با مدیر تست می‌کنیم.
        $token = $this->tokenFor('admin');

        $this->withToken($token)->getJson("/api/invoices/{$invoice->id}")->assertOk()
            ->assertJsonPath('invoice.remaining', 100000)
            ->assertJsonPath('invoice.can_pay', true)
            ->assertJsonStructure(['invoice', 'payment_methods', 'payments']);

        // پرداختِ کامل
        $this->withToken($token)->postJson("/api/invoices/{$invoice->id}/pay", [
            'amount' => 100000, 'method' => 'transfer',
        ])->assertOk();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'amount' => 100000, 'registered_by' => $this->admin->id]);
    }
}
