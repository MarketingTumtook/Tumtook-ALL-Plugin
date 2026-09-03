<?php
/**
 * Plugin Name: Tumtook Job Working Cards
 * Description: แสดง JetEngine Job Working posts เป็น card slider ด้วย shortcode [tumtook_job_working_cards] รองรับ post_type, taxonomy, category, category_id และ limit.
 * Version: 1.0.2
 * Author: Tumtook
 * Text Domain: tumtook-job-working-cards
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Job_Working_Cards
{
	const VERSION = '1.0.2';
	const SHORTCODE = 'tumtook_job_working_cards';
	const FONT_HANDLE = 'tumtook-kanit-font';
	const STYLE_HANDLE = 'tumtook-job-working-cards';
	const SCRIPT_HANDLE = 'tumtook-job-working-cards';

	public function __construct()
	{
		add_action('wp_ajax_ttwc_refresh_items', array($this, 'ajax_get_items'));
		add_action('wp_ajax_nopriv_ttwc_refresh_items', array($this, 'ajax_get_items'));
		add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
	}

	public function render_shortcode($atts = array())
	{
		$settings = $this->normalize_settings($atts);
		$post_type = $this->resolve_post_type($settings['post_type']);

		if ('' === $post_type) {
			return $this->is_editor_preview_context()
				? $this->render_message(__('ไม่พบ post type สำหรับ Job Working กรุณาระบุ post_type ใน shortcode', 'tumtook-job-working-cards'))
				: '';
		}

		$settings['post_type'] = $post_type;
		$items = $this->get_job_items($settings, true);

		if (empty($items)) {
			return $this->is_editor_preview_context()
				? $this->render_message(__('ยังไม่มีข้อมูล Job Working สำหรับแสดงผล', 'tumtook-job-working-cards'))
				: '';
		}

		$this->enqueue_front_assets();

		$instance_id = 'ttwc-' . wp_rand(1000, 9999);
		$enable_dynamic_refresh = !$this->is_editor_preview_context();
		$view_all_url = esc_url($settings['view_all_url']);

		ob_start();
		?>
		<section class="ttwc-section" data-ttwc-slider data-ttwc-dynamic="<?php echo $enable_dynamic_refresh ? '1' : '0'; ?>"
			data-ttwc-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-ttwc-action="ttwc_refresh_items"
			data-ttwc-post-type="<?php echo esc_attr($settings['post_type']); ?>"
			data-ttwc-limit="<?php echo esc_attr($settings['limit']); ?>"
			data-ttwc-taxonomy="<?php echo esc_attr($settings['taxonomy']); ?>"
			data-ttwc-category="<?php echo esc_attr($settings['category']); ?>"
			data-ttwc-category-id="<?php echo esc_attr($settings['category_id']); ?>"
			data-ttwc-info-meta="<?php echo esc_attr($settings['info_meta']); ?>"
			data-ttwc-badge-meta="<?php echo esc_attr($settings['badge_meta']); ?>"
			data-ttwc-image-meta="<?php echo esc_attr($settings['image_meta']); ?>"
			data-ttwc-orderby="<?php echo esc_attr($settings['orderby']); ?>"
			data-ttwc-include="<?php echo esc_attr($settings['include']); ?>"
			data-ttwc-exclude="<?php echo esc_attr($settings['exclude']); ?>" id="<?php echo esc_attr($instance_id); ?>">
			<div class="ttwc-shell">
				<div class="ttwc-header">
					<h2 class="ttwc-title"><?php echo esc_html($settings['title']); ?></h2>
					<?php if (!empty($settings['view_all_label']) && !empty($view_all_url)): ?>
						<a class="ttwc-view-all" href="<?php echo esc_url($view_all_url); ?>">
							<?php echo esc_html($settings['view_all_label']); ?>
							<span class="ttwc-view-all-icon" aria-hidden="true">&rsaquo;</span>
						</a>
					<?php endif; ?>
				</div>

				<div class="ttwc-track-wrap">
					<div class="ttwc-track" data-ttwc-track>
						<?php echo $this->render_card_items($items, $settings); ?>
					</div>
				</div>

				<div class="ttwc-controls">
					<div class="ttwc-pagination" data-ttwc-pagination></div>
					<div class="ttwc-arrows">
						<button type="button" class="ttwc-arrow ttwc-arrow--prev" data-ttwc-prev
							aria-label="<?php esc_attr_e('Previous cards', 'tumtook-job-working-cards'); ?>">
							<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
								<path d="M14.5 6.5L9 12l5.5 5.5" />
							</svg>
						</button>
						<button type="button" class="ttwc-arrow ttwc-arrow--next" data-ttwc-next
							aria-label="<?php esc_attr_e('Next cards', 'tumtook-job-working-cards'); ?>">
							<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
								<path d="M9.5 6.5L15 12l-5.5 5.5" />
							</svg>
						</button>
					</div>
				</div>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	public function ajax_get_items()
	{
		$settings = $this->normalize_settings($_REQUEST);
		$post_type = $this->resolve_post_type($settings['post_type']);

		$this->send_no_cache_headers();

		if ('' === $post_type) {
			wp_send_json_success(
				array(
					'enabled' => false,
					'html' => '',
					'count' => 0,
				)
			);
		}

		$settings['post_type'] = $post_type;
		$items = $this->get_job_items($settings, false);

		wp_send_json_success(
			array(
				'enabled' => true,
				'html' => $this->render_card_items($items, $settings),
				'count' => count($items),
			)
		);
	}

	private function normalize_settings($raw)
	{
		$raw = is_array($raw) ? wp_unslash($raw) : array();

		return array(
			'post_type' => isset($raw['post_type']) ? sanitize_key($raw['post_type']) : '',
			'limit' => (isset($raw['limit']) && '' !== trim((string) $raw['limit'])) ? absint($raw['limit']) : 0,
			'title' => isset($raw['title']) ? sanitize_text_field($raw['title']) : __('งานที่น่าสนใจ', 'tumtook-job-working-cards'),
			'view_all_label' => isset($raw['view_all_label']) ? sanitize_text_field($raw['view_all_label']) : __('ดูงานทั้งหมด', 'tumtook-job-working-cards'),
			'view_all_url' => isset($raw['view_all_url']) ? esc_url_raw($raw['view_all_url']) : '',
			'button_label' => isset($raw['button_label']) ? sanitize_text_field($raw['button_label']) : __('ดูรายละเอียด', 'tumtook-job-working-cards'),
			'taxonomy' => isset($raw['taxonomy']) ? sanitize_key($raw['taxonomy']) : '',
			'category' => isset($raw['category']) ? sanitize_text_field($raw['category']) : '',
			'category_id' => isset($raw['category_id']) ? sanitize_text_field($raw['category_id']) : '',
			'info_meta' => isset($raw['info_meta']) ? sanitize_key($raw['info_meta']) : '',
			'badge_meta' => isset($raw['badge_meta']) ? sanitize_key($raw['badge_meta']) : '',
			'image_meta' => isset($raw['image_meta']) ? sanitize_key($raw['image_meta']) : '',
			'orderby' => isset($raw['orderby']) ? sanitize_key($raw['orderby']) : 'rand',
			'include' => isset($raw['include']) ? sanitize_text_field($raw['include']) : '',
			'exclude' => isset($raw['exclude']) ? sanitize_text_field($raw['exclude']) : '',
		);
	}

	private function resolve_post_type($requested_post_type)
	{
		$candidates = array_filter(
			array_unique(
				array(
					$requested_post_type,
					'job-working',
					'job_working',
					'jobworking',
					'jobs',
					'job',
				)
			)
		);

		foreach ($candidates as $post_type) {
			if (post_type_exists($post_type)) {
				return $post_type;
			}
		}

		return '';
	}

	private function enqueue_front_assets()
	{
		$this->register_kanit_font();
		wp_enqueue_style(self::FONT_HANDLE);

		if (!wp_style_is(self::STYLE_HANDLE, 'registered')) {
			wp_register_style(
				self::STYLE_HANDLE,
				plugin_dir_url(__FILE__) . 'assets/css/front.css',
				array(self::FONT_HANDLE),
				$this->get_asset_version('assets/css/front.css')
			);
		}

		if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
			wp_register_script(
				self::SCRIPT_HANDLE,
				plugin_dir_url(__FILE__) . 'assets/js/front.js',
				array(),
				$this->get_asset_version('assets/js/front.js'),
				true
			);
		}

		wp_enqueue_style(self::STYLE_HANDLE);
		wp_enqueue_script(self::SCRIPT_HANDLE);
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

	private function get_job_items($settings, $use_cache = true)
	{
		$limit = absint($settings['limit']);
		$cache_key = 'ttwc_items_' . md5(wp_json_encode($settings) . '|' . self::VERSION);
		$cached = $use_cache ? get_transient($cache_key) : false;

		if (false !== $cached) {
			return $cached;
		}

		$args = array(
			'post_type' => $settings['post_type'],
			'post_status' => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'fields' => 'ids',
			'orderby' => in_array($settings['orderby'], array('date', 'title', 'menu_order', 'rand'), true) ? $settings['orderby'] : 'rand',
			'order' => 'rand' === $settings['orderby'] ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$include_ids = $this->parse_id_list($settings['include']);
		if (!empty($include_ids)) {
			$args['post__in'] = $include_ids;
			$args['orderby'] = 'post__in';
			$args['posts_per_page'] = $limit > 0 ? max($limit, count($include_ids)) : count($include_ids);
		}

		$exclude_ids = $this->parse_id_list($settings['exclude']);
		if (!empty($exclude_ids)) {
			$args['post__not_in'] = $exclude_ids;
		}

		$tax_query = $this->build_category_tax_query($settings);
		if (!empty($tax_query)) {
			$args['tax_query'] = $tax_query;
		}

		$query = new WP_Query($args);
		$post_ids = array_map('absint', $query->posts);

		if ('rand' === $settings['orderby'] && count($post_ids) > 1) {
			shuffle($post_ids);
		}

		if ($limit > 0) {
			$post_ids = array_slice($post_ids, 0, $limit);
		}

		if (function_exists('_prime_post_caches')) {
			_prime_post_caches($post_ids, true, true);
		} else {
			update_meta_cache('post', $post_ids);
			update_object_term_cache($post_ids, $settings['post_type']);
		}

		$items = array();
		foreach ($post_ids as $post_id) {
			$items[] = array(
				'title' => get_the_title($post_id),
				'url' => get_permalink($post_id),
				'image' => $this->get_card_image($post_id, $settings),
				'info' => $this->get_card_info($post_id, $settings),
				// 'badge' => $this->get_card_badge($post_id, $settings),
			);
		}

		wp_reset_postdata();

		if ($use_cache) {
			set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);
		}

		return $items;
	}

	private function render_card_items($items, $settings)
	{
		ob_start();

		foreach ($items as $item):
			?>
			<article class="ttwc-card" data-card-url="<?php echo esc_url($item['url']); ?>">
				<a class="ttwc-card-link" href="<?php echo esc_url($item['url']); ?>"
					aria-label="<?php echo esc_attr($item['title']); ?>" draggable="false"></a>
				<div class="ttwc-card-media">
					<div class="ttwc-image-link<?php echo empty($item['image']) ? ' ttwc-image-link--missing' : ''; ?>">
						<?php //if (!empty($item['badge'])): ?>
						<!-- <span class="ttwc-badge ttwc-badge--recommended"><?php //echo esc_html($item['badge']); ?></span> -->
						<?php // endif; ?>
						<?php if (!empty($item['image'])): ?>
							<img class="ttwc-image" src="<?php echo esc_url($item['image']); ?>"
								alt="<?php echo esc_attr($item['title']); ?>" loading="lazy" decoding="async"
								onerror="this.style.display='none';this.parentNode.classList.add('ttwc-image-link--missing');" />
						<?php endif; ?>
						<div class="ttwc-image ttwc-image--placeholder" aria-hidden="true">
							<div class="ttwc-image-fallback">
								<span class="ttwc-image-fallback-badge">NO IMAGE</span>
								<div class="ttwc-image-fallback-box"></div>
								<div class="ttwc-image-fallback-lines">
									<span></span>
									<span></span>
									<span></span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="ttwc-card-body">
					<div class="ttwc-content">
						<h3 class="ttwc-product-title"><?php echo esc_html($item['title']); ?></h3>
						<div class="ttwc-footer">
							<div class="ttwc-price"><?php echo esc_html($item['info']); ?></div>
							<a class="ttwc-button" href="<?php echo esc_url($item['url']); ?>">
								<span class="ttwc-button-arrow" aria-hidden="true"></span>
								<span class="ttwc-button-label"><?php echo esc_html($settings['button_label']); ?></span>
							</a>
						</div>
					</div>
				</div>
			</article>
			<?php
		endforeach;

		return ob_get_clean();
	}

	private function get_card_image($post_id, $settings)
	{
		if (!empty($settings['image_meta'])) {
			$image = $this->normalize_image_meta(get_post_meta($post_id, $settings['image_meta'], true));
			if ('' !== $image) {
				return $image;
			}
		}

		$image = get_the_post_thumbnail_url($post_id, 'large');
		if ($image) {
			return $image;
		}

		foreach (array('image', 'job_image', 'thumbnail', 'cover', 'cover_image') as $meta_key) {
			$image = $this->normalize_image_meta(get_post_meta($post_id, $meta_key, true));
			if ('' !== $image) {
				return $image;
			}
		}

		return '';
	}

	private function get_card_info($post_id, $settings)
	{
		if (!empty($settings['info_meta'])) {
			$value = $this->normalize_text_meta(get_post_meta($post_id, $settings['info_meta'], true));
			if ('' !== $value) {
				return $value;
			}
		}

		foreach (array('salary', 'job_salary', 'location', 'job_location', 'company', 'job_company') as $meta_key) {
			$value = $this->normalize_text_meta(get_post_meta($post_id, $meta_key, true));
			if ('' !== $value) {
				return $value;
			}
		}

		$excerpt = get_the_excerpt($post_id);
		return '' !== trim($excerpt) ? wp_trim_words(wp_strip_all_tags($excerpt), 10, '...') : __('รายละเอียดงาน', 'tumtook-job-working-cards');
	}

	private function get_card_badge($post_id, $settings)
	{
		if (!empty($settings['badge_meta'])) {
			$value = $this->normalize_text_meta(get_post_meta($post_id, $settings['badge_meta'], true));
			if ('' !== $value) {
				return $value;
			}
		}

		$taxonomy = $settings['taxonomy'];
		if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
			$taxonomy = $this->get_filter_taxonomy($settings);
		}

		if ('' === $taxonomy) {
			return '';
		}

		$terms = get_the_terms($post_id, $taxonomy);
		if (empty($terms) || is_wp_error($terms)) {
			return '';
		}

		return $terms[0]->name;
	}

	private function get_first_public_taxonomy($post_type)
	{
		$taxonomies = get_object_taxonomies($post_type, 'objects');

		foreach ($taxonomies as $taxonomy) {
			if ('post_format' === $taxonomy->name) {
				continue;
			}

			if (!empty($taxonomy->public) || !empty($taxonomy->show_ui)) {
				return $taxonomy->name;
			}
		}

		return '';
	}

	private function build_category_tax_query($settings)
	{
		if ('' === trim((string) $settings['category']) && '' === trim((string) $settings['category_id'])) {
			return array();
		}

		$taxonomy = $this->get_filter_taxonomy($settings);
		if ('' === $taxonomy) {
			return array();
		}

		$clauses = array();
		$term_ids = $this->parse_id_list($settings['category_id']);
		$category_tokens = $this->parse_text_list($settings['category']);
		$category_slugs = array();
		$category_names = array();

		foreach ($category_tokens as $token) {
			if (is_numeric($token)) {
				$term_ids[] = absint($token);
				continue;
			}

			$slug = sanitize_title($token);
			if ('' !== $slug) {
				$category_slugs[] = $slug;
			}

			$name = sanitize_text_field($token);
			if ('' !== $name) {
				$category_names[] = $name;
			}
		}

		$term_ids = array_values(array_unique(array_filter(array_map('absint', $term_ids))));
		$category_slugs = array_values(array_unique(array_filter($category_slugs)));
		$category_names = array_values(array_unique(array_filter($category_names)));

		if (!empty($term_ids)) {
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field' => 'term_id',
				'terms' => $term_ids,
			);
		}

		if (!empty($category_slugs)) {
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => $category_slugs,
			);
		}

		if (!empty($category_names)) {
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field' => 'name',
				'terms' => $category_names,
			);
		}

		if (count($clauses) > 1) {
			return array_merge(array('relation' => 'OR'), $clauses);
		}

		return $clauses;
	}

	private function get_filter_taxonomy($settings)
	{
		$post_type = $settings['post_type'];
		$requested_taxonomy = $settings['taxonomy'];

		if ('' !== $requested_taxonomy && taxonomy_exists($requested_taxonomy)) {
			return $requested_taxonomy;
		}

		if (taxonomy_exists('category') && is_object_in_taxonomy($post_type, 'category')) {
			return 'category';
		}

		return $this->get_first_public_taxonomy($post_type);
	}

	private function normalize_image_meta($value)
	{
		if (is_numeric($value)) {
			$image = wp_get_attachment_image_url(absint($value), 'large');
			return $image ? $image : '';
		}

		if (is_array($value)) {
			foreach (array('url', 'file', 'src', 'guid', 'id', 'ID') as $key) {
				if (isset($value[$key])) {
					$image = $this->normalize_image_meta($value[$key]);
					if ('' !== $image) {
						return $image;
					}
				}
			}

			return '';
		}

		$value = is_string($value) ? trim($value) : '';
		return filter_var($value, FILTER_VALIDATE_URL) ? esc_url_raw($value) : '';
	}

	private function normalize_text_meta($value)
	{
		if (is_array($value)) {
			$value = implode(', ', array_filter(array_map('strval', $value)));
		}

		return sanitize_text_field((string) $value);
	}

	private function parse_id_list($value)
	{
		$ids = preg_split('/[\s,|]+/', (string) $value);
		return array_values(array_filter(array_map('absint', $ids)));
	}

	private function parse_text_list($value)
	{
		$items = preg_split('/[\r\n,|]+/', (string) $value);
		return array_values(array_filter(array_map('trim', $items), 'strlen'));
	}

	private function render_message($message)
	{
		return '<div class="ttwc-section"><div class="ttwc-shell"><p>' . esc_html($message) . '</p></div></div>';
	}

	private function send_no_cache_headers()
	{
		if (!defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}

		nocache_headers();
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
		header('X-Tumtook-Dynamic: ttwc');
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

new Tumtook_Job_Working_Cards();
