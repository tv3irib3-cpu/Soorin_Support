<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerProject;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست پرتال مشتری — تمرکز اصلی روی کنترل دسترسی: هیچ کاربر مشتری نباید
 * داده مشتری دیگر را ببیند، و کاربر داخلی شرکت نباید وارد پرتال شود.
 */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Customer $aria;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->aria  = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->other = Customer::create(['code' => 'OTHER', 'name' => 'شرکت دیگر']);
    }

    private function customerAdmin(Customer $customer, array $extra = []): User
    {
        $user = User::create(array_merge([
            'name' => 'مدیر مشتری', 'email' => fake()->unique()->safeEmail(),
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN,
            'customer_id' => $customer->id,
        ], $extra));
        $user->assignRole(User::TYPE_CUSTOMER_ADMIN);

        return $user;
    }

    public function test_guest_is_redirected_to_portal_login(): void
    {
        $this->get('/portal')->assertRedirect(route('portal.login'));
    }

    public function test_support_user_cannot_login_through_portal_form(): void
    {
        $support = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
        $support->assignRole(User::TYPE_SUPPORT_STAFF);

        $response = $this->post(route('portal.login'), [
            'identifier' => 'staff@dpst.ir', 'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_support_user_visiting_portal_is_redirected_to_admin(): void
    {
        $support = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);

        $this->actingAs($support)->get('/portal')->assertRedirect('/admin');
    }

    public function test_customer_admin_can_login_and_see_dashboard(): void
    {
        $user = $this->customerAdmin($this->aria, ['email' => 'owner@aria.test']);

        $response = $this->post(route('portal.login'), [
            'identifier' => 'owner@aria.test', 'password' => 'secret123',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_cannot_view_another_customers_ticket(): void
    {
        $ticket = Ticket::create([
            'customer_id' => $this->other->id, 'subject' => 'خرابی', 'description' => 'شرح',
        ]);
        $user = $this->customerAdmin($this->aria);

        $this->actingAs($user)
            ->get(route('portal.tickets.show', $ticket))
            ->assertNotFound();
    }

    public function test_customer_admin_can_view_own_ticket(): void
    {
        $ticket = Ticket::create([
            'customer_id' => $this->aria->id, 'subject' => 'خرابی هارد', 'description' => 'شرح',
        ]);
        $user = $this->customerAdmin($this->aria);

        $this->actingAs($user)
            ->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('خرابی هارد');
    }

    public function test_internal_note_is_never_shown_in_portal(): void
    {
        $ticket = Ticket::create([
            'customer_id' => $this->aria->id, 'subject' => 'خرابی', 'description' => 'شرح',
        ]);
        $ticket->messages()->create(['body' => 'یادداشت محرمانه داخلی', 'is_internal' => true]);
        $ticket->messages()->create(['body' => 'پاسخ عمومی به مشتری', 'is_internal' => false]);

        $user = $this->customerAdmin($this->aria);

        $response = $this->actingAs($user)->get(route('portal.tickets.show', $ticket));

        $response->assertSee('پاسخ عمومی به مشتری');
        $response->assertDontSee('یادداشت محرمانه داخلی');
    }

    public function test_suspended_customer_cannot_submit_ticket(): void
    {
        $this->aria->update([
            'service_status' => Customer::STATUS_SUSPENDED,
            'suspension_message' => 'به دلیل تسویه‌نشدن بدهی',
        ]);
        $user = $this->customerAdmin($this->aria);

        $response = $this->actingAs($user)->post(route('portal.tickets.store'), [
            'subject' => 'تست', 'description' => 'شرح',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_customer_staff_cannot_use_a_project_not_assigned_to_them(): void
    {
        $foreignProject = $this->aria->projects()->create(['code' => 'X', 'name' => 'پروژه دیگر']);
        $ownProject     = $this->aria->projects()->create(['code' => 'Y', 'name' => 'پروژه خودم']);

        $staff = User::create([
            'name' => 'کارشناس مشتری', 'email' => 'staff@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $this->aria->id,
        ]);
        $staff->assignRole(User::TYPE_CUSTOMER_STAFF);
        $staff->projects()->attach($ownProject);

        $response = $this->actingAs($staff)->post(route('portal.tickets.store'), [
            'subject' => 'تست', 'description' => 'شرح',
            'customer_project_id' => $foreignProject->id,
        ]);

        $response->assertForbidden();
    }

    public function test_ticket_store_creates_ticket_with_correct_owner(): void
    {
        $user = $this->customerAdmin($this->aria);

        $response = $this->actingAs($user)->post(route('portal.tickets.store'), [
            'subject' => 'موضوع تست', 'description' => 'شرح تست',
        ]);

        $ticket = Ticket::sole();
        $response->assertRedirect(route('portal.tickets.show', $ticket));
        $this->assertSame($this->aria->id, $ticket->customer_id);
        $this->assertSame($user->id, $ticket->created_by);
    }

    public function test_reply_is_rejected_on_locked_ticket(): void
    {
        $ticket = Ticket::create([
            'customer_id' => $this->aria->id, 'subject' => 'خرابی', 'description' => 'شرح',
            'status' => Ticket::STATUS_CLOSED, 'is_locked' => true,
        ]);
        $user = $this->customerAdmin($this->aria);

        $this->actingAs($user)
            ->post(route('portal.tickets.reply', $ticket), ['body' => 'پاسخ'])
            ->assertForbidden();
    }

    public function test_invoices_page_forbidden_when_permission_disabled(): void
    {
        $this->aria->update(['can_view_invoices' => false]);
        $user = $this->customerAdmin($this->aria);

        $this->actingAs($user)->get(route('portal.invoices.index'))->assertForbidden();
    }

    public function test_invoices_page_shows_only_own_customer_invoices(): void
    {
        $this->aria->update(['can_view_invoices' => true]);

        Invoice::create(['number' => 'F-A', 'customer_id' => $this->aria->id, 'issue_date' => now()]);
        Invoice::create(['number' => 'F-B', 'customer_id' => $this->other->id, 'issue_date' => now()]);

        $user = $this->customerAdmin($this->aria);

        $response = $this->actingAs($user)->get(route('portal.invoices.index'));

        $response->assertSee('F-A');
        $response->assertDontSee('F-B');
    }
}
