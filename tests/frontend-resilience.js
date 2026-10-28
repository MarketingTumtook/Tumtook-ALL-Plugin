const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const flush = async () => { for (let i = 0; i < 5; i++) await new Promise(setImmediate); };
function element() {
  const attrs = {}, handlers = {}, classes = new Set();
  return {
    dataset: {}, children: [], hidden: false, style: { setProperty(k, v) { this[k] = v; }, removeProperty(k) { delete this[k]; } },
    classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x) },
    setAttribute(k, v) { attrs[k] = v; }, getAttribute(k) { return attrs[k] || ''; }, removeAttribute(k) { delete attrs[k]; },
    addEventListener(k, fn) { handlers[k] = fn; }, handlers,
    appendChild(x) { this.children.push(x); }, insertBefore(x) { this.children.push(x); },
    querySelector() { return null; }, querySelectorAll() { return []; },
    getClientRects() { return [this.getBoundingClientRect()]; },
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
async function galleryTest(mode, layout = 'natural', home = false, width = 0, requested = '0') {
  const shell = element(), gallery = element(), loader = element(), sentinel = element();
  shell.dataset = { pageId: '42', columns: '6', limit: '50', endpoint: 'https://example.test/api' };
  shell.querySelector = selector => ({ '.ttg-gallery': gallery, '.ttg-loader': loader, '.ttg-sentinel': sentinel }[selector] || null);
  const env = environment([shell]);
  if (width) {
    shell.dataset.columns = requested;
    gallery.clientWidth = width;
    env.context.window.getComputedStyle = () => ({ direction: 'ltr' });
  }
  if (layout) shell.dataset.layout = layout;
  if (home) env.context.document.body.classList.add('home');
  const urls = [];
  env.context.window.TumtookGalleryData = { restUrl: 'https://example.test/wp-json/gallery', strings: { loading: 'loading', empty: 'empty', error: 'error' } };
  env.context.fetch = (url, options) => {
    urls.push(String(url));
    if (mode === 'error') return Promise.reject(new Error('Simulated transport failure'));
    if (mode === 'timeout') return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('Aborted'))));
    return Promise.resolve({ ok: true, json: async () => ({ items: urls.length === 1 ? [{ image: '/one.webp', width: 300, height: 600 }] : [], has_more: urls.length === 1 }) });
  };
  vm.runInNewContext(source('tumtook-gallery'), env.context);
  assert.equal(urls.length, 0, 'Below-fold Gallery must not start a network request');
  assert.equal(shell.children.length, 0, 'Initialization must not insert retry buttons');
  env.observers.find(observer => observer.targets.includes(shell)).enter(shell);
  if (mode === 'timeout') Array.from(env.timers.values()).forEach(fn => fn());
  await flush();
  assert.equal(urls.length, 1);
  if (mode !== 'success') {
    for (let i = 0; i < 8; i++) {
      const next = env.frames.shift(); if (next) next();
      sentinel.getBoundingClientRect = () => ({ top: 500, bottom: 600 });
      env.observers.find(observer => observer.targets.includes(sentinel)).enter(sentinel);
      await flush();
    }
    assert.equal(urls.length, 1, 'Failures must not loop or restart on scrolling');
    assert.equal(shell.children.length, 0, 'Failures must not insert public controls');
  } else {
    const mixed = layout === 'mixed' || (!layout && home);
    const media = gallery.children[0].children[0].children[0];
    const image = media.children[0];
    assert.equal(media.style['--ttg-ratio'], mixed ? '1 / 1' : '300 / 600', 'Only Home uses mixed frames; ordinary galleries reserve the API image ratio');
    image.naturalWidth = 400;
    image.naturalHeight = 1000;
    image.handlers.load();
    assert.equal(media.style['--ttg-ratio'], mixed ? '1 / 1' : '400 / 1000', 'Natural galleries correct dimensions after image load while Home keeps its frame');
    if (!mixed) assert.equal(env.frames.length, 1, 'Image dimension updates share one scheduled masonry layout');
    if (width) {
      env.frames.shift()();
      const expected = width <= 767 ? 2 : width <= 1024 ? 3 : 6;
      assert.equal(Number(shell.dataset.activeColumns), expected, 'All galleries share responsive 6/3/2 columns, ignoring old overrides');
    }
    env.context.window.innerWidth = 375;
    sentinel.getBoundingClientRect = () => ({ top: 500, bottom: 600 });
      env.observers.find(observer => observer.targets.includes(sentinel)).enter(sentinel);
    await flush();
    assert.equal(urls.length, 2);
    assert.equal(new URL(urls[0]).searchParams.get('per_page'), new URL(urls[1]).searchParams.get('per_page'), 'Breakpoint changes must not change pagination size');
    assert.equal(shell.children.length, 0, 'Successful pagination must not insert retry buttons');
  }
  assert.equal(env.timers.size, 0, 'Settled requests must clear their timeout');
  console.log(`PASS Gallery: ${mode}, no public retry controls, bounded requests, fixed pagination size`);
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
  for (const mode of ['error', 'timeout', 'success']) await galleryTest(mode);
  await galleryTest('success', 'mixed');
  await galleryTest('success', '', true);
  await galleryTest('success', '', false);
  for (const layout of ['natural', 'mixed']) {
    for (const width of [1440, 1140, 1024, 768, 767, 375]) await galleryTest('success', layout, false, width);
  }
  await galleryTest('success', 'natural', false, 1440, '5');
  for (const [module, prefix] of [['tumtook-page-article-recommendations','ttar'],['tumtook-page-product-recommendations','ttpr'],['tumtook-job-working-cards','ttwc']]) await recommendationsTest(module, prefix);
  for (const state of ['loading','complete']) {
    const env = environment([], state);
    vm.runInNewContext(source('tumtook-page-product-cards'), env.context);
    if (state === 'loading') env.listeners.DOMContentLoaded();
    assert.equal(env.queryCount(), 1);
  }
  console.log('PASS Product Cards: initialization before and after DOMContentLoaded');
})().catch(error => { console.error(error); process.exitCode = 1; });
