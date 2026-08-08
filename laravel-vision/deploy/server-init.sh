#!/usr/bin/env bash
#
# server-init.sh — first-time setup on the production server.
# Run ONCE, after the very first upload, before opening the install wizard.
#
# This script is idempotent — re-running it is safe but rewrites caches.
#
# Usage (on the server):
#   bash /home/webmaster/web/vision.banzamel.pl/private/deploy/server-init.sh
#
set -euo pipefail

# Resolve the laravel root (parent of this deploy/ folder)
LARAVEL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_ROOT="$(cd "$LARAVEL_ROOT/.." && pwd)"
PUBLIC_HTML="$WEB_ROOT/public_html"
PHP="${PHP:-php8.4}"

echo "==> Vision server-init"
echo "    LARAVEL_ROOT: $LARAVEL_ROOT"
echo "    WEB_ROOT:     $WEB_ROOT"
echo "    PUBLIC_HTML:  $PUBLIC_HTML"
echo "    PHP:          $PHP"
echo

cd "$LARAVEL_ROOT"

# 1. PHP version + extensions sanity check ------------------------------------
echo "==> [1/8] Checking PHP version + extensions"
$PHP --version | head -1
for ext in pdo_mysql redis gd intl mbstring curl openssl fileinfo exif zip bcmath; do
    if ! $PHP -m | grep -qi "^$ext\$"; then
        echo "    MISSING extension: $ext (apt install $PHP-$ext)"
        exit 1
    fi
done
echo "    All required extensions present."

# 2. .env --------------------------------------------------------------------
echo
echo "==> [2/8] .env"
if [[ ! -f .env ]]; then
    # Szablon produkcyjny (deploy/env.production.example), NIE dev-owy .env.example —
    # ten drugi celuje w kontenery dockerowe (host `mysql-vision` itd.) i na serwerze nie zadziała.
    if [[ -f deploy/env.production.example ]]; then
        cp deploy/env.production.example .env
        echo "    Created .env from deploy/env.production.example — EDIT IT NOW with DB/Redis/VAPID/Reverb keys."
        echo "    Then re-run this script."
        exit 0
    else
        echo "    ERROR: no .env and no deploy/env.production.example. Cannot continue."
        exit 1
    fi
fi
echo "    .env present."

# 3. Composer install --------------------------------------------------------
echo
echo "==> [3/8] composer install --no-dev (via $PHP)"
COMPOSER_BIN="$(command -v composer || true)"
if [[ -z "$COMPOSER_BIN" ]]; then
    echo "    ERROR: composer not on PATH. Install with:"
    echo "      curl -sS https://getcomposer.org/installer | php"
    echo "      sudo mv composer.phar /usr/local/bin/composer"
    exit 1
fi
# Wymuszamy PHP 8.4 — system default to 8.3 i composer brałby je domyślnie.
$PHP "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

# 4. APP_KEY -----------------------------------------------------------------
echo
echo "==> [4/8] APP_KEY"
if grep -q '^APP_KEY=$' .env; then
    $PHP artisan key:generate --force
    echo "    APP_KEY generated."
else
    echo "    APP_KEY already set."
fi

# 5. Passport keys -----------------------------------------------------------
echo
echo "==> [5/8] Passport keys"
if [[ ! -f storage/oauth-private.key || ! -f storage/oauth-public.key ]]; then
    $PHP artisan passport:keys --force
    echo "    Passport keys generated."
else
    echo "    Passport keys already exist."
fi
# Passport (League oauth2-server) wymaga 600 dla private + 660 dla public,
# inaczej trigger_error E_USER_NOTICE i exception przy każdym request'cie.
chmod 600 storage/oauth-private.key
chmod 660 storage/oauth-public.key

# 6. Storage symlink (public_html/storage → laravel storage/app/public) ------
echo
echo "==> [6/8] Storage symlink"
if [[ -L "$PUBLIC_HTML/storage" ]]; then
    echo "    Symlink already in place."
elif [[ -e "$PUBLIC_HTML/storage" ]]; then
    echo "    WARNING: $PUBLIC_HTML/storage exists but is not a symlink — leaving alone."
else
    ln -s "$LARAVEL_ROOT/storage/app/public" "$PUBLIC_HTML/storage"
    echo "    Created: $PUBLIC_HTML/storage → $LARAVEL_ROOT/storage/app/public"
fi

# 7. Permissions -------------------------------------------------------------
echo
echo "==> [7/8] Permissions on storage/ + bootstrap/cache/"
chmod -R u+rwX storage bootstrap/cache
echo "    Done."

# 8. Cache config ------------------------------------------------------------
echo
echo "==> [8/8] Caching config / routes / views / events"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# Done -----------------------------------------------------------------------
echo
echo "==> server-init complete."
echo
echo "Next manual steps (one-time):"
echo "  1. Install nginx config from $LARAVEL_ROOT/deploy/nginx-vision.conf"
echo "     (HestiaCP custom template OR direct edit of"
echo "      /home/webmaster/conf/web/vision.banzamel.pl/nginx.ssl.conf_custom)"
echo "  2. Install supervisor entries from $LARAVEL_ROOT/deploy/supervisor-vision.conf"
echo "     sudo cp $LARAVEL_ROOT/deploy/supervisor-vision.conf /etc/supervisor/conf.d/vision.conf"
echo "     sudo supervisorctl reread && sudo supervisorctl update"
echo "  3. Install cron entry from $LARAVEL_ROOT/deploy/cron.txt:"
echo "     crontab -l | { cat; cat $LARAVEL_ROOT/deploy/cron.txt; } | crontab -"
echo "  4. Open https://vision.banzamel.pl/install and run the wizard."
