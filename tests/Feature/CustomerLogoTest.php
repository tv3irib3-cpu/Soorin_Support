<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_without_logo_reports_none(): void
    {
        $customer = Customer::create(['code' => 'C1', 'name' => 'بی‌لوگو']);

        $this->assertFalse($customer->hasLogo());
        $this->assertNull($customer->logoData());
    }

    public function test_customer_logo_returns_base64_data_uri(): void
    {
        Storage::fake(Branding::DISK);

        // یک PNG کوچکِ معتبر (۱×۱ شفاف)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQAY3Z2VAAAAAElFTkSuQmCC');
        Storage::disk(Branding::DISK)->put('customers/logo.png', $png);

        $customer = Customer::create([
            'code' => 'C2', 'name' => 'بالوگو', 'logo_path' => 'customers/logo.png',
        ]);

        $this->assertTrue($customer->hasLogo());
        $data = $customer->logoData();
        $this->assertNotNull($data);
        $this->assertStringStartsWith('data:image/png;base64,', $data);
    }
}
