{{-- نوارِ بالای پنلِ پشتیبان: نامِ کاربر + کلیدِ روز/شب + خروج — مثلِ پنلِ مشتری. --}}
<div class="soorin-topbar"
     x-data="{ dark: document.documentElement.classList.contains('dark') }">
    <span class="soorin-topbar__name">{{ auth()->user()?->name }}</span>

    {{-- کلیدِ روز/شب: کلاسِ dark فیلامنت را جابه‌جا و در localStorage ذخیره می‌کند. --}}
    <button type="button" class="soorin-topbar__btn"
        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}"
        :title="dark ? @js(__('common.theme_ocean')) : @js(__('common.theme_night'))"
        :aria-label="dark ? @js(__('common.theme_ocean')) : @js(__('common.theme_night'))">
        {{-- الان شب است → خورشید (رفتن به روز) --}}
        <svg x-show="dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
        {{-- الان روز است → ماه (رفتن به شب) --}}
        <svg x-show="!dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    {{-- خروج --}}
    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
        @csrf
        <button type="submit" class="soorin-topbar__btn soorin-topbar__logout" title="{{ __('auth.logout') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
            <span class="soorin-topbar__logout-text">{{ __('auth.logout') }}</span>
        </button>
    </form>
</div>
