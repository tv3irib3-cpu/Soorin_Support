<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function excel(Request $request, ReportService $reports, ReportExcelService $excel): Response
    {
        $this->authorizeAccess();

        $report = $reports->generate($this->from($request), $this->to($request));

        $writer = new Xlsx($excel->build($report));

        $tmp = tempnam(sys_get_temp_dir(), 'report');
        $writer->save($tmp);
        $content = file_get_contents($tmp);
        unlink($tmp);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="report-' . now()->format('Ymd') . '.xlsx"',
        ]);
    }

    public function pdf(Request $request, ReportService $reports, ReportPdfService $pdf): Response
    {
        $this->authorizeAccess();

        $report = $reports->generate($this->from($request), $this->to($request));

        return response($pdf->render($report)->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can(Permission::ViewReports->value), 403);
    }

    private function from(Request $request): \Carbon\Carbon
    {
        return $request->filled('from')
            ? \Carbon\Carbon::parse($request->string('from'))
            : now()->startOfMonth();
    }

    private function to(Request $request): \Carbon\Carbon
    {
        return $request->filled('to')
            ? \Carbon\Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();
    }
}
