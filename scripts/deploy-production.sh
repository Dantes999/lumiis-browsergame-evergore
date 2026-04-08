#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/lumiis}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
NODE_BIN="${NODE_BIN:-node}"
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

if [ "$(id -u)" -eq 0 ]; then
    echo "Warning: deploy is running as root. Prefer a normal deploy user so Composer does not disable plugins for safety." >&2
fi

version_gte() {
    [ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]
}

require_supported_node() {
    if ! command -v "${NODE_BIN}" >/dev/null 2>&1; then
        echo "Node.js is required for RUN_BUILD=1 but '${NODE_BIN}' was not found." >&2
        echo "Install Node 22 or deploy prebuilt assets with RUN_BUILD=0." >&2
        exit 1
    fi

    local raw_version
    local version
    local major

    raw_version="$("${NODE_BIN}" --version)"
    version="${raw_version#v}"
    major="${version%%.*}"

    if [ "${major}" = "20" ] && version_gte "${version}" "20.19.0"; then
        return 0
    fi

    if [ "${major}" -ge 22 ] && version_gte "${version}" "22.12.0"; then
        return 0
    fi

    echo "Unsupported Node.js version: ${raw_version}" >&2
    echo "Vite in this project requires Node 20.19+ or 22.12+." >&2
    echo "Install Node 22 or rerun the deploy with RUN_BUILD=0 after copying prebuilt assets." >&2
    exit 1
}

systemd_unit_exists() {
    systemctl cat "$1" >/dev/null 2>&1
}

cleanup() {
    "${PHP_BIN}" artisan up || true
}

trap cleanup EXIT

"${PHP_BIN}" artisan down --retry=60 || true

"${COMPOSER_BIN}" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [ "${RUN_BUILD}" = "1" ]; then
    require_supported_node
    "${NPM_BIN}" ci
    "${NPM_BIN}" run build
fi

"${PHP_BIN}" artisan migrate --force

if [ "${RUN_SEED}" = "1" ]; then
    "${PHP_BIN}" artisan db:seed --class=WorldSeeder --force
fi

if [ "${RUN_STORAGE_LINK}" = "1" ]; then
    if [ -L public/storage ] || [ -e public/storage ]; then
        echo "Storage link already exists, skipping."
    else
        "${PHP_BIN}" artisan storage:link
    fi
fi

"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan view:cache

if command -v systemctl >/dev/null 2>&1; then
    if systemd_unit_exists "${QUEUE_SERVICE}"; then
        systemctl restart "${QUEUE_SERVICE}" || true
    else
        echo "Skipping queue restart: ${QUEUE_SERVICE} is not installed yet."
    fi

    if systemd_unit_exists "${SCHEDULER_TIMER}"; then
        systemctl start "${SCHEDULER_TIMER}" || true
    else
        echo "Skipping scheduler start: ${SCHEDULER_TIMER} is not installed yet."
    fi

    systemctl reload nginx || true
    systemctl reload php8.3-fpm || true
fi

"${PHP_BIN}" artisan up
trap - EXIT

echo "Deployment finished successfully."
