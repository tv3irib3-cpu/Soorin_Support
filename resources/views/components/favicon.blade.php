{{-- فاوآیکونِ تبِ مرورگر. برای اطمینان از نمایش روی هر هاستی (LiteSpeed گاهی فایلِ
     استاتیک را ۴۰۴ می‌کرد)، خودِ بایت‌ها به‌صورتِ data:base64 جاسازی می‌شوند — چه
     مدیر در «شخصی‌سازی» فاوآیکون آپلود کرده باشد چه پیش‌فرضِ برند باشد. --}}
@php
    $favicon = \App\Support\Branding::logoData('favicon')
        ?? (\App\Support\Branding::logo('favicon') . '?ver=' . \App\Support\AppVersion::current());
@endphp
<link rel="icon" href="{{ $favicon }}" sizes="any">
<link rel="apple-touch-icon" href="{{ $favicon }}">
