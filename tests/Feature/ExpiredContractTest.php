<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قرارداد منقضی یا لغوشده نباید پوشش بدهد — خدمتی که بعد از پایان
 * قرارداد ارائه شده باید کامل حساب شود.
 */
class ExpiredContractTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ContractPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $this->plan = ContractPlan::create(['name' => 'طلایی', 'cover_software' => 100, 'cover_hardware' => 70]);
    }

    private function contract(string $start, string $end, string $status = Contract::STATUS_ACTIVE): Contract
    {
        return Contract::create([
            'number' => 'C-' . fake()->unique()->numberBetween(100, 999),
            'customer_id' => $this->customer->id, 'contract_plan_id' => $this->plan->id,
            'start_date' => $start, 'end_date' => $end, 'status' => $status,
        ]);
    }

    private function invoiceWith(Contract $contract, string $issueDate): Invoice
    {
        $invoice = Invoice::create([
            'number' => 'F-' . fake()->unique()->numberBetween(100, 999),
            'customer_id' => $this->customer->id, 'contract_id' => $contract->id,
            'issue_date' => $issueDate,
        ]);
        $item = $invoice->items()->create(['item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => 2_000_000]);
        $item->recalculate($invoice->effectiveContractPlan(), 'software');
        $invoice->recalculate();

        return $invoice->fresh();
    }

    public function test_expired_contract_gives_no_coverage(): void
    {
        $contract = $this->contract(now()->subYears(2)->toDateString(), now()->subYear()->toDateString(), Contract::STATUS_EXPIRED);

        $invoice = $this->invoiceWith($contract, now()->toDateString());

        $this->assertSame(0, $invoice->contract_amount, 'قرارداد منقضی نباید پوشش بدهد');
        $this->assertSame(2_000_000, $invoice->payable_amount);
        $this->assertFalse($invoice->is_warranty);
    }

    public function test_cancelled_contract_gives_no_coverage(): void
    {
        $contract = $this->contract(now()->subMonth()->toDateString(), now()->addYear()->toDateString(), Contract::STATUS_CANCELLED);

        $invoice = $this->invoiceWith($contract, now()->toDateString());

        $this->assertSame(0, $invoice->contract_amount);
        $this->assertSame(2_000_000, $invoice->payable_amount);
    }

    public function test_active_contract_still_covers_normally(): void
    {
        $contract = $this->contract(now()->subMonth()->toDateString(), now()->addYear()->toDateString());

        $invoice = $this->invoiceWith($contract, now()->toDateString());

        $this->assertSame(2_000_000, $invoice->contract_amount);
        $this->assertSame(0, $invoice->payable_amount);
        $this->assertTrue($invoice->is_warranty);
    }

    public function test_validity_is_judged_by_issue_date_not_today(): void
    {
        // قرارداد دیروز تمام شده، ولی فاکتور مربوط به دو ماه پیش است →
        // آن زمان معتبر بوده، پس باید پوشش بدهد
        $contract = $this->contract(now()->subYear()->toDateString(), now()->subDay()->toDateString());

        $invoice = $this->invoiceWith($contract, now()->subMonths(2)->toDateString());

        $this->assertSame(2_000_000, $invoice->contract_amount);
    }

    public function test_item_rows_are_consistent_with_invoice_total(): void
    {
        $contract = $this->contract(now()->subYears(2)->toDateString(), now()->subYear()->toDateString(), Contract::STATUS_EXPIRED);

        $invoice = Invoice::create([
            'number' => 'F-X', 'customer_id' => $this->customer->id,
            'contract_id' => $contract->id, 'issue_date' => now(),
        ]);
        // عمداً با نوع قرارداد محاسبه می‌شود تا ردیف پوشش بگیرد
        $item = $invoice->items()->create(['item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => 2_000_000]);
        $item->recalculate($this->plan, 'software');
        $this->assertSame(2_000_000, $item->fresh()->contract_covered);

        // بعد از محاسبه فاکتور، ردیف هم باید صفر شود — نه اینکه ردیف پوشش
        // نشان بدهد ولی جمع فاکتور صفر باشد
        $invoice->recalculate();

        $this->assertSame(0, $item->fresh()->contract_covered);
        $this->assertSame(2_000_000, $item->fresh()->payable);
        $this->assertSame(0, $invoice->fresh()->contract_amount);
    }
}
