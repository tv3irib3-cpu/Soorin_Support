<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditClosedTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_admin_can_open_edit_page_of_a_closed_ticket(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'A', 'email' => 'admin@t.test', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $customer = Customer::create(['code' => 'C1', 'name' => 'م', 'entity_type' => 'company']);
        $ticket = Ticket::create([
            'customer_id' => $customer->id, 'subject' => 's', 'description' => 'd', 'created_by' => $admin->id,
        ]);
        // بستن و قفل‌شدن
        $ticket->forceFill(['status' => 'closed', 'is_locked' => true, 'closed_at' => now()])->save();

        $this->actingAs($admin);

        $this->assertTrue(TicketResource::canEdit($ticket->fresh()), 'مدیر باید بتواند تیکتِ بسته را ویرایش کند');

        $this->get(TicketResource::getUrl('edit', ['record' => $ticket]))
            ->assertSuccessful();
    }
}
