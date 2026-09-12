{{-- برندِ سفارشیِ پنلِ پشتیبان: لوگوی متناسب با روز/شب + نامِ شرکت و سامانه.
     استایل‌ها در resources/css/theme.css هستند (که قطعاً در <head>ِ پنل تزریق
     می‌شود)، نه اینجا؛ تا نمایشِ روز/شب مطمئن باشد. --}}
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
