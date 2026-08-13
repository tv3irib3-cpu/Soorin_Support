<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * صفحات خطا باید با ظاهر سامانه باشند، نه متن پیش‌فرض لاراول.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_uses_persian_custom_view(): void
    {
        $response = $this->get('/this-route-does-not-exist-at-all');

        $response->assertNotFound();
        $response->assertSee(__('errors.404.title'));
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('dpst.ir');
    }

    public function test_403_page_uses_persian_custom_view(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customerA = Customer::create(['code' => 'A', 'name' => 'مشتری الف']);
        $customerB = Customer::create(['code' => 'B', 'name' => 'مشتری ب']);

        $invoice = Invoice::create([
            'number' => 'F-1', 'customer_id' => $customerB->id, 'issue_date' => now(),
        ]);

        $outsider = User::create([
            'name' => 'کاربر', 'email' => 'x@a.test', 'password' => 'secret123',
            'user_type' => User::TYPE_CUSTOMER_ADMIN, 'customer_id' => $customerA->id,
        ]);
        $outsider->assignRole(User::TYPE_CUSTOMER_ADMIN);

        $response = $this->actingAs($outsider)->get(route('invoices.pdf.view', $invoice));

        $response->assertForbidden();
        $response->assertSee(__('errors.403.title'));
    }
}
