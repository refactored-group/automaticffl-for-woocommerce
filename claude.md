# Conductor Context

This project uses [Conductor](https://github.com/gagarinyury/claude_conductor) for spec-driven development.

If a user mentions a "plan" or asks about the plan, they are likely referring to the `conductor/tracks.md` file or one of the track plans (`conductor/tracks/<track_id>/plan.md`).

## Universal File Resolution Protocol

**PROTOCOL: How to locate files.**
To find a file (e.g., "**Product Definition**") within a specific context (Project Root or a specific Track):

1.  **Identify Index:** Determine the relevant index file:
    -   **Project Context:** `conductor/index.md`
    -   **Track Context:**
        a. Resolve and read the **Tracks Registry** (via Project Context).
        b. Find the entry for the specific `<track_id>`.
        c. Follow the link provided in the registry to locate the track's folder. The index file is `<track_folder>/index.md`.
        d. **Fallback:** If the track is not yet registered (e.g., during creation) or the link is broken:
            1. Resolve the **Tracks Directory** (via Project Context).
            2. The index file is `<Tracks Directory>/<track_id>/index.md`.

2.  **Check Index:** Read the index file and look for a link with a matching or semantically similar label.

3.  **Resolve Path:** If a link is found, resolve its path **relative to the directory containing the `index.md` file**.
    -   *Example:* If `conductor/index.md` links to `./workflow.md`, the full path is `conductor/workflow.md`.

4.  **Fallback:** If the index file is missing or the link is absent, use the **Default Path** keys below.

5.  **Verify:** You MUST verify the resolved file actually exists on the disk.

**Standard Default Paths (Project):**
- **Product Definition**: `conductor/product.md`
- **Tech Stack**: `conductor/tech-stack.md`
- **Workflow**: `conductor/workflow.md`
- **Product Guidelines**: `conductor/product-guidelines.md`
- **Tracks Registry**: `conductor/tracks.md`
- **Tracks Directory**: `conductor/tracks/`

**Standard Default Paths (Track):**
- **Specification**: `conductor/tracks/<track_id>/spec.md`
- **Implementation Plan**: `conductor/tracks/<track_id>/plan.md`
- **Metadata**: `conductor/tracks/<track_id>/metadata.json`

## Core Rules

1.  **Context First:** Always check `conductor/` files before answering questions about the project domain or tech stack.
2.  **Specs over Chat:** When asked to build a feature, ALWAYS suggest creating a **Track** (`/conductor:new`) first, rather than coding immediately.
3.  **Plan Compliance:** When implementing (`/conductor:implement`), NEVER deviate from the `plan.md` without explicit user approval.
4.  **Workflow Adherence:** Follow the rules in `conductor/workflow.md` (e.g. commit message format) strictly.

## Available Commands

- `/conductor:setup` - Initialize Conductor in a new project
- `/conductor:new <description>` - Create a new feature track
- `/conductor:implement [track_name]` - Execute a track's plan
- `/conductor:status` - View project progress
- `/conductor:revert` - Revert previous work

---

# Automatic FFL for WooCommerce - Development Guide

## Project Overview

This is a WordPress/WooCommerce plugin that integrates FFL (Federal Firearms License) dealer selection into the checkout process. When customers purchase firearms, they must select a licensed FFL dealer for shipping compliance.

**Current Version:** 1.0.22
**Requires:** WordPress 5.2+, WooCommerce 3.5+, PHP 7.0+

## Project Structure

```
automaticffl-for-woocommerce/
├── automaticffl-for-woocommerce.php    # Plugin entry point
├── includes/
│   ├── class-plugin.php                # Main Plugin class (singleton)
│   ├── class-wc-ffl-loader.php         # Loader with environment checks
│   ├── functions.php                   # Helper functions
│   ├── helper/
│   │   └── class-config.php            # Configuration and API URLs
│   ├── views/
│   │   ├── class-cart.php              # Cart page UI/validation
│   │   └── class-checkout.php          # Dealer map and selection UI
│   ├── admin/
│   │   ├── class-settings.php          # Admin settings manager
│   │   ├── class-abstract-settings-screen.php
│   │   └── screens/
│   │       └── class-general.php       # General settings form
│   └── framework/
│       ├── class-helper.php            # Security utilities
│       └── plugin/
│           └── class-compatibility.php # Version checks
└── assets/
    ├── css/main.css                    # Checkout modal styling
    ├── images/                         # Markers and branding
    └── fonts/                          # Mulish font files
```

## Key Classes

| Class | File | Purpose |
|-------|------|---------|
| `AFFL_Loader` | `class-wc-ffl-loader.php` | Singleton loader; environment validation |
| `Plugin` | `class-plugin.php` | Main orchestrator; hooks/filters |
| `Config` | `helper/class-config.php` | API URLs; cart state detection |
| `Cart` | `views/class-cart.php` | Mixed cart validation |
| `Checkout` | `views/class-checkout.php` | FFL dealer selection UI |
| `Settings` | `admin/class-settings.php` | Admin settings tabs |
| `General` | `admin/screens/class-general.php` | Settings form fields |

## Namespacing

- **Primary:** `RefactoredGroup\AutomaticFFL`
- **Sub-namespaces:** `Helper`, `Views`, `Admin`, `Admin\Screens`, `Framework`, `Framework\Plugin`
- **File naming:** PSR-4 compliant → `class-{kebab-case}.php`

## Data Storage

**Product Meta:**
- `_ffl_required` - Flag marking product as requiring FFL (`yes`/`no`)

**Order Meta:**
- `_ffl_license_field` - Selected dealer's FFL license number

**WordPress Options:**
- `wc_ffl_store_hash` - Merchant's AutomaticFFL store ID
- `wc_ffl_sandbox_mode` - Sandbox mode toggle (1/0)
- `wc_ffl_google_maps_api_key` - Google Maps API key

## API Integrations

### AutomaticFFL API

- **Production:** `https://app.automaticffl.com/store-front/api`
- **Sandbox:** `https://app-stage.automaticffl.com/store-front/api`

**Endpoints:**
- `GET /stores/{store-hash}` - Store configuration
- `GET /{store-hash}/dealers?location={search}&radius={miles}` - Dealer search

### Google Maps API
- Used for interactive dealer map on checkout
- API key configured in admin settings

## Important Hooks

**Actions:**
- `woocommerce_before_cart_table` - Mixed cart validation
- `woocommerce_before_checkout_shipping_form` - Display FFL map
- `woocommerce_checkout_update_order_meta` - Save FFL license
- `woocommerce_checkout_create_order` - Override shipping address

**Filters:**
- `woocommerce_checkout_get_value` - Clear shipping fields for FFL
- `woocommerce_checkout_fields` - Modify shipping phone field
- `product_type_options` - Add FFL checkbox to product editor
- `wc_ffl_admin_settings_screens` - Extensibility for custom screens

## Development Workflow

- **No build process** - Direct PHP/CSS/JS development
- **No package managers** - Standalone plugin
- **No test suite** - Manual testing required

## Coding Standards

- Use `defined( 'ABSPATH' ) || exit;` at file start
- Sanitize all input: `sanitize_text_field()`, `wp_kses_post()`
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Verify nonces on form submissions
- Check capabilities: `manage_woocommerce`

## Common Development Tasks

### Add FFL Product Programmatically
```php
update_post_meta( $product_id, '_ffl_required', 'yes' );
```

### Check if Cart Has FFL Products
```php
use RefactoredGroup\AutomaticFFL\Helper\Config;
$has_ffl = Config::is_ffl_cart();
```

### Get API URL
```php
use RefactoredGroup\AutomaticFFL\Helper\Config;
$url = Config::get_api_endpoint();
```

## Version Management

When releasing a new version, the following files MUST be updated:

1. **changelog.txt** - Add new version entry with changes
2. **automaticffl-for-woocommerce.php** - Update `Version:` header comment
3. **README.md** - Update version number and changelog section
4. **claude.md** - Update "Current Version" in Project Overview

Ensure all four files reflect the same version number for consistency.

## Recent Changes

- **v1.0.22:** Preserves customer billing while shipping FFL orders to the selected dealer when WooCommerce is configured to ship only to billing addresses; uses dealer destination for classic checkout shipping-rate calculations; passes full dealer address through Blocks Store API extension data
- **v1.0.21:** Changed FFL metadata fields to semantic hidden inputs and added targeted CSS so checkout builders cannot show the hidden FFL license/UUID/company fields
- **v1.0.20:** Added classic checkout fallback rendering for Divi/billing-only templates; prevents duplicate FFL UI output; posts hidden shipping fields when checkout omits the shipping form; falls back to billing names for dealer shipping addresses
- **v1.0.19:** Refactored Blocks dealer selection to documented child-block API; fixed ammo-only restricted-state UI bug; reset FFL dealer when state changes after pick; hide shipping fields whenever FFL is required (not just post-pick); added "Shipping Address" heading to classic checkout; hide billing-equals-shipping toggle in Blocks; load Blocks CSS via `wp_enqueue_block_style`; restores customer billing on dealer pick
- **v1.0.18:** Captured customer first/last name on FFL shipping addresses
- **v1.0.17:** Dismissible admin review request notice
- **v1.0.16:** Links are clickable in order comments
- **v1.0.15:** Added save-for-later, checkout templates, product FFL meta, and refactor core classes
- **v1.0.14:** Added WooCommerce Blocks checkout support for FFL dealer selection
- **v1.0.13:** Upgraded to new iframe-based dealer map for improved performance
- **v1.0.12:** Replaced FontAwesome with SVG icons (Divi theme compatibility)
- **v1.0.11:** Fixed redirect loop with WooCommerce Payments
- **v1.0.8:** Fixed mixed-cart shipping address bug
- **v1.0.2:** Added CSV import/export and bulk/quick edit
