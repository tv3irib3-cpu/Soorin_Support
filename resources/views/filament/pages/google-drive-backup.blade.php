<x-filament-panels::page>
    {{-- وضعیتِ اتصال --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('gdrive.status') }}</x-slot>

        @if ($this->isConnected())
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <x-filament::badge color="success">{{ __('gdrive.connected') }}</x-filament::badge>
                @if ($this->connectedEmail())
                    <span class="font-mono" dir="ltr">{{ $this->connectedEmail() }}</span>
                @endif
                <x-filament::badge :color="$this->isEnabled() ? 'success' : 'gray'">
                    {{ $this->isEnabled() ? __('gdrive.auto_on') : __('gdrive.auto_off') }}
                </x-filament::badge>
                @if ($this->includesFiles())
                    <x-filament::badge color="info">{{ __('gdrive.files_on') }}</x-filament::badge>
                @endif
            </div>
        @elseif ($this->isConfigured())
            <p class="text-sm text-warning-600 dark:text-warning-400">{{ __('gdrive.configured_not_connected') }}</p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('gdrive.not_configured') }}</p>
        @endif
    </x-filament::section>

    {{-- راهنمای راه‌اندازی --}}
    <x-filament::section collapsible :collapsed="$this->isConnected()">
        <x-slot name="heading">{{ __('gdrive.setup_title') }}</x-slot>

        <ol class="list-decimal space-y-2 pe-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('gdrive.setup_1') }}</li>
            <li>{{ __('gdrive.setup_2') }}</li>
            <li>
                {{ __('gdrive.setup_3') }}
                <div class="mt-1">
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800" dir="ltr">{{ $this->redirectUri() }}</code>
                </div>
            </li>
            <li>{{ __('gdrive.setup_4') }}</li>
            <li>{{ __('gdrive.setup_5') }}</li>
        </ol>

        <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <div class="mb-1 text-sm font-semibold text-warning-700 dark:text-warning-400">{{ __('gdrive.troubleshoot_title') }}</div>
            <p class="text-xs text-gray-700 dark:text-gray-300">{{ __('gdrive.troubleshoot_403') }}</p>
            <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">{{ __('gdrive.troubleshoot_unverified') }}</p>
        </div>
    </x-filament::section>

    {{-- فایل‌های روی درایو --}}
    @if ($this->isConnected())
        <x-filament::section>
            <x-slot name="heading">{{ __('gdrive.files_on_drive') }}</x-slot>
            <x-slot name="description">{{ __('gdrive.files_hint') }}</x-slot>

            @if (! $listed)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('gdrive.press_refresh') }}</p>
            @elseif (empty($files))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('gdrive.no_files') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400">
                                <th class="py-2 pe-3 text-start font-medium">{{ __('gdrive.file_name') }}</th>
                                <th class="py-2 px-3 text-center font-medium">{{ __('storage.size') }}</th>
                                <th class="py-2 px-3 text-center font-medium">{{ __('gdrive.modified') }}</th>
                                <th class="py-2 ps-3 text-center font-medium">{{ __('storage.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($files as $f)
                                <tr>
                                    <td class="py-2 pe-3 font-mono text-xs" dir="ltr">{{ $f['name'] }}</td>
                                    <td class="py-2 px-3 text-center tabular-nums">{{ $this->humanSize((int) $f['size']) }}</td>
                                    <td class="py-2 px-3 text-center">
                                        {{ $f['modified'] ? \App\Support\Jalali::formatDateTime($f['modified']) : '—' }}
                                    </td>
                                    <td class="py-2 ps-3 text-center">
                                        <x-filament::button
                                            size="sm" color="warning" icon="heroicon-o-arrow-down-tray"
                                            wire:click="restoreFromDrive('{{ $f['id'] }}')"
                                            wire:confirm="{{ __('gdrive.restore_confirm') }}">
                                            {{ __('gdrive.restore') }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
