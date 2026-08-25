# Spec: Successful FFL Order Attribution

**Track ID:** `order-attribution_20260825`
**Type:** Feature
**Created:** 2026-08-25

## Overview

When Classic or Blocks checkout places a real WooCommerce order with a canonical selected FFL dealer, the plugin should asynchronously report that placement to Automatic FFL. Reporting must not delay or fail checkout, and later payment or order-lifecycle changes must not alter the attribution.

## Requirements

- Carry the map's canonical dealer `id` through Classic and Blocks checkout and persist it as `_ffl_dealer_id` with the existing license and UUID metadata.
- Enqueue reporting from `woocommerce_checkout_order_processed` and `woocommerce_store_api_checkout_order_processed` only after checkout metadata has been saved.
- Use Action Scheduler so the placement hooks never call Automatic FFL directly.
- Reload the order through `wc_get_order()` and send only order ID, dealer ID, FFL license, and the order creation timestamp.
- Authenticate the request with the plugin's stored WordPress Application Password credentials.
- Treat network failures, timeouts, `429`, and `5xx` responses as retryable with bounded exponential backoff.
- Treat incomplete metadata, invalid credentials, and other `4xx` responses as terminal and log minimal diagnostics without buyer data.
- Do not inspect order status or register payment, status, cancellation, refund, or thank-you hooks.
- Preserve Classic, Blocks, legacy order storage, and HPOS compatibility by using WooCommerce CRUD APIs.
- Synchronize version, stable tag, changelog, project-guide metadata, external-service disclosure, and rebuilt Blocks assets for release.

## Acceptance Criteria

- Classic and Blocks orders persist `_ffl_dealer_id`, `_ffl_license_field`, and the existing UUID metadata.
- Both placement hooks enqueue the same idempotent reporting action without making an HTTP request during checkout.
- A scheduled action sends the backend's exact authenticated minimal payload using the order creation time.
- `200` and `201` complete successfully; network errors, `429`, and `5xx` retry; other `4xx` responses stop.
- Retry delivery is bounded to five total attempts.
- Ordinary non-FFL orders become quiet no-ops when the queued action runs.
- Plugin release metadata is synchronized at `1.0.27`, and production Blocks assets contain `fflDealerId`.
