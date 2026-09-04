# یادداشت‌های استقرار و عیب‌یابیِ نصب (هاستِ اشتراکی — LiteSpeed/DirectAdmin)

این سند از یک عیب‌یابیِ کاملِ واقعی روی `crm.dpst.ir` (هاستِ irwebspace، DirectAdmin،
LiteSpeed، PHP 8.3) به‌دست آمده است. هدف: نصب و ورودِ کاربر دیگر هرگز روی این نوع هاست
نشکند. خلاصهٔ قواعد در `CLAUDE.md` (بخشِ «قواعد استقرار») است؛ اینجا جزئیاتِ کامل.

## محیطِ هدف
- وب‌روت: `public_html` (نه `public`) → در `.env` باید `APP_PUBLIC_PATH=public_html`.
- MySQL/MariaDB با موتورِ MyISAM روی برخی جدول‌ها.
- بدونِ SSH؛ نصب فقط از طریقِ اکسترکتِ بسته + ویزاردِ `/install`.
- LiteSpeed: فایلِ استاتیک (`.js/.css`) را مستقیم سرو می‌کند و مسیرِ زنده‌ای که به `.js`
  ختم شود را ۴۰۴ می‌دهد.

## مشکلاتی که پیدا و رفع شدند (به ترتیب)

### ۱) خطای ۵۰۰ روی `/install` — نبودِ APP_KEY
- **علامت:** لاگ پر از `MissingAppKeyException`؛ هر صفحه (حتی `/install`) ۵۰۰.
- **ریشه:** میدلورِ `EncryptCookies` (گروهِ web) بدونِ `APP_KEY` کرش می‌کند؛ پس کاربرِ
  بدونِ SSH هرگز به فرمِ نصب نمی‌رسد و `key:generate`ِ داخلِ نصب‌کننده اجرا نمی‌شود.
- **رفع:** `app/Providers/EnsureAppKeyServiceProvider.php` — هنگامِ بوت اگر کلید خالی بود
  یک کلیدِ یکتا می‌سازد، در `.env` می‌نویسد و برای همان درخواست هم در config می‌گذارد.
  اولِ `bootstrap/providers.php` ثبت شده. (idempotent)

### ۲) خطای ۵۰۰ روی `/install` — نشستِ دیتابیسی روی نصبِ تازه
- **علامت:** روی نصبِ تازه (دیتابیسِ خالی) `/install` ۵۰۰ می‌داد.
- **ریشه:** `.env.shared-host.example` با `SESSION_DRIVER=database`/`CACHE_STORE=database`
  می‌آمد؛ نصب‌کننده باید پیش از ساختِ جدول‌ها بالا بیاید، ولی `StartSession` نمی‌تواند در
  جدولِ `sessions`ِ ناموجود بنویسد. (تجربی: `database` → ۵۰۰، `file` → ۲۰۰)
- **رفع:** درایورهای فایلی در نمونه: `SESSION_DRIVER=file`, `CACHE_STORE=file`,
  `QUEUE_CONNECTION=sync`.

### ۳) اتصالِ دیتابیس — `127.0.0.1` بسته است
- **علامت:** با `DB_HOST=127.0.0.1` خطای «Establishing tcp connections on remote port
  3306 has been disabled».
- **ریشه:** هاست اتصالِ TCP به MySQL را بسته؛ فقط سوکتِ یونیکس کار می‌کند.
- **رفع:** همیشه `DB_HOST=localhost`. (پیش‌فرضِ فرمِ نصب و نمونه هم `localhost`.)

### ۴) «key too long» روی MyISAM
- **علامت:** خطای «Specified key was too long; max key length is 1000 bytes» هنگامِ نصب.
- **ریشه:** utf8mb4 + varchar(255) ایندکس‌دار روی MyISAM از حدِ ۱۰۰۰ بایت رد می‌شود.
- **رفع:** `Schema::defaultStringLength(191)` در `AppServiceProvider::boot()`.

