<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="ocean">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('survey.title') }} — {{ config('branding.company.name') }}</title>
    <link rel="icon" href="{{ asset(config('branding.logo.mark')) }}">
    <link rel="stylesheet" href="{{ route('theme.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Vazirmatn, system-ui, sans-serif; background: var(--nav);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .card {
            background: var(--card); border-radius: var(--radius); padding: 32px 30px;
            width: 100%; max-width: 440px; box-shadow: 0 10px 40px rgba(0,0,0,.25); text-align: center;
        }
        .card img { height: 36px; margin-bottom: 14px; }
        h1 { font-size: 17px; margin: 0 0 4px; color: var(--text); }
        .ticket-ref { font-size: 12.5px; color: var(--muted); margin: 0 0 24px; }

        .rating { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
        .rating label {
            display: flex; align-items: center; gap: 10px; border: 1px solid var(--border);
            border-radius: 10px; padding: 10px 14px; cursor: pointer; font-size: 13.5px; color: var(--text);
            text-align: right;
        }
        .rating input { accent-color: var(--accent); width: 16px; height: 16px; }
        .rating .star { color: #f5b301; letter-spacing: 1px; }
        .rating label:has(input:checked) { border-color: var(--accent); background: var(--accent-soft); }

        .field { text-align: right; margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 6px; font-size: 12.5px; color: var(--muted); }
        .field textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;
            font-family: inherit; font-size: 13.5px; resize: vertical;
        }

        button.submit {
            width: 100%; background: var(--accent); color: #fff; border: none; border-radius: 8px;
            padding: 11px; font-family: inherit; font-size: 14px; cursor: pointer;
        }

        .thanks-icon { font-size: 40px; margin-bottom: 10px; }
        .thanks-body { color: var(--muted); font-size: 13.5px; }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset(config('branding.logo.mark')) }}" alt="" onerror="this.style.display='none'">

        @if ($submitted || $ticket->rating !== null)
            <div class="thanks-icon">🙏</div>
            <h1>{{ __('survey.thanks_heading') }}</h1>
            <p class="thanks-body">
                @if ($submitted)
                    {{ __('survey.thanks_body') }}
                @else
                    {{ __('survey.already_rated') }}
                @endif
                <br>
                {{ __('survey.your_rating', ['rating' => \App\Support\Jalali::digits((string) $ticket->rating)]) }}
            </p>
        @else
            <h1>{{ __('survey.heading') }}</h1>
            <p class="ticket-ref">{{ __('survey.ticket_ref', ['number' => $ticket->number, 'subject' => $ticket->subject]) }}</p>

            <form method="POST" action="{{ url()->full() }}">
                @csrf

                <div class="rating">
                    @foreach (array_reverse(__('survey.stars'), true) as $value => $label)
                        <label>
                            <input type="radio" name="rating" value="{{ $value }}" {{ old('rating') == $value ? 'checked' : '' }} required>
                            <span class="star">{{ str_repeat('★', $value) }}</span>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="field">
                    <label for="rating_comment">{{ __('survey.comment_label') }}</label>
                    <textarea id="rating_comment" name="rating_comment" rows="3" placeholder="{{ __('survey.comment_hint') }}">{{ old('rating_comment') }}</textarea>
                </div>

                <button type="submit" class="submit">{{ __('survey.submit') }}</button>
            </form>
        @endif
    </div>
</body>
</html>
