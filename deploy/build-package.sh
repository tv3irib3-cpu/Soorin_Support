#!/usr/bin/env bash
#
# ساختِ «بستهٔ کاملِ به‌روزرسانی» برای آپدیتِ تک‌کلیکیِ بدونِ SSH (مثلِ وردپرس).
#
# خروجی در پوشهٔ dist/:
#   - soorin-support-<version>.zip   (کدِ کامل + vendor، بدونِ .env و داده)
#   - latest.json                    (مانیفست: نسخه، آدرسِ zip، sha256)
#
# این اسکریپت را روی یک محیطِ لینوکسی/WSL/CI اجرا کن (به composer و zip نیاز دارد)،
# نه روی هاستِ اشتراکی. بعد dist/soorin-support-<v>.zip و dist/latest.json را روی یک
# آدرسِ عمومی آپلود کن و APP_UPDATE_MANIFEST را به آدرسِ latest.json بگذار.
#
# اجرا:  bash deploy/build-package.sh https://YOUR-HOST/updates
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

BASE_URL="${1:-https://YOUR-HOST/updates}"    # آدرسِ عمومیِ میزبانیِ بسته‌ها (بدونِ / پایانی)
BASE_URL="${BASE_URL%/}"
VERSION="$(tr -d '[:space:]' < VERSION)"
NAME="soorin-support-$VERSION"
DIST="$APP_DIR/dist"
STAGE="$DIST/$NAME"

echo "- نصبِ وابستگی‌های production (vendor) ..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "- آماده‌سازیِ فایل‌های بسته ..."
rm -rf "$STAGE"
mkdir -p "$STAGE"

# فقط چیزهایی که برای اجرا لازم است — بدونِ .env، .git، داده و کشِ محلی، و تست.
for item in app bootstrap config database lang public resources routes storage vendor \
            artisan composer.json composer.lock VERSION deploy .env.shared-host.example; do
    [ -e "$item" ] && cp -a "$item" "$STAGE/"
done

# پاک‌سازیِ داده و کشِ محلی از داخلِ بسته (ساختارِ پوشه می‌ماند، محتوا نه).
rm -rf "$STAGE/storage/app/backups"/* \
       "$STAGE/storage/logs"/* \
       "$STAGE/storage/framework/cache"/* \
       "$STAGE/storage/framework/sessions"/* \
       "$STAGE/storage/framework/views"/* 2>/dev/null || true

echo "- ساختِ ZIP ..."
cd "$DIST"
rm -f "$NAME.zip"
zip -rq "$NAME.zip" "$NAME"
rm -rf "$STAGE"

SHA="$(sha256sum "$NAME.zip" | cut -d' ' -f1)"

cat > latest.json <<JSON
{
  "version": "$VERSION",
  "zip": "$BASE_URL/$NAME.zip",
  "sha256": "$SHA",
  "notes": "نسخهٔ $VERSION"
}
JSON

echo
echo "✓ ساخته شد در dist/:"
echo "   $NAME.zip"
echo "   latest.json   (zip → $BASE_URL/$NAME.zip)"
echo "   sha256: $SHA"
echo
echo "این دو فایل را روی «$BASE_URL» آپلود کن، و در .env سامانه:"
echo "   APP_UPDATE_MANIFEST=$BASE_URL/latest.json"
