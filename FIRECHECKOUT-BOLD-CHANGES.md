# Bold Checkout Payment Booster — FireCheckout Integration Changes

**Document purpose:** Technical justification for all changes on branch `duplicated-orders-firecheckout` compared to `main` in `app/code/community/Bold/CheckoutPaymentBooster`.

**Parent Magento repo branch:** `firecheckout` (templates mirrored under `app/design/frontend/base/default/`).

**Module version:** `2.1.5` → `2.1.6`

**Git comparison (run from module directory):**

```bash
cd app/code/community/Bold/CheckoutPaymentBooster
git diff main --stat
git diff main --name-status
```

**Summary:** 11 files changed, ~1,778 insertions, ~233 deletions (per `git diff main --shortstat`).

---

## 1. Executive summary

Bold Checkout Payment Booster was integrated with **Swissup FireCheckout**, which reloads checkout sections over AJAX (payment method, shipping, totals, billing, etc.). The stock implementation assumed a relatively static DOM and initialized Bold/SPI (Payments iFrame) in `bold_method.phtml`. That caused:

- Multiple live SPI instances and duplicate tokenize / place-order flows
- Payment UI disappearing after shipping or payment-method reloads
- Wallets (PayPal, Venmo, Google Pay) missing while credit card remained
- SDK errors (`SPI frame is already rendered`, `smart-ppcp-buttons is already rendered`) after DOM teardown without SDK reset

The fix splits responsibilities:

| Layer | Approach |
|-------|----------|
| **Frontend** | Single `BoldPaymentMethod` runtime in `base.phtml`; `bold_method.phtml` is markup-only; coalesced FireCheckout sync; explicit DOM + SDK teardown before re-render |
| **Backend** | Order placement guards, duplicate `public_id` handling, FireCheckout `saveOrder` observers |

---

## 2. Problems addressed

### 2.1 FireCheckout AJAX reloads

FireCheckout updates sections such as `payment-method`, `shipping-method`, `cart`, `total`, and `billing` without a full page load. Each update can re-inject `bold_method.phtml` while previous JavaScript listeners and Bold SDK state remain active.

### 2.2 Duplicate orders / duplicate payment authorization

Multiple `saveOrder` or tokenize paths could run when several SPI instances responded to the same place-order action.

### 2.3 Payment form not visible

- Bold only payment method: no radio / `payment.currentMethod` not set → visibility logic never ran
- Bold checkout data not initialized on FireCheckout AJAX actions → payment method missing server-side
- Early-return handlers after `initBoldPaymentMethod()` without completing render

### 2.4 Wallets vs credit card (SPI)

- `renderPayments()` renders the SPI iframe (card fields inside iframe)
- `renderWalletPayments()` renders PPCP buttons into `#smart-ppcp-buttons` / `#paypal-button-container`
- Both are required; removing wallet render left an empty wallet row in SPI UI
- Clearing DOM without resetting SDK left “already rendered” errors and no UI

### 2.5 Double SPI load

Multiple handlers fired full hard reloads in one user action (`ensureBoldCheckoutUi` + `renderPaymentForm` each called `resetPaymentFormContainer()`), producing two `Loading SPI Frame...` sequences in the console.

---

## 3. Architecture change (frontend)

### Before (`main`)

- `BoldPaymentMethod` class lived entirely in **`bold_method.phtml`** (~230 lines)
- Every FireCheckout payment reload re-executed that script → new class instance, new SPI listeners
- `base.phtml` held `BoldBase` only

### After (`duplicated-orders-firecheckout`)

- **`base.phtml`** — `BoldBase` + single long-lived `BoldPaymentMethod` + FireCheckout observers + bootstrap
- **`bold_method.phtml`** — HTML host for PPCP wallets + empty `#payment_form_bold` + injection callback only

### Runtime concepts

