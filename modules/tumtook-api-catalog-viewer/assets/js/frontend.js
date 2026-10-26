(function () {
	const config = window.TumtookApiImageViewer || {};

	const initAccordions = (root) => {
		root.querySelectorAll(".aiv-accordion:not([data-aiv-ready])").forEach((accordion) => {
			const toggle = accordion.querySelector(".aiv-accordion-toggle");
			const panel = accordion.querySelector(".aiv-accordion-panel");

			if (!toggle || !panel) {
				return;
			}

			accordion.setAttribute("data-aiv-ready", "true");
			panel.hidden = true;
			panel.classList.remove("is-open");
			toggle.setAttribute("aria-expanded", "false");

			toggle.addEventListener("click", () => {
				const isOpen = toggle.getAttribute("aria-expanded") === "true";

				if (isOpen) {
					toggle.setAttribute("aria-expanded", "false");
					panel.classList.remove("is-open");
					window.setTimeout(() => {
						if (toggle.getAttribute("aria-expanded") === "false") {
							panel.hidden = true;
						}
					}, 260);
					return;
				}

				panel.hidden = false;
				requestAnimationFrame(() => {
					toggle.setAttribute("aria-expanded", "true");
					panel.classList.add("is-open");
				});
			});
		});
	};

	const initLoadMore = (wrapper) => {
		const gallery = wrapper.querySelector(".aiv-gallery");
		const button = wrapper.querySelector(".aiv-load-more-button");
		const status = wrapper.querySelector(".aiv-load-more-status");

		if (!gallery || !button || !config.ajaxUrl || !config.nonce) {
			return;
		}

		button.addEventListener("click", async () => {
			if (button.disabled) {
				return;
			}

			button.disabled = true;
			button.textContent = config.loadingText || "Loading...";
			if (status) {
				status.textContent = "";
			}

			const formData = new FormData();
			formData.append("action", "tumtook_catalog_images_load_more");
			formData.append("nonce", config.nonce);
			formData.append("post_id", wrapper.dataset.postId || "0");
			formData.append("offset", wrapper.dataset.offset || "0");
			formData.append("per_page", wrapper.dataset.perPage || "12");
			formData.append("limit", wrapper.dataset.limit || "0");

			try {
				const response = await fetch(config.ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: formData
				});
				const data = await response.json();

				if (!response.ok || !data.success || !data.data) {
					throw new Error("Catalog request failed");
				}

				if (data.data.html) {
					const template = document.createElement("template");
					template.innerHTML = data.data.html.trim();
					gallery.appendChild(template.content);
					initAccordions(gallery);
				}

				wrapper.dataset.offset = String(data.data.offset || wrapper.dataset.offset || "0");
				if (!data.data.hasMore) {
					button.closest(".aiv-load-more-wrap").remove();
					return;
				}

				button.disabled = false;
				button.textContent = config.loadMoreText || "Load more";
			} catch (error) {
				button.disabled = false;
				button.textContent = config.loadMoreText || "Load more";
				if (status) {
					status.textContent = config.failureText || "Unable to load more items.";
				}
			}
		});
	};

	document.querySelectorAll(".aiv-gallery-wrapper").forEach((wrapper) => {
		initAccordions(wrapper);
		initLoadMore(wrapper);
	});
})();
