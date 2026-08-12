# Spec: WooCommerce Missing-Certificate Order Upload

**Track ID:** `order-certificate-upload_20260812`
**Type:** Feature
**Created:** 2026-08-12

## Overview

An editable WooCommerce order with a selected FFL license but no certificate UUID should let the merchant upload the missing certificate without leaving the order. The existing Automatic FFL storage, OCR, dealer matching, and canonical-certificate flow remains authoritative. The order is updated only after a valid matching certificate finishes processing.

## Requirements

- Show the upload control only when `_ffl_license_field` exists and `_ffl_uuid` is empty.
- Accept the existing PDF, image, and ZIP certificate formats and repeated multi-file batches.
- Upload directly to one-time, backend-derived GCS sessions; do not send certificate bytes through WordPress.
- Carry store, order, and expected-license context in the order-specific source path without adding application tables.
- Keep valid nonmatching certificates in the network but never attach them to the order.
- Enqueue the Woo callback only for a valid, unexpired matching canonical certificate.
- Bound callback retries to five attempts and bound the open page's status checks to 18 requests over 90 seconds.
- Update Woo order metadata and one private certificate note without customer email or order-status changes.
- Preserve ordinary dashboard uploads and existing classic/Blocks checkout behavior.

## Acceptance Criteria

- Authorized merchants can upload supported files from eligible legacy and HPOS order screens.
- The modal accurately distinguishes upload receipt from certificate validation.
- A matching certificate updates `_ffl_uuid`, `_ffl_expiration_date`, and a private certificate-link note.
- Invalid, expired, unreadable, unsupported, and wrong-license files do not update the order or enqueue a callback.
- Duplicate processing and callback delivery are idempotent.
- The browser stops checking after attachment, authorization failure, page exit, or 18 checks.
- WooCommerce plugin release metadata is synchronized at `1.0.24`.
