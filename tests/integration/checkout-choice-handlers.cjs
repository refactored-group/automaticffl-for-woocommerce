/** Run against a local WordPress jQuery asset; no network or real cart mutations. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const jqueryPath = process.argv[2];
if (!jqueryPath) throw new Error('Pass the path to wp-includes/js/jquery/jquery.js');
(async () => {
  for (const [checkoutGroup, savedGroup] of [['ffl', 'regular'], ['regular', 'ffl']]) {
    const dom = new JSDOM('<div class="automaticffl-mixed-actions"><button class="automaticffl-save-btn" data-checkout-type="ffl">Check out FFL items (2)</button><button class="automaticffl-save-btn" data-checkout-type="regular">Check out regular item (1)</button></div>', { url: 'http://localhost/cart/', runScripts: 'outside-only' });
    const w = dom.window;
    const destinations = [];
    delete w.location;
    w.location = { assign: url => destinations.push(url) };
    w.automaticfflSaveForLater = { ajaxUrl: '/admin-ajax.php', nonce: 'fixture', checkoutUrl: '/checkout/', i18n: { saving: 'Continuing...', error: 'Please retry.' } };
    w.eval(fs.readFileSync(jqueryPath, 'utf8'));
    const $ = w.jQuery;
    const requests = [];
    $.ajax = options => requests.push(options);
    w.eval(fs.readFileSync('assets/js/save-for-later.js', 'utf8'));
    await new Promise(resolve => $(resolve));
    $(w.document.body).trigger('updated_cart_totals').trigger('updated_cart_totals');
    const button = $(`[data-checkout-type="${checkoutGroup}"]`);
    const label = button.text();
    button.trigger('click').trigger('click');
    assert.equal(requests.length, 1, 'duplicate clicks/initialization must not submit twice');
    assert.equal(requests[0].data.item_type, savedGroup, 'save the opposite group');
    assert.equal($('.automaticffl-save-btn:disabled').length, 2, 'disable both choices');
    assert.deepEqual(destinations, [], 'wait for save completion before navigating');
    requests[0].success({ success: false, data: { message: 'Retry fixture' } });
    assert.equal($('.automaticffl-save-btn:disabled').length, 0);
    assert.equal(button.text(), label);
    assert.equal($('#automaticffl-save-error').text(), 'Retry fixture');
    assert.deepEqual(destinations, []);
    button.trigger('click');
    requests[1].error();
    assert.equal($('.automaticffl-save-btn:disabled').length, 0);
    button.trigger('click');
    requests[2].success({ success: true });
    assert.deepEqual(destinations, ['/checkout/']);
    dom.window.close();
    console.log(`PASS Classic checkout ${checkoutGroup}: saves ${savedGroup}, pending/duplicate guards, retries, then checkout`);
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
