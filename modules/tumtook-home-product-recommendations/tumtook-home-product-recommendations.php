<?php
/**
 * Plugin Name: Tumtook Home Product Recommendations
 * Description: Displays repeatable, manually selected page-card sections on Home.
 * Version: 2.0.0
 * Author: Tumtook
 * Text Domain: tumtook-home-product-recommendations
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Home_Product_Recommendations
{
	const VERSION = '2.0.0';
	const META_KEY = '_tt_home_product_recommendations';
	const SHORTCODE = 'tumtook_home_recommended_products';
	const ASSET_HANDLE = 'tt-home-product-recommendations';
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
			'tt-home-product-recommendations',
			__('Tumtook Home — เลือกหน้าที่แสดง', 'tumtook-home-product-recommendations'),
			array($this, 'render_meta_box'),
			'page',
			'normal',
			'default'
		);
	}

	private function get_asset_version($path)
	{
		return function_exists('tumtook_aio_asset_version')
			? tumtook_aio_asset_version(__FILE__, $path, self::VERSION)
			: self::VERSION;
	}

	public function enqueue_admin_assets($hook)
	{
		$screen = get_current_screen();
		if (!in_array($hook, array('post.php', 'post-new.php'), true) || !$screen || 'page' !== $screen->post_type) {
			return;
		}

		wp_enqueue_style(self::ASSET_HANDLE . '-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), $this->get_asset_version('assets/css/admin.css'));
		wp_enqueue_script(self::ASSET_HANDLE . '-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array(), $this->get_asset_version('assets/js/admin.js'), true);
	}

	private function get_default_section($section_id = 'section-default')
	{
		return array(
			'id' => $section_id,
			'enabled' => '1',
			'title' => __('สินค้าแนะนำ', 'tumtook-home-product-recommendations'),
			'view_all_label' => __('ดูสินค้าทั้งหมด', 'tumtook-home-product-recommendations'),
			'view_all_url' => '',
			'button_label' => __('ดูรายละเอียด', 'tumtook-home-product-recommendations'),
			'limit' => '0',
			'page_ids' => array(),
		);
	}

	private function get_default_settings()
	{
		return array(
			'schema_version' => 2,
			'sections' => array($this->get_default_section()),
		);
	}

	private function parse_page_ids($values)
	{
		if (is_string($values)) {
			$values = preg_split('/[\s,]+/', $values);
		}

		$ids = array();
		foreach ((array) $values as $value) {
			if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
				continue;
			}
			$ids[] = (int) $value;
		}

		return array_values(array_unique($ids));
	}

	private function sanitize_section($raw, $fallback_id)
	{
		$settings = $this->get_default_section($fallback_id);
		foreach (array('title', 'view_all_label', 'view_all_url', 'button_label', 'limit') as $key) {
			if (isset($raw[$key]) && is_scalar($raw[$key])) {
				$settings[$key] = sanitize_text_field((string) $raw[$key]);
			}
		}
		$settings['enabled'] = isset($raw['enabled']) && is_scalar($raw['enabled']) && '1' === (string) $raw['enabled'] ? '1' : '0';
		$settings['view_all_url'] = esc_url_raw($settings['view_all_url']);
		$settings['limit'] = (string) max(0, min(99, (int) $settings['limit']));
		$settings['page_ids'] = $this->parse_page_ids(isset($raw['page_ids']) ? $raw['page_ids'] : array());
		$candidate_id = isset($raw['id']) && is_scalar($raw['id']) ? sanitize_key((string) $raw['id']) : '';
		$settings['id'] = $candidate_id ? $candidate_id : sanitize_key($fallback_id);
		return $settings;
	}

	private function sanitize_settings($raw)
	{
		if (!is_array($raw)) {
			return $this->get_default_settings();
		}

		// Migrate the first version, which stored one section directly in this meta value.
		if (!array_key_exists('sections', $raw)) {
			$raw = array('sections' => array($this->sanitize_section($raw, 'section-default')));
		}

		$sections = array();
		$used_ids = array();
		foreach ((array) $raw['sections'] as $index => $raw_section) {
			if (!is_array($raw_section)) {
				continue;
			}
			$fallback_id = is_string($index) ? $index : 'section-' . ($index + 1);
			$section = $this->sanitize_section($raw_section, $fallback_id);
			$base_id = $section['id'] ? $section['id'] : 'section-' . ($index + 1);
			$section_id = $base_id;
			$suffix = 2;
			while (isset($used_ids[$section_id])) {
				$section_id = $base_id . '-' . $suffix;
				$suffix++;
			}
			$section['id'] = $section_id;
			$used_ids[$section_id] = true;
			$sections[] = $section;
		}

		return array(
			'schema_version' => 2,
			'sections' => $sections,
		);
	}

	private function get_settings($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		if (!is_array($saved) || empty($saved)) {
			return $this->get_default_settings();
		}
		return $this->sanitize_settings($saved);
	}

	public function render_meta_box($post)
	{
		$settings = $this->get_settings($post->ID);
		$pages = get_posts(array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'has_password' => false,
			'post__not_in' => array($post->ID),
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		));
		wp_nonce_field('tt_home_product_recommendations_save', 'tt_home_product_recommendations_nonce');
		require __DIR__ . '/templates/admin.php';
	}

	private function render_page_select($pages, $section_key, $selected_id = 0)
	{
		$available_ids = array_map('intval', wp_list_pluck($pages, 'ID'));
		?>
		<div class="tthpr-admin-row" data-tthpr-row>
			<label class="tthpr-admin-page-label">
				<span><?php esc_html_e('หน้าที่แสดง', 'tumtook-home-product-recommendations'); ?> <span data-tthpr-number></span></span>
				<select name="tthpr_sections[<?php echo esc_attr($section_key); ?>][page_ids][]" data-tthpr-page-select>
					<option value=""><?php esc_html_e('— เลือกหน้า —', 'tumtook-home-product-recommendations'); ?></option>
					<?php if ($selected_id && !in_array((int) $selected_id, $available_ids, true)): ?>
						<option value="<?php echo esc_attr($selected_id); ?>" selected><?php echo esc_html(sprintf(__('หน้า #%d ไม่พร้อมแสดง — กรุณาเลือกใหม่หรือลบรายการ', 'tumtook-home-product-recommendations'), $selected_id)); ?></option>
					<?php endif; ?>
					<?php foreach ($pages as $page): ?>
						<option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected_id, $page->ID); ?>><?php echo esc_html(sprintf('%s (#%d)', get_the_title($page), $page->ID)); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="tthpr-admin-row-actions">
				<button type="button" class="button" data-tthpr-move="up" aria-label="<?php esc_attr_e('เลื่อนรายการขึ้น', 'tumtook-home-product-recommendations'); ?>">↑</button>
				<button type="button" class="button" data-tthpr-move="down" aria-label="<?php esc_attr_e('เลื่อนรายการลง', 'tumtook-home-product-recommendations'); ?>">↓</button>
				<button type="button" class="button button-link-delete" data-tthpr-remove><?php esc_html_e('ลบ', 'tumtook-home-product-recommendations'); ?></button>
			</div>
		</div>
		<?php
	}

	private function render_admin_section($pages, $section)
	{
		$section_key = $section['id'];
		require __DIR__ . '/templates/admin-section.php';
	}

	public function save_meta($post_id)
	{
		$nonce = isset($_POST['tt_home_product_recommendations_nonce']) ? $_POST['tt_home_product_recommendations_nonce'] : '';
		if (!is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'tt_home_product_recommendations_save')) {
			return;
		}
		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id) || 'page' !== get_post_type($post_id)) {
			return;
		}
		if (!isset($_POST['tthpr_sections']) || !is_array($_POST['tthpr_sections'])) {
			return;
		}

		$settings = $this->sanitize_settings(array('sections' => wp_unslash($_POST['tthpr_sections'])));
		foreach ($settings['sections'] as &$section) {
			$section['page_ids'] = $this->get_selected_page_ids($post_id, $section['page_ids']);
		}
		unset($section);
		update_post_meta($post_id, self::META_KEY, $settings);
	}

	private function get_selected_page_ids($post_id, $page_ids, $limit = 0)
	{
		$page_ids = array_values(array_diff($this->parse_page_ids($page_ids), array((int) $post_id)));
		// An empty post__in query would otherwise return unrelated pages.
		if (!$page_ids) {
			return array();
		}

		return get_posts(array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'has_password' => false,
			'post__in' => $page_ids,
			'orderby' => 'post__in',
			'posts_per_page' => $limit > 0 ? $limit : count($page_ids),
			'fields' => 'ids',
			'no_found_rows' => true,
			'ignore_sticky_posts' => true,
		));
	}

	private function first_card_meta($post_id, $keys)
	{
		foreach ($keys as $key) {
			$value = get_post_meta($post_id, $key, true);
			$value = is_scalar($value) ? sanitize_text_field((string) $value) : '';
			if ('' !== $value) {
				return $value;
			}
		}
		return '';
	}

	private function get_items($post_id, $settings)
	{
		$page_ids = $this->get_selected_page_ids($post_id, $settings['page_ids'], (int) $settings['limit']);
		if (!$page_ids) {
			return array();
		}
		_prime_post_caches($page_ids, false, true);
		$items = array();
		$badge_map = array(
			'new' => __('ใหม่', 'tumtook-home-product-recommendations'),
			'best' => __('ขายดี', 'tumtook-home-product-recommendations'),
			'recommended' => __('แนะนำ', 'tumtook-home-product-recommendations'),
		);

		foreach ($page_ids as $page_id) {
			$page = get_post($page_id);
			if (!$page || 'page' !== $page->post_type || 'publish' !== $page->post_status || '' !== $page->post_password) {
				continue;
			}
			// Reuse card data from the source pages without writing to their metadata.
			$title = $this->first_card_meta($page_id, array('_ttpr_page_card_title', '_ttpc_page_card_title'));
			$price = $this->first_card_meta($page_id, array('_ttpr_page_price', '_ttpc_page_price'));
			$badge = $this->first_card_meta($page_id, array('_ttpr_page_badge'));
			$image = '';
			foreach (array('_ttpr_page_image_id', '_ttpc_page_image_id') as $key) {
				$image_id = absint($this->first_card_meta($page_id, array($key)));
				$image = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
				if ($image) {
					break;
				}
			}

			$items[] = array(
				'title' => '' !== $title ? $title : get_the_title($page_id),
				'url' => get_permalink($page_id),
				'image' => $image ? $image : get_the_post_thumbnail_url($page_id, 'large'),
				'price' => $this->format_price($price),
				'badge' => isset($badge_map[$badge]) ? $badge_map[$badge] : '',
				'badge_type' => isset($badge_map[$badge]) ? $badge : '',
			);
		}
		return $items;
	}

	private function format_price($price)
	{
		$price = trim((string) $price);
		if ('' === $price || preg_match('/฿|บาท/u', $price)) {
			return $price;
		}
		$normalized = str_replace(',', '', $price);
		if (is_numeric($normalized)) {
			$number = (float) $normalized;
			return '฿' . number_format_i18n($number, floor($number) === $number ? 0 : 2);
		}
		return '฿' . $price;
	}

	private function is_editor_preview_context()
	{
		if (is_admin() || is_preview()) {
			return true;
		}
		if (class_exists('\\Elementor\\Plugin')) {
			$elementor = \Elementor\Plugin::$instance;
			return $elementor && isset($elementor->editor, $elementor->preview)
				&& ($elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode());
		}
		return false;
	}

	private function enqueue_front_assets()
	{
		if (function_exists('tumtook_aio_register_kanit_font')) {
			tumtook_aio_register_kanit_font(self::FONT_HANDLE);
		} elseif (!wp_style_is(self::FONT_HANDLE, 'registered')) {
			wp_register_style(self::FONT_HANDLE, 'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap', array(), null);
		}
		wp_enqueue_style(self::ASSET_HANDLE, plugin_dir_url(__FILE__) . 'assets/css/front.css', array(self::FONT_HANDLE), $this->get_asset_version('assets/css/front.css'));
		wp_enqueue_script(self::ASSET_HANDLE, plugin_dir_url(__FILE__) . 'assets/js/front.js', array(), $this->get_asset_version('assets/js/front.js'), true);
	}

	private function render_section($post_id, $settings)
	{
		$items = $this->get_items($post_id, $settings);
		if (!$items) {
			return '';
		}
		$instance_id = wp_unique_id('tthpr-');
		ob_start();
		require __DIR__ . '/templates/section.php';
		return ob_get_clean();
	}

	public function render_shortcode($atts = array())
	{
		$atts = shortcode_atts(array('page_id' => 0, 'section' => ''), $atts, self::SHORTCODE);
		$post_id = absint($atts['page_id']);
		if (!$post_id) {
			$post_id = get_queried_object_id();
		}
		if (!$post_id && is_singular('page')) {
			$post_id = get_the_ID();
		}
		$page = get_post($post_id);
		if (!$post_id || !$page || 'page' !== $page->post_type) {
			return '';
		}
		$is_preview = $this->is_editor_preview_context() && current_user_can('edit_post', $post_id);
		if (!$is_preview && (!is_post_publicly_viewable($page) || post_password_required($page))) {
			return '';
		}
		$settings = $this->get_settings($post_id);
		$requested_section = isset($atts['section']) && is_scalar($atts['section']) ? sanitize_key((string) $atts['section']) : '';
		$html = '';
		$matched_section = false;

		foreach ($settings['sections'] as $section) {
			if ($requested_section && $section['id'] !== $requested_section) {
				continue;
			}
			$matched_section = true;
			if ('1' !== $section['enabled']) {
				continue;
			}
			$html .= $this->render_section($post_id, $section);
		}

		if ('' !== $html) {
			$this->enqueue_front_assets();
			return $html;
		}

		if (!$is_preview) {
			return '';
		}
		$message = $requested_section && !$matched_section
			? sprintf(__('ไม่พบ Section รหัส %s', 'tumtook-home-product-recommendations'), $requested_section)
			: __('เพิ่ม Section และเลือกหน้าที่ต้องการแสดงในกล่อง Tumtook Home แล้วบันทึกหน้า', 'tumtook-home-product-recommendations');
		return '<p class="tthpr-preview-note">' . esc_html($message) . '</p>';
	}
}

new Tumtook_Home_Product_Recommendations();
