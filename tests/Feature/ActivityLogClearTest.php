<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogClearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_support_admin_can_clear_the_activity_log(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'admin', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        ActivityLog::record('login', $admin);
        ActivityLog::record('login', $admin);
        $this->assertGreaterThanOrEqual(2, ActivityLog::count());

        $this->actingAs($admin);

        Livewire::test(ListActivityLogs::class)
            ->assertActionVisible('clearLog')
            ->callAction('clearLog');

        // همه پاک می‌شوند و فقط یک ردیفِ «پاک‌کردن تاریخچه» می‌ماند
        $this->assertSame(1, ActivityLog::count());
        $this->assertSame('log_cleared', ActivityLog::first()->action);
    }

    public function test_support_staff_cannot_see_the_clear_button(): void
    {
        $staff = User::create(['name' => 'S', 'email' => 'staff', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);
        // اجازهٔ دیدنِ تاریخچه را بده تا بتواند صفحه را باز کند، ولی دکمهٔ پاک‌کردن نباید ببیند
        $staff->givePermissionTo(\App\Enums\Permission::ViewActivity->value);

        $this->actingAs($staff);

        Livewire::test(ListActivityLogs::class)
            ->assertActionHidden('clearLog');
    }
}
