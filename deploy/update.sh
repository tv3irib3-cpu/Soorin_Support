#!/usr/bin/env bash
#
# به‌روزرسانیِ سامانه با یک دستور (برای نصبِ گیتی، روی سرورِ اختصاصی یا هاستِ
# دارای SSH). کد را می‌کشد، وابستگی‌ها را نصب، جدول‌ها را مهاجرت و کش را پاک می‌کند.
#
# اجرا از داخلِ پوشهٔ پروژه:  bash deploy/update.sh
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

[ -f artisan ] || { echo "artisan پیدا نشد؛ از داخلِ پوشهٔ پروژه اجرا کنید." >&2; exit 1; }

PHP_BIN="$(command -v php || echo /usr/bin/php)"
COMPOSER_BIN="$(command -v composer || echo composer)"

echo "- گرفتنِ آخرین کد از گیت ..."
git pull --ff-only

echo "- نصبِ وابستگی‌ها ..."
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "- مهاجرتِ دیتابیس ..."
"$PHP_BIN" artisan migrate --force

echo "- پاک‌سازیِ کش ..."
"$PHP_BIN" artisan optimize:clear

echo "✓ به‌روزرسانی کامل شد — نسخهٔ فعلی: $(cat VERSION 2>/dev/null || echo '—')"
