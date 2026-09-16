<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * لینک‌های داشبورد باید فهرستِ تیکت‌ها را با فیلترِ وضعیت باز کنند (نه فقط صفحه)،
 * و فیلترِ چندانتخابیِ پرتال باید کار کند.
 */
class TicketFilterLinksTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا', 'can_view_history' => true]);
    }

    private function ticket(string $status, string $subject): Ticket
    {
        $t = Ticket::create(['customer_id' => $this->customer->id, 'subject' => $subject, 'description' => 'd']);
        $t->forceFill(['status' => $status])->save();

        return $t;
    }

    public function test_admin_status_filter_url_applies_filter(): void
    {
        $admin = User::create(['name' => 'مدیر', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $resolved = $this->ticket(Ticket::STATUS_RESOLVED, 'ALPHA-RESOLVED');
        $open     = $this->ticket(Ticket::STATUS_IN_PROGRESS, 'BETA-OPEN');

        // پارامترِ status[] در URL (همان که DashboardStats می‌سازد) باید در mount به
        // فیلترِ وضعیت تبدیل و اعمال شود.
        Livewire::withQueryParams(['status' => [Ticket::STATUS_RESOLVED]])
            ->actingAs($admin)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords([$resolved])
            ->assertCanNotSeeTableRecords([$open]);
    }

    public function test_portal_multi_status_filter(): void
    {
        $admin = User::create(['name' => 'مدیرِ مشتری', 'email' => 'ca@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);

        $this->ticket(Ticket::STATUS_WAITING_CUSTOMER, 'PWAIT');
        $this->ticket(Ticket::STATUS_IN_PROGRESS, 'PPROG');
        $this->ticket(Ticket::STATUS_RESOLVED, 'PDONE');

        // فیلترِ «باز» = منتظر مشتری + در حال بررسی
        $this->actingAs($admin)
            ->get(route('portal.tickets.index', ['status' => ['waiting_customer', 'in_progress']]))
            ->assertOk()
            ->assertSee('PWAIT')
            ->assertSee('PPROG')
            ->assertDontSee('PDONE');
    }

    public function test_portal_unrated_filter(): void
    {
        $admin = User::create(['name' => 'مدیرِ مشتری', 'email' => 'ca@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);

        $unrated = $this->ticket(Ticket::STATUS_RESOLVED, 'NEEDS-STARS');
        $rated   = $this->ticket(Ticket::STATUS_RESOLVED, 'ALREADY-STARRED');
        $rated->forceFill(['rating' => 5])->save();

        $this->actingAs($admin)
            ->get(route('portal.tickets.index', ['unrated' => 1]))
            ->assertOk()
            ->assertSee('NEEDS-STARS')
            ->assertDontSee('ALREADY-STARRED');
    }

    public function test_portal_unread_filter(): void
    {
        $admin   = User::create(['name' => 'مدیرِ مشتری', 'email' => 'ca@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id, 'is_active' => true]);
        $support = User::create(['name' => 'پشتیبان', 'email' => 'sp@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);

        $withUnread = $this->ticket(Ticket::STATUS_WAITING_CUSTOMER, 'HAS-UNREAD');
        $withUnread->messages()->create(['user_id' => $support->id, 'body' => 'پاسخِ پشتیبان', 'is_internal' => false]);

        $noUnread = $this->ticket(Ticket::STATUS_WAITING_CUSTOMER, 'NO-UNREAD');

        $this->actingAs($admin)
            ->get(route('portal.tickets.index', ['unread' => 1]))
            ->assertOk()
            ->assertSee('HAS-UNREAD')
            ->assertDontSee('NO-UNREAD');
    }
}
