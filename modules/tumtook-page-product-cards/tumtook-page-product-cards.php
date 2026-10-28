<?php
/**
 * Plugin Name: Tumtook Page Card Products  ทั้งหมด
 * Description: Adds a page-based product card slider with manual page selection and price support for Tumtook landing pages.
 * Version: 1.0.24
 * Author: Tumtook
 * Text Domain: tumtook-page-product-cards
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Page_Product_Cards
{
	const VERSION = '1.0.24';
	const META_KEY = '_tt_page_product_cards';
	const PAGE_IMAGE_META = '_ttpc_page_image_id';
	const PAGE_TITLE_META = '_ttpc_page_card_title';
	const PAGE_PRICE_META = '_ttpc_page_price';
	const CACHE_VERSION_OPTION = '_ttpc_cache_version';
	const SHORTCODE = 'tumtook_product_cards';
	const FONT_HANDLE = 'tumtook-kanit-font';

	private $rendered_posts = array();

	public function register_front_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'tt-page-product-cards',
			plugin_dir_url(__FILE__) . 'assets/css/front.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/front.css')
		);

		wp_register_script(
			'tt-page-product-cards',
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
			'title' => __('ต่อยอดไอเดียด้วยสินค้าอื่น ๆ', 'tumtook-page-product-cards'),
			'view_all_label' => __('สินค้าสร้างรายได้', 'tumtook-page-product-cards'),
			'view_all_url' => '',
			'button_label' => __('ดูสินค้า', 'tumtook-page-product-cards'),
			'limit' => '0',
			'related_page_ids' => '',
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
			return tumtook_aio_asset_version(__FILE__, $relative_path);
		}

		return '1.0.0';
	}

	private function get_settings($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		$settings = wp_parse_args(is_array($saved) ? $saved : array(), $this->get_default_settings());

		$settings['enabled'] = !empty($settings['enabled']) ? '1' : '0';
		$settings['auto_display'] = '0';
		$settings['title'] = sanitize_text_field($settings['title']);
		$settings['view_all_label'] = sanitize_text_field($settings['view_all_label']);
		$settings['view_all_url'] = esc_url_raw($settings['view_all_url']);
		$settings['button_label'] = sanitize_text_field($settings['button_label']);
		$settings['limit'] = (string) max(0, min(99, absint($settings['limit'])));
		$settings['related_page_ids'] = sanitize_text_field($settings['related_page_ids']);

		return $settings;
	}

	private function has_saved_settings($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		return is_array($saved) && !empty($saved);
	}

	private function get_page_card_meta($post_id)
	{
		$image_id = absint(get_post_meta($post_id, self::PAGE_IMAGE_META, true));

		return array(
			'image_id' => $image_id,
			'image' => $image_id ? wp_get_attachment_image_url($image_id, 'large') : '',
			'title' => sanitize_text_field((string) get_post_meta($post_id, self::PAGE_TITLE_META, true)),
			'price' => sanitize_text_field((string) get_post_meta($post_id, self::PAGE_PRICE_META, true)),
		);
	}

	public function append_to_content($content)
	{
		if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		$post_id = get_the_ID();
		$settings = $this->get_settings($post_id);

		if (!$this->has_saved_settings($post_id) || '1' !== $settings['enabled'] || '1' !== $settings['auto_display'] || in_array($post_id, $this->rendered_posts, true)) {
			return $content;
		}

		$section = $this->render_section($post_id, $settings);
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

		return $this->render_section($post_id, $settings, $is_editor_preview);
	}

	private function render_section($post_id, $settings, $force_placeholder = false)
	{
		$this->register_front_assets();

		$items = $this->get_recommended_pages($settings);
		$using_placeholders = false;

		if (empty($items)) {
			$items = $this->get_placeholder_items($settings);
			$using_placeholders = true;
		}

		$this->rendered_posts[] = $post_id;

		wp_enqueue_style('tt-page-product-cards');
		wp_enqueue_script('tt-page-product-cards');

		$instance_id = 'ttpc-' . ($post_id ? $post_id : 'preview') . '-' . wp_rand(100, 999);
		$show_controls = count($items) > 3;

		ob_start();
		?>
		<section class="ttpc-section" data-ttpc-slider id="<?php echo esc_attr($instance_id); ?>">
			<div class="ttpc-shell">
				<?php if (!empty($settings['title']) || !empty($settings['view_all_url'])): ?>
					<div class="ttpc-header">
						<?php if (!empty($settings['title'])): ?>
							<h2 class="ttpc-title"><?php echo esc_html($settings['title']); ?></h2>
						<?php endif; ?>
						<?php if (!empty($settings['view_all_url'])): ?>
							<a class="ttpc-view-all" href="<?php echo esc_url($settings['view_all_url']); ?>">
								<?php echo esc_html($settings['view_all_label']); ?>
								<span class="ttpc-view-all-icon" aria-hidden="true">&rsaquo;</span>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ($using_placeholders && $this->is_editor_preview_context()): ?>
					<div class="ttpc-preview-note">
						<?php esc_html_e('ตอนนี้ยังไม่มี page ที่ถูกเลือกไว้ จึงแสดงตัวอย่าง layout ให้ก่อน', 'tumtook-page-product-cards'); ?>
					</div>
				<?php endif; ?>

				<div class="ttpc-track-wrap">
					<div class="ttpc-track" data-ttpc-track>
							<?php foreach ($items as $item): ?>
								<article class="ttpc-card" data-card-url="<?php echo esc_url($item['url']); ?>">
									<a class="ttpc-card-link" href="<?php echo esc_url($item['url']); ?>"
										aria-label="<?php echo esc_attr($item['title']); ?>" draggable="false"></a>
								<div class="ttpc-image-link<?php echo empty($item['image']) ? ' ttpc-image-link--missing' : ''; ?>">
									<?php if (!empty($item['image'])): ?>
										<img class="ttpc-image" src="<?php echo esc_url($item['image']); ?>"
											alt="<?php echo esc_attr($item['title']); ?>" loading="lazy" decoding="async"
											onload="this.style.removeProperty('display');this.parentNode.classList.remove('ttpc-image-link--missing');"
											onerror="this.style.display='none';this.parentNode.classList.add('ttpc-image-link--missing');" />
									<?php endif; ?>
									<div class="ttpc-image ttpc-image--placeholder" aria-hidden="true">
										<div class="ttpc-image-fallback">
											<span class="ttpc-image-fallback-badge">NO IMAGE</span>
											<div class="ttpc-image-fallback-box"></div>
											<div class="ttpc-image-fallback-lines">
												<span></span>
												<span></span>
												<span></span>
											</div>
										</div>
									</div>
								</div>
								<div class="ttpc-content">
									<h3 class="ttpc-product-title"><?php echo esc_html($item['title']); ?></h3>
									<div class="ttpc-price"><?php echo esc_html($item['price']); ?></div>
									<div class="ttpc-footer">
										<a class="ttpc-button" href="<?php echo esc_url($item['url']); ?>">
											<span class="ttpc-button-arrow" aria-hidden="true"></span>
											<span
												class="ttpc-button-label"><?php echo esc_html($settings['button_label']); ?></span>
										</a>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ($show_controls): ?>
					<div class="ttpc-controls">
						<div class="ttpc-pagination" data-ttpc-pagination></div>
						<div class="ttpc-arrows">
							<button type="button" class="ttpc-arrow ttpc-arrow--prev" data-ttpc-prev
								aria-label="<?php esc_attr_e('Previous cards', 'tumtook-page-product-cards'); ?>">
								<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
									<path d="M14.5 6.5L9 12l5.5 5.5" />
								</svg>
							</button>
							<button type="button" class="ttpc-arrow ttpc-arrow--next" data-ttpc-next
								aria-label="<?php esc_attr_e('Next cards', 'tumtook-page-product-cards'); ?>">
								<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
									<path d="M9.5 6.5L15 12l-5.5 5.5" />
								</svg>
							</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	private function get_recommended_pages($settings)
	{
		$page_ids = $this->parse_id_list($settings['related_page_ids']);
		$limit = isset($settings['limit']) ? absint($settings['limit']) : 0;
		$cache_key = 'ttpc_pages_' . md5(wp_json_encode($settings) . '|' . $limit . '|' . self::VERSION . '|' . $this->get_cache_version());
		$cached = get_transient($cache_key);

		if (false !== $cached) {
			return $cached;
		}

		if ($limit > 0) {
			$page_ids = array_slice($page_ids, 0, $limit);
		}

		if (empty($page_ids)) {
			return array();
		}

		$this->prime_page_caches($page_ids);

		$items = array();

		foreach ($page_ids as $page_id) {
			$page = get_post($page_id);

			if (!$page || 'page' !== $page->post_type || 'publish' !== $page->post_status) {
				continue;
			}

			$meta = $this->get_page_card_meta($page_id);
			$image = $meta['image'] ? $meta['image'] : get_the_post_thumbnail_url($page_id, 'large');
			$title = '' !== $meta['title'] ? $meta['title'] : get_the_title($page_id);

			$items[] = array(
				'title' => $title,
				'url' => get_permalink($page_id),
				'image' => $image ? $image : '',
				'price' => $this->format_price($meta['price']),
			);
		}

		set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);

		return $items;
	}

	private function get_placeholder_items($settings)
	{
		$limit = max(2, min(6, (int) $settings['limit']));
		$items = array();

		for ($i = 1; $i <= $limit; $i++) {
			$items[] = array(
				'title' => sprintf(__('ตัวอย่างสินค้า %d', 'tumtook-page-product-cards'), $i),
				'url' => '#',
				'image' => '',
				'price' => $this->format_price(1 + ($i / 10)),
			);
		}

		return $items;
	}

	private function parse_id_list($raw_ids)
	{
		$ids = preg_split('/[\s,]+/', (string) $raw_ids);
		$ids = array_filter(array_map('absint', $ids));
		return array_values(array_unique($ids));
	}

	private function format_price($raw_price)
	{
		$raw_price = trim((string) $raw_price);

		if ('' === $raw_price) {
			return '฿0.00';
		}

		if (preg_match('/฿|บาท/u', $raw_price)) {
			return $raw_price;
		}

		$normalized = str_replace(',', '', $raw_price);

		if (is_numeric($normalized)) {
			$number = (float) $normalized;
			$decimals = (floor($number) === $number) ? 0 : 2;
			return '฿' . number_format_i18n($number, $decimals);
		}

		return '฿' . $raw_price;
	}

	private function prime_page_caches($page_ids)
	{
		$page_ids = array_values(array_filter(array_map('absint', (array) $page_ids)));
		if (empty($page_ids)) {
			return;
		}

		if (function_exists('_prime_post_caches')) {
			_prime_post_caches($page_ids, false, true);
			return;
		}

		update_meta_cache('post', $page_ids);
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

	private function get_cache_version()
	{
		return (string) max(1, absint(get_option(self::CACHE_VERSION_OPTION, 1)));
	}

	private function bump_cache_version()
	{
		$version = absint(get_option(self::CACHE_VERSION_OPTION, 1));
		$version = $version > 0 ? $version + 1 : 2;

		update_option(self::CACHE_VERSION_OPTION, $version, false);
	}
}

new Tumtook_Page_Product_Cards();