| Concept | Purpose |
|---------|---------|
| `window.bold.boldPaymentMethod` | Single payment UI owner |
| `paymentRuntimeVersion` / `isCurrentRuntime()` | Stale instance detection (callbacks ignore if not current) |
| `spiRenderGeneration` | Cancel outdated scheduled renders |
| `scheduleBoldPaymentSectionSync()` | Debounce (350ms) and merge overlapping FC events |
| `paymentSyncInProgress` | Prevent parallel syncs; queue one follow-up |
| `resetPaymentFormContainer()` | Remove SPI iframe + recreate PPCP host + `resetPaymentsInstance()` |
| `renderSpiPayments()` | SPI render with retry on “already rendered” |
| `renderPpcpWalletButtons()` | Wallet buttons when host empty |
| `isPaymentUiReady()` | SPI iframe visible **and** PPCP buttons present |
| `shouldShowBoldPaymentForm()` | Bold selected, only method, or `lastUsedMethod === bold` |
| `ensureBoldCheckoutUi()` | Single entry for visibility + conditional render |

### FireCheckout events wired

- `firecheckout:setResponseAfter` — `payment-method` → hard reload; other listed sections → force render if UI incomplete
- `firecheckout:paymentMethod:afterInitAfter` — sync after FC payment init
- `payment-method:switched` / `payment-method:switched-off`
- `MutationObserver` on `#checkout-payment-method-load` when `#payment_form_bold` is injected (guarded to avoid loops during `spiRenderInProgress`)
- `onBoldPaymentFormInjected()` — no longer schedules its own hard reload (avoids duplicate with section reload)

---

## 4. Files changed (complete list)

Paths below are relative to `app/code/community/Bold/CheckoutPaymentBooster/`.

| File | Status vs `main` | Role |
|------|------------------|------|
| `design/frontend/base/default/template/bold/checkout_payment_booster/payment/form/base.phtml` | Modified (~+1,191 lines net) | FireCheckout-safe JS runtime |
| `design/frontend/base/default/template/bold/checkout_payment_booster/payment/form/bold_method.phtml` | Modified (~−217 lines) | Markup-only; logic removed |
| `Service/Order/PlacementGuard.php` | **New** (+385 lines) | Duplicate order prevention |
| `Observer/SaveOrderObserver.php` | **New** (+66 lines) | `saveOrder` predispatch/postdispatch |
| `Observer/PredispatchObserver.php` | Modified | FireCheckout actions for Bold order init |
| `Observer/CheckoutObserver.php` | Modified | Placement guard + duplicate `public_id` handling |
| `etc/config.xml` | Modified | v2.1.6, FC saveOrder observers, empty Bold title |
| `sql/bold_checkout_payment_booster_setup/mysql4-upgrade-2.1.5-2.1.6.php` | **New** | Schema/version bump |
| `Model/Order.php` | Modified | Collection accessor |
| `Model/Resource/Order.php` | Modified | Collection factory |
| `Model/Resource/Order/Collection.php` | **New** | Order collection class |

### Mirrored in parent Magento tree (deploy / theme fallback)

These should stay in sync with the module `design/` copies:

| Path |
|------|
| `app/design/frontend/base/default/template/bold/checkout_payment_booster/payment/form/base.phtml` |
| `app/design/frontend/base/default/template/bold/checkout_payment_booster/payment/form/bold_method.phtml` |

`design/frontend/base/default/layout/bold/checkout.xml` — unchanged vs `main` in module repo (still registers `bold.payments.base` on `checkout_onepage_index` and `firecheckout_index_index`).

---

## 5. Per-file justification

### 5.1 `design/.../payment/form/bold_method.phtml`

**Why:** FireCheckout replaces this fragment on AJAX reload. Any JavaScript here re-ran and created a second `BoldPaymentMethod`.

**Changes:**

- Added wallet host: `#smart-ppcp-buttons` > `#paypal-button-container` (required by Bold EPS `renderWalletPayments`)
- Removed entire `BoldPaymentMethod` class and SPI/tokenize logic
- Kept only `window.bold.onBoldPaymentFormInjected()` hook so injection is detected

**Justification:** Separation of concerns — markup reloads; logic does not.

---

### 5.2 `design/.../payment/form/base.phtml`

