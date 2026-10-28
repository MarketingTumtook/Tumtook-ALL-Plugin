function initTumtookGallery() {
	var shells = document.querySelectorAll('.ttg-gallery-shell');
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

		overlay.className = 'ttg-lightbox';
		overlay.hidden = true;
		dialog.className = 'ttg-lightbox-dialog';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-label', 'ดูรูปภาพ');
		stage.className = 'ttg-lightbox-stage';
		close.className = 'ttg-lightbox-close';
		close.type = 'button';
		close.setAttribute('aria-label', 'Close image viewer');
		close.textContent = '×';
		prev.className = 'ttg-lightbox-nav ttg-lightbox-nav--prev';
		prev.type = 'button';
		prev.setAttribute('aria-label', 'Previous image');
		prev.innerHTML = '<span aria-hidden="true">‹</span>';
		next.className = 'ttg-lightbox-nav ttg-lightbox-nav--next';
		next.type = 'button';
		next.setAttribute('aria-label', 'Next image');
		next.innerHTML = '<span aria-hidden="true">›</span>';
		image.className = 'ttg-lightbox-image';

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
		lightbox.stage.style.setProperty('--ttg-nav-gap', imageGap + 'px');
		lightbox.stage.style.setProperty('--ttg-nav-offset', offset + 'px');
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
			lightbox.overlay.classList.add('ttg-lightbox-visible');
		});
		document.body.classList.add('ttg-lightbox-open');
		updateLightboxNavOffset();
	}

	function closeLightbox() {
		lightbox.overlay.classList.remove('ttg-lightbox-visible');
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
		document.body.classList.remove('ttg-lightbox-open');
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
		// Restore the original Gallery defaults without requiring a columns attribute.
		if (shell.dataset.layout === 'natural') {
			return Math.min(width <= 767 ? 2 : width <= 1024 ? 3 : 6, capacity);
		}
		if (width <= 600) {
			return Math.min(2, capacity);
		}
		return Math.min(12, Math.max(1, Math.floor((width + gap) / (minWidth + gap))));
	}

	function imageIdentity(source) {
		try {
			var url = new URL(source, window.location.href);
			var path = url.pathname.replace(/(?:-\d+x\d+|-scaled)(?=\.(?:jpe?g|png|webp|gif|avif)$)/i, '');
			var query = [];
			url.searchParams.forEach(function (value, name) {
				// Keep source selectors; sizing, cache and signature changes are still one image.
				if (!/^(?:w|h|width|height|q|quality|fit|crop|fm|format|auto|dpr|resize|v|ver|cb|cachebust|_|expires|signature|sig|utm_.*|x-amz-.*|x-goog-.*)$/i.test(name)) {
					query.push(encodeURIComponent(name) + '=' + encodeURIComponent(value));
				}
			});
			return url.host.toLowerCase() + path + (query.length ? '?' + query.sort().join('&') : '');
		} catch (error) {
			return String(source).trim();
		}
	}

	function createCard(item, index, eager, priority, mixed, onResize) {
		var article = document.createElement('article');
		var media = document.createElement('button');
		var image = document.createElement('img');
		var fallback = document.createElement('span');
		// Only Home crops images into a fixed mix; other pages preserve source proportions.
		var ratios = ['1 / 1', '16 / 9', '5 / 4', '16 / 9', '5 / 4', '1 / 1', '5 / 4', '1 / 1', '16 / 9'];
		article.className = 'ttg-card';
		article.setAttribute('role', 'listitem');
		article.dataset.ttgKey = String(item.key || item.image);
		media.className = 'ttg-media';
		var width = Number(item.width);
		var height = Number(item.height);
		if (mixed) {
			media.style.setProperty('--ttg-ratio', ratios[index % ratios.length]);
		} else if (width > 0 && height > 0 && Number.isFinite(width) && Number.isFinite(height)) {
			media.style.setProperty('--ttg-ratio', width + ' / ' + height);
		}
		media.type = 'button';
		media.setAttribute('aria-label', item.alt || item.title || 'เปิดรูปภาพ');
		media.setAttribute('aria-haspopup', 'dialog');
		fallback.className = 'ttg-noimage';
		fallback.textContent = 'ไม่สามารถโหลดรูปภาพได้';
		fallback.hidden = true;
		image.alt = item.alt || item.title || '';
		image.decoding = 'async';
		image.dataset.src = item.image;
		if (eager) image.dataset.ttgEager = '1';
		if (priority) image.setAttribute('fetchpriority', 'high');
		image.addEventListener('load', function () {
			if (!mixed && image.naturalWidth > 0 && image.naturalHeight > 0) {
				media.style.setProperty('--ttg-ratio', image.naturalWidth + ' / ' + image.naturalHeight);
				onResize();
			}
			media.classList.add('is-loaded');
		});
		image.addEventListener('error', function () {
			image.hidden = true;
			fallback.hidden = false;
			media.disabled = true;
			media.classList.add('is-loaded');
		});
		media.addEventListener('click', function () {
			var gallery = article.closest('.ttg-gallery');
			var images = Array.prototype.slice.call(gallery.querySelectorAll('.ttg-media img:not([hidden])'));
			openLightbox(images.map(function (entry) {
				return { src: entry.dataset.src || entry.currentSrc || entry.src, alt: entry.alt };
			}), Math.max(0, images.indexOf(image)));
		});
		media.appendChild(image);
		media.appendChild(fallback);
		article.appendChild(media);
		return article;
	}

	shells.forEach(function (shell) {
		if (shell.dataset.ttgInitialized) return;
		shell.dataset.ttgInitialized = '1';
		var gallery = shell.querySelector('.ttg-gallery');
		var loader = shell.querySelector('.ttg-loader');
		var sentinel = shell.querySelector('.ttg-sentinel');
		// Support previously cached markup without data-layout as well.
		var mixed = shell.dataset.layout ? shell.dataset.layout === 'mixed' : document.body.classList.contains('home');
		shell.dataset.layout = mixed ? 'mixed' : 'natural';
		var gap = Math.max(0, Number(shell.dataset.gap) || 0);
		var page = 1;
		// Keep the batch size fixed: changing it on resize would skip API offsets.
		var perPage = 24;
		var loading = false;
		var complete = false;
		var failed = false;
		var layoutFrame = null;
		var observer;
		var imageObserver;
		var scrollFrame = null;
		var preloadDistance = Math.max(1000, Math.round(window.innerHeight * 1.5));
		var seen = new Set();
		var seenUrls = new Set();
		var config = window.TumtookGalleryData;
		if (!gallery || !loader || !sentinel || !config) return;

		function startImage(image) {
			if (!image.dataset.src) return;
			// The observer already delays distant images; avoid a second native lazy-load delay.
			image.loading = 'eager';
			image.src = image.dataset.src;
			delete image.dataset.src;
			if (imageObserver) imageObserver.unobserve(image);
		}

		function loadNearbyImages() {
			if (!shell.getClientRects().length || !gallery.clientWidth) return;
			gallery.querySelectorAll('img[data-src]').forEach(function (image) {
				var bounds = image.getBoundingClientRect();
				if (image.dataset.ttgEager || (bounds.top <= window.innerHeight + preloadDistance && bounds.bottom >= -preloadDistance)) {
					startImage(image);
				} else if (imageObserver && !image.dataset.ttgObserved) {
					image.dataset.ttgObserved = '1';
					imageObserver.observe(image);
				}
			});
		}

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
			loadNearbyImages();
			maybeLoad();
		}

		function setStatus(message) {
			loader.textContent = message;
			loader.hidden = !message;
		}

		function nearViewport() {
			if (!shell.getClientRects().length) return false;
			var bounds = sentinel.getBoundingClientRect();
			return bounds.top <= window.innerHeight + preloadDistance && bounds.bottom >= -preloadDistance;
		}

		function maybeLoad() {
			if (!loading && !complete && !failed && nearViewport()) loadPage();
		}

		function loadPage() {
			if (loading || complete || failed) return;
			loading = true;
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
			var controller = new AbortController();
			var timeout = window.setTimeout(function () { controller.abort(); }, 10000);
			fetch(url.toString(), {
				signal: controller.signal,
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
					var columns = getColumns(shell, gallery.clientWidth, gap);
					var shellBounds = shell.getBoundingClientRect();
					var eager = page === 1 && shellBounds.top < window.innerHeight && shellBounds.bottom >= 0;
					var added = 0;
					data.items.forEach(function (item) {
						if (!item || !item.image) return;
						var key = item.key ? String(item.key) : '';
						var source = imageIdentity(item.image);
						if ((key && seen.has(key)) || seenUrls.has(source)) return;
						if (key) seen.add(key);
						seenUrls.add(source);
						fragment.appendChild(createCard(item, seenUrls.size - 1, eager && added < columns * 2, eager && added < columns, mixed, scheduleLayout));
						added += 1;
					});
					gallery.appendChild(fragment);
					complete = !data.has_more || !data.items.length;
					page += 1;
					setStatus(seenUrls.size ? '' : config.strings.empty);
					if (complete) {
						shell.classList.add('is-complete');
						if (observer) observer.disconnect();
					}
				})
				.catch(function () {
					failed = true;
					setStatus(config.strings.error);
				})
				.finally(function () {
					window.clearTimeout(timeout);
					loading = false;
					gallery.setAttribute('aria-busy', 'false');
					scheduleLayout();
				});
		}

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
			imageObserver = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) startImage(entry.target);
				});
			}, { rootMargin: preloadDistance + 'px 0px' });
			observer = new IntersectionObserver(function (entries) {
				if (entries.some(function (entry) { return entry.isIntersecting; })) maybeLoad();
			}, { rootMargin: preloadDistance + 'px 0px' });
			observer.observe(sentinel);
		}
		window.addEventListener('scroll', function () {
			if (scrollFrame !== null) return;
			scrollFrame = window.requestAnimationFrame(function () {
				scrollFrame = null;
				loadNearbyImages();
				maybeLoad();
			});
		}, { passive: true });
		// Do not fetch offscreen galleries during the initial page load.
		if ('IntersectionObserver' in window) {
			var startObserver = new IntersectionObserver(function (entries) {
				if (entries.some(function (entry) { return entry.isIntersecting; })) {
					startObserver.disconnect();
					loadPage();
				}
			}, { rootMargin: preloadDistance + 'px 0px' });
			startObserver.observe(shell);
		} else {
			loadPage();
		}
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initTumtookGallery);
} else {
	initTumtookGallery();
}
