# Plan: FFL Orders with Billing-Only Shipping Destination

**Track ID:** `ffl-billing-only-destination_20260513`

## Phase 1: Classic Checkout

- [x] Task: Add classic FFL order address normalization
    - [x] Replace inline order-shipping override with named plugin methods
    - [x] Reapply customer billing from posted checkout data
    - [x] Reapply dealer shipping from posted hidden shipping fields
    - [x] Run normalization late on `woocommerce_checkout_order_processed`

- [x] Task: Add classic FFL shipping-rate destination support
    - [x] Capture selected dealer destination during checkout update AJAX
    - [x] Store the destination in WooCommerce session
    - [x] Override package destination through `woocommerce_cart_shipping_packages`
    - [x] Clear the session value when dealer selection is absent or checkout finishes

- [x] Task: Update classic dealer-selection JavaScript
    - [x] Ensure hidden `ship_to_different_address=1` is posted after dealer selection
    - [x] Keep existing hidden `shipping_*` and FFL metadata fields

## Phase 2: Blocks Checkout

- [x] Task: Pass full dealer address through Blocks extension data
    - [x] Add dealer address fields to `setExtensionData`
    - [x] Clear dealer address extension data when FFL state resets
    - [x] Include dealer company in the Blocks shipping address update

- [x] Task: Set Blocks order shipping server-side
    - [x] Extend Store API schema and default data
    - [x] Set full order shipping address from extension data when `fflLicense` is present
    - [x] Preserve billing address

## Phase 3: Release Prep

- [x] Task: Build and update release metadata
    - [x] Run `npm run build`
    - [x] Update plugin version and release files to 1.0.22
    - [x] Update Conductor metadata

- [x] Task: Verification and commit
    - [x] Run focused syntax/build checks
    - [x] Review diff
    - [x] Commit with Conductor message and git note
