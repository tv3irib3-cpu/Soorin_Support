<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerProject;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست قاعده‌ای که نباید هرگز بشکند:
 * کاربر یک مشتری، تحت هیچ شرایطی نباید داده مشتری دیگر را ببیند.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Customer $aria;
    private Customer $other;
    private CustomerProject $bushehr;
    private CustomerProject $chabahar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aria  = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->other = Customer::create(['code' => 'OTHER', 'name' => 'شرکت دیگر']);

        $this->bushehr = $this->aria->projects()->create(['code' => 'BUS', 'name' => 'بوشهر']);
        $this->chabahar = $this->aria->projects()->create(['code' => 'CHB', 'name' => 'چابهار']);
    }

    private function ticketFor(Customer $customer, ?CustomerProject $project = null, ?User $creator = null): Ticket
    {
        return Ticket::create([
            'number'              => 'T' . fake()->unique()->numberBetween(1000, 9999),
            'customer_id'         => $customer->id,
            'customer_project_id' => $project?->id,
            'subject'             => 'تست',
            'description'         => 'شرح',
            'created_by'          => $creator?->id,
        ]);
    }

    private function customerUser(string $type, Customer $customer, array $extra = []): User
    {
        return User::create(array_merge([
            'name'        => 'کاربر',
            'email'       => fake()->unique()->safeEmail(),
            'password'    => 'secret123',
            'user_type'   => $type,
            'customer_id' => $customer->id,
        ], $extra));
    }

    public function test_customer_admin_sees_all_projects_of_own_customer_only(): void
    {
        $admin = $this->customerUser(User::TYPE_CUSTOMER_ADMIN, $this->aria);

        $this->ticketFor($this->aria, $this->bushehr);
        $this->ticketFor($this->aria, $this->chabahar);
        $this->ticketFor($this->other);   // مشتری دیگر — نباید دیده شود

        $visible = Ticket::visibleTo($admin)->get();

        $this->assertCount(2, $visible);
        $this->assertTrue($visible->every(fn ($t) => $t->customer_id === $this->aria->id));
    }

    public function test_customer_staff_sees_only_assigned_projects(): void
    {
        $staff = $this->customerUser(User::TYPE_CUSTOMER_STAFF, $this->aria, [
            'history_scope' => 'project',
        ]);
        $staff->projects()->attach($this->bushehr);

        $mine   = $this->ticketFor($this->aria, $this->bushehr);
        $theirs = $this->ticketFor($this->aria, $this->chabahar);

        $visible = Ticket::visibleTo($staff)->pluck('id');

        $this->assertContains($mine->id, $visible);
        $this->assertNotContains($theirs->id, $visible);
    }

    public function test_customer_staff_with_no_history_sees_nothing(): void
    {
        $staff = $this->customerUser(User::TYPE_CUSTOMER_STAFF, $this->aria);
        $staff->projects()->attach($this->bushehr);

        $this->ticketFor($this->aria, $this->bushehr);

        // پیش‌فرض کارشناس مشتری: هیچ سابقه‌ای نمی‌بیند
        $this->assertSame('none', $staff->historyScope());
        $this->assertCount(0, Ticket::visibleTo($staff)->get());
    }

    public function test_customer_staff_with_own_scope_sees_only_own_tickets(): void
    {
        $staff = $this->customerUser(User::TYPE_CUSTOMER_STAFF, $this->aria, [
            'history_scope' => 'own',
        ]);
        $colleague = $this->customerUser(User::TYPE_CUSTOMER_STAFF, $this->aria);

        $mine   = $this->ticketFor($this->aria, $this->bushehr, $staff);
        $theirs = $this->ticketFor($this->aria, $this->bushehr, $colleague);

        $visible = Ticket::visibleTo($staff)->pluck('id');

        $this->assertContains($mine->id, $visible);
        $this->assertNotContains($theirs->id, $visible);
    }

    public function test_suspended_customer_cannot_create_ticket(): void
    {
        $this->aria->update([
            'service_status'     => Customer::STATUS_SUSPENDED,
            'suspension_message' => 'به دلیل تسویه‌نشدن بدهی',
        ]);

        $admin = $this->customerUser(User::TYPE_CUSTOMER_ADMIN, $this->aria);

        $this->assertFalse($admin->fresh()->canCreateTicket());
        $this->assertSame('به دلیل تسویه‌نشدن بدهی', $this->aria->suspensionNotice());
    }

    public function test_account_level_flag_can_narrow_but_not_widen_access(): void
    {
        // سازمان اجازه ندارد؛ حساب هرچه بگوید بی‌اثر است
        $this->aria->update(['can_create_ticket' => false]);
        $user = $this->customerUser(User::TYPE_CUSTOMER_STAFF, $this->aria, [
            'can_create_ticket' => true,
        ]);

        $this->assertFalse($user->fresh()->canCreateTicket());

        // سازمان اجازه دارد ولی حساب محدود شده
        $this->aria->update(['can_create_ticket' => true]);
        $limited = $this->customerUser(User::TYPE_CUSTOMER_STAFF, $this->aria, [
            'can_create_ticket' => false,
        ]);

        $this->assertFalse($limited->fresh()->canCreateTicket());
    }

    public function test_support_user_sees_every_customer(): void
    {
        $support = User::create([
            'name'      => 'کارشناس پشتیبان',
            'email'     => 'staff@dpst.ir',
            'password'  => 'secret123',
            'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);

        $this->ticketFor($this->aria, $this->bushehr);
        $this->ticketFor($this->other);

        $this->assertCount(2, Ticket::visibleTo($support)->get());
    }
}
