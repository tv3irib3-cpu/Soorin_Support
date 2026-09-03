<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ServiceRate;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * داده نمونه برای شروع کار و تست.
 * چندبار قابل اجراست و داده تکراری نمی‌سازد.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // ---------------------------------------------------- کاربرِ مدیرِ اولیه
        // نامِ کاربری «admin» (نه ایمیل) — ورودِ پنل با همین کار می‌کند. رمزِ پیش‌فرض
        // «password» است و باید بلافاصله پس از اولین ورود عوض شود (نصب‌کننده هم
        // رمزِ تصادفی می‌سازد و نشان می‌دهد).
        $admin = User::firstOrCreate(
            ['email' => 'admin'],
            [
                'name'      => 'مدیر سامانه',
                'password'  => 'password',
                'user_type' => User::TYPE_SUPPORT_ADMIN,
            ],
        );
        $admin->syncRoles(User::TYPE_SUPPORT_ADMIN);

        // ------------------------------------------------- دسته‌بندی دولایه
        $categories = [
            'سخت‌افزار' => ['hardware', ['هارد', 'مانیتور', 'کیس و مادربرد', 'ترک‌بال و ورودی', 'شبکه و کابل‌کشی', 'برق و یو‌پی‌اس']],
            'نرم‌افزار' => ['software', ['سیستم‌عامل', 'نرم‌افزار سامانه', 'پایگاه داده', 'تنظیمات و پیکربندی', 'به‌روزرسانی']],
        ];

        foreach ($categories as $parentName => [$type, $children]) {
            $parent = TicketCategory::firstOrCreate(
                ['name' => $parentName, 'parent_id' => null],
                ['service_type' => $type],
            );

            foreach ($children as $i => $childName) {
                TicketCategory::firstOrCreate(
                    ['name' => $childName, 'parent_id' => $parent->id],
                    ['service_type' => $type, 'sort_order' => $i],
                );
            }
        }

        // -------------------------------------------------------- نرخ خدمات
        $rates = [
            ['پشتیبانی تلفنی', 'software', 'phone', 1_500_000, 'مورد'],
            ['پشتیبانی ریموت', 'software', 'remote', 3_000_000, 'مورد'],
            ['اعزام کارشناس — داخل شهر', 'hardware', 'onsite', 8_000_000, 'بازدید'],
            ['اعزام کارشناس — خارج شهر', 'hardware', 'onsite', 20_000_000, 'بازدید'],
            ['تعویض قطعه سخت‌افزاری', 'hardware', 'any', 5_000_000, 'مورد'],
            ['نصب و راه‌اندازی نرم‌افزار', 'software', 'any', 4_000_000, 'مورد'],
        ];

        foreach ($rates as [$title, $type, $method, $price, $unit]) {
            ServiceRate::firstOrCreate(
                ['title' => $title],
                ['service_type' => $type, 'method' => $method, 'base_price' => $price, 'unit' => $unit],
            );
        }

        $this->call(SampleCustomerSeeder::class);
    }
}
