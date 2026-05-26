# Firecheckout + Bold Checkout Payment Booster — Change Log

This document describes all changes on the **firecheckout-changes** work (compared to the **CheckoutPaymentBooster** submodule `main` branch) for **TM Firecheckout**, **Bold SPI**, and **Apple Pay / Google Pay** on checkout.

**Baseline:** submodule `app/code/community/Bold/CheckoutPaymentBooster` @ `main`  
**Deploy:** copy module `design/` + `skin/` into `app/design/` and `skin/`, flush Magento cache, hard-refresh browser.

---

## Why we made these changes

### Problems on standard checkout + Firecheckout (before)

| Problem | Symptom | Root cause |
|--------|---------|------------|
| Duplicate SPI / wallet init | “Loading SPI Frame…” multiple times; duplicate PayPal buttons | Inline scripts re-ran on every Firecheckout `el.update()` / `payment.init()` |
| Missing `baseInstance` | `window.bold.baseInstance is undefined` | `BoldBase` only created on `dom:loaded`; `bold_method.phtml` ran earlier |
| Apple Pay broken | `InvalidAccessError`, `TypeError` in `payments_sdk.js` | `onRequireOrderData` was **async** (awaited Ajax inside Apple Pay gesture) |
| Wallet approve failed | `Invalid data.` | Minimal `saveOrder` POST without full checkout `payment` / form state |
| Wallet approve failed | `Can't find variable: Translator` | `checkout.save()` ran FC validation without Magento `Translator` in wallet callback |
| Wallet approve failed | `Please specify a shipping method.` | Wallet shipping not persisted to Magento quote / form before `saveOrder` (**still open**) |
| Duplicate Magento orders | Two orders per wallet/FC reload | Double `saveOrder`, FC totals reload, no predispatch guard |
| Bold session cleared on FC | Checkout data lost on FC Ajax | Firecheckout routes not in `PredispatchObserver` allowlist |

### Design goals

1. **One SPI init** per logical checkout session; safe re-render after FC partial reloads.
2. **Apple Pay / Google Pay** on checkout via `renderWalletPayments` in a dedicated host (`#smart-ppcp-buttons`), not inside hidden SPI PayPal panel.
3. **Place order** with full Firecheckout form data + Bold `payment[additional_data][order_id]`, without relying on `checkout.save()` in wallet callbacks.
4. **Duplicate order prevention** at `saveOrder` predispatch (standard + Firecheckout + onecolumn).
5. Keep **Firecheckout Order Total** dependency for payment methods (e.g. Affirm min/max) — do not strip `payment-method` from FC AJAX.

---

## Architecture after changes

```text
layout/bold/checkout.xml
  └── bold.payments.base (base.phtml)     ← BoldBase, SDK, wallet handlers, BEFORE firecheckout
  └── firecheckout block
  └── payment method: bold_method.phtml  ← Markup + BoldPaymentMethod + FC observers

checkout-payment.css
  └── Hide SPI #paypalPanel (duplicate PayPal)
  └── Show top-level #smart-ppcp-buttons (Apple / Google)
```

| Layer | File | Responsibility |
|-------|------|----------------|
| Bootstrap + SDK | `payment/form/base.phtml` | `BoldBase`, wallet callbacks, cart cache, `postWalletSaveOrder` |
| Payment UI + FC | `payment/form/bold_method.phtml` | SPI + wallet render, `wrapCheckoutSave`, FC observers, `placeWalletOrder` |
| Layout | `layout/bold/checkout.xml` | Load CSS; `base` before `firecheckout` |
| Styles | `checkout-payment.css` | Hide duplicate PPCP; show wallet host |
| PHP config | `Block/Payment/Form/Base.php` | `getWalletCheckoutJsConfig()` |
| Duplicate orders | `PlacementGuard`, `SaveOrderObserver`, `CheckoutObserver`, `config.xml` | Predispatch + assert on submit |

---

## Files changed

### Frontend — `design/frontend/base/default/`

#### `template/bold/checkout_payment_booster/payment/form/base.phtml`

**Why:** Central place for Payments SDK and wallet logic; must load before Firecheckout JS.

| Change | Why |
|--------|-----|
| `window.bold.walletCheckoutConfig` from PHP | Single config object for wallet Ajax URLs (FC vs onepage `saveOrder`, express create/update, billing/shipping). |
| `ensureBoldBaseInstance()` + `bold:baseInstanceReady` | Create `BoldBase` immediately so `bold_method` does not race `dom:loaded`. |
| `cachedCartData`, `prefetchCartData()`, deduped `getCartData()` | Apple Pay `onRequireOrderData` must return **synchronously**; prefetch on load and after `firecheckout:setResponseAfter`. |
| Wallet SDK callbacks (`onCreate` / `onUpdate` / `onApprove`) | Main only handled PPCP; Apple/Google need address sync, EPS order create, and place order. |
| `onRequireOrderData` synchronous | Async `await getCartData()` breaks Apple Pay session user-gesture rules. |
| `buildOrderDataFromCartData`, amount/address normalizers | Match Payments SDK + expresspay shapes; avoid NaN amounts. |
| `applyWalletAddressToQuote`, `applyWalletShippingFromPayload` | Push wallet billing/shipping/method to Magento before place order (onepage URLs today). |
| `postWalletSaveOrder` + `getCheckoutPlaceOrderForm` | Serialize **full** FC form to `firecheckout/index/saveOrder`; avoids `Translator` and empty `payment` (“Invalid data”). |
| `handleWalletApprovePaymentOrder` → `placeWalletOrder()` | Delegate to `bold_method` after quote sync. |
| PPCP `onApprove` → `payment.save()` with guards | Unchanged flow for PayPal-in-SPI; null-safe `payment` / `checkout`. |

