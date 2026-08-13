<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractPlan;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * دو مشتری نمونه — یکی با چند پروژه و قرارداد طلایی، یکی بدون قرارداد.
 * مثال «شرکت آریا» دقیقاً همان سناریویی است که مالک پروژه توصیف کرد.
 */
class SampleCustomerSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------- انواع قرارداد
        $gold = ContractPlan::firstOrCreate(['name' => 'طلایی'], [
            'color'            => '#d4a017',
            'description'      => 'پوشش کامل نرم‌افزاری و اعزام، ۷۰٪ سخت‌افزاری، ۵۰٪ قطعات',
            'cover_software'   => 100,
            'cover_hardware'   => 70,
            'cover_parts'      => 50,
            'cover_onsite'     => 100,
            'ceiling_amount'   => 500_000_000,
            'response_hours'   => 4,
        ]);

        ContractPlan::firstOrCreate(['name' => 'نقره‌ای'], [
            'color'          => '#9aa5b1',
            'cover_software' => 100,
            'cover_hardware' => 50,
            'cover_parts'    => 0,
            'cover_onsite'   => 50,
            'ceiling_amount' => 200_000_000,
            'response_hours' => 8,
        ]);

        ContractPlan::firstOrCreate(['name' => 'برنزی'], [
            'color'          => '#b08d57',
            'cover_software' => 50,
            'cover_hardware' => 0,
            'cover_parts'    => 0,
            'cover_onsite'   => 0,
            'ceiling_amount' => 50_000_000,
            'response_hours' => 24,
        ]);

        // ------------------------------------- مشتری اول: چند پروژه + قرارداد
        $aria = Customer::firstOrCreate(['code' => 'ARIA'], [
            'name'        => 'شرکت آریا',
            'entity_type' => 'company',
            'phone'       => '07733334444',
            'city'        => 'بوشهر',
        ]);

        $projects = [
            ['ARIA-BUS', 'بوشهر', 'بوشهر'],
            ['ARIA-CHB', 'چابهار', 'چابهار'],
            ['ARIA-BND', 'بندرعباس', 'بندرعباس'],
        ];

        foreach ($projects as [$code, $name, $city]) {
            $aria->projects()->firstOrCreate(['code' => $code], [
                'name'       => $name,
                'city'       => $city,
                'start_date' => now()->subYear(),
            ]);
        }

        Contract::firstOrCreate(['number' => 'C-1405-001'], [
            'customer_id'      => $aria->id,
            'contract_plan_id' => $gold->id,
            'start_date'       => now()->subMonths(3),
            'end_date'         => now()->addMonths(9),
            'amount'           => 300_000_000,
        ]);

        // مدیر مشتری — هر سه پروژه را می‌بیند
        $ariaAdmin = User::firstOrCreate(['email' => 'modir@aria.test'], [
            'name'        => 'مدیر شرکت آریا',
            'mobile'      => '09121111111',
            'password'    => 'password',
            'user_type'   => User::TYPE_CUSTOMER_ADMIN,
            'customer_id' => $aria->id,
        ]);
        $ariaAdmin->syncRoles(User::TYPE_CUSTOMER_ADMIN);

        // کارشناس مشتری — فقط پروژه بوشهر، بدون دسترسی به سوابق
        $bushehrStaff = User::firstOrCreate(['email' => 'bushehr@aria.test'], [
            'name'          => 'کارشناس بوشهر',
            'mobile'        => '09122222222',
            'password'      => 'password',
            'user_type'     => User::TYPE_CUSTOMER_STAFF,
            'customer_id'   => $aria->id,
            'history_scope' => 'none',
        ]);
        $bushehrStaff->syncRoles(User::TYPE_CUSTOMER_STAFF);
        $bushehrStaff->projects()->syncWithoutDetaching(
            $aria->projects()->where('code', 'ARIA-BUS')->pluck('id'),
        );

        // کارشناس چابهار — با دسترسی به سوابق پروژه خودش
        $chabaharStaff = User::firstOrCreate(['email' => 'chabahar@aria.test'], [
            'name'          => 'کارشناس چابهار',
            'mobile'        => '09123333333',
            'password'      => 'password',
            'user_type'     => User::TYPE_CUSTOMER_STAFF,
            'customer_id'   => $aria->id,
            'history_scope' => 'project',
        ]);
        $chabaharStaff->syncRoles(User::TYPE_CUSTOMER_STAFF);
        $chabaharStaff->projects()->syncWithoutDetaching(
            $aria->projects()->where('code', 'ARIA-CHB')->pluck('id'),
        );

        // ------------------------------ مشتری دوم: بدون قرارداد، یک پروژه
        $pars = Customer::firstOrCreate(['code' => 'PARS'], [
            'name'        => 'مهندسی پارس دریا',
            'entity_type' => 'company',
            'city'        => 'تهران',
        ]);

        $pars->projects()->firstOrCreate(['code' => 'PARS-MAIN'], [
            'name' => 'دفتر مرکزی',
            'city' => 'تهران',
        ]);

        $parsAdmin = User::firstOrCreate(['email' => 'modir@pars.test'], [
            'name'        => 'مدیر پارس دریا',
            'mobile'      => '09124444444',
            'password'    => 'password',
            'user_type'   => User::TYPE_CUSTOMER_ADMIN,
            'customer_id' => $pars->id,
        ]);
        $parsAdmin->syncRoles(User::TYPE_CUSTOMER_ADMIN);
    }
}
