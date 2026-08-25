=== Automatic FFL ===
Contributors: refactoredgroup
Tags: woocommerce, ffl, firearms, ammunition, checkout
Tested up to: 6.9.4
Stable tag: 1.0.27
Requires PHP: 7.0
Requires at least: 5.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Sell firearms on WooCommerce? Add FFL dealer selection to checkout with an interactive map. ATF-validated, block checkout ready.

== Description ==

Selling firearms or ammunition through WooCommerce? Federal law requires those products to ship to a licensed FFL dealer — not directly to your customer. Automatic FFL adds an interactive dealer map to your checkout (classic and block-based), automatically detects FFL-required products in the cart, enforces state-level ammunition restrictions, and pulls dealer data daily from the ATF. Install in 5 minutes. 30-day free trial.

= Why store owners choose Automatic FFL =

* **5-minute install, no developer needed** — install the plugin, paste your store key, and you're live.
* **Interactive Google Maps dealer locator** — customers search by ZIP or address and pick a dealer in seconds, right inside checkout.
* **FFL popularity indicators on the map** — buyers see which dealers are most-used by other shoppers, reducing decision friction at checkout.
* **FFL certificate network** — when a customer ships to an in-network FFL, that dealer's license certificate is automatically attached to your order. No more chasing paperwork by email.
* **ATF-validated dealer database** — refreshed daily from the Bureau of Alcohol, Tobacco, Firearms and Explosives (ATF). You're never serving stale data.
* **Full block-based checkout support** — works with both the new WooCommerce Blocks checkout and the classic checkout, with the same dealer selection experience.
* **Automatic FFL detection** — analyzes the cart automatically and triggers FFL selection only when needed.
* **Mixed cart handling with save-and-restore** — when a cart contains both FFL-required and non-FFL items, customers can save either group, complete the FFL order, and have their saved items automatically restored to the cart.
* **State-level ammunition restrictions** — automatically enforces ammunition shipping rules per state. When a customer's state requires an FFL transfer for ammo, the dealer selector triggers automatically — even without a firearm in the cart.
* **Category-level AND product-level FFL marking** — flag entire product categories as FFL-required, or mark individual SKUs. Granular control without manual tagging.
* **Dealer customization** — promote in-network or preferred dealers, set transfer fees, add business hours, and control which dealers appear to your customers.
* **Same-day customer support** — real humans who know firearms compliance and WooCommerce.

= Pricing =

Automatic FFL starts at **$99/month with a 30-day free trial**. Annual billing is available by request for **$1,079/year**. Firearms + Ammo coverage is **$164/month or $1,776/year**. Cancel anytime.

