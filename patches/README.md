# Release 2.1.6 patches

Apply on top of **Bold CheckoutPaymentBooster 2.1.5** (`5270dd3` / `etc/config.xml` version `2.1.5`).

## Patches

| File | Use on server |
|------|----------------|
| `release-2.1.6-production.patch` | **Recommended** — module runtime files only (no tests) |
| `release-2.1.6.patch` | Full release including tests and security scripts (excludes `tests/unit/composer.lock`) |

## Prerequisites

- Module installed at `app/code/community/Bold/CheckoutPaymentBooster/` (or equivalent via modman/symlink).
- Current version **2.1.5** with unmodified 2.1.6 files (patch will fail if already partially applied).
- Backup the module directory before applying.

## Apply (from Magento root)

```bash
cd /path/to/magento

# Dry run
patch -p0 --dry-run < app/code/community/Bold/CheckoutPaymentBooster/patches/release-2.1.6-production.patch

# Apply
patch -p0 < app/code/community/Bold/CheckoutPaymentBooster/patches/release-2.1.6-production.patch
```

If paths in the patch use `a/` and `b/` prefixes (git diff), use **git apply** from the module root instead:

```bash
cd app/code/community/Bold/CheckoutPaymentBooster
git apply --check patches/release-2.1.6-production.patch
git apply patches/release-2.1.6-production.patch
```

## After apply

1. Confirm `etc/config.xml` shows `<version>2.1.6</version>`.
2. Run Magento upgrade so the DB script runs:
   ```bash
   php shell/index.php   # or your deploy hook
   # Admin: System > Configuration > Advanced > Developer > flush caches
   ```
   The upgrade adds unique index `UNQ_BOLD_CHECKOUT_PAYMENT_BOOSTER_ORDER_PUBLIC_ID` on `bold_checkout_payment_booster_order.public_id`.

3. Clear Magento cache.

## Verify

- Standard checkout `checkout/onepage/saveOrder` with Bold payment.
- RSA: save config once; inbound webhooks succeed; Re-sync RSA button available in admin.
- Logs (if issues): `var/log/bold_checkout_payment_booster.log` (`[PlacementGuard]` / `[DuplicateOrder]` prefixes when Enable Log is on).

## Rollback

Restore backup of `app/code/community/Bold/CheckoutPaymentBooster/` from before the patch. Revert `etc/config.xml` version to `2.1.5` only if you did not run the upgrade script; if upgrade ran, the DB index remains (harmless).

## Contents (production patch)

- **RSA hardening** — SavePipeline, Connect POST-first, Re-sync button, inbound HMAC fallback, API token fingerprint
- **PlacementGuard + CheckoutSessionOwnership** — duplicate order / session ownership / Express Pay IDOR
- **SaveOrderObserver** on `checkout/onepage/saveOrder`
- **CheckoutObserver**, **ExpresspayController**, payment models
- **422 fix** — `Service/Bold` public_order_id rotation, `IndexController`, wallet `base.phtml`
- **Wallet EPS coalesce** — parallel `createOrder` deduplication
- SPI postMessage hardening (`bold_method.phtml`)
- `mysql4-upgrade-2.1.5-2.1.6.php`

**Not included:** Firecheckout-specific changes (deferred to 2.2.x).
