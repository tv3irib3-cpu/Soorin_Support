<x-filament-panels::page>
    <form wire:submit.prevent="applyPreset">
        {{ $this->form }}
    </form>

    @php
        $r = $this->report;
        $s = $r['summary'] ?? [];
    @endphp

    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.revenue') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::money($s['revenue'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.warranty_value') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::money($s['warranty_value'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.service_count') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::digits((string) ($s['service_count'] ?? 0)) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.work_minutes') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::digits((string) ($s['work_minutes'] ?? 0)) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.avg_rating') }}</div>
            <div class="text-xl font-bold">{{ $s['avg_rating'] ? number_format($s['avg_rating'], 1) . ' / ۵' : '—' }}</div>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('reports.by_customer')">
        @if (empty($r['by_customer']))
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="p-2 text-right">{{ __('reports.col_customer') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_tickets') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_minutes') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_invoiced') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_warranty') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['by_customer'] as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['customer'] }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::digits((string) $row['tickets']) }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::digits((string) $row['minutes']) }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::money($row['invoiced']) }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::money($row['warranty']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reports.by_category')">
        @if (empty($r['by_category']))
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
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
                            <td class="p-2">{{ \App\Support\Jalali::digits((string) $row['count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reports.by_staff')">
        @if (empty($r['by_staff']))
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="p-2 text-right">{{ __('reports.col_staff') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_resolved') }}</th>
                        <th class="p-2 text-right">{{ __('reports.col_response') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['by_staff'] as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['staff'] }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::digits((string) $row['resolved']) }}</td>
                            <td class="p-2">{{ $row['avg_response_hr'] !== null ? \App\Support\Jalali::digits((string) $row['avg_response_hr']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
