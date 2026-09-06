<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * تعویضِ تمِ روز/شبِ پرتالِ مشتری.
 *
 * انتخابِ کاربر در یک کوکیِ یک‌ساله نگه داشته می‌شود تا در بازدیدهای بعدی هم
 * بماند. ApplyUserTheme این کوکی را با اولویت بالاتر از تمِ حساب می‌خواند.
 */
class ThemeController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $themes  = array_keys(config('branding.themes'));
        $target  = (string) $request->input('theme');

        if (! in_array($target, $themes, true)) {
            $target = config('branding.default_theme');
        }

        // یک سال ماندگاری، در دسترسِ کلِ سایت.
        return back()->withCookie(cookie('portal_theme', $target, 60 * 24 * 365));
    }
}
