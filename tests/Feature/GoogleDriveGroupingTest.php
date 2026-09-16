<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleDriveBackup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * دسته‌بندیِ فایل‌های گوگل‌درایو بر پایهٔ «نوبتِ پشتیبان» (زمانِ تغییرِ یکسان تا
 * دقیقه) و انتخاب/حذفِ گروهی — منطقِ خالص، بدونِ شبکه.
 */
class GoogleDriveGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $u = User::create(['name' => 'مدیر', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    /** دو نوبتِ پشتیبان: سه فایل در یک دقیقه، دو فایل در دقیقهٔ دیگر. */
    private function files(): array
    {
        return [
            ['id' => 'a', 'name' => 'db.zip', 'size' => 100, 'modified' => '2026-09-16T10:00:05Z'],
            ['id' => 'b', 'name' => 'att.zip', 'size' => 200, 'modified' => '2026-09-16T10:00:20Z'],
            ['id' => 'c', 'name' => 'logo.zip', 'size' => 300, 'modified' => '2026-09-16T10:00:59Z'],
            ['id' => 'd', 'name' => 'db2.zip', 'size' => 400, 'modified' => '2026-09-10T08:30:03Z'],
            ['id' => 'e', 'name' => 'att2.zip', 'size' => 500, 'modified' => '2026-09-10T08:30:40Z'],
        ];
    }

    public function test_files_group_by_backup_batch(): void
    {
        $this->actingAs($this->admin());

        $page = Livewire::test(GoogleDriveBackup::class)->set('files', $this->files());

        $groups = $page->instance()->groupedFiles();

        $this->assertCount(2, $groups);
        $this->assertCount(3, $groups[0]['files']);   // نوبتِ ۱۰:۰۰ سه فایل
        $this->assertCount(2, $groups[1]['files']);   // نوبتِ ۰۸:۳۰ دو فایل
        $this->assertSame(600, $groups[0]['bytes']);  // 100+200+300
        $this->assertSame(['a', 'b', 'c'], $groups[0]['ids']);
    }

    public function test_toggle_group_selects_and_deselects_whole_batch(): void
    {
        $this->actingAs($this->admin());

        $page = Livewire::test(GoogleDriveBackup::class)->set('files', $this->files());

        // تیک‌زدنِ کلِ نوبتِ اول
        $page->call('toggleGroup', ['a', 'b', 'c']);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], $page->get('selected'));

        // دوباره زدن → برداشته شود
        $page->call('toggleGroup', ['a', 'b', 'c']);
        $this->assertSame([], $page->get('selected'));
    }
}
