# Plan: Successful FFL Order Attribution

**Track ID:** `order-attribution_20260825`

## Phase 1: WooCommerce Plugin

- [x] Task: Persist canonical dealer identity and asynchronously report successful FFL order placement
  - [x] Carry dealer ID through Classic checkout and clear it with dealer state
  - [x] Carry dealer ID through Blocks extension data and the Store API schema
  - [x] Persist dealer ID through `WC_Order` APIs for Classic and Blocks
  - [x] Enqueue both placement hooks without blocking checkout
  - [x] Authenticate the minimal backend request with existing Application Password credentials
  - [x] Add terminal error handling and five-attempt bounded exponential retries
  - [x] Rebuild Blocks assets and synchronize release metadata at `1.0.27`

## Phase 2: Verification and Release

- [x] Task: Run source, PHP, Blocks build, payload-contract, retry, and release-metadata checks
- [ ] Task: Verify real Classic and Blocks checkouts under HPOS and legacy order storage
- [ ] Task: Complete track metadata and checkpoint the implementation
