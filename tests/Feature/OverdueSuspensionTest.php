<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private function overdueInvoice(Customer $customer, int $payable, int $paid): Invoice
    {
        $inv = Invoice::create([
            'customer_id' => $customer->id,
            'issue_date'  => now()->subDays(40),
            'due_date'    => now()->subDays(5),   // سررسید گذشته
        ]);
        $inv->forceFill([
            'status'         => $paid > 0 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_ISSUED,
            'payable_amount' => $payable,
            'paid_amount'    => $paid,
        ])->save();

        return $inv;
    }

    public function test_overdue_partial_debt_suspends_customer_once(): void
    {
        $c = Customer::create(['code' => 'C1', 'name' => 'مشتری', 'entity_type' => 'company']);
        $inv = $this->overdueInvoice($c, 1_000_000, 400_000);   // بدهیِ جزئی مانده

        $this->artisan('customers:suspend-overdue')->assertSuccessful();

        $c->refresh();
        $this->assertSame(Customer::STATUS_SUSPENDED, $c->service_status);
        $this->assertSame(Customer::OVERDUE_SUSPENSION_MESSAGE, $c->suspension_message);
        $this->assertNotNull($inv->fresh()->overdue_suspended_at);

        // مدیر دستی فعال می‌کند — همان فاکتور نباید دوباره معلقش کند
        $c->update(['service_status' => Customer::STATUS_ACTIVE]);
        $this->artisan('customers:suspend-overdue')->assertSuccessful();
        $this->assertSame(Customer::STATUS_ACTIVE, $c->fresh()->service_status);
    }

    public function test_fully_paid_overdue_does_not_suspend(): void
    {
        $c = Customer::create(['code' => 'C2', 'name' => 'مشتری۲', 'entity_type' => 'company']);
        $inv = $this->overdueInvoice($c, 1_000_000, 1_000_000);
        $inv->forceFill(['status' => Invoice::STATUS_PAID])->save();

        $this->artisan('customers:suspend-overdue')->assertSuccessful();

        $this->assertSame(Customer::STATUS_ACTIVE, $c->fresh()->service_status);
    }

    public function test_a_new_overdue_invoice_resuspends_after_manual_reactivation(): void
    {
        $c = Customer::create(['code' => 'C3', 'name' => 'مشتری۳', 'entity_type' => 'company']);
        $this->overdueInvoice($c, 500_000, 0);

        $this->artisan('customers:suspend-overdue');
        $this->assertSame(Customer::STATUS_SUSPENDED, $c->fresh()->service_status);

        $c->update(['service_status' => Customer::STATUS_ACTIVE]);

        // فاکتورِ دومِ سررسیدشده → دوباره معلق
        $this->overdueInvoice($c, 300_000, 0);
        $this->artisan('customers:suspend-overdue');
        $this->assertSame(Customer::STATUS_SUSPENDED, $c->fresh()->service_status);
    }
}
