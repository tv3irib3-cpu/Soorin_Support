<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست قاعده سه‌عددی فاکتور.
 *
 * ارزش خدمت همیشه با مبلغ واقعی ثبت می‌شود — حتی وقتی مشتری چیزی نمی‌پردازد.
 */
class InvoiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
    }

    private function plan(array $attrs = []): ContractPlan
    {
        return ContractPlan::create(array_merge([
            'name'           => 'طلایی',
            'cover_software' => 100,
            'cover_hardware' => 70,
            'cover_parts'    => 50,
            'cover_onsite'   => 100,
        ], $attrs));
    }

    private function contract(ContractPlan $plan): Contract
    {
        return Contract::create([
            'number'           => 'C-' . fake()->unique()->numberBetween(100, 999),
            'customer_id'      => $this->customer->id,
            'contract_plan_id' => $plan->id,
            'start_date'       => now()->subMonth(),
            'end_date'         => now()->addYear(),
        ]);
    }

    private function invoice(?Contract $contract = null): Invoice
    {
        return Invoice::create([
            'number'      => 'F-' . fake()->unique()->numberBetween(100, 999),
            'customer_id' => $this->customer->id,
            'contract_id' => $contract?->id,
            'issue_date'  => now(),
            'status'      => Invoice::STATUS_ISSUED,
        ]);
    }

    private function addItem(Invoice $invoice, string $type, int $price, ?ContractPlan $plan, string $serviceType = 'hardware', ?string $method = null): InvoiceItem
    {
        $item = $invoice->items()->create([
            'item_type'  => $type,
            'title'      => 'تعویض هارد',
            'quantity'   => 1,
            'unit_price' => $price,
        ]);

        $item->recalculate($plan, $serviceType, $method);

        return $item;
    }

    public function test_hardware_service_under_gold_contract_covers_seventy_percent(): void
    {
        $plan     = $this->plan();
        $contract = $this->contract($plan);
        $invoice  = $this->invoice($contract);

        $this->addItem($invoice, 'service', 5_000_000, $plan, 'hardware');
        $invoice->recalculate();

        $invoice->refresh();

        $this->assertSame(5_000_000, $invoice->service_amount, 'ارزش خدمت باید کامل ثبت شود');
        $this->assertSame(3_500_000, $invoice->contract_amount, 'سهم قرارداد ۷۰٪');
        $this->assertSame(1_500_000, $invoice->payable_amount, 'پرداختی مشتری');
        $this->assertFalse($invoice->is_warranty);
    }

    public function test_fully_covered_service_records_real_value_with_zero_payable(): void
    {
        $plan     = $this->plan();
        $contract = $this->contract($plan);
        $invoice  = $this->invoice($contract);

        // نرم‌افزاری = ۱۰۰٪ پوشش
        $this->addItem($invoice, 'service', 4_000_000, $plan, 'software');
        $invoice->recalculate();

        $invoice->refresh();

        // نکته کلیدی: ارزش خدمت صفر نمی‌شود، فقط پرداختی صفر می‌شود
        $this->assertSame(4_000_000, $invoice->service_amount);
        $this->assertSame(4_000_000, $invoice->contract_amount);
        $this->assertSame(0, $invoice->payable_amount);
        $this->assertTrue($invoice->is_warranty);
    }

    public function test_customer_without_contract_pays_everything(): void
    {
        $invoice = $this->invoice();

        $this->addItem($invoice, 'service', 3_000_000, null, 'hardware');
        $invoice->recalculate();

        $invoice->refresh();

        $this->assertSame(3_000_000, $invoice->service_amount);
        $this->assertSame(0, $invoice->contract_amount);
        $this->assertSame(3_000_000, $invoice->payable_amount);
    }

    public function test_contract_ceiling_limits_coverage(): void
    {
        $plan     = $this->plan(['ceiling_amount' => 1_000_000]);
        $contract = $this->contract($plan);
        $contract->update(['used_amount' => 800_000]);   // فقط ۲۰۰٬۰۰۰ مانده

        $invoice = $this->invoice($contract);
        $this->addItem($invoice, 'service', 5_000_000, $plan, 'software'); // ۱۰۰٪ = ۵ میلیون
        $invoice->recalculate();

        $invoice->refresh();

        $this->assertSame(200_000, $invoice->contract_amount, 'سهم قرارداد به مانده سقف محدود می‌شود');
        $this->assertSame(4_800_000, $invoice->payable_amount);
    }

    public function test_parts_use_parts_coverage_rate(): void
    {
        $plan     = $this->plan();
        $contract = $this->contract($plan);
        $invoice  = $this->invoice($contract);

        // قطعه = ۵۰٪ حتی وقتی نوع خدمت نرم‌افزاری است
        $this->addItem($invoice, 'part', 2_000_000, $plan, 'software');
        $invoice->recalculate();

        $invoice->refresh();

        $this->assertSame(2_000_000, $invoice->parts_amount);
        $this->assertSame(1_000_000, $invoice->contract_amount);
        $this->assertSame(1_000_000, $invoice->payable_amount);
    }

    public function test_onsite_method_overrides_service_type_rate(): void
    {
        // سخت‌افزاری ۷۰٪ است ولی اعزام حضوری ۱۰۰٪
        $plan     = $this->plan();
        $contract = $this->contract($plan);
        $invoice  = $this->invoice($contract);

        $this->addItem($invoice, 'service', 1_000_000, $plan, 'hardware', 'onsite');
        $invoice->recalculate();

        $this->assertSame(1_000_000, $invoice->refresh()->contract_amount);
    }

    public function test_payable_never_goes_negative(): void
    {
        $plan     = $this->plan();
        $contract = $this->contract($plan);
        $invoice  = $this->invoice($contract);
        $invoice->update(['discount_amount' => 9_000_000]);   // تخفیف بیش از مبلغ

        $this->addItem($invoice, 'service', 1_000_000, $plan, 'software');
        $invoice->recalculate();

        $this->assertSame(0, $invoice->refresh()->payable_amount);
    }

    public function test_payment_updates_invoice_status(): void
    {
        $invoice = $this->invoice();
        $this->addItem($invoice, 'service', 1_000_000, null);
        $invoice->recalculate();

        $invoice->payments()->create([
            'amount'  => 400_000,
            'paid_at' => now(),
            'method'  => 'transfer',
        ]);

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->refresh()->status);
        $this->assertSame(600_000, $invoice->balance());

        $invoice->payments()->create([
            'amount'  => 600_000,
            'paid_at' => now(),
            'method'  => 'cash',
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertSame(0, $invoice->balance());
    }
}
