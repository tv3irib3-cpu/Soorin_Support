<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerProjects\CustomerProjectResource;
use App\Filament\Resources\CustomerProjects\Pages\CreateCustomerProject;
use App\Filament\Resources\CustomerProjects\Pages\ListCustomerProjects;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u;
    }

    private function staff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 's1@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $u->assignRole(User::TYPE_SUPPORT_STAFF);

        return $u;
    }

    public function test_list_page_loads_and_shows_projects_across_customers(): void
    {
        $aria  = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $other = Customer::create(['code' => 'OTH', 'name' => 'دیگر']);
        $aria->projects()->create(['code' => 'BUS', 'name' => 'بوشهر']);
        $other->projects()->create(['code' => 'CHB', 'name' => 'چابهار']);

        $this->actingAs($this->admin());

        Livewire::test(ListCustomerProjects::class)
            ->assertOk()
            ->assertSee('بوشهر')
            ->assertSee('چابهار');
    }

    public function test_create_project_with_selected_customer(): void
    {
        $aria = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $this->actingAs($this->admin());

        Livewire::test(CreateCustomerProject::class)
            ->fillForm([
                'customer_id' => $aria->id,
                'code'        => 'BUS',
                'name'        => 'بوشهر',
                'is_active'   => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customer_projects', [
            'customer_id' => $aria->id,
            'code'        => 'BUS',
            'name'        => 'بوشهر',
        ]);
    }

    public function test_create_requires_customer(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCustomerProject::class)
            ->fillForm(['code' => 'X', 'name' => 'بی‌مشتری'])
            ->call('create')
            ->assertHasFormErrors(['customer_id']);
    }

    public function test_permissions_view_vs_manage(): void
    {
        $this->actingAs($this->staff());
        $this->assertTrue(CustomerProjectResource::canViewAny());   // کارشناس فقط می‌بیند
        $this->assertFalse(CustomerProjectResource::canCreate());

        $this->actingAs($this->admin());
        $this->assertTrue(CustomerProjectResource::canCreate());    // مدیر می‌سازد
    }

    public function test_customer_user_cannot_view(): void
    {
        $c = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
        $cu = User::create(['name' => 'م', 'email' => 'cu', 'password' => 'secret123', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $c->id]);
        $cu->assignRole(User::TYPE_CUSTOMER_ADMIN);
        $this->actingAs($cu);

        $this->assertFalse(CustomerProjectResource::canViewAny());
    }
}
