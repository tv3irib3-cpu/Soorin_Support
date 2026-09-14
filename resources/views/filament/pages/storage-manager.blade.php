<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('storage.label') }}</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('storage.intro') }}</p>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="px-4 py-3 text-center font-semibold">{{ __('storage.category') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('storage.files_count') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('storage.size') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('storage.location') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('storage.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $row)
                        <tr x-data="{ showPath: false }"
                            class="odd:bg-sky-50/70 hover:bg-sky-100/50 dark:odd:bg-white/[0.03] dark:hover:bg-white/[0.06]">
                            {{-- دسته: نام (هم‌اندازه) + توضیحِ ریزتر، وسط‌چین --}}
                            <td class="px-6 py-6 text-center align-middle">
                                <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $row['label'] }}</div>
                                <div class="mx-auto mt-1 max-w-xs text-center text-[11px] leading-5 text-gray-400 dark:text-gray-500">{{ $row['description'] }}</div>
                            </td>

                            {{-- تعداد --}}
                            <td class="px-6 py-6 text-center align-middle">
                                <span class="text-lg font-bold tabular-nums text-gray-800 dark:text-gray-100">{{ \App\Support\Jalali::digits((string) $row['count']) }}</span>
                            </td>

                            {{-- حجم --}}
                            <td class="px-6 py-6 text-center align-middle tabular-nums">
                                {{ \App\Support\Jalali::digits($row['human']) }}
                            </td>

                            {{-- محل: یک نشان + دکمهٔ «نمایش مسیر» (سبزِ کم‌رنگ) که مسیر را باز می‌کند --}}
                            <td class="px-6 py-6 text-center align-middle">
                                <div class="flex flex-col items-center gap-2">
                                    <x-filament::badge :color="$row['in_webroot'] ? 'warning' : 'gray'" size="sm">
                                        {{ $row['in_webroot'] ? __('storage.in_webroot') : __('storage.in_storage') }}
                                    </x-filament::badge>
                                    <button type="button" x-on:click="showPath = !showPath"
                                            class="text-xs font-medium text-success-500/80 hover:text-success-600 dark:text-success-400/80">
                                        <span x-show="!showPath">{{ __('storage.show_path') }}</span>
                                        <span x-show="showPath" x-cloak>{{ __('storage.hide_path') }}</span>
                                    </button>
                                    <div x-show="showPath" x-collapse x-cloak>
                                        <code class="inline-block rounded bg-gray-100 px-2 py-1 font-mono text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-300" dir="ltr">{{ $row['relative'] }}</code>
                                    </div>
                                </div>
                            </td>

                            {{-- عملیات --}}
                            <td class="px-6 py-6 text-center align-middle">
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

    {{-- پاک‌سازیِ کاملِ داده — توضیحِ دقیقِ آنچه پاک/نگه‌داشته می‌شود، کنارِ دکمه --}}
    <x-filament::section>
        <x-slot name="heading">
            <span class="text-danger-600 dark:text-danger-400">{{ __('storage.reset_section') }}</span>
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('storage.reset_intro') }}</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                <div class="mb-2 text-sm font-semibold text-success-700 dark:text-success-400">✓ {{ __('storage.reset_kept_title') }}</div>
                <ul class="list-disc space-y-1 pe-5 text-xs text-gray-700 dark:text-gray-300">
                    @foreach (__('storage.reset_kept') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-500/30 dark:bg-danger-500/10">
                <div class="mb-2 text-sm font-semibold text-danger-700 dark:text-danger-400">✗ {{ __('storage.reset_deleted_title') }}</div>
                <ul class="list-disc space-y-1 pe-5 text-xs text-gray-700 dark:text-gray-300">
                    @foreach (__('storage.reset_deleted') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ __('storage.reset_note') }}</p>

        <div class="mt-4">
            {{ $this->resetDataAction }}
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