#### `template/bold/checkout_payment_booster/payment/form/bold_method.phtml`

**Why:** Payment method markup and FC-specific re-init; must not re-run full SPI on every AJAX tick.

| Change | Why |
|--------|-----|
| Markup: `#payment_form_bold` + `#smart-ppcp-buttons` | Dedicated wallet host; SPI still renders into `payment_form_bold` but PayPal panel hidden via CSS. |
| `whenBoldBaseReady` / `ensureBoldPaymentMethod` | Singleton payment class; re-bootstrap after FC `payment-method` section reload. |
| `refreshDomReferences` | `#p_method_bold` replaced by FC HTML updates. |
| `renderWalletButtons` + `walletButtonsRendered` | One `renderWalletPayments` per host; skip if already rendered. |
| `isSpiFrameLoaded`, `needsPaymentUiRender` | Avoid duplicate `renderPayments` / SPI iframe. |
| `registerCheckoutObservers` | `firecheckout:setResponseAfter`, `firecheckout:paymentMethod:afterInitAfter`, `payment-method:switched`. |
| `wrapCheckoutSave` / `wrapPaymentSave` | Card flow: tokenize via SPI if no `paymentId`, else normal save. |
| `syncPaymentIdToForm`, `selectBoldPaymentMethod`, `placeWalletOrder` | Wallet approve: hidden `order_id`, select Bold, `postWalletSaveOrder` (not `checkout.save()`). |

#### `layout/bold/checkout.xml`

| Change | Why |
|--------|-----|
| `checkout-payment.css` on onepage + `firecheckout_index_index` | Style rules for wallet vs SPI PayPal. |
| `bold.payments.base` **`before="firecheckout"`** | Ensure `BoldBase` exists before Firecheckout initializes. |

#### `skin/.../css/bold/checkout_payment_booster/checkout-payment.css` *(new)*

| Change | Why |
|--------|-----|
| Hide `#payment_form_bold #paypalPanel` | SPI renders PayPal/Venmo/Pay Later inside panel → looked like duplicate buttons. |
| Show `#payment_form_bold > #smart-ppcp-buttons` | Apple Pay / Google Pay only in top-level host. |

---

### PHP — wallet checkout config

#### `Block/Payment/Form/Base.php`

| Change | Why |
|--------|-----|
| **`getWalletCheckoutJsConfig()`** *(new)* | Exposes `formKey`, `quoteId`, `quoteIsVirtual`, express create/update URLs, **`saveOrderUrl`** (`firecheckout/index/saveOrder` when `moduleName === firecheckout`), onepage save billing/shipping/method, success URL for `base.phtml`. |

---

### PHP — duplicate order prevention

#### `Service/Order/PlacementGuard.php` *(new)*

| Change | Why |
|--------|-----|
| `evaluatePlacementRequest()` | Predispatch decision: `allow`, `block`, `block_in_progress`, or `success_existing` (return JSON redirect, no second order). |
| Session flag `bold_order_placement_in_progress` | Block concurrent `saveOrder` while first request runs. |
| MySQL `GET_LOCK` per Bold `public_order_id` | Serialize parallel requests same checkout session. |
| `findOrderByPublicId`, `findOrderByEpsOrderId`, `findOrderByQuoteId` | Detect already-placed orders. |
| `getEpsOrderIdFromRequest()` | Read `payment[additional_data][order_id]` from wallet POST. |
| `respondWithExistingOrderSuccess()` | FC/Magento JSON success + redirect for duplicate submit. |
| `assertQuoteCanSubmit()` | Second line of defense in `CheckoutObserver::beforeSaveOrder`. |

#### `Observer/SaveOrderObserver.php` *(new)*

| Change | Why |
|--------|-----|
| `predispatchSaveOrder` | Run `PlacementGuard` before controller places order. |
| `cleanupAfterSaveOrder` | Clear session flag and release lock after `saveOrder` finishes. |

#### `Observer/CheckoutObserver.php`

| Change | Why |
|--------|-----|
| `assertQuoteCanSubmit($quote)` in `beforeSaveOrder` | Block duplicate Bold payment at order-creation event. |
| Try/catch on `extOrderData->save()` | Log duplicate `public_id` DB race instead of failing after order exists. |
| `isDuplicatePublicIdException()` | Detect unique constraint on `bold_checkout_payment_booster_order.public_id`. |

#### `Observer/PredispatchObserver.php`

