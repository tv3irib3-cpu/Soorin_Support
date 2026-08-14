{{-- خروجی PDF گزارش — راست‌چین، فونت وزیرمتن، افقی چون جدول‌ها ستون زیاد دارند. --}}
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<style>
    body { font-family: vazirmatn; font-size: 10pt; color: #0b2b3f; direction: rtl; }
    table { width: 100%; border-collapse: collapse; }
    .company-name { font-size: 13pt; font-weight: bold; color: #0f2d4d; }
    .report-title { font-size: 14pt; font-weight: bold; color: #0f766e; text-align: left; }
    .period { font-size: 9.5pt; color: #5f7d8c; text-align: left; }
    .divider { border-top: 2px solid #0f2d4d; margin: 6px 0 10px; }
    .summary-table td { padding: 6px 10px; font-size: 10pt; border: 1px solid #dde8ec; }
    .summary-table .label { color: #5f7d8c; background: #eef4f6; }
    .summary-table .value { font-weight: bold; }
    .section-title { font-size: 11.5pt; font-weight: bold; color: #0f2d4d; margin-top: 16px; margin-bottom: 6px; }
    .data-table th { background: #0f2d4d; color: #fff; padding: 5px 8px; font-size: 9pt; text-align: right; }
    .data-table td { padding: 5px 8px; font-size: 9pt; border-bottom: 1px solid #dde8ec; }
    .data-table .num { text-align: left; direction: ltr; }
    .footer-note { margin-top: 20px; font-size: 8pt; color: #5f7d8c; text-align: center; }
</style>
</head>
<body>

    <table>
        <tr>
            <td style="width: 55%;"><div class="company-name">{{ $company['name'] }}</div></td>
            <td style="width: 45%;">
                <div class="report-title">{{ __('reports.pdf_title') }}</div>
                <div class="period">{{ __('reports.period', ['from' => $date($report['from']), 'to' => $date($report['to'])]) }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    <table class="summary-table">
        <tr>
            <td class="label">{{ __('reports.revenue') }}</td>
            <td class="value">{{ $money($report['summary']['revenue']) }} {{ __('common.currency') }}</td>
            <td class="label">{{ __('reports.warranty_value') }}</td>
            <td class="value">{{ $money($report['summary']['warranty_value']) }} {{ __('common.currency') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('reports.service_count') }}</td>
            <td class="value">{{ $digits($report['summary']['service_count']) }}</td>
            <td class="label">{{ __('reports.work_minutes') }}</td>
            <td class="value">{{ $digits($report['summary']['work_minutes']) }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('reports.avg_rating') }}</td>
            <td class="value" colspan="3">{{ $report['summary']['avg_rating'] ? $digits(round($report['summary']['avg_rating'], 1)) . ' / ۵' : '—' }}</td>
        </tr>
    </table>

    <div class="section-title">{{ __('reports.by_customer') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('reports.col_customer') }}</th>
            <th>{{ __('reports.col_tickets') }}</th>
            <th>{{ __('reports.col_minutes') }}</th>
            <th>{{ __('reports.col_invoiced') }}</th>
            <th>{{ __('reports.col_warranty') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_customer'] as $row)
            <tr>
                <td>{{ $row['customer'] }}</td>
                <td class="num">{{ $digits($row['tickets']) }}</td>
                <td class="num">{{ $digits($row['minutes']) }}</td>
                <td class="num">{{ $money($row['invoiced']) }}</td>
                <td class="num">{{ $money($row['warranty']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('reports.by_category') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('reports.col_category') }}</th>
            <th>{{ __('reports.col_count') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_category'] as $row)
            <tr>
                <td>{{ $row['category'] }}</td>
                <td class="num">{{ $digits($row['count']) }}</td>
            </tr>
        @empty
            <tr><td colspan="2">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('reports.by_staff') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('reports.col_staff') }}</th>
            <th>{{ __('reports.col_resolved') }}</th>
            <th>{{ __('reports.col_response') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_staff'] as $row)
            <tr>
                <td>{{ $row['staff'] }}</td>
                <td class="num">{{ $digits($row['resolved']) }}</td>
                <td class="num">{{ $row['avg_response_hr'] !== null ? $digits($row['avg_response_hr']) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="3">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer-note">{{ $company['name'] }} · {{ $company['website_label'] }}</div>

</body>
</html>
