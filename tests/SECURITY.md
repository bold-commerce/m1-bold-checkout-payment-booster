# Security verification — CheckoutPaymentBooster

Manual checks for **2.1.6** (standard `checkout/onepage` + Express Pay security). Run on **staging** before production.

**2.1.6 scope:** Duplicate-order predispatch runs on `checkout/onepage/saveOrder` only (not Firecheckout). Firecheckout + wallet UX is planned for 2.2.x.

Wallet EPS session binding (`assertWalletEpsOrderIdBelongsToSession`) runs on Express Pay Ajax **and** on Bold `saveOrder` predispatch / `assertQuoteCanSubmit` when `payment[additional_data][order_id]` is present.

## Prerequisites

- Bold Payment Booster enabled; **standard one-page checkout** with Bold and/or Express Pay / wallet buttons.
- Two isolated browser profiles (Session A / Session B).
- Access to `var/log/bold_checkout_payment_booster.log` (Enable Log must be Yes in Bold advanced settings).
- Optional: curl scripts in [curl/](curl/) (set env vars documented in each script).

---

## A. EPS session hijack — completed order (PlacementGuard ownership)

**Goal:** Another shopper cannot get a success redirect by reusing someone else's EPS `order_id` after Session A already placed an order.

1. **Session A:** Complete checkout with Bold wallet (Apple Pay / Google Pay) or note EPS id from Network tab: `payment[additional_data][order_id]` on `saveOrder`.
2. **Session B:** New guest or different customer; add product; reach checkout payment step.
3. Capture B's cookies and `form_key` from checkout page source or DevTools.
4. **Session B:** POST `saveOrder` to `/checkout/onepage/saveOrder` with:
   - B's `form_key`
   - `payment[method]=bold`
   - `payment[additional_data][order_id]=<A's EPS order id>`
   - Other required fields from a normal place-order request (copy from DevTools).

**Automated:** [curl/saveorder-eps-hijack.sh](curl/saveorder-eps-hijack.sh)

**Expected:**

- Response is an **error** (not `{"success":true,"redirect":...}` to A's order).
- B's checkout session must **not** show A's increment id on success page.
- Log contains `success_existing_rejected_eps_order_id` or `assert_rejected_assert_eps_order_id`.

5. **Session A:** Repeat `saveOrder` with the same EPS id (legitimate duplicate submit).

**Expected:**

- `success_existing` JSON with redirect to success.
- Still only **one** Magento order for that payment.

---

## A2. EPS session hijack — in-flight (before Session A saveOrder)

**Goal:** Session B cannot place an order using Session A's EPS `order_id` when A created the wallet order via Express Pay but has **not** yet called `saveOrder`.

1. **Session A:** Open standard checkout; start Apple Pay / Google Pay; complete wallet sheet so `expresspay/createOrder` returns an EPS `order_id`. **Do not** place the Magento order.
2. Copy A's EPS `order_id` from the Network tab (or from `expresspay/createOrder` response).
3. **Session B:** Separate browser profile; add product; reach checkout payment step.
4. **Session B:** POST `saveOrder` with B's `form_key` and A's EPS `order_id` (same as section A step 4).

**Automated:** [curl/saveorder-eps-hijack-inflight.sh](curl/saveorder-eps-hijack-inflight.sh) — same env as `saveorder-eps-hijack.sh`, but use an EPS id captured **before** Session A completes `saveOrder`.

**Expected:**

- Response is an **error** (authorization failure or "not authorized to access this payment").
- No Magento order created for Session B.
- Session A can still complete `saveOrder` normally with its own EPS id.

---

## B. Express Pay quote IDOR

**Goal:** Cannot create/update Bold `wallet_pay` for another customer's quote.

1. **Session A:** Open checkout; note `quote_id` in Express Pay Ajax or DB `sales_flat_quote.entity_id`.
2. Obtain **foreign** active quote id (Session B checkout, or admin).
3. Run [curl/expresspay-quote-idor.sh](curl/expresspay-quote-idor.sh) with `FOREIGN_QUOTE_ID` set.

**Expected:**

- HTTP **403** and JSON `error` when using foreign `quote_id`.
- HTTP **200** with `order_id` when using own `quote_id` or empty string (session default).

---

## C. Regression smoke (standard checkout)

- Bold SPI card checkout places order.
- Express Pay / wallet create order + place order on **checkout/onepage** (if enabled).
- Other payment methods still selectable and place order.
- Non-Bold `saveOrder` unaffected (guards only when `payment[method]` is `bold` or `bold_fastlane`).
- Duplicate `saveOrder` on same session logs `[PlacementGuard]` / `[DuplicateOrder]` lines in `bold_checkout_payment_booster.log` and returns `success_existing` when appropriate.

---

## D. IndexController cart endpoints

**Goal:** Cart data Ajax requires POST + `X-Requested-With: XMLHttpRequest` + valid form key.

1. GET `/checkoutpaymentbooster/index/getCartData?form_key=...` → **404** (noroute).
2. POST without Ajax header → **404**.
3. POST with Ajax header and invalid form key → **403** JSON `error`.

---

## Unit tests (local)

```bash
cd app/code/community/Bold/CheckoutPaymentBooster/tests/unit
composer install
./vendor/bin/phpunit -c phpunit.xml.dist
```

See [README.md](README.md).
