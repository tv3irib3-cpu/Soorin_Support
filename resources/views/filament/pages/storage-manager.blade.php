<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('storage.label') }}</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('storage.intro') }}</p>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 dark:text-gray-400 text-start">
                        <th class="py-2 pe-3 text-start font-medium">{{ __('storage.category') }}</th>
                        <th class="py-2 px-3 text-center font-medium">{{ __('storage.files_count') }}</th>
                        <th class="py-2 px-3 text-center font-medium">{{ __('storage.size') }}</th>
                        <th class="py-2 px-3 text-start font-medium">{{ __('storage.path') }}</th>
                        <th class="py-2 ps-3 text-center font-medium">{{ __('storage.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($summary as $row)
                        <tr>
                            <td class="py-3 pe-3 align-top">
                                <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $row['label'] }}</div>
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" style="max-width:30rem">{{ $row['description'] }}</div>
                                <div class="mt-1">
                                    <x-filament::badge :color="$row['in_webroot'] ? 'warning' : 'gray'" size="sm">
                                        {{ $row['in_webroot'] ? __('storage.in_webroot') : __('storage.in_storage') }}
                                    </x-filament::badge>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-center align-top tabular-nums">
                                {{ \App\Support\Jalali::digits((string) $row['count']) }}
                            </td>
                            <td class="py-3 px-3 text-center align-top tabular-nums">
                                {{ \App\Support\Jalali::digits($row['human']) }}
                            </td>
                            <td class="py-3 px-3 align-top">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800" dir="ltr">{{ $row['relative'] }}</code>
                                <div class="mt-1 font-mono text-[11px] text-gray-400" dir="ltr">{{ $row['absolute'] }}</div>
                            </td>
                            <td class="py-3 ps-3 text-center align-top">
                                @if ($row['count'] > 0)
                                    <x-filament::button tag="a" href="{{ route('storage.export', $row['key']) }}" size="sm" icon="heroicon-o-arrow-down-tray" color="gray">
                                        {{ __('storage.download_zip') }}
                                    </x-filament::button>
                                @else
                                    <span class="text-xs text-gray-400">{{ __('storage.empty_category') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- راهنمای جابه‌جاییِ هاست --}}
    <x-filament::section collapsible>
        <x-slot name="heading">{{ __('storage.migration_title') }}</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('storage.migration_intro') }}</p>
        <ol class="mt-3 list-decimal space-y-2 pe-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('storage.migration_db') }}</li>
            <li>{{ __('storage.migration_files') }}</li>
            <li>{{ __('storage.migration_env') }}</li>
        </ol>
        <p class="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
            {{ __('storage.migration_skip') }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
