(function () {

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

	const init = () => document.querySelectorAll(".aiv-gallery-wrapper").forEach((wrapper) => {
		initAccordions(wrapper);
	});
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init, { once: true });
	} else {
		init();
	}
})();
