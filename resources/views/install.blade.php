<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصبِ سامانه پشتیبانی سورین</title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#eef4f6; color:#0b2b3f; margin:0; padding:1.5rem; }
        .box { max-width:640px; margin:2rem auto; background:#fff; border:1px solid #dde8ec; border-radius:14px; padding:2rem; }
        h1 { font-size:1.35rem; margin:0 0 .3rem; }
        h2 { font-size:1rem; color:#0f766e; margin:1.6rem 0 .6rem; border-bottom:1px solid #e2eef1; padding-bottom:.3rem; }
        p.sub { color:#5f7d8c; margin:0 0 1rem; font-size:.9rem; }
        label { display:block; margin:.7rem 0 .25rem; font-size:.9rem; }
        input { width:100%; box-sizing:border-box; padding:.6rem .7rem; border:1px solid #cddde3; border-radius:9px; font-size:1rem; }
        input[dir=ltr] { direction:ltr; text-align:left; }
        .row { display:flex; gap:1rem; } .row > div { flex:1; }
        .btn { margin-top:1.4rem; background:#14b8a6; color:#fff; border:0; padding:.8rem 1.6rem; border-radius:10px; font-size:1rem; cursor:pointer; }
        .ok { color:#0f766e; } .err { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:.8rem 1rem; border-radius:10px; margin:1rem 0; white-space:pre-wrap; }
        .cred { background:#f0f7f9; border:1px dashed #14b8a6; border-radius:10px; padding:1rem; margin:1rem 0; font-size:1.05rem; }
        .cred b { font-family:monospace; direction:ltr; display:inline-block; }
        .warn { background:#fff7ed; border:1px solid #fdba74; color:#9a3412; border-radius:10px; padding:.8rem 1rem; margin:1rem 0; }
        a.link { display:inline-block; background:#14b8a6; color:#fff; text-decoration:none; padding:.7rem 1.4rem; border-radius:10px; margin-top:.5rem; }
        .hint { color:#5f7d8c; font-size:.78rem; margin-top:.2rem; }
    </style>
</head>
<body>
<div class="box">
@if (($state ?? '') === 'already')
    <h1 class="ok">سامانه از قبل نصب شده است ✓</h1>
    <p>برای ورود به پنل مدیریت:</p>
    <a class="link" href="{{ $adminUrl }}">ورود به پنل مدیریت</a>

@elseif (($state ?? '') === 'done')
    <h1 class="ok">نصب با موفقیت انجام شد ✓</h1>
    <p>حساب مدیر با این نام کاربری ساخته شد:</p>
    <div class="cred">نام کاربری: <b>{{ $username }}</b></div>
    <div class="warn">با همین نام کاربری و رمزی که خودت وارد کردی وارد شو. اگر رمز را فراموش کردی، از هاست به دیتابیس دسترسی داری.</div>
    <a class="link" href="{{ $adminUrl }}">ورود به پنل مدیریت</a>

@else
    <h1>نصبِ سامانه پشتیبانی سورین</h1>
    <p class="sub">اطلاعاتِ دیتابیس و حساب مدیر را وارد کن تا نصب کامل شود.</p>

    @if (! empty($error))
        <div class="err">{{ $error }}</div>
    @endif
    @if ($errors->any())
        <div class="err">@foreach ($errors->all() as $e){{ $e }}
@endforeach</div>
    @endif

    <form method="POST" action="{{ url('/install') }}">
        @csrf
        @php $o = $old ?? []; @endphp

        <h2>دیتابیس</h2>
        <div class="row">
            <div>
                <label>میزبان (Host)</label>
                <input dir="ltr" name="db_host" value="{{ $o['db_host'] ?? 'localhost' }}" required>
            </div>
            <div>
                <label>پورت</label>
                <input dir="ltr" name="db_port" value="{{ $o['db_port'] ?? '3306' }}" required>
            </div>
        </div>
        <label>نام دیتابیس</label>
        <input dir="ltr" name="db_database" value="{{ $o['db_database'] ?? '' }}" required>
        <label>نام کاربری دیتابیس</label>
        <input dir="ltr" name="db_username" value="{{ $o['db_username'] ?? '' }}" required>
        <label>رمز دیتابیس</label>
        <input dir="ltr" type="password" name="db_password" value="{{ $o['db_password'] ?? '' }}">
        <label>پیشوند جدول‌ها (اختیاری)</label>
        <input dir="ltr" name="db_prefix" value="{{ $o['db_prefix'] ?? '' }}" placeholder="خالی بگذار مگر دیتابیس را با نرم‌افزار دیگری مشترک کنی">
        <div class="hint">اگر دیتابیس فقط برای این سامانه است، خالی بگذار.</div>

        <h2>حساب مدیر</h2>
        <label>نام و نام خانوادگی</label>
        <input name="admin_name" value="{{ $o['admin_name'] ?? '' }}" required>
        <label>نام کاربری (برای ورود)</label>
        <input dir="ltr" name="admin_username" value="{{ $o['admin_username'] ?? 'admin' }}" required>
        <div class="hint">بدون فاصله. مثلاً admin</div>
        <label>رمز عبور</label>
        <input dir="ltr" type="password" name="admin_password" required>
        <div class="hint">حداقل ۸ نویسه.</div>
        <label>تکرار رمز عبور</label>
        <input dir="ltr" type="password" name="admin_password_confirmation" required>

        <button class="btn" type="submit">نصب کن</button>
    </form>
@endif
</div>
</body>
</html>
