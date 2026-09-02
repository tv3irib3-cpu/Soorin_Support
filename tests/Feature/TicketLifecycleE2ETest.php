<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * چرخهٔ کاملِ تیکت، دقیقاً همان سناریوی خواسته‌شده:
 * کارشناسِ خریدار تیکت می‌سازد → چه کسانی می‌بینند؟ → کارشناسِ پشتیبان پاسخ
 * می‌دهد → مدیرها می‌بینند → تیکت بسته و قفل می‌شود. به‌علاوه جداسازیِ داده بین
 * مشتری‌ها (امنیتِ حیاتی برای اینترنت).
 */
class TicketLifecycleE2ETest extends TestCase
{
    use RefreshDatabase;

    private Customer $aria;
    private Customer $beta;
    private User $creator;       // کارشناسِ خریدارِ آریا (سازندهٔ تیکت)
    private User $custAdmin;     // مدیرِ خریدارِ آریا
    private User $otherCustomer; // مدیرِ خریدارِ بتا (مشتریِ دیگر)
    private User $supportStaff;  // کارشناسِ پشتیبان
    private User $supportAdmin;  // ادمینِ پشتیبان

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->aria = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->beta = Customer::create(['code' => 'BETA', 'name' => 'شرکت بتا']);

        // سازنده: کارشناسِ خریدار با دسترسیِ «سوابقِ خودش» تا تیکتِ خودش را ببیند
        $this->creator = $this->makeUser('creator@aria.test', User::TYPE_CUSTOMER_STAFF, $this->aria, ['history_scope' => 'own']);
        // مدیرِ خریدار: پیش‌فرض سوابقِ کلِ سازمانش را می‌بیند
        $this->custAdmin = $this->makeUser('admin@aria.test', User::TYPE_CUSTOMER_ADMIN, $this->aria);
        // مدیرِ خریدارِ مشتریِ دیگر
        $this->otherCustomer = $this->makeUser('admin@beta.test', User::TYPE_CUSTOMER_ADMIN, $this->beta);

