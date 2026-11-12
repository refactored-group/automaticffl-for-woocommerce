/** Exercise Classic dealer submission across modular and actual checkout forms. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');

const jqueryPath = process.argv[2];
if (!jqueryPath) throw new Error('Pass the path to wp-includes/js/jquery/jquery.js');

const scriptMarkup = execFileSync('php', ['-r', `
define('ABSPATH', __DIR__);
function wp_json_encode($value) { return json_encode($value); }
$allowed_origins = array('http://localhost:9090');
include 'templates/checkout/ffl-map-js.php';
`], { encoding: 'utf8' });
const script = [...scriptMarkup.matchAll(/<script>([\s\S]*?)<\/script>/g)][0][1];

function fieldSet(prefix) {
  return ['dealer_id', 'license_field', 'expiration_date', 'uuid', 'company_name']
    .map(field => `<input id="${prefix}ffl_${field}" name="ffl_${field}" value="">`).join('');
}

function makeDom() {
  return new JSDOM(`<form id="builder-billing">${fieldSet('')}</form>
    <form class="checkout" id="real-checkout">
      <input name="woocommerce-process-checkout-nonce" value="nonce">
      <input id="billing_first_name" name="billing_first_name" value="Alex">
      <input id="billing_last_name" name="billing_last_name" value="Shopper">
      <input id="payment_method_card" name="payment_method" value="card" type="radio">
      <input id="payment_method_credova" name="payment_method" value="credova" type="radio">
    </form>
    <form id="builder-payment">${fieldSet('payment-')}</form>
    <button id="automaticffl-select-dealer" type="button">Find a Dealer</button>
    <div id="automaticffl-dealer-selected"></div>
    <div id="automaticffl-dealer-card-template"><p class="customer-name"></p><p class="dealer-name"></p><p class="dealer-address"></p><a><span class="dealer-phone-formatted"></span></a></div>
    <div class="automaticffl-dealer-layer"></div>`, {
    url: 'http://localhost/checkout/', runScripts: 'outside-only',
  });
}

function dealer(id, license, company, street, state, uuid = '') {
  return { id, fflID: license, company, address1: street, address2: '', city: 'Dealer City',
    stateOrProvinceCode: state, countryCode: 'US', postalCode: '84057', phone: '8015550100',
    expirationDate: '', uuid };
}

function assertCanonical($, expected) {
  for (const [name, value] of Object.entries(expected)) {
    const enabled = $(`#real-checkout [name="${name}"]`).filter((_, field) => !field.disabled);
    assert.equal(enabled.length, 1, `${name} has one submitted value`);
    assert.equal(enabled.val(), String(value), `${name} has current value`);
  }
}

(async () => {
  const dom = makeDom();
  try {
    const w = dom.window;
    w.eval(fs.readFileSync(jqueryPath, 'utf8'));
    const $ = w.jQuery;
    w.eval(script);
    await new Promise(resolve => $(resolve));

    const first = dealer(42, 'UT-first', 'First Dealer', '42 First Street', 'UT', 'old-certificate');
    w.dispatchEvent(new w.MessageEvent('message', { origin: 'http://localhost:9090', data: { type: 'dealerUpdate', value: first } }));
    assertCanonical($, {
      ffl_dealer_id: 42, ffl_license_field: 'UT-first', ffl_company_name: 'First Dealer',
      ffl_uuid: 'old-certificate', shipping_address_1: '42 First Street', shipping_state: 'UT',
    });

    const replacement = dealer(84, 'CO-second', 'Second Dealer', '84 Second Street', 'CO');
    w.dispatchEvent(new w.MessageEvent('message', { origin: 'http://localhost:9090', data: { type: 'dealerUpdate', value: replacement } }));
    assertCanonical($, {
      ffl_dealer_id: 84, ffl_license_field: 'CO-second', ffl_company_name: 'Second Dealer',
      ffl_uuid: '', shipping_address_1: '84 Second Street', shipping_state: 'CO',
    });

    $('#payment_method_card').prop('checked', true).trigger('change');
    $('#payment_method_credova').prop('checked', true).trigger('change');
    assertCanonical($, { ffl_license_field: 'CO-second', shipping_address_1: '84 Second Street' });

    $('#real-checkout [name^="ffl_"], #real-checkout [name^="shipping_"]').remove();
    $(w.document.body).trigger('updated_checkout');
    assertCanonical($, {
      ffl_dealer_id: 84, ffl_license_field: 'CO-second', ffl_company_name: 'Second Dealer',
      ffl_uuid: '', shipping_address_1: '84 Second Street', shipping_state: 'CO',
    });
    console.log('PASS Classic modular forms: selection, replacement, payment switching, and checkout refresh submit one current dealer');
  } finally {
    dom.window.close();
  }

  const fresh = makeDom();
  try {
    fresh.window.eval(fs.readFileSync(jqueryPath, 'utf8'));
    fresh.window.eval(script);
    await new Promise(resolve => fresh.window.jQuery(resolve));
    const $ = fresh.window.jQuery;
    assert.equal($('#real-checkout [name="ffl_license_field"]').val(), '', 'fresh navigation does not restore a dealer');
    console.log('PASS Classic fresh navigation requires dealer reselection');
  } finally {
    fresh.window.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