Also available for BigCommerce and Magento — visit [automaticffl.com](https://www.automaticffl.com/) for details.

= Live demo =

Try the checkout experience yourself: [woo80.demos.automaticffl.com/shop/](https://woo80.demos.automaticffl.com/shop/)

== Installation ==

1. Install the plugin through the WordPress plugin directory, or upload the plugin files to `/wp-content/plugins/automaticffl-for-woocommerce/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Sign up for a free trial at [automaticffl.com](https://www.automaticffl.com/) to get your store key.
4. In WordPress admin, go to **WooCommerce > Settings > Automatic FFL** and paste your store key.
5. Mark FFL-required products by editing them and checking the **FFL Required** product type option, or mark entire product categories.
6. Test your checkout — when an FFL product is in the cart, the dealer selector will appear automatically.

For the full setup guide, visit [automaticffl.com/installation/woocommerce/](https://www.automaticffl.com/installation/woocommerce/).

== Frequently Asked Questions ==

= Does this work with the new WooCommerce block-based checkout? =

Yes. Automatic FFL fully supports both the classic WooCommerce checkout and the newer block-based (WooCommerce Blocks) checkout. The dealer selection experience is identical in both.

= How is your FFL dealer database kept current? =

We pull updated dealer records daily from the ATF (Bureau of Alcohol, Tobacco, Firearms and Explosives). When dealers change status, addresses, or licenses, your store reflects it within 24 hours.

= Do I need a developer to install this? =

No. Install the plugin from WordPress.org, paste your store key in the settings, and you're ready to go. Most stores are live in under 5 minutes.

= How does state-level ammunition compliance work? =

Automatic FFL knows which states require ammunition to ship through an FFL dealer. When a customer in one of those states adds ammo to their cart, the dealer selector triggers automatically — even if there's no firearm in the cart.

= What happens when a customer's cart has both firearms and non-firearm items? =

Customers can save either group for later. They complete the FFL-required order, and their saved items are automatically restored to the cart so they can finish a separate order. You don't lose the sale.

= What is the FFL certificate network? =

When a customer chooses to ship to an in-network FFL dealer, that dealer's license certificate is automatically attached to your order. You don't have to email or fax dealers asking for paperwork — it's already on the order.

= Is there a free trial? =

Yes. Automatic FFL starts at $99/month with a 30-day free trial. Annual billing is available by request for $1,079/year; Firearms + Ammo is $164/month or $1,776/year.

= Do you support BigCommerce or Magento? =

Yes. Automatic FFL is also available as a BigCommerce app and Magento extension. Visit [automaticffl.com](https://www.automaticffl.com/) for details.

== Screenshots ==

1. Customers with a firearm in their cart must choose an FFL location for shipment.
2. Automatic FFL Locator map.
3. Automatic FFL address selected.
4. Automatic FFL admin homepage.
5. Automatic FFL Dealer Management Page.
6. FFL Fees and Business Hours config.
7. Bulk enable/prefer FFL locations.
8. Allow us to automatically enable new Type 1 & 2 FFL dealers or take full control.

== Changelog ==

= 1.0.27 =
* Preserves the selected Automatic FFL dealer ID on Classic and Blocks orders.
* Reports successfully placed FFL orders asynchronously with bounded retries.
* Keeps payment, status, cancellation, refund, and customer data outside order attribution.
* Aligns the documented minimum requirements with WordPress 5.6 and PHP 7.0.

= 1.0.26 =
* Reworded the missing-certificate banner around saving time on this order and future orders.
* Clarified support for multiple PDFs, images, and ZIP files.

= 1.0.25 =
* Added a prominent, non-dismissible banner to eligible orders that are missing an FFL certificate.
* Kept the existing shipping-address upload action as a secondary option.

= 1.0.24 =
* Added a secure missing-certificate upload action to eligible WooCommerce orders.
* Automatically attaches a valid matching certificate after server-side processing without changing order status or notifying the customer.

= 1.0.23 =
* Fixed classic checkout layouts where the FFL firearm notice could wrap between floated shipping first/last name fields.

= 1.0.22 =
* Preserves customer billing while shipping FFL orders to the selected dealer when WooCommerce is configured to ship only to billing addresses.
* Uses the selected FFL dealer destination for classic checkout shipping-rate calculations.
* Passes the full selected dealer address through WooCommerce Blocks Store API extension data and applies it server-side.

= 1.0.19 =
* Refactored WooCommerce Blocks dealer selection to use the documented child-block API. Drops React portal targeting and CSS hiding of WooCommerce internal classes.
* Fixes the ammo-only restricted-state bug where the FFL dealer prompt rendered at the bottom of checkout instead of inside the shipping address section.
* Hides non-name shipping address fields when a dealer is selected (uses CSS :has() against standard form name attributes — no internal WC class targeting).
* On dealer pick, restores the customer's original address to billing and unchecks "use shipping as billing" so billing is never the FFL dealer.

= 1.0.16 =
* Links are now clickable in order comments

= 1.0.15 =
* Added save-for-later functionality for mixed carts containing both FFL and non-FFL items
* Added ammunition detection and state-based shipping restrictions

= 1.0.14 =
* Added WooCommerce Blocks (block-based checkout) support for FFL dealer selection

= 1.0.13 =
* Upgraded to new iframe-based dealer map for improved performance and reliability

= 1.0.12 =
* Replaced FontAwesome icons with SVGs for better theme compatibility (fixes issues with Divi theme and other themes that remove FontAwesome)

= 1.0.11 =
* Fixes redirect loop on cart page when WooCommerce payments is also installed

= 1.0.10 =
* Tested with latest WordPress

= 1.0.9 =
* Updated tested up to version

= 1.0.8 =
* Fixes bug where customers couldn't ship normal products to a different place than the billing address

= 1.0.7 =
* Adds shipping calculation trigger upon dealer selection

= 1.0.6 =
* CSS updates for checkout

= 1.0.5 =
* Updates for Wordpress standards

== External services ==

**Google Maps:** used to create the map experience during the checkout

* [Google Maps](https://developers.google.com/maps/)
* [Terms of service](https://cloud.google.com/maps-platform/terms)

**AutomaticFFL:** provides FFL dealer data, an interactive map experience, and product restrictions

Order certificate files selected by a store manager are uploaded to AutomaticFFL-managed Google Cloud Storage and processed by AutomaticFFL so a valid matching certificate can be attached to the WooCommerce order.

When an order using a selected FFL dealer is placed, the plugin sends the WooCommerce order ID, canonical dealer ID, FFL license, and order creation time to Automatic FFL for aggregate order attribution. Buyer information is not sent for this reporting.

* [AutomaticFFL](https://www.automaticffl.com/)
* [Privacy Policy](https://www.automaticffl.com/privacy-policy/)

== Support and Documentation ==

For support, inquiries, or documentation, please visit [https://automaticffl.com/](https://automaticffl.com/).
