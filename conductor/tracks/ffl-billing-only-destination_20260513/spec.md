# Spec: FFL Orders with Billing-Only Shipping Destination

**Track ID:** `ffl-billing-only-destination_20260513`
**Type:** Bugfix
**Created:** 2026-05-13

## Overview

WooCommerce stores can set the shipping destination to billing-only. In that mode WooCommerce treats the billing address as the shipping destination for checkout forms, checkout AJAX, and shipping-rate calculation. That is correct for normal products, but FFL orders are a regulated exception: the customer billing address must remain the customer's address, while the shipping address must be the selected FFL dealer.

Automatic FFL should own this exception without changing the merchant's global WooCommerce setting. The fix must be scoped to orders where a dealer was actually selected.

## Requirements

- Preserve WooCommerce's billing-only setting for non-FFL orders.
- For classic checkout FFL orders, keep billing as customer billing and shipping as selected dealer.
- For classic checkout shipping-rate calculations, use the selected dealer destination when an FFL dealer has been selected.
- For Blocks checkout FFL orders, pass the full selected dealer address through Store API extension data and set the order shipping address server-side.
- Keep existing FFL metadata fields and order notes.
- Keep existing customer shipping-address restore behavior.
- Do not force global WooCommerce option changes.

## Acceptance Criteria

- Classic checkout with `woocommerce_ship_to_destination = billing_only` creates FFL orders with customer billing and dealer shipping.
- Classic checkout rates are calculated from the selected dealer destination after dealer selection.
- Classic checkout with normal shipping settings continues to work.
- Blocks checkout creates FFL orders with dealer shipping from extension data.
- Non-FFL orders are untouched.
- Version metadata and changelog are updated for release.
