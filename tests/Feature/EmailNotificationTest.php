<?php

namespace Tests\Feature;

use App\Mail\InvoiceIssuedMail;
use App\Mail\TicketReplyMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * تست اطلاع‌رسانی ایمیل — پاسخ تیکت و صدور فاکتور.
 * هیچ ایمیل واقعی ارسال نمی‌شود؛ Mail::fake فقط بررسی می‌کند «قرار بود» ارسال شود یا نه.
 */
class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->customer = Customer::create([
            'code' => 'ARIA', 'name' => 'شرکت آریا', 'email' => 'customer@aria.test',
        ]);
        $this->staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
    }

    private function ticket(): Ticket
    {
        return Ticket::create([
            'customer_id' => $this->customer->id, 'subject' => 'خرابی', 'description' => 'شرح',
        ]);
    }

    public function test_staff_reply_emails_the_customer(): void
    {
        $ticket = $this->ticket();

        $ticket->messages()->create([
            'user_id' => $this->staff->id, 'body' => 'بررسی شد', 'is_internal' => false,
        ]);

        Mail::assertSent(TicketReplyMail::class, fn ($mail) => $mail->hasTo('customer@aria.test')
            && $mail->ticket->is($ticket));
    }

    public function test_internal_note_sends_no_email(): void
    {
        $ticket = $this->ticket();

        $ticket->messages()->create([
            'user_id' => $this->staff->id, 'body' => 'یادداشت داخلی', 'is_internal' => true,
        ]);

        Mail::assertNothingSent();
    }

    public function test_customer_without_email_sends_no_reply_mail(): void
    {
        $this->customer->update(['email' => null]);
        $ticket = $this->ticket();

        $ticket->messages()->create([
            'user_id' => $this->staff->id, 'body' => 'بررسی شد', 'is_internal' => false,
        ]);

        Mail::assertNothingSent();
    }

    public function test_customer_reply_emails_the_assignee(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['assigned_to' => $this->staff->id]);

        $customerUser = User::create([
            'name' => 'مشتری', 'email' => 'user@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id,
        ]);

        $ticket->messages()->create([
            'user_id' => $customerUser->id, 'body' => 'پیگیری می‌کنم', 'is_internal' => false,
        ]);

        Mail::assertSent(TicketReplyMail::class, fn ($mail) => $mail->hasTo('staff@dpst.ir'));
    }

    public function test_customer_reply_with_no_assignee_sends_no_email(): void
    {
        $ticket = $this->ticket();

        $customerUser = User::create([
            'name' => 'مشتری', 'email' => 'user@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id,
        ]);

        $ticket->messages()->create([
            'user_id' => $customerUser->id, 'body' => 'پیگیری می‌کنم', 'is_internal' => false,
        ]);

        Mail::assertNothingSent();
    }

    public function test_issuing_invoice_emails_the_customer(): void
    {
        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $this->customer->id, 'issue_date' => now(),
        ]);

        app(\App\Actions\IssueInvoice::class)($invoice);

        // فاکتور بدون ردیف، مبلغ قابل‌پرداختش صفر است، پس بلافاصله «پرداخت‌شده» می‌شود —
        // نکته اینجا صرفاً این است که دیگر «پیش‌نویس» نیست
        $this->assertNotSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
        Mail::assertSent(InvoiceIssuedMail::class, fn ($mail) => $mail->hasTo('customer@aria.test')
            && $mail->invoice->is($invoice->fresh()));
    }

    public function test_issuing_invoice_without_customer_email_sends_no_mail(): void
    {
        $this->customer->update(['email' => null]);

        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $this->customer->id, 'issue_date' => now(),
        ]);

        app(\App\Actions\IssueInvoice::class)($invoice);

        // فاکتور بدون ردیف، مبلغ قابل‌پرداختش صفر است، پس بلافاصله «پرداخت‌شده» می‌شود —
        // نکته اینجا صرفاً این است که دیگر «پیش‌نویس» نیست
        $this->assertNotSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
        Mail::assertNothingSent();
    }
}
