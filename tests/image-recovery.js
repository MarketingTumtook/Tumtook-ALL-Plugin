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
  createElement() { throw new Error('Compatibility script must not create frontend controls'); }
};
vm.runInNewContext(fs.readFileSync('assets/js/image-recovery.js', 'utf8'), { document });
assert.equal(requests, 0, 'Compatibility script must not request any files');
assert.equal(holder.children.length, 0, 'No retry controls may be created');
assert.equal(events.error, undefined, 'Image errors do not create public controls');
events.load({ target: image });
assert.equal(image.hidden, false);
assert.equal(image.style.display, '');
assert.equal(classes.size, 0);
assert.equal(requests, 0);
assert.equal(image.loading, 'lazy');
assert.equal(attrs.srcset, '/variant.webp 300w');
console.log('PASS Image compatibility: no controls, no requests, preserved lazy loading and responsive sources, restoration on load');
