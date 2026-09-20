<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تاریخچهٔ تغییرات برای اپِ پشتیبان — فهرستِ رویدادها (چه کسی، کِی، چه کاری).
 * فقط با مجوزِ «مشاهده تاریخچه تغییرات». این رکوردها هرگز حذف نمی‌شوند.
 */
class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewActivity->value), 403);

        $logs = ActivityLog::with('user')->latest()->paginate(30);

        return response()->json([
            'data' => collect($logs->items())->map(fn (ActivityLog $l) => [
                'id'           => $l->id,
                'action'       => $l->action,
                'action_label' => __("activity.actions.$l->action") === "activity.actions.$l->action"
                    ? $l->action
                    : __("activity.actions.$l->action"),
                'subject_type' => class_basename((string) $l->subject_type),
                'subject_id'   => $l->subject_id,
                'user'         => $l->user?->name ?? '—',
                'created_at_jalali' => Jalali::formatDateTime($l->created_at),
            ])->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}
