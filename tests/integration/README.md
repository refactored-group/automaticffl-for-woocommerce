# Local WooCommerce Acceptance Fixture

This fixture is destructive only to records that it creates in a disposable localhost WordPress/WooCommerce site. Never run it against a merchant, staging, or production site.

## Prerequisites

1. Make a disposable copy of the plugin. In that copy only, change the API and iframe constants to exact loopback URLs for the local storefront service and local dealer-map application.
2. In that disposable copy only, add the exact local iframe origin (scheme, host, and port) to `Config::get_iframe_allowed_origins()`. Do not use `*` and do not ship this override.
3. Install and activate WooCommerce and the disposable plugin copy on a WordPress site whose Site URL is `localhost`, `127.0.0.1`, or `::1`.
4. Choose a unique local store hash, such as `local-woo-checkout-20261027`. The same value is supplied to both fixtures.

## Create WordPress products first

```bash
export WC_TEST_ROOT='/absolute/path/to/disposable-wordpress'
export AUTOMATICFFL_FIXTURE_STORE_HASH='local-woo-checkout-20261027'
wp --path="$WC_TEST_ROOT" eval-file "$PWD/tests/integration/setup-fixture.php"
```

The script aborts unless WordPress, the Automatic FFL API, and the iframe widget are all loopback destinations and the exact iframe origin is allowlisted. It creates firearm, ammunition, overlap, ordinary, and parent/variation products; a classic cart; separate classic and Blocks checkout pages; a disposable US free-shipping method; a local-only MU plugin that disables Application Passwords; and enables cash on delivery. Record the printed firearm and ammunition category IDs.

## Create matching backend rules

From `automatic-ffl-backend`, use the IDs printed by the WordPress fixture:

```bash
export WOO_STORE_HASH='local-woo-checkout-20261027'
export WOO_FIREARM_CATEGORY_ID='REPLACE_WITH_PRINTED_ID'
export WOO_AMMO_CATEGORY_ID='REPLACE_WITH_PRINTED_ID'
ASDF_ERLANG_VERSION=24.3.4 AUTOFFL_TEST_DISABLE_GOTH=1 MIX_ENV=test mix run \
  apps/store_front/test/support/woocommerce_checkout_fixture.exs
```

The backend script refuses non-test environments and non-loopback database hosts. Start the local storefront endpoint against that test database and confirm its URL matches the disposable plugin override. It creates no Stripe objects and makes no merchant HTTP calls.

## Acceptance

Use only cash on delivery and test customer data. Do not place a real charge. Exercise the printed classic-cart and checkout-page URLs for the matrix in the feature validation guide, including 101/201-product programmatic carts, later-batch failures through a local stub, state changes, mixed carts, Save for Later, billing-only shipping, and normal certificate links. Record actual versions, URLs, expected/actual behavior, and screenshots or logs in the feature `validation.md`.

The generated test MU plugin also provides two localhost-only browser helpers for repeatable cart checks. They are not part of the distributable plugin:

- `/?automaticffl_fixture_action=empty_cart` clears the current fixture cart and selected ammunition state.
- `/?automaticffl_fixture_action=ammo_state&state=CA` saves a two-letter fixture ammunition state; replace `CA` as needed.

## Cleanup

```bash
export AUTOMATICFFL_FIXTURE_CLEANUP='1'
wp --path="$WC_TEST_ROOT" eval-file "$PWD/tests/integration/setup-fixture.php"
unset AUTOMATICFFL_FIXTURE_CLEANUP
```

Cleanup removes the fixture products, pages, categories, shipping zone, and MU plugin. Remove the disposable WordPress site and disposable plugin copy after evidence is recorded. The test-only API/iframe overrides and MU plugin must not enter a release package.

## Checkout-choice regression checks

After `npm install`, the Classic handler harness accepts the jQuery asset from a local WordPress installation and makes no network/cart requests:

```sh
node tests/integration/checkout-choice-handlers.cjs "$WC_TEST_ROOT/wp-includes/js/jquery/jquery.js"
```

The ammunition dealer regression check executes the shipped Classic checkout templates in a local DOM. It verifies that normal and billing-only checkout retain dealers in unrestricted states through selection, replacement, and checkout updates, without making network requests:

```sh
node tests/integration/ammo-dealer-workflow.cjs "$WC_TEST_ROOT/wp-includes/js/jquery/jquery.js"
```

The saved-cart integration check targets the disposable Cart Block acceptance fixture (`wc_ffl_store_hash=local-cart-blocks-only`, option `affl_cart_fixture_ids`, loopback classification stubs). It refuses other hosts/stores. It creates and cleans up one test variable product, checks both checkout groups for firearm/ammo/variation carts, models completed checkout by emptying the remaining cart, then restores the saved group. It submits no order or payment:

```sh
wp --path="$WC_TEST_ROOT" eval-file "$PWD/tests/integration/checkout-choice-saved-cart.php"
```
