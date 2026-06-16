(function () {
	const box = document.querySelector(".aiv-page-box");
	if (!box || typeof TumtookApiImageViewerAdmin === "undefined") {
		return;
	}

	const previewCard = box.querySelector(".aiv-preview-card");
	const previewContent = document.getElementById("aiv-preview-content");
	const pageFormTable = box.querySelector(".form-table");
	const postIdField = document.getElementById("post_ID");

	if (!previewCard || !previewContent || !pageFormTable) {
		return;
	}

	const fields = {
		api_url: box.querySelector('[name="tumtook_catalog_images_page_settings[api_url]"]'),
		items_path: box.querySelector('[name="tumtook_catalog_images_page_settings[items_path]"]'),
		item_code_filter: box.querySelector('[name="tumtook_catalog_images_page_settings[item_code_filter]"]'),
		image_key: box.querySelector('[name="tumtook_catalog_images_page_settings[image_key]"]'),
		title_key: box.querySelector('[name="tumtook_catalog_images_page_settings[title_key]"]'),
		alt_key: box.querySelector('[name="tumtook_catalog_images_page_settings[alt_key]"]'),
		cache_minutes: box.querySelector('[name="tumtook_catalog_images_page_settings[cache_minutes]"]')
	};

	let timer = null;
	let requestId = 0;

	const getPayload = () => ({
		api_url: fields.api_url ? fields.api_url.value.trim() : "",
		items_path: fields.items_path ? fields.items_path.value.trim() : "",
		item_code_filter: fields.item_code_filter ? fields.item_code_filter.value.trim() : "",
		image_key: fields.image_key ? fields.image_key.value.trim() : "",
		title_key: fields.title_key ? fields.title_key.value.trim() : "",
		alt_key: fields.alt_key ? fields.alt_key.value.trim() : "",
		cache_minutes: fields.cache_minutes ? fields.cache_minutes.value.trim() : ""
	});

	const renderMessage = (message, isError) => {
		previewContent.innerHTML = isError
			? '<div class="notice notice-error inline aiv-preview-message"><p>' + message + "</p></div>"
			: '<p class="aiv-preview-message">' + message + "</p>";
	};

	const renderLoading = () => {
		previewContent.innerHTML =
			'<div class="aiv-preview-loading">' +
			'<span class="aiv-spinner" aria-hidden="true"></span>' +
			'<span>' + TumtookApiImageViewerAdmin.loadingText + "</span>" +
			"</div>";
	};

	const syncEditorSurface = (surface) => {
		const inputId = surface.getAttribute("data-input-id");
		if (!inputId) {
			return;
		}

		const input = document.getElementById(inputId);
		if (!input) {
			return;
		}

		input.value = surface.innerHTML;

		const htmlEditor = surface.parentElement.querySelector(".aiv-html-editor-input");
		if (htmlEditor && htmlEditor.hidden) {
			htmlEditor.value = surface.innerHTML;
		}
	};

	const initCustomEditors = (scope) => {
		const surfaces = scope.querySelectorAll("[data-editor-surface]");
		surfaces.forEach((surface) => {
			if (surface.dataset.editorReady === "true") {
				return;
			}

			surface.dataset.editorReady = "true";

			surface.addEventListener("input", () => {
				syncEditorSurface(surface);
			});

			surface.addEventListener("blur", () => {
				syncEditorSurface(surface);
			});

			const toolbar = surface.parentElement.querySelector("[data-editor-toolbar]");
			const htmlEditor = surface.parentElement.querySelector(".aiv-html-editor-input");
			if (toolbar) {
				toolbar.addEventListener("click", (event) => {
					const button = event.target.closest("[data-command]");
					const toggleHtml = event.target.closest("[data-toggle-html]");

					if (toggleHtml) {
						event.preventDefault();
						const isHtmlMode = toggleHtml.classList.contains("is-active");

						if (isHtmlMode) {
							surface.innerHTML = htmlEditor ? htmlEditor.value : surface.innerHTML;
							surface.hidden = false;
							surface.setAttribute("contenteditable", "true");
							if (htmlEditor) {
								htmlEditor.hidden = true;
							}
							toggleHtml.classList.remove("is-active");
							toggleHtml.textContent = "HTML";
							syncEditorSurface(surface);
						} else {
							syncEditorSurface(surface);
							if (htmlEditor) {
								htmlEditor.value = surface.innerHTML;
								htmlEditor.hidden = false;
							}
							surface.hidden = true;
							surface.setAttribute("contenteditable", "false");
							toggleHtml.classList.add("is-active");
							toggleHtml.textContent = "Visual";
						}
						return;
					}

					if (!button) {
						return;
					}

					event.preventDefault();
					surface.focus();
					document.execCommand(button.getAttribute("data-command"), false, null);
					syncEditorSurface(surface);
				});

				toolbar.addEventListener("change", (event) => {
					const control = event.target.closest("[data-command]");
					if (!control) {
						return;
					}

					surface.focus();
					document.execCommand(control.getAttribute("data-command"), false, control.value);
					syncEditorSurface(surface);
				});
			}

			if (htmlEditor) {
				htmlEditor.addEventListener("input", () => {
					const inputId = surface.getAttribute("data-input-id");
					const input = inputId ? document.getElementById(inputId) : null;
					if (input) {
						input.value = htmlEditor.value;
					}
				});
			}

			syncEditorSurface(surface);
		});
	};

	const loadPreview = async () => {
		const currentRequestId = ++requestId;
		const payload = getPayload();

		if (!payload.api_url) {
			renderMessage(TumtookApiImageViewerAdmin.emptyUrlText, false);
			return;
		}

		renderLoading();

		const formData = new FormData();
		formData.append("action", "tumtook_catalog_images_preview");
		formData.append("nonce", TumtookApiImageViewerAdmin.nonce);
		formData.append("post_id", postIdField ? postIdField.value : "0");

		Object.entries(payload).forEach(([key, value]) => {
			formData.append("settings[" + key + "]", value);
		});

		try {
			const response = await fetch(TumtookApiImageViewerAdmin.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: formData
			});
			const data = await response.json();

			if (!response.ok || !data.success || !data.data || !data.data.html) {
				renderMessage(TumtookApiImageViewerAdmin.failureText, true);
				return;
			}

			if (currentRequestId !== requestId) {
				return;
			}

			previewContent.innerHTML = data.data.html;
			initCustomEditors(previewContent);
		} catch (error) {
			renderMessage(TumtookApiImageViewerAdmin.failureText, true);
		}
	};

	const schedulePreviewReload = (delay) => {
		window.clearTimeout(timer);
		timer = window.setTimeout(loadPreview, delay);
	};

	const watchedFieldNames = new Set(
		Object.values(fields)
			.filter(Boolean)
			.map((field) => field.name)
	);

	const handleFieldEvent = (eventName, event) => {
		const target = event.target;
		if (!target || !target.name || !watchedFieldNames.has(target.name)) {
			return;
		}

		if (fields.api_url && target === fields.api_url) {
			if (eventName === "blur" || eventName === "change") {
				loadPreview();
				return;
			}

			schedulePreviewReload(120);
			return;
		}

		schedulePreviewReload(eventName === "blur" || eventName === "change" ? 100 : 400);
	};

	["input", "change", "keyup", "blur", "paste"].forEach((eventName) => {
		pageFormTable.addEventListener(
			eventName,
			(event) => {
				handleFieldEvent(eventName, event);
			},
			true
		);
	});

	initCustomEditors(document);
	schedulePreviewReload(50);
})();
