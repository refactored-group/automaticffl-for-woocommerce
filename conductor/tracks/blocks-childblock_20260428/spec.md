# Spec: Blocks Child-Block Refactor for FFL Dealer Selection

**Track ID:** `blocks-childblock_20260428`
**Type:** Refactor
**Created:** 2026-04-28

## Overview

The WooCommerce Blocks integration for FFL dealer selection currently renders via two patterns that WooCommerce documentation explicitly discourages:

1. **React `createPortal`** into `.wp-block-woocommerce-checkout-shipping-address-block` — a private, internal block class name not part of the public extension API.
2. **CSS targeting** `.wc-block-components-address-card-wrapper` and `.wc-block-components-address-form` — internal component classes the WC Blocks team has stated may change without notice.

Both approaches put the plugin at risk of silent breakage on WC Blocks updates and are flagged by the WC theming guide as unsupported. They also produce a user-visible bug: in the ammo-only restricted-state flow, the FFL UI only portals into the shipping section after a dealer is locked in (`ammoFflLocked = true`), so before dealer selection the component renders in its default registered location at the bottom of the checkout — below billing, below shipping options. Customers see "FFL dealer required" + Find a Dealer button at the very bottom of the form, far from the shipping context where it belongs.

This track migrates the rendering layer to the documented WC Blocks extension API. The Store API extension (`fflLicense`, `fflCompanyName`, `fflExpirationDate`, `fflUuid`) and all server-side order processing remain untouched — only how the UI is rendered changes.

## Decisions Made

- **Scope:** Blocks rendering refactor only. Classic checkout fixes (Issues 1+2 from earlier diagnosis: hook position, missing h3, extending field-move to firearms) are handled separately on the existing `gb/name-fields` branch.
- **Address card strategy:** When a dealer is selected, `setShippingAddress` populates the WC shipping address card with the dealer's address while preserving the customer's first/last name. The card is the single source of truth for "where this ships." After dealer selection, address fields except `first_name` and `last_name` become disabled to prevent accidental edits to the dealer's address. The customer can still correct their own name and trigger "Change Dealer" to swap dealer.
- **Block registration:** Always whitelisted as a child of `woocommerce/checkout-shipping-address-block` via `additionalCartCheckoutInnerBlockTypes`. The component itself decides whether to render content based on cart state via `useSelect(CART_STORE_KEY)` and returns `null` for non-FFL carts. Single source of truth for "is this an FFL cart?" lives in JS, handles mid-checkout cart mutations naturally.

## Functional Requirements

### FR-1: Register FFL UI as a Gutenberg child block

- A block.json defines a new block (proposed name: `automaticffl/dealer-selection`) with `parent: ["woocommerce/checkout-shipping-address-block"]` in its metadata.
- The block is registered server-side via `register_block_type()` in `class-blocks-integration.php`.
- The block is registered for the WC Blocks checkout via `registerCheckoutBlock({ metadata, component })` in the JS entry point.
- The block is whitelisted as a permitted inner block of `woocommerce/checkout-shipping-address-block` via the `additionalCartCheckoutInnerBlockTypes` filter (registered via `registerCheckoutFilters`).

### FR-2: Replace portal + private CSS with native rendering

- Remove the `createPortal` logic from `FFLDealerSelection.js`.
- Remove the `automaticffl-portal-target` container creation/cleanup.
- Remove the body-class `automaticffl-checkout-active` toggle (driven the now-deleted CSS).
- Remove the `MutationObserver` that watched for the `Use same address for billing` checkbox.
- Remove the CSS rules in `assets/css/main.css` that target `.wc-block-components-address-card-wrapper` and `.wc-block-components-address-form`.
- Remove the inline `automaticffl-name-fields` first/last name inputs from the React component (the WC card now owns those fields).

### FR-3: Address card displays the dealer

