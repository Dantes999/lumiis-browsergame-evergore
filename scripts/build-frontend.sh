#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FRONTEND_DIR="${ROOT_DIR}/frontend"
PUBLIC_DIR="${ROOT_DIR}/public/play"
BASE_PATH="${VITE_BASE_PATH:-/play/}"

cd "${FRONTEND_DIR}"

if [ ! -d node_modules ]; then
    npm ci
fi

VITE_BASE_PATH="${BASE_PATH}" npm run build

rm -rf "${PUBLIC_DIR}"
mkdir -p "${PUBLIC_DIR}"
cp -R dist/. "${PUBLIC_DIR}/"

echo "SPA built into public/play (base ${BASE_PATH})."
