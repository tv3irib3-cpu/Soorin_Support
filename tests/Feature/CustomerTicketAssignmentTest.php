<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختصاصِ تیکت به کارشناسِ خودِ مشتری از پرتال.
 *
 * مدیرِ مشتری تیکتی را که پشتیبان فرستاده به کارشناسِ خودش می‌سپارد؛ آن کارشناس
 * حتی با دامنهٔ تاریخچهٔ «هیچ» باید تیکت را ببیند و بتواند پاسخ دهد.
 */
class CustomerTicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $admin;
    private User $staff;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // مشتری با اجازهٔ دیدنِ تاریخچه، ولی کارشناس دامنه‌اش «هیچ» است.
        $this->customer = Customer::create([
            'code' => 'ARIA', 'name' => 'آریا', 'entity_type' => 'company',
            'can_view_history' => true, 'can_create_ticket' => true, 'service_status' => 'active',
        ]);

        $this->admin = User::create(['name' => 'مدیرِ مشتری', 'email' => 'ca@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);
        $this->staff = User::create(['name' => 'کارشناسِ مشتری', 'email' => 'cs@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $this->customer->id, 'is_active' => true, 'history_scope' => 'none']);

        // تیکتی که پشتیبان برای این مشتری ساخته (کارشناسِ مشتری خودش نساخته).
        $this->ticket = Ticket::create([
            'customer_id' => $this->customer->id, 'subject' => 'از طرفِ پشتیبان', 'description' => 'd',
        ]);
    }

    public function test_staff_cannot_see_unassigned_ticket(): void
    {
        // کارشناس با دامنهٔ «هیچ» و بدونِ اختصاص، تیکتی که خودش نساخته را نمی‌بیند.
        $this->assertFalse(Ticket::visibleTo($this->staff)->whereKey($this->ticket->id)->exists());
    }

    public function test_customer_admin_can_assign_and_staff_then_sees_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('portal.tickets.assign', $this->ticket), ['customer_assigned_to' => $this->staff->id])
            ->assertRedirect();

        $this->assertSame($this->staff->id, $this->ticket->fresh()->customer_assigned_to);

        // حالا کارشناس تیکت را می‌بیند (اختصاص دسترسی داد) و می‌تواند پاسخ دهد.
        $this->assertTrue(Ticket::visibleTo($this->staff)->whereKey($this->ticket->id)->exists());

        $this->actingAs($this->staff)
            ->post(route('portal.tickets.reply', $this->ticket), ['body' => 'سلام پشتیبان'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $this->ticket->id, 'user_id' => $this->staff->id, 'body' => 'سلام پشتیبان']);
    }

    public function test_customer_staff_cannot_assign(): void
    {
        $this->actingAs($this->staff)
            ->post(route('portal.tickets.assign', $this->ticket), ['customer_assigned_to' => $this->staff->id])
            ->assertForbidden();
    }

    public function test_cannot_assign_to_another_customers_user(): void
    {
        $other = Customer::create(['code' => 'BETA', 'name' => 'بتا', 'entity_type' => 'company']);
        $otherStaff = User::create(['name' => 'غریبه', 'email' => 'x@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $other->id, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('portal.tickets.assign', $this->ticket), ['customer_assigned_to' => $otherStaff->id])
            ->assertStatus(422);

        $this->assertNull($this->ticket->fresh()->customer_assigned_to);
    }
}
