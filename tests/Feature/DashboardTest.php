<?php

namespace Tests\Feature;

use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\LatestTicketsWidget;
use App\Filament\Widgets\TicketsTrendChart;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Jalali;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * داشبوردِ پنل: کارت‌های آمار + نمودارِ روندِ تیکت + جدولِ آخرین تیکت‌ها.
 * ویجت‌ها با isLazy=false در تست رندر می‌شوند، پس بارگذاریِ صفحه خطاهای رندر را می‌گیرد.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    private function seedTicket(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        Ticket::create([
            'number' => 'T-1001', 'customer_id' => $customer->id,
            'subject' => 'مشکلِ نمونه', 'description' => 'شرحِ نمونه',
            'status' => Ticket::STATUS_NEW,
        ]);
    }

    public function test_dashboard_loads_for_admin(): void
    {
        $this->seedTicket();

        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_tickets_trend_chart_renders(): void
    {
        $this->seedTicket();

        Livewire::actingAs($this->admin())
            ->test(TicketsTrendChart::class)
            ->assertOk();
    }

    public function test_latest_tickets_widget_lists_recent_tickets(): void
    {
        $this->seedTicket();

        Livewire::actingAs($this->admin())
            ->test(LatestTicketsWidget::class)
            ->assertOk()
            ->assertSee('T-1001')
            ->assertSee(__('dashboard.latest_tickets'))   // عنوانِ فارسی، نه نامِ کلاسِ انگلیسی
            ->assertDontSee('Latest Tickets');
    }

    /**
     * سه باکسِ داشبورد بدونِ هم‌پوشانی و دقیقاً طبقِ تعریفِ تازه:
     *   نیازمندِ رسیدگی = فقط «در انتظار پاسخ پشتیبان»
     *   باز            = «در حال بررسی» یا «منتظر پاسخ مشتری»
     *   حل‌شده         = فقط «حل‌شده»
     * تیکتِ «جدید» و «منتظر پرداخت» در هیچ باکسی نباید شمرده شوند.
     */
    public function test_dashboard_boxes_are_partitioned(): void
    {
        $c = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $mk = function (string $status) use ($c): void {
            $t = Ticket::create(['customer_id' => $c->id, 'subject' => 's', 'description' => 'd']);
            $t->forceFill(['status' => $status, 'resolved_at' => $status === Ticket::STATUS_RESOLVED ? now() : null])->save();
        };

        // نیازمندِ رسیدگی = ۳ (فقط منتظرِ پشتیبان؛ «جدید» شمرده نمی‌شود)
        $mk(Ticket::STATUS_NEW);
        $mk(Ticket::STATUS_WAITING_SUPPORT);
        $mk(Ticket::STATUS_WAITING_SUPPORT);
        $mk(Ticket::STATUS_WAITING_SUPPORT);
        // باز = ۲ (بررسی + منتظرِ مشتری)
        $mk(Ticket::STATUS_IN_PROGRESS);
        $mk(Ticket::STATUS_WAITING_CUSTOMER);
        // حل‌شده = ۱
        $mk(Ticket::STATUS_RESOLVED);
        // یتیم: در هیچ باکسی نباید بیاید
        $mk(Ticket::STATUS_WAITING_PAYMENT);

        Livewire::actingAs($this->admin())
            ->test(DashboardStats::class)
            ->assertOk()
            ->assertSee(Jalali::digits('3'))   // نیازمندِ رسیدگی (فقط waiting_support)
            ->assertSee(Jalali::digits('2'))   // باز
            ->assertSee(Jalali::digits('1'));  // حل‌شده
    }
}
