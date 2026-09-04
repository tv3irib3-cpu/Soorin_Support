<?php
namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_http_renders(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::create(['name'=>'A','email'=>'admin','password'=>'password','user_type'=>User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        ActivityLog::record('login', $admin);
        ActivityLog::record('updated', $admin, ['name' => ['old' => 'x', 'new' => 'y']]);
        // دادهٔ لبه: action/subject خارج از فهرست، کاربرِ سامانه (null)
        \App\Models\ActivityLog::create(['user_id'=>null,'action'=>'weird_action','subject_type'=>'App\Models\Nonexistent','subject_id'=>5,'changes'=>['a'=>1]]);

        $this->actingAs($admin)->get(ActivityLogResource::getUrl('index'))->assertSuccessful();
    }
}
