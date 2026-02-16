# Automatic FFL for WooCommerce - Development Guide

## Project Overview

This is a WordPress/WooCommerce plugin that integrates FFL (Federal Firearms License) dealer selection into the checkout process. When customers purchase firearms, they must select a licensed FFL dealer for shipping compliance.

**Current Version:** 1.0.16
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

- **v1.0.16:** Links are clickable in order comments
- **v1.0.15:** Added save-for-later, checkout templates, product FFL meta, and refactor core classes
- **v1.0.14:** Added WooCommerce Blocks checkout support for FFL dealer selection
- **v1.0.13:** Upgraded to new iframe-based dealer map for improved performance
- **v1.0.12:** Replaced FontAwesome with SVG icons (Divi theme compatibility)
- **v1.0.11:** Fixed redirect loop with WooCommerce Payments
- **v1.0.8:** Fixed mixed-cart shipping address bug
- **v1.0.2:** Added CSV import/export and bulk/quick edit