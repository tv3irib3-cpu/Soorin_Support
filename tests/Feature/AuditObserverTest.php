<?php
namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_is_logged_with_from_to(): void
    {
        $customer = Customer::create(['code' => 'X1', 'name' => 'تست']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created', 'subject_type' => Customer::class, 'subject_id' => $customer->id,
        ]);

        $customer->update(['name' => 'تستِ جدید']);
        $log = ActivityLog::where('action', 'updated')->where('subject_id', $customer->id)->first();
        $this->assertNotNull($log, 'updated log missing');
        $this->assertArrayHasKey('name', $log->changes);
        $this->assertSame('تست', $log->changes['name']['from']);
        $this->assertSame('تستِ جدید', $log->changes['name']['to']);

        $customer->delete();
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deleted', 'subject_id' => $customer->id,
        ]);
    }
}
