<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست چرخه وضعیت تیکت، شماره‌گذاری خودکار، قفل شدن، و ثبت تاریخچه.
 * این قواعد در App\Observers\TicketObserver پیاده شده‌اند.
 */
class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $supportAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);

        $this->supportAdmin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $this->supportAdmin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($this->supportAdmin);
    }

    private function newTicket(): Ticket
    {
        return Ticket::create([
            'customer_id' => $this->customer->id,
            'subject'     => 'خرابی هارد',
            'description' => 'شرح مشکل',
            'created_by'  => $this->supportAdmin->id,
        ]);
    }

    public function test_ticket_number_is_generated_automatically(): void
    {
        $ticket = $this->newTicket();

        $this->assertNotEmpty($ticket->number);
        $this->assertStringStartsWith('T-', $ticket->number);
    }

    public function test_sequential_tickets_get_increasing_numbers_same_day(): void
    {
        $first  = $this->newTicket();
        $second = $this->newTicket();

        $this->assertNotSame($first->number, $second->number);
        $this->assertSame(
            ((int) substr($first->number, -4)) + 1,
            (int) substr($second->number, -4),
        );
    }

    public function test_new_ticket_starts_with_new_status_and_is_not_locked(): void
    {
        $ticket = $this->newTicket();

        $this->assertSame(Ticket::STATUS_NEW, $ticket->status);
        $this->assertFalse($ticket->is_locked);
    }

    public function test_creating_a_ticket_logs_initial_status(): void
    {
        $ticket = $this->newTicket();

        $log = TicketStatusLog::where('ticket_id', $ticket->id)->sole();

        $this->assertNull($log->from_status);
        $this->assertSame(Ticket::STATUS_NEW, $log->to_status);
    }

    public function test_valid_transition_is_allowed_and_logged(): void
    {
        $ticket = $this->newTicket();
        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
        $this->assertCount(2, TicketStatusLog::where('ticket_id', $ticket->id)->get());
    }

    public function test_cannot_transition_directly_from_new_to_closed(): void
    {
        $ticket = $this->newTicket();

        // «جدید» فقط می‌تواند به «در حال بررسی» یا «لغوشده» برود
        $this->assertFalse($ticket->canTransitionTo(Ticket::STATUS_CLOSED));
        $this->assertContains(Ticket::STATUS_IN_PROGRESS, $ticket->availableTransitions());
        $this->assertNotContains(Ticket::STATUS_CLOSED, $ticket->availableTransitions());
    }

    public function test_closing_ticket_locks_it_automatically(): void
    {
        $ticket = $this->newTicket();
        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);
        $ticket->update(['status' => Ticket::STATUS_CLOSED]);

        $ticket->refresh();
        $this->assertTrue($ticket->is_locked);
        $this->assertNotNull($ticket->closed_at);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_locked_ticket_rejects_further_transitions(): void
    {
        $ticket = $this->newTicket();
        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);
        $ticket->update(['status' => Ticket::STATUS_CLOSED]);

        $this->assertFalse($ticket->fresh()->canTransitionTo(Ticket::STATUS_IN_PROGRESS));
    }

    public function test_internal_note_is_excluded_from_public_messages(): void
    {
        $ticket = $this->newTicket();

        $ticket->messages()->create(['user_id' => $this->supportAdmin->id, 'body' => 'پاسخ عمومی', 'is_internal' => false]);
        $ticket->messages()->create(['user_id' => $this->supportAdmin->id, 'body' => 'یادداشت داخلی', 'is_internal' => true]);

        $this->assertCount(2, $ticket->messages);
        $this->assertCount(1, $ticket->publicMessages);
        $this->assertSame('پاسخ عمومی', $ticket->publicMessages->first()->body);
    }

    public function test_ticket_page_loads_for_support_admin(): void
    {
        $ticket = $this->newTicket();

        $this->get("/admin/tickets/{$ticket->id}")->assertOk();
    }

    public function test_ticket_list_page_loads(): void
    {
        $this->newTicket();

        $this->get('/admin/tickets')->assertOk()->assertSee('خرابی هارد');
    }
}
