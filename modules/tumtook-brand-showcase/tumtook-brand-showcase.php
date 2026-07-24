<?php
/**
 * Plugin Name: Tumtook brand showCase
 * Description: Add up to 6 page-level brand showcase images and display them in a looping slider.
 * Version: 1.0.21
 * Author: Tumtook
 * Text Domain: tumtook-brand-showcase
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Brand_Showcase_Plugin
{
	const META_KEY = '_tumtook_brand_showcase_data';
	const SHORTCODE = 'tumtook_brand_showcase';
	const VERSION = '1.0.21';
	const FONT_HANDLE = 'tumtook-kanit-font';
	const SLIDE_COUNT = 6;

	private $rendered_page_ids = array();

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post_page', array($this, 'save_page_meta'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
		add_shortcode('tumtook_brand_showCase', array($this, 'render_shortcode'));
	}

	public function register_meta_boxes()
	{
		add_meta_box(
			'tumtook-brand-showcase-page',
			__('Tumtook Brand Showcase', 'tumtook-brand-showcase'),
			array($this, 'render_page_meta_box'),
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

		wp_enqueue_media();
		wp_enqueue_script(
			'tumtook-brand-showcase-admin',
			plugin_dir_url(__FILE__) . 'assets/js/admin.js',
			array('jquery'),
			$this->get_asset_version('assets/js/admin.js'),
			true
		);
	}

	public function register_front_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'tumtook-brand-showcase',
			plugin_dir_url(__FILE__) . 'assets/css/tumtook-brand-showcase.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/tumtook-brand-showcase.css')
		);

		wp_register_script(
			'tumtook-brand-showcase',
			plugin_dir_url(__FILE__) . 'assets/js/tumtook-brand-showcase.js',
			array(),
			$this->get_asset_version('assets/js/tumtook-brand-showcase.js'),
			true
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

	private function get_default_slide()
	{
		return array(
			'image_id' => 0,
			'image_url' => '',
			'alt' => '',
			'link_url' => '',
		);
	}

	private function get_default_data()
	{
		return array(
			'title' => __('ไอเดียจากแบรนด์จริง', 'tumtook-brand-showcase'),
			'subtitle' => __('แบรนด์แอสเซ็ทไอเดียภาพที่ปรับเครื่องมือ Tumtook', 'tumtook-brand-showcase'),
			'view_all_label' => __('ดูทั้งหมด', 'tumtook-brand-showcase'),
			'view_all_url' => '',
			'slides' => array_fill(0, self::SLIDE_COUNT, $this->get_default_slide()),
		);
	}

	private function normalize_media_url($attachment_id, $fallback_url = '')
	{
		$attachment_id = absint($attachment_id);
		$uploads = wp_get_upload_dir();

		if ($attachment_id) {
			$attachment_url = wp_get_attachment_url($attachment_id);

			if ($attachment_url) {
				return $attachment_url;
			}

			$attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);

			if ($attached_file && !empty($uploads['baseurl'])) {
				return trailingslashit($uploads['baseurl']) . ltrim($attached_file, '/');
			}
		}

		return esc_url_raw($fallback_url);
	}

	private function get_page_data($post_id)
	{
		$saved = get_post_meta($post_id, self::META_KEY, true);
		$defaults = $this->get_default_data();
		$data = wp_parse_args(is_array($saved) ? $saved : array(), $defaults);

		$data['title'] = sanitize_text_field($data['title']);
		$data['subtitle'] = sanitize_text_field($data['subtitle']);
		$data['view_all_label'] = sanitize_text_field(isset($data['view_all_label']) ? $data['view_all_label'] : $defaults['view_all_label']);
		$data['view_all_url'] = esc_url_raw(isset($data['view_all_url']) ? $data['view_all_url'] : '');
		$data['slides'] = isset($data['slides']) && is_array($data['slides']) ? array_values($data['slides']) : array();

		for ($index = 0; $index < self::SLIDE_COUNT; $index++) {
			$slide = isset($data['slides'][$index]) && is_array($data['slides'][$index]) ? $data['slides'][$index] : array();
			$slide = wp_parse_args($slide, $this->get_default_slide());

			$data['slides'][$index] = array(
				'image_id' => absint($slide['image_id']),
				'image_url' => $this->normalize_media_url($slide['image_id'], $slide['image_url']),
				'alt' => sanitize_text_field($slide['alt']),
				'link_url' => esc_url_raw($slide['link_url']),
			);
		}

		return $data;
	}

	private function get_slides_for_page($post_id)
	{
		$data = $this->get_page_data($post_id);
		$slides = array();

		foreach ($data['slides'] as $slide) {
			if (empty($slide['image_url'])) {
				continue;
			}

			$slides[] = array(
				'image_url' => $slide['image_url'],
				'alt' => $slide['alt'],
				'link_url' => $slide['link_url'],
			);
		}

		return $slides;
	}

	private function render_media_picker_field($args)
	{
		$args = wp_parse_args(
			$args,
			array(
				'label' => '',
				'id_name' => '',
				'url_name' => '',
				'alt_name' => '',
				'link_name' => '',
				'id_value' => '',
				'url_value' => '',
				'alt_value' => '',
				'link_value' => '',
			)
		);
		?>
		<div class="ttbs-admin-field ttbs-admin-field--full">
			<label><?php echo esc_html($args['label']); ?></label>
			<div class="ttbs-admin-media">
				<div class="ttbs-admin-media__inputs">
					<input type="hidden" class="ttbs-media-id" name="<?php echo esc_attr($args['id_name']); ?>"
						value="<?php echo esc_attr($args['id_value']); ?>" />
					<input type="url" class="ttbs-media-url" name="<?php echo esc_attr($args['url_name']); ?>"
						value="<?php echo esc_url($args['url_value']); ?>"
						placeholder="<?php esc_attr_e('Upload or paste image URL', 'tumtook-brand-showcase'); ?>" />
					<input type="text" name="<?php echo esc_attr($args['alt_name']); ?>"
						value="<?php echo esc_attr($args['alt_value']); ?>"
						placeholder="<?php esc_attr_e('Image alt text (optional)', 'tumtook-brand-showcase'); ?>" />
					<input type="url" name="<?php echo esc_attr($args['link_name']); ?>"
						value="<?php echo esc_url($args['link_value']); ?>"
						placeholder="<?php esc_attr_e('Card link URL (optional)', 'tumtook-brand-showcase'); ?>" />
				</div>
				<div class="ttbs-admin-media__actions">
					<button type="button"
						class="button button-secondary ttbs-upload"><?php esc_html_e('Select Image', 'tumtook-brand-showcase'); ?></button>
					<button type="button"
						class="button button-link-delete ttbs-clear"><?php esc_html_e('Clear', 'tumtook-brand-showcase'); ?></button>
				</div>
			</div>
			<div class="ttbs-admin-preview">
				<img class="ttbs-image-preview" src="<?php echo esc_url($args['url_value']); ?>" alt="" <?php echo empty($args['url_value']) ? 'style="display:none"' : ''; ?> />
			</div>
		</div>
		<?php
	}

	public function render_page_meta_box($post)
	{
		$data = $this->get_page_data($post->ID);

		wp_nonce_field('tumtook_brand_showcase_save', 'tumtook_brand_showcase_nonce');
		?>
		<style>
			.ttbs-admin-wrap {
				display: grid;
				gap: 18px
			}

			.ttbs-admin-intro {
				margin: 0;
				color: #50575e
			}

			.ttbs-admin-section {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 18px;
				padding: 18px
			}

			.ttbs-admin-title {
				margin: 0 0 14px;
				font-size: 15px;
				font-weight: 700
			}

			.ttbs-admin-grid {
				display: grid;
				gap: 16px;
				grid-template-columns: repeat(2, minmax(0, 1fr))
			}

			.ttbs-admin-header-fields {
				display: grid;
				gap: 12px;
				margin-bottom: 18px
			}

			.ttbs-admin-slides {
				display: grid;
				gap: 16px;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				align-items: start
			}

			.ttbs-admin-slide-card {
				border: 1px solid #e2e4e7;
				border-radius: 16px;
				padding: 14px;
				background: #fcfcfd
			}

			.ttbs-admin-slide-card h4 {
				margin: 0 0 10px;
				font-size: 14px;
				font-weight: 700
			}

			.ttbs-admin-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 6px
			}

			.ttbs-admin-field input,
			.ttbs-admin-field textarea {
				width: 100%
			}

			.ttbs-admin-field--full {
				grid-column: 1/-1
			}

			.ttbs-admin-media {
				display: grid;
				grid-template-columns: minmax(0, 1fr) auto;
				gap: 10px;
				align-items: start
			}

			.ttbs-admin-media__inputs {
				display: grid;
				gap: 10px
			}

			.ttbs-admin-media__actions {
				display: grid;
				gap: 8px;
				justify-items: start
			}

			.ttbs-admin-preview {
				margin-top: 10px
			}

			.ttbs-admin-preview img {
				width: 100%;
				max-width: 100%;
				height: 140px;
				object-fit: cover;
				border-radius: 14px;
				display: block;
				box-shadow: 0 8px 22px rgba(0, 0, 0, .08)
			}

			@media (max-width: 782px) {

				.ttbs-admin-grid,
				.ttbs-admin-slides {
					grid-template-columns: 1fr
				}

				.ttbs-admin-field--full {
					grid-column: auto
				}

				.ttbs-admin-media {
					grid-template-columns: 1fr
				}
			}
		</style>
		<div class="ttbs-admin-wrap">
			<p class="ttbs-admin-intro">
				<?php esc_html_e('Upload brand showcase images one by one from the media library. You can add up to 6 images for this page.', 'tumtook-brand-showcase'); ?>
			</p>

			<section class="ttbs-admin-section">
				<h3 class="ttbs-admin-title"><?php esc_html_e('Tumtook Brand Showcase', 'tumtook-brand-showcase'); ?></h3>
				<div class="ttbs-admin-header-fields">
					<div class="ttbs-admin-field">
						<label for="ttbs-title"><?php esc_html_e('Header title', 'tumtook-brand-showcase'); ?></label>
						<input id="ttbs-title" type="text" name="tumtook_brand_showcase_data[title]"
							value="<?php echo esc_attr($data['title']); ?>" />
					</div>
					<div class="ttbs-admin-grid">
						<div class="ttbs-admin-field">
							<label
								for="ttbs-view-all-label"><?php esc_html_e('Right link label', 'tumtook-brand-showcase'); ?></label>
							<input id="ttbs-view-all-label" type="text" name="tumtook_brand_showcase_data[view_all_label]"
								value="<?php echo esc_attr($data['view_all_label']); ?>" />
						</div>
						<div class="ttbs-admin-field">
							<label
								for="ttbs-view-all-url"><?php esc_html_e('Right link URL', 'tumtook-brand-showcase'); ?></label>
							<input id="ttbs-view-all-url" type="url" name="tumtook_brand_showcase_data[view_all_url]"
								value="<?php echo esc_url($data['view_all_url']); ?>" />
						</div>
					</div>
				</div>
				<h3 class="ttbs-admin-title"><?php esc_html_e('Brand Showcase Images', 'tumtook-brand-showcase'); ?></h3>
				<div class="ttbs-admin-slides">
					<?php foreach ($data['slides'] as $index => $slide): ?>
						<div class="ttbs-admin-slide-card">
							<h4><?php echo esc_html(sprintf(__('Slide %d', 'tumtook-brand-showcase'), $index + 1)); ?></h4>
							<div class="ttbs-admin-grid">
								<?php
								$this->render_media_picker_field(
									array(
										'label' => __('Image File', 'tumtook-brand-showcase'),
										'id_name' => 'tumtook_brand_showcase_data[slides][' . $index . '][image_id]',
										'url_name' => 'tumtook_brand_showcase_data[slides][' . $index . '][image_url]',
										'alt_name' => 'tumtook_brand_showcase_data[slides][' . $index . '][alt]',
										'link_name' => 'tumtook_brand_showcase_data[slides][' . $index . '][link_url]',
										'id_value' => $slide['image_id'],
										'url_value' => $slide['image_url'],
										'alt_value' => $slide['alt'],
										'link_value' => $slide['link_url'],
									)
								);
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}

	public function save_page_meta($post_id)
	{
		if (!isset($_POST['tumtook_brand_showcase_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tumtook_brand_showcase_nonce'])), 'tumtook_brand_showcase_save')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$raw_data = isset($_POST['tumtook_brand_showcase_data']) ? wp_unslash($_POST['tumtook_brand_showcase_data']) : array();

		if (!is_array($raw_data)) {
			delete_post_meta($post_id, self::META_KEY);
			return;
		}

		$sanitized = array(
			'title' => sanitize_text_field(isset($raw_data['title']) ? $raw_data['title'] : $this->get_default_data()['title']),
			'subtitle' => sanitize_text_field(isset($raw_data['subtitle']) ? $raw_data['subtitle'] : $this->get_default_data()['subtitle']),
			'view_all_label' => sanitize_text_field(isset($raw_data['view_all_label']) ? $raw_data['view_all_label'] : $this->get_default_data()['view_all_label']),
			'view_all_url' => esc_url_raw(isset($raw_data['view_all_url']) ? $raw_data['view_all_url'] : ''),
			'slides' => array(),
		);

		$raw_slides = isset($raw_data['slides']) && is_array($raw_data['slides']) ? $raw_data['slides'] : array();

		for ($index = 0; $index < self::SLIDE_COUNT; $index++) {
			$slide = isset($raw_slides[$index]) && is_array($raw_slides[$index]) ? $raw_slides[$index] : array();

			$sanitized['slides'][] = array(
				'image_id' => absint(isset($slide['image_id']) ? $slide['image_id'] : 0),
				'image_url' => esc_url_raw(isset($slide['image_url']) ? $slide['image_url'] : ''),
				'alt' => sanitize_text_field(isset($slide['alt']) ? $slide['alt'] : ''),
				'link_url' => esc_url_raw(isset($slide['link_url']) ? $slide['link_url'] : ''),
			);
		}

		$has_image = false;

		foreach ($sanitized['slides'] as $slide) {
			if (!empty($slide['image_id']) || !empty($slide['image_url'])) {
				$has_image = true;
				break;
			}
		}

		if (!$has_image) {
			delete_post_meta($post_id, self::META_KEY);
			return;
		}

		update_post_meta($post_id, self::META_KEY, $sanitized);
	}

	private function resolve_page_id($atts)
	{
		if (!empty($atts['page_id'])) {
			return absint($atts['page_id']);
		}

		if (is_singular('page')) {
			return get_queried_object_id();
		}

		return 0;
	}

	private function build_slider_markup($page_id, $slides, $args)
	{
		if (empty($slides)) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'title' => '',
				'subtitle' => '',
				'view_all_label' => '',
				'view_all_url' => '',
			)
		);

		wp_enqueue_style('tumtook-brand-showcase');
		wp_enqueue_script('tumtook-brand-showcase');

		$instance_id = 'ttbs-showcase-' . $page_id . '-' . wp_generate_uuid4();

		ob_start();
		?>
		<section class="ttbs-showcase" id="<?php echo esc_attr($instance_id); ?>" data-autoplay-delay="4500">
			<?php if (!empty($args['title']) || !empty($args['view_all_url'])): ?>
				<div class="ttbs-showcase__header">
					<?php if (!empty($args['title'])): ?>
						<h2 class="ttbs-showcase__title"><?php echo esc_html($args['title']); ?></h2>
					<?php endif; ?>
					<?php if (!empty($args['view_all_url'])): ?>
						<a class="ttbs-showcase__view-all" href="<?php echo esc_url($args['view_all_url']); ?>">
							<?php echo esc_html($args['view_all_label']); ?>
							<span class="ttbs-showcase__view-all-icon" aria-hidden="true">&rsaquo;</span>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="ttbs-showcase__viewport" data-slider-track>
				<?php foreach ($slides as $index => $slide): ?>
						<article class="ttbs-showcase__slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-slide>
							<div class="ttbs-showcase__card">
								<?php if (!empty($slide['link_url'])): ?>
										<a class="ttbs-showcase__card-link" href="<?php echo esc_url($slide['link_url']); ?>" draggable="false"
											aria-label="<?php esc_attr_e('Open brand link', 'tumtook-brand-showcase'); ?>">
											<img class="ttbs-showcase__image" src="<?php echo esc_url($slide['image_url']); ?>"
												alt="<?php echo esc_attr($slide['alt']); ?>" loading="lazy" draggable="false" />
										</a>
									<button class="ttbs-showcase__card-link-icon" type="button"
										data-card-link="<?php echo esc_url($slide['link_url']); ?>"
										aria-label="<?php esc_attr_e('Open brand link', 'tumtook-brand-showcase'); ?>"></button>
									<?php else: ?>
										<img class="ttbs-showcase__image" src="<?php echo esc_url($slide['image_url']); ?>"
											alt="<?php echo esc_attr($slide['alt']); ?>" loading="lazy" draggable="false" />
								<?php endif; ?>
							</div>
						</article>
				<?php endforeach; ?>
			</div>

			<div class="ttbs-showcase__nav"
				aria-label="<?php esc_attr_e('Brand showcase controls', 'tumtook-brand-showcase'); ?>">
				<div class="ttbs-showcase__dots" data-slider-dots></div>
				<div class="ttbs-showcase__buttons">
					<button type="button" class="ttbs-showcase__button ttbs-showcase__button--prev" data-slider-prev
						aria-label="<?php esc_attr_e('Previous slide', 'tumtook-brand-showcase'); ?>">
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
							<path d="M14.5 6.5L9 12l5.5 5.5" />
						</svg>
					</button>
					<button type="button" class="ttbs-showcase__button ttbs-showcase__button--next" data-slider-next
						aria-label="<?php esc_attr_e('Next slide', 'tumtook-brand-showcase'); ?>">
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
							<path d="M9.5 6.5L15 12l-5.5 5.5" />
						</svg>
					</button>
				</div>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	public function render_shortcode($atts)
	{
		$this->register_front_assets();

		$atts = shortcode_atts(
			array(
				'page_id' => 0,
				'title' => '',
				'subtitle' => '',
				'view_all_label' => '',
				'view_all_url' => '',
			),
			$atts,
			self::SHORTCODE
		);

		$page_id = $this->resolve_page_id($atts);

		if (!$page_id) {
			return '';
		}

		$page_data = $this->get_page_data($page_id);
		$slides = $this->get_slides_for_page($page_id);

		if (empty($slides)) {
			return '';
		}

		$this->rendered_page_ids[$page_id] = true;

		return $this->build_slider_markup(
			$page_id,
			$slides,
			array(
				'title' => '' !== $atts['title'] ? $atts['title'] : $page_data['title'],
				'subtitle' => '' !== $atts['subtitle'] ? $atts['subtitle'] : $page_data['subtitle'],
				'view_all_label' => '' !== $atts['view_all_label'] ? $atts['view_all_label'] : $page_data['view_all_label'],
				'view_all_url' => '' !== $atts['view_all_url'] ? $atts['view_all_url'] : $page_data['view_all_url'],
			)
		);
	}
}

new Tumtook_Brand_Showcase_Plugin();
