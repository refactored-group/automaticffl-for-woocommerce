# Product Definition: Automatic FFL for WooCommerce

## Initial Concept
WordPress/WooCommerce plugin that integrates FFL (Federal Firearms License) dealer selection and ammunition restriction enforcement into the checkout process for firearms compliance.

## Vision
Automatic FFL for WooCommerce is the go-to compliance automation plugin for firearms and ammunition retailers on WooCommerce. It eliminates manual FFL coordination by providing an automated, customer-friendly dealer lookup and selection experience directly within checkout — ensuring every regulated order ships legally and correctly with minimal merchant effort.

## Target Users

### 1. Online Firearms Retailers
Gun shop owners running WooCommerce stores who sell firearms and must comply with federal shipping regulations requiring delivery to a licensed FFL dealer.

### 2. Ammunition-Only Retailers
Sellers of ammunition and related consumables who face varying state-level shipping restrictions and need automated enforcement to prevent illegal shipments.

### 3. Multi-Product Retailers
Stores selling a mix of firearms, ammunition, and non-regulated items (accessories, apparel, optics) that need intelligent cart analysis to route each product type through the correct compliance and shipping workflow.

## Core Value Proposition

- **Automated FFL Shipping Compliance** — Retailers connect once via a store hash and the plugin handles dealer lookups, map-based selection, and shipping address overrides automatically, eliminating manual FFL paperwork coordination.
- **Easy FFL Dealer Lookup** — Customers get an intuitive, map-based dealer search and selection experience embedded directly in checkout, reducing friction and cart abandonment.
- **Modern Checkout Compatibility** — Supports both classic WooCommerce checkout and the newer WooCommerce Blocks/Store API checkout, ensuring the plugin works regardless of the merchant's theme or checkout setup.
- **Extensive Customization** — Hooks, filters, settings screens, and template overrides allow merchants and developers to tailor behavior, messaging, and appearance to their specific store needs.
- **Compliance Information** — Provides merchants and customers with relevant regulatory information throughout the purchase flow.

## Key Features

### Interactive FFL Dealer Map
Iframe-based map powered by Google Maps integration, allowing customers to search for and select a licensed FFL dealer by location and radius during checkout. Includes preferred dealer markers, dealer details display, and automatic shipping address override to the selected dealer.

### Ammunition State Restriction Enforcement
State selector banner that detects ammo products in the cart and enforces state-level shipping restrictions. Blocks checkout for shipments to restricted states with customizable messaging and UI.

### WooCommerce Blocks Support
React-based FFL dealer selection block for the modern block-based checkout. Includes a Store API extension for headless and API-driven store implementations. Full feature parity with the classic checkout experience.

### Cart Analysis and Mixed-Cart Handling
Automatically detects FFL-required products, ammo-restricted products, and mixed carts. Enforces appropriate validation rules, displays contextual warnings, and manages shipping address overrides per product type.

### Admin Configuration and Product Management
- Store hash setup with automatic backend registration
- Sandbox/production mode toggle
- Google Maps API key configuration
- Per-product FFL requirement flag via product editor checkbox
- Bulk edit and quick edit support for FFL flags
- CSV import/export for FFL product metadata

### Save-for-Later
Allows customers to save their selected FFL dealer for future orders, reducing repeat selection friction.

### Order Integration
- FFL license number stored as order meta
- Dealer details added to order comments with clickable links
- Shipping address automatically set to selected dealer on FFL orders

## Technical Requirements
- WordPress 5.2+
- WooCommerce 3.5+
- PHP 7.0+
- Google Maps API key (for dealer map)
- AutomaticFFL store hash (merchant account)

## API Dependencies
- **AutomaticFFL API** — Store configuration and dealer search (production and sandbox environments)
- **Google Maps API** — Interactive dealer map rendering and geocoding
