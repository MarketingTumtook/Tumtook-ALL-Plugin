function initTumtookGalleryAuto() {
	var shells = document.querySelectorAll('.ttga-gallery-shell');
	if (!shells.length) return;
	var lightboxState = {
		items: [],
		index: 0,
		closeTimer: null,
		returnFocus: null
	};
	var lightbox = createLightbox();

	function createLightbox() {
		var overlay = document.createElement('div');
		var dialog = document.createElement('div');
		var stage = document.createElement('div');
		var close = document.createElement('button');
		var prev = document.createElement('button');
		var next = document.createElement('button');
		var image = document.createElement('img');

		overlay.className = 'ttga-lightbox';
		overlay.hidden = true;
		dialog.className = 'ttga-lightbox-dialog';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-label', 'ดูรูปภาพ');
		stage.className = 'ttga-lightbox-stage';
		close.className = 'ttga-lightbox-close';
		close.type = 'button';
		close.setAttribute('aria-label', 'Close image viewer');
		close.textContent = '×';
		prev.className = 'ttga-lightbox-nav ttga-lightbox-nav--prev';
		prev.type = 'button';
		prev.setAttribute('aria-label', 'Previous image');
		prev.innerHTML = '<span aria-hidden="true">‹</span>';
		next.className = 'ttga-lightbox-nav ttga-lightbox-nav--next';
		next.type = 'button';
		next.setAttribute('aria-label', 'Next image');
		next.innerHTML = '<span aria-hidden="true">›</span>';
		image.className = 'ttga-lightbox-image';

		dialog.appendChild(close);
		stage.appendChild(prev);
		stage.appendChild(image);
		stage.appendChild(next);
		dialog.appendChild(stage);
		overlay.appendChild(dialog);
		document.body.appendChild(overlay);

		close.addEventListener('click', closeLightbox);
		image.addEventListener('load', function () {
			image.classList.add('is-loaded');
			updateLightboxNavOffset();
		});
		prev.addEventListener('click', function () {
			showLightboxImage(lightboxState.index - 1);
		});
		next.addEventListener('click', function () {
			showLightboxImage(lightboxState.index + 1);
		});
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				closeLightbox();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (overlay.hidden) {
				return;
			}

			if (event.key === 'Tab') {
				var buttons = [close, prev, next].filter(function (button) { return !button.hidden; });
				var position = buttons.indexOf(document.activeElement);
				event.preventDefault();
				buttons[(position + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
				return;
			}

			if (event.key === 'Escape') {
				closeLightbox();
			} else if (event.key === 'ArrowLeft') {
				event.preventDefault();
				showLightboxImage(lightboxState.index - 1);
			} else if (event.key === 'ArrowRight') {
				event.preventDefault();
				showLightboxImage(lightboxState.index + 1);
			}
		});

		window.addEventListener('resize', updateLightboxNavOffset);

		return {
			overlay: overlay,
			image: image,
			stage: stage,
			prev: prev,
			next: next,
			close: close
		};
	}

	function updateLightboxNavOffset() {
		if (!lightbox || !lightbox.stage || !lightbox.image) {
			return;
		}

		var imageWidth = lightbox.image.getBoundingClientRect().width || 0;
		var navWidth = lightbox.prev && lightbox.prev.getBoundingClientRect().width || 48;
		var imageGap = Math.max(8, Math.min(24, Math.round(imageWidth * 0.04)));
		var offset = Math.round(navWidth + imageGap);
		lightbox.stage.style.setProperty('--ttga-nav-gap', imageGap + 'px');
		lightbox.stage.style.setProperty('--ttga-nav-offset', offset + 'px');
	}

	function showLightboxImage(index) {
		if (!lightboxState.items.length) {
			return;
		}

		lightboxState.index = (index + lightboxState.items.length) % lightboxState.items.length;
		renderLightbox();
	}

	function renderLightbox() {
		var item = lightboxState.items[lightboxState.index];
		if (!item) {
			return;
		}

		lightbox.image.classList.remove('is-loaded');
		lightbox.image.src = item.src;
		lightbox.image.alt = item.alt || '';
		if (lightbox.image.complete && lightbox.image.naturalWidth) lightbox.image.classList.add('is-loaded');
		updateLightboxNavOffset();
		var hasMultiple = lightboxState.items.length > 1;
		lightbox.prev.hidden = !hasMultiple;
		lightbox.next.hidden = !hasMultiple;
	}

	function openLightbox(items, index) {
		if (lightboxState.closeTimer) {
			window.clearTimeout(lightboxState.closeTimer);
			lightboxState.closeTimer = null;
		}

		lightboxState.returnFocus = document.activeElement;
		lightboxState.items = items;
		lightboxState.index = index;
		renderLightbox();
		lightbox.overlay.hidden = false;
		lightbox.close.focus();
		window.requestAnimationFrame(function () {
			lightbox.overlay.classList.add('ttga-lightbox-visible');
		});
		document.body.classList.add('ttga-lightbox-open');
		updateLightboxNavOffset();
	}

	function closeLightbox() {
		lightbox.overlay.classList.remove('ttga-lightbox-visible');
		if (lightboxState.closeTimer) {
			window.clearTimeout(lightboxState.closeTimer);
		}
		lightboxState.closeTimer = window.setTimeout(function () {
			lightbox.overlay.hidden = true;
			lightbox.image.removeAttribute('src');
			lightboxState.closeTimer = null;
			if (lightboxState.returnFocus && lightboxState.returnFocus.isConnected) {
				lightboxState.returnFocus.focus({ preventScroll: true });
			}
		}, 240);
		document.body.classList.remove('ttga-lightbox-open');
	}

	function positiveNumber(value, fallback) {
		value = Number(value);
		return Number.isFinite(value) && value > 0 ? value : fallback;
	}

	function getColumns(shell, width, gap) {
		var requested = parseInt(shell.dataset.columns, 10) || 0;
		var minWidth = positiveNumber(shell.dataset.minWidth, 220);
		var capacity = Math.max(1, Math.floor((width + gap) / (140 + gap)));
		if (requested) {
			return Math.min(12, requested, capacity);
		}
		if (width <= 600) {
			return Math.min(2, capacity);
		}
		return Math.min(12, Math.max(1, Math.floor((width + gap) / (minWidth + gap))));
	}

	function createCard(item, priority, scheduleLayout) {
		var article = document.createElement('article');
		var media = document.createElement('button');
		var image = document.createElement('img');
		var fallback = document.createElement('span');
		var width = positiveNumber(item.width, 0);
		var height = positiveNumber(item.height, 0);
		article.className = 'ttga-card';
		article.setAttribute('role', 'listitem');
		article.dataset.ttgaKey = String(item.key || item.image);
		media.className = 'ttga-media';
		media.type = 'button';
		media.setAttribute('aria-label', item.alt || item.title || 'เปิดรูปภาพ');
		media.setAttribute('aria-haspopup', 'dialog');
		fallback.className = 'ttga-noimage';
		fallback.textContent = 'ไม่สามารถโหลดรูปภาพได้';
		fallback.hidden = true;
		image.alt = item.alt || item.title || '';
		image.loading = priority ? 'eager' : 'lazy';
		image.decoding = 'async';
		if (priority) image.setAttribute('fetchpriority', 'high');
		if (width && height) {
			image.width = Math.round(width);
			image.height = Math.round(height);
			media.style.setProperty('--ttga-ratio', width + ' / ' + height);
		}
		image.addEventListener('load', function () {
			// Replace stale API dimensions with the real dimensions, including cached images.
			if (image.naturalWidth && image.naturalHeight) {
				image.width = image.naturalWidth;
				image.height = image.naturalHeight;
			}
			media.classList.add('is-loaded');
			scheduleLayout();
		});
		image.addEventListener('error', function () {
			image.hidden = true;
			fallback.hidden = false;
			media.disabled = true;
			media.classList.add('is-loaded');
			scheduleLayout();
		});
		media.addEventListener('click', function () {
			var gallery = article.closest('.ttga-gallery');
			var images = Array.prototype.slice.call(gallery.querySelectorAll('.ttga-media img:not([hidden])'));
			openLightbox(images.map(function (entry) {
				return { src: entry.currentSrc || entry.src, alt: entry.alt };
			}), Math.max(0, images.indexOf(image)));
		});
		media.appendChild(image);
		media.appendChild(fallback);
		article.appendChild(media);
		image.src = item.image;
		return article;
	}

	shells.forEach(function (shell) {
		if (shell.dataset.ttgaInitialized) return;
		shell.dataset.ttgaInitialized = '1';
		var gallery = shell.querySelector('.ttga-gallery');
		var loader = shell.querySelector('.ttga-loader');
		var retry = shell.querySelector('.ttga-retry');
		var sentinel = shell.querySelector('.ttga-sentinel');
		var gap = Math.max(0, Number(shell.dataset.gap) || 0);
		var page = 1;
		// Keep the batch size fixed: changing it on resize would skip API offsets.
		var perPage = 12;
		var loading = false;
		var complete = false;
		var failed = false;
		var layoutFrame = null;
		var observer;
		var seen = new Set();
		var config = window.TumtookGalleryAutoData;
		if (!gallery || !loader || !retry || !sentinel || !config) return;

		function scheduleLayout() {
			if (layoutFrame !== null) return;
			layoutFrame = window.requestAnimationFrame(function () {
				layoutFrame = null;
				layout();
			});
		}

		function layout() {
			var width = gallery.clientWidth;
			if (!width) return; // A hidden tab will be laid out by ResizeObserver when shown.
			var cards = Array.prototype.slice.call(gallery.children);
			var columns = getColumns(shell, width, gap);
			var cardWidth = (width - gap * (columns - 1)) / columns;
			var heights = Array(columns).fill(0);
			var rtl = window.getComputedStyle(gallery).direction === 'rtl';
			gallery.classList.add('is-masonry');
			shell.dataset.activeColumns = String(columns);
			// Batch width writes before measuring so every height reflects the new width.
			cards.forEach(function (card) { card.style.width = cardWidth + 'px'; });
			var cardHeights = cards.map(function (card) { return card.getBoundingClientRect().height; });
			cards.forEach(function (card, index) {
				var column = heights.indexOf(Math.min.apply(Math, heights));
				var left = column * (cardWidth + gap);
				card.style.left = (rtl ? width - cardWidth - left : left) + 'px';
				card.style.top = heights[column] + 'px';
				heights[column] += cardHeights[index] + gap;
			});
			gallery.style.height = Math.max(0, Math.max.apply(Math, heights) - gap) + 'px';
			maybeLoad();
		}

		function setStatus(message) {
			loader.textContent = message;
			loader.hidden = !message;
		}

		function nearViewport() {
			if (!shell.getClientRects().length) return false;
			var bounds = sentinel.getBoundingClientRect();
			return bounds.top <= window.innerHeight + 420 && bounds.bottom >= -420;
		}

		function maybeLoad() {
			if (!loading && !complete && !failed && nearViewport()) loadPage();
		}

		function loadPage() {
			if (loading || complete || failed) return;
			loading = true;
			retry.hidden = true;
			gallery.setAttribute('aria-busy', 'true');
			setStatus(config.strings.loading);
			var url = new URL(config.restUrl, window.location.origin);
			url.searchParams.set('page_id', shell.dataset.pageId || '0');
			url.searchParams.set('limit', shell.dataset.limit || '50');
			url.searchParams.set('page', page);
			url.searchParams.set('per_page', perPage);
			if (shell.dataset.endpoint) {
				url.searchParams.set('endpoint', shell.dataset.endpoint);
				url.searchParams.set('endpoint_signature', shell.dataset.endpointSignature || '');
			}
			fetch(url.toString(), {
				credentials: 'same-origin',
				headers: config.nonce ? { 'X-WP-Nonce': config.nonce } : {}
			})
				.then(function (response) {
					if (!response.ok) throw new Error('Gallery request failed');
					return response.json();
				})
				.then(function (data) {
					if (!Array.isArray(data.items)) throw new Error('Invalid gallery response');
					var fragment = document.createDocumentFragment();
					data.items.forEach(function (item, index) {
						if (!item || !item.image) return;
						var key = String(item.key || item.image);
						if (seen.has(key)) return;
						seen.add(key);
						fragment.appendChild(createCard(item, page === 1 && index < 8, scheduleLayout));
					});
					gallery.appendChild(fragment);
					complete = !data.has_more || !data.items.length;
					page += 1;
					setStatus(seen.size ? '' : config.strings.empty);
					if (complete) {
						shell.classList.add('is-complete');
						if (observer) observer.disconnect();
					}
				})
				.catch(function () {
					failed = true;
					setStatus(config.strings.error);
					retry.hidden = false;
				})
				.finally(function () {
					loading = false;
					gallery.setAttribute('aria-busy', 'false');
					scheduleLayout();
				});
		}

		retry.addEventListener('click', function () {
			failed = false;
			loadPage();
		});
		if ('ResizeObserver' in window) {
			var lastWidth = -1;
			var resizeObserver = new ResizeObserver(function () {
				var width = gallery.clientWidth;
				if (width !== lastWidth) {
					lastWidth = width;
					scheduleLayout();
				}
			});
			resizeObserver.observe(gallery);
		}
		window.addEventListener('resize', scheduleLayout);
		if ('IntersectionObserver' in window) {
			observer = new IntersectionObserver(function (entries) {
				if (entries.some(function (entry) { return entry.isIntersecting; })) maybeLoad();
			}, { rootMargin: '420px 0px' });
			observer.observe(sentinel);
		}
		window.addEventListener('scroll', maybeLoad, { passive: true });
		loadPage();
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initTumtookGalleryAuto);
} else {
	initTumtookGalleryAuto();
}
