<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('storage.label') }}</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('storage.intro') }}</p>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="soorin-grid">
                <thead>
                    <tr>
                        <th>{{ __('storage.category') }}</th>
                        <th>{{ __('storage.files_count') }}</th>
                        <th>{{ __('storage.size') }}</th>
                        <th>{{ __('storage.location') }}</th>
                        <th>{{ __('storage.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $row)
                        <tr x-data="{ showPath: false }">
                            {{-- دسته: نام (هم‌اندازه) + توضیحِ ریزتر، وسط‌چین --}}
                            <td>
                                <div class="soorin-grid__title">{{ $row['label'] }}</div>
                                <div class="soorin-grid__desc">{{ $row['description'] }}</div>
                            </td>

                            {{-- تعداد --}}
                            <td><span class="soorin-grid__num">{{ \App\Support\Jalali::digits((string) $row['count']) }}</span></td>

                            {{-- حجم --}}
                            <td>{{ \App\Support\Jalali::digits($row['human']) }}</td>

                            {{-- محل: نشان + دکمهٔ «نمایش مسیر» (سبزِ کم‌رنگ) که مسیر را باز می‌کند --}}
                            <td>
                                <div class="soorin-grid__cell">
                                    <x-filament::badge :color="$row['in_webroot'] ? 'warning' : 'gray'" size="sm">
                                        {{ $row['in_webroot'] ? __('storage.in_webroot') : __('storage.in_storage') }}
                                    </x-filament::badge>
                                    <button type="button" x-on:click="showPath = !showPath" class="soorin-pathbtn">
                                        <span x-show="!showPath">{{ __('storage.show_path') }}</span>
                                        <span x-show="showPath" x-cloak>{{ __('storage.hide_path') }}</span>
                                    </button>
                                    <div x-show="showPath" x-collapse x-cloak>
                                        <code class="soorin-code">{{ $row['relative'] }}</code>
                                    </div>
                                </div>
                            </td>

                            {{-- عملیات --}}
                            <td>
                                @if ($row['count'] > 0)
                                    <x-filament::button tag="a" href="{{ route('storage.export', $row['key']) }}" size="sm" icon="heroicon-o-arrow-down-tray" color="gray">
                                        {{ __('storage.download_zip') }}
                                    </x-filament::button>
                                @else
                                    <span style="font-size:12px;color:var(--muted);">{{ __('storage.empty_category') }}</span>
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
