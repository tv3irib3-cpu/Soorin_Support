<?php

namespace App\Services;

use Mpdf\Mpdf;

/**
 * خروجی PDF گزارش — همان تنظیمات فونت وزیرمتن و useOTL که در
 * InvoicePdfService جواب داد (بدون آن، حروف فارسی چسبیده چاپ نمی‌شوند).
 */
class ReportPdfService
{
    public function render(array $report): Mpdf
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4-L', // افقی — جدول‌ها ستون بیشتری دارند
            'default_font'   => 'vazirmatn',
            'directionality' => 'rtl',
            'fontDir'        => array_merge($defaultConfig['fontDir'], [storage_path('fonts/vazirmatn')]),
            'fontdata'       => array_merge($defaultFontConfig['fontdata'], [
                'vazirmatn' => [
                    'R' => 'Vazirmatn-Regular.ttf',
                    'B' => 'Vazirmatn-Bold.ttf',
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ],
            ]),
            'margin_top'    => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->SetTitle(__('reports.pdf_title'));
        $mpdf->WriteHTML(view('pdf.report', [
            'report'  => $report,
            'company' => config('branding.company'),
            'money'   => fn (int $amount) => \App\Support\Jalali::money($amount),
            'date'    => fn ($d) => \App\Support\Jalali::format($d),
            'digits'  => fn ($n) => \App\Support\Jalali::digits((string) $n),
        ])->render());

        return $mpdf;
    }
}
