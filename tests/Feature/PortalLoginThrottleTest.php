<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * محدودیتِ تلاش برای ورودِ پرتال — دفاع در برابرِ حدسِ رمز (brute-force).
 * برای رفتن روی اینترنت حیاتی است.
 */
class PortalLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        RateLimiter::clear('someone@aria.test|127.0.0.1');
    }

    private function customerUser(): User
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $u = User::create([
            'name' => 'مدیر آریا', 'email' => 'someone@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id,
        ]);
        $u->assignRole(User::TYPE_CUSTOMER_ADMIN);

        return $u;
    }

    public function test_portal_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->customerUser();

        // ۵ تلاشِ ناموفق
        for ($i = 0; $i < 5; $i++) {
            $this->post('/portal/login', [
                'identifier' => 'someone@aria.test',
                'password'   => 'wrong-password',
            ])->assertSessionHasErrors('identifier');
        }

        // تلاشِ ششم — حتی با رمزِ درست — باید به‌خاطرِ throttle رد شود و کاربر واردِ سیستم نشود
        $this->post('/portal/login', [
            'identifier' => 'someone@aria.test',
            'password'   => 'secret123',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_successful_login_clears_the_throttle_counter(): void
    {
        $this->customerUser();

        // ۳ تلاشِ ناموفق، بعد ورودِ موفق
        for ($i = 0; $i < 3; $i++) {
            $this->post('/portal/login', ['identifier' => 'someone@aria.test', 'password' => 'wrong'])
                ->assertSessionHasErrors('identifier');
        }

        $this->post('/portal/login', ['identifier' => 'someone@aria.test', 'password' => 'secret123'])
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticated();
    }
}
