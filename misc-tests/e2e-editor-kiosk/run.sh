#!/bin/sh
# Run editor-kiosk.e2e.cjs in the Playwright Docker image, on the Docker network
# of a running DOMjudge test installation. Nothing is written to this checkout.
#
# Usage: run.sh <Docker network> [DOMjudge base URL on that network]
# E.g. for the docker-compose.yml in the repository root:
#   misc-tests/e2e-editor-kiosk/run.sh domjudge_default http://domjudge
# Set EXPECT_PROD=1 to also check that DOMjudge runs with the production CSP.

set -eu

if [ $# -lt 1 ]; then
	echo "Usage: $0 <Docker network> [DOMjudge base URL]" >&2
	exit 1
fi
dir=$(cd "$(dirname "$0")" && pwd)
repo=$(cd "$dir/../.." && pwd)

exec docker run --rm --ipc=host --network "$1" \
	-v "$repo:/repo:ro" \
	-e BASE_URL="${2:-http://domjudge}" \
	-e ADMIN_AUTH="admin:$(cat "$repo/etc/initial_admin_password.secret")" \
	-e EXPECT_PROD="${EXPECT_PROD:-}" \
	mcr.microsoft.com/playwright:v1.60.0-noble sh -c '
		mkdir /tmp/e2e && cd /tmp/e2e && cp /repo/misc-tests/e2e-editor-kiosk/editor-kiosk.e2e.cjs . &&
		npm install --silent --no-save --no-package-lock playwright@1.60.0 &&
		node editor-kiosk.e2e.cjs'
