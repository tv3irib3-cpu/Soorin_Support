<?php

namespace Tests\Feature;

use App\Filament\Widgets\AgentRatingsWidget;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentRatingsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->customer = Customer::create(['code' => 'C1', 'name' => 'آریا']);
    }

    private function rated(int $assignedTo, int $rating): void
    {
        $t = Ticket::create(['customer_id' => $this->customer->id, 'subject' => 's', 'description' => 'd', 'assigned_to' => $assignedTo]);
        $t->forceFill(['rating' => $rating, 'status' => Ticket::STATUS_RESOLVED, 'resolved_at' => now()])->save();
    }

    public function test_admin_sees_overall_and_all_agents(): void
    {
        $admin = User::create(['name' => 'مدیر یک', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);
        $staff = User::create(['name' => 'کارشناس یک', 'email' => 's@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);

        // ۹ تیکت ۵ستاره برای staff، ۱ تیکت ۱ستاره برای admin → میانگینِ کل = (45+1)/10 = 4.6
        for ($i = 0; $i < 9; $i++) {
            $this->rated($staff->id, 5);
        }
        $this->rated($admin->id, 1);

        $this->actingAs($admin);

        Livewire::test(AgentRatingsWidget::class)
            ->assertOk()
            ->assertSee(__('dashboard.overall_company_rating'))
            ->assertSee(\App\Support\Jalali::digits('4.6'))     // میانگینِ سادهٔ کل (فارسی‌رقم)
            ->assertSee(__('dashboard.rating_count', ['count' => \App\Support\Jalali::digits('10')]))
            ->assertSee('مدیر یک')
            ->assertSee('کارشناس یک');
    }

    public function test_staff_sees_only_own_rating(): void
    {
        $admin = User::create(['name' => 'مدیر یک', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $staff = User::create(['name' => 'کارشناس یک', 'email' => 's@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);
        $other = User::create(['name' => 'کارشناس دو', 'email' => 's2@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);

        $this->rated($staff->id, 4);
        $this->rated($other->id, 2);

        $this->actingAs($staff);

        Livewire::test(AgentRatingsWidget::class)
            ->assertOk()
            ->assertSee('کارشناس یک')
            ->assertDontSee('کارشناس دو')                         // امتیازِ دیگران را نمی‌بیند
            ->assertDontSee(__('dashboard.overall_company_rating')); // امتیازِ کلِ شرکت فقط مدیر
    }
}
