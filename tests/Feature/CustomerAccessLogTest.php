<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerAccessLogs\Pages\ListCustomerAccessLogs;
use App\Models\Customer;
use App\Models\CustomerAccessLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerAccessLogTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(): Customer
    {
        return Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا', 'entity_type' => 'company']);
    }

    private function customerUser(Customer $c, string $email = 'ali'): User
    {
        return User::create([
            'name' => 'کاربر', 'email' => $email, 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $c->id, 'is_active' => true,
        ]);
    }

    public function test_successful_login_is_logged_with_parsed_user_agent(): void
    {
        $c = $this->customer();
        $this->customerUser($c, 'ali');

        $this->withHeaders(['User-Agent' => self::CHROME])
            ->post('/portal/login', ['identifier' => 'ali', 'password' => 'secret123'])
            ->assertRedirect(route('portal.dashboard'));

        $log = CustomerAccessLog::where('event', CustomerAccessLog::EVENT_LOGIN)->first();
        $this->assertNotNull($log);
        $this->assertSame('ali', $log->username);
        $this->assertSame($c->id, $log->customer_id);
        $this->assertSame('Chrome 128', $log->browser);
        $this->assertSame('Windows 10/11', $log->platform);
        $this->assertSame('desktop', $log->device);
        $this->assertNotNull($log->ip_address);
    }

    public function test_failed_login_is_logged(): void
    {
        $c = $this->customer();
        $this->customerUser($c, 'ali');

        $this->withHeaders(['User-Agent' => self::CHROME])
            ->from('/portal/login')
            ->post('/portal/login', ['identifier' => 'ali', 'password' => 'WRONG'])
            ->assertRedirect('/portal/login');

        $log = CustomerAccessLog::where('event', CustomerAccessLog::EVENT_LOGIN_FAILED)->first();
        $this->assertNotNull($log);
        $this->assertSame('ali', $log->username);
        $this->assertNull($log->user_id);   // احراز نشده
    }

    public function test_page_visit_is_logged_via_middleware(): void
    {
        $c    = $this->customer();
        $user = $this->customerUser($c);

        $this->actingAs($user)
            ->withHeaders(['User-Agent' => self::CHROME])
            ->get('/portal')
            ->assertOk();

        $visit = CustomerAccessLog::where('event', CustomerAccessLog::EVENT_VISIT)->first();
        $this->assertNotNull($visit);
        $this->assertSame($user->id, $visit->user_id);
        $this->assertSame('dashboard', str($visit->route_name)->afterLast('.')->value());
    }

    public function test_ajax_polling_is_not_logged(): void
    {
        $c    = $this->customer();
        $user = $this->customerUser($c);

        $this->actingAs($user)
            ->withHeaders(['User-Agent' => self::CHROME, 'X-Requested-With' => 'XMLHttpRequest'])
            ->get('/portal/unread-count')
            ->assertOk();

        $this->assertSame(0, CustomerAccessLog::where('event', CustomerAccessLog::EVENT_VISIT)->count());
    }

    public function test_logout_is_logged(): void
    {
        $c    = $this->customer();
        $user = $this->customerUser($c);

        $this->actingAs($user)
            ->withHeaders(['User-Agent' => self::CHROME])
            ->post('/portal/logout')
            ->assertRedirect(route('portal.login'));

        $this->assertSame(1, CustomerAccessLog::where('event', CustomerAccessLog::EVENT_LOGOUT)->count());
    }

    public function test_admin_can_view_and_clear_logs(): void
    {
        $c    = $this->customer();
        $user = $this->customerUser($c);
        CustomerAccessLog::record(CustomerAccessLog::EVENT_LOGIN, request(), $user);

        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        Livewire::test(ListCustomerAccessLogs::class)
            ->assertOk()
            ->callAction('clearAll');

        $this->assertSame(0, CustomerAccessLog::count());
    }

    public function test_support_staff_cannot_view_access_logs(): void
    {
        $staff = User::create(['name' => 'کارشناس', 'email' => 's1', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);
        $this->actingAs($staff);

        $this->assertFalse(\App\Filament\Resources\CustomerAccessLogs\CustomerAccessLogResource::canViewAny());
    }
}
