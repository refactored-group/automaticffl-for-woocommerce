# Plan: WooCommerce Missing-Certificate Order Upload

**Track ID:** `order-certificate-upload_20260812`

## Phase 1: AutoFFL Backend

- [x] Task: Harden WooCommerce credential registration
- [x] Task: Issue exact-path resumable GCS upload sessions
- [x] Task: Carry optional Woo order context through certificate upsert
- [x] Task: Deliver matching canonical certificates with a bounded Oban worker

## Phase 2: Certificate Processor

- [x] Task: Decode direct and ZIP-parent Woo order context
- [x] Task: Send optional context with the existing certificate upsert
- [x] Task: Preserve ordinary uploads and exact-generation completion behavior

## Phase 3: WooCommerce Plugin

- [x] Task: Add authenticated upload, status, callback, and registration-verification REST routes
- [x] Task: Add the eligible-order upload UI and bounded local status check
- [x] Task: Promote the missing-certificate action with a prominent order-page banner
- [x] Task: Refine the banner around merchant benefits and supported batch formats
- [x] Task: Update order metadata and one private certificate note idempotently

## Phase 4: Verification and Release

- [ ] Task: Run focused backend, processor, PHP, JavaScript, HPOS, and legacy checks
  - [x] Backend, processor, PHP, JavaScript, and callback contract checks
  - [ ] Live classic/Blocks plus HPOS/legacy order-editor checks
- [x] Task: Update release metadata to 1.0.26
- [ ] Task: Complete track metadata and checkpoint the implementation
