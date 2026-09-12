<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\RelationManagers\PaymentsRelationManager;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsRelationEditableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_payments_relation_is_editable_for_staff_with_manage_payments(): void
    {
        $staff = User::create(['name' => 'S', 'email' => 'staff', 'password' => 'password', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        $staff->assignRole(User::TYPE_SUPPORT_STAFF);

        $this->actingAs($staff);

        $rm = new PaymentsRelationManager();
        $this->assertFalse($rm->isReadOnly(), 'کاربرِ دارای مجوزِ ثبتِ پرداخت باید بتواند پرداخت اضافه کند');
    }

    public function test_payments_relation_is_read_only_without_permission(): void
    {
        $customer = User::create(['name' => 'C', 'email' => 'cust', 'password' => 'password', 'user_type' => User::TYPE_CUSTOMER_ADMIN]);
        $customer->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $this->actingAs($customer);

        $rm = new PaymentsRelationManager();
        $this->assertTrue($rm->isReadOnly(), 'کاربرِ بدونِ مجوز نباید بتواند پرداخت ثبت کند');
    }
}
