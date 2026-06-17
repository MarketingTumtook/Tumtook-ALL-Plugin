<?php
/**
 * Plugin Name: Tumtook Page Article Recommendations
 * Description: Adds a random article slider section for Tumtook pages and posts with a layout tailored to article recommendations.
 * Version: 1.0.0
 * Author: Tumtook
 * Text Domain: tumtook-page-article-recommendations
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Page_Article_Recommendations
{
	const VERSION = '1.0.0';
	const META_KEY = '_tt_page_article_recommendations';
	const SHORTCODE = 'tumtook_recommended_articles';
	const FONT_HANDLE = 'tumtook-kanit-font';
	const CACHE_VERSION_OPTION = '_ttar_cache_version';

	private $rendered_posts = array();

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_box'));
		add_action('save_post', array($this, 'save_meta'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
		add_filter('the_content', array($this, 'append_to_content'));
	}

	public function enqueue_admin_assets($hook)
	{
		$screen = get_current_screen();

		if (('post.php' !== $hook && 'post-new.php' !== $hook) || !$screen || !in_array($screen->post_type, array('page', 'post'), true)) {
			return;
		}

		$this->enqueue_kanit_font();
	}

	public function register_meta_box()
	{
		foreach (array('page', 'post') as $post_type) {
			add_meta_box(
				'tt-page-article-recommendations',
				__('Recommended Articles', 'tumtook-page-article-recommendations'),
				array($this, 'render_meta_box'),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	public function register_front_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'tt-page-article-recommendations',
			plugin_dir_url(__FILE__) . 'assets/css/front.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/front.css')
		);

		wp_register_script(
			'tt-page-article-recommendations',
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
			'auto_display' => '1',
			'title' => __('บทความน่าสนใจ', 'tumtook-page-article-recommendations'),
			'view_all_label' => __('ดูบทความอื่นๆ', 'tumtook-page-article-recommendations'),
			'view_all_url' => 'https://www.tumtook.com/content',
			'button_label' => __('ดูบทความ', 'tumtook-page-article-recommendations'),
			'limit' => '10',
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
		$settings['auto_display'] = !empty($settings['auto_display']) ? '1' : '0';
		$settings['title'] = sanitize_text_field($settings['title']);
		$settings['view_all_label'] = sanitize_text_field($settings['view_all_label']);
		$settings['view_all_url'] = esc_url_raw($settings['view_all_url']);
		$settings['button_label'] = sanitize_text_field($settings['button_label']);
		$settings['limit'] = (string) max(1, min(10, absint($settings['limit'])));

		return $settings;
	}

	private function has_saved_settings($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		return is_array($saved) && !empty($saved);
	}

	public function render_meta_box($post)
	{
		$settings = $this->get_settings($post->ID);

		wp_nonce_field('tt_page_article_recommendations_save', 'tt_page_article_recommendations_nonce');
		?>
		<style>
			.ttar-admin-wrap,
			.ttar-admin-wrap button,
			.ttar-admin-wrap input,
			.ttar-admin-wrap select,
			.ttar-admin-wrap textarea {
				font-family: "Kanit", sans-serif
			}

			.ttar-admin-wrap {
				display: grid;
				gap: 18px
			}

			.ttar-admin-note {
				margin: 0;
				color: #50575e
			}

			.ttar-admin-grid {
				display: grid;
				grid-template-columns: repeat(2, minmax(320px, 1fr));
				column-gap: 16px;
				row-gap: 14px;
				align-items: start
			}

			.ttar-admin-field {
				display: grid;
				gap: 6px
			}

			.ttar-admin-field label {
				font-weight: 600
			}

			.ttar-admin-field input {
				width: 100%;
				max-width: none;
				box-sizing: border-box
			}

			.ttar-admin-checklist {
				display: grid;
				gap: 8px
			}

			.ttar-admin-hint {
				margin: 0;
				color: #646970;
				font-size: 12px;
				line-height: 1.35
			}

			.ttar-admin-panel {
				border: 1px solid #dcdcde;
				border-radius: 16px;
				padding: 16px;
				background: #fff
			}

			.ttar-admin-panel h3 {
				margin: 0 0 12px;
				font-size: 15px
			}

			@media (max-width:782px) {
				.ttar-admin-grid {
					grid-template-columns: 1fr
				}
			}
		</style>
		<div class="ttar-admin-wrap">
			<div class="ttar-admin-panel">
				<h3>
					<?php esc_html_e('Section บทความแนะนำ', 'tumtook-page-article-recommendations'); ?>
				</h3>
				<p class="ttar-admin-note">
					<?php esc_html_e('ปลั๊กอินนี้จะสุ่มบทความที่เผยแพร่แล้วมาแสดงเป็นสไลด์ โดยจำกัดสูงสุด 10 รายการ', 'tumtook-page-article-recommendations'); ?>
				</p>

				<div class="ttar-admin-checklist" style="margin-top:16px">
					<label><input type="checkbox" name="ttar_settings[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?> />
						<?php esc_html_e('เปิดใช้งาน section นี้', 'tumtook-page-article-recommendations'); ?>
					</label>
					<label><input type="checkbox" name="ttar_settings[auto_display]" value="1" <?php checked($settings['auto_display'], '1'); ?> />
						<?php esc_html_e('แสดงท้ายเนื้อหาอัตโนมัติ', 'tumtook-page-article-recommendations'); ?>
					</label>
				</div>

				<div class="ttar-admin-grid" style="margin-top:16px">
					<div class="ttar-admin-field">
						<label for="ttar-title">
							<?php esc_html_e('หัวข้อ', 'tumtook-page-article-recommendations'); ?>
						</label>
						<input id="ttar-title" type="text" name="ttar_settings[title]"
							value="<?php echo esc_attr($settings['title']); ?>" />
					</div>
					<div class="ttar-admin-field">
						<label for="ttar-limit">
							<?php esc_html_e('จำนวนสไลด์', 'tumtook-page-article-recommendations'); ?>
						</label>
						<input id="ttar-limit" type="number" min="1" max="10" name="ttar_settings[limit]"
							value="<?php echo esc_attr($settings['limit']); ?>" />
						<p class="ttar-admin-hint">
							<?php esc_html_e('ระบบจะสุ่มบทความมาแสดง และจำกัดได้สูงสุด 10 รายการต่อ section', 'tumtook-page-article-recommendations'); ?>
						</p>
					</div>
					<div class="ttar-admin-field">
						<label for="ttar-view-all-label">
							<?php esc_html_e('ข้อความลิงก์ทั้งหมด', 'tumtook-page-article-recommendations'); ?>
						</label>
						<input id="ttar-view-all-label" type="text" name="ttar_settings[view_all_label]"
							value="<?php echo esc_attr($settings['view_all_label']); ?>" />
					</div>
					<div class="ttar-admin-field">
						<label for="ttar-view-all-url">
							<?php esc_html_e('ลิงก์ทั้งหมด', 'tumtook-page-article-recommendations'); ?>
						</label>
						<input id="ttar-view-all-url" type="url" name="ttar_settings[view_all_url]"
							value="<?php echo esc_attr($settings['view_all_url']); ?>" />
					</div>
					<div class="ttar-admin-field">
						<label for="ttar-button-label">
							<?php esc_html_e('ข้อความปุ่ม', 'tumtook-page-article-recommendations'); ?>
						</label>
						<input id="ttar-button-label" type="text" name="ttar_settings[button_label]"
							value="<?php echo esc_attr($settings['button_label']); ?>" />
					</div>
				</div>

				<p class="ttar-admin-note" style="margin-top:16px">
					<?php esc_html_e('Shortcode ที่ใช้ได้: [tumtook_recommended_articles]', 'tumtook-page-article-recommendations'); ?>
				</p>
			</div>
		</div>
		<?php
	}

	public function save_meta($post_id)
	{
		$post_type = get_post_type($post_id);

		if (!in_array($post_type, array('page', 'post'), true)) {
			return;
		}

		if (!isset($_POST['tt_page_article_recommendations_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tt_page_article_recommendations_nonce'])), 'tt_page_article_recommendations_save')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$raw = isset($_POST['ttar_settings']) ? wp_unslash($_POST['ttar_settings']) : array();
		if (!is_array($raw)) {
			$raw = array();
		}

		$settings = $this->get_default_settings();
		$settings['enabled'] = !empty($raw['enabled']) ? '1' : '0';
		$settings['auto_display'] = !empty($raw['auto_display']) ? '1' : '0';
		$settings['title'] = isset($raw['title']) ? sanitize_text_field($raw['title']) : $settings['title'];
		$settings['view_all_label'] = isset($raw['view_all_label']) ? sanitize_text_field($raw['view_all_label']) : $settings['view_all_label'];
		$settings['view_all_url'] = isset($raw['view_all_url']) ? esc_url_raw($raw['view_all_url']) : '';
		$settings['button_label'] = isset($raw['button_label']) ? sanitize_text_field($raw['button_label']) : $settings['button_label'];
		$settings['limit'] = (string) max(1, min(10, absint(isset($raw['limit']) ? $raw['limit'] : 10)));

		update_post_meta($post_id, self::META_KEY, $settings);
		$this->bump_cache_version();
	}

	public function append_to_content($content)
	{
		if (!is_singular(array('page', 'post')) || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		try {
			$post_id = get_the_ID();
			$settings = $this->get_settings($post_id);

			if (!$this->has_saved_settings($post_id) || '1' !== $settings['enabled'] || '1' !== $settings['auto_display'] || in_array($post_id, $this->rendered_posts, true)) {
				return $content;
			}

			$section = $this->render_section($post_id, $settings);
			return $section ? $content . $section : $content;
		} catch (Throwable $e) {
			error_log('[Tumtook Page Article Recommendations] append_to_content failed: ' . $e->getMessage());
			return $content;
		}
	}

	public function render_shortcode($atts = array(), $content = '', $tag = '')
	{
		try {
			$this->register_front_assets();

			$is_editor_preview = $this->is_editor_preview_context();

			$atts = shortcode_atts(
				array(
					'post_id' => 0,
					'limit' => 0,
				),
				$atts,
				$tag ? $tag : self::SHORTCODE
			);

			$post_id = absint($atts['post_id']);
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

			if (absint($atts['limit']) > 0) {
				$settings['limit'] = (string) max(1, min(10, absint($atts['limit'])));
			}

			if (!$this->has_saved_settings($post_id)) {
				return $is_editor_preview ? $this->render_section($post_id, $settings, true) : '';
			}

			if ('1' !== $settings['enabled']) {
				return $is_editor_preview ? $this->render_section($post_id, $settings, true) : '';
			}

			return $this->render_section($post_id, $settings, $is_editor_preview);
		} catch (Throwable $e) {
			error_log('[Tumtook Page Article Recommendations] render_shortcode failed: ' . $e->getMessage());
			return '';
		}
	}

	private function render_section($post_id, $settings, $force_placeholder = false)
	{
		try {
			$this->register_front_assets();

			$items = $this->get_recommended_articles($post_id, $settings);
			$using_placeholders = false;

				if (empty($items)) {
					$items = $this->get_placeholder_items($settings);
					$using_placeholders = true;
				}

			$this->rendered_posts[] = $post_id;

			wp_enqueue_style('tt-page-article-recommendations');
			wp_enqueue_script('tt-page-article-recommendations');

			$instance_id = 'ttar-' . ($post_id ? $post_id : 'preview') . '-' . wp_rand(100, 999);
			$view_all_url = $this->get_view_all_url($settings);

			ob_start();
			?>
			<section class="ttar-section" data-ttar-slider id="<?php echo esc_attr($instance_id); ?>">
				<div class="ttar-shell">
				<div class="ttar-header">
					<h2 class="ttar-title">
						<?php echo esc_html($settings['title']); ?>
					</h2>
					<?php if (!empty($settings['view_all_label']) && !empty($view_all_url)): ?>
						<a class="ttar-view-all" href="<?php echo esc_url($view_all_url); ?>">
							<?php echo esc_html($settings['view_all_label']); ?>
							<span class="ttar-view-all-icon" aria-hidden="true">&rsaquo;</span>
						</a>
					<?php endif; ?>
				</div>

				<?php if ($using_placeholders && $this->is_editor_preview_context()): ?>
					<div class="ttar-preview-note">
						<?php esc_html_e('ตอนนี้ยังไม่มีบทความให้ดึง จึงแสดงตัวอย่าง layout ให้ก่อน', 'tumtook-page-article-recommendations'); ?>
					</div>
				<?php endif; ?>

				<div class="ttar-track-wrap">
					<div class="ttar-track" data-ttar-track>
						<?php foreach ($items as $item): ?>
							<article class="ttar-card">
								<a class="ttar-image-link<?php echo empty($item['image']) ? ' ttar-image-link--missing' : ''; ?>"
									href="<?php echo esc_url($item['url']); ?>">
									<?php if (!empty($item['badge'])): ?>
										<span class="ttar-badge"
											style="<?php echo esc_attr($this->get_badge_style($item['badge'])); ?>">
											<?php echo esc_html($item['badge']); ?>
										</span>
									<?php endif; ?>
									<?php if (!empty($item['image'])): ?>
										<img class="ttar-image" src="<?php echo esc_url($item['image']); ?>"
											alt="<?php echo esc_attr($item['title']); ?>" loading="lazy"
											onerror="this.style.display='none';this.parentNode.classList.add('ttar-image-link--missing');" />
									<?php endif; ?>
									<div class="ttar-image ttar-image--placeholder" aria-hidden="true">
										<div class="ttar-image-fallback">
											<span class="ttar-image-fallback-badge">NO IMAGE</span>
											<div class="ttar-image-fallback-box"></div>
											<div class="ttar-image-fallback-lines">
												<span></span>
												<span></span>
												<span></span>
											</div>
										</div>
									</div>
								</a>
								<div class="ttar-content">
									<h3 class="ttar-article-title">
										<?php echo esc_html($item['title']); ?>
									</h3>
									<a class="ttar-button" href="<?php echo esc_url($item['url']); ?>">
										<span class="ttar-button-arrow" aria-hidden="true">
											<svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
												<path d="M5 15L15 5M7 5h8v8" />
											</svg>
										</span>
										<span class="ttar-button-label">
											<?php echo esc_html($settings['button_label']); ?>
										</span>
									</a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="ttar-controls">
					<div class="ttar-pagination" data-ttar-pagination></div>
					<div class="ttar-arrows">
						<button type="button" class="ttar-arrow ttar-arrow--prev" data-ttar-prev
							aria-label="<?php esc_attr_e('Previous articles', 'tumtook-page-article-recommendations'); ?>">
							<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
								<path d="M14.5 6.5L9 12l5.5 5.5" />
							</svg>
						</button>
						<button type="button" class="ttar-arrow ttar-arrow--next" data-ttar-next
							aria-label="<?php esc_attr_e('Next articles', 'tumtook-page-article-recommendations'); ?>">
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
		} catch (Throwable $e) {
			error_log('[Tumtook Page Article Recommendations] render_section failed: ' . $e->getMessage());
			return '';
		}
	}

	private function get_recommended_articles($post_id, $settings)
	{
		try {
			$limit = max(1, min(10, absint($settings['limit'])));
			$exclude_ids = array();
			$cache_key = 'ttar_articles_' . md5($post_id . '|' . $limit . '|' . wp_json_encode($settings) . '|' . self::VERSION . '|' . $this->get_cache_version());
			$cached = get_transient($cache_key);

			if (false !== $cached) {
				return $cached;
			}

			if ('post' === get_post_type($post_id)) {
				$exclude_ids[] = $post_id;
			}

			$query = new WP_Query(
				array(
					'post_type' => 'post',
					'post_status' => 'publish',
					'posts_per_page' => $limit,
					'orderby' => 'rand',
					'post__not_in' => $exclude_ids,
					'ignore_sticky_posts' => true,
					'no_found_rows' => true,
				)
			);

			if (!$query->have_posts()) {
				return array();
			}

			$items = array();

			foreach ($query->posts as $article) {
				$category = $this->get_primary_category_name($article->ID);
				$image = get_the_post_thumbnail_url($article->ID, 'large');

				$items[] = array(
					'title' => get_the_title($article->ID),
					'url' => get_permalink($article->ID),
					'image' => $image ? $image : '',
					'badge' => $category,
				);
			}

			wp_reset_postdata();

			set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);

			return $items;
		} catch (Throwable $e) {
			error_log('[Tumtook Page Article Recommendations] get_recommended_articles failed: ' . $e->getMessage());
			return array();
		}
	}

	private function get_primary_category_name($post_id)
	{
		$categories = get_the_category($post_id);

		if (empty($categories) || !is_array($categories)) {
			return '';
		}

		return $categories[0]->name;
	}

	private function get_placeholder_items($settings)
	{
		$limit = max(2, min(4, (int) $settings['limit']));
		$items = array();

		for ($i = 1; $i <= $limit; $i++) {
			$items[] = array(
				'title' => sprintf(__('ตัวอย่างบทความ %d', 'tumtook-page-article-recommendations'), $i),
				'url' => '#',
				'image' => '',
				'badge' => 1 === $i ? __('คู่มือ', 'tumtook-page-article-recommendations') : __('Marketing', 'tumtook-page-article-recommendations'),
			);
		}

		return $items;
	}

	private function get_view_all_url($settings)
	{
		if (!empty($settings['view_all_url'])) {
			return esc_url_raw($settings['view_all_url']);
		}

		$posts_page_id = (int) get_option('page_for_posts');
		if ($posts_page_id > 0) {
			$posts_page_url = get_permalink($posts_page_id);
			if ($posts_page_url) {
				return $posts_page_url;
			}
		}

		return home_url('/');
	}

	private function get_badge_style($badge)
	{
		$badge = trim((string) $badge);

		if ('' === $badge) {
			return '';
		}

		$palette = array(
			'#35b879',
			'#4c7df0',
			'#ff7a45',
			'#8b5cf6',
			'#ec4899',
			'#f59e0b',
			'#ef4444',
			'#3977cfff',
		);

		$hash = abs(crc32(wp_strip_all_tags($badge)));
		$color = $palette[$hash % count($palette)];

		return 'background-color:' . $color . ';color:#ffffff;';
	}

	private function is_editor_preview_context()
	{
		if (is_admin()) {
			return true;
		}

		if (!class_exists('\Elementor\Plugin')) {
			return false;
		}

		$elementor = isset(\Elementor\Plugin::$instance) ? \Elementor\Plugin::$instance : null;
		if (!$elementor || !is_object($elementor)) {
			return false;
		}

		$editor = isset($elementor->editor) ? $elementor->editor : null;
		$preview = isset($elementor->preview) ? $elementor->preview : null;

		if (
			is_object($editor) &&
			is_object($preview) &&
			method_exists($editor, 'is_edit_mode') &&
			method_exists($preview, 'is_preview_mode') &&
			($editor->is_edit_mode() || $preview->is_preview_mode())
		) {
			return true;
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

new Tumtook_Page_Article_Recommendations();
