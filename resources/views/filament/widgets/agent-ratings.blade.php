@php
    $star = function (?float $avg): string {
        $r = (int) round($avg ?? 0);
        $out = '';
        for ($i = 1; $i <= 5; $i++) {
            $out .= $i <= $r ? '★' : '☆';
        }
        return $out;
    };
    $fa = fn ($n) => \App\Support\Jalali::digits((string) $n);
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('dashboard.ratings_title') }}</x-slot>

        {{-- امتیازِ کلیِ شرکت — برای همه (کارشناس و مدیر)، درشت و بالای فهرست --}}
        <div class="rating-overall">
            <div class="rating-overall__label">{{ __('dashboard.overall_company_rating') }}</div>
            @if ($overall !== null)
                <div class="rating-overall__stars">{{ $star($overall) }}</div>
                <div class="rating-overall__num">{{ $fa(number_format($overall, 1)) }} / ۵</div>
                <div class="rating-overall__count">{{ __('dashboard.rating_count', ['count' => $fa($overallCount)]) }}</div>
            @else
                <div class="rating-overall__num">—</div>
            @endif
        </div>

        <div class="overflow-x-auto" style="margin-top: 16px;">
            <table class="soorin-grid">
                <thead>
                    <tr>
                        <th>{{ __('dashboard.agent') }}</th>
                        <th>{{ __('tickets.rating') }}</th>
                        <th>{{ __('dashboard.rating_count_col') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>
                                @if ($row['avg'] !== null)
                                    <span style="color:#f59e0b; font-size:18px; letter-spacing:2px;">{{ $star($row['avg']) }}</span>
                                    <span style="margin-inline-start:6px; font-weight:700;">{{ $fa(number_format($row['avg'], 1)) }}</span>
                                @else
                                    <span style="color:var(--muted);">—</span>
                                @endif
                            </td>
                            <td>{{ $fa($row['count']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="color:var(--muted);">{{ __('dashboard.no_ratings') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
