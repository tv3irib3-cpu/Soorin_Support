<?php

namespace Tests\Feature;

use App\Auth\LoginAttempt;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PortalUsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    private function customerUser(string $email, string $mobile = ''): User
    {
        $this->seed(RolePermissionSeeder::class);
        $c = Customer::create(['code' => 'C' . random_int(100, 999), 'name' => 'مشتری', 'entity_type' => 'company']);

        return User::create([
            'name' => 'کاربر', 'email' => $email, 'mobile' => $mobile ?: null,
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN,
            'customer_id' => $c->id, 'is_active' => true,
        ]);
    }

    public function test_customer_can_log_in_with_username(): void
    {
        $user = $this->customerUser('ali');   // نام کاربری در ستون email

        $result = (new LoginAttempt('ali', 'secret123'))->authenticate();

        $this->assertTrue($result->is($user));
    }

    public function test_customer_can_log_in_with_real_email(): void
    {
        $user = $this->customerUser('ali@customer.test');

        $result = (new LoginAttempt('ali@customer.test', 'secret123'))->authenticate();

        $this->assertTrue($result->is($user));
    }

    public function test_customer_can_log_in_with_mobile(): void
    {
        $user = $this->customerUser('ali2', '09121234567');

        $result = (new LoginAttempt('0912 123 4567', 'secret123'))->authenticate();

        $this->assertTrue($result->is($user));
    }

    public function test_wrong_password_fails(): void
    {
        $this->customerUser('ali3');

        $this->expectException(ValidationException::class);
        (new LoginAttempt('ali3', 'WRONG'))->authenticate();
    }
}