| Change | Why |
|--------|-----|
| Firecheckout routes in `$allowedActions` | Keep Bold checkout session on FC index and section Ajax (`saveShipping`, `updateSections`, `savePayment`, etc.). |

#### `etc/config.xml`

| Change | Why |
|--------|-----|
| Version `2.1.5` → `2.1.6` | Module bump for release tracking. |
| Observers on `predispatch` / `postdispatch` **`saveOrder`** | `checkout/onepage/saveOrder`, `firecheckout/index/saveOrder`, `firecheckout/onecolumn/saveOrder` → `SaveOrderObserver`. |

---

### PHP — supporting / related (same branch)

| File | Change | Why |
|------|--------|-----|
| `Model/Order.php` | `getCollection()` | `PlacementGuard::findOrderByPublicId` uses order mapping collection. |
| `Model/Resource/Order/Collection.php` | *(new)* | Collection class for mapping queries. |
| `Model/Resource/Order.php` | Minor | Resource support for collection. |
| `Model/Payment/Bold.php`, `Fastlane.php` | `isAvailable`, `getTitle` | Safer admin/frontend checks; card/PayPal title from `card_details`. |
| `Service/Order/Hydrate/ExtractData.php` | Tweaks | Align hydrate with checkout data shapes. |
| `etc/modules/Bold_CheckoutPaymentBooster.xml` | Removed | Module declaration consolidated elsewhere (verify deploy if module fails to load). |

**Not production:** `Observer/CheckoutObserver-working.php`, `payment/form/backup-*` folders — backups only.

---

## Parent repo (`magento1-community`) copies

These paths mirror the submodule after deploy and are what git tracks at the monorepo level:

- `app/design/frontend/base/default/template/bold/checkout_payment_booster/payment/form/base.phtml`
- `app/design/frontend/base/default/template/bold/checkout_payment_booster/payment/form/bold_method.phtml`
- `app/design/frontend/base/default/layout/bold/checkout.xml`
- `skin/frontend/base/default/css/bold/checkout_payment_booster/checkout-payment.css`

Submodule canonical sources:

- `app/code/community/Bold/CheckoutPaymentBooster/design/...`
- `app/code/community/Bold/CheckoutPaymentBooster/skin/...`

---

## Wallet approve flow (current)

```text
Apple Pay authorize
  → onApprovePaymentOrder (base.phtml)
  → applyWalletAddressesFromPayload / applyWalletShippingFromPayload
  → boldPaymentMethod.paymentId = EPS order id
  → placeWalletOrder()
      → syncPaymentIdToForm + selectBoldPaymentMethod
      → postWalletSaveOrder()  // Form.serialize(firecheckout form) + payment fields
  → firecheckout/index/saveOrder
  → SaveOrderObserver::predispatchSaveOrder → PlacementGuard
```

---

## Known gaps / follow-up

| Item | Status | Planned fix |
|------|--------|-------------|
| **“Please specify a shipping method.”** on wallet approve | Open | Persist shipping on `onUpdate` + approve; map wallet option id → Magento rate code; use FC `saveShippingMethod` URLs; append `shipping_method` to `postWalletSaveOrder` body. |
| Wallet address/shipping via **onepage** URLs on FC | Risk | Point `getWalletCheckoutJsConfig()` to `firecheckout/index/save*` when on FC. |
| SDK `InvalidAccessError` after failed approve | Often secondary | Retest after shipping fix; keep cart prefetch warm. |

---

## Deploy checklist

```bash
# From magento root
cp -r app/code/community/Bold/CheckoutPaymentBooster/design/* app/design/
cp -r app/code/community/Bold/CheckoutPaymentBooster/skin/* skin/

# Flush cache, compile if needed
# Hard refresh browser on Firecheckout checkout
```

**Verify:**

- `!!window.bold.baseInstance` and `getBoldPaymentsInstance` in console  
- One visible Apple/Google block; SPI PayPal panel hidden  
- Normal Bold card Place Order still tokenizes + saves once  
- Duplicate `saveOrder` returns success redirect to existing order (PlacementGuard)  

---

## Inline code annotations

Source files also contain `[vs main]` / `CHANGES vs main` comments for quick diff context when comparing to submodule `main`. Search:

```bash
cd app/code/community/Bold/CheckoutPaymentBooster
rg '\[vs main\]|CHANGES vs main'

cd app/design/frontend/base/default
rg '\[vs main\]|CHANGES vs main' template/bold layout/bold
```

---

## Errors addressed (chronological)

| Error | Fix |
|-------|-----|
| Duplicate SPI / PayPal UI | FC observers + render guards + `checkout-payment.css` |
| `baseInstance is undefined` | `ensureBoldBaseInstance()` + `whenBoldBaseReady` |
| `Invalid data.` on approve | `postWalletSaveOrder` with full form (not minimal Ajax) |
| `Translator` ReferenceError | Avoid `checkout.save()` in wallet path |
| `Please specify a shipping method.` | Documented; not yet implemented |

---

*Last updated: firecheckout-changes branch — Bold Checkout Payment Booster + TM Firecheckout integration.*
