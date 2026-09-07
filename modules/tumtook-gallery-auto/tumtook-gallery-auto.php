<?php
/**
 * Plugin Name: Tumtook Gallery Auto
 * Description: Pinterest-style gallery with automatic columns and natural image proportions. Derived from Tumtook Gallery.
 * Version: 1.0.1
 * Author: Tumtook
 * Text Domain: tumtook-gallery-auto
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Gallery_Auto_Plugin
{
	const OPTION_KEY = 'tumtook_gallery_auto_settings';
	const SHORTCODE = 'tumtook_gallery_auto';
	const META_KEY = '_tumtook_gallery_auto_settings';
	const VERSION = '1.0.1';
	const DEFAULT_LIMIT = 50;
	const FONT_HANDLE = 'tumtook-kanit-font';

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_box'));
		add_action('save_post_page', array($this, 'save_page_settings'));
		add_action('wp_ajax_ttga_preview_items', array($this, 'ajax_preview_items'));
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
			'end_panel_background' => '#f9f9f9',
		);
	}

	public function register_assets()
	{
		$this->register_kanit_font();

		wp_register_style(
			'tumtook-gallery-auto',
			plugin_dir_url(__FILE__) . 'assets/css/tumtook-gallery-auto.css',
			array(self::FONT_HANDLE),
			self::VERSION
		);

		wp_register_script(
			'tumtook-gallery-auto',
			plugin_dir_url(__FILE__) . 'assets/js/tumtook-gallery-auto.js',
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'tumtook-gallery-auto',
			'TumtookGalleryAutoData',
			array(
				'restUrl' => esc_url_raw(rest_url('tumtook-gallery-auto/v1/items')),
				'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
				'strings' => array(
					'loading' => __('Loading images...', 'tumtook-gallery-auto'),
					'empty' => __('No images found from the API response.', 'tumtook-gallery-auto'),
					'error' => __('โหลดรูปไม่สำเร็จ กรุณาลองอีกครั้ง', 'tumtook-gallery-auto'),
					'retry' => __('ลองอีกครั้ง', 'tumtook-gallery-auto'),
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
			'tumtook-gallery-auto/v1',
			'/items',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'rest_get_items'),
				'permission_callback' => array($this, 'can_view_gallery'),
				'args' => array(
					'page_id' => array(
						'default' => 0,
						'sanitize_callback' => 'absint',
					),
					'endpoint' => array(
						'default' => '',
						'sanitize_callback' => 'esc_url_raw',
					),
					'endpoint_signature' => array(
						'default' => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'default' => self::DEFAULT_LIMIT,
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
			'tumtook-gallery-auto-meta-box',
			__('Tumtook Gallery Auto', 'tumtook-gallery-auto'),
			array($this, 'render_meta_box'),
			'page',
			'normal',
			'default'
		);
	}

	private function sanitize_settings($input)
	{
		$input = array_filter($input, 'is_scalar');
		$defaults = self::get_default_settings();
		$output = array();

		$output['preset'] = isset($input['preset']) ? sanitize_text_field(trim($input['preset'])) : $defaults['preset'];
		$output['api_url'] = isset($input['api_url']) ? esc_url_raw(trim($input['api_url'])) : $defaults['api_url'];
		$output['match_code'] = isset($input['match_code']) ? sanitize_textarea_field(trim($input['match_code'])) : $defaults['match_code'];
		$output['bearer_token'] = isset($input['bearer_token']) ? sanitize_text_field($input['bearer_token']) : $defaults['bearer_token'];
		$output['header_name'] = isset($input['header_name']) ? sanitize_text_field(trim($input['header_name'])) : $defaults['header_name'];
		$output['header_value'] = isset($input['header_value']) ? sanitize_text_field(trim($input['header_value'])) : $defaults['header_value'];
		$output['cache_minutes'] = isset($input['cache_minutes']) ? max(1, absint($input['cache_minutes'])) : $defaults['cache_minutes'];
		$output['items_path'] = isset($input['items_path']) ? sanitize_text_field(trim($input['items_path'])) : $defaults['items_path'];
		$output['image_key'] = isset($input['image_key']) ? sanitize_text_field(trim($input['image_key'])) : $defaults['image_key'];
		$output['title_key'] = isset($input['title_key']) ? sanitize_text_field(trim($input['title_key'])) : $defaults['title_key'];
		$output['link_key'] = isset($input['link_key']) ? sanitize_text_field(trim($input['link_key'])) : $defaults['link_key'];
		$output['alt_key'] = isset($input['alt_key']) ? sanitize_text_field(trim($input['alt_key'])) : $defaults['alt_key'];
		$output['end_panel_background'] = isset($input['end_panel_background']) ? sanitize_hex_color(trim($input['end_panel_background'])) : $defaults['end_panel_background'];
		$output['end_panel_background'] = $output['end_panel_background'] ? $output['end_panel_background'] : $defaults['end_panel_background'];

		return $output;
	}

	public function render_field($args, $settings = null)
	{
		$key = $args['key'];
		$settings = wp_parse_args(is_array($settings) ? $settings : array(), self::get_default_settings());
		$value = isset($settings[$key]) ? $settings[$key] : '';

		$descriptions = array(
			'api_url' => __('ใส่ URL API แบบเต็ม เช่น https://line.tumtook.com/api/config/galleries?activeOnly=true&x-api-key=YOUR_API_KEY', 'tumtook-gallery-auto'),
			'match_code' => __('ใส่ Item Code ได้สูงสุด 3 โค้ด โดยคั่นด้วย comma, space หรือขึ้นบรรทัดใหม่: 1 โค้ดแสดง 50 รูป, 2 โค้ดแสดง 25/25 รูป, 3 โค้ดแสดง 25/15/10 รูป', 'tumtook-gallery-auto'),
			'cache_minutes' => __('กำหนดเวลาจำข้อมูลจาก API ไว้ในแคช หน่วยเป็นนาที แนะนำ 30 นาทีเพื่อให้หน้าเว็บโหลดเร็วขึ้น', 'tumtook-gallery-auto'),
			'items_path' => __('ตำแหน่งรายการรูปใน JSON เช่น items หรือ data.items', 'tumtook-gallery-auto'),
			'image_key' => __('ตำแหน่ง URL รูปภาพในแต่ละ item เช่น images.fileUrl รองรับข้อมูลแบบ nested array', 'tumtook-gallery-auto'),
			'alt_key' => __('ตำแหน่งข้อความ alt ของรูปภาพ เช่น images.altText', 'tumtook-gallery-auto'),
			'end_panel_background' => __('สีพื้นหลังของส่วนท้าย Gallery หลังจากโหลดรูปครบแล้ว', 'tumtook-gallery-auto'),
		);

		$type = 'text';
		if ('cache_minutes' === $key) {
			$type = 'number';
		} elseif ('end_panel_background' === $key) {
			$type = 'color';
		}

		if ('match_code' === $key) {
			printf(
				'<textarea class="large-text" rows="3" name="%1$s[%2$s]">%3$s</textarea>',
				esc_attr(self::META_KEY),
				esc_attr($key),
				esc_textarea($value)
			);
		} else {
			printf(
				'<input type="%1$s" class="regular-text" name="%2$s[%3$s]" value="%4$s" %5$s />',
				esc_attr($type),
				esc_attr(self::META_KEY),
				esc_attr($key),
				esc_attr($value),
				'cache_minutes' === $key ? 'min="1" step="1"' : ''
			);
		}

		if (isset($descriptions[$key])) {
			echo '<p class="description">' . esc_html($descriptions[$key]) . '</p>';
		}
	}

	public function render_meta_box($post)
	{
		$settings = $this->get_page_settings($post->ID);
		echo '<p>' . esc_html__('หากยังไม่เคยบันทึกโมดูลนี้ จะใช้ค่าจาก Tumtook Gallery เดิมของหน้านี้เป็นค่าเริ่มต้น หลังบันทึกแล้วจะแยกการตั้งค่าออกจากกัน', 'tumtook-gallery-auto') . '</p>';
		$fields = array(
			'api_url',
			'cache_minutes',
			'items_path',
			'match_code',
			'image_key',
			'alt_key',
			'end_panel_background',
		);
		$labels = array(
			'api_url' => __('API URL', 'tumtook-gallery-auto'),
			'cache_minutes' => __('เวลาแคช', 'tumtook-gallery-auto'),
			'items_path' => __('ตำแหน่งรายการรูป', 'tumtook-gallery-auto'),
			'match_code' => __('Item Code Filter', 'tumtook-gallery-auto'),
			'image_key' => __('ตำแหน่ง URL รูป', 'tumtook-gallery-auto'),
			'alt_key' => __('ตำแหน่ง Alt รูป', 'tumtook-gallery-auto'),
			'end_panel_background' => __('สีพื้นหลังท้าย Gallery', 'tumtook-gallery-auto'),
		);

		wp_nonce_field('tumtook_gallery_auto_save_page_settings', 'tumtook_gallery_auto_nonce');
		echo '<p>' . esc_html__('หน้านี้ใช้แหล่งข้อมูล Tumtook Gallery Auto แยกของตัวเอง ให้ใส่ shortcode [tumtook_gallery_auto] เพื่อจัดภาพแบบ Pinterest โดยปรับคอลัมน์และความสูงตามสัดส่วนภาพอัตโนมัติ', 'tumtook-gallery-auto') . '</p>';
		echo '<p>' . esc_html__('แนะนำให้ใส่ API URL แบบเต็มที่มี x-api-key อยู่ใน query string แล้ว ไม่ต้องใส่ Bearer token หรือ custom header เพิ่ม', 'tumtook-gallery-auto') . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ($fields as $field) {
			$label = isset($labels[$field]) ? $labels[$field] : ucwords(str_replace('_', ' ', $field));
			echo '<tr>';
			echo '<th scope="row"><label>' . esc_html($label) . '</label></th>';
			echo '<td>';
			$this->render_field(array('key' => $field), $settings);
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
			?>
			<div class="ttga-admin-preview">
				<h4><?php esc_html_e('ตัวอย่างรูป', 'tumtook-gallery-auto'); ?></h4>
				<p class="description">
					<?php esc_html_e('ถ้าตั้งค่า API ถูกต้อง ระบบจะแสดงตัวอย่างรูปให้อัตโนมัติ', 'tumtook-gallery-auto'); ?>
				</p>
				<div class="ttga-admin-preview-status" data-ttga-preview-status>
					<?php esc_html_e('กรอกข้อมูลด้านบนเพื่อดูตัวอย่างรูป', 'tumtook-gallery-auto'); ?>
				</div>
			<div class="ttga-admin-preview-grid" data-ttga-preview-grid></div>
		</div>
		<style>
			.ttga-admin-preview,
			.ttga-admin-preview button,
			.ttga-admin-preview input,
			.ttga-admin-preview select,
			.ttga-admin-preview textarea {
				font-family: "Kanit", sans-serif
			}

			.ttga-admin-preview {
				margin-top: 18px;
				padding-top: 18px;
				border-top: 1px solid #dcdcde;
			}

			.ttga-admin-preview-grid {
				column-width: 140px;
				gap: 12px;
				margin-top: 12px;
			}

			.ttga-admin-preview-card {
				break-inside: avoid;
				margin-bottom: 12px;
				border: 1px solid #dcdcde;
				border-radius: 12px;
				overflow: hidden;
				background: #fff;
			}

			.ttga-admin-preview-card img {
				display: block;
				width: 100%;
				height: auto;
				background: #f6f7f7;
			}

			.ttga-admin-preview-title {
				padding: 8px 10px 10px;
				font-size: 12px;
				line-height: 1.4;
				font-weight: 600;
				word-break: break-word;
			}

			.ttga-admin-preview-status {
				color: #50575e;
			}
		</style>
		<script>
			(function () {
				var root = document.currentScript.parentElement;
				var statusNode = root.querySelector('[data-ttga-preview-status]');
				var gridNode = root.querySelector('[data-ttga-preview-grid]');
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

						card.className = 'ttga-admin-preview-card';
						image.src = item.image;
						image.alt = item.alt || item.title || '';
						image.loading = 'lazy';
						title.className = 'ttga-admin-preview-title';
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

					if (!apiUrl || !imageKey) {
							statusNode.textContent = 'กรุณากรอก API URL และตำแหน่ง URL รูป เพื่อดูตัวอย่าง';
							gridNode.innerHTML = '';
							return;
						}

						statusNode.textContent = 'กำลังโหลดตัวอย่างรูป...';

					var body = new URLSearchParams();
					body.set('action', 'ttga_preview_items');
					body.set('_ajax_nonce', '<?php echo esc_js(wp_create_nonce('ttga_preview_items')); ?>');
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
									throw new Error('โหลดตัวอย่างรูปไม่สำเร็จ');
								}
							return response.json();
						})
						.then(function (payload) {
								if (!payload.success) {
									throw new Error(payload.data && payload.data.message ? payload.data.message : 'ไม่สามารถแสดงตัวอย่างรูปได้');
								}

								if (!payload.data.items.length) {
									statusNode.textContent = 'ไม่พบรูปสำหรับแสดงตัวอย่าง';
									gridNode.innerHTML = '';
									return;
								}

								statusNode.textContent = 'โหลดตัวอย่างรูปเรียบร้อยแล้ว';
								renderItems(payload.data.items);
							})
							.catch(function (error) {
								statusNode.textContent = error.message || 'ไม่สามารถแสดงตัวอย่างรูปได้';
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

					wrap.querySelectorAll('input, textarea').forEach(function (field) {
						field.addEventListener('input', function () { queuePreview(false); });
						field.addEventListener('change', function () { queuePreview(false); });
					});

				queuePreview(true);
			})();
		</script>
		<?php
	}

	public function save_page_settings($post_id)
	{
		if (!isset($_POST['tumtook_gallery_auto_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tumtook_gallery_auto_nonce'])), 'tumtook_gallery_auto_save_page_settings')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_page', $post_id)) {
			return;
		}

		if (!isset($_POST[self::META_KEY]) || !is_array($_POST[self::META_KEY])) {
			return;
		}

		$input = isset($_POST[self::META_KEY]) ? wp_unslash($_POST[self::META_KEY]) : array();
		update_post_meta($post_id, self::META_KEY, $this->sanitize_settings(is_array($input) ? $input : array()));
	}

	public function ajax_preview_items()
	{
		check_ajax_referer('ttga_preview_items');

		$page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
		if (!$page_id || !current_user_can('edit_post', $page_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to preview this gallery.', 'tumtook-gallery-auto')), 403);
		}

		$input = array();
		$input['api_url'] = isset($_POST['api_url']) ? wp_unslash($_POST['api_url']) : '';
		$input['items_path'] = isset($_POST['items_path']) ? wp_unslash($_POST['items_path']) : '';
		$input['match_code'] = isset($_POST['match_code']) ? wp_unslash($_POST['match_code']) : '';
		$input['image_key'] = isset($_POST['image_key']) ? wp_unslash($_POST['image_key']) : '';
		$input['alt_key'] = isset($_POST['alt_key']) ? wp_unslash($_POST['alt_key']) : '';
		$input['cache_minutes'] = isset($_POST['cache_minutes']) ? wp_unslash($_POST['cache_minutes']) : '1';

		$settings = $this->sanitize_settings($input);
		$items = $this->get_gallery_items($settings['api_url'], $settings, self::DEFAULT_LIMIT, $page_id);

		if (is_wp_error($items)) {
			wp_send_json_error(array('message' => $items->get_error_message()), 400);
		}

		wp_send_json_success(array('items' => array_values(array_slice($items, 0, 8))));
	}

	public function render_shortcode($atts)
	{
		$this->register_assets();

		$page_id = get_the_ID();
		$settings = $this->get_page_settings($page_id);
		$atts = shortcode_atts(
			array(
				'limit' => self::DEFAULT_LIMIT,
				'columns' => 'auto',
				'min_width' => 220,
				'gap' => 16,
				'radius' => 16,
				'endpoint' => '',
			),
			$atts,
			self::SHORTCODE
		);

		$endpoint = !empty($atts['endpoint']) ? esc_url_raw($atts['endpoint']) : '';
		wp_enqueue_style('tumtook-gallery-auto');
		if (empty($endpoint) && empty($settings['api_url'])) {
			return $this->render_message(__('Please configure Tumtook Gallery Auto in this page settings.', 'tumtook-gallery-auto'));
		}

		wp_enqueue_script('tumtook-gallery-auto');

		$columns = 'auto' === strtolower(trim((string) $atts['columns'])) || !absint($atts['columns']) ? 0 : min(12, max(1, absint($atts['columns'])));
		$min_width = min(640, max(140, absint($atts['min_width'])));
		$gap = min(64, absint($atts['gap']));
		$radius = min(100, absint($atts['radius']));
		$limit = $this->normalize_gallery_limit($atts['limit']);
		$end_panel_background = sanitize_hex_color($settings['end_panel_background']) ?: '#f9f9f9';
		// Only shortcode-authored overrides may replace the configured server-side URL.
		$endpoint_signature = $endpoint ? wp_hash($page_id . '|' . $endpoint, 'auth') : '';

		ob_start();
		?>
		<div class="ttga-gallery-shell" data-page-id="<?php echo esc_attr($page_id); ?>"
			data-endpoint="<?php echo esc_url($endpoint); ?>" data-limit="<?php echo esc_attr($limit); ?>"
			data-endpoint-signature="<?php echo esc_attr($endpoint_signature); ?>"
			data-columns="<?php echo esc_attr($columns); ?>" data-gap="<?php echo esc_attr($gap); ?>"
			data-min-width="<?php echo esc_attr($min_width); ?>"
			style="--ttga-min-width: <?php echo esc_attr($min_width); ?>px; --ttga-gap: <?php echo esc_attr($gap); ?>px; --ttga-radius: <?php echo esc_attr($radius); ?>px; --ttga-end-panel-background: <?php echo esc_attr($end_panel_background); ?>;">
			<div class="ttga-gallery" role="list" aria-label="<?php esc_attr_e('แกลเลอรีรูปภาพ', 'tumtook-gallery-auto'); ?>"></div>
			<div class="ttga-end-panel" aria-hidden="true"></div>
			<div class="ttga-loader" aria-live="polite"><?php esc_html_e('Loading images...', 'tumtook-gallery-auto'); ?></div>
			<button class="ttga-retry" type="button" hidden><?php esc_html_e('ลองอีกครั้ง', 'tumtook-gallery-auto'); ?></button>
			<div class="ttga-sentinel" aria-hidden="true"></div>
			<noscript><?php esc_html_e('กรุณาเปิด JavaScript เพื่อดูแกลเลอรีรูปภาพ', 'tumtook-gallery-auto'); ?></noscript>
		</div>
		<?php

		return ob_get_clean();
	}

	public function can_view_gallery(WP_REST_Request $request)
	{
		$page_id = absint($request->get_param('page_id'));
		$post = get_post($page_id);
		if (!$page_id || !$post) {
			return false;
		}

		return current_user_can('edit_post', $page_id) || (is_post_publicly_viewable($post) && !post_password_required($post));
	}

	public function rest_get_items(WP_REST_Request $request)
	{
		$page_id = absint($request->get_param('page_id'));
		$settings = $this->get_page_settings($page_id);
		$endpoint = esc_url_raw($request->get_param('endpoint'));
		$limit = $this->normalize_gallery_limit($request->get_param('limit'));
		$page = max(1, absint($request->get_param('page')));
		$per_page = min(24, max(1, absint($request->get_param('per_page'))));

		if ($endpoint) {
			$signature = (string) $request->get_param('endpoint_signature');
			if (!hash_equals(wp_hash($page_id . '|' . $endpoint, 'auth'), $signature)) {
				return new WP_Error('ttga_invalid_endpoint', __('Invalid gallery endpoint.', 'tumtook-gallery-auto'), array('status' => 403));
			}
		} else {
			$endpoint = $settings['api_url'];
		}

		if (empty($endpoint)) {
			return new WP_Error('ttga_missing_endpoint', __('Missing gallery endpoint.', 'tumtook-gallery-auto'), array('status' => 400));
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
		$limit = $this->normalize_gallery_limit($limit);
		$match_codes = $this->get_match_codes(!empty($settings['match_code']) ? $settings['match_code'] : '');

		if (empty($match_codes)) {
			return array();
		}

		$cache_key = 'ttga_' . md5($endpoint . wp_json_encode($settings) . $limit . self::VERSION . '_multi_code_mixed_allocations');
		$cached = get_transient($cache_key);

		if (false !== $cached) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Accept' => 'application/json',
				),
				'timeout' => 5,
				'redirection' => 2,
			)
		);

		if (is_wp_error($response)) {
			$error = new WP_Error('ttga_request_failed', __('Could not connect to the image API.', 'tumtook-gallery-auto'));
			set_transient($cache_key, $error, 2 * MINUTE_IN_SECONDS);
			return $error;
		}

		$status = wp_remote_retrieve_response_code($response);
		if ($status < 200 || $status >= 300) {
			$error = new WP_Error('ttga_bad_status', sprintf(__('Image API returned HTTP %d.', 'tumtook-gallery-auto'), absint($status)));
			set_transient($cache_key, $error, 2 * MINUTE_IN_SECONDS);
			return $error;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		if (JSON_ERROR_NONE !== json_last_error()) {
			$error = new WP_Error('ttga_invalid_json', __('The API response is not valid JSON.', 'tumtook-gallery-auto'));
			set_transient($cache_key, $error, 2 * MINUTE_IN_SECONDS);
			return $error;
		}

		$items = $this->get_collection_by_path($data, $settings['items_path']);
		if (!is_array($items)) {
			$error = new WP_Error('ttga_invalid_items', __('The configured items path does not point to an array.', 'tumtook-gallery-auto'));
			set_transient($cache_key, $error, 2 * MINUTE_IN_SECONDS);
			return $error;
		}

		$gallery_items = array();
		$seen_images = array();
		$allocations = $this->get_match_code_allocations(count($match_codes), $limit);

		foreach ($match_codes as $code_index => $match_code) {
			$remaining = $limit - count($gallery_items);
			if ($remaining <= 0) {
				break;
			}

			$this->append_gallery_items_from_items(
				$items,
				$endpoint,
				$settings,
				$gallery_items,
				$seen_images,
				min($allocations[$code_index], $remaining),
				'filter-' . ($code_index + 1),
				true,
				$match_code
			);
		}

		if (empty($gallery_items)) {
			return new WP_Error('ttga_no_matching_code', __('ไม่พบข้อมูลสำหรับ Item Code Filter ที่ระบุ', 'tumtook-gallery-auto'));
		}

		if (count($gallery_items) > 1) {
			shuffle($gallery_items);
		}

		set_transient($cache_key, $gallery_items, max(1, absint($settings['cache_minutes'])) * MINUTE_IN_SECONDS);

		return $gallery_items;
	}

	private function normalize_gallery_limit($limit)
	{
		$limit = absint($limit);

		if ($limit <= 0) {
			return self::DEFAULT_LIMIT;
		}

		return min($limit, self::DEFAULT_LIMIT);
	}

	private function get_match_codes($value)
	{
		$parts = preg_split('/[\s,;|]+/', (string) $value);
		$codes = array();
		$seen = array();

		foreach ($parts as $part) {
			$code = trim((string) $part);
			if ('' === $code) {
				continue;
			}

			$normalized = $this->normalize_compare_code($code);
			if ('' === $normalized || isset($seen[$normalized])) {
				continue;
			}

			$codes[] = $code;
			$seen[$normalized] = true;

			if (count($codes) >= 3) {
				break;
			}
		}

		return $codes;
	}

	private function get_match_code_allocations($code_count, $limit)
	{
		$code_count = max(1, min(3, absint($code_count)));
		$limit = $this->normalize_gallery_limit($limit);

		if (1 === $code_count) {
			$allocations = array(self::DEFAULT_LIMIT);
		} elseif (2 === $code_count) {
			$allocations = array(25, 25);
		} else {
			$allocations = array(25, 15, 10);
		}

		$total = 0;
		foreach ($allocations as $index => $allocation) {
			$remaining = max(0, $limit - $total);
			$allocations[$index] = min($allocation, $remaining);
			$total += $allocations[$index];
		}

		return $allocations;
	}

	private function append_gallery_items_from_items($items, $endpoint, $settings, &$gallery_items, &$seen_images, $max_items, $group = 'primary', $randomize_images = false, $match_code = '')
	{
		$max_items = absint($max_items);
		if ($max_items <= 0) {
			return;
		}

		$candidates = $this->collect_gallery_item_candidates($items, $endpoint, $settings, $group, $max_items, $randomize_images, $match_code);

		foreach ($candidates as $gallery_item) {
			$item_key = isset($gallery_item['key']) ? (string) $gallery_item['key'] : '';
			if ('' === $item_key || isset($seen_images[$item_key])) {
				continue;
			}

			$seen_images[$item_key] = true;
			$gallery_items[] = $gallery_item;

			if ($max_items <= 1) {
				return;
			}

			$max_items--;
		}
	}

	private function collect_gallery_item_candidates($items, $endpoint, $settings, $group, $max_items, $randomize_images, $match_code = '')
	{
		$candidates = array();
		$candidate_keys = array();
		$total_seen = 0;
		$max_items = absint($max_items);

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			if (!$this->item_matches_code($item, $match_code)) {
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

				$item_key = $this->get_gallery_item_key($image);
				if (isset($candidate_keys[$item_key])) {
					continue;
				}

				$item_alt = isset($alts[$index]) && is_scalar($alts[$index]) ? $alts[$index] : $alt;
				$item_title = $this->extract_filename_title($raw_image);
				$item_dimensions = $this->get_item_image_dimensions($item, $index, $settings['image_key']);

				$candidate = array(
					'key' => $item_key,
					'image' => $image,
					'title' => is_scalar($item_title) ? wp_strip_all_tags((string) $item_title) : '',
					'link' => is_string($link) ? $this->normalize_url($link, $endpoint) : '',
					'alt' => is_scalar($item_alt) ? wp_strip_all_tags((string) $item_alt) : (is_scalar($item_title) ? wp_strip_all_tags((string) $item_title) : ''),
					'width' => $item_dimensions['width'],
					'height' => $item_dimensions['height'],
					'group' => $group,
				);
				$candidate_keys[$item_key] = true;

				if (!$randomize_images) {
					$candidates[] = $candidate;
					if ($max_items > 0 && count($candidates) >= $max_items) {
						return $candidates;
					}
					continue;
				}

				$total_seen++;
				if (count($candidates) < $max_items) {
					$candidates[] = $candidate;
					continue;
				}

				$replace_index = wp_rand(0, $total_seen - 1);
				if ($replace_index < $max_items) {
					$candidates[$replace_index] = $candidate;
				}
			}
		}

		if ($randomize_images && count($candidates) > 1) {
			shuffle($candidates);
		}

		return $candidates;
	}

	private function item_matches_code($item, $match_code)
	{
		$item_code = isset($item['code']) && is_string($item['code']) ? $item['code'] : '';
		return $this->codes_match($item_code, $match_code);
	}

	private function get_gallery_item_key($image)
	{
		return md5(trim((string) $image));
	}

	private function get_item_image_dimensions($item, $index, $image_key = '')
	{
		// API galleries commonly keep dimensions beside images.fileUrl.
		$separator = strrpos($image_key, '.');
		if (false !== $separator) {
			$prefix = substr($image_key, 0, $separator) . '.';
			$nested_width = $this->get_indexed_dimension_value($item, array($prefix . 'width', $prefix . 'dimensions.width'), $index);
			$nested_height = $this->get_indexed_dimension_value($item, array($prefix . 'height', $prefix . 'dimensions.height'), $index);
			if ($nested_width && $nested_height) {
				return array('width' => $nested_width, 'height' => $nested_height);
			}
		}

		$width = $this->get_indexed_dimension_value(
			$item,
			array(
				'width',
				'w',
				'image_width',
				'imageWidth',
				'original_width',
				'originalWidth',
				'dimensions.width',
				'image.width',
				'image.meta.width',
				'media_details.width',
				'mediaDetails.width',
				'metadata.width',
				'meta.width',
				'sizes.full.width',
				'sizes.large.width',
				'media_details.sizes.full.width',
				'media_details.sizes.large.width',
			),
			$index
		);
		$height = $this->get_indexed_dimension_value(
			$item,
			array(
				'height',
				'h',
				'image_height',
				'imageHeight',
				'original_height',
				'originalHeight',
				'dimensions.height',
				'image.height',
				'image.meta.height',
				'media_details.height',
				'mediaDetails.height',
				'metadata.height',
				'meta.height',
				'sizes.full.height',
				'sizes.large.height',
				'media_details.sizes.full.height',
				'media_details.sizes.large.height',
			),
			$index
		);

		return array(
			'width' => $width,
			'height' => $height,
		);
	}

	private function get_indexed_dimension_value($item, $keys, $index)
	{
		foreach ($keys as $key) {
			if (!is_array($item)) {
				continue;
			}

			$values = false === strpos($key, '.')
				? (array_key_exists($key, $item) ? array($item[$key]) : array())
				: $this->get_values_by_path($item, $key);

			if (empty($values)) {
				continue;
			}

			$value = isset($values[$index]) ? $values[$index] : reset($values);
			if (is_array($value)) {
				$value = isset($value[$index]) ? $value[$index] : reset($value);
			}

			if (is_numeric($value) && (float) $value > 0) {
				return (float) $value;
			}
		}

		return 0;
	}

	private function get_page_settings($page_id)
	{
		$stored = array();

		if ($page_id > 0) {
			$stored = get_post_meta($page_id, self::META_KEY, true);
			if (!metadata_exists('post', $page_id, self::META_KEY)) {
				$stored = get_post_meta($page_id, '_tumtook_gallery_settings', true);
			}
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
		return '<div class="ttga-notice">' . esc_html($message) . '</div>';
	}
}

register_activation_hook(__FILE__, array('Tumtook_Gallery_Auto_Plugin', 'activate'));

new Tumtook_Gallery_Auto_Plugin();
