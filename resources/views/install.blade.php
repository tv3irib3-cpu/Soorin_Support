<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصبِ سامانه پشتیبانی سورین</title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#eef4f6; color:#0b2b3f; margin:0; padding:2rem; }
        .box { max-width:560px; margin:3rem auto; background:#fff; border:1px solid #dde8ec; border-radius:14px; padding:2rem; }
        h1 { font-size:1.3rem; margin:0 0 1rem; }
        .ok { color:#0f766e; }
        .err { color:#b91c1c; }
        .cred { background:#f0f7f9; border:1px dashed #14b8a6; border-radius:10px; padding:1rem; margin:1rem 0; font-size:1.05rem; }
        .cred b { font-family: monospace; direction:ltr; display:inline-block; }
        .warn { background:#fff7ed; border:1px solid #fdba74; color:#9a3412; border-radius:10px; padding:.8rem 1rem; margin:1rem 0; }
        a.btn { display:inline-block; background:#14b8a6; color:#fff; text-decoration:none; padding:.7rem 1.4rem; border-radius:10px; margin-top:.5rem; }
    </style>
</head>
<body>
    <div class="box">
        @if (! empty($already))
            <h1>سامانه از قبل نصب شده است ✓</h1>
            <p>جدول‌ها از قبل ساخته شده‌اند. برای ورود به پنل مدیریت:</p>
            <a class="btn" href="{{ $adminUrl }}">ورود به پنل مدیریت</a>
        @elseif (! empty($error))
            <h1 class="err">نصب ناموفق بود</h1>
            <p>خطا هنگام ساخت جدول‌ها:</p>
            <pre style="white-space:pre-wrap; direction:ltr; text-align:left; background:#fef2f2; padding:1rem; border-radius:8px;">{{ $error }}</pre>
            <p>معمولاً یعنی اطلاعاتِ دیتابیس در <code>.env</code> درست نیست. آن را بررسی و دوباره این صفحه را باز کنید.</p>
        @elseif (! empty($done))
            <h1 class="ok">نصب با موفقیت انجام شد ✓</h1>
            <p>این اطلاعاتِ ورودِ مدیر را همین حالا یادداشت کنید — رمز فقط همین یک‌بار نمایش داده می‌شود:</p>
            <div class="cred">
                نام کاربری: <b>{{ $username }}</b><br>
                رمز عبور: <b>{{ $password }}</b>
            </div>
            <div class="warn">پس از ورود، از منوی پروفایل رمز را به یک رمزِ دلخواهِ خودتان تغییر دهید.</div>
            <a class="btn" href="{{ $adminUrl }}">ورود به پنل مدیریت</a>
        @endif
    </div>
</body>
</html>
