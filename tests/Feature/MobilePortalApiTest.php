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
 * API اپِ مشتری: ورودِ ماندگار، داشبورد، تیکت (فهرست/جزئیات/پاسخ/ایجاد/امتیاز/
 * اختصاص)، فاکتور، و جداییِ پلتفرم از اپِ پشتیبان.
 */
class MobilePortalApiTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا',
            'can_create_ticket' => true, 'can_view_history' => true, 'can_view_invoices' => true, 'service_status' => 'active']);
        $this->admin = User::create(['name' => 'مدیرِ مشتری', 'email' => 'ca', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);
        $this->staff = User::create(['name' => 'کارشناسِ مشتری', 'email' => 'cs', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $this->customer->id, 'is_active' => true]);
    }

    private function token(string $identifier): string
    {
        return $this->postJson('/api/portal/login', ['identifier' => $identifier, 'password' => 'secret123'])
            ->assertOk()->json('token');
    }

    private function ticket(array $attrs = []): Ticket
    {
        return Ticket::create(array_merge(['customer_id' => $this->customer->id, 'subject' => 's', 'description' => 'd'], $attrs));
    }

    public function test_customer_login_works_and_support_is_rejected(): void
    {
        $this->postJson('/api/portal/login', ['identifier' => 'ca', 'password' => 'secret123'])->assertOk();

        $sup = User::create(['name' => 'پشتیبان', 'email' => 'sup', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $this->postJson('/api/portal/login', ['identifier' => 'sup', 'password' => 'secret123'])->assertStatus(403);
    }

    public function test_platform_separation(): void
    {
        // توکنِ مشتری روی endpointِ پشتیبان → 401؛ روی endpointِ خودش → 200.
        $portalToken = $this->token('ca');
        $this->withToken($portalToken)->getJson('/api/dashboard')->assertStatus(401);
        $this->withToken($portalToken)->getJson('/api/portal/dashboard')->assertOk();

        // و برعکس: توکنِ پشتیبان روی endpointِ مشتری → 401.
        $sup = User::create(['name' => 'پشتیبان', 'email' => 'sup', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true]);
        $supToken = $this->postJson('/api/support/login', ['identifier' => 'sup', 'password' => 'secret123'])
            ->assertOk()->json('token');
        $this->withToken($supToken)->getJson('/api/portal/dashboard')->assertStatus(401);
        $this->withToken($supToken)->getJson('/api/dashboard')->assertOk();
    }

    public function test_dashboard_and_ticket_list(): void
    {
        $this->ticket(['created_by' => $this->admin->id])->forceFill(['status' => Ticket::STATUS_WAITING_CUSTOMER])->save();

        $token = $this->token('ca');
        $this->withToken($token)->getJson('/api/portal/dashboard')->assertOk()
            ->assertJsonStructure(['needs_attention', 'resolved', 'unpaid_invoices', 'unread']);

        $this->withToken($token)->getJson('/api/portal/tickets')->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_customer_can_create_reply_and_rate(): void
    {
        $token = $this->token('ca');

        // ساخت
        $res = $this->withToken($token)->postJson('/api/portal/tickets', ['subject' => 'مشکلِ من', 'description' => 'شرح'])
            ->assertStatus(201);
        $id = $res->json('id');
        $this->assertDatabaseHas('tickets', ['id' => $id, 'created_by' => $this->admin->id]);

        // پاسخ (بدونِ مدتِ کارکرد)
        $this->withToken($token)->postJson("/api/portal/tickets/$id/reply", ['body' => 'پیگیری'])->assertOk();
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $id, 'body' => 'پیگیری']);

        // امتیاز فقط به تیکتِ حل‌شده
        $t = Ticket::find($id);
        $t->forceFill(['status' => Ticket::STATUS_RESOLVED])->save();
        $this->withToken($token)->postJson("/api/portal/tickets/$id/rate", ['rating' => 5, 'rating_comment' => 'عالی'])->assertOk();
        $this->assertSame(5, (int) $t->fresh()->rating);
    }

    public function test_admin_can_assign_staff_but_staff_cannot(): void
    {
        $ticket = $this->ticket(['created_by' => $this->admin->id]);

        $this->withToken($this->token('cs'))
            ->postJson("/api/portal/tickets/{$ticket->id}/assign", ['customer_assigned_to' => $this->staff->id])
            ->assertStatus(403);

        $this->withToken($this->token('ca'))
            ->postJson("/api/portal/tickets/{$ticket->id}/assign", ['customer_assigned_to' => $this->staff->id])
            ->assertOk();
        $this->assertSame($this->staff->id, $ticket->fresh()->customer_assigned_to);
    }

    public function test_invoices_are_scoped(): void
    {
        $mine = $this->ticket(['created_by' => $this->staff->id]);
        Invoice::create(['number' => 'F-1', 'customer_id' => $this->customer->id, 'ticket_id' => $mine->id, 'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED]);
        $others = $this->ticket(['created_by' => $this->admin->id]);
        Invoice::create(['number' => 'F-2', 'customer_id' => $this->customer->id, 'ticket_id' => $others->id, 'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED]);

        // کارشناس فقط فاکتورِ تیکتِ خودش
        $numbers = collect($this->withToken($this->token('cs'))->getJson('/api/portal/invoices')->assertOk()->json('data'))->pluck('number');
        $this->assertContains('F-1', $numbers);
        $this->assertNotContains('F-2', $numbers);

        // مدیر همه
        $adminNumbers = collect($this->withToken($this->token('ca'))->getJson('/api/portal/invoices')->json('data'))->pluck('number');
        $this->assertContains('F-1', $adminNumbers);
        $this->assertContains('F-2', $adminNumbers);
    }
}
