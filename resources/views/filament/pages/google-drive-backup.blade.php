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

                <span class="ms-auto">{{ $this->disconnectAction }}</span>
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

        {{-- شماره‌ها داخلِ متنِ هر مرحله هستند، پس فهرستِ بدونِ شمارهٔ خودکار. --}}
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('gdrive.setup_1') }}</li>
            <li>{{ __('gdrive.setup_2') }}</li>
            <li>{{ __('gdrive.setup_3') }}</li>
            <li>
                {{ __('gdrive.setup_4') }}
                <div class="mt-1">
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800" dir="ltr">{{ $this->redirectUri() }}</code>
                </div>
            </li>
            <li>{{ __('gdrive.setup_5') }}</li>
            <li>{{ __('gdrive.setup_6') }}</li>
            <li>{{ __('gdrive.setup_7') }}</li>
        </ul>

        <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <div class="mb-1 text-sm font-semibold text-warning-700 dark:text-warning-400">{{ __('gdrive.troubleshoot_title') }}</div>
            <p class="text-xs text-gray-700 dark:text-gray-300">{{ __('gdrive.troubleshoot_403') }}</p>
            <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">{{ __('gdrive.troubleshoot_scope') }}</p>
            <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">{{ __('gdrive.troubleshoot_unverified') }}</p>
        </div>
    </x-filament::section>

    {{-- فایل‌های روی درایو --}}
    @if ($this->isConnected())
        <x-filament::section>
            <x-slot name="heading">{{ __('gdrive.files_on_drive') }}</x-slot>
            <x-slot name="description">{{ __('gdrive.files_hint') }}</x-slot>

            {{-- نوارِ ابزار: «تازه‌سازی فهرست» و «حذفِ انتخاب‌شده‌ها» کنارِ هم با فاصلهٔ روشن --}}
            <div class="mb-4 flex flex-wrap items-center gap-4">
                {{ $this->refreshAction }}

                @if ($listed && ! empty($files))
                    <x-filament::button
                        size="sm" color="danger" icon="heroicon-o-trash"
                        wire:click="deleteSelected"
                        wire:confirm="{{ __('gdrive.delete_selected_confirm') }}">
                        {{ __('gdrive.delete_selected') }}
                        @if (count($selected))
                            <span class="mx-1">({{ \App\Support\Jalali::digits((string) count($selected)) }})</span>
                        @endif
                    </x-filament::button>
                @endif
            </div>

            @if (! $listed)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('gdrive.press_refresh') }}</p>
            @elseif (empty($files))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('gdrive.no_files') }}</p>
            @else
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ __('gdrive.group_hint') }}</p>

                <div class="overflow-x-auto">
                    <table class="soorin-grid gdrive-grid">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>{{ __('gdrive.file_name') }}</th>
                                <th>{{ __('storage.size') }}</th>
                                <th>{{ __('gdrive.modified') }}</th>
                                <th>{{ __('storage.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->groupedFiles() as $gi => $group)
                                @php $groupChecked = array_diff($group['ids'], $selected) === []; @endphp
                                {{-- سرِ دسته: تاریخ/ساعتِ پشتیبان + تعداد + حجم، با رنگِ متمایز --}}
                                <tr class="gdrive-batch gdrive-batch--{{ $gi % 2 }}">
                                    <td>
                                        <input type="checkbox" class="gdrive-check"
                                            wire:click="toggleGroup(@js($group['ids']))"
                                            @checked($groupChecked)>
                                    </td>
                                    <td colspan="4" style="text-align:start;">
                                        <span class="gdrive-batch__label">{{ __('gdrive.backup_batch') }}</span>
                                        <span class="gdrive-batch__time">{{ $group['label'] }}</span>
                                        <span class="gdrive-batch__meta">
                                            {{ __('gdrive.batch_files', ['count' => \App\Support\Jalali::digits((string) count($group['files']))]) }}
                                            · {{ $this->humanSize((int) $group['bytes']) }}
                                        </span>
                                    </td>
                                </tr>

                                @foreach ($group['files'] as $f)
                                    <tr class="gdrive-row gdrive-row--{{ $gi % 2 }}">
                                        <td>
                                            <input type="checkbox" class="gdrive-check"
                                                value="{{ $f['id'] }}" wire:model.live="selected">
                                        </td>
                                        <td><span class="soorin-code">{{ $f['name'] }}</span></td>
                                        <td>{{ $this->humanSize((int) $f['size']) }}</td>
                                        <td>{{ $f['modified'] ? \App\Support\Jalali::formatDateTime($f['modified']) : '—' }}</td>
                                        <td>
                                            <div class="flex items-center justify-center gap-2">
                                                <x-filament::button
                                                    size="sm" color="warning" icon="heroicon-o-arrow-down-tray"
                                                    wire:click="restoreFromDrive('{{ $f['id'] }}')"
                                                    wire:confirm="{{ __('gdrive.restore_confirm') }}">
                                                    {{ __('gdrive.restore') }}
                                                </x-filament::button>
                                                <x-filament::button
                                                    size="sm" color="danger" icon="heroicon-o-trash"
                                                    wire:click="deleteFromDrive('{{ $f['id'] }}')"
                                                    wire:confirm="{{ __('gdrive.delete_confirm') }}">
                                                    {{ __('gdrive.delete') }}
                                                </x-filament::button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
