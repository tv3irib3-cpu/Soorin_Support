<?php

namespace App\Services;

use App\Support\Jalali;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * خروجی اکسل گزارش — چهار برگه: خلاصه، مشتریان، دسته‌بندی، کارشناسان.
 * هر برگه راست‌چین است (متن فارسی در اکسل هم درست نمایش داده شود).
 */
class ReportExcelService
{
    public function build(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->summarySheet($spreadsheet, $report);
        $this->tableSheet(
            $spreadsheet, 'مشتریان', $report['by_customer'],
            ['customer' => 'مشتری', 'created' => 'تیکت ثبت‌شده', 'tickets' => 'تیکت حل‌شده', 'minutes' => 'زمان کارکرد (دقیقه)', 'service' => 'ارزش خدمت (ریال)', 'total' => 'مبلغ کل فاکتور (ریال)', 'paid' => 'پرداختی مشتری (ریال)', 'debt' => 'بدهی مشتری (ریال)', 'warranty' => 'سهم قرارداد/گارانتی (ریال)'],
        );
        $this->tableSheet(
            $spreadsheet, 'پروژه‌ها', $report['by_project'] ?? collect(),
            ['project' => 'پروژه', 'customer' => 'مشتری', 'created' => 'تیکت ثبت‌شده', 'resolved' => 'تیکت حل‌شده'],
        );
        $this->tableSheet(
            $spreadsheet, 'دسته‌بندی', $report['by_category'],
            ['category' => 'دسته‌بندی', 'count' => 'تعداد'],
        );
        $this->tableSheet(
            $spreadsheet, 'وضعیت', $report['by_status'] ?? collect(),
            ['label' => 'وضعیت', 'count' => 'تعداد'],
        );
        $this->tableSheet(
            $spreadsheet, 'کارشناسان', $report['by_staff'],
            ['staff' => 'کارشناس', 'resolved' => 'تیکت حل‌شده', 'avg_response_hr' => 'میانگین پاسخ (ساعت)'],
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function save(array $report, string $path): void
    {
        (new Xlsx($this->build($report)))->save($path);
    }

    private function summarySheet(Spreadsheet $spreadsheet, array $report): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('خلاصه');
        $sheet->setRightToLeft(true);

        $sum = $report['summary'];

        $rows = [
            ['بازه گزارش', Jalali::format($report['from']) . ' تا ' . Jalali::format($report['to'])],
            ['تعداد تیکت ثبت‌شده', $sum['tickets_created'] ?? '—'],
            ['تعداد تیکت حل‌شده', $sum['service_count']],
            ['تیکت‌های هنوز باز', $sum['tickets_still_open'] ?? '—'],
            ['میانگین زمان حل (ساعت)', $sum['avg_resolution_hours'] ?? '—'],
            ['نقض تعهد پاسخ (SLA)', $sum['sla_breaches'] ?? '—'],
            ['مبلغ کل فاکتورها (ریال)', $sum['revenue']],
            ['پرداختی مشتریان (ریال)', $sum['paid'] ?? '—'],
            ['بدهی مشتریان (ریال)', $sum['debt'] ?? '—'],
            ['ارزش کل خدمات صادرشده (ریال)', $sum['service_value'] ?? '—'],
            ['ارزش خدمات رایگان تحت قرارداد (ریال)', $sum['warranty_value']],
            ['تعداد فاکتور', $sum['invoice_count'] ?? '—'],
            ['مجموع زمان کارکرد (دقیقه)', $sum['work_minutes']],
            ['میانگین رضایت (از ۵)', $sum['avg_rating'] ? round($sum['avg_rating'], 2) : '—'],
        ];

        foreach ($rows as $i => [$label, $value]) {
            $sheet->setCellValue('A' . ($i + 1), $label)->setCellValue('B' . ($i + 1), $value);
            $sheet->getStyle('A' . ($i + 1))->getFont()->setBold(true);
        }

        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F2D4D');
        $sheet->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');
    }

    /** @param array<string, string> $headers key => برچسب فارسی */
    private function tableSheet(Spreadsheet $spreadsheet, string $title, iterable $rows, array $headers): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->setRightToLeft(true);

        $columns = array_keys($headers);
        $col = 'A';
        foreach ($headers as $label) {
            $sheet->setCellValue($col . '1', $label);
            $col++;
        }
        $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F2D4D');
        $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')
            ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $col = 'A';
            foreach ($columns as $key) {
                $value = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                $sheet->setCellValue($col . $rowIndex, $value ?? '—');
                $col++;
            }
            $rowIndex++;
        }

        foreach (range('A', chr(ord('A') + count($headers) - 1)) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
