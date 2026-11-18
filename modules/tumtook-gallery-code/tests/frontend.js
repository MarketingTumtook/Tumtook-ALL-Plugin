const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('modules/tumtook-gallery-code/assets/gallery-code.js', 'utf8');
const flush = async () => { for (let n = 0; n < 8; n++) await new Promise(setImmediate); };
function element(tag) {
    return {
        tag, dataset: {}, children: [], style: {}, attrs: {}, events: {},
        appendChild(node) { this.children.push(...(node.tag === 'fragment' ? node.children : [node])); },
        addEventListener(name, callback) { this.events[name] = callback; },
        setAttribute(name, value) { this.attrs[name] = value; if (name === 'open') this.open = true; },
        removeAttribute(name) { delete this.attrs[name]; if (name === 'open') this.open = false; if (name === 'src') delete this.src; }
    };
}
async function test(fail, nativeDialog = true) {
    const observers = [], timers = new Map(), roots = [], calls = [];
    let timerId = 0;
    for (let i = 0; i < 2; i++) {
        const root = element('div'), grid = element('div');
        root.dataset = { ttgcCode: 'VN', ttgcSignature: 'signed-code', ttgcUrl: '/wp-json/tumtook-gallery-code/v1/items' };
        root.querySelector = selector => selector === '.ttgc-items' ? grid : null;
        root.grid = grid;
        roots.push(root);
    }
    class Observer {
        constructor(callback) { this.callback = callback; observers.push(this); }
        observe(root) { this.root = root; }
        disconnect() { this.disconnected = true; }
        enter() { this.callback([{ target: this.root, isIntersecting: true }]); }
    }
    const document = { readyState: 'complete', querySelectorAll: () => roots, createElement: element, createDocumentFragment: () => element('fragment') };
    document.body = element('body');
    document.body.style.overflow = 'auto';
    document.createElement = function (tag) {
        const node = element(tag);
        node.isConnected = true;
        node.focus = () => { document.activeElement = node; };
        if (tag === 'dialog' && nativeDialog) {
            node.showModal = () => { node.open = true; };
            node.close = () => { node.open = false; if (node.events.close) node.events.close(); };
        }
        return node;
    };
    const window = {
        IntersectionObserver: Observer, location: { origin: 'https://site.test' },
        setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
        clearTimeout(id) { timers.delete(id); }
    };
    const fetch = (url, options) => {
        calls.push({ url, options });
        if (fail) return Promise.reject(new Error('API unavailable'));
        return Promise.resolve({ ok: true, json: async () => ({ items: [
            { image: 'https://images.test/portrait.webp', alt: 'Portrait', width: 900, height: 1600 },
            { image: 'javascript:alert(1)' },
            { image: 'https://images.test/square.webp', alt: 'Square', width: 1000, height: 1000 }
        ] }) });
    };
    vm.runInNewContext(source, { document, window, fetch, URL, Map, AbortController, IntersectionObserver: Observer });
    assert.equal(calls.length, 0, 'Offscreen shortcode must not request API data');
    assert.equal(document.body.children.length, 0, 'Lightbox DOM is created only when a picture is opened');
    observers.forEach(observer => observer.enter());
    await flush();
    assert.equal(calls.length, 1, 'Identical shortcode instances share one in-flight request');
    assert.equal(new URL(calls[0].url).searchParams.get('code'), 'VN');
    assert.equal(new URL(calls[0].url).searchParams.has('endpoint'), false, 'API URL is never supplied by the frontend');
    assert.equal(timers.size, 0, 'Settled requests clear abort timers');
    roots.forEach(root => {
        assert.equal(root.attrs['aria-busy'], 'false');
        assert.equal(root.dataset.ttgcState, fail ? 'error' : 'ready');
        assert.equal(root.grid.children.length, fail ? 0 : 2, 'Unsafe image URLs are rejected');
        if (!fail) {
            const image = root.grid.children[0].children[0].children[0];
            assert.equal(image.width, 900);
            assert.equal(image.height, 1600);
            assert.equal(image.style.aspectRatio, 'auto 900 / 1600');
            assert.equal(image.alt, 'Portrait');
            const trigger = root.grid.children[0].children[0];
            assert.equal(trigger.tag, 'button', 'Thumbnails cannot be hijacked by a theme link lightbox');
            trigger.events.click();
            const dialog = document.body.children[0];
            const [close, prev, stage, next] = dialog.children;
            const [fullImage, error, counter] = stage.children;
            assert.equal(dialog.open, true);
            assert.equal(document.body.style.overflow, 'hidden');
            assert.equal(counter.textContent, '1 / 2', 'Each shortcode has its own image group');
            assert.equal(prev.disabled, true);
            assert.equal(next.disabled, false);
            next.events.click();
            assert.equal(fullImage.src, 'https://images.test/square.webp');
            assert.equal(counter.textContent, '2 / 2');
            assert.equal(next.disabled, true);
            dialog.events.keydown({ key: 'ArrowLeft', preventDefault() {} });
            assert.equal(counter.textContent, '1 / 2');
            fullImage.events.error();
            assert.equal(error.hidden, false, 'Full-image failures leave navigation usable');
            next.events.click();
            assert.equal(error.hidden, true);
            dialog.events.keydown({ key: 'Escape', preventDefault() {} });
            assert.equal(dialog.open, false);
            assert.equal(document.body.style.overflow, 'auto', 'Closing restores the previous scroll state');
            assert.equal(document.activeElement, trigger, 'Closing restores thumbnail focus');
            assert.equal(document.body.children.length, 1, 'Different shortcode instances reuse one lightbox without merging their items');
            assert.equal(calls.length, 1, 'Lightbox navigation cannot refetch the API');
            image.events.error();
            assert.equal(root.grid.children[0].hidden, true, 'Broken images are hidden without public controls');
        }
    });
    observers.forEach(observer => observer.enter());
    await flush();
    assert.equal(calls.length, 1, 'Repeated scrolling never retries or duplicates a request');
    console.log(`PASS Gallery Code ${fail ? 'failure' : 'success'}: deferred load, instance isolation, shared request and no retry loop`);
}
(async () => { await test(false); await test(false, false); await test(true); })().catch(error => { console.error(error); process.exitCode = 1; });