- When a dealer is selected (postMessage `dealerUpdate` from iframe), the existing `setShippingAddress` call populates the WC shipping address card with `address_1`, `address_2`, `city`, `state`, `postcode`, `country`, `phone` from the dealer payload.
- `first_name` and `last_name` are preserved from current cart shipping state (not overwritten by the dealer payload).
- `shipping_company` is set to the dealer's business name via the existing Store API extension `fflCompanyName` server-side path.

### FR-4: Hide shipping address fields after dealer pick (Phase 1 decision)

- When a dealer is selected, all WC shipping address fields **except** `first_name` and `last_name` are **hidden** via CSS targeting standard form input `name` attributes.
- A body class we own (`automaticffl-dealer-locked`) gates the hide. CSS uses `:has()` to target the wrapper div by what it contains:
    ```css
    body.automaticffl-dealer-locked div:has(> input[name="address_1"]),
    body.automaticffl-dealer-locked div:has(> input[name="address_2"]),
    body.automaticffl-dealer-locked div:has(> input[name="city"]),
    body.automaticffl-dealer-locked div:has(> select[name="state"]),
    body.automaticffl-dealer-locked div:has(> input[name="postcode"]),
    body.automaticffl-dealer-locked div:has(> select[name="country"]),
    body.automaticffl-dealer-locked div:has(> input[name="phone"]),
    body.automaticffl-dealer-locked div:has(> input[name="company"]) { display: none; }
    ```
- The `name` attributes are part of the public Store API order schema — stable contract. No `.wc-block-components-*` or `.wp-block-*` class targeting.
- The body class is added/removed by our React component's `useEffect` based on `selectedDealer` state.
- Layout when dealer is selected: name fields visible → our prominent `SelectedDealerCard` with "Change Dealer" button → address fields hidden.
- "Change Dealer" remains functional and overwrites the dealer's address via `setShippingAddress` when a new dealer is picked.
- On dealer clear (ammo customer switches to unrestricted state): body class removed, fields reappear normally.
- `:has()` browser support: Safari 15.4+, Chrome 105+, Firefox 121+ — within WC Blocks' modern browser baseline.

### FR-4a: Billing address handling on dealer pick (Phase 1 addition)

- When a dealer is selected and `getUseShippingAsBilling()` is true:
    1. Read the customer's pre-dealer shipping address from the cart store (this is their original input before `setShippingAddress` overwrites it).
    2. Call `setShippingAddress(dealerAddress)` (preserves first/last name).
    3. Call `setBillingAddress(customerOriginalAddress)` to keep billing on the customer.
    4. Call `__internalSetUseShippingAsBilling(false)` to flip the toggle off so billing fields appear.
- All cart-store calls are public (`setShippingAddress`, `setBillingAddress`, `getUseShippingAsBilling`). The `__internalSetUseShippingAsBilling` action is internal (`__` prefix) — wrap in try/catch with graceful no-op fallback, matching the existing `__internalSetExtensionData` pattern in `FFLDealerSelection.js`.
- Why: when a dealer is shipping the firearm/ammo, billing must NOT be the dealer's address. Without this handling, customers who left "use same as shipping" checked would be billing the FFL.

### FR-5: Conditional rendering inside the child block component

- The component returns `null` (renders nothing) when:
  - The cart has no FFL products (no firearms, no ammo).
  - The cart is mixed FFL + regular (different validation flow elsewhere).
  - The cart is ammo-only AND in an unrestricted state (no FFL needed).
- The component renders the dealer selection UI when:
  - Cart has firearms.
  - Cart is ammo-only AND in a restricted state.

### FR-6: Cleanup of removed code paths

- Remove `useState`-based `portalTarget` tracking.
- Remove the persisted `persistedAmmoFflLocked` global if it's only used for the portal-trigger condition. (Verify before removing.)
- Update component dependencies, comments, and prop types to reflect the simpler rendering model.

## Non-Functional Requirements

### NFR-1: Doc-clean APIs only

Every WC Blocks integration point used must be documented in the public WooCommerce developer docs. Cite the source URL in code comments at the registration sites. No `.wp-block-*` class targeting, no `.wc-block-components-*` class targeting in CSS or JS.

