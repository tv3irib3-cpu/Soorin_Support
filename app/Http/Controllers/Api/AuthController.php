<?php

namespace App\Http\Controllers\Api;

use App\Auth\LoginAttempt;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * ورود/خروجِ اپِ موبایل. توکنِ ماندگار می‌دهد تا کاربر فقط یک‌بار لاگین کند.
 */
class AuthController extends Controller
{
    /** ورودِ اپِ پشتیبان — فقط کاربرانِ پشتیبان. */
    public function loginSupport(Request $request): JsonResponse
    {
        return $this->login($request, 'support');
    }

    /** ورودِ اپِ مشتری — فقط کاربرانِ مشتری. */
    public function loginPortal(Request $request): JsonResponse
    {
        return $this->login($request, 'portal');
    }

    private function login(Request $request, string $platform): JsonResponse
    {
        $data = $request->validate([
            'identifier'  => ['required', 'string'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $key = 'api-login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ], 429);
        }

        try {
            $user = (new LoginAttempt($data['identifier'], $data['password']))->authenticate();
        } catch (ValidationException) {
            RateLimiter::hit($key);

            return response()->json(['message' => __('auth.failed')], 422);
        }

        // هر اپ فقط کاربرانِ خودش را می‌پذیرد.
        $allowed = $platform === 'support' ? $user->isSupportUser() : $user->isCustomerUser();

        if (! $allowed) {
            return response()->json(['message' => __('auth.failed')], 403);
        }

        RateLimiter::clear($key);

        $token = ApiToken::issue($user, $data['device_name'] ?? 'app', $platform);

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /** اطلاعاتِ کاربرِ واردشده (برای بازکردنِ اپ با توکنِ ذخیره‌شده). */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /** خروج — باطل‌کردنِ توکنِ همین دستگاه. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');
        $token?->delete();

        return response()->json(['message' => 'ok']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'user_type'  => $user->user_type,
            'is_admin'   => $user->isSupportAdmin() || $user->isCustomerAdmin(),
            'customer'   => $user->customer?->name,
        ];
    }
}
