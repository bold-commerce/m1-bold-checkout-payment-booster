# Security fixes — `fix/rsa-auth-hardening` (2.1.6 unified)

**Branch:** `fix/rsa-auth-hardening`  
**Reference patch:** `patches/release-2.1.6-production.patch`  
**Verification:** `tests/SECURITY.md` + PHPUnit in `tests/unit/`

---

## Current state on `fix/rsa-auth-hardening`

| Fix | Status |
|-----|--------|
| Duplicate `saveOrder` guards (`PlacementGuard`, `SaveOrderObserver`) | **Applied** — wired on `checkout/onepage/saveOrder` |
| `success_existing` session ownership | **Applied** — `buildSuccessExistingEvaluation()` in full `PlacementGuard` |
| Express Pay quote IDOR binding | **Applied** — `loadCheckoutSessionQuoteForRequest()` in `ExpresspayController` |
| Submit-time guards (`assertQuoteCanSubmit` + ownership) | **Applied** — `CheckoutObserver::beforeSaveOrder` |
| 422 auth/full (`public_order_id` rotation) | **Applied** — `Service/Bold::initBoldCheckoutData` + `IndexController::getCheckoutSessionAction` |
| Wallet EPS 15s coalesce | **Applied** — `getRecentWalletEpsOrderId` in `ExpresspayController::createOrderAction` |
| RSA hardening (SavePipeline, Re-sync, HMAC) | **Applied** |
| DB unique index on `public_id` | **Applied** — `mysql4-upgrade-2.1.5-2.1.6.php` |
| PHPUnit + curl scripts | **Applied** — 33 tests pass |

**Out of scope:** Firecheckout-specific routes/observers (deferred to 2.2.x per `patches/README.md`).

---

## Verify

### Unit tests

```bash
cd app/code/community/Bold/CheckoutPaymentBooster/tests/unit
composer install
./vendor/bin/phpunit -c phpunit.xml.dist
```

### Manual / curl (staging)

See [tests/SECURITY.md](tests/SECURITY.md):

- **A** — EPS session hijack (completed order)
- **A2** — EPS session hijack (in-flight)
- **B** — Express Pay quote IDOR
- **C** — Standard checkout regression smoke
- **D** — IndexController POST/Ajax/form-key

Curl scripts in `tests/curl/`:

- `saveorder-eps-hijack.sh`
- `saveorder-eps-hijack-inflight.sh`
- `expresspay-quote-idor.sh`

### RSA (production)

1. Save Bold config once at website scope
2. Confirm inbound webhooks return 200 (no `Authorization failed` / `matched=NO`)
3. Use **Re-sync RSA with Bold** if shared secret drift recurs

---

## Known open items (follow-up)

| Item | Severity | Notes |
|------|----------|-------|
| `expresspay/getOrder` not session-bound | Medium | EPS ids hard to guess; bind in follow-up |
| Firecheckout duplicate guard wiring | Medium | Standard checkout only in 2.1.6 |
| Admin log export ACL | Low | `LogsController::_isAllowed()` |

---

## Rollback

If guards block legitimate checkout:

1. Check `bold_checkout_payment_booster.log` for `success_existing_rejected_*` or `[PlacementGuard]`.
2. Verify guest email is on quote before place order (ownership uses email match for guests).
3. Temporarily disable `SaveOrderObserver` wiring in `config.xml` only after confirming false positive — do not remove ownership fix in production without replacement.
