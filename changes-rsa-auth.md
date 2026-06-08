# RSA / shared secret hardening (2.1.6)

Branch: `fix/rsa-auth-hardening`

## Summary

Hardens Bold Checkout Payment Booster RSA registration and inbound webhook auth to prevent shared-secret drift between Magento and Bold.

## Changes

- **Atomic RSA registration** — POST first; DELETE+POST retry only on conflict; local secret saved only after Bold accepts POST
- **Shop ID safety** — no longer clears `shop_id` before `shops/v1/info` fetch
- **Website-scoped callback URL** — RSA `url` uses website default store base URL
- **Conditional rotation** — RSA re-registers only on first setup, API token change, or explicit Re-sync
- **Unified config pipeline** — single observer runs shop info → domain check → CORS → RSA → flows in order
- **Domain mismatch notice** — admin warning when Bold `shop_domain` and Magento host differ (www/apex)
- **Inbound HMAC** — header fallback via `$_SERVER`; WARN-level auth failure logging with secret fingerprint
- **Admin Re-sync RSA button** — website-scoped manual recovery without repeated full config saves
- **ACL** — log export and RSA re-sync require `system/config`

## Merchant runbook

1. Save Bold config **once** at website scope after API token entry
2. If inbound webhooks fail (`Authorization failed` in log), use **Re-sync RSA with Bold** once
3. Do **not** rotate API token unless `shops/v1/info` fails
4. Do **not** click Save repeatedly while troubleshooting

## Verify

```bash
./tests/unit/vendor/bin/phpunit -c phpunit.xml.dist
```

After deploy, confirm `bold_checkout_payment_booster.log` shows inbound `Result Code: 200` and no `matched=NO` WARN lines.
