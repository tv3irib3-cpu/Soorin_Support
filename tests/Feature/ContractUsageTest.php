<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست به‌روزرسانی used_amount قرارداد پس از هر فاکتور.
 *
 * نکته حیاتی: محاسبه دوباره یک فاکتور (مثلاً بعد از ویرایش ردیف‌ها)
 * نباید سهم قبلی‌اش را دوباره روی سقف قرارداد بشمارد.
 */
class ContractUsageTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ContractPlan $plan;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);

        $this->plan = ContractPlan::create([
            'name' => 'طلایی', 'cover_software' => 100, 'cover_hardware' => 70,
            'ceiling_amount' => 1_000_000,
        ]);

        $this->contract = Contract::create([
            'number' => 'C-1', 'customer_id' => $this->customer->id,
            'contract_plan_id' => $this->plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(),
        ]);
    }

    private function invoiceWithServiceAmount(int $amount, string $serviceType = 'software'): Invoice
    {
        $invoice = Invoice::create([
            'number' => 'F-' . fake()->unique()->numberBetween(100, 999),
            'customer_id' => $this->customer->id,
            'contract_id' => $this->contract->id,
            'issue_date' => now(),
        ]);

        $item = $invoice->items()->create([
            'item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => $amount,
        ]);
        $item->recalculate($this->plan, $serviceType);
        $invoice->recalculate();

        return $invoice->refresh();
    }

    public function test_invoice_increases_contract_used_amount(): void
    {
        $this->invoiceWithServiceAmount(500_000);

        $this->assertSame(500_000, $this->contract->fresh()->used_amount);
    }

    public function test_recalculating_same_invoice_does_not_double_count(): void
    {
        $invoice = $this->invoiceWithServiceAmount(500_000);
        $this->assertSame(500_000, $this->contract->fresh()->used_amount);

        // فقط دوباره محاسبه می‌کنیم بدون تغییر ردیف — نباید تغییری کند
        $invoice->recalculate();
        $this->assertSame(500_000, $this->contract->fresh()->used_amount);

        // یک ردیف دیگر اضافه می‌کنیم و دوباره محاسبه می‌کنیم
        $item = $invoice->items()->create([
            'item_type' => 'service', 'title' => 'خدمت دوم', 'quantity' => 1, 'unit_price' => 200_000,
        ]);
        $item->recalculate($this->plan, 'software');
        $invoice->recalculate();

        // جمع باید ۷۰۰٬۰۰۰ باشد، نه ۱٬۲۰۰٬۰۰۰ (که یعنی دوبار شمرده شده)
        $this->assertSame(700_000, $this->contract->fresh()->used_amount);
    }

    public function test_multiple_invoices_share_the_same_ceiling(): void
    {
        $this->invoiceWithServiceAmount(700_000);   // سقف را تا ۷۰۰٬۰۰۰ پر می‌کند
        $second = $this->invoiceWithServiceAmount(700_000);   // فقط ۳۰۰٬۰۰۰ مانده دارد

        $this->assertSame(300_000, $second->contract_amount);
        $this->assertSame(1_000_000, $this->contract->fresh()->used_amount);
    }

    public function test_cancelling_invoice_releases_contract_usage(): void
    {
        $invoice = $this->invoiceWithServiceAmount(500_000);
        $this->assertSame(500_000, $this->contract->fresh()->used_amount);

        $invoice->cancel();

        $this->assertSame(0, $this->contract->fresh()->used_amount);
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->fresh()->status);
        // مبلغ تاریخی برای حسابرسی دست‌نخورده می‌ماند
        $this->assertSame(500_000, $invoice->fresh()->contract_amount);
    }
}
