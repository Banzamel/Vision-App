#!/usr/bin/env bash
#
# server-update.sh — uruchamiany PO każdym wysłaniu kodu z PhpStorma (SFTP).
# Reinstalls composer deps, runs migrations, refreshes caches, restarts workers.
#
# Nie ma już skryptu upload.sh — wysyłką zajmuje się deployment PhpStorma
# (laravel-vision/ → private/, react-vision/dist/ → public_html/), więc ten skrypt
# odpalamy ręcznie po stronie serwera:
#
#   ssh webmaster@vision.banzamel.pl \
#       'bash /home/webmaster/web/vision.banzamel.pl/private/deploy/server-update.sh'
#
# Idempotent.
#
set -euo pipefail

LARAVEL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="${PHP:-php8.4}"

cd "$LARAVEL_ROOT"

echo "==> Vision server-update"
echo "    LARAVEL_ROOT: $LARAVEL_ROOT"
echo

# 1. Composer (production deps only) — wymuszamy PHP 8.4
echo "==> [1/5] composer install --no-dev (via $PHP)"
COMPOSER_BIN="$(command -v composer || true)"
if [[ -z "$COMPOSER_BIN" ]]; then
    echo "    ERROR: composer not on PATH"
    exit 1
fi
$PHP "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

# 2. Migrations (no-op when already applied)
echo
echo "==> [2/5] Database migrations"
if grep -q '^APP_INSTALLED=true' .env 2>/dev/null; then
    $PHP artisan migrate --force
else
    echo "    APP_INSTALLED=false — skipping migrate (installer wizard hasn't run yet)."
fi

# 3. Cache refresh
echo
echo "==> [3/5] Refreshing caches"
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
$PHP artisan event:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# 4. Queue workers — pick up new code
echo
echo "==> [4/5] Restarting queue workers"
$PHP artisan queue:restart

# 5. Reverb — supervisor restart (needs sudo, so skipped automatically if no perms)
echo
echo "==> [5/5] Restarting Reverb (WebSocket)"
if command -v supervisorctl >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo supervisorctl restart vision-reverb || echo "    (vision-reverb not registered yet — skip)"
else
    echo "    Skipping — no passwordless sudo. Run manually:"
    echo "      sudo supervisorctl restart vision-reverb"
fi

echo
echo "==> server-update complete."
