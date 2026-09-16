<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ویرایشِ تیکت فقط برای مدیرِ پشتیبان است، نه کارشناس.
 */
class TicketEditAccessTest extends TestCase
{
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $c = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $this->ticket = Ticket::create(['customer_id' => $c->id, 'subject' => 's', 'description' => 'd']);
    }

    public function test_support_staff_cannot_edit(): void
    {
        $staff = User::create(['name' => 'کارشناس', 'email' => 's@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);

        $this->actingAs($staff);
        $this->assertFalse(TicketResource::canEdit($this->ticket));
    }

    public function test_support_admin_can_edit(): void
    {
        $admin = User::create(['name' => 'مدیر', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->actingAs($admin);
        $this->assertTrue(TicketResource::canEdit($this->ticket));
    }

    public function test_only_admin_can_delete(): void
    {
        $staff = User::create(['name' => 'کارشناس', 'email' => 's2@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);
        $admin = User::create(['name' => 'مدیر', 'email' => 'a2@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $this->actingAs($staff);
        $this->assertFalse(TicketResource::canDelete($this->ticket));

        $this->actingAs($admin);
        $this->assertTrue(TicketResource::canDelete($this->ticket));
    }

    public function test_delete_is_soft(): void
    {
        // حذفِ تیکت نباید رکورد را واقعاً پاک کند — فقط deleted_at ست شود.
        $this->ticket->delete();

        $this->assertSoftDeleted('tickets', ['id' => $this->ticket->id]);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticket->id]);
    }
}
