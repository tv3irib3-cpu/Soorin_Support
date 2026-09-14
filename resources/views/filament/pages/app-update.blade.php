<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('updates.current_version') }}</x-slot>

        <div class="flex flex-wrap items-center gap-3">
            <span class="text-2xl font-bold" dir="ltr">{{ $status['current'] ?? '—' }}</span>

            @if (($status['method'] ?? null) === 'offline')
                <x-filament::badge color="gray">{{ __('updates.offline_mode') }}</x-filament::badge>
            @endif
        </div>

        {{-- آدرسِ مخزنِ گیت‌هابِ پروژه --}}
        @php
            $repo = rtrim(preg_replace('/\.git$/', '', (string) config('branding.github.repo')), '/');
        @endphp
        @if ($repo !== '')
            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">{{ __('updates.repo') }}:</span>
                <a href="{{ $repo }}" target="_blank" rel="noopener"
                   class="font-mono text-primary-600 underline dark:text-primary-400" dir="ltr">{{ $repo }}</a>
            </div>
        @endif

        @if (! empty($status['checked']))
            <div class="mt-4">
                @if (! empty($status['error']))
                    <p class="text-sm text-warning-600 dark:text-warning-400">
                        {{ __('updates.check_failed') }} — {{ $status['error'] }}
                    </p>
                @elseif (! empty($status['available']))
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                        {{ __('updates.available', ['version' => $status['latest']]) }}
                    </p>
                @else
                    <p class="text-sm font-medium text-success-600 dark:text-success-400">
                        {{ __('updates.up_to_date') }}
                    </p>
                @endif
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('updates.how_it_works') }}</x-slot>

        <ul class="list-disc space-y-1 pe-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('updates.hint_check') }}</li>
            <li>{{ __('updates.hint_git') }}</li>
            <li>{{ __('updates.hint_zip') }}</li>
            <li>{{ __('updates.hint_backup') }}</li>
            <li>{{ __('updates.hint_rollback') }}</li>
        </ul>
    </x-filament::section>

    {{-- راهنمای بازیابیِ دستی — وقتی آپدیتِ خراب، دسترسی به همین صفحه را هم قطع کرده --}}
    @php
        $repo = rtrim(preg_replace('/\.git$/', '', (string) config('branding.github.repo')), '/');
        $releasesUrl = $repo !== '' ? $repo . '/releases' : null;
        $rb = $this->rollback ?? [];
    @endphp
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            <span class="text-warning-600 dark:text-warning-400">{{ __('updates.recover_title') }}</span>
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('updates.recover_intro') }}</p>

        {{-- آخرین نقطهٔ بازگشتِ ثبت‌شده: نام دقیقِ فایل‌هایی که باید استفاده شوند --}}
        <div class="mt-4 rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
            <div class="mb-2 font-semibold text-gray-700 dark:text-gray-200">{{ __('updates.recover_last_point') }}</div>
            @if (filled($rb))
                <dl class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                    <div class="flex justify-between gap-3 sm:block">
                        <dt class="text-gray-500">{{ __('updates.recover_point_version') }}</dt>
                        <dd class="font-semibold" dir="ltr">{{ $rb['version'] ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 sm:block">
                        <dt class="text-gray-500">{{ __('updates.recover_point_at') }}</dt>
                        <dd>{{ isset($rb['at']) ? \App\Support\Jalali::formatDateTime($rb['at']) : '—' }}</dd>
                    </div>
                    @if (filled($rb['snapshot'] ?? null))
                        <div class="flex justify-between gap-3 sm:col-span-2 sm:block">
                            <dt class="text-gray-500">{{ __('updates.recover_point_snapshot') }}</dt>
                            <dd class="font-mono text-xs" dir="ltr">storage/app/rollback/{{ basename($rb['snapshot']) }}</dd>
                        </div>
                    @endif
                    @if (filled($rb['backup'] ?? null))
                        <div class="flex justify-between gap-3 sm:col-span-2 sm:block">
                            <dt class="text-gray-500">{{ __('updates.recover_point_backup') }}</dt>
                            <dd class="font-mono text-xs" dir="ltr">storage/app/private/backups/{{ $rb['backup'] }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <p class="text-gray-500">{{ __('updates.recover_point_none') }}</p>
            @endif
        </div>

        {{-- محلِ ثابتِ فایل‌ها --}}
        <div class="mt-4 text-sm">
            <div class="mb-2 font-semibold text-gray-700 dark:text-gray-200">{{ __('updates.recover_files') }}</div>
            <ul class="space-y-2">
                <li>
                    <span class="font-medium">{{ __('updates.recover_backups_loc') }}:</span>
                    <code class="mx-1 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800" dir="ltr">storage/app/private/backups/</code>
                    <span class="text-gray-500">— {{ __('updates.recover_backups_note') }}</span>
                </li>
                <li>
                    <span class="font-medium">{{ __('updates.recover_snapshot_loc') }}:</span>
                    <code class="mx-1 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800" dir="ltr">storage/app/rollback/</code>
                    <span class="text-gray-500">— {{ __('updates.recover_snapshot_note') }}</span>
                </li>
                <li>
                    <span class="font-medium">{{ __('updates.recover_releases_loc') }}:</span>
                    @if ($releasesUrl)
                        <a href="{{ $releasesUrl }}" target="_blank" rel="noopener" class="mx-1 font-mono text-xs text-primary-600 underline dark:text-primary-400" dir="ltr">{{ $releasesUrl }}</a>
                    @endif
                    <span class="text-gray-500">— {{ __('updates.recover_releases_note') }}</span>
                </li>
            </ul>
        </div>

        {{-- مراحل --}}
        <div class="mt-4 text-sm">
            <div class="mb-2 font-semibold text-gray-700 dark:text-gray-200">{{ __('updates.recover_steps') }}</div>
            <ol class="list-decimal space-y-2 pe-5 text-gray-600 dark:text-gray-400">
                <li>{{ __('updates.recover_step_code') }}</li>
                <li>{{ __('updates.recover_step_cache') }}</li>
                <li>{{ __('updates.recover_step_db') }}</li>
                <li>{{ __('updates.recover_step_done') }}</li>
            </ol>
        </div>
    </x-filament::section>
</x-filament-panels::page>
