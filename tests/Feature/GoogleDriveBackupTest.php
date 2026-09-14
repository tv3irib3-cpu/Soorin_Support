<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleDriveBackup;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleDriveService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleDriveBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    private function svc(): GoogleDriveService
    {
        return app(GoogleDriveService::class);
    }

    private function configure(): void
    {
        $this->svc()->set('client_id', 'cid.apps.googleusercontent.com');
        $this->svc()->set('client_secret', 'secret');
    }

    public function test_state_flags(): void
    {
        $this->assertFalse($this->svc()->isConfigured());
        $this->configure();
        $this->assertTrue($this->svc()->isConfigured());
        $this->assertFalse($this->svc()->isConnected());

        $this->svc()->set('refresh_token', 'rtok');
        $this->assertTrue($this->svc()->isConnected());
        $this->assertFalse($this->svc()->isEnabled());   // enabled جدا باید روشن شود

        $this->svc()->set('enabled', '1');
        $this->assertTrue($this->svc()->isEnabled());
    }

    public function test_auth_url_contains_required_params(): void
    {
        $this->configure();
        $url = $this->svc()->authUrl('https://crm.test/google-drive/callback');

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('client_id=cid', $url);
    }

    public function test_exchange_code_stores_refresh_token_and_email(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'atok', 'refresh_token' => 'rtok', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'me@gmail.com']),
            'www.googleapis.com/drive/v3/files' => Http::response(['id' => 'folder123']),
        ]);

        $this->svc()->exchangeCode('the-code', 'https://crm.test/google-drive/callback');

        $this->assertSame('rtok', Setting::get('gdrive.refresh_token'));
        $this->assertSame('me@gmail.com', Setting::get('gdrive.email'));
        $this->assertSame('folder123', Setting::get('gdrive.folder_id'));
    }

    public function test_exchange_code_rejects_when_drive_scope_missing(): void
    {
        $this->configure();

        // گوگل فقط ایمیل را برگردانده (کاربر تیکِ Drive را نزده)
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'atok', 'refresh_token' => 'rtok', 'expires_in' => 3600,
                'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->svc()->exchangeCode('code', 'https://crm.test/google-drive/callback');
        } finally {
            // اتصالِ ناقص نباید ذخیره شده باشد
            $this->assertFalse($this->svc()->isConnected());
        }
    }

    public function test_upload_uses_resumable_session(): void
    {
        $this->configure();
        $this->svc()->set('refresh_token', 'rtok');
        $this->svc()->set('folder_id', 'folder123');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'atok', 'expires_in' => 3600]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response(null, 200, ['Location' => 'https://upload.session/xyz']),
            'upload.session/*' => Http::response(['id' => 'file999']),
        ]);

        $local = storage_path('app/private/test-upload.txt');
        @mkdir(dirname($local), 0775, true);
        file_put_contents($local, 'hello');

        $id = $this->svc()->upload($local, 'db-test.sql');
        @unlink($local);

        $this->assertSame('file999', $id);
    }

    public function test_list_files_maps_fields(): void
    {
        $this->configure();
        $this->svc()->set('refresh_token', 'rtok');
        $this->svc()->set('folder_id', 'folder123');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'atok', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files*' => Http::response(['files' => [
                ['id' => 'f1', 'name' => 'db-x.sql', 'size' => '1024', 'modifiedTime' => '2026-01-01T10:00:00Z'],
            ]]),
        ]);

        $files = $this->svc()->listFiles();

        $this->assertCount(1, $files);
        $this->assertSame('f1', $files[0]['id']);
        $this->assertSame(1024, $files[0]['size']);
    }

    public function test_page_access_admin_only(): void
    {
        $staff = User::create(['name' => 's', 'email' => 's1@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);
        $this->actingAs($staff);
        $this->assertFalse(GoogleDriveBackup::canAccess());

        $admin = User::create(['name' => 'a', 'email' => 'a@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);
        $this->assertTrue(GoogleDriveBackup::canAccess());

        Livewire::test(GoogleDriveBackup::class)->assertOk();
    }

    public function test_connected_page_shows_inline_disconnect_and_refresh_and_disconnect_works(): void
    {
        $admin = User::create(['name' => 'a', 'email' => 'a@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        $this->configure();
        $this->svc()->set('refresh_token', 'rtok');
        $this->svc()->set('email', 'me@gmail.com');

        Livewire::test(GoogleDriveBackup::class)
            ->assertOk()
            ->assertSee(__('gdrive.disconnect'))
            ->assertSee(__('gdrive.refresh'))
            ->callAction('disconnect');

        $this->assertFalse($this->svc()->isConnected());
    }

    public function test_save_credentials_via_action(): void
    {
        $admin = User::create(['name' => 'a', 'email' => 'a@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $this->actingAs($admin);

        Livewire::test(GoogleDriveBackup::class)
            ->callAction('credentials', ['client_id' => 'abc', 'client_secret' => 'xyz']);

        $this->assertSame('abc', Setting::get('gdrive.client_id'));
        $this->assertTrue($this->svc()->isConfigured());
    }
}
