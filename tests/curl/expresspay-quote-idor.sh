#!/usr/bin/env bash
#
# Verifies expresspay/createOrder rejects a foreign quote_id (403).
#
# Required env:
#   BASE_URL          e.g. https://staging.example.com
#   COOKIE_JAR        path to Netscape cookie jar from checkout session
#   FORM_KEY          from checkout page
#   QUOTE_ID          session quote id (must match cookie session)
#   FOREIGN_QUOTE_ID  another active quote id
#   GATEWAY_ID        EPS gateway id from Bold checkout config
#
set -euo pipefail

: "${BASE_URL:?}"
: "${COOKIE_JAR:?}"
: "${FORM_KEY:?}"
: "${QUOTE_ID:?}"
: "${FOREIGN_QUOTE_ID:?}"
: "${GATEWAY_ID:?}"

ENDPOINT="${BASE_URL%/}/checkoutpaymentbooster/expresspay/createOrder"

post_create() {
  local quote_id="$1"
  curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$ENDPOINT" \
    -H 'X-Requested-With: XMLHttpRequest' \
    -H 'Content-Type: application/json' \
    -d "{\"form_key\":\"${FORM_KEY}\",\"quote_id\":\"${quote_id}\",\"gateway_id\":\"${GATEWAY_ID}\",\"shipping_strategy\":\"dynamic\"}" \
    -w '\nHTTP_CODE:%{http_code}\n'
}

echo "=== Foreign quote_id (expect HTTP 403) ==="
RESP_FOREIGN="$(post_create "$FOREIGN_QUOTE_ID")"
echo "$RESP_FOREIGN"
if ! echo "$RESP_FOREIGN" | grep -q 'HTTP_CODE:403'; then
  echo "FAIL: expected HTTP 403 for foreign quote_id" >&2
  exit 1
fi
if ! echo "$RESP_FOREIGN" | grep -q '"error"'; then
  echo "FAIL: expected JSON error body" >&2
  exit 1
fi

echo "=== Own quote_id (expect HTTP 200 and order_id) ==="
RESP_OWN="$(post_create "$QUOTE_ID")"
echo "$RESP_OWN"
if ! echo "$RESP_OWN" | grep -q 'HTTP_CODE:200'; then
  echo "FAIL: expected HTTP 200 for own quote_id" >&2
  exit 1
fi
if ! echo "$RESP_OWN" | grep -q '"order_id"'; then
  echo "FAIL: expected order_id in response" >&2
  exit 1
fi

echo "PASS: quote IDOR checks completed"
