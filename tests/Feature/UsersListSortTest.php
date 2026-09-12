<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_list_loads_with_default_grouped_sort(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create(['name' => 'مدیر', 'email' => 'admin', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_ADMIN, 'is_active' => true]);
        $admin->assignRole(User::TYPE_SUPPORT_ADMIN);

        $c = Customer::create(['code' => 'C1', 'name' => 'مشتری', 'entity_type' => 'company']);
        User::create(['name' => 'ک', 'email' => 'st', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_STAFF, 'is_active' => true]);
        User::create(['name' => 'مم', 'email' => 'ca', 'password' => 'password', 'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $c->id, 'is_active' => true]);
        User::create(['name' => 'کک', 'email' => 'cs', 'password' => 'password', 'user_type' => User::TYPE_CUSTOMER_STAFF, 'customer_id' => $c->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(UserResource::getUrl('index'))
            ->assertSuccessful();
    }
}