**Why:** Central place for one runtime, FC hooks, and safe SPI/PPCP lifecycle.

**Major change groups:**

1. **`BoldPaymentMethod` (single instance)**  
   - `initialize()` no longer hides Bold when form not yet in DOM; uses `schedulePaymentFormBootstrapRetry()`  
   - `subscribeToSpiEvents()` once per runtime  

2. **Visibility & selection**  
   - `shouldShowBoldPaymentForm()`, `isBoldPaymentAvailableInCheckout()`, `syncBoldAsCurrentPaymentMethod()` for single-method FC  
   - `ensurePaymentFormVisible()` / `hidePaymentFormWhenNotSelected()` integrate with `payment.changeVisible`  

3. **DOM + SDK lifecycle**  
   - `resetPaymentFormContainer()` — full teardown (children, orphan SPI frames, fresh PPCP host, SDK reset)  
   - `renderSpiPayments()` — retry after “already rendered”  
   - `renderPpcpWalletButtons()` — wallets after SPI; host replace + retry on SDK stale state  
   - `renderPaymentForm(forceReset, hardReset)` — hard reset only once per path (not duplicated in `ensureBoldCheckoutUi`)  

4. **FireCheckout orchestration**  
   - `scheduleBoldPaymentSectionSync` / `runBoldPaymentSectionSync`  
   - `handleFirecheckoutCheckoutChange` for section keys: `payment-method`, `shipping-method`, `cart`, `total`, `billing`, `review`, `coupon-discount`  
   - Skip sync when `isPaymentUiReady()` (SPI + wallets) unless `hardReload`  

5. **Tokenize / place order guards**  
   - `canAttemptTokenize()`, `tokenizeCooldownUntil`, `isCompletingPayment`  
   - `wrapCheckoutSave` / `wrapPaymentSave` — avoid double tokenize when FireCheckout uses `checkout.save`  
   - `handleSpiTerminalFailure` debouncing  

6. **`BoldBase.resetPaymentsInstance()`**  
   - Clears cached `boldPaymentsInstance` so SPI can mount after DOM rebuild  

**Justification:** Addresses all reported FC issues without re-initializing on every `bold_method` script execution.

---

### 5.3 `Observer/PredispatchObserver.php`

**Why:** `initializeBoldOrder` only ran for actions in `$allowedActions`. FireCheckout page and AJAX actions were missing → no `getBoldCheckoutData()` → Bold payment method not available in re-rendered HTML.

**Changes:** Added:

- `firecheckout_index_index`
- `firecheckout_index_saveshippingmethod`
- `firecheckout_index_saveshipping`
- `firecheckout_index_updatesections`
- `firecheckout_index_savepayment`
- `firecheckout_onecolumn_index`

**Justification:** Session must contain Bold checkout data before payment block is rendered on FC.

---

### 5.4 `Service/Order/PlacementGuard.php` (new)

**Why:** Defensive server-side protection when frontend still fires duplicate place-order requests.

**Responsibilities (high level):**

- Detect Bold payment methods on incoming `saveOrder`
- `GET_LOCK` per `public_order_id` to serialize placement
- Find existing order by Bold `public_id`
- Quote/submission guards (`assertQuoteCanSubmit`)
- Logging duplicate attempts
- Return success response for idempotent retry when order already exists

**Justification:** Duplicate orders were observed in production-like FC testing; guards are required even after frontend fix.

---

### 5.5 `Observer/SaveOrderObserver.php` (new)

**Why:** Wire `PlacementGuard` into Magento dispatch.

**Changes:**

- `predispatchSaveOrder` on:
  - `checkout_onepage_saveOrder`
  - `firecheckout_index_saveOrder`
  - `firecheckout_onecolumn_saveOrder`
- `cleanupAfterSaveOrder` on matching `postdispatch` events

**Justification:** FireCheckout uses different controller actions than core onepage checkout.

---

### 5.6 `Observer/CheckoutObserver.php`

**Why:** Align order save pipeline with placement guard and race on `public_id` persistence.

