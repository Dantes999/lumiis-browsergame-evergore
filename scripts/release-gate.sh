#!/usr/bin/env bash
set -euo pipefail

HOST="${UI_GATE_HOST:-127.0.0.1}"
START_PORT="${UI_GATE_PORT:-8000}"
PORT="${START_PORT}"
PORT_MAX="${UI_GATE_PORT_MAX:-8030}"
BASE_URL="${UI_BASE_URL:-http://${HOST}:${PORT}}"
SERVER_LOG="${UI_GATE_SERVER_LOG:-/tmp/lumiis-release-gate-server.log}"
SERVER_PID=""

php artisan config:clear --ansi
php artisan test
npm run build
php artisan db:seed --class=WorldSeeder --force
php artisan game:sync-proof-users

cleanup() {
    if [ -n "${SERVER_PID}" ] && kill -0 "${SERVER_PID}" >/dev/null 2>&1; then
        kill "${SERVER_PID}" >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

start_server() {
    local attempt_port
    local attempt_base_url
    local max_port

    if [ -n "${UI_BASE_URL:-}" ]; then
        if curl -fsS "${UI_BASE_URL}/up" >/dev/null 2>&1; then
            BASE_URL="${UI_BASE_URL}"
            return 0
        fi

        max_port="${PORT}"
    else
        max_port="${PORT_MAX}"
    fi

    for attempt_port in $(seq "${PORT}" "${max_port}"); do
        attempt_base_url="${UI_BASE_URL:-http://${HOST}:${attempt_port}}"
        : >"${SERVER_LOG}"
        php -S "${HOST}:${attempt_port}" -t public scripts/ui-proof-router.php >"${SERVER_LOG}" 2>&1 &
        SERVER_PID=$!

        for _ in $(seq 1 15); do
            if curl -fsS "${attempt_base_url}/up" >/dev/null 2>&1; then
                PORT="${attempt_port}"
                BASE_URL="${attempt_base_url}"
                return 0
            fi

            if grep -q "Failed to listen" "${SERVER_LOG}" || ! kill -0 "${SERVER_PID}" >/dev/null 2>&1; then
                break
            fi

            sleep 1
        done

        cleanup
        wait "${SERVER_PID}" 2>/dev/null || true
        SERVER_PID=""
    done

    return 1
}

if ! start_server; then
    echo "Laravel server did not become ready between ports ${START_PORT} and ${PORT_MAX}." >&2
    if [ -f "${SERVER_LOG}" ]; then
        echo "Last server log output:" >&2
        tail -n 20 "${SERVER_LOG}" >&2 || true
    fi
    exit 1
fi

UI_BASE_URL="${BASE_URL}" npm run ui:screenshots
