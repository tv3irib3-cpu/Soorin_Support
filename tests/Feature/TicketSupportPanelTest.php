<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تغییراتِ پنلِ پشتیبان روی تیکت: پاسخ→منتظر مشتری، مدت کارکردِ اجباریِ انباشته،
 * روشِ انجامِ چندگزینه‌ایِ اجباری هنگامِ حل، حذفِ «جدید» از تغییر وضعیت، و محدودیتِ
 * تخصیص به مدیر.
 */
class TicketSupportPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create(['name' => 'مدیر', 'email' => 'admin@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $this->admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->staff = User::create(['name' => 'کارشناس', 'email' => 'staff@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $this->staff->assignRole(User::TYPE_SUPPORT_STAFF);

        $this->customer = Customer::create(['code' => 'C1', 'name' => 'آریا', 'entity_type' => 'company']);
    }

    private function ticket(array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'customer_id' => $this->customer->id,
            'subject'     => 's',
            'description' => 'd',
            'status'      => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $this->staff->id,
        ], $extra));
    }

    public function test_support_reply_auto_sets_waiting_customer(): void
    {
        $ticket = $this->ticket();

        // پاسخِ پشتیبان (پیامِ عمومی) → خودکار «منتظر پاسخ مشتری»
        TicketMessage::create(['ticket_id' => $ticket->id, 'user_id' => $this->staff->id, 'body' => 'پاسخ']);

        $this->assertSame(Ticket::STATUS_WAITING_CUSTOMER, $ticket->fresh()->status);
    }

    public function test_reply_accumulates_work_minutes_and_sets_waiting_customer(): void
    {
        // منطقِ پاسخ در TicketReplyService است (اکشنِ Filament همان را صدا می‌زند).
        $ticket = $this->ticket(['work_minutes' => 10]);

        app(\App\Services\TicketReplyService::class)
            ->reply($ticket, $this->admin, ['body' => 'سلام', 'work_minutes' => 15]);

        $ticket->refresh();
        $this->assertSame(25, (int) $ticket->work_minutes);   // 10 + 15
        $this->assertSame(Ticket::STATUS_WAITING_CUSTOMER, $ticket->status);
    }

    public function test_change_status_rejects_new_and_requires_method_on_resolve(): void
    {
        $this->actingAs($this->admin);

        // «جدید» پذیرفته نمی‌شود
        $t1 = $this->ticket();
        Livewire::test(ViewTicket::class, ['record' => $t1->getKey()])
            ->callAction('changeStatus', ['status' => Ticket::STATUS_NEW]);
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $t1->fresh()->status);

        // حل‌شدن بدونِ روشِ انجام → خطا
        $t2 = $this->ticket();
        Livewire::test(ViewTicket::class, ['record' => $t2->getKey()])
            ->callAction('changeStatus', ['status' => Ticket::STATUS_RESOLVED, 'resolution' => 'انجام شد'])
            ->assertHasActionErrors(['method']);

        // حل‌شدن با روشِ انجامِ چندگزینه‌ای → ثبت می‌شود و قفل می‌شود
        $t3 = $this->ticket();
        Livewire::test(ViewTicket::class, ['record' => $t3->getKey()])
            ->callAction('changeStatus', [
                'status'     => Ticket::STATUS_RESOLVED,
                'resolution' => 'انجام شد',
                'method'     => ['remote', 'chat'],
            ])
            ->assertHasNoActionErrors();

        $t3->refresh();
        $this->assertSame(Ticket::STATUS_RESOLVED, $t3->status);
        $this->assertSame(['remote', 'chat'], $t3->method);
        $this->assertTrue($t3->is_locked);
    }

    public function test_only_admin_can_reassign(): void
    {
        $ticket = $this->ticket();

        // کارشناس: اکشنِ تخصیص پنهان است
        $this->actingAs($this->staff);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('assign');

        // مدیر: اکشنِ تخصیص در دسترس است
        $this->actingAs($this->admin);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible('assign');
    }

    /** اگر مدیر مجوزِ «تخصیص کارشناس» را به کارشناسی بدهد، آن کارشناس می‌تواند تخصیص دهد. */
    public function test_staff_granted_assign_permission_can_reassign(): void
    {
        $ticket = $this->ticket();

        $defaults = \App\Enums\Permission::defaultsByRole()['support_staff'];
        $this->staff->forceFill(['permissions_customized' => true])->save();
        $this->staff->syncPermissions(array_merge($defaults, [\App\Enums\Permission::AssignTickets->value]));

        $this->actingAs($this->staff->fresh());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible('assign');
    }

    public function test_dashboard_unread_excludes_waiting_customer(): void
    {
        $other    = User::create(['name' => 'دیگر', 'email' => 'o@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $customerUser = User::create(['name' => 'مشتری', 'email' => 'cu@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id]);

        // تیکتی که پاسخِ پشتیبان خورده → خودکار «منتظر پاسخ مشتری»؛ پیامِ خوانده‌نشده
        // دارد ولی نباید در شمارِ پشتیبان بیاید.
        $waiting = $this->ticket();
        TicketMessage::create(['ticket_id' => $waiting->id, 'user_id' => $other->id, 'body' => 'پاسخ پشتیبان']);
        $this->assertSame(Ticket::STATUS_WAITING_CUSTOMER, $waiting->fresh()->status);

        // تیکتی که مشتری پاسخ داده → «در انتظار پاسخ پشتیبان»؛ باید شمرده شود.
        $active = $this->ticket();
        TicketMessage::create(['ticket_id' => $active->id, 'user_id' => $customerUser->id, 'body' => 'پاسخ مشتری']);
        $this->assertSame(Ticket::STATUS_WAITING_SUPPORT, $active->fresh()->status);

        // برای admin: waiting_customer نباید شمرده شود → فقط ۱ خوانده‌نشده (تیکتِ منتظر پشتیبان)
        $count = \App\Models\TicketRead::unreadCountFor($this->admin, [Ticket::STATUS_WAITING_CUSTOMER]);
        $this->assertSame(1, $count);
    }
}
