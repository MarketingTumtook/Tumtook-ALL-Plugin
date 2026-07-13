<?php
/**
 * Plugin Name: Tumtook Page FAQ
 * Description: Adds a page-based FAQ section with accordion answers.
 * Version: 1.0.2
 * Author: Tumtook
 * Text Domain: tumtook-page-faq
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Page_FAQ
{
	const VERSION = '1.0.2';
	const META_KEY = '_tt_page_faq';
	const SHORTCODE = 'tumtook_faq';
	const FONT_HANDLE = 'tumtook-kanit-font';

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_box'));
		add_action('save_post_page', array($this, 'save_meta'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
	}

	public function register_meta_box()
	{
		add_meta_box(
			'tt-page-faq',
			__('Page FAQ', 'tumtook-page-faq'),
			array($this, 'render_meta_box'),
			'page',
			'normal',
			'default'
		);
	}

	public function enqueue_admin_assets($hook)
	{
		global $post_type;

		if (('post.php' !== $hook && 'post-new.php' !== $hook) || 'page' !== $post_type) {
			return;
		}

		$this->enqueue_kanit_font();
		wp_enqueue_script('jquery-ui-sortable');
	}

	public function register_front_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'tt-page-faq',
			plugin_dir_url(__FILE__) . 'assets/css/front.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/front.css')
		);

		wp_register_script(
			'tt-page-faq',
			plugin_dir_url(__FILE__) . 'assets/js/front.js',
			array(),
			$this->get_asset_version('assets/js/front.js'),
			true
		);
	}

	private function get_default_settings()
	{
		return array(
			'enabled' => '1',
			'auto_display' => '0',
			'title' => __('คำถามที่พบบ่อย', 'tumtook-page-faq'),
			'subtitle' => __('คำตอบก่อนสั่งผลิต กระชับ อ่านง่าย ตัดสินใจไว', 'tumtook-page-faq'),
			'empty_title' => __('สนใจสั่งสินค้า', 'tumtook-page-faq'),
			'empty_text' => __('ติดต่อแอดไลน์เพื่อสั่งสินค้าได้เลย', 'tumtook-page-faq'),
			'contact_button_label' => __('พูดคุยกับฝ่ายขาย', 'tumtook-page-faq'),
			'contact_button_url' => '',
			'items' => array(),
		);
	}

	private function enqueue_kanit_font()
	{
		$this->register_kanit_font();
		wp_enqueue_style(self::FONT_HANDLE);
	}

	private function register_kanit_font()
	{
		if (function_exists('tumtook_aio_register_kanit_font')) {
			tumtook_aio_register_kanit_font(self::FONT_HANDLE);
			return;
		}

		wp_register_style(
			self::FONT_HANDLE,
			'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap',
			array(),
			null
		);
	}

	private function get_asset_version($relative_path)
	{
		if (function_exists('tumtook_aio_asset_version')) {
			return tumtook_aio_asset_version(__FILE__, $relative_path, self::VERSION);
		}

		return self::VERSION;
	}

	private function get_settings($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		$settings = wp_parse_args(is_array($saved) ? $saved : array(), $this->get_default_settings());

		$settings['enabled'] = !empty($settings['enabled']) ? '1' : '0';
		$settings['auto_display'] = '0';
		$settings['title'] = sanitize_text_field($settings['title']);
		$settings['subtitle'] = sanitize_text_field($settings['subtitle']);
		$settings['empty_title'] = sanitize_text_field($settings['empty_title']);
		$settings['empty_text'] = sanitize_textarea_field($settings['empty_text']);
		$settings['contact_button_label'] = sanitize_text_field($settings['contact_button_label']);
		$settings['contact_button_url'] = esc_url_raw($settings['contact_button_url']);
		$settings['items'] = $this->sanitize_items(isset($settings['items']) ? $settings['items'] : array());

		return $settings;
	}

	private function has_saved_settings($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		return is_array($saved) && !empty($saved);
	}

	private function has_faq_items($settings)
	{
		return !empty($settings['items']) && is_array($settings['items']);
	}

	private function sanitize_items($items)
	{
		$clean_items = array();

		if (!is_array($items)) {
			return $clean_items;
		}

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$question = sanitize_text_field(isset($item['question']) ? $item['question'] : '');
			$answer = sanitize_textarea_field(isset($item['answer']) ? $item['answer'] : '');
			$item_id = $this->sanitize_item_id(isset($item['item_id']) ? $item['item_id'] : '');
			$deleted = !empty($item['deleted']);

			if ($deleted) {
				continue;
			}

			if ('' === $question && '' === $answer) {
				continue;
			}

			$clean_items[] = array(
				'id' => $item_id ? $item_id : $this->generate_item_id(),
				'question' => $question,
				'answer' => $answer,
			);
		}

		return $clean_items;
	}

	private function sanitize_item_id($item_id)
	{
		$item_id = sanitize_key((string) $item_id);
		return $item_id ? $item_id : '';
	}

	private function generate_item_id()
	{
		return 'faq-' . wp_generate_uuid4();
	}

	public function render_meta_box($post)
	{
		$settings = $this->get_settings($post->ID);

		wp_nonce_field('tt_page_faq_save', 'tt_page_faq_nonce');
		?>
		<style>
			.ttfq-admin-wrap,
			.ttfq-admin-wrap button,
			.ttfq-admin-wrap input,
			.ttfq-admin-wrap select,
			.ttfq-admin-wrap textarea {
				font-family: "Kanit", sans-serif
			}

			.ttfq-admin-wrap {
				display: grid;
				gap: 18px
			}

			.ttfq-admin-panel {
				border: 1px solid #dcdcde;
				border-radius: 16px;
				padding: 16px;
				background: #fff
			}

			.ttfq-admin-panel h3 {
				margin: 0 0 12px;
				font-size: 15px
			}

			.ttfq-admin-note,
			.ttfq-admin-hint {
				margin: 0;
				color: #646970
			}

			.ttfq-admin-hint {
				font-size: 12px;
				line-height: 1.35
			}

			.ttfq-admin-grid {
				display: grid;
				grid-template-columns: repeat(2, minmax(280px, 1fr));
				gap: 14px 16px
			}

			.ttfq-admin-field {
				display: grid;
				gap: 6px
			}

			.ttfq-admin-field--full {
				grid-column: 1 / -1
			}

			.ttfq-admin-field label {
				font-weight: 600
			}

			.ttfq-admin-field input,
			.ttfq-admin-field textarea,
			.ttfq-admin-field select {
				width: 100%;
				max-width: none;
				box-sizing: border-box
			}

			.ttfq-admin-checklist {
				display: grid;
				gap: 8px
			}

			.ttfq-admin-items {
				display: grid;
				gap: 12px
			}

			.ttfq-admin-item {
				border: 1px solid #dcdcde;
				border-radius: 14px;
				padding: 14px;
				background: #fcfcfc
			}

			.ttfq-admin-item.is-dragging {
				opacity: .88;
				box-shadow: 0 14px 32px rgba(16, 24, 40, .12);
			}

			.ttfq-admin-item-head {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
				margin-bottom: 12px
			}

			.ttfq-admin-item-title {
				font-weight: 600
			}

			.ttfq-admin-item-title-wrap {
				display: inline-flex;
				align-items: center;
				gap: 10px;
				min-width: 0
			}

			.ttfq-admin-item-handle {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 34px;
				height: 34px;
				border: 1px solid #d0d5dd;
				border-radius: 10px;
				background: #fff;
				color: #667085;
				cursor: grab;
				flex: 0 0 auto;
				transition: background-color .2s ease, border-color .2s ease, color .2s ease
			}

			.ttfq-admin-item-handle:hover {
				background: #f8fafc;
				border-color: #bfc6d1;
				color: #344054
			}

			.ttfq-admin-item-handle:active {
				cursor: grabbing
			}

			.ttfq-admin-item-handle svg {
				width: 16px;
				height: 16px;
				display: block
			}

			.ttfq-admin-sort-placeholder {
				border: 1px dashed #ff2e13;
				border-radius: 14px;
				background: rgba(255, 46, 19, .04);
				min-height: 176px
			}

			.ttfq-admin-item-remove {
				border: 0;
				border-radius: 999px;
				padding: 6px 10px;
				background: #fbeaea;
				color: #b42318;
				cursor: pointer
			}

			.ttfq-admin-add {
				border: 0;
				border-radius: 12px;
				padding: 10px 16px;
				background: #ff2e13;
				color: #fff;
				cursor: pointer
			}

			@media (max-width:782px) {
				.ttfq-admin-grid {
					grid-template-columns: 1fr
				}
			}
		</style>

		<div class="ttfq-admin-wrap">
			<input type="hidden" id="ttfq-deleted-item-ids" name="ttfq_deleted_item_ids" value="" />
			<div class="ttfq-admin-panel">
				<h3><?php esc_html_e('Section FAQ ของหน้านี้', 'tumtook-page-faq'); ?></h3>
				<p class="ttfq-admin-note">
					<?php esc_html_e('ใส่คำถามและคำตอบจากหลังบ้านของหน้า page ได้เลย ระบบจะแสดงเป็น accordion ด้านหน้าเว็บให้อัตโนมัติ', 'tumtook-page-faq'); ?>
				</p>

				<div class="ttfq-admin-checklist" style="margin-top:16px">
					<label><input type="checkbox" name="ttfq_settings[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?> /> <?php esc_html_e('เปิดใช้งาน section นี้', 'tumtook-page-faq'); ?></label>
				</div>

				<div class="ttfq-admin-grid" style="margin-top:16px">
					<div class="ttfq-admin-field">
						<label for="ttfq-title"><?php esc_html_e('หัวข้อ', 'tumtook-page-faq'); ?></label>
						<input id="ttfq-title" type="text" name="ttfq_settings[title]"
							value="<?php echo esc_attr($settings['title']); ?>" />
					</div>
					<div class="ttfq-admin-field">
						<label for="ttfq-subtitle"><?php esc_html_e('คำอธิบายสั้น', 'tumtook-page-faq'); ?></label>
						<input id="ttfq-subtitle" type="text" name="ttfq_settings[subtitle]"
							value="<?php echo esc_attr($settings['subtitle']); ?>" />
					</div>
					<div class="ttfq-admin-field">
						<label for="ttfq-contact-button-label"><?php esc_html_e('ปุ่มขวา', 'tumtook-page-faq'); ?></label>
						<input id="ttfq-contact-button-label" type="text" name="ttfq_settings[contact_button_label]"
							value="<?php echo esc_attr($settings['contact_button_label']); ?>" />
					</div>
					<div class="ttfq-admin-field">
						<label for="ttfq-contact-button-url"><?php esc_html_e('ลิงก์ปุ่มขวา', 'tumtook-page-faq'); ?></label>
						<input id="ttfq-contact-button-url" type="url" name="ttfq_settings[contact_button_url]"
							value="<?php echo esc_attr($settings['contact_button_url']); ?>" />
					</div>
					<div class="ttfq-admin-field">
						<label for="ttfq-empty-title"><?php esc_html_e('หัวข้อกล่องช่วยเหลือ', 'tumtook-page-faq'); ?></label>
						<input id="ttfq-empty-title" type="text" name="ttfq_settings[empty_title]"
							value="<?php echo esc_attr($settings['empty_title']); ?>" />
					</div>
					<div class="ttfq-admin-field ttfq-admin-field--full">
						<label for="ttfq-empty-text"><?php esc_html_e('ข้อความกล่องช่วยเหลือ', 'tumtook-page-faq'); ?></label>
						<textarea id="ttfq-empty-text" rows="3"
							name="ttfq_settings[empty_text]"><?php echo esc_textarea($settings['empty_text']); ?></textarea>
					</div>
				</div>
			</div>

			<div class="ttfq-admin-panel">
				<h3><?php esc_html_e('รายการคำถาม', 'tumtook-page-faq'); ?></h3>
				<div class="ttfq-admin-items" id="ttfq-admin-items" style="margin-top:16px">
					<?php foreach ($settings['items'] as $index => $item): ?>
						<?php $this->render_admin_item($index, $item); ?>
					<?php endforeach; ?>
				</div>

				<button type="button" class="ttfq-admin-add" id="ttfq-admin-add" style="margin-top:16px">
					<?php esc_html_e('เพิ่มคำถาม', 'tumtook-page-faq'); ?>
				</button>

				<p class="ttfq-admin-hint" style="margin-top:16px">
					<?php esc_html_e('วิธีใช้ shortcode: วาง [tumtook_faq] ในเนื้อหาของหน้านี้เพื่อแสดง FAQ ตรงตำแหน่งที่ต้องการ หรือใช้ [tumtook_faq page_id="123"] เพื่อดึง FAQ ของ page อื่นมาแสดง', 'tumtook-page-faq'); ?>
				</p>
			</div>
		</div>

		<template id="ttfq-admin-item-template">
			<?php $this->render_admin_item('__INDEX__', array('question' => '', 'answer' => '')); ?>
		</template>
		<script>
			(function ($) {
				function initTumtookFaqAdminInline() {
					var $items = $('#ttfq-admin-items');
					var $add = $('#ttfq-admin-add');
					var $template = $('#ttfq-admin-item-template');
					var $deleted = $('#ttfq-deleted-item-ids');

					if (!$items.length || !$add.length || !$template.length || !$deleted.length) {
						return;
					}

					function syncFieldNames() {
						$items.find('[data-ttfq-item]').each(function (index) {
							$(this).find('[data-ttfq-field]').each(function () {
								var field = $(this).attr('data-ttfq-field');

								if (!field) {
									return;
								}

								$(this).attr('name', 'ttfq_settings[items][' + index + '][' + field + ']');
							});
						});
					}

					function initSortable() {
						if (typeof $items.sortable !== 'function') {
							return;
						}

						if ($items.data('ui-sortable')) {
							$items.sortable('destroy');
						}

						$items.sortable({
							items: '> [data-ttfq-item]:visible',
							handle: '.ttfq-admin-item-handle',
							placeholder: 'ttfq-admin-sort-placeholder',
							forcePlaceholderSize: true,
							tolerance: 'pointer',
							cancel: 'input, textarea, a, label',
							start: function (event, ui) {
								ui.item.addClass('is-dragging');
							},
							stop: function (event, ui) {
								ui.item.removeClass('is-dragging');
								syncFieldNames();
							},
							update: function () {
								syncFieldNames();
							}
						});
					}

					function rememberDeleted(id) {
						var ids;

						if (!id) {
							return;
						}

						ids = ($deleted.val() || '').split(',').map(function (value) {
							return $.trim(value);
						}).filter(Boolean);

						if (ids.indexOf(id) === -1) {
							ids.push(id);
							$deleted.val(ids.join(','));
						}
					}

					$add.off('click.ttfqInline').on('click.ttfqInline', function (event) {
						var html;

						event.preventDefault();
						html = ($template.html() || '').replace(/__INDEX__/g, String(Date.now()));
						$items.append(html);
						syncFieldNames();
						initSortable();
					});

					$(document).off('click.ttfqInlineRemove').on('click.ttfqInlineRemove', '[data-ttfq-remove]', function (event) {
						var $item = $(this).closest('[data-ttfq-item]');
						var itemId = $item.find('[data-ttfq-item-id]').val() || '';

						event.preventDefault();
						event.stopPropagation();
						rememberDeleted(itemId);
						$item.find('[data-ttfq-deleted-flag]').val('1');
						$item.find('[data-ttfq-field="question"]').val('');
						$item.find('[data-ttfq-field="answer"]').val('');
						$item.hide();
						syncFieldNames();
						initSortable();
					});

					syncFieldNames();
					initSortable();
				}

				$(initTumtookFaqAdminInline);
			})(jQuery);
		</script>
		<?php
	}

	private function render_admin_item($index, $item)
	{
		?>
		<div class="ttfq-admin-item" data-ttfq-item>
			<input type="hidden" name="ttfq_settings[items][<?php echo esc_attr($index); ?>][item_id]"
				value="<?php echo esc_attr(isset($item['id']) ? $item['id'] : ''); ?>" data-ttfq-item-id
				data-ttfq-field="item_id" />
			<input type="hidden" name="ttfq_settings[items][<?php echo esc_attr($index); ?>][deleted]" value="0"
				data-ttfq-deleted-flag data-ttfq-field="deleted" />
			<div class="ttfq-admin-item-head">
				<div class="ttfq-admin-item-title-wrap">
					<span class="ttfq-admin-item-handle" role="img"
						aria-label="<?php esc_attr_e('ลากเพื่อเรียงลำดับ', 'tumtook-page-faq'); ?>"
						title="<?php esc_attr_e('ลากเพื่อเรียงลำดับ', 'tumtook-page-faq'); ?>">
						<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
							<path fill="currentColor"
								d="M7 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm9-12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
						</svg>
					</span>
					<div class="ttfq-admin-item-title"><?php esc_html_e('คำถาม FAQ', 'tumtook-page-faq'); ?></div>
				</div>
				<button type="button" class="ttfq-admin-item-remove"
					data-ttfq-remove><?php esc_html_e('ลบ', 'tumtook-page-faq'); ?></button>
			</div>
			<div class="ttfq-admin-grid">
				<div class="ttfq-admin-field ttfq-admin-field--full">
					<label><?php esc_html_e('คำถาม', 'tumtook-page-faq'); ?></label>
					<input type="text" name="ttfq_settings[items][<?php echo esc_attr($index); ?>][question]"
						value="<?php echo esc_attr(isset($item['question']) ? $item['question'] : ''); ?>"
						data-ttfq-field="question" />
				</div>
				<div class="ttfq-admin-field ttfq-admin-field--full">
					<label><?php esc_html_e('คำตอบ', 'tumtook-page-faq'); ?></label>
					<textarea rows="4" name="ttfq_settings[items][<?php echo esc_attr($index); ?>][answer]"
						data-ttfq-field="answer"><?php echo esc_textarea(isset($item['answer']) ? $item['answer'] : ''); ?></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_meta($post_id)
	{
		if (!isset($_POST['tt_page_faq_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tt_page_faq_nonce'])), 'tt_page_faq_save')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_page', $post_id)) {
			return;
		}

		$settings = isset($_POST['ttfq_settings']) ? wp_unslash($_POST['ttfq_settings']) : array();
		if (!is_array($settings)) {
			$settings = array();
		}

		$clean = $this->get_default_settings();
		$clean['enabled'] = !empty($settings['enabled']) ? '1' : '0';
		$clean['auto_display'] = '0';
		$clean['title'] = sanitize_text_field(isset($settings['title']) ? $settings['title'] : '');
		$clean['subtitle'] = sanitize_text_field(isset($settings['subtitle']) ? $settings['subtitle'] : '');
		$clean['empty_title'] = sanitize_text_field(isset($settings['empty_title']) ? $settings['empty_title'] : '');
		$clean['empty_text'] = sanitize_textarea_field(isset($settings['empty_text']) ? $settings['empty_text'] : '');
		$clean['contact_button_label'] = sanitize_text_field(isset($settings['contact_button_label']) ? $settings['contact_button_label'] : '');
		$clean['contact_button_url'] = esc_url_raw(isset($settings['contact_button_url']) ? $settings['contact_button_url'] : '');
		$clean['items'] = $this->sanitize_items(isset($settings['items']) ? $settings['items'] : array());

		update_post_meta($post_id, self::META_KEY, $clean);
	}

	public function append_to_content($content)
	{
		if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		$post_id = get_the_ID();

		if (!$post_id) {
			return $content;
		}

		if (false !== strpos($content, '[' . self::SHORTCODE)) {
			return $content;
		}

		if (false !== strpos($content, 'class="ttfaq-section"') || false !== strpos($content, "class='ttfaq-section'")) {
			return $content;
		}

		$settings = $this->get_settings($post_id);

		if ('1' !== $settings['enabled'] || '1' !== $settings['auto_display']) {
			return $content;
		}

		if (!$this->has_saved_settings($post_id) && !$this->is_editor_preview_context()) {
			return $content;
		}

		if (!$this->has_faq_items($settings) && !$this->is_editor_preview_context()) {
			return $content;
		}

		$section = $this->render_section($post_id, $settings, $this->is_editor_preview_context());
		return $section ? $content . $section : $content;
	}

	public function render_shortcode($atts = array(), $content = '', $tag = '')
	{
		$this->register_front_assets();
		$is_editor_preview = $this->is_editor_preview_context();

		$atts = shortcode_atts(
			array(
				'page_id' => 0,
			),
			$atts,
			$tag ? $tag : self::SHORTCODE
		);

		$post_id = absint($atts['page_id']);

		if (!$post_id) {
			$post_id = get_queried_object_id();
		}

		if (!$post_id && is_singular()) {
			$post_id = get_the_ID();
		}

		if (!$post_id) {
			return $is_editor_preview ? $this->render_section(0, $this->get_default_settings(), true) : '';
		}

		$settings = $this->get_settings($post_id);
		$has_saved_settings = $this->has_saved_settings($post_id);

		if (!$has_saved_settings) {
			return $is_editor_preview ? $this->render_section($post_id, $settings, true) : '';
		}

		if ('1' !== $settings['enabled']) {
			return $is_editor_preview ? $this->render_section($post_id, $settings, true) : '';
		}

		if (!$this->has_faq_items($settings)) {
			return $is_editor_preview ? $this->render_section($post_id, $settings, true) : '';
		}

		return $this->render_section($post_id, $settings, $is_editor_preview);
	}

	private function render_section($post_id, $settings, $force_placeholder = false)
	{
		$this->register_front_assets();

		$items = $settings['items'];
		$using_placeholders = false;

		if (empty($items) && !$force_placeholder) {
			return '';
		}

		if (empty($items) || $force_placeholder) {
			$items = $this->get_placeholder_items();
			$using_placeholders = true;
		}

		if (empty($items)) {
			return '';
		}

		$first_item_key = isset($items[0]['id']) ? $items[0]['id'] : '';
		$instance_id = 'ttfaq-' . ($post_id ? $post_id : 'preview') . '-' . wp_rand(100, 999);

		wp_enqueue_style('tt-page-faq');
		wp_enqueue_script('tt-page-faq');

		ob_start();
		?>
		<section class="ttfaq-section" id="<?php echo esc_attr($instance_id); ?>" data-ttfaq>
			<div class="ttfaq-shell">
				<div class="ttfaq-header">
					<h2 class="ttfaq-title"><?php echo esc_html($settings['title']); ?></h2>
					<?php if (!empty($settings['subtitle'])): ?>
						<p class="ttfaq-subtitle"><?php echo esc_html($settings['subtitle']); ?></p>
					<?php endif; ?>
				</div>

				<div class="ttfaq-list" data-ttfaq-list>
					<?php foreach ($items as $index => $item): ?>
						<?php
						$is_open = $item['id'] === $first_item_key;
						?>
						<article class="ttfaq-item<?php echo $is_open ? ' is-open' : ''; ?>" data-ttfaq-item>
							<button type="button" class="ttfaq-question" data-ttfaq-toggle
								aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
								<span class="ttfaq-question-text"><?php echo esc_html($item['question']); ?></span>
								<span class="ttfaq-question-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" focusable="false">
										<path d="M6 9l6 6 6-6" />
									</svg>
								</span>
							</button>
							<div class="ttfaq-answer" <?php echo $is_open ? '' : 'hidden'; ?>>
								<div class="ttfaq-answer-inner"><?php echo nl2br(esc_html($item['answer'])); ?></div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<div class="ttfaq-support">
					<div class="ttfaq-support-icon" aria-hidden="true">
						<img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/images/Quistion.png'); ?>" alt="" />
					</div>
					<div class="ttfaq-support-content">
						<h3 class="ttfaq-support-title"><?php echo esc_html($settings['empty_title']); ?></h3>
						<p class="ttfaq-support-text"><?php echo esc_html($settings['empty_text']); ?></p>
					</div>
					<div class="ttfaq-support-actions">
						<?php if (!empty($settings['contact_button_label'])): ?>
							<a class="ttfaq-button ttfaq-button--primary"
								href="<?php echo esc_url(!empty($settings['contact_button_url']) ? $settings['contact_button_url'] : '#'); ?>">
								<span class="ttfaq-button-icon" aria-hidden="true"><img
										src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/images/icon-line.svg'); ?>"
										alt="" /></span>
								<span><?php echo esc_html($settings['contact_button_label']); ?></span>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<?php if ($using_placeholders && $this->is_editor_preview_context()): ?>
					<p class="ttfaq-preview-note">
						<?php esc_html_e('กำลังแสดงตัวอย่าง FAQ เพราะหน้านี้ยังไม่ได้เพิ่มคำถามจริง', 'tumtook-page-faq'); ?>
					</p>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	private function get_placeholder_items()
	{
		return array(
			array(
				'id' => 'faq-1',
				'question' => __('ป้ายโรลอัพราคาเริ่มต้นเท่าไหร่?', 'tumtook-page-faq'),
				'answer' => __('ราคาเริ่มต้น 590 บาท ขึ้นอยู่กับขนาดและวัสดุที่เลือกใช้ พิมพ์ไว งานเสร็จพร้อมส่งภายใน 1-3 วันทำการ', 'tumtook-page-faq'),
			),
			array(
				'id' => 'faq-2',
				'question' => __('พิมพ์กี่วันได้รับสินค้า?', 'tumtook-page-faq'),
				'answer' => __('โดยทั่วไปใช้เวลาผลิต 1-3 วันทำการ และระยะเวลาจัดส่งขึ้นอยู่กับพื้นที่ปลายทาง', 'tumtook-page-faq'),
			),
			array(
				'id' => 'faq-3',
				'question' => __('ส่งต่างจังหวัดได้ไหม?', 'tumtook-page-faq'),
				'answer' => __('ได้ครับ เรามีบริการจัดส่งทั่วประเทศ พร้อมแจ้งเลขติดตามเมื่อสินค้าออกจากโรงงาน', 'tumtook-page-faq'),
			),
			array(
				'id' => 'faq-4',
				'question' => __('ไม่มีไฟล์งานสั่งได้ไหม?', 'tumtook-page-faq'),
				'answer' => __('สั่งได้ ทีมงานช่วยตรวจไฟล์และแนะนำรูปแบบที่เหมาะกับงานพิมพ์ให้ก่อนผลิต', 'tumtook-page-faq'),
			),
			array(
				'id' => 'faq-5',
				'question' => __('ต้องใช้ไฟล์แบบไหน?', 'tumtook-page-faq'),
				'answer' => __('แนะนำไฟล์ AI, PDF, PSD หรือภาพความละเอียดสูง หากไม่แน่ใจส่งมาให้ทีมงานช่วยเช็กได้เลย', 'tumtook-page-faq'),
			),
		);
	}

	private function is_editor_preview_context()
	{
		if (is_admin()) {
			return true;
		}

		if (class_exists('\Elementor\Plugin')) {
			$elementor = \Elementor\Plugin::$instance;
			if ($elementor && isset($elementor->editor, $elementor->preview) && ($elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode())) {
				return true;
			}
		}

		return false;
	}

}

new Tumtook_Page_FAQ();
