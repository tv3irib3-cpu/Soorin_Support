<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * پس از حذفِ تفکیکِ ورودی/خروجی: یک بخشِ واحدِ «تیکت‌ها» هم تیکتِ مشتری و هم
 * تیکتِ ساختهٔ پشتیبان را نشان می‌دهد؛ تیکتِ پشتیبان نشانِ «پشتیبان» می‌گیرد.
 */
class TicketsMergedListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_both_customer_and_support_created_with_badge(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $admin = User::create(['name' => 'مدیرِ پشتیبان', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        // تیکتِ مشتری (بدونِ سازنده = از پرتال)
        $byCustomer = Ticket::create(['customer_id' => $customer->id, 'subject' => 'ساختهٔ مشتری', 'description' => 'd']);

        // تیکتِ ساختهٔ پشتیبان
        $bySupport = Ticket::create(['customer_id' => $customer->id, 'subject' => 'ساختهٔ پشتیبان', 'description' => 'd', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords([$byCustomer, $bySupport])
            ->assertSee(__('tickets.by_support'));   // نشانِ خاکستریِ تیکتِ پشتیبان
    }

    public function test_support_can_create_ticket_and_it_is_marked_as_support_created(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $admin = User::create(['name' => 'مدیرِ پشتیبان', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 's', 'description' => 'd', 'created_by' => $admin->id]);

        $this->assertTrue($ticket->isCreatedBySupport());
    }

    /**
     * کارشناسِ پشتیبان تیکتی که خودش ساخته (هرچند به او تخصیص نیافته) باید در
     * فهرست ببیند و بتواند صفحهٔ نمایشش را باز کند (نه ۴۰۴).
     */
    public function test_support_staff_sees_ticket_they_created_even_if_unassigned(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $staff = User::create(['name' => 'کارشناسِ پشتیبان', 'email' => 's@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);
        $other = User::create(['name' => 'کارشناسِ دیگر', 'email' => 'o@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $other->assignRole(User::TYPE_SUPPORT_STAFF);

        // ساختهٔ خودِ کارشناس ولی تخصیص‌یافته به کسِ دیگر — باز هم باید ببیند (سازنده است).
        $mine = Ticket::create(['customer_id' => $customer->id, 'subject' => 'ساختهٔ من', 'description' => 'd', 'created_by' => $staff->id]);
        $mine->forceFill(['assigned_to' => $other->id])->save();
        // نه ساختهٔ او و نه تخصیص‌یافته به او (به «دیگر» سپرده شده) → نباید ببیند.
        $notMine = Ticket::create(['customer_id' => $customer->id, 'subject' => 'مالِ دیگری', 'description' => 'd']);
        $notMine->forceFill(['assigned_to' => $other->id, 'created_by' => null])->save();

        Livewire::actingAs($staff)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertSee('ساختهٔ من')
            ->assertDontSee('مالِ دیگری');

        // صفحهٔ نمایشِ تیکتِ خودش باز می‌شود (۴۰۴ نمی‌دهد).
        $this->actingAs($staff)
            ->get(\App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $mine]))
            ->assertOk();
    }
}
