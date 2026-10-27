const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const flush = async () => { for (let i = 0; i < 5; i++) await new Promise(setImmediate); };
function element() {
  const attrs = {}, handlers = {}, classes = new Set();
  return {
    dataset: {}, children: [], hidden: false, style: { setProperty() {}, removeProperty() {} },
    classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x) },
    setAttribute(k, v) { attrs[k] = v; }, getAttribute(k) { return attrs[k] || ''; }, removeAttribute(k) { delete attrs[k]; },
    addEventListener(k, fn) { handlers[k] = fn; }, handlers,
    appendChild(x) { this.children.push(x); }, insertBefore(x) { this.children.push(x); },
    querySelector() { return null; }, querySelectorAll() { return []; },
    getBoundingClientRect() { return { top: 5000, bottom: 5100, left: 0, width: 100, height: 100 }; }
  };
}
function environment(roots, state = 'complete') {
  const observers = [], timers = new Map(), frames = [], listeners = {};
  let sequence = 0, queries = 0;
  class Observer {
    constructor(callback) { this.callback = callback; this.targets = []; observers.push(this); }
    observe(target) { this.targets.push(target); }
    unobserve(target) { this.targets = this.targets.filter(x => x !== target); }
    disconnect() { this.targets = []; }
    enter(target) { this.callback([{ target, isIntersecting: true }]); }
  }
  const document = {
    readyState: state, body: element(), documentElement: element(),
    querySelectorAll() { queries++; return roots; },
    createElement: element, createDocumentFragment: element,
    addEventListener(name, fn) { listeners[name] = fn; }
  };
  const window = {
    innerWidth: 1280, innerHeight: 900, location: { origin: 'https://example.test' },
    IntersectionObserver: Observer, addEventListener() {},
    setTimeout(fn) { timers.set(++sequence, fn); return sequence; }, clearTimeout(id) { timers.delete(id); },
    requestAnimationFrame(fn) { frames.push(fn); return frames.length; }, cancelAnimationFrame() {}
  };
  return { context: { document, window, IntersectionObserver: Observer, AbortController, URL, console, Date, Promise }, observers, timers, frames, listeners, queryCount: () => queries };
}
const source = module => fs.readFileSync(`modules/${module}/assets/js/${module === 'tumtook-gallery' ? 'tumtook-gallery.js' : 'front.js'}`, 'utf8');
async function galleryTest() {
  const shell = element(), gallery = element(), loader = element(), sentinel = element();
  shell.dataset = { pageId: '42', columns: '6', limit: '50', endpoint: 'https://example.test/api' };
  shell.querySelector = selector => ({ '.ttg-gallery': gallery, '.ttg-loader': loader, '.ttg-sentinel': sentinel }[selector] || null);
  const env = environment([shell]);
  const urls = [];
  let mode = 'error';
  env.context.TumtookGalleryData = { restUrl: 'https://example.test/wp-json/gallery', strings: { loading: 'loading', empty: 'empty', error: 'error' } };
  env.context.fetch = (url, options) => {
    urls.push(String(url));
    if (mode === 'error') return Promise.reject(new Error('Simulated transport failure'));
    if (mode === 'timeout') return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('Aborted'))));
    return Promise.resolve({ ok: true, json: async () => ({ items: [], has_more: false }) });
  };
  vm.runInNewContext(source('tumtook-gallery'), env.context);
  assert.equal(urls.length, 0, 'Below-fold Gallery must not start a network request');
  env.observers[0].enter(shell);
  await flush();
  assert.equal(urls.length, 1);
  const retry = shell.children.find(x => x.className === 'ttg-retry');
  assert.equal(retry.hidden, false);
  for (let i = 0; i < 8; i++) {
    const next = env.frames.shift(); if (next) next();
    env.observers[1].enter(sentinel);
    await flush();
  }
  assert.equal(urls.length, 1, 'Failures must not loop or restart on scrolling');
  assert.equal(env.timers.size, 0, 'Failed fetch must clear its timeout');
  env.context.window.innerWidth = 375;
  mode = 'timeout';
  retry.handlers.click();
  assert.equal(urls.length, 2, 'Manual retry permits one additional request');
  assert.equal(new URL(urls[0]).searchParams.get('per_page'), new URL(urls[1]).searchParams.get('per_page'), 'Breakpoint changes must not change pagination size');
  Array.from(env.timers.values()).forEach(fn => fn());
  await flush();
  assert.equal(retry.hidden, false, 'Timeout restores retry control');
  assert.equal(urls.length, 2, 'Timeout must not create an automatic retry loop');
  mode = 'success';
  retry.handlers.click(); await flush();
  assert.equal(urls.length, 3);
  assert.equal(retry.hidden, true, 'Successful manual retry clears the error control');
  assert.equal(env.timers.size, 0);
  console.log('PASS Gallery: delayed initialization, near-viewport requests, stop on error, manual retry, timeout, fixed pagination size, recovery');
}
async function recommendationsTest(module, prefix) {
  for (const state of ['loading', 'complete']) {
    const root = element(), track = element();
    root.querySelector = selector => selector === `[data-${prefix}-track]` ? track : null;
    root.getAttribute = key => ({ [`data-${prefix}-ajax-url`]: 'https://example.test/admin-ajax.php', [`data-${prefix}-dynamic`]: '1' }[key] || '');
    const env = environment([root], state);
    let calls = 0;
    env.context.fetch = (url, options) => { calls++; return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('Aborted')))); };
    vm.runInNewContext(source(module), env.context);
    if (state === 'loading') { assert.equal(env.queryCount(), 0); env.listeners.DOMContentLoaded(); }
    assert(env.queryCount() > 0, 'Initialization must run even after DOMContentLoaded');
    assert.equal(calls, 0, 'Below-fold recommendations must not fetch immediately');
    env.observers[0].enter(root);
    env.observers[0].enter(root);
    assert.equal(calls, 1, 'Repeated intersection events must not duplicate fetch');
    Array.from(env.timers.values()).forEach(fn => fn());
    await flush();
    assert.equal(root[`__${prefix}Initialized`], true, 'Timeout initializes existing server-rendered cards');
    assert.equal(env.timers.size, 0);
    console.log(`PASS ${module}: ${state}, viewport gating, one refresh, timeout with fallback`);
  }
}
(async () => {
  await galleryTest();
  for (const [module, prefix] of [['tumtook-page-article-recommendations','ttar'],['tumtook-page-product-recommendations','ttpr'],['tumtook-job-working-cards','ttwc']]) await recommendationsTest(module, prefix);
  for (const state of ['loading','complete']) {
    const env = environment([], state);
    vm.runInNewContext(source('tumtook-page-product-cards'), env.context);
    if (state === 'loading') env.listeners.DOMContentLoaded();
    assert.equal(env.queryCount(), 1);
  }
  console.log('PASS Product Cards: initialization before and after DOMContentLoaded');
})().catch(error => { console.error(error); process.exitCode = 1; });
