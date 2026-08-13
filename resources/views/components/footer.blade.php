{{--
    فوتر سامانه — در پنل مدیریت، پرتال مشتری و صفحه ورود استفاده می‌شود.
    سال به‌صورت شمسی و پویا نمایش داده می‌شود؛ نسخه از config/branding خوانده می‌شود.
--}}
@php
    $company     = config('branding.company');
    $app         = config('branding.app');
    $currentYear = verta()->format('Y');            // سال جاری شمسی
    $founded     = $company['founded_year'];
    $yearRange   = $founded && $founded < $currentYear
        ? $founded . ' – ' . $currentYear
        : $currentYear;
@endphp

<footer class="app-footer">
    <div class="app-footer__inner">
        <p class="app-footer__copy">
            © {{ $yearRange }} — کلیه حقوق برای {{ $company['name'] }} محفوظ است.
        </p>

        <div class="app-footer__meta">
            <a href="{{ $company['website'] }}" target="_blank" rel="noopener">
                {{ $company['website_label'] }}
            </a>
            <span aria-hidden="true">·</span>
            <span>{{ $app['title'] }}</span>
            <span aria-hidden="true">·</span>
            <span dir="ltr">v{{ $app['version'] }}</span>
        </div>
    </div>
</footer>
