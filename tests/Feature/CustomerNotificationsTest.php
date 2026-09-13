<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceRead;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketRead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(): Customer
    {
        return Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'entity_type' => 'company']);
    }

    private function customerUser(Customer $c): User
    {
        return User::create([
            'name' => 'کاربر', 'email' => 'ali', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $c->id, 'is_active' => true,
        ]);
    }

    private function support(): User
    {
        $u = User::create(['name' => 'پشتیبان', 'email' => 'sup@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    // ---------------------------------------------------- تیکت: خوانده‌نشده به تفکیک

    public function test_unread_counts_are_reported_per_ticket(): void
    {
        $c = $this->customer();
        $user = $this->customerUser($c);
        $support = $this->support();

        $t1 = Ticket::create(['customer_id' => $c->id, 'subject' => 'الف', 'description' => '...', 'created_by' => $user->id]);
        $t2 = Ticket::create(['customer_id' => $c->id, 'subject' => 'ب', 'description' => '...', 'created_by' => $user->id]);

        // پشتیبان روی تیکتِ اول دو پاسخ می‌دهد، روی دومی هیچ
        TicketMessage::create(['ticket_id' => $t1->id, 'user_id' => $support->id, 'body' => 'پاسخ ۱']);
        TicketMessage::create(['ticket_id' => $t1->id, 'user_id' => $support->id, 'body' => 'پاسخ ۲']);

        $counts = TicketRead::unreadCountsFor($user, [$t1->id, $t2->id]);

        $this->assertSame(2, $counts[$t1->id] ?? 0);
        $this->assertArrayNotHasKey($t2->id, $counts);   // بدونِ خوانده‌نشده در آرایه نیست
    }

    public function test_portal_ticket_list_shows_per_ticket_unread_pill(): void
    {
        $c = $this->customer();
        $user = $this->customerUser($c);
        $support = $this->support();

        $t = Ticket::create(['customer_id' => $c->id, 'subject' => 'مشکل', 'description' => '...', 'created_by' => $user->id]);
        TicketMessage::create(['ticket_id' => $t->id, 'user_id' => $support->id, 'body' => 'پاسخ']);

        $this->actingAs($user)->get(route('portal.tickets.index'))
            ->assertOk()
            ->assertSee(__('portal.unread_new'));
    }

    // ---------------------------------------------------- فاکتور: نشانِ جدید

    private function issuedInvoice(Customer $c): Invoice
    {
        $inv = Invoice::create(['number' => 'F-' . fake()->unique()->numberBetween(1, 9999), 'customer_id' => $c->id, 'issue_date' => now()]);
        $inv->update(['status' => Invoice::STATUS_ISSUED]);

        return $inv;
    }

    public function test_issued_at_is_set_when_invoice_becomes_issued(): void
    {
        $c = $this->customer();

        $draft = Invoice::create(['number' => 'F-1', 'customer_id' => $c->id, 'issue_date' => now()]);
        $this->assertNull($draft->issued_at);   // پیش‌نویس هنوز issued_at ندارد

        $draft->update(['status' => Invoice::STATUS_ISSUED]);
        $this->assertNotNull($draft->fresh()->issued_at);
    }

    public function test_new_invoice_counts_only_after_last_seen(): void
    {
        $c = $this->customer();
        $user = $this->customerUser($c);

        // کاربر «به‌روز» است (همین حالا دیده)
        InvoiceRead::markSeen($user);

        $this->assertSame(0, InvoiceRead::newCountFor($user));

        // یک ثانیه بعد فاکتور صادر می‌شود → باید «جدید» شمرده شود
        $this->travel(5)->seconds();
        $this->issuedInvoice($c);

        $this->assertSame(1, InvoiceRead::newCountFor($user));

        // پس از دیدنِ دوباره، صفر می‌شود
        $this->travel(5)->seconds();
        InvoiceRead::markSeen($user);
        $this->assertSame(0, InvoiceRead::newCountFor($user));
    }

    public function test_draft_and_cancelled_invoices_are_not_counted_new(): void
    {
        $c = $this->customer();
        $user = $this->customerUser($c);
        InvoiceRead::markSeen($user);
        $this->travel(5)->seconds();

        Invoice::create(['number' => 'F-D', 'customer_id' => $c->id, 'issue_date' => now()]);   // پیش‌نویس
        $cancelled = $this->issuedInvoice($c);
        $cancelled->update(['status' => Invoice::STATUS_CANCELLED]);

        $this->assertSame(0, InvoiceRead::newCountFor($user), 'پیش‌نویس و لغوشده نباید جدید شمرده شوند');
    }

    public function test_portal_invoice_list_marks_new_and_then_clears(): void
    {
        $c = $this->customer();
        $user = $this->customerUser($c);
        InvoiceRead::markSeen($user);
        $this->travel(5)->seconds();
        $this->issuedInvoice($c);

        // نخستین بازدید: نشانِ «جدید» دیده می‌شود و بازدید ثبت می‌گردد
        $this->actingAs($user)->get(route('portal.invoices.index'))
            ->assertOk()
            ->assertSee(__('portal.new_badge'));

        // بازدیدِ بعدی: دیگر جدیدی نیست
        $this->assertSame(0, InvoiceRead::newCountFor($user->fresh()));
    }
}
