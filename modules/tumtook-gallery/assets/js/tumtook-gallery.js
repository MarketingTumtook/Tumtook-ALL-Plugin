document.addEventListener('DOMContentLoaded', function () {
	var shells = document.querySelectorAll('.ttg-gallery-shell');
	var lightboxState = {
		items: [],
		index: 0,
		closeTimer: null
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
			next: next
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

		lightboxState.items = items;
		lightboxState.index = index;
		renderLightbox();
		lightbox.overlay.hidden = false;
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
		}, 240);
		document.body.classList.remove('ttg-lightbox-open');
	}

	function createCard(item, options) {
		var article = document.createElement('article');
		var media = document.createElement('div');
		var noImage = document.createElement('div');
		var noImageIcon = document.createElement('span');
		var noImageText = document.createElement('span');
		var image = document.createElement('img');
		var itemKey = getItemKey(item);
		var imageWidth = getPositiveNumber(item.width);
		var imageHeight = getPositiveNumber(item.height);
		var priority = !!(options && options.priority);

		article.className = 'ttg-card';
		article.setAttribute('data-ttg-key', itemKey);
		media.className = 'ttg-media';
		noImage.className = 'ttg-noimage';
		noImage.hidden = true;
		noImageIcon.className = 'ttg-noimage-icon';
		noImageIcon.setAttribute('aria-hidden', 'true');
		noImageText.className = 'ttg-noimage-text';
		noImageText.textContent = 'No image';

		noImage.appendChild(noImageIcon);
		noImage.appendChild(noImageText);

		image.alt = item.alt || item.title || '';
		image.loading = priority ? 'eager' : 'lazy';
		image.decoding = 'async';
		image.tabIndex = 0;
		if (priority) {
			image.fetchPriority = 'high';
			image.setAttribute('fetchpriority', 'high');
		}
		if (imageWidth && imageHeight) {
			image.width = Math.round(imageWidth);
			image.height = Math.round(imageHeight);
		}
		image.addEventListener('click', function () {
			var parentGallery = article.closest('.ttg-gallery');
			var galleryImages = Array.prototype.slice.call(parentGallery.querySelectorAll('.ttg-media img:not([hidden])'));
			var items = galleryImages.map(function (galleryImage) {
				return {
					src: galleryImage.currentSrc || galleryImage.src,
					alt: galleryImage.alt || ''
				};
			});
			var currentIndex = galleryImages.indexOf(image);
			openLightbox(items, currentIndex >= 0 ? currentIndex : 0);
		});
		image.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				image.click();
			}
		});
		image.addEventListener('load', function () {
			var parentGallery = article.closest('.ttg-gallery');
			media.classList.add('ttg-media--loaded');
			if (parentGallery) {
				scheduleMasonryResize(parentGallery, [article]);
			}
		});
		image.addEventListener('error', function () {
			var parentGallery = article.closest('.ttg-gallery');
			image.hidden = true;
			media.classList.add('ttg-media--loaded');
			noImage.hidden = false;
			if (parentGallery) {
				scheduleMasonryResize(parentGallery, [article]);
			}
		});
		image.src = item.image;

		media.appendChild(noImage);
		media.appendChild(image);
		article.appendChild(media);

		return article;
	}

	function getItemKey(item) {
		if (item && item.key) {
			return String(item.key);
		}

		return item && item.image ? String(item.image).trim() : '';
	}

	function getPositiveNumber(value) {
		value = parseFloat(value);

		return value > 0 ? value : 0;
	}

	function resizeMasonryItems(gallery, cards) {
		var galleryStyles = window.getComputedStyle(gallery);
		var rowGap = parseFloat(galleryStyles.getPropertyValue('row-gap')) || parseFloat(galleryStyles.getPropertyValue('gap')) || 0;
		var rowSize = parseFloat(galleryStyles.getPropertyValue('grid-auto-rows')) || 4;

		cards.forEach(function (card) {
			card.style.gridRowEnd = '';
			var span = Math.ceil((card.getBoundingClientRect().height + rowGap) / (rowSize + rowGap));
			card.style.gridRowEnd = 'span ' + Math.max(span, 1);
		});
	}

	function scheduleMasonryResize(gallery, cards) {
		if (!gallery) {
			return;
		}

		gallery.__ttgResizeCards = (gallery.__ttgResizeCards || []).concat(cards || []);
		if (gallery.__ttgResizeFrame) {
			return;
		}

		gallery.__ttgResizeFrame = window.requestAnimationFrame(function () {
			var queuedCards = gallery.__ttgResizeCards || [];
			gallery.__ttgResizeCards = [];
			gallery.__ttgResizeFrame = null;
			resizeMasonryItems(gallery, queuedCards);
		});
	}

	function showEndPanel(shell) {
		shell.classList.add('is-complete');
	}

	function animateCards(gallery, cards) {
		var shell = gallery.closest('.ttg-gallery-shell');
		var columns = Math.max(1, getColumns(shell || gallery));
		var now = Date.now();
			var itemDelay = 42;
			var revealDuration = 560;
			var rowPause = 20;
		var rowStartDelay = shell ? Math.max(0, parseFloat(shell.dataset.ttgRevealReadyAt || '0') - now) : 0;
		var rows = [];

		resizeMasonryItems(gallery, cards);

		cards.forEach(function (card, index) {
			var rowIndex = Math.floor(index / columns);
			if (!rows[rowIndex]) {
				rows[rowIndex] = [];
			}

			rows[rowIndex].push(card);
		});

		rows.forEach(function (row) {
			row.forEach(function (card, index) {
				card.style.setProperty('--ttg-delay', (rowStartDelay + index * itemDelay) + 'ms');
				window.requestAnimationFrame(function () {
					card.classList.add('ttg-card-visible');
				});
			});

				rowStartDelay += Math.max(0, row.length - 1) * itemDelay + revealDuration + rowPause;
		});

		if (shell) {
			shell.dataset.ttgRevealReadyAt = String(now + rowStartDelay);
		}
	}

	function getColumns(shell) {
		var columns = parseInt(shell.dataset.columns || '4', 10);
		if (window.innerWidth <= 479) {
			return Math.min(columns, 2);
		}
		if (window.innerWidth <= 767) {
			return Math.min(columns, 2);
		}
		if (window.innerWidth <= 1024) {
			return Math.min(columns, 3);
		}

		return columns;
	}

	function getInitialPerPage(shell) {
		var columns = Math.max(1, getColumns(shell));
		return Math.min(Math.max(columns * 2, 6), 12);
	}

	function getBatchPerPage(shell) {
		var columns = Math.max(1, getColumns(shell));
		return Math.min(Math.max(columns * 2, 6), 12);
	}

	shells.forEach(function (shell) {
		var gallery = shell.querySelector('.ttg-gallery');
		var loader = shell.querySelector('.ttg-loader');
		var sentinel = shell.querySelector('.ttg-sentinel');
		var pageId = parseInt(shell.dataset.pageId || '0', 10);
		var endpoint = shell.dataset.endpoint || '';
		var limit = parseInt(shell.dataset.limit || '0', 10);
		var page = 1;
		var loading = false;
		var complete = false;
		var pendingLoad = false;
		var observer;
		var resizeFrame;
		var scrollFrame;
		var seenItems = {};

		function setLoaderState(message, hidden) {
			loader.textContent = message;
			loader.hidden = hidden;
		}

		function buildUrl(perPage) {
			var url = new URL(TumtookGalleryData.restUrl, window.location.origin);

			url.searchParams.set('page_id', pageId);
			url.searchParams.set('endpoint', endpoint);
			url.searchParams.set('page', page);
			url.searchParams.set('per_page', perPage);
			url.searchParams.set('limit', limit);

			return url.toString();
		}

		function isSentinelNearViewport() {
			if (!sentinel) {
				return false;
			}

			var rect = sentinel.getBoundingClientRect();
			var preloadDistance = Math.max(window.innerHeight * 0.75, 420);

			return rect.top <= preloadDistance;
		}

			function requestLoad(perPage) {
				if (loading || complete) {
					if (loading && !complete) {
					pendingLoad = true;
				}
				return;
			}

			loadPage(perPage);
		}

		function loadPage(perPage) {
			if (loading || complete) {
				return;
			}

			loading = true;
			setLoaderState(TumtookGalleryData.strings.loading, false);

			fetch(buildUrl(perPage), {
				method: 'GET',
				credentials: 'same-origin'
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('Request failed');
					}

					return response.json();
				})
				.then(function (data) {
					var items = Array.isArray(data.items) ? data.items : [];
					var uniqueItems = items.filter(function (item) {
						var itemKey = getItemKey(item);

						if (!itemKey || seenItems[itemKey]) {
							return false;
						}

						seenItems[itemKey] = true;
						return true;
					});

					if (!uniqueItems.length && page === 1) {
						complete = true;
						setLoaderState(TumtookGalleryData.strings.empty, false);
						return;
					}

					var priorityCount = page === 1 ? Math.min(uniqueItems.length, getColumns(shell) * 2) : 0;
					var newCards = uniqueItems.map(function (item, index) {
						return createCard(item, {
							priority: index < priorityCount
						});
					});
					var fragment = document.createDocumentFragment();

					newCards.forEach(function (card) {
						fragment.appendChild(card);
					});

					gallery.appendChild(fragment);

					complete = !data.has_more;
					page += 1;
					setLoaderState('', true);

						window.requestAnimationFrame(function () {
							animateCards(gallery, newCards);
							if (complete) {
								showEndPanel(shell);
							}
						});

					if (complete && observer) {
						observer.disconnect();
					}
				})
				.catch(function () {
					setLoaderState(TumtookGalleryData.strings.error, false);
				})
				.finally(function () {
					loading = false;
					if (!complete && (pendingLoad || isSentinelNearViewport())) {
						pendingLoad = false;
						window.requestAnimationFrame(function () {
							requestLoad(getBatchPerPage(shell));
						});
					}
				});
		}

		requestLoad(getInitialPerPage(shell));

		window.addEventListener('resize', function () {
			window.cancelAnimationFrame(resizeFrame);
			resizeFrame = window.requestAnimationFrame(function () {
				resizeMasonryItems(gallery, Array.prototype.slice.call(gallery.querySelectorAll('.ttg-card')));
			});
		});

		observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						requestLoad(getBatchPerPage(shell));
					}
				});
			},
			{
				rootMargin: '420px 0px'
			}
		);

		observer.observe(sentinel);

		window.addEventListener('scroll', function () {
			if (scrollFrame) {
				return;
			}

			scrollFrame = window.requestAnimationFrame(function () {
				scrollFrame = null;
				if (!complete && isSentinelNearViewport()) {
					requestLoad(getBatchPerPage(shell));
				}
			});
		}, { passive: true });
	});
});
