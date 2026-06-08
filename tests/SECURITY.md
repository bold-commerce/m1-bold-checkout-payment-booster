# Security verification — CheckoutPaymentBooster

Manual checks for **2.1.6** (standard `checkout/onepage` + Express Pay security). Run on **staging** before production.

**2.1.6 scope:** Duplicate-order predispatch runs on `checkout/onepage/saveOrder` only (not Firecheckout). Firecheckout + wallet UX is planned for 2.2.x.

## Prerequisites

- Bold Payment Booster enabled; **standard one-page checkout** with Bold and/or Express Pay / wallet buttons.
- Two isolated browser profiles (Session A / Session B).
- Access to `var/log/bold_checkout_payment_booster.log` (Enable Log must be Yes in Bold advanced settings).
- Optional: curl scripts in [curl/](curl/) (set env vars documented in each script).

---

## A. EPS session hijack (PlacementGuard ownership)

**Goal:** Another shopper cannot get a success redirect by reusing someone else's EPS `order_id`.

1. **Session A:** Complete checkout with Bold wallet (Apple Pay / Google Pay) or note EPS id from Network tab: `payment[additional_data][order_id]` on `saveOrder`.
2. **Session B:** New guest or different customer; add product; reach checkout payment step.
3. Capture B's cookies and `form_key` from checkout page source or DevTools.
4. **Session B:** POST `saveOrder` to `/checkout/onepage/saveOrder` with:
  - B's `form_key`
  - `payment[method]=bold`
  - `payment[additional_data][order_id]=<A's EPS order id>`
  - Other required fields from a normal place-order request (copy from DevTools).

**Expected:**

- Response is an **error** (not `{"success":true,"redirect":...}` to A's order).
- B's checkout session must **not** show A's increment id on success page.
- Log contains `success_existing_rejected_eps_order_id` or `assert_rejected_assert_eps_order_id`.

1. **Session A:** Repeat `saveOrder` with the same EPS id (legitimate duplicate submit).

**Expected:**

- `success_existing` JSON with redirect to success.
- Still only **one** Magento order for that payment.

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

## Unit tests (local)

```bash
cd app/code/community/Bold/CheckoutPaymentBooster/tests/unit
composer install
./vendor/bin/phpunit -c phpunit.xml.dist
```

See [README.md](README.md).