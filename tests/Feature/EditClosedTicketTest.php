<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_support_admin_can_set_any_status_on_a_closed_ticket(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'A', 'email' => 'admin2@t.test', 'password' => 'secret123',
            'user_type' => User::TYPE_SUPPORT_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $customer = Customer::create(['code' => 'C2', 'name' => 'م', 'entity_type' => 'company']);
        $ticket = Ticket::create([
            'customer_id' => $customer->id, 'subject' => 's', 'description' => 'd', 'created_by' => $admin->id,
        ]);
        $ticket->forceFill(['status' => 'closed', 'is_locked' => true, 'closed_at' => now()])->save();

        $this->actingAs($admin);

        // مدیر می‌تواند مستقیم هر وضعیتی بگذارد — نه فقط «در حال بررسی»
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction('changeStatus', ['status' => Ticket::STATUS_WAITING_CUSTOMER])
            ->assertHasNoActionErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_WAITING_CUSTOMER, $ticket->status);
        $this->assertFalse($ticket->is_locked, 'بازکردنِ تیکتِ بسته باید قفل را بردارد');
    }
}
