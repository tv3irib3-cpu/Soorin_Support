<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * دسترسیِ کاربرِ پشتیبان: پیش‌فرضِ نقش، و سفارشی‌سازیِ به‌تفکیکِ کاربر توسطِ مدیر.
 *
 * قاعدهٔ حیاتی: کاربرِ «غیرسفارشی» باید دقیقاً مثلِ قبل از نقش پیروی کند (بدونِ
 * تغییر)، و مدیر باید بتواند برای کاربرِ سفارشی هم مجوز اضافه کند و هم بردارد.
 */
class UserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function staff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 's@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF]);
        // hookِ مدل خودش نقش را ست می‌کند؛ برای اطمینان صریح هم می‌زنیم.
        $u->assignRole(User::TYPE_SUPPORT_STAFF);

        return $u->fresh();
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'a@t.test', 'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_ADMIN]);
        $u->assignRole(User::TYPE_SUPPORT_ADMIN);

        return $u->fresh();
    }

    /** کاربرِ غیرسفارشی: دقیقاً پیش‌فرضِ نقش (رفتارِ قدیم، بدون تغییر). */
    public function test_non_customized_staff_follows_role_defaults(): void
    {
        $staff = $this->staff();

        $this->assertFalse($staff->permissions_customized);
        $this->assertTrue($staff->can(Permission::ViewTickets->value));    // جزوِ پیش‌فرض
        $this->assertTrue($staff->can(Permission::ManageInvoices->value)); // جزوِ پیش‌فرض
        $this->assertFalse($staff->can(Permission::ManageSettings->value)); // خارج از پیش‌فرض
        $this->assertFalse($staff->can(Permission::RestoreBackups->value)); // خارج از پیش‌فرض
    }

    public function test_non_customized_admin_has_everything(): void
    {
        $admin = $this->admin();

        foreach (Permission::values() as $perm) {
            $this->assertTrue($admin->can($perm), "مدیر باید {$perm} را داشته باشد");
        }
    }

    /** سفارشی‌سازی: مدیر می‌تواند مجوزِ پیش‌فرض را بردارد. */
    public function test_admin_can_revoke_a_default_permission(): void
    {
        $staff = $this->staff();

        // فقط «مشاهده تیکت» را نگه دار — «ثبت تیکت» و «مدیریت تیکت» که پیش‌فرض بودند حذف شوند.
        $staff->forceFill(['permissions_customized' => true])->save();
        $staff->syncPermissions([Permission::ViewTickets->value]);
        $staff = $staff->fresh();

        $this->assertTrue($staff->can(Permission::ViewTickets->value));
        $this->assertFalse($staff->can(Permission::CreateTickets->value));  // پیش‌فرض بود، حذف شد
        $this->assertFalse($staff->can(Permission::ManageTickets->value));  // پیش‌فرض بود، حذف شد
        $this->assertFalse($staff->can(Permission::ManageInvoices->value)); // پیش‌فرض بود، حذف شد
    }

    /** سفارشی‌سازی: مدیر می‌تواند مجوزِ خارج از پیش‌فرض را اضافه کند. */
    public function test_admin_can_grant_an_extra_permission(): void
    {
        $staff = $this->staff();

        $staff->forceFill(['permissions_customized' => true])->save();
        $staff->syncPermissions([Permission::ViewTickets->value, Permission::ManageSettings->value]);
        $staff = $staff->fresh();

        $this->assertTrue($staff->can(Permission::ManageSettings->value)); // خارج از پیش‌فرض، اضافه شد
        $this->assertTrue($staff->can(Permission::ViewTickets->value));
        $this->assertFalse($staff->can(Permission::CreateTickets->value)); // انتخاب نشده
    }

    /** ویرایشِ کاربرِ پشتیبان از فرم، دسترسی را «مستقیم» و سفارشی می‌کند. */
    public function test_edit_form_syncs_direct_permissions(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm([
                'permissions' => [Permission::ViewTickets->value, Permission::ViewReports->value],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff = $staff->fresh();

        $this->assertTrue($staff->permissions_customized);
        $this->assertTrue($staff->can(Permission::ViewTickets->value));
        $this->assertTrue($staff->can(Permission::ViewReports->value));
        $this->assertFalse($staff->can(Permission::ManageTickets->value)); // پیش‌فرض بود، در فرم انتخاب نشد → حذف
        $this->assertFalse($staff->can(Permission::ManageSettings->value));
    }

    /** ساختِ کارشناس از فرم با انتخابِ مجوز، دسترسی را مستقیم و سفارشی می‌کند. */
    public function test_create_form_customizes_with_selected_permissions(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name'        => 'کارشناسِ نو',
                'email'       => 'new@t.test',
                'password'    => 'secret123',
                'user_type'   => User::TYPE_SUPPORT_STAFF,
                'permissions' => [Permission::ViewTickets->value],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'new@t.test')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->permissions_customized);
        $this->assertTrue($created->can(Permission::ViewTickets->value));
        $this->assertFalse($created->can(Permission::ManageTickets->value));   // انتخاب نشد
        $this->assertFalse($created->can(Permission::ManageSettings->value));
    }

    /** فرم باید وضعیتِ پیش‌فرضِ هر مجوز را کنارِ آن بنویسد (راهنما). */
    public function test_create_form_shows_default_markers(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->assertSee(__('users.perm_default_on'))
            ->assertSee(__('users.perm_default_off'))
            ->assertSee(Permission::RestoreBackups->label());   // «بازیابی از پشتیبان» در فهرست هست
    }

    /** مدیرِ واردشده هنگام ویرایشِ حسابِ خودش سفارشی نمی‌شود (جلوگیری از قفل‌شدن). */
    public function test_admin_editing_self_is_not_customized(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['name' => 'مدیرِ ویرایش‌شده'])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin = $admin->fresh();

        $this->assertFalse($admin->permissions_customized);
        $this->assertTrue($admin->can(Permission::ViewUsers->value));      // هنوز همه‌چیز را دارد
        $this->assertTrue($admin->can(Permission::ManageSettings->value));
    }
}
