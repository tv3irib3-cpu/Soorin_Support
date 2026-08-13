<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\Jalali;
use Mpdf\Mpdf;

/**
 * تولید خروجی PDF فارسی راست‌چین فاکتور با فونت وزیرمتن.
 *
 * mPDF به فایل TTF نیاز دارد (نه woff2 که در وب استفاده می‌شود)، پس یک
 * نسخه جدا در storage/fonts/vazirmatn نگهداری می‌شود.
 */
class InvoicePdfService
{
    public function render(Invoice $invoice): Mpdf
    {
        $invoice->loadMissing(['customer', 'items', 'payments', 'contract.plan', 'ticket']);

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'default_font'  => 'vazirmatn',
            'directionality'=> 'rtl',
            'fontDir'       => array_merge($defaultConfig['fontDir'], [storage_path('fonts/vazirmatn')]),
            'fontdata'      => array_merge($defaultFontConfig['fontdata'], [
                'vazirmatn' => [
                    'R' => 'Vazirmatn-Regular.ttf',
                    'B' => 'Vazirmatn-Bold.ttf',
                ],
            ]),
            'margin_top'    => 15,
            'margin_bottom' => 15,
        ]);

        $mpdf->SetTitle(__('invoices.invoice_title') . ' ' . $invoice->number);
        $mpdf->WriteHTML($this->html($invoice));

        return $mpdf;
    }

    private function html(Invoice $invoice): string
    {
        return view('pdf.invoice', [
            'invoice' => $invoice,
            'company' => config('branding.company'),
            'money'   => fn (int $amount) => Jalali::money($amount),
            'date'    => fn ($d) => Jalali::format($d),
        ])->render();
    }
}
