#!/usr/bin/env bash
# Smoke test for PHP runtime and management API.
# Run from repo root. Requires php and curl in PATH.

set -e
cd "$(dirname "$0")/.."
PORT=8765
URL="http://127.0.0.1:${PORT}/sb/minimal/v1/health"
API_URL="http://127.0.0.1:${PORT}/api/apps"
INVALID_JSON_URL="http://127.0.0.1:${PORT}/api/test-request"

php -S "127.0.0.1:${PORT}" -t . scripts/php-router.php &
PID=$!
trap "kill $PID 2>/dev/null || true" EXIT

# Wait for server to listen
sleep 0.5
for i in 1 2 3 4 5 6 7 8 9 10; do
  if curl -s -o /dev/null -w "%{http_code}" "$URL" 2>/dev/null | grep -q 200; then
    break
  fi
  [ "$i" -eq 10 ] && { echo "Server did not respond with 200 in time"; exit 1; }
  sleep 0.3
done

STATUS=$(curl -s -o /tmp/smoke-php-out.json -w "%{http_code}" "$URL")
if [ "$STATUS" != "200" ]; then
  echo "Smoke test failed: expected HTTP 200, got $STATUS"
  cat /tmp/smoke-php-out.json
  exit 1
fi

if ! grep -q '"ok"' /tmp/smoke-php-out.json || ! grep -q '"service"' /tmp/smoke-php-out.json; then
  echo "Smoke test failed: response body missing expected keys (ok, service)"
  cat /tmp/smoke-php-out.json
  exit 1
fi

API_STATUS=$(curl -s -o /tmp/smoke-php-api-apps.json -w "%{http_code}" "$API_URL")
if [ "$API_STATUS" != "200" ]; then
  echo "Smoke test failed: expected management API HTTP 200, got $API_STATUS"
  cat /tmp/smoke-php-api-apps.json
  exit 1
fi

if ! grep -q '"slug"' /tmp/smoke-php-api-apps.json; then
  echo "Smoke test failed: management API response missing app data"
  cat /tmp/smoke-php-api-apps.json
  exit 1
fi

INVALID_JSON_STATUS=$(curl -s -o /tmp/smoke-php-invalid-json.json -w "%{http_code}" \
  -X POST "$INVALID_JSON_URL" \
  -H "Content-Type: application/json" \
  -d '{"path":')
if [ "$INVALID_JSON_STATUS" != "400" ]; then
  echo "Smoke test failed: expected invalid JSON HTTP 400, got $INVALID_JSON_STATUS"
  cat /tmp/smoke-php-invalid-json.json
  exit 1
fi

if ! grep -q '"invalid_json"' /tmp/smoke-php-invalid-json.json; then
  echo "Smoke test failed: invalid JSON response missing invalid_json error"
  cat /tmp/smoke-php-invalid-json.json
  exit 1
fi

NON_OBJECT_JSON_STATUS=$(curl -s -o /tmp/smoke-php-non-object-json.json -w "%{http_code}" \
  -X POST "$INVALID_JSON_URL" \
  -H "Content-Type: application/json" \
  -d 'true')
if [ "$NON_OBJECT_JSON_STATUS" != "400" ]; then
  echo "Smoke test failed: expected non-object JSON HTTP 400, got $NON_OBJECT_JSON_STATUS"
  cat /tmp/smoke-php-non-object-json.json
  exit 1
fi

if ! grep -q '"Request body must be a JSON object"' /tmp/smoke-php-non-object-json.json; then
  echo "Smoke test failed: non-object JSON response missing object requirement"
  cat /tmp/smoke-php-non-object-json.json
  exit 1
fi

echo "Smoke test passed: GET $URL -> 200, GET $API_URL -> 200, invalid API JSON -> 400, non-object API JSON -> 400"
exit 0
