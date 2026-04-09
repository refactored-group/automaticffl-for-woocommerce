# JavaScript Code Style Guide

## Standard
Follow the [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/) with WooCommerce Blocks conventions for React components.

## File Structure

### Vanilla JS (Classic Checkout)
- Located in `assets/js/`
- Self-contained scripts, no module bundling
- Enqueued via `wp_enqueue_script()` with proper dependencies

### React/JSX (WooCommerce Blocks)
- Located in `assets/js/blocks/`
- Component-based architecture in `components/` subdirectories
- Built with `@wordpress/scripts` — output to `build/`
- Use `.js` extension (JSX syntax handled by build tool)

## Formatting
- Use tabs for indentation (WordPress standard)
- Single quotes for strings
- Semicolons required
- Opening braces on the same line
- Space inside parentheses for control structures: `if ( condition )`
- No space inside parentheses for function calls in React components

## Naming Conventions
- **Files:** `kebab-case.js` for vanilla JS, `PascalCase.js` for React components
- **Variables/Functions:** `camelCase` (e.g., `dealerData`, `handleSelectDealer`)
- **React Components:** `PascalCase` (e.g., `DealerModal`, `FFLDealerSelection`)
- **Constants:** `UPPER_SNAKE_CASE` for true constants
- **CSS Classes:** `kebab-case` with `affl-` prefix (e.g., `affl-dealer-modal`)

## React / WooCommerce Blocks
- Use functional components with hooks (no class components)
- Import from `@wordpress/element` instead of `react` directly
- Use `@woocommerce/blocks-checkout` APIs for checkout integration
- Register blocks via `block.json` metadata
- Use `@wordpress/i18n` (`__()`, `sprintf()`) for translatable strings
- Keep components focused — one responsibility per component

## DOM Interaction (Vanilla JS)
- Use `document.querySelector()` / `querySelectorAll()` — no jQuery dependency for new code
- Use event delegation where appropriate
- Communicate with iframes via `postMessage` API
- Use `jQuery` only when interacting with WooCommerce's existing jQuery-dependent APIs

## Security
- Never use `innerHTML` with unsanitized data — use `textContent` or proper escaping
- Validate and sanitize data received from `postMessage` events
- Use nonces for AJAX requests to WordPress

## Error Handling
- Use `try/catch` for async operations and API calls
- Log errors to console in development, fail gracefully in production
- Never let JS errors break the WooCommerce checkout flow
