#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/lumiis}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
RUN_BUILD="${RUN_BUILD:-1}"
RUN_SEED="${RUN_SEED:-1}"
RUN_STORAGE_LINK="${RUN_STORAGE_LINK:-1}"
QUEUE_SERVICE="${QUEUE_SERVICE:-lumiis-queue.service}"
SCHEDULER_TIMER="${SCHEDULER_TIMER:-lumiis-scheduler.timer}"

cd "${APP_DIR}"

if [ ! -f artisan ]; then
    echo "artisan was not found in ${APP_DIR}" >&2
    exit 1
fi

cleanup() {
    "${PHP_BIN}" artisan up || true
}

trap cleanup EXIT

"${PHP_BIN}" artisan down --retry=60 || true

"${COMPOSER_BIN}" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [ "${RUN_BUILD}" = "1" ]; then
    "${NPM_BIN}" ci
    "${NPM_BIN}" run build
fi

"${PHP_BIN}" artisan migrate --force

if [ "${RUN_SEED}" = "1" ]; then
    "${PHP_BIN}" artisan db:seed --class=WorldSeeder --force
fi

if [ "${RUN_STORAGE_LINK}" = "1" ]; then
    "${PHP_BIN}" artisan storage:link || true
fi

"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan view:cache

if command -v systemctl >/dev/null 2>&1; then
    systemctl restart "${QUEUE_SERVICE}" || true
    systemctl start "${SCHEDULER_TIMER}" || true
    systemctl reload nginx || true
    systemctl reload php8.3-fpm || true
fi

"${PHP_BIN}" artisan up
trap - EXIT

echo "Deployment finished successfully."
