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
    .data-table th { background: #0f2d4d; color: #fff; padding: 5px 8px; font-size: 9pt; text-align: center; vertical-align: middle; }
    .data-table td { padding: 5px 8px; font-size: 9pt; border-bottom: 1px solid #dde8ec; text-align: center; vertical-align: middle; }
    .data-table .num { text-align: center; direction: ltr; }
    .summary-table .value { text-align: center; }
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
                <div class="period">{{ __('reports.generated_at') }}: {{ \App\Support\Jalali::formatDateTime(now()) }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    <table class="summary-table">
        <tr>
            <td class="label">{{ __('reports.revenue') }}</td>
            <td class="value">{{ $money($report['summary']['revenue']) }} {{ __('common.currency') }}</td>
            <td class="label">{{ __('reports.service_value') }}</td>
            <td class="value">{{ $money($report['summary']['service_value'] ?? 0) }} {{ __('common.currency') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('reports.paid') }}</td>
            <td class="value">{{ $money($report['summary']['paid'] ?? 0) }} {{ __('common.currency') }}</td>
            <td class="label">{{ __('reports.debt') }}</td>
            <td class="value">{{ $money($report['summary']['debt'] ?? 0) }} {{ __('common.currency') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('reports.warranty_value') }}</td>
            <td class="value">{{ $money($report['summary']['warranty_value']) }} {{ __('common.currency') }}</td>
            <td class="label">{{ __('reports.service_count') }}</td>
            <td class="value">{{ $digits($report['summary']['service_count']) }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('reports.work_minutes') }}</td>
            <td class="value">{{ $digits($report['summary']['work_minutes']) }}</td>
            <td class="label">{{ __('reports.avg_rating') }}</td>
            <td class="value">{{ $report['summary']['avg_rating'] ? $digits(round($report['summary']['avg_rating'], 1)) . ' / ۵' : '—' }}</td>
        </tr>
    </table>

    <div class="section-title">{{ __('reports.by_customer') }}</div>
    <table class="data-table">
        <thead><tr>
            <th class="num">{{ __('reports.col_row') }}</th>
            <th>{{ __('reports.col_customer') }}</th>
            <th>{{ __('reports.col_tickets') }}</th>
            <th>{{ __('reports.col_minutes') }}</th>
            <th>{{ __('reports.col_service') }}</th>
            <th>{{ __('reports.col_total') }}</th>
            <th>{{ __('reports.col_paid') }}</th>
            <th>{{ __('reports.col_debt') }}</th>
            <th>{{ __('reports.col_warranty') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_customer'] as $row)
            <tr>
                <td class="num">{{ $digits($loop->iteration) }}</td>
                <td>{{ $row['customer'] }}</td>
                <td class="num">{{ $digits($row['tickets']) }}</td>
                <td class="num">{{ $digits($row['minutes']) }}</td>
                <td class="num">{{ $money($row['service'] ?? 0) }}</td>
                <td class="num">{{ $money($row['total'] ?? 0) }}</td>
                <td class="num">{{ $money($row['paid'] ?? 0) }}</td>
                <td class="num">{{ $money($row['debt'] ?? 0) }}</td>
                <td class="num">{{ $money($row['warranty']) }}</td>
            </tr>
        @empty
            <tr><td colspan="9">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('reports.by_category') }}</div>
    <table class="data-table">
        <thead><tr>
            <th class="num">{{ __('reports.col_row') }}</th>
            <th>{{ __('reports.col_category') }}</th>
            <th>{{ __('reports.col_count') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_category'] as $row)
            <tr>
                <td class="num">{{ $digits($loop->iteration) }}</td>
                <td>{{ $row['category'] }}</td>
                <td class="num">{{ $digits($row['count']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('reports.by_staff') }}</div>
    <table class="data-table">
        <thead><tr>
            <th class="num">{{ __('reports.col_row') }}</th>
            <th>{{ __('reports.col_staff') }}</th>
            <th>{{ __('reports.col_resolved') }}</th>
            <th>{{ __('reports.col_response') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_staff'] as $row)
            <tr>
                <td class="num">{{ $digits($loop->iteration) }}</td>
                <td>{{ $row['staff'] }}</td>
                <td class="num">{{ $digits($row['resolved']) }}</td>
                <td class="num">{{ $row['avg_response_hr'] !== null ? $digits($row['avg_response_hr']) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer-note">{{ $company['name'] }} · {{ $company['website_label'] }}</div>

</body>
</html>
