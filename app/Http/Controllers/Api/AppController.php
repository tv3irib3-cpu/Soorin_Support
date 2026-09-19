<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * اطلاعاتِ نسخهٔ اپ برای بررسیِ به‌روزرسانیِ درون‌اپ (عمومی — پیش از لاگین هم لازم است).
 */
class AppController extends Controller
{
    public function version(Request $request): JsonResponse
    {
        $platform = $request->query('platform') === 'portal' ? 'portal' : 'support';
        $info = config("mobile.$platform");

        return response()->json([
            'platform'      => $platform,
            'version'       => $info['version'],
            'min_supported' => $info['min_supported'],
            'apk_url'       => $info['apk_url'],
            'notes'         => $info['notes'],
        ]);
    }
}
