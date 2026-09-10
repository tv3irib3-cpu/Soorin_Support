<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceCreateAfterCancelTest extends TestCase
{
    use RefreshDatabase;

    private function bootScenario(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'A', 'email' => 'admin@t.test', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $customer = Customer::create(['code' => 'C1', 'name' => 'مشتری', 'entity_type' => 'company']);
        $ticket = Ticket::create([
            'customer_id' => $customer->id, 'subject' => 's', 'description' => 'd', 'created_by' => $admin->id,
        ]);

        // فاکتور صادر و لغو می‌شود
        $invoice = Invoice::create(['customer_id' => $customer->id, 'ticket_id' => $ticket->id, 'issue_date' => now()]);
        $invoice->forceFill(['status' => 'issued'])->save();
        $invoice->cancel();

        return [$admin, $customer, $ticket];
    }

    public function test_create_page_loads_after_cancel(): void
    {
        [$admin, , $ticket] = $this->bootScenario();

        $this->actingAs($admin)
            ->get(InvoiceResource::getUrl('create', ['ticket' => $ticket->id]))
            ->assertSuccessful();
    }

    public function test_can_submit_new_invoice_with_service_lines_after_cancel(): void
    {
        [$admin, $customer, $ticket] = $this->bootScenario();

        $this->actingAs($admin);

        Livewire::test(CreateInvoice::class, ['ticket' => $ticket->id])
            ->fillForm([
                'customer_id' => $customer->id,
                'ticket_id'   => $ticket->id,
                'issue_date'  => now()->toDateString(),
                'service_items' => [
                    ['title' => 'کار اول', 'amount' => 300000],
                    ['title' => 'کار دوم', 'amount' => 200000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // فاکتور جدید ساخته و مجموعِ دو ردیف در آن جمع شده
        $new = Invoice::where('ticket_id', $ticket->id)->where('status', '!=', 'cancelled')->latest('id')->first();
        $this->assertNotNull($new, 'فاکتور جدید ساخته نشد');
        $this->assertSame(500000, (int) $new->service_amount, 'جمعِ ردیف‌های خدمت درست نیست');
        $this->assertSame(2, $new->items()->count());
    }
}
