{{-- برندِ سفارشیِ پنلِ پشتیبان: لوگوی متناسب با روز/شب + نامِ شرکت و سامانه.
     به‌جای کلاس‌های Tailwind (dark:hidden/…) که ممکن است در باندلِ CSSِ فیلامنت
     نباشند و باعثِ نمایشِ هم‌زمانِ هر دو لوگو شوند، از CSSِ صریح با نشانگرِ .dark
     استفاده می‌شود (فیلامنت در حالتِ شب کلاسِ dark را روی <html> می‌گذارد). --}}
@php
    $light = \App\Support\Branding::logoData('light') ?? \App\Support\Branding::logo('light');
    $dark  = \App\Support\Branding::logoData('dark')  ?? \App\Support\Branding::logo('dark');
@endphp
<div class="soorin-brand">
    <img class="soorin-brand__logo soorin-brand__logo--light" src="{{ $light }}" alt="{{ \App\Support\Branding::companyName() }}">
    <img class="soorin-brand__logo soorin-brand__logo--dark"  src="{{ $dark }}"  alt="{{ \App\Support\Branding::companyName() }}">
    <span class="soorin-brand__text">
        <span class="soorin-brand__company">{{ \App\Support\Branding::companyName() }}</span>
        <span class="soorin-brand__app">{{ \App\Support\Branding::appTitle() }}</span>
    </span>
</div>
<style>
    .soorin-brand { display: flex; align-items: center; gap: 10px; }
    .soorin-brand__logo { height: 2.5rem; width: auto; }
    /* پیش‌فرض (روز): لوگوی روشن دیده می‌شود، لوگوی شب پنهان است. */
    .soorin-brand__logo--dark { display: none; }
    .soorin-brand__text { display: flex; flex-direction: column; line-height: 1.2; }
    .soorin-brand__company { font-weight: 800; font-size: .95rem; color: #1f2937; }
    .soorin-brand__app { font-size: .7rem; opacity: .65; color: #1f2937; }
    /* حالتِ شب: فقط لوگوی شب، و نامِ سفید. */
    .dark .soorin-brand__logo--light { display: none; }
    .dark .soorin-brand__logo--dark { display: inline-block; }
    .dark .soorin-brand__company,
    .dark .soorin-brand__app { color: #ffffff; }
</style>
