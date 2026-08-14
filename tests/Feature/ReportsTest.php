<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
        $this->staff->assignRole(User::TYPE_SUPPORT_STAFF);
    }

    private function resolvedTicket(int $workMinutes = 30, ?TicketCategory $category = null): Ticket
    {
        $ticket = Ticket::create([
            'customer_id' => $this->customer->id, 'subject' => 'خرابی', 'description' => 'شرح',
            'assigned_to' => $this->staff->id, 'ticket_category_id' => $category?->id,
            'work_minutes' => $workMinutes,
        ]);
        $ticket->messages()->create(['user_id' => $this->staff->id, 'body' => 'پاسخ', 'is_internal' => false]);
        $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);

        return $ticket->fresh();
    }

    public function test_summary_counts_resolved_tickets_and_revenue_within_range(): void
    {
        $this->resolvedTicket(45);

        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $this->customer->id, 'issue_date' => now(),
            'status' => Invoice::STATUS_ISSUED,
        ]);
        $item = $invoice->items()->create(['item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => 2_000_000]);
        $item->recalculate(null, 'hardware');
        $invoice->recalculate();

        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertSame(1, $report['summary']['service_count']);
        $this->assertSame(45, $report['summary']['work_minutes']);
        $this->assertSame(2_000_000, $report['summary']['revenue']);
    }

    public function test_tickets_outside_range_are_excluded(): void
    {
        $ticket = $this->resolvedTicket(20);
        $ticket->forceFill(['resolved_at' => now()->subMonths(2)])->save();

        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertSame(0, $report['summary']['service_count']);
    }

    public function test_by_category_groups_correctly(): void
    {
        $hardware = TicketCategory::create(['name' => 'سخت‌افزار', 'service_type' => 'hardware']);
        $hdd = TicketCategory::create(['parent_id' => $hardware->id, 'name' => 'هارد', 'service_type' => 'hardware']);

        $this->resolvedTicket(10, $hdd);
        $this->resolvedTicket(10, $hdd);

        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertCount(1, $report['by_category']);
        $this->assertSame(2, $report['by_category']->first()['count']);
        $this->assertSame('سخت‌افزار ← هارد', $report['by_category']->first()['category']);
    }

    public function test_by_staff_computes_average_response_time(): void
    {
        $this->resolvedTicket(10);

        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertCount(1, $report['by_staff']);
        $this->assertSame('کارشناس', $report['by_staff']->first()['staff']);
        $this->assertSame(1, $report['by_staff']->first()['resolved']);
        $this->assertNotNull($report['by_staff']->first()['avg_response_hr']);
    }

    public function test_warranty_value_reflects_contract_coverage(): void
    {
        $plan = ContractPlan::create(['name' => 'طلایی', 'cover_hardware' => 70]);
        $contract = Contract::create([
            'number' => 'C-1', 'customer_id' => $this->customer->id, 'contract_plan_id' => $plan->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(),
        ]);

        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $this->customer->id, 'contract_id' => $contract->id,
            'issue_date' => now(), 'status' => Invoice::STATUS_ISSUED,
        ]);
        $item = $invoice->items()->create(['item_type' => 'service', 'title' => 'خدمت', 'quantity' => 1, 'unit_price' => 1_000_000]);
        $item->recalculate($plan, 'hardware');
        $invoice->recalculate();

        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertSame(700_000, $report['summary']['warranty_value']);
    }

    public function test_excel_export_downloads_valid_file(): void
    {
        $this->resolvedTicket();

        $this->actingAs($this->staff)
            ->get(route('reports.export.excel', ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_pdf_export_downloads_valid_file(): void
    {
        $this->resolvedTicket();

        $response = $this->actingAs($this->staff)
            ->get(route('reports.export.pdf', ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_export_forbidden_for_customer_user(): void
    {
        $customerUser = User::create([
            'name' => 'مشتری', 'email' => 'c@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($customerUser)
            ->get(route('reports.export.excel'))
            ->assertForbidden();
    }

    public function test_reports_page_loads_for_staff(): void
    {
        $this->actingAs($this->staff)->get('/admin/reports')->assertOk();
    }

    public function test_reports_page_forbidden_without_permission(): void
    {
        $customerUser = User::create([
            'name' => 'مشتری', 'email' => 'c@aria.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($customerUser)->get('/admin/reports')->assertForbidden();
    }

    public function test_excel_service_builds_spreadsheet_with_four_sheets(): void
    {
        $this->resolvedTicket();
        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $spreadsheet = app(ReportExcelService::class)->build($report);

        $this->assertSame(4, $spreadsheet->getSheetCount());
    }

    public function test_pdf_service_has_no_untranslated_lang_keys(): void
    {
        $this->resolvedTicket();
        $report = app(ReportService::class)->generate(now()->subDay(), now()->addDay());

        $html = view('pdf.report', [
            'report'  => $report,
            'company' => config('branding.company'),
            'money'   => fn (int $amount) => \App\Support\Jalali::money($amount),
            'date'    => fn ($d) => \App\Support\Jalali::format($d),
            'digits'  => fn ($n) => \App\Support\Jalali::digits((string) $n),
        ])->render();

        $this->assertDoesNotMatchRegularExpression('/\breports\.[a-z_]+\b/', $html);
    }
}
