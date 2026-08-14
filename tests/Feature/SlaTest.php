<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست هشدار SLA — تیکتی که مهلت پاسخ تعهدشده قراردادش گذشته و هنوز
 * کارشناسی جواب نداده «معطل» است.
 */
class SlaTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
    }

    private function contractWithSla(int $hours): Contract
    {
        $plan = ContractPlan::create(['name' => 'طلایی', 'response_hours' => $hours]);

        return Contract::create([
            'customer_id' => $this->customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(),
        ]);
    }

    private function ticket(?Contract $contract, \DateTimeInterface $createdAt): Ticket
    {
        $t = Ticket::create([
            'customer_id' => $this->customer->id, 'contract_id' => $contract?->id,
            'subject' => 'خرابی', 'description' => 'شرح',
        ]);
        $t->forceFill(['created_at' => $createdAt])->save();

        return $t->fresh();
    }

    public function test_ticket_without_contract_is_never_sla_breached(): void
    {
        $ticket = $this->ticket(null, now()->subDays(5));

        $this->assertFalse($ticket->isSlaBreached());
    }

    public function test_ticket_within_sla_window_is_not_breached(): void
    {
        $contract = $this->contractWithSla(4);
        $ticket = $this->ticket($contract, now()->subHours(2));

        $this->assertFalse($ticket->isSlaBreached());
    }

    public function test_ticket_past_sla_window_without_response_is_breached(): void
    {
        $contract = $this->contractWithSla(4);
        $ticket = $this->ticket($contract, now()->subHours(6));

        $this->assertTrue($ticket->isSlaBreached());
    }

    public function test_staff_reply_clears_sla_breach(): void
    {
        $contract = $this->contractWithSla(4);
        $ticket = $this->ticket($contract, now()->subHours(6));
        $this->assertTrue($ticket->isSlaBreached());

        $ticket->messages()->create([
            'user_id' => $this->staff->id, 'body' => 'پاسخ کارشناس', 'is_internal' => false,
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->first_response_at);
        $this->assertFalse($ticket->isSlaBreached());
    }

    public function test_internal_note_does_not_count_as_response(): void
    {
        $contract = $this->contractWithSla(4);
        $ticket = $this->ticket($contract, now()->subHours(6));

        $ticket->messages()->create([
            'user_id' => $this->staff->id, 'body' => 'یادداشت داخلی', 'is_internal' => true,
        ]);

        $ticket->refresh();
        $this->assertNull($ticket->first_response_at);
        $this->assertTrue($ticket->isSlaBreached());
    }

    public function test_customer_message_does_not_count_as_response(): void
    {
        $contract = $this->contractWithSla(4);
        $ticket = $this->ticket($contract, now()->subHours(6));

        $customerUser = User::create([
            'name' => 'مشتری', 'email' => 'c@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id,
        ]);
        $ticket->messages()->create([
            'user_id' => $customerUser->id, 'body' => 'پیگیری می‌کنم', 'is_internal' => false,
        ]);

        $ticket->refresh();
        $this->assertNull($ticket->first_response_at);
        $this->assertTrue($ticket->isSlaBreached());
    }

    public function test_closed_ticket_is_never_flagged_as_breached(): void
    {
        $contract = $this->contractWithSla(4);
        $ticket = $this->ticket($contract, now()->subHours(6));
        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);
        $ticket->update(['status' => Ticket::STATUS_CLOSED]);

        $this->assertFalse($ticket->fresh()->isSlaBreached());
    }
}
