const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

async function run(module, prefix, readyState) {
  let timers = [], retries = 0, hook, domReady;
  const classes = new Set();
  const attrs = { src: '/original.webp', srcset: '/small.webp 300w', sizes: 'auto' };
  const handlers = {};
  const image = {
    complete: true, naturalWidth: 0,
    parentNode: { classList: { add: x => classes.add(x), remove: x => classes.delete(x) } },
    style: { display: '', removeProperty() { this.display = ''; } },
    addEventListener: (name, fn) => { handlers[name] = fn; },
    getAttribute: name => attrs[name] || null,
    removeAttribute: name => { delete attrs[name]; },
    setAttribute: (name, value) => { attrs[name] = value; if (name === 'src') retries++; }
  };
  const root = {
    querySelector: () => null,
    querySelectorAll: selector => selector === `img.${prefix}-image` ? [image] : [],
    getAttribute: () => '',
    matches: () => true
  };
  const document = {
    readyState,
    querySelectorAll: () => [root],
    documentElement: { classList: { add() {} } },
    body: { classList: { add() {} } },
    addEventListener: (name, fn) => { if (name === 'DOMContentLoaded') domReady = fn; }
  };
  const window = {
    setTimeout: fn => { timers.push(fn); return timers.length; },
    clearTimeout: id => { timers[id - 1] = null; },
    elementorFrontend: { hooks: { addAction: (name, fn) => { hook = fn; } } }
  };
  vm.runInNewContext(fs.readFileSync(`modules/${module}/assets/js/front.js`, 'utf8'), { document, window, console, URL });
  if (readyState === 'loading') {
    assert.equal(timers.length, 0, 'Wait for DOM before initialization');
    domReady();
  }
  await Promise.resolve();
  await Promise.resolve();
  assert.equal(timers.length, 1, 'Detect image failure before delayed script initialization');
  hook([root]);
  assert.equal(timers.length, 1, 'Elementor hooks do not duplicate image listeners or retries');
  timers[0]();
  assert.equal(retries, 1);
  assert.equal(attrs.src, '/original.webp');
  assert.equal(attrs.srcset, undefined, 'Retry original instead of broken responsive variant');
  assert.equal(attrs.sizes, undefined);
  assert.equal(image.style.display, '', 'Keep image visible during retry');
  handlers.error();
  timers[1]();
  handlers.error();
  assert.equal(retries, 2, 'Bound retries to two requests');
  assert.equal(image.style.display, 'none');
  assert(classes.has(`${prefix}-image-link--missing`));
  handlers.load();
  assert.equal(image.style.display, '', 'Successful load restores image');
  assert(!classes.has(`${prefix}-image-link--missing`));
  console.log(`PASS ${module}: ${readyState}, early failure, original fallback, bounded retries, recovery, Elementor hook`);
}
(async () => {
  for (const [module, prefix] of [
    ['tumtook-home-product-recommendations', 'tthpr'],
    ['tumtook-page-article-recommendations', 'ttar']
  ]) {
    for (const readyState of ['loading', 'complete']) await run(module, prefix, readyState);
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
