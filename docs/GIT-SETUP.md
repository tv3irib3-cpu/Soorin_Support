# راه‌اندازی گیت‌هاب و کار با Claude Code

## ۱. ساخت مخزن

در گیت‌هاب یک ریپازیتوری **Private** با نام `soorin-support` بساز.
هیچ فایلی (README/gitignore) موقع ساخت اضافه نکن — پروژه خودش دارد.

## ۲. اتصال پروژه محلی

در پوشه پروژه:

```bash
git init
git branch -M main
git add .
git commit -m "chore: راه‌اندازی اولیه پروژه — فاز ۰"
git remote add origin https://github.com/<username>/soorin-support.git
git push -u origin main
```

**قبل از اولین کامیت مطمئن شو `.env` ساخته نشده یا در `.gitignore` هست.**
بررسی سریع:

```bash
git status --short | grep -i "\.env$"     # نباید چیزی نشان دهد
```

## ۳. احراز هویت برای پوش

گیت‌هاب رمز عبور معمولی را نمی‌پذیرد. یکی از دو روش:

**الف) Personal Access Token**
Settings ← Developer settings ← Personal access tokens ← Fine-grained tokens
دسترسی: فقط همین مخزن، مجوز Contents = Read and write.
هنگام پوش، توکن را به‌جای رمز وارد کن.

**ب) GitHub CLI** (ساده‌تر و برای Claude Code راحت‌تر)

```bash
gh auth login
```

## ۴. کار با Claude Code

```bash
cd soorin-support
claude
```

Claude Code به‌صورت خودکار `CLAUDE.md` را می‌خواند. در شروع هر جلسه بگو:

> `docs/PROJECT_STATE.md` و `docs/PHASE-1-BRIEF.md` را بخوان و فاز ۱ را شروع کن.

### نکات کار با Claude Code

- اجازه اجرای دستور و ویرایش فایل را در همان جلسه بده تا مجبور نباشد هر بار بپرسد.
- برای کامیت: از او بخواه پس از هر بخش کامل‌شده کامیت بزند، نه در انتهای همه‌چیز.
- اگر جلسه طولانی شد و context پر شد، جلسه جدید باز کن؛ `PROJECT_STATE.md`
  حافظه پروژه است و ادامه کار از روی آن ممکن است.
- بعد از هر پیشرفت مهم، از او بخواه بخش «گزارش پیشرفت» را در
  `docs/PROJECT_STATE.md` به‌روز کند.

## ۵. استقرار روی سرور

```bash
git clone https://github.com/<username>/soorin-support.git
cd soorin-support
cp .env.example .env && nano .env
bash scripts/install.sh
```

به‌روزرسانی بعدی روی سرور:

```bash
git pull && docker compose exec -T app php artisan migrate --force && docker compose exec -T app php artisan optimize
```

## ۶. اگر گیت‌هاب از سرور در دسترس نبود

گزینه جایگزین: **Gitea** روی همان ESXi به‌عنوان مخزن داخلی.
در این حالت فقط آدرس `origin` عوض می‌شود و بقیه روند یکسان است.
