(function () {
    'use strict';
    function createLightbox() {
        var dialog = document.createElement('dialog');
        var close = document.createElement('button');
        var prev = document.createElement('button');
        var next = document.createElement('button');
        var stage = document.createElement('figure');
        var image = document.createElement('img');
        var count = document.createElement('figcaption');
        var error = document.createElement('p');
        var items = [], index = 0, trigger = null, active = false, previousOverflow = '';
        dialog.className = 'ttgc-lightbox';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-label', 'แกลเลอรีรูปภาพ');
        dialog.setAttribute('aria-modal', 'true');
        close.className = 'ttgc-lightbox-close';
        prev.className = 'ttgc-lightbox-prev';
        next.className = 'ttgc-lightbox-next';
        [close, prev, next].forEach(function (button) { button.type = 'button'; });
        close.textContent = '×'; prev.textContent = '←'; next.textContent = '→';
        close.setAttribute('aria-label', 'ปิดรูปภาพ');
        prev.setAttribute('aria-label', 'รูปก่อนหน้า');
        next.setAttribute('aria-label', 'รูปถัดไป');
        stage.className = 'ttgc-lightbox-stage';
        image.className = 'ttgc-lightbox-image';
        count.className = 'ttgc-lightbox-count';
        count.setAttribute('role', 'status');
        error.className = 'ttgc-lightbox-error';
        error.setAttribute('role', 'status');
        error.textContent = 'ไม่สามารถเปิดรูปนี้ได้ กรุณาเลือกรูปอื่น';
        error.hidden = true;
        stage.appendChild(image); stage.appendChild(error); stage.appendChild(count);
        dialog.appendChild(close); dialog.appendChild(prev); dialog.appendChild(stage); dialog.appendChild(next);
        document.body.appendChild(dialog);

        function render() {
            image.hidden = false; error.hidden = true;
            image.alt = items[index].alt;
            image.src = items[index].src;
            count.textContent = (index + 1) + ' / ' + items.length;
            prev.disabled = index === 0;
            next.disabled = index === items.length - 1;
        }
        function move(delta) {
            if (!active || index + delta < 0 || index + delta >= items.length) return;
            index += delta; render();
        }
        function cleanup() {
            if (!active) return;
            active = false;
            document.body.style.overflow = previousOverflow;
            image.removeAttribute('src');
            items = [];
            if (trigger && trigger.isConnected) trigger.focus();
        }
        function hide() {
            if (typeof dialog.close === 'function') dialog.close();
            else dialog.removeAttribute('open');
            cleanup();
        }
        close.addEventListener('click', hide);
        prev.addEventListener('click', function () { move(-1); });
        next.addEventListener('click', function () { move(1); });
        image.addEventListener('error', function () { image.hidden = true; error.hidden = false; });
        dialog.addEventListener('close', cleanup);
        dialog.addEventListener('cancel', function (event) { event.preventDefault(); hide(); });
        dialog.addEventListener('click', function (event) { if (event.target === dialog) hide(); });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault(); move(event.key === 'ArrowLeft' ? -1 : 1);
            } else if (event.key === 'Escape') { event.preventDefault(); hide(); }
            else if (event.key === 'Tab') {
                var controls = [close, prev, next].filter(function (button) { return !button.disabled; });
                var first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        return {
            open: function (entries, selected, button) {
                items = entries;
                index = selected;
                trigger = button;
                if (!active) previousOverflow = document.body.style.overflow;
                active = true;
                render();
                if (!dialog.open) {
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                    else dialog.setAttribute('open', '');
                }
                document.body.style.overflow = 'hidden';
                close.focus();
            }
        };
    }
    function init() {
        var lightbox = null;
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
                var galleryItems = [];
                items.forEach(function (item, index) {
                    if (!item || typeof item.image !== 'string') return;
                    var imageUrl;
                    try { imageUrl = new URL(item.image, window.location.origin); } catch (error) { return; }
                    if (imageUrl.protocol !== 'https:' && imageUrl.protocol !== 'http:') return;
                    var card = document.createElement('article');
                    var link = document.createElement('button');
                    var image = document.createElement('img');
                    card.className = 'ttgc-item';
                    card.setAttribute('role', 'listitem');
                    link.className = 'ttgc-link';
                    link.type = 'button';
                    link.setAttribute('aria-label', item.alt || 'ดูรูปภาพ');
                    link.setAttribute('aria-haspopup', 'dialog');
                    galleryItems.push({ src: imageUrl.href, alt: item.alt || '', button: link, card: card });
                    link.addEventListener('click', function () {
                        var available = galleryItems.filter(function (entry) { return !entry.card.hidden; });
                        var selected = available.findIndex(function (entry) { return entry.button === link; });
                        if (selected < 0) return;
                        if (!lightbox) lightbox = createLightbox();
                        lightbox.open(available, selected, link);
                    });
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