        $this->supportStaff = $this->makeUser('staff@dpst.ir', User::TYPE_SUPPORT_STAFF);
        $this->supportAdmin = $this->makeUser('admin@dpst.ir', User::TYPE_SUPPORT_ADMIN);
    }

    private function makeUser(string $email, string $type, ?Customer $customer = null, array $extra = []): User
    {
        $user = User::create(array_merge([
            'name' => $email, 'email' => $email, 'password' => 'secret123',
            'user_type' => $type, 'customer_id' => $customer?->id,
        ], $extra));
        $user->assignRole($type);

        return $user;
    }

    /** ۱) کارشناسِ خریدار از پرتال تیکت می‌سازد. */
    public function test_customer_staff_creates_a_ticket_from_the_portal(): void
    {
        $this->actingAs($this->creator)
            ->post(route('portal.tickets.store'), [
                'subject'     => 'نمایشگر روشن نمی‌شود',
                'description'  => 'مانیتور ۲۲ اینچ هیچ تصویری نمی‌دهد.',
                'priority'    => 'high',
            ])
            ->assertRedirect();

        $ticket = Ticket::firstWhere('subject', 'نمایشگر روشن نمی‌شود');

        $this->assertNotNull($ticket);
        $this->assertSame($this->aria->id, $ticket->customer_id);
        $this->assertSame($this->creator->id, $ticket->created_by);
        $this->assertNotEmpty($ticket->number);            // شمارهٔ خودکار
        $this->assertSame(Ticket::STATUS_NEW, $ticket->status);
    }

    /** ۲) چه کسانی تیکت را می‌بینند؟ همهٔ آریا + پشتیبان؛ نه مشتریِ دیگر. */
    public function test_visibility_covers_the_company_and_support_but_never_another_customer(): void
    {
        $ticket = $this->makeTicket();

        // می‌بینند:
        $this->assertTrue($this->sees($this->creator, $ticket), 'سازنده باید تیکتِ خودش را ببیند');
        $this->assertTrue($this->sees($this->custAdmin, $ticket), 'مدیرِ خریدار باید ببیند');
        $this->assertTrue($this->sees($this->supportStaff, $ticket), 'کارشناسِ پشتیبان باید ببیند');
        $this->assertTrue($this->sees($this->supportAdmin, $ticket), 'ادمینِ پشتیبان باید ببیند');

        // نمی‌بیند (جداسازیِ داده):
        $this->assertFalse($this->sees($this->otherCustomer, $ticket), 'مشتریِ دیگر هرگز نباید ببیند');

        // پرتال: سازنده ۲۰۰، مشتریِ دیگر ۴۰۴ (حتی وجودِ تیکت فاش نشود)
        $this->actingAs($this->creator)->get(route('portal.tickets.show', $ticket))->assertOk();
        $this->actingAs($this->otherCustomer)->get(route('portal.tickets.show', $ticket))->assertNotFound();
    }

    /** ۳) پاسخِ کارشناسِ پشتیبان را مشتری می‌بیند؛ یادداشتِ داخلی را نه. */
    public function test_support_reply_is_visible_to_customer_but_internal_note_is_hidden(): void
    {
        $ticket = $this->makeTicket();

        // کارشناسِ پشتیبان پاسخِ عمومی می‌دهد و وضعیت را جلو می‌برد
        $ticket->messages()->create([
            'user_id' => $this->supportStaff->id,
            'body'    => 'لطفاً کابل برق را بررسی کنید.',
            'is_internal' => false,
        ]);
        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);

        // یادداشتِ داخلیِ تیم (نباید به مشتری برسد)
        $ticket->messages()->create([
            'user_id' => $this->supportStaff->id,
            'body'    => 'یادداشتِ داخلی: احتمالاً منبعِ تغذیه سوخته.',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($this->creator)->get(route('portal.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('لطفاً کابل برق را بررسی کنید.', false);      // پاسخِ عمومی دیده می‌شود
        $response->assertDontSee('یادداشتِ داخلی', false);                  // یادداشتِ داخلی پنهان است
    }

    /** ۴) مدیرها (پشتیبان) تیکت را در پنل می‌بینند. */
    public function test_managers_see_the_ticket_in_the_admin_panel(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->supportAdmin)->get('/admin/tickets')->assertOk()->assertSee($ticket->number, false);
        $this->actingAs($this->supportAdmin)->get('/admin/tickets/' . $ticket->id)->assertOk();
    }

    /** ۵) بستنِ تیکت آن را قفل می‌کند و جلوی پاسخِ بعدی را می‌گیرد. */
    public function test_closing_locks_the_ticket_and_blocks_further_replies(): void
    {
        $ticket = $this->makeTicket();

        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);
        $ticket->update(['status' => Ticket::STATUS_CLOSED]);
        $ticket->refresh();

        $this->assertTrue($ticket->is_locked, 'تیکتِ بسته باید قفل شود');
        $this->assertNotNull($ticket->closed_at);
        $this->assertSame([], $ticket->availableTransitions());

        // مشتری دیگر نمی‌تواند روی تیکتِ قفل‌شده پاسخ بگذارد
        $this->actingAs($this->creator)
            ->post(route('portal.tickets.reply', $ticket), ['body' => 'یک پیام دیگر'])
            ->assertForbidden();
    }

    private function makeTicket(): Ticket
    {
        return Ticket::create([
            'customer_id' => $this->aria->id,
            'subject'     => 'نمایشگر روشن نمی‌شود',
            'description' => 'مانیتور هیچ تصویری نمی‌دهد.',
            'created_by'  => $this->creator->id,
            'status'      => Ticket::STATUS_NEW,
        ]);
    }

    private function sees(User $user, Ticket $ticket): bool
    {
        return Ticket::visibleTo($user)->whereKey($ticket->id)->exists();
    }
}
