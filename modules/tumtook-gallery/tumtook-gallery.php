<?php
/**
 * Plugin Name: Tumtook Gallery
 * Description: Fetch images from an API and display them in a masonry gallery via shortcode.
 * Version: 1.0.13
 * Author: Tumtook
 * Text Domain: tumtook-gallery
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Gallery_Plugin
{
	const OPTION_KEY = 'tumtook_gallery_settings';
	const SHORTCODE = 'tumtook_gallery';
	const META_KEY = '_tumtook_gallery_settings';
	const VERSION = '1.0.13';
	const FONT_HANDLE = 'tumtook-kanit-font';

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_box'));
		add_action('save_post_page', array($this, 'save_page_settings'));
		add_action('wp_ajax_ttg_preview_items', array($this, 'ajax_preview_items'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('rest_api_init', array($this, 'register_rest_routes'));
		add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
	}

	public function enqueue_admin_assets($hook)
	{
		$screen = get_current_screen();

		if (('post.php' !== $hook && 'post-new.php' !== $hook) || !$screen || 'page' !== $screen->post_type) {
			return;
		}

		$this->enqueue_kanit_font();
	}

	public static function activate()
	{
		$defaults = self::get_default_settings();

		if (!get_option(self::OPTION_KEY)) {
			add_option(self::OPTION_KEY, $defaults);
		}
	}

	public static function get_default_settings()
	{
		return array(
			'preset' => 'custom',
			'api_url' => '',
			'match_code' => '',
			'bearer_token' => '',
			'header_name' => '',
			'header_value' => '',
			'cache_minutes' => 30,
			'items_path' => '',
			'image_key' => 'image',
			'title_key' => 'title',
			'link_key' => 'link',
			'alt_key' => 'alt',
		);
	}

	public function register_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'tumtook-gallery',
			plugin_dir_url(__FILE__) . 'assets/css/tumtook-gallery.css',
			array(self::FONT_HANDLE),
			self::VERSION
		);

		wp_register_script(
			'tumtook-gallery',
			plugin_dir_url(__FILE__) . 'assets/js/tumtook-gallery.js',
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'tumtook-gallery',
			'TumtookGalleryData',
			array(
				'restUrl' => esc_url_raw(rest_url('tumtook-gallery/v1/items')),
				'strings' => array(
					'loading' => __('Loading images...', 'tumtook-gallery'),
					'empty' => __('No images found from the API response.', 'tumtook-gallery'),
					'error' => __('Could not load more images right now.', 'tumtook-gallery'),
				),
			)
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

	public function register_rest_routes()
	{
		register_rest_route(
			'tumtook-gallery/v1',
			'/items',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'rest_get_items'),
				'permission_callback' => '__return_true',
				'args' => array(
					'page_id' => array(
						'default' => 0,
						'sanitize_callback' => 'absint',
					),
					'endpoint' => array(
						'required' => false,
						'sanitize_callback' => 'esc_url_raw',
					),
					'limit' => array(
						'default' => 0,
						'sanitize_callback' => 'absint',
					),
					'page' => array(
						'default' => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default' => 12,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function register_meta_box()
	{
		add_meta_box(
			'tumtook-gallery-meta-box',
			__('Tumtook Gallery', 'tumtook-gallery'),
			array($this, 'render_meta_box'),
			'page',
			'normal',
			'default'
		);
	}

	private function sanitize_settings($input)
	{
		$defaults = self::get_default_settings();
		$output = array();

		$output['preset'] = isset($input['preset']) ? sanitize_text_field(trim($input['preset'])) : $defaults['preset'];
		$output['api_url'] = isset($input['api_url']) ? esc_url_raw(trim($input['api_url'])) : $defaults['api_url'];
		$output['match_code'] = isset($input['match_code']) ? sanitize_text_field(trim($input['match_code'])) : $defaults['match_code'];
		$output['bearer_token'] = isset($input['bearer_token']) ? sanitize_text_field($input['bearer_token']) : $defaults['bearer_token'];
		$output['header_name'] = isset($input['header_name']) ? sanitize_text_field(trim($input['header_name'])) : $defaults['header_name'];
		$output['header_value'] = isset($input['header_value']) ? sanitize_text_field(trim($input['header_value'])) : $defaults['header_value'];
		$output['cache_minutes'] = isset($input['cache_minutes']) ? max(1, absint($input['cache_minutes'])) : $defaults['cache_minutes'];
		$output['items_path'] = isset($input['items_path']) ? sanitize_text_field(trim($input['items_path'])) : $defaults['items_path'];
		$output['image_key'] = isset($input['image_key']) ? sanitize_text_field(trim($input['image_key'])) : $defaults['image_key'];
		$output['title_key'] = isset($input['title_key']) ? sanitize_text_field(trim($input['title_key'])) : $defaults['title_key'];
		$output['link_key'] = isset($input['link_key']) ? sanitize_text_field(trim($input['link_key'])) : $defaults['link_key'];
		$output['alt_key'] = isset($input['alt_key']) ? sanitize_text_field(trim($input['alt_key'])) : $defaults['alt_key'];

		return $output;
	}

	public function render_field($args, $settings = null)
	{
		$key = $args['key'];
		$settings = wp_parse_args(is_array($settings) ? $settings : array(), self::get_default_settings());
		$value = isset($settings[$key]) ? $settings[$key] : '';

		$descriptions = array(
			'api_url' => __('Example: https://line.tumtook.com/api/config/galleries?activeOnly=true&x-api-key=YOUR_API_KEY', 'tumtook-gallery'),
			'match_code' => __('Optional item code used to filter the API result first. Leave blank to load every code.', 'tumtook-gallery'),
			'cache_minutes' => __('How long API responses should be cached.', 'tumtook-gallery'),
			'items_path' => __('Dot notation for where the list lives in the JSON, for example data.items or items.', 'tumtook-gallery'),
			'image_key' => __('Field path for image URL in each item. Supports nested arrays like images.fileUrl', 'tumtook-gallery'),
			'alt_key' => __('Field path for image alt text.', 'tumtook-gallery'),
		);

		$type = 'text';
		if ('cache_minutes' === $key) {
			$type = 'number';
		}

		printf(
			'<input type="%1$s" class="regular-text" name="%2$s[%3$s]" value="%4$s" %5$s />',
			esc_attr($type),
			esc_attr(self::META_KEY),
			esc_attr($key),
			esc_attr($value),
			'cache_minutes' === $key ? 'min="1" step="1"' : ''
		);

		if (isset($descriptions[$key])) {
			echo '<p class="description">' . esc_html($descriptions[$key]) . '</p>';
		}
	}

	public function render_settings_page()
	{
		if (isset($_GET['ttg_cache_cleared']) && '1' === $_GET['ttg_cache_cleared']) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Tumtook Gallery cache cleared.', 'tumtook-gallery') . '</p></div>';
		}

		$clear_cache_url = wp_nonce_url(
			add_query_arg(
				array(
					'page' => 'tumtook-gallery',
					'tumtook_action' => 'clear_cache',
				),
				admin_url('options-general.php')
			),
			'tumtook_gallery_clear_cache'
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Tumtook Gallery', 'tumtook-gallery'); ?></h1>
			<p><?php esc_html_e('Use shortcode [tumtook_gallery] to display the gallery.', 'tumtook-gallery'); ?></p>
			<p><?php esc_html_e('Optional shortcode attributes: limit, columns, gap, endpoint.', 'tumtook-gallery'); ?></p>
			<p><?php esc_html_e('For Tumtook Catalog API, choose the preset then fill only API URL and Header Value.', 'tumtook-gallery'); ?>
			</p>
			<p><a href="<?php echo esc_url($clear_cache_url); ?>"
					class="button button-secondary"><?php esc_html_e('Clear Gallery Cache', 'tumtook-gallery'); ?></a></p>
			<form method="post" action="options.php">
				<?php
				settings_fields('tumtook_gallery_group');
				do_settings_sections('tumtook-gallery');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_meta_box($post)
	{
		$settings = $this->get_page_settings($post->ID);
		$fields = array(
			'api_url',
			'cache_minutes',
			'items_path',
			'match_code',
			'image_key',
			'alt_key',
		);

		wp_nonce_field('tumtook_gallery_save_page_settings', 'tumtook_gallery_nonce');
		echo '<p>' . esc_html__('This page uses its own Tumtook Gallery data source. Add [tumtook_gallery] in this page content to render this page settings.', 'tumtook-gallery') . '</p>';
		echo '<p>' . esc_html__('Use a full API URL including x-api-key in the query string. Bearer token and custom headers are no longer needed here.', 'tumtook-gallery') . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ($fields as $field) {
			$label = 'match_code' === $field ? __('Item Code Filter', 'tumtook-gallery') : ucwords(str_replace('_', ' ', $field));
			echo '<tr>';
			echo '<th scope="row"><label>' . esc_html($label) . '</label></th>';
			echo '<td>';
			$this->render_field(array('key' => $field), $settings);
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		?>
		<div class="ttg-admin-preview">
			<h4><?php esc_html_e('Preview', 'tumtook-gallery'); ?></h4>
			<p class="description">
				<?php esc_html_e('When the API settings are correct, preview images will appear here automatically.', 'tumtook-gallery'); ?>
			</p>
			<div class="ttg-admin-preview-status" data-ttg-preview-status>
				<?php esc_html_e('Fill in the fields above to preview images.', 'tumtook-gallery'); ?>
			</div>
			<div class="ttg-admin-preview-grid" data-ttg-preview-grid></div>
		</div>
		<style>
			.ttg-admin-preview,
			.ttg-admin-preview button,
			.ttg-admin-preview input,
			.ttg-admin-preview select,
			.ttg-admin-preview textarea {
				font-family: "Kanit", sans-serif
			}

			.ttg-admin-preview {
				margin-top: 18px;
				padding-top: 18px;
				border-top: 1px solid #dcdcde;
			}

			.ttg-admin-preview-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
				gap: 12px;
				margin-top: 12px;
			}

			.ttg-admin-preview-card {
				border: 1px solid #dcdcde;
				border-radius: 12px;
				overflow: hidden;
				background: #fff;
			}

			.ttg-admin-preview-card img {
				display: block;
				width: 100%;
				aspect-ratio: 1 / 1;
				object-fit: cover;
				background: #f6f7f7;
			}

			.ttg-admin-preview-title {
				padding: 8px 10px 10px;
				font-size: 12px;
				line-height: 1.4;
				font-weight: 600;
				word-break: break-word;
			}

			.ttg-admin-preview-status {
				color: #50575e;
			}
		</style>
		<script>
			(function () {
				var root = document.currentScript.parentElement;
				var statusNode = root.querySelector('[data-ttg-preview-status]');
				var gridNode = root.querySelector('[data-ttg-preview-grid]');
				var wrap = root.closest('.postbox');
				var timer;
				if (!wrap) return;

				function fieldValue(key) {
					var el = wrap.querySelector('[name="<?php echo esc_js(self::META_KEY); ?>[' + key + ']"]');
					return el ? el.value.trim() : '';
				}

				function renderItems(items) {
					gridNode.innerHTML = '';
					items.forEach(function (item) {
						var card = document.createElement('div');
						var image = document.createElement('img');
						var title = document.createElement('div');

						card.className = 'ttg-admin-preview-card';
						image.src = item.image;
						image.alt = item.alt || item.title || '';
						image.loading = 'lazy';
						title.className = 'ttg-admin-preview-title';
						title.textContent = item.title || '';

						card.appendChild(image);
						card.appendChild(title);
						gridNode.appendChild(card);
					});
				}

				function requestPreview() {
					var apiUrl = fieldValue('api_url');
					var itemsPath = fieldValue('items_path');
					var matchCode = fieldValue('match_code');
					var imageKey = fieldValue('image_key');
					var altKey = fieldValue('alt_key');
					var cacheMinutes = fieldValue('cache_minutes') || '1';

					if (!apiUrl || !itemsPath || !imageKey) {
						statusNode.textContent = 'Fill in API URL, Items Path, and Image Key to preview images.';
						gridNode.innerHTML = '';
						return;
					}

					statusNode.textContent = 'Loading preview...';

					var body = new URLSearchParams();
					body.set('action', 'ttg_preview_items');
					body.set('_ajax_nonce', '<?php echo esc_js(wp_create_nonce('ttg_preview_items')); ?>');
					body.set('page_id', '<?php echo (int) $post->ID; ?>');
					body.set('api_url', apiUrl);
					body.set('items_path', itemsPath);
					body.set('match_code', matchCode);
					body.set('image_key', imageKey);
					body.set('alt_key', altKey);
					body.set('cache_minutes', cacheMinutes);

					fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
						},
						body: body.toString(),
						credentials: 'same-origin'
					})
						.then(function (response) {
							if (!response.ok) {
								throw new Error('Preview request failed');
							}
							return response.json();
						})
						.then(function (payload) {
							if (!payload.success) {
								throw new Error(payload.data && payload.data.message ? payload.data.message : 'Could not preview images.');
							}

							if (!payload.data.items.length) {
								statusNode.textContent = 'No preview images found.';
								gridNode.innerHTML = '';
								return;
							}

							statusNode.textContent = 'Preview loaded.';
							renderItems(payload.data.items);
						})
						.catch(function (error) {
							statusNode.textContent = error.message || 'Could not preview images.';
							gridNode.innerHTML = '';
						});
				}

				function queuePreview(immediate) {
					window.clearTimeout(timer);
					if (immediate) {
						requestPreview();
						return;
					}
					timer = window.setTimeout(requestPreview, 180);
				}

				wrap.querySelectorAll('input').forEach(function (input) {
					input.addEventListener('input', queuePreview);
					input.addEventListener('change', queuePreview);
				});

				queuePreview(true);
			})();
		</script>
		<?php
	}

	public function save_page_settings($post_id)
	{
		if (!isset($_POST['tumtook_gallery_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tumtook_gallery_nonce'])), 'tumtook_gallery_save_page_settings')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_page', $post_id)) {
			return;
		}

		$input = isset($_POST[self::META_KEY]) ? wp_unslash($_POST[self::META_KEY]) : array();
		update_post_meta($post_id, self::META_KEY, $this->sanitize_settings(is_array($input) ? $input : array()));
	}

	public function ajax_preview_items()
	{
		check_ajax_referer('ttg_preview_items');

		if (!current_user_can('edit_pages')) {
			wp_send_json_error(array('message' => __('You do not have permission to preview this gallery.', 'tumtook-gallery')), 403);
		}

		$input = array();
		$input['api_url'] = isset($_POST['api_url']) ? wp_unslash($_POST['api_url']) : '';
		$input['items_path'] = isset($_POST['items_path']) ? wp_unslash($_POST['items_path']) : '';
		$input['match_code'] = isset($_POST['match_code']) ? wp_unslash($_POST['match_code']) : '';
		$input['image_key'] = isset($_POST['image_key']) ? wp_unslash($_POST['image_key']) : '';
		$input['alt_key'] = isset($_POST['alt_key']) ? wp_unslash($_POST['alt_key']) : '';
		$input['cache_minutes'] = isset($_POST['cache_minutes']) ? wp_unslash($_POST['cache_minutes']) : '1';

		$page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
		$settings = $this->sanitize_settings($input);
		$items = $this->get_gallery_items($settings['api_url'], $settings, 8, $page_id);

		if (is_wp_error($items)) {
			wp_send_json_error(array('message' => $items->get_error_message()), 400);
		}

		wp_send_json_success(array('items' => array_values($items)));
	}

	public function render_shortcode($atts)
	{
		$this->register_assets();

		$page_id = get_the_ID();
		$settings = $this->get_page_settings($page_id);
		$atts = shortcode_atts(
			array(
				'limit' => 0,
				'columns' => 6,
				'gap' => 12,
				'endpoint' => '',
			),
			$atts,
			self::SHORTCODE
		);

		$endpoint = !empty($atts['endpoint']) ? esc_url_raw($atts['endpoint']) : $settings['api_url'];
		if (empty($endpoint)) {
			return $this->render_message(__('Please configure Tumtook Gallery in this page settings.', 'tumtook-gallery'));
		}

		wp_enqueue_style('tumtook-gallery');
		wp_enqueue_script('tumtook-gallery');

		$columns = min(12, max(6, absint($atts['columns'])));
		$gap = max(0, absint($atts['gap']));
		$limit = absint($atts['limit']);

		ob_start();
		?>
		<div class="ttg-gallery-shell" data-page-id="<?php echo esc_attr($page_id); ?>"
			data-endpoint="<?php echo esc_url($endpoint); ?>" data-limit="<?php echo esc_attr($limit); ?>"
			data-columns="<?php echo esc_attr($columns); ?>" data-gap="<?php echo esc_attr($gap); ?>">
			<div class="ttg-gallery"
				style="--ttg-columns: <?php echo esc_attr($columns); ?>; --ttg-gap: <?php echo esc_attr($gap); ?>px;"></div>
			<div class="ttg-end-panel" aria-hidden="true"></div>
			<div class="ttg-loader" aria-live="polite"><?php esc_html_e('Loading images...', 'tumtook-gallery'); ?></div>
			<div class="ttg-sentinel" aria-hidden="true"></div>
		</div>
		<?php

		return ob_get_clean();
	}

	public function rest_get_items(WP_REST_Request $request)
	{
		$page_id = absint($request->get_param('page_id'));
		$settings = $this->get_page_settings($page_id);
		$endpoint = esc_url_raw($request->get_param('endpoint'));
		$limit = absint($request->get_param('limit'));
		$page = max(1, absint($request->get_param('page')));
		$per_page = min(48, max(1, absint($request->get_param('per_page'))));

		if (empty($endpoint)) {
			return new WP_Error('ttg_missing_endpoint', __('Missing gallery endpoint.', 'tumtook-gallery'), array('status' => 400));
		}

		$gallery_items = $this->get_gallery_items($endpoint, $settings, $limit, $page_id);
		if (is_wp_error($gallery_items)) {
			return $gallery_items;
		}

		$total = count($gallery_items);
		$offset = ($page - 1) * $per_page;
		$items = array_slice($gallery_items, $offset, $per_page);

		return rest_ensure_response(
			array(
				'items' => array_values($items),
				'page' => $page,
				'per_page' => $per_page,
				'total' => $total,
				'has_more' => ($offset + $per_page) < $total,
			)
		);
	}

	private function get_gallery_items($endpoint, $settings, $limit, $page_id = 0)
	{
		$cache_key = 'ttg_' . md5($endpoint . wp_json_encode($settings) . $limit . self::VERSION . '_shuffle');
		$cached = get_transient($cache_key);

		if (false !== $cached) {
			return $cached;
		}

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Accept' => 'application/json',
				),
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error('ttg_request_failed', __('Could not connect to the image API.', 'tumtook-gallery'));
		}

		$status = wp_remote_retrieve_response_code($response);
		if ($status < 200 || $status >= 300) {
			return new WP_Error('ttg_bad_status', sprintf(__('Image API returned HTTP %d.', 'tumtook-gallery'), absint($status)));
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		if (JSON_ERROR_NONE !== json_last_error()) {
			return new WP_Error('ttg_invalid_json', __('The API response is not valid JSON.', 'tumtook-gallery'));
		}

		$items = $this->get_collection_by_path($data, $settings['items_path']);
		if (!is_array($items)) {
			return new WP_Error('ttg_invalid_items', __('The configured items path does not point to an array.', 'tumtook-gallery'));
		}

		$match_code = !empty($settings['match_code']) ? $settings['match_code'] : '';

		if (!empty($match_code)) {
			$items = array_values(
				array_filter(
					$items,
					function ($item) use ($match_code) {
						if (!is_array($item) || empty($item['code']) || !is_string($item['code'])) {
							return false;
						}

						return $this->codes_match($item['code'], $match_code);
					}
				)
			);

			if (empty($items)) {
				return new WP_Error('ttg_no_matching_code', __('ไม่พบข้อมูลสำหรับ Item Code Filter ที่ระบุ', 'tumtook-gallery'));
			}
		}

		$gallery_items = array();

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$link = $this->get_value_by_path($item, $settings['link_key']);
			$alt = $this->get_value_by_path($item, $settings['alt_key']);

			$images = $this->get_values_by_path($item, $settings['image_key']);
			$alts = $this->get_values_by_path($item, $settings['alt_key']);

			foreach ($images as $index => $image) {
				if (!is_string($image) || '' === trim($image)) {
					continue;
				}

				$raw_image = $image;
				$image = $this->normalize_url($image, $endpoint);
				if (empty($image)) {
					continue;
				}

				$item_alt = isset($alts[$index]) && is_scalar($alts[$index]) ? $alts[$index] : $alt;
				$item_title = $this->extract_filename_title($raw_image);

				$gallery_items[] = array(
					'image' => $image,
					'title' => is_scalar($item_title) ? wp_strip_all_tags((string) $item_title) : '',
					'link' => is_string($link) ? $this->normalize_url($link, $endpoint) : '',
					'alt' => is_scalar($item_alt) ? wp_strip_all_tags((string) $item_alt) : (is_scalar($item_title) ? wp_strip_all_tags((string) $item_title) : ''),
				);

				if ($limit > 0 && count($gallery_items) >= $limit) {
					break 2;
				}
			}
		}

		if (count($gallery_items) > 1) {
			$gallery_items = $this->shuffle_gallery_items($gallery_items);
		}

		set_transient($cache_key, $gallery_items, max(1, absint($settings['cache_minutes'])) * MINUTE_IN_SECONDS);

		return $gallery_items;
	}

	private function shuffle_gallery_items($items)
	{
		$items = array_values($items);
		if (count($items) <= 1) {
			return $items;
		}

		// Keep random visual order while preserving item structure for page pagination.
		shuffle($items);
		return $items;
	}

	private function get_page_settings($page_id)
	{
		$stored = array();

		if ($page_id > 0) {
			$stored = get_post_meta($page_id, self::META_KEY, true);
		}

		return wp_parse_args(is_array($stored) ? $stored : array(), self::get_default_settings());
	}

	private function extract_filename_title($value)
	{
		$value = is_string($value) ? trim($value) : '';
		if ('' === $value) {
			return '';
		}

		$path = wp_parse_url($value, PHP_URL_PATH);
		$basename = wp_basename($path ? $path : $value);
		$title = preg_replace('/\.[^.]+$/', '', $basename);

		return is_string($title) ? $title : '';
	}

	private function codes_match($left, $right)
	{
		$left = $this->normalize_compare_code($left);
		$right = $this->normalize_compare_code($right);

		return '' !== $left && '' !== $right && $left === $right;
	}

	private function normalize_compare_code($value)
	{
		$value = is_string($value) ? trim($value) : '';
		if ('' === $value) {
			return '';
		}

		return strtolower($value);
	}

	private function get_value_by_path($source, $path)
	{
		$values = $this->get_values_by_path($source, $path);

		if (empty($values)) {
			return null;
		}

		return reset($values);
	}

	private function get_collection_by_path($source, $path)
	{
		if ('' === $path || null === $path) {
			return is_array($source) ? $source : array();
		}

		$current = $source;
		$segments = explode('.', (string) $path);

		foreach ($segments as $segment) {
			if (is_array($current) && array_key_exists($segment, $current)) {
				$current = $current[$segment];
				continue;
			}

			return null;
		}

		return is_array($current) ? $current : null;
	}

	private function get_values_by_path($source, $path)
	{
		if ('' === $path || null === $path) {
			return is_array($source) ? array($source) : array($source);
		}

		$segments = explode('.', (string) $path);
		$results = array();
		$this->collect_path_values(array($source), $segments, 0, $results);

		return array_values(
			array_filter(
				$results,
				static function ($value) {
					return null !== $value && '' !== $value;
				}
			)
		);
	}

	private function collect_path_values($sources, $segments, $index, &$results)
	{
		if ($index >= count($segments)) {
			foreach ($sources as $source) {
				$results[] = $source;
			}
			return;
		}

		$segment = $segments[$index];

		foreach ($sources as $source) {
			if (!is_array($source)) {
				continue;
			}

			if (array_key_exists($segment, $source)) {
				$value = $source[$segment];

				if ($index + 1 >= count($segments)) {
					if (is_array($value) && $this->is_list_array($value)) {
						foreach ($value as $child_value) {
							$results[] = $child_value;
						}
					} else {
						$results[] = $value;
					}
					continue;
				}

				if (is_array($value) && $this->is_list_array($value)) {
					$this->collect_path_values($value, $segments, $index + 1, $results);
				} else {
					$this->collect_path_values(array($value), $segments, $index + 1, $results);
				}
			}
		}
	}

	private function is_list_array($value)
	{
		if (!is_array($value)) {
			return false;
		}

		return array_keys($value) === range(0, count($value) - 1);
	}

	private function normalize_url($value, $endpoint)
	{
		$value = is_string($value) ? trim($value) : '';
		if ('' === $value) {
			return '';
		}

		$parts = wp_parse_url($value);
		if (!empty($parts['scheme']) && !empty($parts['host'])) {
			$absolute = esc_url_raw($value);
			if (!empty($absolute)) {
				return $absolute;
			}
		}

		if (0 === strpos($value, '/')) {
			$endpoint_parts = wp_parse_url($endpoint);
			if (empty($endpoint_parts['scheme']) || empty($endpoint_parts['host'])) {
				return '';
			}

			$base = $endpoint_parts['scheme'] . '://' . $endpoint_parts['host'];
			if (!empty($endpoint_parts['port'])) {
				$base .= ':' . $endpoint_parts['port'];
			}

			return esc_url_raw($base . $value);
		}

		return '';
	}

	private function render_message($message)
	{
		return '<div class="ttg-notice">' . esc_html($message) . '</div>';
	}
}

register_activation_hook(__FILE__, array('Tumtook_Gallery_Plugin', 'activate'));

new Tumtook_Gallery_Plugin();
