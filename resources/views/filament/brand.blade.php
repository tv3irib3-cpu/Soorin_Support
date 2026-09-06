{{-- برندِ سفارشیِ پنلِ پشتیبان: لوگوی بزرگ‌ترِ متناسب با روز/شب + نامِ شرکت و سامانه.
     بایت‌های لوگو به‌صورتِ base64 جاسازی می‌شوند تا روی هر هاستی نمایش داده شوند. --}}
@php
    $light = \App\Support\Branding::logoData('light') ?? \App\Support\Branding::logo('light');
    $dark  = \App\Support\Branding::logoData('dark')  ?? \App\Support\Branding::logo('dark');
@endphp
<div style="display:flex; align-items:center; gap:10px;">
    <img src="{{ $light }}" alt="{{ \App\Support\Branding::companyName() }}" class="dark:hidden" style="height:2.5rem; width:auto;">
    <img src="{{ $dark }}"  alt="{{ \App\Support\Branding::companyName() }}" class="hidden dark:block" style="height:2.5rem; width:auto;">
    <span style="display:flex; flex-direction:column; line-height:1.2;">
        <span style="font-weight:800; font-size:.95rem; color:var(--gray-800,#1f2937);" class="dark:!text-white">{{ \App\Support\Branding::companyName() }}</span>
        <span style="font-size:.7rem; opacity:.65;">{{ \App\Support\Branding::appTitle() }}</span>
    </span>
</div>
