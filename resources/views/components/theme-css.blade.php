{{--
    متغیرهای رنگیِ تم را مستقیم و inline در صفحه می‌گذارد — نه لینک به فایلِ خارجی.
    چرا؟ روی هاست‌هایی مثلِ LiteSpeed، فایلِ CSSِ سرو‌شده از مسیرِ زنده یا از وب‌روتِ
    اشتباه ۴۰۴ می‌شد و کلِ پرتال بی‌رنگ (سفید) می‌شد. با inline، تم همیشه هست.
--}}
@php
    $__themeCss = @file_get_contents(resource_path('css/theme.css'));
@endphp
@if ($__themeCss !== false)
<style>{!! $__themeCss !!}</style>
@endif
