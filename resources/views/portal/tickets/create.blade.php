<x-layouts.portal :title="__('portal.new_ticket')">
    <h2 style="margin-top:0;">{{ __('portal.new_ticket') }}</h2>

    <div class="card">
        @if ($errors->any())
            <div class="status-banner warning">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('portal.tickets.store') }}">
            @csrf

            @if ($projects->isNotEmpty())
            <div class="field">
                <label for="customer_project_id">{{ __('tickets.project') }}</label>
                <select id="customer_project_id" name="customer_project_id">
                    <option value="">{{ __('common.select') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" {{ old('customer_project_id') == $project->id ? 'selected' : '' }}>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="field">
                <label for="ticket_category_id">{{ __('portal.ticket_category') }}</label>
                <select id="ticket_category_id" name="ticket_category_id">
                    <option value="">{{ __('common.select') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('ticket_category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->fullName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="subject">{{ __('portal.ticket_subject') }}</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required>
            </div>

            <div class="field">
                <label for="description">{{ __('portal.ticket_description') }}</label>
                <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                <div style="font-size:11.5px; color:var(--muted); margin-top:4px;">{{ __('portal.ticket_description_hint') }}</div>
            </div>

            <div class="field">
                <label for="priority">{{ __('tickets.priority') }}</label>
                <select id="priority" name="priority">
                    @foreach (__('tickets.priorities') as $value => $label)
                        <option value="{{ $value }}" {{ old('priority', 'normal') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn">{{ __('common.save') }}</button>
            <a href="{{ route('portal.tickets.index') }}" class="btn secondary">{{ __('common.cancel') }}</a>
        </form>
    </div>
</x-layouts.portal>
