#!/usr/bin/env bash
#
# Verifies saveOrder with another shopper's EPS order_id does not return success_existing hijack.
#
# Required env:
#   BASE_URL       e.g. https://staging.example.com
#   COOKIE_JAR_B   cookie jar for Session B (attacker / second shopper)
#   FORM_KEY_B     form_key from B's checkout page
#   EPS_ORDER_ID   EPS order id from Session A's completed wallet payment
#   SAVE_ORDER_URL e.g. /firecheckout/index/saveOrder or /checkout/onepage/saveOrder
#
# Optional:
#   SAVE_ORDER_BODY  full urlencoded body from a valid B saveOrder minus payment fields;
#                    script appends payment[method]=bold and payment[additional_data][order_id]
#
set -euo pipefail

: "${BASE_URL:?}"
: "${COOKIE_JAR_B:?}"
: "${FORM_KEY_B:?}"
: "${EPS_ORDER_ID:?}"
: "${SAVE_ORDER_URL:?}"

URL="${BASE_URL%/}/${SAVE_ORDER_URL#/}"

ENCODED_EPS="$(python3 -c "import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1], safe=''))" "$EPS_ORDER_ID")"
if [[ -n "${SAVE_ORDER_BODY:-}" ]]; then
  BODY="${SAVE_ORDER_BODY}&payment[method]=bold&payment[additional_data][order_id]=${ENCODED_EPS}"
else
  BODY="form_key=${FORM_KEY_B}&payment[method]=bold&payment[additional_data][order_id]=${ENCODED_EPS}"
fi

echo "POST $URL"
RESP="$(curl -sS -b "$COOKIE_JAR_B" -c "$COOKIE_JAR_B" \
  -X POST "$URL" \
  -H 'X-Requested-With: XMLHttpRequest' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-raw "$BODY" \
  -w '\nHTTP_CODE:%{http_code}\n')"

echo "$RESP"

if echo "$RESP" | grep -q '"success":true'; then
  echo "FAIL: saveOrder returned success — possible session hijack" >&2
  exit 1
fi

if echo "$RESP" | grep -q 'HTTP_CODE:200' && echo "$RESP" | grep -q '"redirect"'; then
  echo "FAIL: got success redirect with foreign EPS order_id" >&2
  exit 1
fi

echo "PASS: foreign EPS order_id did not yield success redirect"
