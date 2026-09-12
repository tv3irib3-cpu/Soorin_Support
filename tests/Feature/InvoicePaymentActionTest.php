<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * دکمهٔ «ثبت پرداخت» روی صفحهٔ خودِ فاکتور (بدونِ رفتن به بخشِ پرداخت‌ها).
 */
class InvoicePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function issuedInvoice(int $payable): Invoice
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $invoice  = Invoice::create([
            'number' => 'F-1', 'customer_id' => $customer->id, 'issue_date' => now(),
        ]);
        $invoice->forceFill([
            'status'         => Invoice::STATUS_ISSUED,
            'payable_amount' => $payable,
            'paid_amount'    => 0,
        ])->save();

        return $invoice;
    }

    public function test_admin_can_record_payment_from_invoice_view_page(): void
    {
        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        $invoice = $this->issuedInvoice(1_000_000);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->callAction('addPayment', [
                'amount'  => 400_000,
                'paid_at' => now()->toDateString(),
                'method'  => 'transfer',
            ])
            ->assertHasNoActionErrors();

        $invoice->refresh();
        $this->assertSame(400_000, (int) $invoice->paid_amount);
        $this->assertSame(600_000, $invoice->balance());
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertSame($admin->id, $invoice->payments()->first()->registered_by);
    }

    public function test_payment_over_balance_is_capped_to_balance(): void
    {
        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        $invoice = $this->issuedInvoice(500_000);

        // فرمِ اکشن maxValue دارد؛ اینجا صحّتِ منطقِ سقف را با مقدارِ مجاز می‌سنجیم
        Livewire::test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->callAction('addPayment', [
                'amount'  => 500_000,
                'paid_at' => now()->toDateString(),
                'method'  => 'cash',
            ])
            ->assertHasNoActionErrors();

        $invoice->refresh();
        $this->assertSame(0, $invoice->balance());
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
    }

    public function test_payment_action_hidden_when_invoice_fully_paid(): void
    {
        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        $invoice = $this->issuedInvoice(500_000);
        $invoice->forceFill(['paid_amount' => 500_000, 'status' => Invoice::STATUS_PAID])->save();

        // ماندهٔ صفر → دکمهٔ ثبت پرداخت نباید دیده شود
        Livewire::test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->assertActionHidden('addPayment');
    }

    public function test_payment_action_hidden_on_draft_invoice(): void
    {
        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        $customer = Customer::create(['code' => 'D', 'name' => 'پیش‌نویس']);
        $invoice  = Invoice::create(['number' => 'F-9', 'customer_id' => $customer->id, 'issue_date' => now()]);
        // پیش‌فرض draft با ماندهٔ صفر → پنهان

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->assertActionHidden('addPayment');
    }
}
