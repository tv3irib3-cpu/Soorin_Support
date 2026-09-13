<?php

namespace Tests\Feature;

use App\Filament\Pages\StorageManager;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\DataResetService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DataResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    private function buildWorld(): array
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $plan = ContractPlan::create(['name' => 'طلایی', 'cover_software' => 100, 'ceiling_amount' => 10_000_000]);
        $contract = Contract::create([
            'number' => 'C-1', 'customer_id' => $customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(), 'used_amount' => 500_000,
        ]);
        $category = TicketCategory::create(['name' => 'سخت‌افزار', 'is_active' => true]);
        $user = User::create(['name' => 'کاربر', 'email' => 'u1', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customer->id]);

        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 's', 'description' => 'd', 'created_by' => $user->id]);
        TicketMessage::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'body' => 'x']);
        Storage::disk('local')->put('ticket-attachments/f.pdf', 'data');
        TicketAttachment::create(['ticket_id' => $ticket->id, 'path' => 'ticket-attachments/f.pdf', 'original_name' => 'f.pdf', 'size' => 4]);

        $invoice = Invoice::create(['number' => 'F-1', 'customer_id' => $customer->id, 'issue_date' => now()]);
        $invoice->update(['status' => Invoice::STATUS_ISSUED, 'payable_amount' => 500_000]);
        Payment::create(['invoice_id' => $invoice->id, 'amount' => 100_000, 'paid_at' => now(), 'method' => 'cash']);

        ActivityLog::create(['action' => 'login', 'ip_address' => '127.0.0.1']);

        return compact('customer', 'plan', 'contract', 'category', 'user');
    }

    public function test_purge_deletes_transactional_data_but_keeps_configuration(): void
    {
        $w = $this->buildWorld();

        $counts = app(DataResetService::class)->purge();

        // پاک شد
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketMessage::count());
        $this->assertSame(0, TicketAttachment::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, ActivityLog::count());
        $this->assertFalse(Storage::disk('local')->exists('ticket-attachments/f.pdf'));

        // ماند
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, ContractPlan::count());
        $this->assertSame(1, Contract::count());
        $this->assertSame(1, TicketCategory::count());
        $this->assertTrue(User::whereKey($w['user']->id)->exists());

        // سقفِ مصرف‌شدهٔ قرارداد صفر شد
        $this->assertSame(0, (int) $w['contract']->fresh()->used_amount);

        // شمارش‌ها درست
        $this->assertSame(1, $counts['tickets']);
        $this->assertSame(1, $counts['invoices']);
        $this->assertSame(1, $counts['payments']);
        $this->assertSame(1, $counts['attachments']);
    }

    public function test_reset_action_requires_exact_confirmation_word(): void
    {
        $this->buildWorld();

        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        // عبارتِ اشتباه → هیچ‌چیز پاک نمی‌شود
        Livewire::test(StorageManager::class)
            ->callAction('resetData', ['confirm' => 'اشتباه']);
        $this->assertSame(1, Ticket::count());

        // عبارتِ درست → پاک می‌شود
        Livewire::test(StorageManager::class)
            ->callAction('resetData', ['confirm' => __('storage.reset_keyword')]);
        $this->assertSame(0, Ticket::count());
        $this->assertSame(1, Customer::count());   // پیکربندی ماند
    }
}
