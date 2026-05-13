# Spec: Classic FFL Notice Layout Clearing

**Track ID:** `classic-ffl-notice-layout_20260513`
**Type:** Bugfix
**Created:** 2026-05-13

## Overview

Some classic checkout builders render shipping first and last name fields as floated columns and do not clear those floats before the `woocommerce_after_checkout_shipping_form` hook output. When Automatic FFL renders the firearm notice immediately after those fields, the notice text can wrap between the floated columns instead of starting below them.

## Requirements

- Keep the existing classic FFL dealer selector hook behavior.
- Ensure the firearm notice and dealer selector start below floated shipping-name fields.
- Avoid changing checkout behavior or field visibility.
- Keep the fix scoped to the classic dealer-selector template.

## Acceptance Criteria

- On the phone-orders/FunnelKit-style classic checkout, the firearm notice starts below the first/last name row.
- The notice text no longer wraps between the name fields.
- The Find a Dealer button and modal behavior are unchanged.
