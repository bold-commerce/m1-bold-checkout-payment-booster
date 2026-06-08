# RSA / shared secret hardening + duplicate-order security (2.1.6)

Branch: `fix/rsa-auth-hardening`

## Summary

Unified **2.1.6** release combining RSA/shared-secret hardening (Lethal incident), CHK-9603 duplicate-order guards, and 422 auth/full fixes on **standard M1 checkout** (`checkout/onepage`).

## RSA hardening

- **Atomic RSA registration** — POST first; DELETE+POST retry only on conflict; local secret saved only after Bold accepts POST
- **Shop ID safety** — no longer clears `shop_id` before `shops/v1/info` fetch
- **Website-scoped callback URL** — RSA `url` uses website default store base URL
- **Conditional rotation** — RSA re-registers only on first setup, API token change, or explicit Re-sync
- **Unified config pipeline** — single observer runs shop info → domain check → CORS → RSA → flows in order
- **Domain mismatch notice** — admin warning when Bold `shop_domain` and Magento host differ (www/apex)
- **Inbound HMAC** — header fallback via `$_SERVER`; WARN-level auth failure logging with secret fingerprint
- **Admin Re-sync RSA button** — website-scoped manual recovery without repeated full config saves
- **ACL** — log export and RSA re-sync require `system/config`

## Duplicate-order + security (CHK-9603 / 422-fix)

- **`PlacementGuard` + `SaveOrderObserver`** — predispatch/postdispatch on `checkout/onepage/saveOrder`
- **`CheckoutSessionOwnership`** — session ownership on `success_existing`; Express Pay quote IDOR binding
- **`CheckoutObserver::assertQuoteCanSubmit`** — submit-time duplicate + EPS session checks with ownership
- **422 auth/full** — `Service/Bold::initBoldCheckoutData` clears wallet EPS on `public_order_id` rotation; `IndexController::getCheckoutSessionAction`
- **Wallet EPS 15s coalesce** — parallel `expresspay/createOrder` returns cached EPS id within coalesce window
- **SPI postMessage hardening** — origin check in `bold_method.phtml`
- **DB upgrade** — unique index on `bold_checkout_payment_booster_order.public_id`

## Merchant runbook

1. Save Bold config **once** at website scope after API token entry
2. If inbound webhooks fail (`Authorization failed` in log), use **Re-sync RSA with Bold** once
3. Do **not** rotate API token unless `shops/v1/info` fails
4. Do **not** click Save repeatedly while troubleshooting

## Verify

```bash
cd tests/unit
composer install
./vendor/bin/phpunit -c phpunit.xml.dist
```

Manual checks: [tests/SECURITY.md](tests/SECURITY.md)

Production patch (runtime only): [patches/release-2.1.6-production.patch](patches/release-2.1.6-production.patch)

After deploy, confirm `bold_checkout_payment_booster.log` shows inbound `Result Code: 200` and no `matched=NO` WARN lines.
