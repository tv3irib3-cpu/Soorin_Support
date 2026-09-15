<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ترتیبِ جدولِ تیکت‌ها: پیش‌فرض تازه‌ترین بالا، و سورت‌های اولویت و آخرین پیام.
 * تیکت‌ها بدونِ سازنده ساخته می‌شوند تا «ورودی» شمرده و در ListTickets دیده شوند.
 */
class TicketsTableSortTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->customer = Customer::create(['code' => 'C1', 'name' => 'آریا', 'entity_type' => 'company']);
    }

    private function ticket(string $priority, string $createdAt): Ticket
    {
        $t = Ticket::create([
            'customer_id' => $this->customer->id,
            'subject'     => "s-$priority-$createdAt",
            'description' => 'd',
            'priority'    => $priority,
        ]);
        // created_by را خالی می‌گذاریم (ورودی)؛ created_at را دستی می‌نشانیم.
        $t->forceFill(['created_by' => null, 'created_at' => $createdAt])->save();

        return $t;
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    public function test_default_sort_is_newest_first(): void
    {
        $old = $this->ticket('critical', '2026-01-01 10:00:00'); // قدیمی ولی بحرانی
        $mid = $this->ticket('low', '2026-02-01 10:00:00');
        $new = $this->ticket('low', '2026-03-01 10:00:00');      // تازه‌ترین باید بالا باشد

        $this->actingAs($this->admin());

        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$new, $mid, $old], inOrder: true);
    }

    public function test_can_sort_by_priority_desc_critical_first(): void
    {
        $low      = $this->ticket('low', '2026-03-01 10:00:00');
        $critical = $this->ticket('critical', '2026-01-01 10:00:00');
        $normal   = $this->ticket('normal', '2026-02-01 10:00:00');

        $this->actingAs($this->admin());

        Livewire::test(ListTickets::class)
            ->sortTable('priority', 'desc')
            ->assertCanSeeTableRecords([$critical, $normal, $low], inOrder: true);
    }

    public function test_can_sort_by_last_message_date(): void
    {
        $a = $this->ticket('normal', '2026-01-01 10:00:00');
        $b = $this->ticket('normal', '2026-01-02 10:00:00');

        // پیامِ تازه‌ترِ a آن را در سورتِ «آخرین پیام» بالاتر از b می‌برد.
        TicketMessage::create(['ticket_id' => $b->id, 'body' => 'x'])
            ->forceFill(['created_at' => '2026-05-01 10:00:00'])->save();
        TicketMessage::create(['ticket_id' => $a->id, 'body' => 'y'])
            ->forceFill(['created_at' => '2026-06-01 10:00:00'])->save();

        $this->actingAs($this->admin());

        Livewire::test(ListTickets::class)
            ->sortTable('last_message_at', 'desc')
            ->assertCanSeeTableRecords([$a, $b], inOrder: true);
    }
}
