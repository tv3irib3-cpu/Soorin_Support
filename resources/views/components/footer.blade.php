{{--
    فوتر سامانه — در پنل مدیریت، پرتال مشتری و صفحه ورود استفاده می‌شود.
    سال به‌صورت شمسی و پویا نمایش داده می‌شود؛ نسخه از config/branding خوانده می‌شود.
--}}
@php
    // همهٔ مقادیر از سیستمِ برندینگ خوانده می‌شوند تا با «شخصی‌سازی» عوض شوند
    // (نه مستقیم از config که ثابت است).
    $currentYear = (int) verta()->format('Y');       // سال جاری شمسی
    $founded     = \App\Support\Branding::foundedYear();
    $yearRange   = $founded && $founded < $currentYear
        ? $founded . ' – ' . $currentYear
        : (string) $currentYear;

    // سال شمسی است، پس ارقامش هم باید فارسی باشد. شماره نسخه عمداً لاتین
    // می‌ماند چون شناسه فنی است، نه عددی که خوانده شود.
    $yearRange   = \App\Support\Jalali::digits((string) $yearRange);
@endphp

<footer class="app-footer">
    <div class="app-footer__inner">
        <p class="app-footer__copy">
            © {{ $yearRange }} — کلیه حقوق برای {{ \App\Support\Branding::companyName() }} محفوظ است.
        </p>

        <div class="app-footer__meta">
            <a href="{{ \App\Support\Branding::website() }}" target="_blank" rel="noopener">
                {{ \App\Support\Branding::websiteLabel() }}
            </a>
            <span aria-hidden="true">·</span>
            <span>{{ \App\Support\Branding::appTitle() }}</span>
            <span aria-hidden="true">·</span>
            <span dir="ltr">v{{ \App\Support\AppVersion::current() }}</span>
        </div>
    </div>
</footer>
