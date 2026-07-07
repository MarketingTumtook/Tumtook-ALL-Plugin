<?php
/**
 * Plugin Name: Tumtook Video How To Slider
 * Description: Add page-level promo media fields and display a rounded promo slider with one video plus six image slides.
 * Version: 1.2.6
 * Author: Tumtook
 * Text Domain: tumtook-video-rollup-slider
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Video_Howtoknow_Slider_Plugin
{
	const META_KEY = '_tumtook_video_howtoknow_data';
	const PRODUCT_PRICE_META_KEY = '_tumtook_recommended_price';
	const PRODUCT_RECOMMENDED_META_KEY = '_tumtook_recommended_enabled';
	const VERSION = '1.2.6';
	const FONT_HANDLE = 'tumtook-kanit-font';

	private $rendered_page_ids = array();

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post_page', array($this, 'save_page_meta'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_shortcode('video_how_to_slider', array($this, 'render_shortcode'));
		add_shortcode('tumtook_video_how_to_slider', array($this, 'render_shortcode'));
		add_shortcode('tumtook_video_how_to_recommended_products', array($this, 'render_recommended_products_shortcode'));
	}

	public function register_meta_boxes()
	{
		add_meta_box(
			'tumtook-video-rollup-slider-page',
			__('สไลด์วิดีโอและรูปภาพ', 'tumtook-video-rollup-slider'),
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
			'video-rollup-slider-admin',
			plugin_dir_url(__FILE__) . 'assets/js/admin.js',
			array('jquery'),
			$this->get_asset_version('assets/js/admin.js'),
			true
		);
		wp_add_inline_script(
			'video-rollup-slider-admin',
			'jQuery(function($){$(document).off("click.videoRollupClearInline",".video-rollup-clear").on("click.videoRollupClearInline",".video-rollup-clear",function(event){event.preventDefault();var wrapper=$(this).closest(".video-rollup-admin-field");wrapper.find(".video-rollup-media-id").val("");wrapper.find(".video-rollup-media-url").val("");wrapper.find(".video-rollup-image-preview").attr("src","").hide();});});'
		);
	}

	public function register_front_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'video-rollup-slider',
			plugin_dir_url(__FILE__) . 'assets/css/video-rollup-slider.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/video-rollup-slider.css')
		);

		wp_register_script(
			'video-rollup-slider',
			plugin_dir_url(__FILE__) . 'assets/js/video-rollup-slider.js',
			array(),
			$this->get_asset_version('assets/js/video-rollup-slider.js'),
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

	private function get_default_video_item()
	{
		return array(
			'video_id' => 0,
			'video_url' => '',
			'poster_id' => 0,
			'poster_url' => '',
		);
	}

	private function get_default_image_item($index = 0)
	{
		return array(
			'heading' => '',
			'image_id' => 0,
			'image_url' => '',
		);
	}

	private function get_default_data()
	{
		$images = array();

		for ($index = 0; $index < 6; $index++) {
			$images[] = $this->get_default_image_item($index);
		}

		return array(
			'video' => $this->get_default_video_item(),
			'images' => $images,
			'youtube' => array(
				'title' => __('คลิปเกี่ยวกับ how to', 'tumtook-video-rollup-slider'),
				'items' => array_fill(0, 5, ''),
			),
			'product' => array(
				'price' => '',
				'recommended' => 0,
			),
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
		$data = wp_parse_args(is_array($saved) ? $saved : array(), $this->get_default_data());

		$data['video'] = wp_parse_args(is_array($data['video']) ? $data['video'] : array(), $this->get_default_video_item());
		$data['video']['video_url'] = $this->normalize_media_url($data['video']['video_id'], $data['video']['video_url']);
		$data['video']['poster_url'] = $this->normalize_media_url($data['video']['poster_id'], $data['video']['poster_url']);

		$images = array();

		for ($index = 0; $index < 6; $index++) {
			$image = isset($data['images'][$index]) && is_array($data['images'][$index]) ? $data['images'][$index] : array();
			$image = wp_parse_args($image, $this->get_default_image_item($index));
			$image['heading'] = isset($image['heading']) ? sanitize_textarea_field($image['heading']) : '';
			$image['image_url'] = $this->normalize_media_url($image['image_id'], $image['image_url']);
			$images[] = $image;
		}

		$data['images'] = $images;
		$data['youtube'] = wp_parse_args(
			isset($data['youtube']) && is_array($data['youtube']) ? $data['youtube'] : array(),
			$this->get_default_data()['youtube']
		);
		$data['youtube']['items'] = isset($data['youtube']['items']) && is_array($data['youtube']['items']) ? array_values($data['youtube']['items']) : array();

		for ($index = 0; $index < 5; $index++) {
			$data['youtube']['items'][$index] = isset($data['youtube']['items'][$index]) ? esc_url_raw($data['youtube']['items'][$index]) : '';
		}

		$data['product'] = wp_parse_args(
			isset($data['product']) && is_array($data['product']) ? $data['product'] : array(),
			$this->get_default_data()['product']
		);

		$saved_price = get_post_meta($post_id, self::PRODUCT_PRICE_META_KEY, true);
		$saved_recommended = get_post_meta($post_id, self::PRODUCT_RECOMMENDED_META_KEY, true);

		if ('' !== $saved_price) {
			$data['product']['price'] = (string) $saved_price;
		}

		if ('' !== $saved_recommended) {
			$data['product']['recommended'] = absint($saved_recommended) ? 1 : 0;
		}

		return $data;
	}

	private function get_page_product_card_data($page_id)
	{
		$page = get_post($page_id);

		if (!$page || 'page' !== $page->post_type || 'publish' !== $page->post_status) {
			return array();
		}

		$data = $this->get_page_data($page_id);
		$image_url = get_the_post_thumbnail_url($page_id, 'large');

		if (empty($image_url)) {
			$image_url = '';
		}

		return array(
			'id' => $page_id,
			'title' => get_the_title($page_id),
			'url' => get_permalink($page_id),
			'image_url' => $image_url,
			'price' => isset($data['product']['price']) ? trim((string) $data['product']['price']) : '',
			'recommended' => !empty($data['product']['recommended']),
		);
	}

	private function get_recommended_product_cards($current_page_id = 0, $limit = 8, $include_current = false)
	{
		$query_args = array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'posts_per_page' => max(1, absint($limit)),
			'orderby' => array(
				'menu_order' => 'ASC',
				'date' => 'DESC',
			),
			'meta_query' => array(
				array(
					'key' => self::PRODUCT_RECOMMENDED_META_KEY,
					'value' => '1',
				),
			),
		);

		if ($current_page_id && !$include_current) {
			$query_args['post__not_in'] = array(absint($current_page_id));
		}

		$query = new WP_Query($query_args);
		$cards = array();

		if ($query->have_posts()) {
			foreach ($query->posts as $page) {
				$card = $this->get_page_product_card_data($page->ID);

				if (empty($card['title']) || empty($card['url'])) {
					continue;
				}

				$cards[] = $card;
			}
		}

		wp_reset_postdata();

		return $cards;
	}

	private function get_youtube_video_id($url)
	{
		$url = trim((string) $url);

		if (empty($url)) {
			return '';
		}

		$parts = wp_parse_url($url);

		if (empty($parts['host'])) {
			return '';
		}

		$host = strtolower($parts['host']);

		if (false !== strpos($host, 'youtu.be') && !empty($parts['path'])) {
			return preg_replace('/[^A-Za-z0-9_-]/', '', trim($parts['path'], '/'));
		}

		if (false !== strpos($host, 'youtube.com')) {
			if (!empty($parts['query'])) {
				parse_str($parts['query'], $query);

				if (!empty($query['v'])) {
					return preg_replace('/[^A-Za-z0-9_-]/', '', $query['v']);
				}
			}

			if (!empty($parts['path']) && preg_match('#/(embed|shorts)/([^/?&]+)#', $parts['path'], $matches)) {
				return preg_replace('/[^A-Za-z0-9_-]/', '', $matches[2]);
			}
		}

		return '';
	}

	private function get_youtube_slides_for_page($post_id)
	{
		$data = $this->get_page_data($post_id);
		$slides = array();

		foreach ($data['youtube']['items'] as $url) {
			$video_id = $this->get_youtube_video_id($url);

			if (empty($video_id)) {
				continue;
			}

			$slides[] = array(
				'type' => 'youtube',
				'video_id' => $video_id,
				'youtube_url' => esc_url_raw($url),
				'image_url' => 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/hqdefault.jpg',
				'embed_url' => 'https://www.youtube.com/embed/' . rawurlencode($video_id) . '?autoplay=0&rel=0&playsinline=1',
			);
		}

		return array(
			'title' => isset($data['youtube']['title']) ? $data['youtube']['title'] : '',
			'slides' => $slides,
		);
	}

	private function get_slides_for_page($post_id)
	{
		$data = $this->get_page_data($post_id);
		$slides = array();

		if (!empty($data['video']['video_url'])) {
			$slides[] = array(
				'type' => 'video',
				'video_url' => $data['video']['video_url'],
				'poster_url' => $data['video']['poster_url'],
			);
		}

		foreach ($data['images'] as $image) {
			if (empty($image['image_url'])) {
				continue;
			}

			$slides[] = array(
				'type' => 'image',
				'heading' => $image['heading'],
				'image_url' => $image['image_url'],
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
				'media_type' => 'image',
				'id_value' => '',
				'url_value' => '',
				'preview' => false,
				'help' => '',
			)
		);
		?>
		<div class="video-rollup-admin-field video-rollup-admin-field--full">
			<label><?php echo esc_html($args['label']); ?></label>
			<div class="video-rollup-admin-media">
				<div>
					<input type="hidden" class="video-rollup-media-id" name="<?php echo esc_attr($args['id_name']); ?>"
						value="<?php echo esc_attr($args['id_value']); ?>" />
					<input type="url" class="video-rollup-media-url" name="<?php echo esc_attr($args['url_name']); ?>"
						value="<?php echo esc_url($args['url_value']); ?>"
						placeholder="<?php echo esc_attr('video' === $args['media_type'] ? __('อัปโหลดหรือวางลิงก์วิดีโอ', 'tumtook-video-rollup-slider') : __('อัปโหลดหรือวางลิงก์รูปภาพ', 'tumtook-video-rollup-slider')); ?>" />
					<?php if (!empty($args['help'])): ?>
						<p class="description"><?php echo esc_html($args['help']); ?></p>
					<?php endif; ?>
				</div>
				<div class="video-rollup-admin-actions">
					<button type="button" class="button button-secondary video-rollup-upload"
						data-media-type="<?php echo esc_attr($args['media_type']); ?>">
						<?php echo esc_html('video' === $args['media_type'] ? __('เลือกวิดีโอ', 'tumtook-video-rollup-slider') : __('เลือกรูปภาพ', 'tumtook-video-rollup-slider')); ?>
					</button>
					<button type="button" class="button button-link-delete video-rollup-clear">
						<?php esc_html_e('ล้างค่า', 'tumtook-video-rollup-slider'); ?>
					</button>
				</div>
			</div>
			<?php if ($args['preview']): ?>
				<div class="video-rollup-admin-preview">
					<img class="video-rollup-image-preview" src="<?php echo esc_url($args['url_value']); ?>" alt="" <?php echo empty($args['url_value']) ? 'style="display:none"' : ''; ?> />
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_admin_meta_styles()
	{
		?>
		<style>
			.video-rollup-page-wrap,
			.video-rollup-page-wrap button,
			.video-rollup-page-wrap input,
			.video-rollup-page-wrap select,
			.video-rollup-page-wrap textarea {
				font-family: "Kanit", sans-serif
			}

			.video-rollup-page-wrap {
				display: grid;
				gap: 14px
			}

			.video-rollup-page-intro {
				background: #f6f7f7;
				border: 1px solid #dcdcde;
				border-radius: 14px;
				color: #50575e;
				margin: 0;
				padding: 12px 14px
			}

			.video-rollup-page-section {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 16px;
				overflow: hidden
			}

			.video-rollup-page-section[open] {
				box-shadow: 0 8px 22px rgba(15, 23, 42, .06)
			}

			.video-rollup-page-section__summary {
				align-items: center;
				cursor: pointer;
				display: flex;
				gap: 12px;
				justify-content: space-between;
				list-style: none;
				padding: 14px 16px
			}

			.video-rollup-page-section__summary::-webkit-details-marker {
				display: none
			}

			.video-rollup-page-section__summary::after {
				color: #6b7280;
				content: "+";
				font-size: 24px;
				font-weight: 400;
				line-height: 1;
				transform: translateY(-1px)
			}

			.video-rollup-page-section[open] .video-rollup-page-section__summary::after {
				content: "−"
			}

			.video-rollup-page-section__summary-main {
				display: grid;
				gap: 3px
			}

			.video-rollup-page-section__title {
				font-size: 15px;
				font-weight: 700;
				margin: 0
			}

			.video-rollup-page-section__meta {
				color: #6b7280;
				font-size: 12px;
				line-height: 1.35
			}

			.video-rollup-page-section__body {
				border-top: 1px solid #eef0f1;
				display: grid;
				gap: 16px;
				padding: 16px
			}

			.video-rollup-page-grid {
				display: grid;
				gap: 14px;
				grid-template-columns: repeat(2, minmax(0, 1fr))
			}

			.video-rollup-admin-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 6px
			}

			.video-rollup-admin-field input,
			.video-rollup-admin-field textarea {
				width: 100%
			}

			.video-rollup-admin-field--full {
				grid-column: 1/-1
			}

			.video-rollup-admin-media {
				display: grid;
				grid-template-columns: minmax(0, 1fr) auto;
				gap: 10px;
				align-items: start
			}

			.video-rollup-admin-actions {
				align-items: center;
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				justify-content: flex-start
			}

			.video-rollup-admin-preview {
				margin-top: 8px
			}

			.video-rollup-admin-preview img {
				border-radius: 14px;
				display: block;
				height: auto;
				max-width: 180px
			}

			@media (max-width: 782px) {
				.video-rollup-page-grid {
					grid-template-columns: 1fr
				}

				.video-rollup-admin-field--full {
					grid-column: auto
				}

				.video-rollup-admin-media {
					grid-template-columns: 1fr
				}
			}
		</style>
		<?php
	}

	public function render_page_meta_box($post)
	{
		$data = $this->get_page_data($post->ID);

		wp_nonce_field('tumtook_video_howtoknow_page_save', 'tumtook_video_howtoknow_page_nonce');
		$this->render_admin_meta_styles();
		?>
		<div class="video-rollup-page-wrap">
			<p class="video-rollup-page-intro">
				<?php esc_html_e('อัปโหลดวิดีโอ 1 รายการสำหรับสไลด์แรก และเพิ่มรูปภาพได้สูงสุด 6 รายการสำหรับหน้านี้', 'tumtook-video-rollup-slider'); ?>
			</p>

			<details class="video-rollup-page-section" open>
				<summary class="video-rollup-page-section__summary">
					<span class="video-rollup-page-section__summary-main">
						<span
							class="video-rollup-page-section__title"><?php esc_html_e('สไลด์ที่ 1: วิดีโอ', 'tumtook-video-rollup-slider'); ?></span>
						<span
							class="video-rollup-page-section__meta"><?php esc_html_e('ตั้งค่าวิดีโอหลักของ section นี้', 'tumtook-video-rollup-slider'); ?></span>
					</span>
				</summary>
				<div class="video-rollup-page-section__body">
					<div class="video-rollup-page-grid">
						<?php
						$this->render_media_picker_field(
							array(
								'label' => __('ไฟล์วิดีโอ', 'tumtook-video-rollup-slider'),
								'id_name' => 'tumtook_video_howtoknow_data[video][video_id]',
								'url_name' => 'tumtook_video_howtoknow_data[video][video_url]',
								'media_type' => 'video',
								'id_value' => $data['video']['video_id'],
								'url_value' => $data['video']['video_url'],
								'help' => __('สไลด์แรกเท่านั้นที่ใช้ไฟล์วิดีโอ', 'tumtook-video-rollup-slider'),
							)
						);

						$this->render_media_picker_field(
							array(
								'label' => __('รูปปกก่อนเล่นวิดีโอ', 'tumtook-video-rollup-slider'),
								'id_name' => 'tumtook_video_howtoknow_data[video][poster_id]',
								'url_name' => 'tumtook_video_howtoknow_data[video][poster_url]',
								'media_type' => 'image',
								'id_value' => $data['video']['poster_id'],
								'url_value' => $data['video']['poster_url'],
								'preview' => true,
							)
						);
						?>
					</div>
				</div>
			</details>

			<?php foreach ($data['images'] as $index => $image): ?>
				<details class="video-rollup-page-section">
					<summary class="video-rollup-page-section__summary">
						<span class="video-rollup-page-section__summary-main">
							<span
								class="video-rollup-page-section__title"><?php echo esc_html(sprintf(__('สไลด์ที่ %d: รูปภาพ', 'tumtook-video-rollup-slider'), $index + 2)); ?></span>
							<span
								class="video-rollup-page-section__meta"><?php echo !empty($image['image_url']) ? esc_html__('มีรูปภาพแล้ว', 'tumtook-video-rollup-slider') : esc_html__('ยังไม่ได้เลือกรูปภาพ', 'tumtook-video-rollup-slider'); ?></span>
						</span>
					</summary>
					<div class="video-rollup-page-section__body">
						<div class="video-rollup-page-grid">
							<div class="video-rollup-admin-field video-rollup-admin-field--full">
								<label><?php echo esc_html(sprintf(__('ข้อความหัวการ์ด รูปภาพ %d', 'tumtook-video-rollup-slider'), $index + 1)); ?></label>
								<textarea name="tumtook_video_howtoknow_data[images][<?php echo esc_attr($index); ?>][heading]" rows="2"
									placeholder="<?php esc_attr_e('ข้อความที่จะโชว์บนรูปภาพนี้', 'tumtook-video-rollup-slider'); ?>"><?php echo esc_textarea($image['heading']); ?></textarea>
							</div>

							<?php
							$this->render_media_picker_field(
								array(
									'label' => __('ไฟล์รูปภาพ', 'tumtook-video-rollup-slider'),
									'id_name' => 'tumtook_video_howtoknow_data[images][' . $index . '][image_id]',
									'url_name' => 'tumtook_video_howtoknow_data[images][' . $index . '][image_url]',
									'media_type' => 'image',
									'id_value' => $image['image_id'],
									'url_value' => $image['image_url'],
									'preview' => true,
								)
							);
							?>
						</div>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function render_youtube_meta_box($post)
	{
		$data = $this->get_page_data($post->ID);

		wp_nonce_field('tumtook_video_howtoknow_page_save', 'tumtook_video_howtoknow_page_nonce');
		$this->render_admin_meta_styles();
		?>
		<div class="video-rollup-page-wrap">
			<p class="video-rollup-page-intro">
				<?php esc_html_e('จัดการลิงก์ YouTube สำหรับ section แยกของคลิปวิดีโอได้ที่นี่', 'tumtook-video-rollup-slider'); ?>
			</p>

			<details class="video-rollup-page-section" open>
				<summary class="video-rollup-page-section__summary">
					<span class="video-rollup-page-section__summary-main">
						<span
							class="video-rollup-page-section__title"><?php esc_html_e('ส่วนที่ 2: คลิป YouTube', 'tumtook-video-rollup-slider'); ?></span>
						<span
							class="video-rollup-page-section__meta"><?php esc_html_e('กรอกลิงก์ YouTube ได้สูงสุด 5 รายการ', 'tumtook-video-rollup-slider'); ?></span>
					</span>
				</summary>
				<div class="video-rollup-page-section__body">
					<div class="video-rollup-page-grid">
						<div class="video-rollup-admin-field video-rollup-admin-field--full">
							<label><?php esc_html_e('ชื่อหัวข้อ section', 'tumtook-video-rollup-slider'); ?></label>
							<input type="text" name="tumtook_video_howtoknow_data[youtube][title]"
								value="<?php echo esc_attr($data['youtube']['title']); ?>" />
						</div>

						<?php for ($index = 0; $index < 5; $index++): ?>
							<div class="video-rollup-admin-field video-rollup-admin-field--full">
								<label><?php echo esc_html(sprintf(__('ลิงก์ YouTube %d', 'tumtook-video-rollup-slider'), $index + 1)); ?></label>
								<input type="url"
									name="tumtook_video_howtoknow_data[youtube][items][<?php echo esc_attr($index); ?>]"
									value="<?php echo esc_url(isset($data['youtube']['items'][$index]) ? $data['youtube']['items'][$index] : ''); ?>"
									placeholder="https://www.youtube.com/watch?v=..." />
							</div>
						<?php endfor; ?>
					</div>
				</div>
			</details>
		</div>
		<?php
	}

	public function save_page_meta($post_id)
	{
		if (!isset($_POST['tumtook_video_howtoknow_page_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tumtook_video_howtoknow_page_nonce'])), 'tumtook_video_howtoknow_page_save')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$raw_data = isset($_POST['tumtook_video_howtoknow_data']) ? wp_unslash($_POST['tumtook_video_howtoknow_data']) : array();
		$raw_product = isset($_POST['tumtook_video_howtoknow_product']) ? wp_unslash($_POST['tumtook_video_howtoknow_product']) : array();

		$product_price = '';
		$product_recommended = 0;

		if (is_array($raw_product)) {
			$product_price = isset($raw_product['price']) ? sanitize_text_field($raw_product['price']) : '';
			$product_recommended = !empty($raw_product['recommended']) ? 1 : 0;
		}

		if ('' !== $product_price) {
			update_post_meta($post_id, self::PRODUCT_PRICE_META_KEY, $product_price);
		} else {
			delete_post_meta($post_id, self::PRODUCT_PRICE_META_KEY);
		}

		if ($product_recommended) {
			update_post_meta($post_id, self::PRODUCT_RECOMMENDED_META_KEY, 1);
		} else {
			delete_post_meta($post_id, self::PRODUCT_RECOMMENDED_META_KEY);
		}

		if (!is_array($raw_data)) {
			delete_post_meta($post_id, self::META_KEY);
			return;
		}

		$defaults = $this->get_default_data();
		$raw_data = wp_parse_args($raw_data, $defaults);
		$video = wp_parse_args(is_array($raw_data['video']) ? $raw_data['video'] : array(), $defaults['video']);

		$sanitized = array(
			'video' => array(
				'video_id' => absint(isset($video['video_id']) ? $video['video_id'] : 0),
				'video_url' => esc_url_raw(isset($video['video_url']) ? $video['video_url'] : ''),
				'poster_id' => absint(isset($video['poster_id']) ? $video['poster_id'] : 0),
				'poster_url' => esc_url_raw(isset($video['poster_url']) ? $video['poster_url'] : ''),
			),
			'images' => array(),
			'youtube' => array(
				'title' => sanitize_text_field(isset($raw_data['youtube']['title']) ? $raw_data['youtube']['title'] : __('คลิปเกี่ยวกับ how to', 'tumtook-video-rollup-slider')),
				'items' => array(),
			),
		);

		$raw_images = isset($raw_data['images']) && is_array($raw_data['images']) ? $raw_data['images'] : array();

		for ($index = 0; $index < 6; $index++) {
			$image = isset($raw_images[$index]) && is_array($raw_images[$index]) ? $raw_images[$index] : array();

			$sanitized['images'][] = array(
				'heading' => sanitize_textarea_field(isset($image['heading']) ? $image['heading'] : ''),
				'image_id' => absint(isset($image['image_id']) ? $image['image_id'] : 0),
				'image_url' => esc_url_raw(isset($image['image_url']) ? $image['image_url'] : ''),
			);
		}

		$raw_youtube_items = isset($raw_data['youtube']['items']) && is_array($raw_data['youtube']['items']) ? $raw_data['youtube']['items'] : array();

		for ($index = 0; $index < 5; $index++) {
			$sanitized['youtube']['items'][] = esc_url_raw(isset($raw_youtube_items[$index]) ? $raw_youtube_items[$index] : '');
		}

		$has_video = !empty($sanitized['video']['video_url']) || !empty($sanitized['video']['video_id']);
		$has_image = false;

		foreach ($sanitized['images'] as $image) {
			if (!empty($image['image_url']) || !empty($image['image_id'])) {
				$has_image = true;
				break;
			}
		}

		$has_youtube = false;

		foreach ($sanitized['youtube']['items'] as $youtube_url) {
			if (!empty($this->get_youtube_video_id($youtube_url))) {
				$has_youtube = true;
				break;
			}
		}

		if (!$has_video && !$has_image && !$has_youtube) {
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

	private function render_media_slide($slide)
	{
		$type = isset($slide['type']) ? $slide['type'] : 'image';
		$heading = isset($slide['heading']) ? trim((string) $slide['heading']) : '';
		?>
		<article class="video-rollup-media-item" data-slide>
			<div class="video-rollup-media-item__content">
				<div class="video-rollup-media-item__media">
					<div
						class="video-rollup-video-card<?php echo 'image' === $type ? ' video-rollup-video-card--image' : ''; ?><?php echo 'youtube' === $type ? ' video-rollup-video-card--youtube' : ''; ?>">
						<?php if ('video' === $type): ?>
							<video class="video-rollup-video-card__video" playsinline preload="auto" muted <?php echo !empty($slide['poster_url']) ? 'poster="' . esc_url($slide['poster_url']) . '"' : ''; ?>>
								<source src="<?php echo esc_url($slide['video_url']); ?>" type="video/mp4">
							</video>
							<button type="button" class="video-rollup-video-card__play" data-play-toggle
								aria-label="<?php esc_attr_e('Play video', 'tumtook-video-rollup-slider'); ?>">
								<span class="video-rollup-video-card__play-icon"></span>
							</button>
							<div class="video-rollup-video-card__overlay"></div>
							<div class="video-rollup-video-card__timeline">
								<div class="video-rollup-video-card__duration" data-duration>0:00</div>
								<div class="video-rollup-video-card__progress" data-progress-track role="slider" tabindex="0"
									aria-label="<?php esc_attr_e('Seek video', 'tumtook-video-rollup-slider'); ?>" aria-valuemin="0"
									aria-valuemax="100" aria-valuenow="0">
									<span class="video-rollup-video-card__progress-fill" data-progress-fill></span>
								</div>
							</div>
							<div class="video-rollup-video-card__controls">
								<div class="video-rollup-video-card__volume-shell" data-volume-shell style="--volume-level: 0.6;">
									<span class="video-rollup-video-card__volume-fill" data-volume-fill></span>
									<input type="range" class="video-rollup-video-card__volume" data-volume-slider min="0" max="1"
										step="0.01" value="0.6"
										aria-label="<?php esc_attr_e('Adjust volume', 'tumtook-video-rollup-slider'); ?>" />
								</div>
								<button type="button" class="video-rollup-video-card__speaker" data-volume-toggle
									aria-label="<?php esc_attr_e('Toggle sound', 'tumtook-video-rollup-slider'); ?>">
									<svg class="video-rollup-video-card__speaker-svg video-rollup-video-card__speaker-svg--on"
										viewBox="0 0 24 24" aria-hidden="true" focusable="false">
										<path d="M4 14H8L13 18V6L8 10H4V14Z" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linejoin="round" />
										<path d="M16 9C17.3 10.1 18 11.4 18 12C18 12.6 17.3 13.9 16 15" fill="none"
											stroke="currentColor" stroke-width="2" stroke-linecap="round" />
										<path d="M18.5 6.5C20.4 8.2 21.5 10.2 21.5 12C21.5 13.8 20.4 15.8 18.5 17.5" fill="none"
											stroke="currentColor" stroke-width="2" stroke-linecap="round" />
									</svg>
									<svg class="video-rollup-video-card__speaker-svg video-rollup-video-card__speaker-svg--muted"
										viewBox="0 0 24 24" aria-hidden="true" focusable="false">
										<path d="M4 14H8L13 18V6L8 10H4V14Z" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linejoin="round" />
										<path d="M16 8L21 16" fill="none" stroke="currentColor" stroke-width="2.4"
											stroke-linecap="round" />
										<path d="M21 8L16 16" fill="none" stroke="currentColor" stroke-width="2.4"
											stroke-linecap="round" />
									</svg>
								</button>
							</div>
						<?php elseif ('youtube' === $type): ?>
							<div class="video-rollup-youtube-card">
								<iframe class="video-rollup-youtube-card__frame" src="<?php echo esc_url($slide['embed_url']); ?>"
									title="<?php esc_attr_e('YouTube video player', 'tumtook-video-rollup-slider'); ?>"
									allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
									allowfullscreen loading="lazy"></iframe>
							</div>
						<?php else: ?>
							<img class="video-rollup-video-card__video video-rollup-video-card__image"
								src="<?php echo esc_url($slide['image_url']); ?>" alt="" />
							<div class="video-rollup-video-card__overlay"></div>
							<?php if ('' !== $heading): ?>
								<div class="video-rollup-video-card__heading"><?php echo nl2br(esc_html($heading)); ?></div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</article>
		<?php
	}

	private function build_slider_markup($slides, $atts, $modifier = '')
	{
		$this->register_front_assets();

		$atts = shortcode_atts(
			array(
				'title' => __('ทำความรู้จักกับ how to', 'tumtook-video-rollup-slider'),
				'subtitle' => '',
			),
			$atts
		);

		if (empty($slides)) {
			return '';
		}

		wp_enqueue_style('video-rollup-slider');
		wp_enqueue_script('video-rollup-slider');

		$instance_id = 'video-rollup-slider-' . wp_generate_uuid4();

		ob_start();
		?>
		<section class="video-rollup-slider<?php echo $modifier ? ' ' . esc_attr($modifier) : ''; ?>"
			id="<?php echo esc_attr($instance_id); ?>">
			<?php if (!empty($atts['subtitle'])): ?>
				<div class="video-rollup-slider__header">
					<p class="video-rollup-slider__subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
				</div>
			<?php endif; ?>

			<div class="video-rollup-slider__viewport" data-slider-track>
				<?php foreach ($slides as $slide): ?>
					<?php $this->render_media_slide($slide); ?>
				<?php endforeach; ?>
			</div>

			<div class="video-rollup-slider__nav"
				aria-label="<?php esc_attr_e('Video slider controls', 'tumtook-video-rollup-slider'); ?>">
				<div class="video-rollup-slider__dots" data-slider-dots></div>
				<div class="video-rollup-slider__buttons">
					<button type="button" class="video-rollup-slider__button is-prev" data-slider-prev
						aria-label="<?php esc_attr_e('Previous slide', 'tumtook-video-rollup-slider'); ?>">
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
							<path d="M14.5 6.5L9 12l5.5 5.5" />
						</svg>
					</button>
					<button type="button" class="video-rollup-slider__button is-next" data-slider-next
						aria-label="<?php esc_attr_e('Next slide', 'tumtook-video-rollup-slider'); ?>">
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

	private function build_recommended_products_markup($cards, $atts)
	{
		$this->register_front_assets();

		$atts = shortcode_atts(
			array(
				'title' => __('สินค้าแนะนำ', 'tumtook-video-rollup-slider'),
				'more_label' => __('ดูสินค้าอื่นๆ', 'tumtook-video-rollup-slider'),
				'more_url' => '',
			),
			$atts
		);

		if (empty($cards)) {
			return '';
		}

		wp_enqueue_style('video-rollup-slider');
		wp_enqueue_script('video-rollup-slider');

		ob_start();
		?>
		<section class="video-rollup-slider video-rollup-slider--recommended">
			<div class="video-rollup-slider__header video-rollup-slider__header--recommended">
				<?php if (!empty($atts['title'])): ?>
					<h2 class="video-rollup-slider__section-title"><?php echo esc_html($atts['title']); ?></h2>
				<?php endif; ?>

				<?php if (!empty($atts['more_url'])): ?>
					<a class="video-rollup-slider__header-link" href="<?php echo esc_url($atts['more_url']); ?>">
						<?php echo esc_html($atts['more_label']); ?>
					</a>
				<?php endif; ?>
			</div>

			<div class="video-rollup-slider__nav"
				aria-label="<?php esc_attr_e('Recommended product controls', 'tumtook-video-rollup-slider'); ?>">
				<div class="video-rollup-slider__dots" data-slider-dots></div>
				<div class="video-rollup-slider__buttons">
					<button type="button" class="video-rollup-slider__button is-prev" data-slider-prev
						aria-label="<?php esc_attr_e('Previous slide', 'tumtook-video-rollup-slider'); ?>">
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
							<path d="M14.5 6.5L9 12l5.5 5.5" />
						</svg>
					</button>
					<button type="button" class="video-rollup-slider__button is-next" data-slider-next
						aria-label="<?php esc_attr_e('Next slide', 'tumtook-video-rollup-slider'); ?>">
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
				'title' => __('ทำความรู้จักกับ how to', 'tumtook-video-rollup-slider'),
				'subtitle' => '',
				'page_id' => 0,
			),
			$atts,
			'video_how_to_slider'
		);

		$page_id = $this->resolve_page_id($atts);

		if (!$page_id) {
			return '';
		}

		$this->rendered_page_ids[$page_id] = true;
		$slides = $this->get_slides_for_page($page_id);

		if (empty($slides)) {
			return '';
		}

		return $this->build_slider_markup($slides, $atts);
	}

	public function render_recommended_products_shortcode($atts)
	{
		$this->register_front_assets();

		$atts = shortcode_atts(
			array(
				'title' => __('สินค้าแนะนำ', 'tumtook-video-rollup-slider'),
				'page_id' => 0,
				'limit' => 8,
				'include_current' => 'false',
				'more_label' => __('ดูสินค้าอื่นๆ', 'tumtook-video-rollup-slider'),
				'more_url' => '',
			),
			$atts,
			'tumtook_video_how_to_recommended_products'
		);

		$page_id = $this->resolve_page_id($atts);
		$include_current = filter_var($atts['include_current'], FILTER_VALIDATE_BOOLEAN);
		$cards = $this->get_recommended_product_cards($page_id, absint($atts['limit']), $include_current);

		if (empty($cards)) {
			return '';
		}

		return $this->build_recommended_products_markup($cards, $atts);
	}

	public function append_slider_to_page_content($content)
	{
		return $content;
	}

	public function render_footer_fallback()
	{
		return;
	}
}

new Video_howtoknow_Slider_Plugin();
