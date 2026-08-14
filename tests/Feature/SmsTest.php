<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Models\Customer;
use App\Models\CustomerProject;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست زیرساخت پیامک — بدون سرویس واقعی، فقط بررسی می‌شود که:
 *   ۱. وقتی sms.enabled خاموش است، هیچ تماسی با گیت‌وی گرفته نمی‌شود
 *   ۲. وقتی روشن است، گیرندگان درست محاسبه می‌شوند
 */
class SmsTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = [];
        $sentRef = &$this->sent;

        $this->app->bind(SmsGateway::class, function () use (&$sentRef) {
            return new class($sentRef) implements SmsGateway {
                public function __construct(private array &$sent) {}

                public function send(string $toMobile, string $message): bool
                {
                    $this->sent[] = ['to' => $toMobile, 'message' => $message];

                    return true;
                }
            };
        });
    }

    private function enableSms(): void
    {
        Setting::set('sms.enabled', true, 'sms', 'bool');
    }

    public function test_no_sms_sent_when_disabled(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir', 'mobile' => '09120000001',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);

        Ticket::create(['customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);

        $this->assertEmpty($this->sent);
    }

    public function test_new_ticket_notifies_support_team(): void
    {
        $this->enableSms();

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir', 'mobile' => '09120000001',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir', 'mobile' => '09120000002',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
        // کاربر مشتری هرگز نباید این پیامک را بگیرد
        User::create([
            'name' => 'مشتری', 'email' => 'c@aria.test', 'mobile' => '09120000099',
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id,
        ]);

        Ticket::create(['customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);

        $recipients = array_column($this->sent, 'to');
        $this->assertContains('09120000001', $recipients);
        $this->assertContains('09120000002', $recipients);
        $this->assertNotContains('09120000099', $recipients);
    }

    public function test_resolving_ticket_notifies_customer_admin(): void
    {
        $this->enableSms();

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        User::create([
            'name' => 'مدیر مشتری', 'email' => 'owner@aria.test', 'mobile' => '09121111111',
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id,
        ]);

        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);
        $this->sent = [];

        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);

        $recipients = array_column($this->sent, 'to');
        $this->assertContains('09121111111', $recipients);
    }

    public function test_resolving_ticket_notifies_only_staff_of_same_project(): void
    {
        $this->enableSms();

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $bushehr  = $customer->projects()->create(['code' => 'BUS', 'name' => 'بوشهر']);
        $chabahar = $customer->projects()->create(['code' => 'CHB', 'name' => 'چابهار']);

        $bushehrStaff = User::create([
            'name' => 'کارشناس بوشهر', 'email' => 'b@aria.test', 'mobile' => '09122222222',
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $customer->id,
        ]);
        $bushehrStaff->projects()->attach($bushehr);

        $chabaharStaff = User::create([
            'name' => 'کارشناس چابهار', 'email' => 'ch@aria.test', 'mobile' => '09123333333',
            'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $customer->id,
        ]);
        $chabaharStaff->projects()->attach($chabahar);

        $ticket = Ticket::create([
            'customer_id' => $customer->id, 'customer_project_id' => $bushehr->id,
            'subject' => 'خرابی', 'description' => 'شرح',
        ]);
        $this->sent = [];

        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);

        $recipients = array_column($this->sent, 'to');
        $this->assertContains('09122222222', $recipients);
        $this->assertNotContains('09123333333', $recipients);
    }

    public function test_gateway_exception_does_not_break_ticket_creation(): void
    {
        $this->enableSms();

        $this->app->bind(SmsGateway::class, fn () => new class implements SmsGateway {
            public function send(string $toMobile, string $message): bool
            {
                throw new \RuntimeException('قطعی سرویس پیامک');
            }
        });

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir', 'mobile' => '09120000001',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);

        // نباید استثنا به بیرون درز کند
        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);

        $this->assertNotNull($ticket->id);
    }
}
