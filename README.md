# سامانه خدمات و پشتیبانی — دریاپردازشگران سورین طبرستان

سامانه تحت وب ثبت تیکت، صدور فاکتور، مدیریت قرارداد پشتیبانی، انبار و پروژه.

| | |
|---|---|
| نسخه | 0.3.0 — فاز ۱ و ۲ |
| استک | Laravel 12 · Filament v4 · MariaDB 11 · Nginx · Redis |
| وب‌سایت | https://dpst.ir |

> وضعیت فعلی: **فاز ۱ (پشتیبانی، فاکتور، پرتال) و فاز ۲ (گزارش‌ها) تحویل شد.**
> برای دیدن نقشه کامل پروژه و فازهای بعدی، `docs/PROJECT_STATE.md` را بخوانید.

---

## ۱. نصب روی سرور اوبونتو (Docker) — روش پیشنهادی

```bash
git clone <repo-url> soorin-support
cd soorin-support
cp .env.example .env
nano .env                 # رمزهای دیتابیس را عوض کنید
bash scripts/install.sh
```

پس از نصب:

- پنل مدیریت: `http://SERVER-IP:8080`
- phpMyAdmin: `http://SERVER-IP:8081`

### به‌روزرسانی

```bash
git pull
docker compose exec -T app composer install --no-dev --optimize-autoloader
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
```

### بکاپ خودکار

```bash
crontab -e
0 2 * * * /path/to/soorin-support/scripts/backup.sh >> /var/log/soorin-backup.log 2>&1
```

---

## ۲. نصب روی هاست اشتراکی (DirectAdmin / cPanel)

### پیش‌نیازها — قبل از خرید هاست بررسی کنید

- PHP نسخه **۸.۲ یا بالاتر**
- افزونه‌های فعال: `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `pdo_mysql`, `fileinfo`
- `memory_limit` حداقل **256M** (تولید فاکتور PDF)
- امکان تعریف **Cron Job**
- ترجیحاً دسترسی SSH

### مراحل

1. فایل‌ها را در `domains/<domain>/` آپلود کنید (نه داخل `public_html`).
2. `.env.shared-host.example` را به `.env` کپی و اطلاعات دیتابیس را وارد کنید.
3. با SSH: `composer install --no-dev --optimize-autoloader`
   بدون SSH: نسخه‌ای که پوشه `vendor` را همراه دارد آپلود کنید.
4. `php artisan key:generate` و `php artisan migrate --force`
5. **تنظیم ریشه دامنه** — در DirectAdmin ریشه به‌صورت پیش‌فرض `public_html` است:

   با SSH:
   ```bash
   rm -rf public_html
   ln -s /home/USER/domains/DOMAIN/public public_html
   ```
   بدون SSH: محتوای پوشه `public` را داخل `public_html` منتقل کنید و در
   `public_html/index.php` دو مسیر `__DIR__.'/../'` را به `__DIR__.'/../DOMAIN/'` اصلاح کنید.

6. کرون‌جاب زمان‌بند:
   ```
   * * * * * cd /home/USER/domains/DOMAIN && php artisan schedule:run >> /dev/null 2>&1
   ```

در این حالت `CACHE_STORE`، `QUEUE_CONNECTION` و `SESSION_DRIVER` باید روی `database` بمانند (Redis در دسترس نیست).

---

## ۳. اجرای محلی برای تست (ویندوز — XAMPP یا Laragon)

```bash
composer install
copy .env.shared-host.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

سپس `http://127.0.0.1:8000`.

**هشدار:** ویندوز به بزرگی و کوچکی حروف در نام فایل حساس نیست، ولی سرور لینوکسی هست.
نام فایل‌ها و کلاس‌ها را دقیقاً مطابق استاندارد PSR-4 نگه دارید تا کد روی سرور هم اجرا شود.

---

## ۴. ساختار دیتابیس

| گروه | جدول‌ها |
|---|---|
| پایه | `settings` · `users` · `sessions` · `activity_logs` |
| مشتری | `customers` · `customer_contacts` |
| قرارداد | `contract_plans` · `contracts` |
| تیکت | `ticket_categories` · `tickets` · `ticket_messages` · `ticket_attachments` · `ticket_status_logs` |
| مالی | `invoices` · `invoice_items` · `payments` · `service_rates` |
| کالا و انبار | `item_categories` · `items` · `item_versions` · `warehouses` · `stock_lots` · `stock_movements` · `stock_balances` · `item_serials` |
| خرید و واردات | `suppliers` · `currencies` · `purchases` · `purchase_items` |
| سامانه و پروژه | `system_models` · `system_versions` · `system_bom_lines` · `projects` · `project_checklist_lines` · `customer_systems` · `customer_system_parts` |

### سه تصمیم طراحی که نباید تغییر کند

1. **موجودی و قیمت روی `item_versions` ثبت می‌شود، نه روی `items`.**
   موجودی کل کالا از جمع ورژن‌هایش به دست می‌آید.
2. **`system_versions` (نقشه) از `customer_systems` (سامانه اجراشده) جداست.**
   نقشه تغییر می‌کند، سوابق نصب‌شده دست‌نخورده می‌ماند.
3. **قیمت تمام‌شده از `stock_lots` با روش FIFO خوانده می‌شود.**
   هیچ رکورد `stock_movements` حذف نمی‌شود؛ اصلاح فقط با سند معکوس انجام می‌شود.

---

## ۵. امنیت

- `.env` هرگز کامیت نمی‌شود؛ ریپازیتوری **Private** نگه داشته شود.
- رمزهای پیش‌فرض `CHANGE_ME` باید قبل از اجرا عوض شوند.
- برای انتشار روی اینترنت حتماً SSL (Let's Encrypt) و ترجیحاً محدودسازی IP روی مسیر مدیریت.
- phpMyAdmin روی سرور عمومی نباید بدون محدودیت IP در دسترس باشد.

---

## ۶. ادامه توسعه با Claude Code

- `CLAUDE.md` — قواعد پروژه؛ Claude Code خودکار می‌خواندش
- `docs/PROJECT_STATE.md` — حافظه پروژه: نیازمندی‌ها، تصمیم‌ها، گزارش پیشرفت
- `docs/PHASE-1-BRIEF.md` — شرح کار فاز بعدی
- `docs/GIT-SETUP.md` — راه‌اندازی مخزن، توکن گیت‌هاب و روند کار

شروع جلسه:

```bash
cd soorin-support
claude
```

سپس: «`docs/PROJECT_STATE.md` و `docs/PHASE-1-BRIEF.md` را بخوان و فاز ۱ را شروع کن.»

---

© ۱۴۰۰ – ۱۴۰۵ شرکت دریاپردازشگران سورین طبرستان · dpst.ir
