<?php

namespace Tests\Feature;

use App\Filament\Pages\Backups;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackupBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_backups_and_names_carry_user_prefix(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create(['name' => 'Ali', 'email' => 'admin', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        $service = app(DatabaseBackupService::class);
        $a = $service->create();
        $b = $service->create();

        // نامِ بکاپِ دستی با ۵ حرفِ اولِ نامِ کاربر شروع می‌شود
        $this->assertStringStartsWith('Ali_', $a);
        $this->assertCount(2, $service->list());

        Livewire::test(Backups::class)
            ->set('selected', [$a, $b])
            ->call('deleteSelected');

        $this->assertCount(0, app(DatabaseBackupService::class)->list());
    }

    public function test_scheduled_backup_uses_auto_prefix(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        // بدونِ کاربرِ واردشده (مثلِ کنسول) → پیشوندِ Auto
        $name = app(DatabaseBackupService::class)->create('reason', 'Auto');
        $this->assertStringStartsWith('Auto_', $name);
    }
}
