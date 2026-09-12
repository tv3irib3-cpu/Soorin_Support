<x-filament-panels::page>
    <form wire:submit.prevent="applyPreset">
        {{ $this->form }}
    </form>

    @php
        $r = $this->report;
        $s = $r['summary'] ?? [];
        $money  = fn ($v) => \App\Support\Jalali::money($v ?? 0);
        $digits = fn ($v) => \App\Support\Jalali::digits((string) ($v ?? 0));
    @endphp

    {{-- تاریخ و ساعتِ تهیهٔ گزارش + بازهٔ انتخابی --}}
    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('reports.generated_at') }}: <span class="font-medium">{{ \App\Support\Jalali::formatDateTime(now()) }}</span>
        @if (!empty($r['from']) && !empty($r['to']))
            &nbsp;·&nbsp; {{ __('reports.period', ['from' => \App\Support\Jalali::format($r['from']), 'to' => \App\Support\Jalali::format($r['to'])]) }}
        @endif
    </div>

    {{-- ردیف‌های یک‌درمیانِ رنگی برای خواناترشدنِ جدول‌های گزارش. --}}
    <style>
        .rep tbody tr:nth-child(odd) { background: rgba(148,163,184,.08); }
        .rep td, .rep th { padding: 8px 10px; text-align: center; }   /* همهٔ سلول‌ها وسط‌چین */
        .rep th { background: rgba(15,45,77,.06); }
        .dark .rep th { background: rgba(255,255,255,.06); }
        .rep .rownum { color: #64748b; font-variant-numeric: tabular-nums; text-align: center; width: 3rem; }
    </style>

    {{-- ---------- کارت‌های خلاصه ---------- --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.tickets_created') }}</div>
            <div class="text-xl font-bold">{{ $digits($s['tickets_created'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.service_count') }}</div>
            <div class="text-xl font-bold">{{ $digits($s['service_count'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.tickets_still_open') }}</div>
            <div class="text-xl font-bold">{{ $digits($s['tickets_still_open'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.avg_resolution_hours') }}</div>
            <div class="text-xl font-bold">{{ ($s['avg_resolution_hours'] ?? null) !== null ? $digits($s['avg_resolution_hours']) : '—' }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.revenue') }}</div>
            <div class="text-xl font-bold">{{ $money($s['revenue'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.paid') }}</div>
            <div class="text-xl font-bold" style="color:#16a34a">{{ $money($s['paid'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.debt') }}</div>
            <div class="text-xl font-bold" style="color:#dc2626">{{ $money($s['debt'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.service_value') }}</div>
            <div class="text-xl font-bold">{{ $money($s['service_value'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.warranty_value') }}</div>
            <div class="text-xl font-bold">{{ $money($s['warranty_value'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.sla_breaches') }}</div>
            <div class="text-xl font-bold">{{ $digits($s['sla_breaches'] ?? 0) }}</div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.work_minutes') }}</div>
            <div class="text-lg font-bold">{{ $digits($s['work_minutes'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.invoice_count') }}</div>
            <div class="text-lg font-bold">{{ $digits($s['invoice_count'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.avg_rating') }}</div>
            <div class="text-lg font-bold">{{ ($s['avg_rating'] ?? null) ? \App\Support\Jalali::digits(number_format($s['avg_rating'], 1)) . ' / ۵' : '—' }}</div>
        </x-filament::section>
    </div>

    {{-- ---------- خدمات و تیکت هر مشتری ---------- --}}
    <x-filament::section :heading="__('reports.by_customer')">
        @if (empty($r['by_customer']) || count($r['by_customer']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="rep w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="rownum">{{ __('reports.col_row') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_customer') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_created') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_tickets') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_minutes') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_service') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_total') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_paid') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_debt') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_warranty') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['by_customer'] as $row)
                        <tr class="border-b">
                            <td class="rownum">{{ $digits($loop->iteration) }}</td>
                            <td class="p-2">
                                <span style="display:inline-flex;align-items:center;gap:7px;justify-content:center;">
                                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $row['color'] ?? '#94a3b8' }};flex:none;"></span>
                                    {{ $row['customer'] }}
                                </span>
                            </td>
                            <td class="p-2">{{ $digits($row['created'] ?? 0) }}</td>
                            <td class="p-2">{{ $digits($row['tickets']) }}</td>
                            <td class="p-2">{{ $digits($row['minutes']) }}</td>
                            <td class="p-2">{{ $money($row['service'] ?? 0) }}</td>
                            <td class="p-2">{{ $money($row['total'] ?? 0) }}</td>
                            <td class="p-2">{{ $money($row['paid'] ?? 0) }}</td>
                            <td class="p-2" style="color:{{ ($row['debt'] ?? 0) > 0 ? '#dc2626' : 'inherit' }};font-weight:{{ ($row['debt'] ?? 0) > 0 ? '700' : '400' }};">{{ $money($row['debt'] ?? 0) }}</td>
                            <td class="p-2">{{ $money($row['warranty']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ---------- تیکت به تفکیک پروژه ---------- --}}
    <x-filament::section :heading="__('reports.by_project')">
        @if (empty($r['by_project']) || count($r['by_project']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="rep w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="p-2 text-right">{{ __('reports.col_project') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_customer') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_created') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_resolved') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['by_project'] as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['project'] }}</td>
                            <td class="p-2">{{ $row['customer'] }}</td>
                            <td class="p-2">{{ $digits($row['created']) }}</td>
                            <td class="p-2">{{ $digits($row['resolved']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ---------- وضعیت و اولویت کنار هم ---------- --}}
    <div class="grid gap-4 md:grid-cols-2">
        <x-filament::section :heading="__('reports.by_status')">
            @if (empty($r['by_status']) || count($r['by_status']) === 0)
                <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
            @else
                <table class="rep w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 border-b">
                            <th class="p-2 text-right">{{ __('reports.col_status') }}</th>
                            <th class="p-2 text-right">{{ __('reports.col_count') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['by_status'] as $row)
                            <tr class="border-b">
                                <td class="p-2">{{ $row['label'] }}</td>
                                <td class="p-2">{{ $digits($row['count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('reports.by_priority')">
            @if (empty($r['by_priority']) || count($r['by_priority']) === 0)
                <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
            @else
                <table class="rep w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 border-b">
                            <th class="p-2 text-right">{{ __('reports.col_priority') }}</th>
                            <th class="p-2 text-right">{{ __('reports.col_count') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['by_priority'] as $row)
                            <tr class="border-b">
                                <td class="p-2">{{ $row['label'] }}</td>
                                <td class="p-2">{{ $digits($row['count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    </div>

    {{-- ---------- دسته‌بندی خرابی ---------- --}}
    <x-filament::section :heading="__('reports.by_category')">
        @if (empty($r['by_category']) || count($r['by_category']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="rep w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="p-2 text-right">{{ __('reports.col_category') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_count') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['by_category'] as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['category'] }}</td>
                            <td class="p-2">{{ $digits($row['count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ---------- عملکرد کارشناسان ---------- --}}
    <x-filament::section :heading="__('reports.by_staff')">
        @if (empty($r['by_staff']) || count($r['by_staff']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="rep w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="rownum">{{ __('reports.col_row') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_staff') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_resolved') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_response') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['by_staff'] as $row)
                        <tr class="border-b">
                            <td class="rownum">{{ $digits($loop->iteration) }}</td>
                            <td class="p-2">{{ $row['staff'] }}</td>
                            <td class="p-2">{{ $digits($row['resolved']) }}</td>
                            <td class="p-2">{{ $row['avg_response_hr'] !== null ? $digits($row['avg_response_hr']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
