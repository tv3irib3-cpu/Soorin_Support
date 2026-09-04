<?php

namespace App\Filament\Auth;

use App\Auth\LoginAttempt;
use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/**
 * ورودِ پنل با **نام کاربری، ایمیل یا موبایل** — نه فقط ایمیل.
 *
 * پیش‌فرضِ Filament فیلدِ ورود را با فرمتِ ایمیل اعتبارسنجی می‌کند، پس با نام‌کاربریِ
 * ساده مثل «admin» کار نمی‌کرد. اینجا اعتبارسنجیِ ایمیل برداشته می‌شود و کاربر با
 * تطبیقِ دقیقِ ستونِ email (که می‌تواند نام‌کاربری باشد) یا موبایل پیدا می‌شود.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('auth.login_identifier'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /**
     * ورودی می‌تواند نام‌کاربری/ایمیل (تطبیقِ دقیق با ستونِ email) یا موبایل باشد.
     * اعتبارنامه بر پایهٔ ستونی ساخته می‌شود که کاربر با آن پیدا شده تا attempt درست کار کند.
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['email']);

        $user = User::query()
            ->where('email', $login)
            ->orWhere('mobile', LoginAttempt::normalizeMobile($login))
            ->first();

        if ($user?->email !== null && $user?->email !== '') {
            return ['email' => $user->email, 'password' => $data['password']];
        }

        if ($user?->mobile !== null && $user?->mobile !== '') {
            return ['mobile' => $user->mobile, 'password' => $data['password']];
        }

        // کاربری پیدا نشد — همان ورودی برگردد تا attempt شکست بخورد و پیامِ استاندارد بدهد.
        return ['email' => $login, 'password' => $data['password']];
    }
}
