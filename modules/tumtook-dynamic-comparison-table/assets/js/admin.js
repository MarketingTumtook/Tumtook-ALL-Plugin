(function ($) {
	'use strict';

	const rowTypes = [
		['text', 'Plain Text'],
		['multiline', 'Multiline Text'],
		['price', 'Price'],
		['check', 'Checkmark'],
		['cross', 'Cross Mark'],
		['yesno', 'Yes / No'],
		['button', 'Button'],
		['image', 'Image'],
		['html', 'Limited HTML'],
		['custom', 'Custom Text']
	];

	function uid(prefix) {
		return prefix + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
	}

	function clone(value) {
		return JSON.parse(JSON.stringify(value));
	}

	function getPath(object, path) {
		return path.split('.').reduce((carry, key) => carry && carry[key], object);
	}

	function setPath(object, path, value) {
		const parts = path.split('.');
		let target = object;
		parts.slice(0, -1).forEach((key) => {
			target[key] = target[key] || {};
			target = target[key];
		});
		target[parts[parts.length - 1]] = value;
	}

	function ensureCells(data) {
		const ids = data.columns.map((column) => column.id);
		data.rows.forEach((row) => {
			const nextValues = {};
			ids.forEach((id) => {
				nextValues[id] = row.values && row.values[id] ? row.values[id] : { content: '' };
			});
			row.values = nextValues;
		});
	}

	function Builder($root) {
		this.$root = $root;
		this.$json = $root.find('[data-ttct-json]');
		this.$workspace = $root.find('[data-ttct-workspace]');
		this.collapsed = false;
		this.data = this.read();
		ensureCells(this.data);
		this.bind();
		this.render();
	}

	Builder.prototype.read = function () {
		try {
			return JSON.parse(this.$json.val() || '{}');
		} catch (error) {
			return clone(TTCTAdmin.defaultData);
		}
	};

	Builder.prototype.persist = function () {
		ensureCells(this.data);
		this.$json.val(JSON.stringify(this.data));
	};

	Builder.prototype.bind = function () {
		const self = this;

		this.$root.on('input change', '[data-ttct-path]', function () {
			const $field = $(this);
			const value = $field.attr('type') === 'checkbox' ? $field.is(':checked') : $field.val();
			setPath(self.data, $field.data('ttct-path'), value);
			self.persist();
		});

		this.$root.on('click', '[data-ttct-add-column]', function () {
			const column = clone(TTCTAdmin.defaultData.columns[0]);
			column.id = uid('column');
			column.title = 'รุ่นสินค้า';
			self.data.columns.push(column);
			self.data.rows.forEach((row) => {
				row.values[column.id] = { content: '' };
			});
			self.render();
		});

		this.$root.on('click', '[data-ttct-add-row]', function () {
			const row = clone(TTCTAdmin.defaultData.rows[0]);
			row.id = uid('row');
			row.label = 'หัวข้อใหม่';
			row.values = {};
			self.data.columns.forEach((column) => {
				row.values[column.id] = { content: '' };
			});
			self.data.rows.push(row);
			self.render();
		});

		this.$root.on('click', '[data-ttct-delete-column]', function () {
			if (!window.confirm(TTCTAdmin.confirmDeleteColumn)) {
				return;
			}
			const id = $(this).closest('[data-column-id]').data('column-id');
			self.data.columns = self.data.columns.filter((column) => column.id !== id);
			self.data.rows.forEach((row) => delete row.values[id]);
			self.render();
		});

		this.$root.on('click', '[data-ttct-delete-row]', function () {
			if (!window.confirm(TTCTAdmin.confirmDeleteRow)) {
				return;
			}
			const id = $(this).closest('[data-row-id]').data('row-id');
			self.data.rows = self.data.rows.filter((row) => row.id !== id);
			self.render();
		});

		this.$root.on('click', '[data-ttct-copy-column]', function () {
			const id = $(this).closest('[data-column-id]').data('column-id');
			const source = self.data.columns.find((column) => column.id === id);
			if (!source) {
				return;
			}
			const copy = clone(source);
			copy.id = uid('column');
			copy.title = copy.title + ' Copy';
			const index = self.data.columns.findIndex((column) => column.id === id);
			self.data.columns.splice(index + 1, 0, copy);
			self.data.rows.forEach((row) => {
				row.values[copy.id] = clone(row.values[id] || { content: '' });
			});
			self.render();
		});

		this.$root.on('click', '[data-ttct-copy-row]', function () {
			const id = $(this).closest('[data-row-id]').data('row-id');
			const source = self.data.rows.find((row) => row.id === id);
			if (!source) {
				return;
			}
			const copy = clone(source);
			copy.id = uid('row');
			copy.label = copy.label + ' Copy';
			const index = self.data.rows.findIndex((row) => row.id === id);
			self.data.rows.splice(index + 1, 0, copy);
			self.render();
		});

		this.$root.on('input change', '[data-column-field]', function () {
			const $field = $(this);
			const id = $field.closest('[data-column-id]').data('column-id');
			const column = self.data.columns.find((item) => item.id === id);
			if (!column) {
				return;
			}
			column[$field.data('column-field')] = $field.attr('type') === 'checkbox' ? $field.is(':checked') : $field.val();
			self.persist();
		});

		this.$root.on('input change', '[data-row-field]', function () {
			const $field = $(this);
			const row = self.findRow($field);
			if (!row) {
				return;
			}
			row[$field.data('row-field')] = $field.attr('type') === 'checkbox' ? $field.is(':checked') : $field.val();
			self.renderIfTypeChanged($field);
			self.persist();
		});

		this.$root.on('input change', '[data-cell-field]', function () {
			const $field = $(this);
			const row = self.findRow($field);
			const columnId = $field.closest('[data-cell-column]').data('cell-column');
			if (!row || !columnId) {
				return;
			}
			row.values[columnId] = row.values[columnId] || {};
			row.values[columnId][$field.data('cell-field')] = $field.attr('type') === 'checkbox' ? $field.is(':checked') : $field.val();
			self.persist();
		});

		this.$root.on('click', '[data-ttct-media-row]', function () {
			self.openMedia((attachment) => {
				const row = self.findRow($(this));
				if (row) {
					row.icon_type = 'image';
					row.icon_value = String(attachment.id);
					row.icon_alt = attachment.alt || '';
					self.render();
				}
			});
		});

		this.$root.on('click', '[data-ttct-media-cell]', function () {
			const $button = $(this);
			self.openMedia((attachment) => {
				const row = self.findRow($button);
				const columnId = $button.closest('[data-cell-column]').data('cell-column');
				if (row && columnId) {
					row.values[columnId] = { content: String(attachment.id), alt: attachment.alt || '' };
					self.render();
				}
			});
		});

		this.$root.on('click', '[data-ttct-preview-toggle]', function () {
			self.$root.find('[data-ttct-preview]').prop('hidden', !self.$root.find('[data-ttct-preview]').prop('hidden'));
		});

		this.$root.on('click', '[data-ttct-collapse]', function () {
			self.collapsed = !self.collapsed;
			self.$workspace.toggleClass('is-collapsed', self.collapsed);
		});

		this.$root.on('click', '[data-ttct-reset]', function () {
			if (window.confirm(TTCTAdmin.confirmReset)) {
				self.data = clone(TTCTAdmin.defaultData);
				self.render();
			}
		});

		this.$root.on('click', '[data-ttct-load-sample]', function () {
			self.data = clone(TTCTAdmin.defaultData);
			self.render();
		});

		this.$root.on('click', '[data-ttct-duplicate]', function () {
			const copy = clone(self.data);
			copy.title = copy.title ? copy.title + ' Copy' : 'Comparison Copy';
			self.data = copy;
			self.render();
		});

		this.$root.closest('form').on('submit', function () {
			self.persist();
		});
	};

	Builder.prototype.findRow = function ($element) {
		const id = $element.closest('[data-row-id]').data('row-id');
		return this.data.rows.find((row) => row.id === id);
	};

	Builder.prototype.renderIfTypeChanged = function ($field) {
		if ($field.data('row-field') === 'type') {
			this.render();
		}
	};

	Builder.prototype.openMedia = function (callback) {
		const frame = wp.media({
			title: TTCTAdmin.mediaTitle,
			button: { text: TTCTAdmin.mediaButton },
			multiple: false
		});
		frame.on('select', function () {
			callback(frame.state().get('selection').first().toJSON());
		});
		frame.open();
	};

	Builder.prototype.render = function () {
		ensureCells(this.data);
		this.$workspace.html(this.template());
		this.makeSortable();
		this.persist();
	};

	Builder.prototype.makeSortable = function () {
		const self = this;
		this.$workspace.find('[data-ttct-columns]').sortable({
			items: '[data-column-id]',
			handle: '.ttct-drag',
			update: function () {
				const ids = $(this).children('[data-column-id]').map(function () {
					return $(this).data('column-id');
				}).get();
				self.data.columns.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
				self.render();
			}
		});
		this.$workspace.find('[data-ttct-rows]').sortable({
			items: '[data-row-id]',
			handle: '.ttct-row-drag',
			update: function () {
				const ids = $(this).children('[data-row-id]').map(function () {
					return $(this).data('row-id');
				}).get();
				self.data.rows.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
				self.render();
			}
		});
	};

	Builder.prototype.template = function () {
		const columns = this.data.columns.map((column) => this.columnTemplate(column)).join('');
		const rows = this.data.rows.map((row) => this.rowTemplate(row)).join('');
		return `
			<div class="ttct-columns" data-ttct-columns>${columns}</div>
			<div class="ttct-grid" style="--ttct-columns:${this.data.columns.length}">
				<div class="ttct-grid-head">
					<div class="ttct-feature-col">หัวข้อเปรียบเทียบ</div>
					${this.data.columns.map((column) => `<div>${escapeHtml(column.title || column.id)}</div>`).join('')}
				</div>
				<div data-ttct-rows>${rows}</div>
			</div>
		`;
	};

	Builder.prototype.columnTemplate = function (column) {
		return `
			<section class="ttct-column-card" data-column-id="${escapeAttr(column.id)}">
				<header class="ttct-card-header">
					<span class="ttct-drag dashicons dashicons-move"></span>
					<div>
						<strong>${escapeHtml(column.title || 'รุ่นสินค้า')}</strong>
						<small>${escapeHtml(column.subtitle || 'ตั้งค่าคอลัมน์สินค้า')}</small>
					</div>
					<label class="ttct-compact-check ttct-status-pill"><input type="checkbox" data-column-field="visible" ${checked(column.visible !== false)}> แสดง</label>
				</header>

				<div class="ttct-card-body">
					<div class="ttct-field-section">
						<h4>ข้อมูลรุ่น</h4>
						<div class="ttct-field-grid">
							<label>ชื่อรุ่น<input type="text" data-column-field="title" value="${escapeAttr(column.title)}" placeholder="เช่น รุ่นมาตรฐาน"><small>ชื่อที่แสดงบนหัวคอลัมน์และแท็บมือถือ</small></label>
							<label>คำอธิบายสั้น<input type="text" data-column-field="subtitle" value="${escapeAttr(column.subtitle)}" placeholder="แสดงใต้ชื่อรุ่น"><small>ใช้บอกจุดเด่นของรุ่นนี้แบบสั้น ๆ</small></label>
						</div>
					</div>

					<div class="ttct-field-section">
						<h4>สถานะและป้าย</h4>
						<div class="ttct-checks">
							<label class="ttct-compact-check"><input type="checkbox" data-column-field="featured" ${checked(column.featured)}> แนะนำ</label>
							<label>Badge<input type="text" data-column-field="badge" value="${escapeAttr(column.badge)}" placeholder="ยอดนิยม"><small>ข้อความป้าย เช่น ยอดนิยม</small></label>
							<label>ตำแหน่ง Badge<select data-column-field="badge_position"><option value="bottom" ${selected(column.badge_position, 'bottom')}>ด้านล่าง</option><option value="top" ${selected(column.badge_position, 'top')}>ด้านบน</option></select><small>เลือกว่าจะอยู่เหนือหรือใต้ชื่อรุ่น</small></label>
						</div>
					</div>

					<details class="ttct-advanced">
						<summary>CTA และขั้นสูง</summary>
						<div class="ttct-field-grid">
							<label>ข้อความ CTA<input type="text" data-column-field="button_text" value="${escapeAttr(column.button_text)}" placeholder="สอบถามราคา"><small>ปุ่มนี้จะแสดงในหัวคอลัมน์</small></label>
							<label>URL CTA<input type="url" data-column-field="button_url" value="${escapeAttr(column.button_url)}" placeholder="https://example.com"><small>ใส่ # ได้ถ้ายังไม่มีลิงก์จริง</small></label>
							<label class="ttct-compact-check"><input type="checkbox" data-column-field="button_new_tab" ${checked(column.button_new_tab)}> เปิด CTA ในแท็บใหม่</label>
						</div>
					</details>
				</div>
				<footer>
					<button type="button" class="button ttct-btn ttct-btn--small" data-ttct-copy-column><span class="dashicons dashicons-admin-page" aria-hidden="true"></span>คัดลอกคอลัมน์</button>
					<button type="button" class="button ttct-btn ttct-btn--small ttct-btn--danger" data-ttct-delete-column><span class="dashicons dashicons-trash" aria-hidden="true"></span>ลบคอลัมน์</button>
				</footer>
			</section>
		`;
	};

	Builder.prototype.rowTemplate = function (row) {
		const cells = this.data.columns.map((column) => `
			<div class="ttct-cell" data-cell-column="${escapeAttr(column.id)}">
				${this.cellTemplate(row, column)}
			</div>
		`).join('');
		return `
			<section class="ttct-row" data-row-id="${escapeAttr(row.id)}">
				<div class="ttct-row-config">
					<span class="ttct-row-drag dashicons dashicons-move"></span>
					<div class="ttct-row-main">
						<label>หัวข้อแถว<input type="text" data-row-field="label" value="${escapeAttr(row.label)}" placeholder="เช่น ราคาเริ่มต้น"><small>หัวข้อนี้จะแสดงทางซ้ายของตาราง</small></label>
						<label>ประเภทข้อมูล<select data-row-field="type">${rowTypes.map(([value, label]) => `<option value="${value}" ${selected(row.type, value)}>${label}</option>`).join('')}</select><small>เลือกก่อนกรอก cell เพื่อให้ช่องเหมาะกับข้อมูล</small></label>
					</div>
					<div class="ttct-row-options">
						<label class="ttct-compact-check ttct-status-pill"><input type="checkbox" data-row-field="visible" ${checked(row.visible !== false)}> แสดง</label>
						<button type="button" class="button ttct-btn ttct-btn--small" data-ttct-copy-row><span class="dashicons dashicons-admin-page" aria-hidden="true"></span>คัดลอก</button>
						<button type="button" class="button ttct-btn ttct-btn--small ttct-btn--danger" data-ttct-delete-row><span class="dashicons dashicons-trash" aria-hidden="true"></span>ลบ</button>
					</div>
					<details class="ttct-advanced">
						<summary>ไอคอนและขั้นสูง</summary>
						<div class="ttct-field-grid">
							<label>Icon Type<select data-row-field="icon_type"><option value="none" ${selected(row.icon_type, 'none')}>None</option><option value="dashicon" ${selected(row.icon_type, 'dashicon')}>Dashicon</option><option value="image" ${selected(row.icon_type, 'image')}>Image</option></select><small>Dashicon ใช้ชื่อ class, Image ใช้รูปจาก Media</small></label>
							<label>Icon Value<input type="text" data-row-field="icon_value" value="${escapeAttr(row.icon_value)}" placeholder="dashicons-shield หรือ Attachment ID"><small>ตัวอย่าง dashicons-shield</small></label>
							<label>Alt Text<input type="text" data-row-field="icon_alt" value="${escapeAttr(row.icon_alt)}"><small>จำเป็นเมื่อใช้ไอคอนแบบรูปภาพ</small></label>
							<button type="button" class="button ttct-btn ttct-btn--small" data-ttct-media-row><span class="dashicons dashicons-format-image" aria-hidden="true"></span>เลือกจาก Media</button>
						</div>
					</details>
				</div>
				<div class="ttct-row-cells">${cells}</div>
			</section>
		`;
	};

	Builder.prototype.cellTemplate = function (row, column) {
		const cell = row.values[column.id] || { content: '' };
		switch (row.type) {
			case 'multiline':
			case 'html':
				return `<textarea data-cell-field="content" rows="3">${escapeHtml(cell.content)}</textarea>`;
			case 'check':
			case 'cross':
			case 'yesno':
				return `<label class="ttct-cell-check"><input type="checkbox" data-cell-field="content" ${checked(cell.content === '1' || cell.content === true)}> เปิดค่า</label>`;
			case 'button':
				return `<input type="text" data-cell-field="content" placeholder="ข้อความปุ่ม" value="${escapeAttr(cell.content)}"><input type="url" data-cell-field="url" placeholder="URL" value="${escapeAttr(cell.url)}"><label class="ttct-cell-hint"><input type="checkbox" data-cell-field="new_tab" ${checked(cell.new_tab)}> เปิดแท็บใหม่</label><small>ใช้เมื่อ cell นี้ต้องเป็นปุ่มเฉพาะรุ่น</small>`;
			case 'image':
				return `<input type="number" data-cell-field="content" placeholder="Attachment ID" value="${escapeAttr(cell.content)}"><input type="text" data-cell-field="alt" placeholder="Alt text" value="${escapeAttr(cell.alt)}"><button type="button" class="button ttct-btn ttct-btn--small" data-ttct-media-cell><span class="dashicons dashicons-format-image" aria-hidden="true"></span>Media</button><small>เลือกรูปจาก Media Library หรือใส่ Attachment ID</small>`;
			default:
				return `<input type="${row.type === 'price' ? 'text' : 'text'}" data-cell-field="content" value="${escapeAttr(cell.content)}">`;
		}
	};

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
	}

	function escapeAttr(value) {
		return escapeHtml(value);
	}

	function checked(value) {
		return value ? 'checked' : '';
	}

	function selected(value, expected) {
		return value === expected ? 'selected' : '';
	}

	$(function () {
		$('[data-ttct-builder]').each(function () {
			new Builder($(this));
		});
	});
})(jQuery);
