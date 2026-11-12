/** Exercise the shipped Classic templates with local jQuery; no network requests. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');

const jqueryPath = process.argv[2];
if (!jqueryPath) throw new Error('Pass the path to wp-includes/js/jquery/jquery.js');
const scripts = execFileSync('php', ['-r', `
define('ABSPATH', __DIR__);
function wp_json_encode($value) { return json_encode($value); }
function esc_js($value) { return addslashes($value); }
function __($value, $domain) { return $value; }
$restricted_states = array('CA', 'NY', 'DC', 'AS', 'GU', 'MP', 'PR', 'VI');
$allowed_origins = array('http://localhost:9090');
$messages = array('ammoSelectState' => 'Select a state', 'fflRequiredForState' => 'Select a dealer', 'selectDealerBeforeOrder' => 'Dealer required');
include 'templates/checkout/ammo-state-selector-js.php';
include 'templates/checkout/ffl-map-js.php';
`], { encoding: 'utf8' });

(async () => {
  for (const billingOnly of [false, true]) {
    const fields = ['first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone'];
    const dom = new JSDOM(`<form class="checkout">
      <input id="billing_country" value="US"><input id="billing_state" value="CA"><input id="billing_first_name" value="Alex"><input id="billing_last_name" value="Shopper">
      ${billingOnly ? '' : `<div id="ship-to-different-address"><input type="checkbox" id="ship-to-different-address-checkbox" name="ship_to_different_address" checked></div>
      <div class="shipping_address">${fields.map(field => `<p id="shipping_${field}_field"><input id="shipping_${field}" name="shipping_${field}" value="${field === 'state' ? 'CA' : (field === 'country' ? 'US' : '')}"></p>`).join('')}</div>`}
      ${['dealer_id', 'license_field', 'expiration_date', 'uuid', 'company_name'].map(field => `<input type="hidden" id="ffl_${field}" name="ffl_${field}">`).join('')}
      <div id="automaticffl-grouped-fields-anchor"></div><div id="automaticffl-state-message"></div>
      <div id="automaticffl-ffl-container"><div id="automaticffl-dealer-selected"></div><button type="button" id="automaticffl-select-dealer">Find a Dealer</button></div>
      <div class="automaticffl-dealer-layer"></div>
      <div id="automaticffl-dealer-card-template"><p class="customer-name"></p><p class="dealer-name"></p><p class="dealer-address"></p><a><span class="dealer-phone-formatted"></span></a></div>
    </form>`, { url: 'http://localhost/checkout/', runScripts: 'outside-only' });
    try {
      const w = dom.window;
      w.eval(fs.readFileSync(jqueryPath, 'utf8'));
      w.eval(fs.readFileSync('assets/js/ammo-destination.js', 'utf8'));
      const $ = w.jQuery;
      for (const match of scripts.matchAll(/<script>([\s\S]*?)<\/script>/g)) w.eval(match[1]);
      await new Promise(resolve => $(resolve));
      assert.equal($('form.checkout').triggerHandler('checkout_place_order'), false, 'restricted ammo requires a dealer');
      assert.equal($('.automaticffl-checkout-error').text(), 'Dealer required', 'restricted ammo shows one dealer error');

      for (const [id, state] of [[101, 'TX'], [202, 'CO'], [303, 'DC']]) {
        w.dispatchEvent(new w.MessageEvent('message', {
          origin: 'http://localhost:9090',
          data: { type: 'dealerUpdate', value: { id, fflID: `${state}-license`, company: `${state} dealer`, address1: 'Dealer street', city: 'Dealer city', stateOrProvinceCode: state, postalCode: '12345', phone: '5555550100' } },
        }));
        $(w.document.body).trigger('updated_checkout').trigger('updated_checkout');
        assert.equal($('#ffl_license_field').val(), `${state}-license`);
        assert.equal($('#ffl_dealer_id').val(), String(id));
        assert.equal($('#shipping_state').val(), state);
        assert.equal($('#shipping_address_1').val(), 'Dealer street');
        assert.equal($('#automaticffl-select-dealer').text(), 'Change Dealer');
        assert.notEqual($('#automaticffl-ffl-container').css('display'), 'none');
        assert.equal($('#automaticffl-dealer-selected .dealer-name').text(), `${state} dealer`);
        assert.equal($('form.checkout').triggerHandler('checkout_place_order'), true);
        assert.equal($('.automaticffl-checkout-error').length, 0, 'dealer selection clears the dealer error');
        if (!billingOnly) assert.equal($('#shipping_address_1_field').css('display'), 'none');
      }

      for (const [index, territory] of ['AS', 'GU', 'MP', 'PR', 'VI'].entries()) {
        $('#shipping_country').val(territory);
        $('#shipping_state').val('').trigger('change');
        assert.equal($('#ffl_license_field').val(), '', `${territory} country change clears the previous dealer`);
        assert.equal($('form.checkout').triggerHandler('checkout_place_order'), false, `${territory} requires a dealer when enabled`);

        w.dispatchEvent(new w.MessageEvent('message', {
          origin: 'http://localhost:9090',
          data: { type: 'dealerUpdate', value: { id: 400 + index, fflID: `${territory}-license`, company: `${territory} dealer`, address1: 'Island dealer street', city: 'Island dealer city', countryCode: 'US', stateOrProvinceCode: territory, postalCode: '12345', phone: '5555550100' } },
        }));
        $(w.document.body).trigger('updated_checkout').trigger('updated_checkout');
        assert.equal($('#shipping_country').val(), territory, `${territory} uses WooCommerce's native country`);
        assert.equal($('#shipping_state').val(), '', `${territory} does not persist as a WooCommerce state`);
        assert.equal($('#ffl_license_field').val(), `${territory}-license`);
        assert.equal($('#billing_first_name').val(), 'Alex', 'Dealer selection preserves billing');
        assert.equal($('form.checkout').triggerHandler('checkout_place_order'), true);
        assert.equal($('.automaticffl-checkout-error').length, 0, `${territory} dealer selection clears the dealer error`);
      }

      $('#shipping_country').val('CA');
      $('#shipping_state').val('PR').trigger('change');
      assert.equal($('#ffl_license_field').val(), '', 'Foreign country change clears the previous dealer');
      assert.equal($('form.checkout').triggerHandler('checkout_place_order'), true, 'Foreign province collision does not activate Puerto Rico');
      assert.equal($('.automaticffl-checkout-error').length, 0, 'unrestricted destination clears the dealer error');
      console.log(`PASS Classic ${billingOnly ? 'billing-only' : 'normal'}: restricted ammo retains unrestricted-state dealers across selection, replacement and checkout updates`);
    } finally {
      dom.window.close();
    }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
