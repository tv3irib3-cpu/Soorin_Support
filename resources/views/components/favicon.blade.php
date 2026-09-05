{{-- فاوآیکون از سیستمِ برندینگ خوانده می‌شود: اگر مدیر در «شخصی‌سازی» فاوآیکون آپلود
     کرده باشد همان، وگرنه پیش‌فرضِ برند (images/favicon.png). یک ‎?v= هم اضافه می‌شود
     تا مرورگر (که فاوآیکون را سرسختانه کش می‌کند) نسخهٔ تازه را بگیرد. --}}
@php
    $favicon  = \App\Support\Branding::logo('favicon');
    $favicon .= (str_contains($favicon, '?') ? '&' : '?') . 'ver=' . \App\Support\AppVersion::current();
@endphp
<link rel="icon" href="{{ $favicon }}" sizes="any">
<link rel="apple-touch-icon" href="{{ $favicon }}">
