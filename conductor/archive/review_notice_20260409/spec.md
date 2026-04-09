# Specification: Dismissible Admin Review Request Notice

## Overview
Add a dismissible WordPress admin notice to the Automatic FFL plugin that asks store owners to leave a review on WordPress.org. The notice appears after 14 days of plugin activation and supports permanent dismissal and snooze (14-day delay) actions per user.

## Functional Requirements

### Display Conditions
The notice MUST only display when ALL of the following are true:
1. The plugin has been activated for 14 or more days (tracked via `wc_ffl_activated_at` option)
2. The current user has the `manage_woocommerce` capability
3. The user has NOT permanently dismissed the notice (stored in user meta)
4. The user has NOT snoozed the notice within the last 14 days (stored in user meta)

### Activation Timestamp
- On plugin activation, store the current timestamp in the `wc_ffl_activated_at` WordPress option — but only if the option does not already exist (preserves original activation date across deactivation/reactivation cycles).

### Notice Content
- Style: `notice-info` (not warning or error)
- Dismissible: Yes (standard WordPress dismissible notice)
- Copy: "Enjoying Automatic FFL? You've been using it for a couple of weeks now — if it's been working well for your store, we'd really appreciate a quick review on WordPress.org. It helps other firearms retailers find us. Thanks!"

### Action Links
The notice must include three action links:

1. **"Leave a review"**
   - Opens `https://wordpress.org/support/plugin/automatic-ffl-for-wc/reviews/#new-post` in a new tab
   - Permanently dismisses the notice for the current user

2. **"Maybe later"**
   - Snoozes the notice for 14 days for the current user
   - After 14 days, the notice reappears

3. **"I already did"**
   - Permanently dismisses the notice for the current user

### State Storage
- **Activation date:** Stored in `wp_options` table via `wc_ffl_activated_at` option (shared across all users)
- **Dismissal state:** Stored in user meta (per-user), so each admin user has independent dismiss/snooze state
- **User meta keys:**
  - `wc_ffl_review_dismissed` — permanent dismissal flag
  - `wc_ffl_review_snoozed_until` — timestamp until which the notice is hidden

### Action Handling
- Dismiss/snooze actions handled via `admin_init` GET parameter with nonce verification
- Nonce action: verify before processing any dismiss/snooze request
- Capability check: verify `manage_woocommerce` before processing
- Redirect back to the referring admin page after processing

## Non-Functional Requirements
- No JavaScript required — use standard WordPress admin notice with nonce-protected action links
- No inline styles — use only WordPress default admin notice CSS classes
- No external dependencies
- Follow WordPress/WooCommerce coding standards (sanitize input, escape output, verify nonces, check capabilities)

## Implementation Details
- New class: `RefactoredGroup\AutomaticFFL\Admin\Review_Notice`
- File: `includes/admin/class-review-notice.php`
- Registration: Hook into `includes/class-plugin.php` where other admin hooks are registered

## Acceptance Criteria
1. Notice does not appear before 14 days after activation
2. Notice appears after 14 days for users with `manage_woocommerce` capability
3. "Leave a review" opens the review URL in a new tab and permanently hides the notice
4. "Maybe later" hides the notice for exactly 14 days, then it reappears
5. "I already did" permanently hides the notice
6. Each admin user has independent dismiss/snooze state
7. Deactivating and reactivating the plugin does not reset the activation date
8. All actions are nonce-protected and capability-checked
9. No console errors, no inline styles, no external dependencies

## Out of Scope
- Email-based review requests
- Review status tracking or analytics
- Customizable notice timing or copy via admin settings
- Notice display on non-admin pages
