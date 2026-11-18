const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('modules/tumtook-gallery-code/assets/gallery-code-admin.js', 'utf8');
const flush = async () => { for (let n = 0; n < 8; n++) await new Promise(setImmediate); };
function element() { return { children: [], events: {}, style: {}, textContent: '', addEventListener(name, fn) { this.events[name] = fn; }, appendChild(child) { this.children.push(child); } }; }
async function test(response, expected, fresh = false) {
    const button = element(), status = element(), details = element(), images = element(), checkbox = { checked: fresh };
    const root = { dataset: { postId: '99', nonce: 'post-bound-nonce', url: 'https://site.test/wp-admin/admin-ajax.php' }, querySelector: selector => ({ '[data-ttgc-check]': button, '[data-ttgc-status]': status, '[data-ttgc-details]': details, '[data-ttgc-images]': images, '[data-ttgc-fresh]': checkbox }[selector]) };
    const calls = [], timers = new Map();
    const document = { readyState: 'complete', querySelectorAll: () => [root], createElement: element };
    const window = { setTimeout(fn) { timers.set(1, fn); return 1; }, clearTimeout(id) { timers.delete(id); } };
    const fetch = (url, options) => { calls.push({ url, options }); return Promise.resolve({ json: async () => response }); };
    vm.runInNewContext(source, { document, window, fetch, URLSearchParams, AbortController });
    assert.equal(calls.length, 0, 'Opening the edit screen cannot trigger an API check');
    button.events.click(); button.events.click();
    assert.equal(calls.length, 1, 'Double clicks cannot create duplicate diagnostic requests');
    assert.equal(calls[0].options.body.get('post_id'), '99');
    assert.equal(calls[0].options.body.get('nonce'), 'post-bound-nonce');
    assert.equal(calls[0].options.body.get('fresh'), fresh ? '1' : '0');
    assert.equal(calls[0].options.body.has('code'), false, 'Diagnostic code must come from saved server-side post data');
    await flush();
    assert(status.textContent.includes(expected));
    assert.equal(button.disabled, false);
    assert.equal(timers.size, 0);
    if (response.success) {
        assert.equal(details.children.length, 14, 'Diagnostic facts are rendered as text');
        assert.equal(images.children.length, response.data.preview.length);
        if (images.children[0]) { images.children[0].events.error(); assert.equal(images.children[0].hidden, true); }
    }
    console.log('PASS Admin diagnostics: explicit action, saved post, bounded request and expected status');
}
const data = { post_id: 99, code: 'KH', http_status: 200, source: 'cached', matched_records: 1, api_image_count: 60, display_image_count: 50, preview: [{ image: 'https://images.test/kh.webp', alt: 'KH' }] };
async function settingsTest() {
    const button = element(), status = element(), details = element(), codes = element(), images = element();
    const api = { value: 'https://unsaved.test/gallery?x-api-key=test-only' }, code = { value: 'KH' };
    const root = { dataset: { nonce: 'settings-nonce', url: '/wp-admin/admin-ajax.php' }, querySelector: selector => ({ '[data-ttgc-test-api]': button, '[data-ttgc-api-status]': status, '[data-ttgc-api-details]': details, '[data-ttgc-api-codes]': codes, '[data-ttgc-api-images]': images, '#ttgc-api-url': api, '#ttgc-test-code': code }[selector]) };
    const calls = [], timers = new Map();
    const document = { readyState: 'complete', querySelectorAll: () => [root], createElement: element };
    const window = { setTimeout(fn) { timers.set(1, fn); return 1; }, clearTimeout(id) { timers.delete(id); } };
    const response = { success: true, data: { http_status: 200, record_count: 2, code_count: 2, image_count: 3, codes: [{ code: 'KH', records: 1, images: 1 }], requested_code: 'KH', matched_records: 1, display_image_count: 1, preview: data.preview } };
    const fetch = (url, options) => { calls.push({ url, options }); return Promise.resolve({ json: async () => response }); };
    vm.runInNewContext(fs.readFileSync('modules/tumtook-gallery-code/assets/gallery-code-settings.js', 'utf8'), { document, window, fetch, URLSearchParams, AbortController });
    assert.equal(calls.length, 0, 'Settings page must not test automatically');
    button.events.click(); button.events.click(); await flush();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].options.method, 'POST', 'URL credentials are not added to browser query strings');
    assert.equal(calls[0].options.body.get('api_url'), api.value, 'Test uses current unsaved URL input');
    assert.equal(calls[0].options.body.get('code'), 'KH');
    assert.equal(calls[0].options.body.get('action'), 'ttgc_test_api');
    assert(status.textContent.includes('รูปพร้อมแสดง'));
    assert.equal(images.children.length, 1);
    assert.equal(button.disabled, false);
    assert.equal(timers.size, 0);
    api.value = ''; button.events.click(); await flush();
    assert.equal(calls.length, 1, 'Empty URL cannot trigger a test request');
    assert(status.textContent.includes('กรุณากรอก API URL'));
    console.log('PASS Settings URL test: unsaved input, optional code, explicit POST request and results');
}
(async () => {
    await test({ success: true, data }, 'มีรูปพร้อมแสดง');
    await test({ success: true, data: { ...data, matched_records: 0, preview: [] } }, 'ไม่พบ code', true);
    await test({ success: false, data: { message: 'ยังไม่ได้ตั้งค่า API' } }, 'ยังไม่ได้ตั้งค่า API');
    await settingsTest();
})().catch(error => { console.error(error); process.exitCode = 1; });
