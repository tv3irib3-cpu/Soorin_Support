<?php

namespace Tests\Feature;

use App\Auth\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'      => 'کاربر تست',
            'email'     => 'test@dpst.ir',
            'mobile'    => '09121234567',
            'password'  => 'secret123',
            'user_type' => User::TYPE_SUPPORT_STAFF,
        ], $attrs));
    }

    public function test_login_with_email(): void
    {
        $user = $this->user();

        $this->assertSame(
            $user->id,
            (new LoginAttempt('test@dpst.ir', 'secret123'))->authenticate()->id,
        );
    }

    public function test_login_with_mobile(): void
    {
        $user = $this->user();

        $this->assertSame(
            $user->id,
            (new LoginAttempt('09121234567', 'secret123'))->authenticate()->id,
        );
    }

    /**
     * کاربر ممکن است شماره را به هر شکلی وارد کند — همه باید کار کنند.
     */
    public function test_mobile_formats_are_normalized(): void
    {
        $this->user();

        foreach (['9121234567', '+989121234567', '00989121234567', '0912-123-4567', '۰۹۱۲۱۲۳۴۵۶۷'] as $input) {
            $this->assertSame(
                '09121234567',
                LoginAttempt::normalizeMobile($input),
                // آکولاد الزامی است: PHP کاراکتر «»» را جزو نام متغیر می‌گیرد
                "قالب «{$input}» درست نرمال نشد",
            );
        }
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user();

        $this->expectException(ValidationException::class);
        (new LoginAttempt('test@dpst.ir', 'wrong-password'))->authenticate();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $this->user(['is_active' => false]);

        $this->expectException(ValidationException::class);
        (new LoginAttempt('test@dpst.ir', 'secret123'))->authenticate();
    }

    public function test_successful_login_records_timestamp_and_ip(): void
    {
        $user = $this->user();
        $this->assertNull($user->last_login_at);

        (new LoginAttempt('test@dpst.ir', 'secret123'))->authenticate();

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }
}
