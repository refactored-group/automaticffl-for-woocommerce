# Tech Stack: Automatic FFL for WooCommerce

## Language
- **PHP 7.0+** — Primary server-side language for all plugin logic, hooks, and WooCommerce integration.

## Platform
- **WordPress 5.2+** — Content management system and plugin runtime environment.
- **WooCommerce 3.5+** — E-commerce framework providing checkout, cart, order, and product APIs.

## Frontend

### JavaScript
- **Vanilla JavaScript** — Used for classic checkout interactions (FFL map iframe communication, ammo state selector, save-for-later).
- **React (JSX)** — Used for WooCommerce Blocks integration (FFL dealer selection block, modal components, selected dealer card). Built with `@wordpress/scripts`.

### CSS
- **Vanilla CSS** — No preprocessor. Single `main.css` file for checkout modal styling, banners, and plugin UI. Relies on theme inheritance for general form/button styling.

### Fonts
- **Mulish** — Self-hosted TTF files (Light and Regular weights) used in the dealer map modal and branded elements.

## Build Tools
- **`@wordpress/scripts`** — Compiles React/JSX source files in `assets/js/blocks/` to production bundles in `build/`. Only used for WooCommerce Blocks components.
- **No build process for PHP or vanilla JS/CSS** — Direct development, no transpilation or bundling.

## Architecture
- **WordPress Plugin** — Standard plugin structure with a singleton loader pattern (`AFFL_Loader`).
- **Namespaced PHP Classes** — PSR-4 compliant naming under `RefactoredGroup\AutomaticFFL` with sub-namespaces (`Helper`, `Views`, `Admin`, `Blocks`, `Api`, `Framework`).
- **MVC-like Separation** — Views handle frontend output, helper classes manage business logic and configuration, admin classes handle settings UI.
- **WooCommerce Hooks/Filters** — Deep integration via actions and filters for checkout, cart, order, and product editor customization.

## External API Dependencies
- **AutomaticFFL REST API** — Store configuration retrieval and FFL dealer search. Supports production (`app.automaticffl.com`) and sandbox (`app-stage.automaticffl.com`) environments.
- **Google Maps JavaScript API** — Powers the interactive dealer map for location-based dealer search and selection.

## Compatibility
- **HPOS (High-Performance Order Storage)** — Declared compatible via `FeaturesUtil::declare_compatibility()`.
- **WooCommerce Blocks / Store API** — Full support via custom block registration and Store API extension for headless checkout flows.
- **Classic Checkout** — Full support via WooCommerce hooks and template overrides in `templates/checkout/`.

## Data Storage
- **WordPress Options API** — Plugin settings (store hash, sandbox mode, Google Maps API key).
- **Post Meta / Order Meta** — Per-product FFL flags (`_ffl_required`), per-order FFL license (`_ffl_license_field`).

## Testing
- **No automated test suite** — Manual testing required.
- **Sandbox mode** — Built-in sandbox API environment for testing without production data.

## Package Management
- **No PHP package manager** (no Composer) — Standalone plugin with no external PHP dependencies.
- **npm** — Used only for `@wordpress/scripts` build tooling for WooCommerce Blocks.
