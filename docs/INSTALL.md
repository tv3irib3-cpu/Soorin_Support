# راهنمای نصب — سامانه پشتیبانی سورین (CRM)

این سامانه «دوحالته» است: با یک کد، هم روی **سرور اختصاصی** (اوبونتو/دبیان) و هم
روی **هاست اشتراکی** (DirectAdmin/cPanel) اجرا می‌شود. تنها تفاوتِ دو محیط، فایل
`.env` است.

> پیش از شروع، سه چیز را آماده داشته باشید: یک **دیتابیس MySQL/MariaDB**، دسترسی
> برای اجرای دستور (SSH یا ترمینالِ هاست)، و یک **دامنه یا زیردامنه** برای سامانه.

فهرست:
- [الف) سرور اختصاصی (اوبونتو/دبیان — بدون Docker)](#الف-سرور-اختصاصی)
- [ب) سرور اختصاصی با Docker](#ب-سرور-اختصاصی-با-docker)
- [ج) هاست اشتراکی DirectAdmin/cPanel](#ج-هاست-اشتراکی-directadmin)
- [پس از نصب — امنیت و نگهداری](#پس-از-نصب)

پیش‌نیازهای PHP (هر سه حالت): **PHP ۸.۲ یا بالاتر** با افزونه‌های
`mbstring`, `intl`, `gd`, `zip`, `bcmath`, `pdo_mysql`, `fileinfo`, `curl`, `openssl`
و `memory_limit` حداقل **۲۵۶M** (برای تولید PDF).

---

## الف) سرور اختصاصی

روی یک سرورِ تازهٔ اوبونتو/دبیان با دسترسی root:

```bash
# ۱) بسته‌های سیستم
sudo apt update
sudo apt install -y nginx mariadb-server git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-zip php8.3-bcmath php8.3-curl php8.3-gd php8.3-intl
# اگر «بکاپ روی پوشهٔ شبکهٔ SMB» می‌خواهید: sudo apt install -y smbclient

# ۲) گرفتن کد
sudo git clone https://github.com/tv3irib3-cpu/Soorin_Support.git /var/www/soorin-support
cd /var/www/soorin-support

# ۳) Composer و وابستگی‌ها
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
sudo composer install --no-dev --optimize-autoloader --no-interaction

# ۴) دیتابیس
sudo mysql <<'SQL'
CREATE DATABASE soorin_support CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'soorin'@'localhost' IDENTIFIED BY 'یک-رمز-قوی';
CREATE USER 'soorin'@'127.0.0.1' IDENTIFIED BY 'یک-رمز-قوی';
GRANT ALL PRIVILEGES ON soorin_support.* TO 'soorin'@'localhost';
GRANT ALL PRIVILEGES ON soorin_support.* TO 'soorin'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# ۵) پیکربندی
sudo cp .env.example .env
sudo nano .env      # APP_URL، DB_DATABASE/USERNAME/PASSWORD را تنظیم کنید
sudo php artisan key:generate --force
sudo php artisan migrate --force --seed        # جدول‌ها + ادمینِ پیش‌فرض
sudo php artisan storage:link

# ۶) دسترسی فایل‌ها
sudo chown -R www-data:www-data /var/www/soorin-support
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

**وب‌سرور (Nginx):** ریشهٔ سایت باید پوشهٔ `public` باشد، نه ریشهٔ پروژه:

```nginx
server {
    listen 80;
    server_name support.dpst.ir;
    root /var/www/soorin-support/public;
    index index.php;
    client_max_body_size 50M;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```
سپس: `sudo ln -s /etc/nginx/sites-available/soorin-support /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx`

**زمان‌بند (برای بکاپِ خودکار و انقضای قرارداد):**
```bash
sudo bash deploy/install-scheduler.sh
```
این یک تایمرِ systemd می‌سازد که هر دقیقه `schedule:run` را اجرا می‌کند و خودش
اتصالِ دیتابیس را هم می‌سنجد.

**SSL/HTTPS:** بعد از نصب، دستیارِ SSL را نصب کنید تا از پنل (بخش «SSL») گواهی
بگیرید:
```bash
sudo bash deploy/install-ssl-helper.sh
```
سپس در پنل، برای سرورِ داخلی گواهی self-signed و برای دامنهٔ عمومی Let's Encrypt
بگیرید و «اجبار HTTPS» را روشن کنید.

---

## ب) سرور اختصاصی با Docker

اگر Docker دارید، ساده‌ترین راه است:
```bash
git clone https://github.com/tv3irib3-cpu/Soorin_Support.git soorin-support
cd soorin-support
cp .env.example .env
nano .env                 # رمزهای دیتابیس را عوض کنید
bash scripts/install.sh   # کانتینرها + composer + migrate --seed + optimize
```
پنل روی `http://localhost:8080`.

---

## ج) هاست اشتراکی DirectAdmin

این حالت مخصوصِ وضعیتی است که یک هاست با DirectAdmin دارید و می‌خواهید سامانه را
روی یک **زیردامنه** بالا بیاورید (مثلاً `support.dpst.ir`).

> **نکتهٔ کلیدی امنیت:** فقط پوشهٔ `public` باید از اینترنت قابل‌دسترس باشد. بقیهٔ
> پروژه (شاملِ `.env` که رمزها در آن است) باید **بیرونِ** ریشهٔ وب بماند.

### گام ۱ — زیردامنه بسازید
در DirectAdmin → **Subdomain Management** → زیردامنه (مثلاً `crm`) را بسازید.
DirectAdmin ممکن است دو گزینه برای ریشهٔ وب بدهد:
- `/domains/crm.dpst.ir/public_html` ← **این را انتخاب کن** (زیردامنه یک پوشهٔ جدا و
  مستقل از سایتِ اصلی دارد؛ تمیزتر و امن‌تر).
- `/domains/dpst.ir/public_html/crm` (تودرتو داخلِ سایتِ اصلی).

در ادامه فرض می‌کنیم گزینهٔ اول را زده‌ای؛ پس ریشهٔ وب `~/domains/crm.dpst.ir/public_html` است.

### گام ۲ و ۳ — چیدمانِ فایل‌ها (بدونِ SSH، تمیز)

چون DirectAdmin پوشهٔ `~/domains/crm.dpst.ir/public_html` را به‌عنوان ریشهٔ وب ساخته،
کلِ برنامه را **یک پله بالاتر** می‌گذاریم و فقط محتوای `public` را داخلِ `public_html`:

```
~/domains/crm.dpst.ir/
├── app/  bootstrap/  config/  database/  routes/  vendor/  storage/ …   ← بدنهٔ برنامه
├── artisan   .env
└── public_html/   ← ریشهٔ وب (محتوای پوشهٔ public لاراول)
    ├── index.php   (دو مسیرش را به «../» اصلاح کن)
    ├── .htaccess
    └── …
```

مراحل با **File Manager** (بدونِ SSH):
1. بستهٔ کامل (همراهِ `vendor`) را که با `deploy/build-package.sh` ساخته‌ای، در
   `~/domains/crm.dpst.ir/` آپلود و اکسترکت کن.
2. **محتوای** پوشهٔ `public` را به `public_html` منتقل کن (خودِ پوشهٔ `public` خالی می‌ماند/حذف).
3. در `public_html/index.php` دو خط را از `__DIR__.'/../…'` به `__DIR__.'/../…'` نگه‌دار
   (چون حالا `public_html` مستقیماً زیرِ ریشهٔ برنامه است، همان `../` درست است:
   `require __DIR__.'/../vendor/autoload.php';` و `$app = require_once __DIR__.'/../bootstrap/app.php';`).
4. در `.env` بگذار: **`APP_PUBLIC_PATH=public_html`** — تا `public_path()` (و در نتیجه
   لوگوهای «شخصی‌سازی» و `storage:link`) به `public_html` اشاره کند. بدونِ این، لوگوهای
   آپلودی روی وب دیده نمی‌شوند.

> اگر SSH داری، ساده‌تر: کلِ ریپو را در `~/domains/crm.dpst.ir/app` کلون کن، بعد
> `public_html` را حذف و `ln -s ../app/public public_html` بزن؛ آن‌وقت به `APP_PUBLIC_PATH`
> هم نیازی نیست.

### گام ۴ — دیتابیس
در DirectAdmin → **MySQL Management**. دو گزینه دارید:

- **گزینهٔ الف (توصیه‌شده): یک دیتابیسِ جدید بسازید** برای CRM و یوزر/رمزش را
  بردارید.
- **گزینهٔ ب: همان دیتابیسِ فعلیِ سایت را استفاده کنید** — چون سامانه از **پیشوندِ
  جدول** پشتیبانی می‌کند، جدول‌های CRM با پیشوند از جدول‌های سایتِ فعلی جدا می‌مانند
  و قاطی نمی‌شوند. کافی است در `.env` مقدار `DB_TABLE_PREFIX=soorin_` را بگذارید.

  > **بکاپ در حالتِ دیتابیسِ مشترک امن است:** وقتی پیشوند تنظیم شده باشد، «پشتیبان‌گیری»
  > فقط جدول‌های `soorin_` را می‌گیرد و «بازیابی» هم فقط همان‌ها را برمی‌گرداند —
  > جدول‌های سایتِ دیگر (مثلاً وردپرس) هرگز حذف یا بازنویسی نمی‌شوند. با این حال،
  > برای سادگی و ایمنیِ بیشتر، **گزینهٔ الف (دیتابیسِ جدا) توصیه می‌شود.**

### گام ۵ — فایل `.env`
```bash
cd ~/domains/dpst.ir/crm_app
cp .env.shared-host.example .env
nano .env
```
این‌ها را تنظیم کنید:
```
APP_URL=https://support.dpst.ir
DB_DATABASE=نام_دیتابیس
DB_USERNAME=یوزر_دیتابیس
DB_PASSWORD=رمز_دیتابیس
# اگر دیتابیسِ فعلی را به اشتراک می‌گذارید:
DB_TABLE_PREFIX=soorin_
```
(در این نمونه از قبل `CACHE_STORE`/`QUEUE_CONNECTION`/`SESSION_DRIVER` روی
`database` است چون Redis روی اشتراکی نیست.)

### گام ۶ — نصب نهایی (با SSH)
```bash
php artisan key:generate --force
php artisan migrate --force --seed
php artisan storage:link || true
chmod -R 775 storage bootstrap/cache
```

### گام ۷ — کرون‌جابِ زمان‌بند
در DirectAdmin → **Cron Jobs** یک کرونِ هر-دقیقه‌ای بسازید (مسیرِ php هاست را از
پشتیبانی بپرسید؛ معمولاً `/usr/local/bin/php`):
```
* * * * * /usr/local/bin/php /home/USER/domains/dpst.ir/crm_app/artisan schedule:run >/dev/null 2>&1
```
بدونِ این، بکاپِ خودکار و انقضای قرارداد اجرا نمی‌شود و چراغِ «زمان‌بندِ سرور» در
صفحهٔ پشتیبان‌گیری قرمز می‌ماند.

### گام ۸ — SSL
در DirectAdmin → **SSL Certificates** → Let's Encrypt را برای `support.dpst.ir`
با یک کلیک فعال کنید، و گزینهٔ Force HTTPS را روشن کنید.

---

## ج-۲) هاست اشتراکیِ بدونِ SSH — روشِ «vendor + import»

اگر هاست اصلاً SSH/ترمینال ندارد، نمی‌توانی `composer install`، `key:generate` و
`migrate` را روی هاست اجرا کنی. راهِ حل: **کارهایی که به خطِ فرمان نیاز دارند را
روی کامپیوترِ خودت انجام بده، بعد فقط فایلِ آماده را آپلود کن** — یعنی «build» را
از «run» جدا می‌کنی.

**روی کامپیوترِ خودت** (که PHP + Composer دارد، مثلِ Laragon):
```bash
composer install --no-dev --optimize-autoloader   # پوشهٔ vendor کامل ساخته می‌شود
cp .env.shared-host.example .env
# .env را برای هاست تنظیم کن: APP_URL، DB_*، و در صورتِ دیتابیسِ مشترک DB_TABLE_PREFIX=soorin_
php artisan key:generate                            # کلید را داخلِ همین .env می‌گذارد
php artisan migrate --seed                          # روی یک دیتابیسِ محلی، جدول‌ها را می‌سازد
```
سپس آن دیتابیسِ محلی را به‌صورتِ یک فایلِ `.sql` **اکسپورت** کن (phpMyAdmin →
Export، یا `mysqldump`). اگر پیشوند گذاشته‌ای، جدول‌های خروجی از قبل `soorin_` دارند.

**روی هاست (فقط File Manager و phpMyAdmin):**
1. کلِ پروژه را **همراهِ پوشهٔ `vendor/` و فایلِ `.env`** فشرده (zip) و در
   `~/domains/dpst.ir/crm_app` آپلود و اکسترکت کن.
2. فایلِ `.sql` را در **phpMyAdmin** روی دیتابیسِ هاست **Import** کن → همهٔ جدول‌ها
   بدونِ نیاز به `migrate` ساخته می‌شوند.
3. ریشهٔ وبِ زیردامنه را مثلِ بخشِ قبل به `public` وصل کن.
4. با File Manager روی `storage`, `bootstrap/cache` و `public/branding` دسترسیِ
   نوشتن (۷۷۵) بده.

**مزیت‌ها:** بدونِ SSH کار می‌کند؛ سریع و قطعی (همان وابستگی‌هایی که تست کرده‌ای)؛
کمترین چیز روی هاست اجرا می‌شود.
**محدودیت:** هر به‌روزرسانی باید دوباره روی سیستمِ خودت build و آپلود شود (و اگر
migrationِ تازه بود، SQLاش را import کنی). یعنی به‌روزرسانی دستی‌تر است — برای همین
اگر بشود، **فعال‌کردنِ SSH بهتر است.**

---

## به‌روزرسانی به نسخهٔ جدید

### روشِ تک‌کلیکیِ بدونِ SSH (مثلِ وردپرس) — توصیه‌شده برای هاستِ اشتراکی

سامانه می‌تواند مثلِ وردپرس، از داخلِ پنل و **بدونِ SSH/composer/git** به‌روز شود.
همه‌چیز با خودِ PHP انجام می‌شود: دانلودِ یک بستهٔ آمادهٔ ZIP (همراهِ `vendor`) از یک
آدرسِ عمومی، بازکردن با ZipArchive، کپیِ فایل‌ها، و مهاجرتِ دیتابیس «درجا».

راه‌اندازی (یک‌بار):
1. روی یک محیطِ لینوکسی/WSL/CI بستهٔ به‌روزرسانی را بساز:
   ```bash
   bash deploy/build-package.sh https://آدرسِ-عمومیِ-تو/updates
   ```
   خروجی در `dist/`: فایلِ `soorin-support-<نسخه>.zip` و `latest.json`.
2. آن دو فایل را روی یک **آدرسِ عمومی** آپلود کن (یک پوشه روی هاستِ خودت، یا هر
   فضای عمومیِ دیگر).
3. در `.env` سامانه:
   ```
   APP_UPDATE_MANIFEST=https://آدرسِ-عمومیِ-تو/updates/latest.json
   ```

از این پس هر بار نسخهٔ تازه‌ای منتشر کردی (build-package.sh را دوباره اجرا و دو فایل
را جایگزین کن)، در پنلِ سامانه کنارِ منوی «به‌روزرسانی» یک **نقطهٔ قرمز** ظاهر می‌شود و
با **یک کلیک** روی «به‌روزرسانیِ یک‌کلیکی» نصب می‌شود (پیش از آن پشتیبانِ خودکار گرفته
می‌شود). همچنین می‌توانی فایلِ zip را مستقیم در «به‌روزرسانی با فایل» آپلود کنی.

> نیازمندی‌های هاست برای این روش: افزونهٔ `zip` فعال، و `allow_url_fopen` یا cURL
> (تقریباً همیشه هست). چون هیچ دستورِ شل اجرا نمی‌شود، روی DirectAdminِ بدونِ SSH هم کار می‌کند.

### با SSH (سرور اختصاصی) — تک‌دستوری
```bash
cd مسیرِ-پروژه
bash deploy/update.sh
```
این اسکریپت: `git pull` → `composer install` → `migrate --force` → `optimize:clear`.

**آیا می‌شود کاملاً خودکار باشد؟** بله، چند سطح دارد:
- **نیمه‌خودکار (توصیه‌شده):** همان `deploy/update.sh` — یک دستور، هر وقت خواستی.
- **خودکارِ زمان‌بندی‌شده:** یک کرون‌جاب که مثلاً هر شب اجرا شود:
  `30 3 * * * cd /home/USER/domains/dpst.ir/crm_app && bash deploy/update.sh >> storage/logs/update.log 2>&1`
- **خودکار با هر push (Webhook):** یک وب‌هوکِ گیت‌هاب که پس از هر push اسکریپت را
  اجرا کند. قوی ولی پیکربندیِ بیشتری دارد.

> توصیه: برای محیطِ تولید، به‌روزرسانیِ **دستیِ تک‌دستوری** امن‌تر است تا خودکارِ کور؛
> چون هر آپدیت را وقتی خودت آماده‌ای اعمال می‌کنی و اگر مشکلی بود، از صفحهٔ
> «پشتیبان‌گیری» می‌توانی برگردی. (بدونِ SSH، به‌روزرسانی طبقِ روشِ «vendor + import»
> بالا انجام می‌شود.)

---

## پس از نصب

ورود: `https://support.dpst.ir/admin`

**۱) رمزِ ادمینِ پیش‌فرض را فوراً عوض کنید.** بعد از `--seed` یک حساب ساخته می‌شود:
`admin@dpst.ir` با رمز `password`. بلافاصله وارد شوید، رمز را از پروفایل عوض کنید،
و حسابِ نمونهٔ `karshenas@dpst.ir` را در صورت نیاز حذف کنید. (اگر می‌خواهید بدونِ
دادهٔ نمونه شروع کنید، به‌جای `--seed` فقط `migrate` بزنید و ادمین را دستی بسازید.)

**۲) امنیتِ اینترنت:**
- `APP_DEBUG=false` و `APP_ENV=production` بماند (پیش‌فرضِ نمونه‌ها همین است).
- روی HTTPS مقدار `SESSION_SECURE_COOKIE=true` را در `.env` بگذارید.
- ریپازیتوری **Private** بماند و `.env` هرگز کامیت نشود.
- دسترسیِ بکاپ/SSL/شخصی‌سازی فقط برای «ادمینِ پشتیبان» است؛ کاربرانِ خریدار اصلاً
  به پنل راه ندارند.

**۳) بکاپ:** از صفحهٔ «پشتیبان‌گیری» بکاپِ دستی بگیرید یا زمان‌بندیِ خودکار را روشن
کنید (به کرون/زمان‌بندِ گام‌های بالا وابسته است).

**۴) به‌روزرسانی:** بخشِ [«به‌روزرسانی به نسخهٔ جدید»](#به‌روزرسانی-به-نسخهٔ-جدید)
بالا را ببین (با SSH تک‌دستوری با `deploy/update.sh`؛ بدونِ SSH با روشِ vendor+import).
