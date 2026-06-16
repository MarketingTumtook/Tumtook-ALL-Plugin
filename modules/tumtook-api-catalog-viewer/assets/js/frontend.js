(function () {
	const accordions = document.querySelectorAll(".aiv-accordion");
	if (!accordions.length) {
		return;
	}

	accordions.forEach((accordion) => {
		const toggle = accordion.querySelector(".aiv-accordion-toggle");
		const panel = accordion.querySelector(".aiv-accordion-panel");

		if (!toggle || !panel) {
			return;
		}

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
})();
