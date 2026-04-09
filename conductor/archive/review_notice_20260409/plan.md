# Implementation Plan: Dismissible Admin Review Request Notice

## Phase 1: Activation Timestamp

- [x] Task: Store activation timestamp on plugin activation
    - [x] In `includes/class-wc-ffl-loader.php` or the plugin entry point, hook into plugin activation to store `wc_ffl_activated_at` option with `current_time( 'timestamp' )` — only if the option does not already exist
    - [x] Verify the option persists across deactivation/reactivation cycles
- [x] Task: Conductor - User Manual Verification 'Phase 1: Activation Timestamp' (Protocol in workflow.md)

## Phase 2: Review Notice Class

- [x] Task: Create the Review_Notice class file
    - [x] Create `includes/admin/class-review-notice.php` with namespace `RefactoredGroup\AutomaticFFL\Admin`
    - [x] Add `defined( 'ABSPATH' ) || exit;` guard
    - [x] Define class `Review_Notice` with the following structure:
        - [x] Constants for option/meta keys (`ACTIVATED_AT_OPTION`, `DISMISSED_META`, `SNOOZED_META`), snooze duration, and nonce action
        - [x] `init()` method that hooks into `admin_notices` and `admin_init`
        - [x] `maybe_display_notice()` method that checks all four display conditions and renders the notice HTML
        - [x] `handle_action()` method on `admin_init` that processes dismiss/snooze GET parameters with nonce and capability verification
- [x] Task: Conductor - User Manual Verification 'Phase 2: Review Notice Class' (Protocol in workflow.md)

## Phase 3: Display Logic

- [x] Task: Implement display condition checks
    - [x] Check `wc_ffl_activated_at` option exists and is 14+ days old
    - [x] Check `current_user_can( 'manage_woocommerce' )`
    - [x] Check user meta `wc_ffl_review_dismissed` is not set
    - [x] Check user meta `wc_ffl_review_snoozed_until` is not set or has expired
    - [x] Return early (show nothing) if any condition fails
- [x] Task: Implement notice HTML rendering
    - [x] Use `notice notice-info is-dismissible` CSS classes
    - [x] Render the notice copy text with proper escaping (`esc_html()`)
    - [x] Render three action links with nonce-protected URLs:
        - [x] "Leave a review" — links to WordPress.org review page (`target="_blank"`) AND triggers permanent dismiss via nonce URL
        - [x] "Maybe later" — nonce-protected URL that sets snooze
        - [x] "I already did" — nonce-protected URL that sets permanent dismiss
    - [x] Generate action URLs using `wp_nonce_url()` with `add_query_arg()`
- [x] Task: Conductor - User Manual Verification 'Phase 3: Display Logic' (Protocol in workflow.md)

## Phase 4: Action Handling

- [x] Task: Implement dismiss and snooze action handler
    - [x] Hook into `admin_init` to check for the plugin's action GET parameter
    - [x] Verify nonce with `wp_verify_nonce()`
    - [x] Verify capability with `current_user_can( 'manage_woocommerce' )`
    - [x] For "dismiss" action: set user meta `wc_ffl_review_dismissed` to `1`
    - [x] For "snooze" action: set user meta `wc_ffl_review_snoozed_until` to `current_time( 'timestamp' ) + 14 days`
    - [x] Redirect back to the referring page using `wp_safe_redirect()` and `wp_get_referer()`
- [x] Task: Conductor - User Manual Verification 'Phase 4: Action Handling' (Protocol in workflow.md)

## Phase 5: Registration and Integration

- [x] Task: Register Review_Notice in the Plugin class
    - [x] In `includes/class-plugin.php`, add `use` import and call `Review_Notice::init()` in admin context
    - [x] Autoloader handles class loading — no manual `require_once` needed
    - [x] Ensure it only initializes in admin context (`is_admin()`)
- [x] Task: Final manual verification of complete flow
    - [x] Verify notice appears after 14 days
    - [x] Verify all three action links work correctly
    - [x] Verify per-user state independence
    - [x] Verify no inline styles or JS dependencies added
- [x] Task: Conductor - User Manual Verification 'Phase 5: Registration and Integration' (Protocol in workflow.md)
