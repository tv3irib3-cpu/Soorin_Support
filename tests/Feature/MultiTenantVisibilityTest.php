<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerProject;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ماتریسِ کاملِ «چه کسی چه تیکتی را می‌بیند» — با چند مشتری، چند پروژه و چند
 * کارشناس با دامنه‌های دسترسیِ متفاوت (customer / project / own).
 *
 * این حیاتی‌ترین تستِ امنیت است: هیچ مشتری/کارشناسی نباید دادهٔ دیگری را ببیند.
 */
class MultiTenantVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Customer $aria;
    private Customer $beta;
    private CustomerProject $pA1;
    private CustomerProject $pA2;
    private CustomerProject $pB1;

    private User $adminA;      // مدیرِ خریدارِ آریا — دامنهٔ کلِ سازمان
    private User $staffAOwn;   // کارشناسِ آریا — فقط سوابقِ خودش
    private User $staffAProj;  // کارشناسِ آریا — فقط پروژهٔ P_A1
    private User $adminB;      // مدیرِ خریدارِ بتا
    private User $staffBOwn;   // کارشناسِ بتا — فقط سوابقِ خودش
    private User $supportAdmin;
    private User $supportStaff;

    private Ticket $tA_admin;  // آریا / P_A1 / ساختهٔ مدیرِ آریا
    private Ticket $tA_own;    // آریا / P_A2 / ساختهٔ کارشناسِ own
    private Ticket $tA_proj;   // آریا / P_A1 / ساختهٔ کارشناسِ project
    private Ticket $tB;        // بتا  / P_B1 / ساختهٔ کارشناسِ بتا

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->aria = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->beta = Customer::create(['code' => 'BETA', 'name' => 'شرکت بتا']);

        $this->pA1 = $this->project($this->aria, 'ARIA-P1', 'بندرعباس');
        $this->pA2 = $this->project($this->aria, 'ARIA-P2', 'چابهار');
        $this->pB1 = $this->project($this->beta, 'BETA-P1', 'بوشهر');

        $this->adminA = $this->user('admin@aria.test', User::TYPE_CUSTOMER_ADMIN, $this->aria);
        $this->staffAOwn = $this->user('own@aria.test', User::TYPE_CUSTOMER_STAFF, $this->aria, ['history_scope' => 'own']);
        $this->staffAProj = $this->user('proj@aria.test', User::TYPE_CUSTOMER_STAFF, $this->aria, ['history_scope' => 'project']);
        $this->staffAProj->projects()->attach($this->pA1->id);   // فقط به P_A1 دسترسی دارد

        $this->adminB = $this->user('admin@beta.test', User::TYPE_CUSTOMER_ADMIN, $this->beta);
        $this->staffBOwn = $this->user('own@beta.test', User::TYPE_CUSTOMER_STAFF, $this->beta, ['history_scope' => 'own']);

        $this->supportAdmin = $this->user('admin@dpst.ir', User::TYPE_SUPPORT_ADMIN);
        $this->supportStaff = $this->user('staff@dpst.ir', User::TYPE_SUPPORT_STAFF);

        $this->tA_admin = $this->ticket($this->aria, $this->pA1, $this->adminA);
        $this->tA_own   = $this->ticket($this->aria, $this->pA2, $this->staffAOwn);
        $this->tA_proj  = $this->ticket($this->aria, $this->pA1, $this->staffAProj);
        $this->tB       = $this->ticket($this->beta, $this->pB1, $this->staffBOwn);
    }

    private function project(Customer $c, string $code, string $name): CustomerProject
    {
        return CustomerProject::create(['customer_id' => $c->id, 'code' => $code, 'name' => $name]);
    }

    private function user(string $email, string $type, ?Customer $c = null, array $extra = []): User
    {
        $u = User::create(array_merge([
            'name' => $email, 'email' => $email, 'password' => 'secret123',
            'user_type' => $type, 'customer_id' => $c?->id,
        ], $extra));
        $u->assignRole($type);

        return $u;
    }

    private function ticket(Customer $c, CustomerProject $p, User $creator): Ticket
    {
        return Ticket::create([
            'customer_id'         => $c->id,
            'customer_project_id' => $p->id,
            'subject'             => 'تیکتِ ' . $p->code . ' توسط ' . $creator->email,
            'description'         => 'شرح',
            'created_by'          => $creator->id,
            'status'              => Ticket::STATUS_NEW,
        ]);
    }

    /** @return array<int> شناسه‌های تیکت‌های قابل‌مشاهده برای این کاربر */
    private function visibleIds(User $user): array
    {
        return Ticket::visibleTo($user)->pluck('id')->sort()->values()->all();
    }

    private function ids(Ticket ...$tickets): array
    {
        return collect($tickets)->pluck('id')->sort()->values()->all();
    }

    /** مدیرِ خریدار همهٔ تیکت‌های سازمانِ خودش را می‌بیند — و هیچ‌کدام از مشتریِ دیگر. */
    public function test_customer_admin_sees_only_their_own_company_tickets(): void
    {
        $this->assertEqualsCanonicalizing(
            $this->ids($this->tA_admin, $this->tA_own, $this->tA_proj),
            $this->visibleIds($this->adminA),
        );

        $this->assertEqualsCanonicalizing(
            $this->ids($this->tB),
            $this->visibleIds($this->adminB),
        );

        // مدیرِ آریا هیچ‌یک از تیکت‌های بتا را نمی‌بیند (و برعکس)
        $this->assertNotContains($this->tB->id, $this->visibleIds($this->adminA));
        $this->assertNotContains($this->tA_admin->id, $this->visibleIds($this->adminB));
    }

    /** کارشناسِ «own» فقط تیکتِ ساختهٔ خودش را می‌بیند — نه تیکتِ کارشناسِ دیگر. */
    public function test_own_scope_staff_sees_only_their_own_tickets(): void
    {
        $this->assertEqualsCanonicalizing(
            $this->ids($this->tA_own),
            $this->visibleIds($this->staffAOwn),
        );

        // تیکتِ مدیر و تیکتِ کارشناسِ دیگرِ همان شرکت را نمی‌بیند
        $this->assertNotContains($this->tA_admin->id, $this->visibleIds($this->staffAOwn));
        $this->assertNotContains($this->tA_proj->id, $this->visibleIds($this->staffAOwn));
        // و قطعاً چیزی از مشتریِ دیگر
        $this->assertNotContains($this->tB->id, $this->visibleIds($this->staffAOwn));
    }

    /** کارشناسِ «project» فقط تیکت‌های پروژه‌های در دسترسش را می‌بیند. */
    public function test_project_scope_staff_sees_only_their_assigned_projects(): void
    {
        // به P_A1 دسترسی دارد → تیکت‌های P_A1 (ساختهٔ هرکسی) را می‌بیند، نه P_A2 را
        $this->assertEqualsCanonicalizing(
            $this->ids($this->tA_admin, $this->tA_proj),
            $this->visibleIds($this->staffAProj),
        );

        $this->assertNotContains($this->tA_own->id, $this->visibleIds($this->staffAProj)); // P_A2
        $this->assertNotContains($this->tB->id, $this->visibleIds($this->staffAProj));     // مشتریِ دیگر
    }

    /** کارشناسِ بتا فقط تیکتِ خودش را می‌بیند و هیچ‌چیز از آریا. */
    public function test_customer_staff_is_isolated_across_customers(): void
    {
        $this->assertEqualsCanonicalizing(
            $this->ids($this->tB),
            $this->visibleIds($this->staffBOwn),
        );

        foreach ([$this->tA_admin, $this->tA_own, $this->tA_proj] as $ariaTicket) {
            $this->assertNotContains($ariaTicket->id, $this->visibleIds($this->staffBOwn));
        }
    }

    /** پشتیبان (ادمین و کارشناس) همهٔ تیکت‌های همهٔ مشتری‌ها را می‌بیند. */
    public function test_support_users_see_every_ticket(): void
    {
        $all = $this->ids($this->tA_admin, $this->tA_own, $this->tA_proj, $this->tB);

        $this->assertEqualsCanonicalizing($all, $this->visibleIds($this->supportAdmin));
        $this->assertEqualsCanonicalizing($all, $this->visibleIds($this->supportStaff));
    }

    /** آزمونِ متقاطعِ پرتال: هر کاربر فقط تیکت‌های مجازش را با ۲۰۰ باز می‌کند، بقیه ۴۰۴. */
    public function test_portal_show_enforces_the_same_matrix(): void
    {
        // staffAOwn: فقط tA_own → ۲۰۰؛ بقیه ۴۰۴
        $this->actingAs($this->staffAOwn)->get(route('portal.tickets.show', $this->tA_own))->assertOk();
        $this->actingAs($this->staffAOwn)->get(route('portal.tickets.show', $this->tA_admin))->assertNotFound();
        $this->actingAs($this->staffAOwn)->get(route('portal.tickets.show', $this->tB))->assertNotFound();

        // adminB: فقط tB → ۲۰۰؛ تیکتِ آریا ۴۰۴
        $this->actingAs($this->adminB)->get(route('portal.tickets.show', $this->tB))->assertOk();
        $this->actingAs($this->adminB)->get(route('portal.tickets.show', $this->tA_admin))->assertNotFound();
    }
}
