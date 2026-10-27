const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = {}, classes = new Set(['tthpr-image-link--missing']);
let requests = 0;
const attrs = { src: '/original.webp', srcset: '/variant.webp 300w', sizes: 'auto' };
const holder = {
  children: [], classList: { remove: x => classes.delete(x) },
  querySelector: () => null, appendChild(button) { this.children.push(button); }
};
const image = {
  complete: true, naturalWidth: 0, currentSrc: '/variant.webp', loading: 'lazy', parentNode: holder,
  hidden: true, style: { display: 'none', removeProperty() { this.display = ''; } },
  matches: () => true, getAttribute: name => attrs[name],
  removeAttribute(name) { delete attrs[name]; },
  setAttribute(name, value) { attrs[name] = value; if (name === 'src') requests++; }
};
const document = {
  readyState: 'complete', querySelectorAll: () => [image],
  addEventListener(name, fn) { events[name] = fn; },
  createElement() { return { style: {}, handlers: {}, addEventListener(name, fn) { this.handlers[name] = fn; } }; }
};
vm.runInNewContext(fs.readFileSync('assets/js/image-recovery.js', 'utf8'), { document });
assert.equal(requests, 0, 'Detecting a broken image must not request any files');
assert.equal(holder.children.length, 1);
events.error({ target: image });
assert.equal(holder.children.length, 1, 'Repeated errors must not duplicate controls');
const button = holder.children[0];
button.handlers.click({ preventDefault() {}, stopPropagation() {} });
assert.equal(requests, 1, 'Only a user click retries an image');
assert.equal(image.loading, 'lazy', 'Manual retry must preserve native lazy loading');
assert.equal(attrs.src, '/original.webp');
assert.equal(attrs.srcset, undefined, 'Manual retry can recover from a broken responsive variant');
events.error({ target: image });
assert.equal(button.disabled, false, 'A failed manual retry allows another user attempt');
assert.equal(requests, 1, 'Failed manual retry must not schedule an automatic retry');
events.load({ target: image });
assert.equal(image.hidden, false);
assert.equal(image.style.display, '');
assert.equal(classes.size, 0);
assert.equal(button.hidden, true);
console.log('PASS Image recovery: zero automatic requests, single manual retry control, original fallback, preserved lazy loading, recovery after success');
