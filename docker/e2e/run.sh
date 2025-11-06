#!/usr/bin/env bash

set -euo pipefail

export APP_ENV=${APP_ENV:-prod}
export APP_DEBUG=${APP_DEBUG:-0}
export E2E_BASE_URL=${E2E_BASE_URL:-http://app}

ROOT_DIR="/var/www/html"
cd "${ROOT_DIR}"

echo "Waiting for app service to be ready..."
deadline=$((SECONDS + 60))
until curl -f "${E2E_BASE_URL}/" > /dev/null 2>&1; do
    if [ "${SECONDS}" -ge "${deadline}" ]; then
        echo "E2E server failed to start at ${E2E_BASE_URL}" >&2
        exit 1
    fi
    sleep 1
done

echo "App service is ready. Running E2E tests..."
vendor/bin/phpunit --testsuite=E2E "$@"