### NFR-2: Backwards compatibility

- Classic checkout flow must remain functional and unchanged in behavior.
- Existing Store API extension (`fflLicense`, `fflCompanyName`, `fflExpirationDate`, `fflUuid`) stays the same — schema, `update_order_from_request` callback, and server-side order processing untouched.
- Existing iframe postMessage protocol stays the same — same dealer payload shape, same allowed origins.

### NFR-3: Build pipeline

- New block requires a build entry point in `webpack.config.js` (or `@wordpress/scripts` config) outputting to `build/dealer-selection/`.
- `npm run build` produces `block.json`, `index.js`, `style-index.css`, `view.js`, and `block.json` asset metadata.
- Build artifacts committed alongside source per existing project convention.

### NFR-4: Bundle size

The new child block must not significantly increase the total JS bundle size compared to the current `ffl-dealer-selection-frontend.js`. The portal removal and `MutationObserver` removal should offset any new registration overhead.

## Acceptance Criteria

### AC-1: Doc compliance
- No code references `.wp-block-woocommerce-checkout-shipping-address-block` for DOM lookup.
- No CSS rules target `.wc-block-components-*` classes.
- No `createPortal` calls in `FFLDealerSelection.js`.

### AC-2: Ammo-only restricted-state UX (the original bug — Issue 4)
- Cart contains only ammo, customer enters a restricted state in the shipping address card.
- The "FFL dealer required" banner and "Find a Dealer" button appear **inside the shipping address section**, not at the bottom of the checkout.
- This is true *before* a dealer is selected, not just after.

### AC-3: Firearms flow
- Cart contains firearms.
- The FFL child block appears inside the shipping address section as soon as the page loads.
- "Find a Dealer" button is visible from page load.

### AC-4: Dealer selection populates the card
- Customer enters first/last name in the WC shipping address card.
- Customer clicks "Find a Dealer", picks a dealer in the iframe.
- The WC shipping address card updates: address fields show dealer's address; first/last name remain as customer entered.
- All address fields except first/last name become disabled (or contingency banner shown per FR-4).
- A "Change Dealer" affordance is visible.

### AC-5: Change dealer
- After dealer pick, customer clicks "Change Dealer".
- Iframe re-opens.
- Customer picks a different dealer.
- Card updates to new dealer's address. Fields stay locked except first/last name.

### AC-6: Ammo state transition
- Cart is ammo-only. Customer is in a restricted state with a dealer selected.
- Customer changes the state to an unrestricted state in the WC card.
- The FFL child block returns `null` (no longer rendered).
- Address fields unlock.
- The previously-set dealer data is cleared (existing JS handles this — no regression).

### AC-7: Non-FFL cart
- Cart has only regular products.
- The FFL child block is registered but renders `null`.
- No DOM elements introduced into the shipping section.
- No CSS classes added to body.

### AC-8: Classic checkout regression check
- Existing classic checkout flow with the FFL map iframe works identically to before this track.
- Order placement, dealer selection, shipping address override on order create — all behave the same.

### AC-9: Build artifacts and editor sanity
- `npm run build` runs cleanly with no errors.
- Resulting bundle loads in the WC Blocks checkout without console errors.
- The block appears in the block editor's inserter under the shipping address block context.
- No editor errors or block-validation warnings.

## Out of Scope

- Classic checkout fixes (`get_ffl()` hook position, ffl-map.php h3, firearms-side field move) — separate work on `gb/name-fields`.
- Changes to the AutomaticFFL backend API.
- Changes to the iframe map's postMessage protocol or dealer payload shape.
- Changes to the Store API extension schema (`fflLicense`, `fflCompanyName`, etc.).
- Changes to server-side order processing (`update_order_from_request`).
- Changes to the order confirmation, order email, or admin order display.
- Translating new strings introduced by the refactor (existing text domain reuse only).
- Integration tests / E2E test harness — project has no test suite per `tech-stack.md`; manual verification only per `workflow.md`.
