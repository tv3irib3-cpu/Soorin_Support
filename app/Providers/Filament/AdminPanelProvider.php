<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ApplyUserTheme;
use App\Http\Middleware\EnsureSupportUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\FontProviders\LocalFontProvider;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * پنل مدیریت — فقط کاربران داخلی شرکت (مدیر و کارشناس پشتیبان).
 * کاربران مشتری به پرتال جداگانه هدایت می‌شوند.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $ocean = config('branding.themes.ocean');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // نام و لوگو از App\Support\Branding خوانده می‌شوند تا شخصی‌سازیِ مدیر
            // (صفحهٔ «شخصی‌سازی») همه‌جا اعمال شود، نه فقط پیش‌فرضِ config.
            ->brandName(fn () => \App\Support\Branding::appTitle())
            ->brandLogo(fn () => \App\Support\Branding::logo('light'))
            ->darkModeBrandLogo(fn () => \App\Support\Branding::logo('dark'))
            ->favicon(fn () => \App\Support\Branding::logo('favicon'))
            ->colors([
                'primary' => $ocean['accent'],   // فیروزه‌ای برند
                'gray'    => '#5f7d8c',
            ])
            // فونت وزیرمتن محلی — بدون CDN، چون سرور ممکن است اینترنت نداشته باشد
            ->font('Vazirmatn', url: asset('css/fonts.css'), provider: LocalFontProvider::class)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                // ?v=نسخه تا بعد از هر به‌روزرسانی مرورگر CSSِ تازه را بگیرد.
                fn () => '<link rel="stylesheet" href="' . route('theme.css') . '?v=' . \App\Support\AppVersion::current() . '">',
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('components.footer'),
            )
            // نقطهٔ قرمزِ «نسخهٔ جدید» کنار تیترِ گروهِ منو.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.update-nav-indicator'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureSupportUser::class,   // کاربر مشتری اینجا راه ندارد
                ApplyUserTheme::class,
            ]);
    }
}