**Changes (summary):**

- `beforeSaveOrder` calls `PlacementGuard::assertQuoteCanSubmit`
- `afterSaveOrder` handles duplicate key on `public_id` mapping without fatal loop

**Justification:** Backend consistency with frontend idempotency goals.

---

### 5.7 `etc/config.xml`

**Changes:**

- Module version `2.1.6`
- Register `SaveOrderObserver` on standard and FireCheckout saveOrder routes (see §5.5)
- Bold payment `<title></title>` emptied (UI title comes from SPI/PIGI, not duplicated label)

**Justification:** Configuration is the Magento 1 integration surface for observers and versioning.

---

### 5.8 `sql/.../mysql4-upgrade-2.1.5-2.1.6.php` (new)

**Why:** Versioned schema/data upgrade path for release `2.1.6`.

---

### 5.9 `Model/Order.php`, `Model/Resource/Order.php`, `Model/Resource/Order/Collection.php`

**Why:** Support querying Bold order mapping collection in `PlacementGuard` (e.g. find order by `public_id`).

**Changes:** Standard Magento 1 collection factory / `getCollection()` wiring.

---

## 6. Behaviour matrix (expected after changes)

| User action | Expected UI behaviour |
|-------------|----------------------|
| First load FC checkout, Bold only | Payment method + wallets + card visible |
| First load, Bold + Affirm | Both methods; Bold form when Bold selected |
| Change shipping | Form stays visible; wallets + card remain; **no** full SPI double load |
| Change shipping (payment-method section reloads) | One SPI init; wallets rendered if host empty |
| 3rd+ shipping change | Still visible (no aborted render from version bump race) |
| Select Affirm | Bold form hidden |
| Re-select Bold | Form visible; render only if UI not ready |
| Place order | Single tokenize path; server guards duplicate submit |

---

## 7. Console messages (interpretation)

| Message | Meaning | Handling |
|---------|---------|----------|
| `Loading SPI Frame...` | Normal SPI bootstrap | Once per hard reload |
| `SPI frame is already rendered` | SDK state out of sync with DOM | `resetPaymentFormContainer()` + retry |
| `smart-ppcp-buttons is already rendered` | PPCP host/SDK mismatch | Replace host, retry `renderWalletPayments` |
| `[Bold] SPI stale SDK state detected, resetting...` | Our recovery path | Expected during recovery |

---

## 8. Testing checklist

- [ ] FireCheckout: Bold as only payment method — form visible without full page refresh  
- [ ] FireCheckout: Bold + Affirm — toggle selection, form shows/hides correctly  
- [ ] Change shipping 4+ times — wallets + card remain  
- [ ] Console: one SPI init per payment-method reload, not two back-to-back  
- [ ] Place order once — single Magento order, single Bold authorization  
- [ ] Rapid double-click place order — guard blocks or idempotently succeeds  
- [ ] Standard `checkout/onepage` (if used) — regression smoke test  

---

## 9. Related documentation in repo

- `bold-m1-firecheckout-multi-init-fix.md` (parent repo) — broader multi-init plan; much of it is implemented via `base.phtml` runtime described above.

---

## 10. Commits on branch (reference)

From `git log main..HEAD` in `CheckoutPaymentBooster`:

```
76b2ae0 almost
bbc4350 almost
5b848d6 Checking order before creating it
41a2884 Checking order before creating it
```

---

## 11. Reviewer notes / trade-offs

1. **Wallets outside SPI iframe** — PPCP buttons render into `#smart-ppcp-buttons` above the iframe. The empty “wallet” row inside SPI is normal; buttons appear in the external host.  
2. **`MutationObserver`** — Safety net for injection; guarded to reduce render loops.  
3. **PlacementGuard** — Server-side last line of defence; frontend must still avoid duplicate tokenize for UX.  
4. **Template mirroring** — Changes must be deployed to both module `design/` and `app/design/frontend/base/default/` (or rely on Magento theme fallback rules).

---

*Generated for technical review and PR justification. Compare with `main` using the git commands in the header.*
