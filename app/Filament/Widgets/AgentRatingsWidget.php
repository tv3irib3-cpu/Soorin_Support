<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * امتیازِ رضایت به‌تفکیکِ هر کارشناسِ پشتیبان.
 *
 * «امتیازِ کلیِ شرکت» (میانگینِ سادهٔ همهٔ تیکت‌های امتیازخورده) را همه می‌بینند —
 * چه کارشناس چه مدیر. تفاوت در فهرست است: کارشناس فقط امتیازِ خودش را می‌بیند،
 * مدیرِ پشتیبان امتیازِ همهٔ کارشناسان را.
 * امتیازِ هر کارشناس = میانگینِ تیکت‌های تخصیص‌یافته به او که امتیاز گرفته‌اند.
 */
class AgentRatingsWidget extends Widget
{
    protected string $view = 'filament.widgets.agent-ratings';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isSupportUser() ?? false;
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $isAdmin = $user?->isSupportAdmin() ?? false;

        $users = $isAdmin
            ? User::whereIn('user_type', [User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect(array_filter([$user]));

        $rows = $users->map(function (User $u): array {
            $base  = Ticket::where('assigned_to', $u->id)->whereNotNull('rating');
            $count = (clone $base)->count();
            $avg   = $count > 0 ? (float) (clone $base)->avg('rating') : null;

            return ['name' => $u->name, 'avg' => $avg, 'count' => $count];
        })->all();

        // امتیازِ کلیِ شرکت — میانگینِ سادهٔ همهٔ تیکت‌های امتیازخورده. برای همه
        // (کارشناس و مدیر) نمایش داده می‌شود.
        $overallCount = Ticket::whereNotNull('rating')->count();
        $overall = $overallCount > 0 ? (float) Ticket::whereNotNull('rating')->avg('rating') : null;

        return [
            'isAdmin'      => $isAdmin,
            'rows'         => $rows,
            'overall'      => $overall,
            'overallCount' => $overallCount,
        ];
    }
}
