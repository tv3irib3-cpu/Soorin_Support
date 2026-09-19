<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * احرازِ هویتِ درخواست‌های اپ با توکنِ Bearer.
 *
 * توکن را از هدرِ Authorization می‌خواند، کاربرِ متناظر را پیدا و روی درخواست
 * می‌نشاند تا auth()->user() در کنترلرها مثلِ همیشه کار کند. اگر platform مشخص
 * شود (support/portal)، فقط توکنِ همان اپ پذیرفته می‌شود.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $platform = null): Response
    {
        $token = ApiToken::findByPlain($request->bearerToken());

        if (! $token || ! $token->user || ! $token->user->is_active) {
            return response()->json(['message' => __('auth.failed')], 401);
        }

        if ($platform !== null && $token->platform !== $platform) {
            return response()->json(['message' => __('auth.failed')], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
