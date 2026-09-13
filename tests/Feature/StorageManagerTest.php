<?php

namespace Tests\Feature;

use App\Filament\Pages\StorageManager;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\StorageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class StorageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('branding');
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    private function staff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 's1@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $u->assignRole(User::TYPE_SUPPORT_STAFF);

        return $u;
    }

    private function seedFiles(): void
    {
        Storage::disk('local')->put('ticket-attachments/a.pdf', 'aaa');
        Storage::disk('local')->put('ticket-attachments/b.png', 'bbbb');
        Storage::disk('local')->put('backups/PreUp_x.sql', 'sql');
        Storage::disk('branding')->put('customers/logo1.png', 'png');
        Storage::disk('branding')->put('logos/brand.svg', 'svg');
    }

    public function test_summary_reports_all_categories_with_counts(): void
    {
        $this->seedFiles();

        $summary = collect(app(StorageService::class)->summary())->keyBy('key');

        $this->assertSame(2, $summary['attachments']['count']);
        $this->assertSame(1, $summary['customer_logos']['count']);
        $this->assertSame(1, $summary['brand_logos']['count']);
        $this->assertSame(1, $summary['backups']['count']);
        $this->assertTrue($summary['customer_logos']['in_webroot']);
        $this->assertFalse($summary['attachments']['in_webroot']);
    }

    public function test_zip_contains_category_files_flat(): void
    {
        $this->seedFiles();

        $path = app(StorageService::class)->zip('attachments');
        $this->assertFileExists($path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($path);

        sort($names);
        $this->assertSame(['a.pdf', 'b.png'], $names);   // تخت، فقط نامِ فایل
    }

    public function test_restore_extracts_files_into_category_dir(): void
    {
        // یک ZIP بساز که یک فایل دارد
        $tmp = storage_path('app/private/test-restore-' . uniqid() . '.zip');
        @mkdir(dirname($tmp), 0775, true);
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE);
        $zip->addFromString('restored-logo.png', 'data');
        $zip->close();

        $count = app(StorageService::class)->restore('customer_logos', $tmp);
        @unlink($tmp);

        $this->assertSame(1, $count);
        $this->assertTrue(Storage::disk('branding')->exists('customers/restored-logo.png'));
    }

    public function test_delete_attachments_older_than_removes_old_only(): void
    {
        $c = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $t = Ticket::create(['customer_id' => $c->id, 'subject' => 's', 'description' => 'd']);

        Storage::disk('local')->put('ticket-attachments/old.pdf', 'old');
        Storage::disk('local')->put('ticket-attachments/new.pdf', 'new');

        $old = TicketAttachment::create(['ticket_id' => $t->id, 'path' => 'ticket-attachments/old.pdf', 'original_name' => 'old.pdf', 'size' => 3]);
        $old->forceFill(['created_at' => now()->subYears(2)])->save();

        $new = TicketAttachment::create(['ticket_id' => $t->id, 'path' => 'ticket-attachments/new.pdf', 'original_name' => 'new.pdf', 'size' => 3]);

        // حذفِ قدیمی‌تر از ۱ سال
        $result = app(StorageService::class)->deleteAttachmentsOlderThan(now()->subYear());

        $this->assertSame(1, $result['rows']);
        $this->assertDatabaseMissing('ticket_attachments', ['id' => $old->id]);
        $this->assertDatabaseHas('ticket_attachments', ['id' => $new->id]);
        $this->assertFalse(Storage::disk('local')->exists('ticket-attachments/old.pdf'));
        $this->assertTrue(Storage::disk('local')->exists('ticket-attachments/new.pdf'));
    }

    public function test_page_loads_for_admin_only(): void
    {
        $this->actingAs($this->staff());
        $this->assertFalse(StorageManager::canAccess());

        $this->actingAs($this->admin());
        $this->assertTrue(StorageManager::canAccess());

        // با فایل، شاخهٔ دکمهٔ دانلود (button tag=a) هم رندر و بررسی می‌شود.
        $this->seedFiles();

        Livewire::test(StorageManager::class)
            ->assertOk()
            ->assertSee(__('storage.download_zip'))
            ->assertSee(__('storage.categories.attachments'));
    }

    public function test_export_route_downloads_zip_for_admin_and_forbids_staff(): void
    {
        $this->seedFiles();

        $this->actingAs($this->staff())
            ->get(route('storage.export', 'attachments'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('storage.export', 'attachments'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_export_route_rejects_unknown_category(): void
    {
        $this->actingAs($this->admin())
            ->get(route('storage.export', 'bogus'))
            ->assertNotFound();
    }
}