### ۵) مشکلِ اصلی و سمج: «فرمِ ورود فقط ری‌لود می‌شود» — هستهٔ Livewire بار نمی‌شود
- **علامت:** بدونِ ۵۰۰، بدونِ هیچ پیامی؛ رمزِ اشتباه هم خطا نمی‌داد و فقط صفحه ری‌لود
  می‌شد. (یعنی فرم اصلاً به سرور ارسال نمی‌شد.)
- **تشخیص:** با خواندنِ مستقیمِ صفحهٔ زنده دیده شد که src به `/livewire/livewire.min.js`
  اشاره دارد (مسیرِ زنده) که روی این هاست **۴۰۴** است؛ در نتیجه `window.Livewire` تعریف
  نمی‌شد و فرمِ Filament بی‌جاوااسکریپت فقط ری‌لود می‌کرد. فایلِ فیزیکیِ
  `/vendor/livewire/livewire.js` و `manifest.json` روی سرور ۲۰۰ بودند، ولی برنامه سراغشان
  نمی‌رفت.
- **ریشه:** تشخیصِ خودکارِ Livewire (`usePublishedAssetsIfAvailable`) برای سوییچ به
  assetِ منتشرشده، `public_path('vendor/livewire/manifest.json')` را چک می‌کند؛ و
  `public_path()` روی این هاست فقط وقتی درست است که `APP_PUBLIC_PATH=public_html` باشد.
  حتی با آن هم قابل‌اعتماد نبود.
- **رفع (دو تکه، هر دو لازم):**
  - **الف)** publishِ فایل‌های Livewire در `public/vendor/livewire/` (در
    `post-autoload-dump`ِ composer و کامیت‌شده در `public/`).
  - **ب)** اجبارِ آدرس در `AppServiceProvider::boot()`:
    ```php
    if (blank(config('livewire.asset_url'))) {
        $f = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
        config(['livewire.asset_url' => '/vendor/livewire/' . $f]);
    }
    ```
    این آدرسِ اسکریپت را **قطعاً** به فایلِ فیزیکی می‌بندد، مستقل از `public_path`/تشخیص.
- **تأیید:** پس از این، صفحهٔ لاگین به `/vendor/livewire/livewire.min.js` اشاره می‌کند و
  ورود کار می‌کند (رمزِ اشتباه هم حالا پیامِ خطای درست می‌دهد).

### ۶) نکتهٔ استقرار — انتقالِ کاملِ public
- کلِ محتوای `public/` باید در `public_html/` باشد (نه فقط `index.php`): `js/`, `css/`,
  `fonts/`, `images/`, و مخصوصاً `vendor/livewire/`.

## چک‌لیستِ نصبِ تمیز (بدونِ SSH)
1. بستهٔ آخر را اکسترکت کن؛ **کلِ محتوای `public` را در `public_html` بگذار**.
2. `/install` را باز کن (کلید خودکار ساخته می‌شود) و ویزارد را کامل کن؛ آدرسِ دیتابیس
   = **`localhost`**. مطمئن شو `.env` مقدارِ `APP_PUBLIC_PATH=public_html` دارد.
3. `/admin/login` — فرم باید کار کند. اگر ری‌لودِ خالی داد: چک کن
   `https://…/vendor/livewire/livewire.min.js` = ۲۰۰ و صفحهٔ لاگین به همان اشاره کند.
4. `APP_DEBUG=false` بماند (امنیت).

## نکاتِ امنیتی
- `.env` بیرونِ وب‌روت است و هرگز کامیت نمی‌شود (تاریخچهٔ گیت هم تمیز است).
- هیچ مسیرِ تشخیصیِ باز (`/__debug/...`) در نسخهٔ عمومی نباشد.
- `/install` پس از ساختِ اولین کاربر بی‌اثر می‌شود.
- `APP_DEBUG=true` روی اینترنت ممنوع (نشتِ اطلاعات).
