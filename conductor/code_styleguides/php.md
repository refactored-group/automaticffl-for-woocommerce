# PHP Code Style Guide

## Standard
Follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) with WooCommerce plugin conventions.

## File Structure
- Every PHP file must begin with `defined( 'ABSPATH' ) || exit;`
- File naming: `class-{kebab-case}.php` (PSR-4 compliant)
- One class per file
- Namespace: `RefactoredGroup\AutomaticFFL\{SubNamespace}`

## Formatting
- Use tabs for indentation, not spaces
- Opening braces on the same line for functions and control structures
- Space inside parentheses: `if ( $condition )`, `function_name( $arg )`
- Space after commas in function arguments
- No trailing whitespace
- Single blank line between methods

## Naming Conventions
- **Classes:** `PascalCase` (e.g., `CartAnalyzer`, `BlocksIntegration`)
- **Methods/Functions:** `snake_case` (e.g., `is_ffl_cart()`, `get_api_endpoint()`)
- **Variables:** `snake_case` (e.g., `$store_hash`, `$ffl_required`)
- **Constants:** `UPPER_SNAKE_CASE` (e.g., `AFFL_VERSION`, `_AFFL_LOADER_`)
- **Hooks:** Prefix with `wc_ffl_` for custom hooks (e.g., `wc_ffl_admin_settings_screens`)

## Security
- **Sanitize all input:** `sanitize_text_field()`, `wp_kses_post()`, `absint()`, `sanitize_key()`
- **Escape all output:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- **Verify nonces** on all form submissions: `wp_verify_nonce()`
- **Check capabilities** before admin actions: `current_user_can( 'manage_woocommerce' )`
- Never trust `$_GET`, `$_POST`, `$_REQUEST` without sanitization

## WordPress/WooCommerce Patterns
- Use WordPress Options API for plugin settings (`get_option()`, `update_option()`)
- Use post meta / order meta for per-entity data (`get_post_meta()`, `update_post_meta()`)
- Use WooCommerce hooks and filters — avoid overriding templates when hooks suffice
- Use `wp_enqueue_script()` / `wp_enqueue_style()` for assets — never inline
- Use `wp_localize_script()` or `wp_add_inline_script()` to pass PHP data to JS
- Declare HPOS compatibility via `FeaturesUtil::declare_compatibility()`

## PHP Version
- Target PHP 7.0+ minimum
- No use of PHP 7.1+ features (nullable types, void return, etc.)
- Use array syntax `array()` or short syntax `[]` consistently (project uses both — prefer `[]` for new code)

## Documentation
- Use PHPDoc blocks for classes and public methods
- Include `@param`, `@return`, and `@throws` tags
- Include `@since` version tag for new methods
- Include `@package AutomaticFFL` on class-level docblocks
