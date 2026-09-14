<?php

namespace App\Support;

/**
 * نمایشِ یکدستِ «آواتار + نامِ هایلایت‌شده با رنگِ مشتری» در جدول‌ها
 * (فهرستِ مشتریان و فهرستِ پروژه‌ها). خروجی HTML است.
 */
class CustomerBadge
{
    /**
     * @param  string       $name       متنی که نمایش داده می‌شود (نامِ مشتری یا پروژه)
     * @param  string       $color      رنگِ اختصاصیِ مشتری (hex)
     * @param  string|null  $logoData   data: URI لوگو (اگر باشد)، وگرنه آواتارِ حرفِ اول
     * @param  string|null  $initialOf  متنی که حرفِ اولش در آواتارِ جایگزین می‌آید (پیش‌فرض همان $name)
     */
    public static function nameWithColor(string $name, string $color, ?string $logoData = null, ?string $initialOf = null): string
    {
        $color = e($color);

        if (filled($logoData)) {
            $avatar = '<img src="' . e($logoData) . '" alt="" '
                . 'style="width:26px;height:26px;border-radius:7px;object-fit:contain;'
                . 'background:#fff;border:1px solid rgba(0,0,0,.08);flex:none;">';
        } else {
            $initial = e(mb_substr((string) ($initialOf ?? $name), 0, 1));
            $avatar = '<span style="width:26px;height:26px;border-radius:7px;flex:none;'
                . 'display:grid;place-items:center;color:#fff;font-size:12px;font-weight:700;'
                . 'background:' . $color . ';">' . $initial . '</span>';
        }

        // نام با پس‌زمینهٔ کم‌رنگِ همان رنگ + خطِ کناریِ پررنگ = هایلایتِ خوانا در روز و شب.
        $pill = '<span style="padding:3px 10px;border-radius:7px;font-weight:600;'
            . 'background:color-mix(in srgb, ' . $color . ' 16%, transparent);'
            . 'box-shadow:inset 2px 0 0 ' . $color . ';">' . e($name) . '</span>';

        return '<span style="display:inline-flex;align-items:center;gap:9px;">' . $avatar . $pill . '</span>';
    }
}
