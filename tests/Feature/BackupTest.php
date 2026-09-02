<?php

namespace Tests\Feature;

use App\Filament\Pages\Backups;
use App\Filament\Pages\SslSettings;
use App\Models\Customer;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * پشتیبان‌گیری و بازیابی + دسترسیِ صفحه‌ها.
 *
 * مهم‌ترین تست رفت‌وبرگشتِ کامل است: داده‌ای که پشتیبان گرفته می‌شود باید پس از
 * حذف و بازیابی دقیقاً همان باشد.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseBackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->service = app(DatabaseBackupService::class);

        foreach ($this->service->list() as $old) {
            $this->service->delete($old['name']);
        }
    }

    private function supportAdmin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    private function supportStaff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 'staff@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $u->assignRole(User::TYPE_SUPPORT_STAFF);

        return $u;
    }

    public function test_a_backup_file_is_created_with_data(): void
    {
        Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);

        $name = $this->service->create();

        $this->assertTrue($this->service->exists($name));
        // دیتابیس ممکن است پیشوندِ جدول داشته باشد (soorin_)، پس نامِ واقعی ملاک است.
        $table = \Illuminate\Support\Facades\DB::getTablePrefix() . 'customers';
        $this->assertStringContainsString("INSERT INTO `{$table}`", file_get_contents($this->service->absolutePath($name)));
    }

    /** رفت‌وبرگشت: بکاپ → حذف رکورد → بازیابی → رکورد باید برگردد. */
    public function test_restore_brings_back_deleted_data(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $name = $this->service->create();

        $customer->forceDelete();
        $this->assertDatabaseMissing('customers', ['code' => 'ARIA']);

        $this->service->restore($this->service->absolutePath($name));

        $this->assertDatabaseHas('customers', ['code' => 'ARIA']);
    }

    /** بازیابی پیش از اجرا، یک پشتیبانِ ایمنی از وضعیت فعلی می‌گیرد. */
    public function test_restore_takes_a_safety_backup_first(): void
    {
        Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $name = $this->service->create();

        $safety = $this->service->restore($this->service->absolutePath($name));

        $this->assertNotNull($safety);
        $this->assertTrue($this->service->exists($safety));
    }

    public function test_backups_page_loads_for_support_admin(): void
    {
        $this->actingAs($this->supportAdmin())->get('/admin/backups')->assertOk();
    }

    public function test_backups_page_is_forbidden_for_support_staff(): void
    {
        $this->actingAs($this->supportStaff())->get('/admin/backups')->assertForbidden();
    }

    public function test_ssl_page_loads_for_support_admin(): void
    {
        $this->actingAs($this->supportAdmin())->get('/admin/ssl-settings')->assertOk();
    }

    public function test_ssl_page_is_forbidden_for_support_staff(): void
    {
        $this->actingAs($this->supportStaff())->get('/admin/ssl-settings')->assertForbidden();
    }
}
