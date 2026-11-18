(function () {
    'use strict';
    function init() {
        // Coalesce identical code instances on one page without persisting stale results.
        var requests = new Map();
        function load(root) {
            if (root.dataset.ttgcStarted) return;
            root.dataset.ttgcStarted = '1';
            var url = new URL(root.dataset.ttgcUrl, window.location.origin);
            url.searchParams.set('code', root.dataset.ttgcCode);
            url.searchParams.set('signature', root.dataset.ttgcSignature);
            var key = url.toString();
            if (!requests.has(key)) {
                var controller = new AbortController();
                var timeout = window.setTimeout(function () { controller.abort(); }, 10000);
                var request = fetch(key, { signal: controller.signal, credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Gallery request failed');
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || !Array.isArray(data.items)) throw new Error('Invalid gallery response');
                        return data.items.slice(0, 50);
                    })
                    .finally(function () { window.clearTimeout(timeout); });
                requests.set(key, request);
            }
            requests.get(key).then(function (items) {
                var fragment = document.createDocumentFragment();
                items.forEach(function (item, index) {
                    if (!item || typeof item.image !== 'string') return;
                    var imageUrl;
                    try { imageUrl = new URL(item.image, window.location.origin); } catch (error) { return; }
                    if (imageUrl.protocol !== 'https:' && imageUrl.protocol !== 'http:') return;
                    var card = document.createElement('article');
                    var link = document.createElement('a');
                    var image = document.createElement('img');
                    card.className = 'ttgc-item';
                    card.setAttribute('role', 'listitem');
                    link.className = 'ttgc-link';
                    link.href = imageUrl.href;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.setAttribute('aria-label', (item.alt || 'ดูรูปภาพ') + ' (เปิดในแท็บใหม่)');
                    image.alt = item.alt || '';
                    image.decoding = 'async';
                    image.loading = index < 2 ? 'eager' : 'lazy';
                    if (Number(item.width) > 0 && Number(item.height) > 0) {
                        image.width = Number(item.width);
                        image.height = Number(item.height);
                        image.style.aspectRatio = 'auto ' + image.width + ' / ' + image.height;
                    }
                    image.addEventListener('error', function () { card.hidden = true; });
                    image.src = imageUrl.href;
                    link.appendChild(image);
                    card.appendChild(link);
                    fragment.appendChild(card);
                });
                root.querySelector('.ttgc-items').appendChild(fragment);
                root.dataset.ttgcState = items.length ? 'ready' : 'empty';
            }).catch(function () {
                // Preserve the production policy: no public error text or retry controls.
                root.dataset.ttgcState = 'error';
            }).finally(function () { root.setAttribute('aria-busy', 'false'); });
        }
        document.querySelectorAll('.ttgc[data-ttgc-code]').forEach(function (root) {
            if (root.dataset.ttgcInitialized) return;
            root.dataset.ttgcInitialized = '1';
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    if (!entries.some(function (entry) { return entry.isIntersecting; })) return;
                    observer.disconnect();
                    load(root);
                }, { rootMargin: '600px 0px' });
                observer.observe(root);
            } else load(root);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
