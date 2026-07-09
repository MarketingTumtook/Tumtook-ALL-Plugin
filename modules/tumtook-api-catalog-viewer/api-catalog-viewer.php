<?php
/**
 * Plugin Name: Tumtook Catalog Image Data
 * Description: Fetch and display image catalog data from API inside WordPress pages.
 * Version: 1.1.14
 * Author: Tumtook
 * Text Domain: api-catalog-images-viewer
 */

if (!defined('ABSPATH')) {
	exit;
}

final class API_Catalog_Images_Plugin
{
	const OPTION_KEY = 'catalog_images_settings';
	const META_KEY = '_tumtook_catalog_images_page_settings';
	const ITEM_CONTENT_META_KEY = '_tumtook_catalog_images_item_content';
	const SHORTCODE = 'catalog_images';
	const VERSION = '1.1.14';
	const NONCE_KEY = 'tumtook_catalog_images_nonce';
	const FONT_HANDLE = 'tumtook-kanit-font';

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_page_meta_box'));
		add_action('save_post_page', array($this, 'save_page_settings'));
		add_action('wp_ajax_tumtook_catalog_images_preview', array($this, 'ajax_preview_page_images'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
		add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
	}

	public static function activate()
	{
		if (!get_option(self::OPTION_KEY)) {
			add_option(self::OPTION_KEY, self::get_default_settings());
		}
	}

	public static function get_default_settings()
	{
		return array(
			'api_url' => '',
			'items_path' => 'items',
			'item_code_filter' => '',
			'image_key' => 'images.*.fileUrl',
			'title_key' => 'cate',
			'alt_key' => 'images.*.altText',
			'cache_minutes' => 15,
		);
	}

	public function enqueue_admin_assets($hook)
	{
		if ('post.php' !== $hook && 'post-new.php' !== $hook) {
			return;
		}

		if (in_array($hook, array('post.php', 'post-new.php'), true)) {
			$screen = get_current_screen();
			if (!$screen || 'page' !== $screen->post_type) {
				return;
			}
		}

		$this->enqueue_kanit_font();

		wp_enqueue_style(
			'api-catalog-images-viewer-admin',
			plugin_dir_url(__FILE__) . 'assets/css/admin.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/admin.css')
		);

		wp_enqueue_script(
			'api-catalog-images-viewer-admin',
			plugin_dir_url(__FILE__) . 'assets/js/admin.js',
			array(),
			$this->get_asset_version('assets/js/admin.js'),
			true
		);

		wp_localize_script(
			'api-catalog-images-viewer-admin',
			'TumtookApiImageViewerAdmin',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('tumtook_catalog_images_preview'),
				'failureText' => __('ดึงไม่สําเร็จ', 'api-catalog-images-viewer'),
				'emptyUrlText' => __('ใส่ API URL แล้วระบบจะลองโหลดรูปในส่วนนี้ทันที', 'api-catalog-images-viewer'),
				'loadingText' => __('กําลังโหลดรูป...', 'api-catalog-images-viewer'),
			)
		);
	}

	public function enqueue_frontend_assets()
	{
		$this->enqueue_kanit_font();

		wp_register_script(
			'api-catalog-images-viewer-frontend',
			plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
			array(),
			$this->get_asset_version('assets/js/frontend.js'),
			true
		);

		wp_register_style(
			'api-catalog-images-viewer-frontend',
			plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
			array(self::FONT_HANDLE),
			$this->get_asset_version('assets/css/frontend.css')
		);
	}

	private function enqueue_kanit_font()
	{
		wp_register_style(
			self::FONT_HANDLE,
			'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(self::FONT_HANDLE);
	}

	public function render_settings_page()
	{
		?>
		<div class="wrap aiv-wrap">
			<h1><?php esc_html_e('Tumtook Catalog Image Data', 'api-catalog-images-viewer'); ?></h1>
			<div class="aiv-preview-card">
				<h2><?php esc_html_e('ใช้งานปลั๊กอิน', 'api-catalog-images-viewer'); ?></h2>
				<p><?php esc_html_e('ปลั๊กอินนี้ไม่ใช้การตั้งค่า API ส่วนกลางแล้ว', 'api-catalog-images-viewer'); ?></p>
				<p><?php esc_html_e('ให้ไปที่หน้า Page ที่ต้องการ แล้วตั้งค่า API ในกล่อง Tumtook Catalog Image Data ของหน้านั้นโดยตรง', 'api-catalog-images-viewer'); ?>
				</p>
				<p><?php esc_html_e('จากนั้นวาง shortcode [catalog_images] ในหน้าเดียวกันเพื่อแสดงผล', 'api-catalog-images-viewer'); ?>
				</p>
			</div>
		</div>
		<?php
	}

	public function render_shortcode($atts)
	{
		$settings = $this->get_effective_settings_for_shortcode();
		$atts = shortcode_atts(
			array(
				'limit' => 12,
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE
		);

		if (absint($atts['post_id']) > 0) {
			$settings = $this->get_effective_settings_for_post(absint($atts['post_id']));
		}

		$content_post_id = absint($atts['post_id']);
		if ($content_post_id <= 0 && is_singular('page')) {
			$content_post_id = get_queried_object_id();
		}

		if (empty($settings['api_url'])) {
			return '<div class="aiv-message">' . esc_html__('Please configure the API URL in this Page first.', 'api-catalog-images-viewer') . '</div>';
		}

		$items = $this->fetch_images($settings, absint($atts['limit']));

		if (is_wp_error($items)) {
			return '<div class="aiv-message">' . esc_html($items->get_error_message()) . '</div>';
		}

		if (empty($items)) {
			return '<div class="aiv-message">' . esc_html__('No images available right now.', 'api-catalog-images-viewer') . '</div>';
		}

		wp_enqueue_style('api-catalog-images-viewer-frontend');
		wp_enqueue_script('api-catalog-images-viewer-frontend');

		ob_start();
		?>
		<div class="aiv-gallery">
			<?php foreach ($items as $item): ?>
				<figure class="aiv-gallery-item">
					<?php echo $this->render_item_media($item, 'frontend'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if (!empty($item['content']) || !empty($item['spec_fields'])): ?>
						<figcaption class="aiv-accordion">
							<button type="button" class="aiv-accordion-toggle" aria-expanded="false">
								<span
									class="aiv-figure-title"><?php esc_html_e('ข้อมูลสินค้าเพิ่มเติม', 'api-catalog-images-viewer'); ?></span>
								<span class="aiv-accordion-icon" aria-hidden="true"></span>
							</button>
							<div class="aiv-accordion-panel" hidden>
								<div class="aiv-accordion-panel-inner">
									<?php echo $this->render_item_detail_tabs($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</div>
						</figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function register_page_meta_box()
	{
		add_meta_box(
			'tumtook-api-catalog-images-viewer-page',
			__('ตั้งค่า Catalog Tumtook', 'api-catalog-images-viewer'),
			array($this, 'render_page_meta_box'),
			'page',
			'normal',
			'default'
		);
	}

	public function render_page_meta_box($post)
	{
		$page_settings = $this->get_page_settings($post->ID);
		$settings = wp_parse_args($page_settings, self::get_default_settings());
		$preview_items = array();
		$preview_error = '';

		wp_nonce_field('tumtook_catalog_images_save_page_settings', self::NONCE_KEY);

		if (!empty($settings['api_url'])) {
			$result = $this->fetch_images($settings, 0);
			if (is_wp_error($result)) {
				$preview_error = __('ดึงไม่สําเร็จ', 'api-catalog-images-viewer');
			} else {
				$preview_items = $result;
			}
		}

		$fields = array(
			'api_url' => __('ลิงก์ API', 'api-catalog-images-viewer'),
			'items_path' => __('เส้นทางรายการ', 'api-catalog-images-viewer'),
			'item_code_filter' => __('โค้ดรายการที่ต้องการ', 'api-catalog-images-viewer'),
			'image_key' => __('คีย์รูปภาพ', 'api-catalog-images-viewer'),
			'title_key' => __('คีย์ชื่อรายการ', 'api-catalog-images-viewer'),
			'alt_key' => __('คีย์ Alt', 'api-catalog-images-viewer'),
			'cache_minutes' => __('เวลาการแคช (นาที)', 'api-catalog-images-viewer'),
		);

		$descriptions = array(
			'api_url' => __('ใส่ API URL แบบเต็มได้เลย เช่น https://line.tumtook.com/api/config/catalogs?activeOnly=true&x-api-key=YOUR_KEY', 'api-catalog-images-viewer'),
			'items_path' => __('ค่าเริ่มต้นสำหรับ API ชุดใหม่คือ items', 'api-catalog-images-viewer'),
			'item_code_filter' => __('ใส่ item.code ที่ต้องการให้แสดงเฉพาะรายการนั้น เช่น PM, PA, XS ถ้าไม่ใส่จะแสดงทั้งหมด', 'api-catalog-images-viewer'),
			'image_key' => __('ค่าเริ่มต้นสำหรับ API ชุดใหม่คือ images.*.fileUrl เพื่อดึงทุกรูปใน images[]', 'api-catalog-images-viewer'),
			'title_key' => __('ค่าเริ่มต้นสำหรับ API ชุดใหม่คือ cate ถ้าต้องการใช้ code หรือ images.*.altText ก็เปลี่ยนได้', 'api-catalog-images-viewer'),
			'alt_key' => __('ค่าเริ่มต้นสำหรับ API ชุดใหม่คือ images.*.altText ถ้าเว้นว่างจะ fallback ไปใช้ Title Key', 'api-catalog-images-viewer'),
			'cache_minutes' => __('เวลาการ cache สำหรับหน้านี้', 'api-catalog-images-viewer'),
		);
		?>
		<div class="aiv-page-box">
			<p><?php esc_html_e('ตั้งค่า API สำหรับหน้านี้โดยเฉพาะ เมื่อหน้านี้ใช้ shortcode [catalog_images] ระบบจะดึงข้อมูลตามค่าที่ตั้งไว้ของหน้านี้', 'api-catalog-images-viewer'); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ($fields as $key => $label): ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr('aiv-page-' . $key); ?>"><?php echo esc_html($label); ?></label>
							</th>
							<td>
								<?php $type = in_array($key, array('cache_minutes'), true) ? 'number' : 'text'; ?>
								<input id="<?php echo esc_attr('aiv-page-' . $key); ?>" type="<?php echo esc_attr($type); ?>"
									class="regular-text" name="tumtook_catalog_images_page_settings[<?php echo esc_attr($key); ?>]"
									value="<?php echo esc_attr(isset($page_settings[$key]) ? $page_settings[$key] : ''); ?>"
									placeholder="<?php echo esc_attr(isset($settings[$key]) ? $settings[$key] : ''); ?>" <?php echo 'number' === $type ? 'min="1" step="1"' : ''; ?> />
								<p class="description"><?php echo esc_html($descriptions[$key]); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="aiv-preview-card">
				<h3><?php esc_html_e('ตัวอย่างข้อมูลสำหรับหน้านี้', 'api-catalog-images-viewer'); ?></h3>
				<div class="aiv-preview-content" id="aiv-preview-content">
					<?php if (empty($settings['api_url'])): ?>
						<p class="aiv-preview-message">
							<?php esc_html_e('ใส่ API URL แล้วระบบจะลองโหลดรูปในส่วนนี้ทันที', 'api-catalog-images-viewer'); ?>
						</p>
					<?php elseif (!empty($preview_error)): ?>
						<div class="notice notice-error inline aiv-preview-message">
							<p><?php echo esc_html($preview_error); ?></p>
						</div>
					<?php elseif (empty($preview_items)): ?>
						<p class="aiv-preview-message"><?php esc_html_e('ดึงไม่สําเร็จ', 'api-catalog-images-viewer'); ?></p>
					<?php else: ?>
						<?php echo $this->render_editable_preview_items($preview_items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_page_settings($post_id)
	{
		if (!isset($_POST[self::NONCE_KEY]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_KEY])), 'tumtook_catalog_images_save_page_settings')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_page', $post_id)) {
			return;
		}

		$raw_input = isset($_POST['tumtook_catalog_images_page_settings']) ? wp_unslash($_POST['tumtook_catalog_images_page_settings']) : array();
		$input = is_array($raw_input) ? $raw_input : array();
		$defaults = self::get_default_settings();
		$output = array();

		$output['api_url'] = isset($input['api_url']) ? esc_url_raw(trim($input['api_url'])) : '';
		$output['items_path'] = isset($input['items_path']) ? sanitize_text_field(trim($input['items_path'])) : '';
		$output['item_code_filter'] = isset($input['item_code_filter']) ? sanitize_text_field(trim($input['item_code_filter'])) : '';
		$output['image_key'] = isset($input['image_key']) ? sanitize_text_field(trim($input['image_key'])) : '';
		$output['title_key'] = isset($input['title_key']) ? sanitize_text_field(trim($input['title_key'])) : '';
		$output['alt_key'] = isset($input['alt_key']) ? sanitize_text_field(trim($input['alt_key'])) : '';
		$output['cache_minutes'] = isset($input['cache_minutes']) ? max(1, absint($input['cache_minutes'])) : $defaults['cache_minutes'];

		$clean_output = array_filter(
			$output,
			static function ($value) use ($defaults) {
				if (is_string($value)) {
					return '' !== $value;
				}

				return !in_array($value, array($defaults['cache_minutes']), true);
			}
		);

		if (empty($clean_output)) {
			delete_post_meta($post_id, self::META_KEY);
		} else {
			update_post_meta($post_id, self::META_KEY, $clean_output);
		}

		$raw_item_content = isset($_POST['tumtook_catalog_images_item_content']) ? wp_unslash($_POST['tumtook_catalog_images_item_content']) : array();
		$item_content = is_array($raw_item_content) ? $raw_item_content : array();
		$item_output = array();

		foreach ($item_content as $item_key => $value) {
			$item_key = sanitize_key($item_key);
			if ('' === $item_key) {
				continue;
			}

			$value = is_scalar($value) ? trim((string) $value) : '';
			if ('' === $value) {
				continue;
			}

			$item_output[$item_key] = $this->sanitize_rich_item_content($value);
		}

		if (empty($item_output)) {
			delete_post_meta($post_id, self::ITEM_CONTENT_META_KEY);
			return;
		}

		update_post_meta($post_id, self::ITEM_CONTENT_META_KEY, $item_output);
	}

	private function fetch_images($settings, $limit = 0)
	{
		$settings = wp_parse_args($settings, self::get_default_settings());
		$cache_key = 'aiv_' . md5(self::VERSION . '|' . wp_json_encode($settings) . '|' . $limit);
		$cached = get_transient($cache_key);

		if (false !== $cached) {
			return $cached;
		}

		$headers = array(
			'Accept' => 'application/json',
		);

		$response = wp_remote_get(
			$settings['api_url'],
			array(
				'headers' => $headers,
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error('aiv_request_failed', __('Could not connect to the configured API.', 'api-catalog-images-viewer'));
		}

		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code < 200 || $status_code >= 300) {
			return new WP_Error('aiv_bad_status', sprintf(__('The API returned HTTP %d.', 'api-catalog-images-viewer'), absint($status_code)));
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (JSON_ERROR_NONE !== json_last_error()) {
			return new WP_Error('aiv_invalid_json', __('The API response is not valid JSON.', 'api-catalog-images-viewer'));
		}

		$items = $this->extract_items($data, $settings['items_path']);
		if (empty($items) || !is_array($items)) {
			return new WP_Error('aiv_no_items', __('No list of images was found at the configured items path.', 'api-catalog-images-viewer'));
		}

		$images = array();
		$use_title_as_alt_fallback = empty($settings['alt_key']);
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$title = $this->get_item_title($item, $settings['title_key']);
			$item_code = $this->get_item_code($item);
			$link = $this->get_item_link($item);
			$link = is_string($link) ? esc_url_raw(trim($link)) : '';

			if (!empty($settings['item_code_filter']) && !$this->values_match($settings['item_code_filter'], $item_code)) {
				continue;
			}

			foreach ($this->expand_item_media_entries($item, $settings) as $media_entry) {
				$images[] = array(
					'image' => $media_entry['image'],
					'title' => is_scalar($title) ? wp_strip_all_tags((string) $title) : '',
					'link' => $link,
					'content' => isset($media_entry['content']) && is_scalar($media_entry['content']) ? (string) $media_entry['content'] : '',
					'spec_fields' => $this->get_item_spec_fields($media_entry['source'], $title),
					'alt' => !empty($media_entry['alt'])
						? $media_entry['alt']
						: ($use_title_as_alt_fallback && is_scalar($title) ? wp_strip_all_tags((string) $title) : ''),
				);

				if ($limit > 0 && count($images) >= $limit) {
					break 2;
				}
			}
		}

		set_transient($cache_key, $images, max(1, absint($settings['cache_minutes'])) * MINUTE_IN_SECONDS);

		return $images;
	}

	private function extract_items($data, $items_path)
	{
		$items = $this->get_value_by_path($data, $items_path);

		if (!is_array($items)) {
			return array();
		}

		if ($this->is_sequential_array($items)) {
			return $items;
		}

		return array($items);
	}

	private function get_value_by_path($source, $path)
	{
		if ('' === $path || null === $path) {
			return $source;
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

		return $current;
	}

	private function get_item_title($item, $path)
	{
		$candidates = array();

		if (!empty($path)) {
			$candidates[] = $path;
		}

		$candidates = array_merge($candidates, array('cate', 'name', 'code', 'description'));

		foreach ($candidates as $candidate) {
			$value = $this->get_value_by_path($item, $candidate);
			if (is_scalar($value) && '' !== trim((string) $value)) {
				return (string) $value;
			}
		}

		return '';
	}

	private function get_item_link($item)
	{
		foreach (array('url') as $candidate) {
			$value = $this->get_value_by_path($item, $candidate);
			if (is_scalar($value) && '' !== trim((string) $value)) {
				return (string) $value;
			}
		}

		return '';
	}

	private function get_item_code($item)
	{
		$value = $this->get_value_by_path($item, 'code');

		if (is_scalar($value) && '' !== trim((string) $value)) {
			return (string) $value;
		}

		return '';
	}

	private function get_values_by_path($source, $path)
	{
		if ('' === $path || null === $path) {
			return array($source);
		}

		$segments = explode('.', (string) $path);

		return $this->resolve_path_values($source, $segments);
	}

	private function resolve_path_values($current, $segments)
	{
		if (empty($segments)) {
			return array($current);
		}

		$segment = array_shift($segments);

		if ('*' === $segment) {
			if (!is_array($current)) {
				return array();
			}

			$results = array();
			foreach ($current as $value) {
				$results = array_merge($results, $this->resolve_path_values($value, $segments));
			}

			return $results;
		}

		if (is_array($current) && array_key_exists($segment, $current)) {
			return $this->resolve_path_values($current[$segment], $segments);
		}

		return array();
	}

	private function expand_item_media_entries($item, $settings)
	{
		$image_values = $this->get_values_by_path($item, $settings['image_key']);
		$image_items = $this->get_values_by_path($item, 'images.*');
		$alt_path = !empty($settings['alt_key']) ? $settings['alt_key'] : $settings['title_key'];
		$alt_values = $this->get_values_by_path($item, $alt_path);
		$content_values = $this->get_values_by_path($item, 'images.*.description');
		$item_content = '';
		if (isset($item['detailHtml']) && is_scalar($item['detailHtml'])) {
			$item_content = $this->normalize_detail_html((string) $item['detailHtml']);
		} elseif (isset($item['description']) && is_scalar($item['description'])) {
			$item_content = (string) $item['description'];
		}
		$entries = array();

		foreach ($image_values as $index => $image_value) {
			$image = is_string($image_value) ? $this->normalize_media_url($image_value, $settings['api_url']) : '';
			$has_image_source = isset($image_items[$index]) && is_array($image_items[$index]);
			$source = $has_image_source ? $image_items[$index] : $item;
			$alt = isset($alt_values[$index]) && is_scalar($alt_values[$index]) ? wp_strip_all_tags((string) $alt_values[$index]) : '';
			$content = '';
			if (isset($source['detailHtml']) && is_scalar($source['detailHtml'])) {
				$content = $this->normalize_detail_html((string) $source['detailHtml']);
			}
			if ('' === trim(wp_strip_all_tags($content)) && isset($content_values[$index]) && is_scalar($content_values[$index])) {
				$content = (string) $content_values[$index];
			}
			if (!$has_image_source && '' === trim(wp_strip_all_tags($content))) {
				$content = $item_content;
			}

			$entries[] = array(
				'image' => $image,
				'alt' => $alt,
				'content' => $content,
				'source' => $source,
			);
		}

		if (empty($entries)) {
			$entries[] = array(
				'image' => '',
				'alt' => '',
				'content' => '',
				'source' => $item,
			);
		}

		return $entries;
	}

	private function normalize_media_url($value, $api_url)
	{
		$value = trim((string) $value);
		if ('' === $value) {
			return '';
		}

		$parsed_value = wp_parse_url($value);
		if (!empty($parsed_value['scheme']) && !empty($parsed_value['host'])) {
			return esc_url_raw($value);
		}

		$api_parts = wp_parse_url($api_url);
		if (empty($api_parts['scheme']) || empty($api_parts['host'])) {
			return esc_url_raw($value);
		}

		$base = $api_parts['scheme'] . '://' . $api_parts['host'];
		if (!empty($api_parts['port'])) {
			$base .= ':' . $api_parts['port'];
		}

		if (0 === strpos($value, '/')) {
			return esc_url_raw($base . $value);
		}

		$path = !empty($api_parts['path']) ? trailingslashit(dirname($api_parts['path'])) : '/';

		return esc_url_raw($base . $path . ltrim($value, '/'));
	}

	private function values_match($expected_value, $actual_value)
	{
		$expected_value = trim((string) $expected_value);
		$actual_value = trim((string) $actual_value);

		if ('' === $expected_value) {
			return true;
		}

		return strtolower($expected_value) === strtolower($actual_value);
	}

	private function get_item_spec_fields($item, $title = '')
	{
		$rows = array();
		$has_spec_values = false;
		$spec_source = $this->find_item_spec_source($item);
		$values = isset($spec_source['fixedFieldValues']) && is_array($spec_source['fixedFieldValues']) ? $spec_source['fixedFieldValues'] : array();
		$visibility = isset($spec_source['fixedFieldVisibility']) && is_array($spec_source['fixedFieldVisibility']) ? $spec_source['fixedFieldVisibility'] : array();
		$extra_fields = isset($spec_source['extraFields']) && is_array($spec_source['extraFields']) ? $spec_source['extraFields'] : $this->find_first_array_by_key($item, 'extraFields');
		$fixed_map = array(
			'productType' => __('ประเภทสินค้า', 'api-catalog-images-viewer'),
			'material' => __('วัสดุ', 'api-catalog-images-viewer'),
			'size' => __('ขนาดสินค้า', 'api-catalog-images-viewer'),
			'productColor' => __('สี', 'api-catalog-images-viewer'),
		);

		if (is_scalar($title) && '' !== trim((string) $title)) {
			$rows[] = array(
				'label' => __('รหัสสินค้า/ชื่อสินค้า', 'api-catalog-images-viewer'),
				'value' => wp_strip_all_tags((string) $title),
			);
		}

		foreach ($fixed_map as $key => $label) {
			if (!$this->is_fixed_field_visible($visibility, $key) || !is_array($values) || !array_key_exists($key, $values) || !is_scalar($values[$key])) {
				continue;
			}

			$value = trim((string) $values[$key]);
			if ('' === $value) {
				continue;
			}

			$rows[] = array(
				'label' => $label,
				'value' => wp_strip_all_tags($value),
			);
			$has_spec_values = true;
		}

		if (!empty($extra_fields) && is_array($extra_fields)) {
			foreach ($extra_fields as $field) {
				if (!is_array($field) || empty($field['label']) || !isset($field['value']) || !is_scalar($field['value'])) {
					continue;
				}

				$label = trim((string) $field['label']);
				$value = trim((string) $field['value']);
				if ('' === $label || '' === $value) {
					continue;
				}

				if ($this->is_hidden_fixed_field_label($label, $visibility, $fixed_map)) {
					continue;
				}

				$rows[] = array(
					'label' => wp_strip_all_tags($label),
					'value' => wp_strip_all_tags($value),
				);
				$has_spec_values = true;
			}
		}

		return $has_spec_values ? $rows : array();
	}

	private function find_item_spec_source($source)
	{
		if (!is_array($source)) {
			return array();
		}

		if (isset($source['fixedFieldValues'], $source['fixedFieldVisibility']) && is_array($source['fixedFieldValues']) && is_array($source['fixedFieldVisibility'])) {
			return $source;
		}

		foreach ($source as $value) {
			if (!is_array($value)) {
				continue;
			}

			$match = $this->find_item_spec_source($value);
			if (!empty($match)) {
				return $match;
			}
		}

		return array();
	}

	private function is_hidden_fixed_field_label($label, $visibility, $fixed_map)
	{
		$normalized_label = $this->normalize_spec_label($label);

		foreach ($fixed_map as $key => $fixed_label) {
			if ($normalized_label !== $this->normalize_spec_label($fixed_label)) {
				continue;
			}

			return !$this->is_fixed_field_visible($visibility, $key);
		}

		return false;
	}

	private function normalize_spec_label($label)
	{
		return strtolower(preg_replace('/\s+/u', '', wp_strip_all_tags((string) $label)));
	}

	private function is_fixed_field_visible($visibility, $key)
	{
		if (!is_array($visibility) || !array_key_exists($key, $visibility)) {
			return false;
		}

		$value = $visibility[$key];
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return 1 === (int) $value;
		}

		if (is_string($value)) {
			return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
		}

		return false;
	}

	private function find_first_array_by_key($source, $target_key)
	{
		if (!is_array($source)) {
			return array();
		}

		if (array_key_exists($target_key, $source) && is_array($source[$target_key])) {
			return $source[$target_key];
		}

		foreach ($source as $value) {
			if (!is_array($value)) {
				continue;
			}

			$match = $this->find_first_array_by_key($value, $target_key);
			if (!empty($match)) {
				return $match;
			}
		}

		return array();
	}

	private function is_sequential_array($array)
	{
		if (array() === $array) {
			return true;
		}

		return array_keys($array) === range(0, count($array) - 1);
	}

	private function get_page_settings($post_id)
	{
		$settings = get_post_meta($post_id, self::META_KEY, true);

		return is_array($settings) ? $settings : array();
	}

	private function get_asset_version($relative_path)
	{
		$file_path = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');
		if (file_exists($file_path)) {
			$mtime = filemtime($file_path);
			if (false !== $mtime) {
				return (string) $mtime;
			}
		}

		return self::VERSION;
	}

	private function get_item_content_map($post_id)
	{
		$settings = get_post_meta($post_id, self::ITEM_CONTENT_META_KEY, true);

		return is_array($settings) ? $settings : array();
	}

	private function get_item_storage_key($item)
	{
		$image = isset($item['image']) ? (string) $item['image'] : '';
		$title = isset($item['title']) ? (string) $item['title'] : '';

		return sanitize_key(md5($image . '|' . $title));
	}

	private function apply_item_content_map($items, $item_content_map)
	{
		foreach ($items as $index => $item) {
			$item_key = $this->get_item_storage_key($item);
			if (isset($item_content_map[$item_key]) && '' !== trim((string) $item_content_map[$item_key])) {
				$items[$index]['content'] = (string) $item_content_map[$item_key];
			}
		}

		return $items;
	}

	private function sanitize_rich_item_content($content)
	{
		$allowed_html = wp_kses_allowed_html('post');

		if (isset($allowed_html['span'])) {
			$allowed_html['span']['style'] = true;
			$allowed_html['span']['class'] = true;
		} else {
			$allowed_html['span'] = array(
				'style' => true,
				'class' => true,
			);
		}

		if (isset($allowed_html['p'])) {
			$allowed_html['p']['style'] = true;
			$allowed_html['p']['class'] = true;
		}

		if (isset($allowed_html['strong'])) {
			$allowed_html['strong']['style'] = true;
		}

		return wp_kses($content, $allowed_html);
	}

	private function normalize_detail_html($content)
	{
		$content = trim((string) $content);
		if ('' === $content) {
			return '';
		}

		$decoded = wp_specialchars_decode($content, ENT_QUOTES);
		if ($decoded !== $content && preg_match('/<\\/?[a-z][\\s\\S]*>/i', $decoded)) {
			return $decoded;
		}

		return $content;
	}

	private function render_rich_item_content($content)
	{
		$content = $this->normalize_detail_html($content);
		if ('' === trim(wp_strip_all_tags($content))) {
			return '';
		}

		$has_html = preg_match('/<\\/?[a-z][\\s\\S]*>/i', $content);
		$content = $has_html ? $content : wpautop($content);

		return $this->sanitize_rich_item_content($content);
	}

	private function render_editable_preview_items($items)
	{
		ob_start();
		?>
		<div class="aiv-grid aiv-grid-editable">
			<?php foreach ($items as $item): ?>
				<div class="aiv-card aiv-card-editable">
					<?php echo $this->render_item_media($item, 'admin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if (!empty($item['title'])): ?>
						<p class="aiv-card-title"><?php echo esc_html($item['title']); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	private function render_item_media($item, $context = 'frontend')
	{
		$image = isset($item['image']) ? (string) $item['image'] : '';
		$alt = isset($item['alt']) ? (string) $item['alt'] : '';
		$class = 'admin' === $context ? 'aiv-media aiv-media-admin' : 'aiv-media aiv-media-frontend';

		ob_start();
		?>
		<div class="<?php echo esc_attr($class); ?>">
			<div class="aiv-noimage" <?php echo !empty($image) ? ' hidden' : ''; ?>>
				<span class="aiv-noimage-icon" aria-hidden="true"></span>
				<span class="aiv-noimage-text"><?php esc_html_e('No image', 'api-catalog-images-viewer'); ?></span>
			</div>
			<?php if (!empty($image)): ?>
				<img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy"
					onerror="this.hidden=true; var p=this.parentNode.querySelector('.aiv-noimage'); if(p){p.hidden=false;}" />
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	private function render_item_detail_tabs($item)
	{
		$content = !empty($item['content']) ? (string) $item['content'] : '';
		$spec_rows = !empty($item['spec_fields']) && is_array($item['spec_fields']) ? $item['spec_fields'] : array();
		$has_content = '' !== trim(wp_strip_all_tags($content));
		$has_specs = !empty($spec_rows);

		if (!$has_content && !$has_specs) {
			return '';
		}

		ob_start();
		?>
		<div class="aiv-detail-content">
			<?php if ($has_content): ?>
				<div class="aiv-figure-content"><?php echo $this->render_rich_item_content($content); ?></div>
			<?php endif; ?>
			<?php if ($has_specs): ?>
				<div class="aiv-spec-section<?php echo $has_content ? ' has-content-before' : ''; ?>">
					<dl class="aiv-spec-list">
						<?php foreach ($spec_rows as $row): ?>
							<div class="aiv-spec-row">
								<dt><?php echo esc_html($row['label']) . ' : '; ?></dt>
								<dd><?php echo esc_html($row['value']); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	private function get_effective_settings_for_post($post_id)
	{
		$page_settings = $this->get_page_settings($post_id);

		return wp_parse_args($page_settings, self::get_default_settings());
	}

	private function get_effective_settings_for_shortcode()
	{
		if (is_singular('page')) {
			$post_id = get_queried_object_id();
			if ($post_id > 0) {
				return $this->get_effective_settings_for_post($post_id);
			}
		}

		return self::get_default_settings();
	}

	public function ajax_preview_page_images()
	{
		check_ajax_referer('tumtook_catalog_images_preview', 'nonce');

		if (!current_user_can('edit_pages')) {
			wp_send_json_error(array('message' => __('ดึงไม่สําเร็จ', 'api-catalog-images-viewer')), 403);
		}

		$raw_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
		$input = is_array($raw_settings) ? $raw_settings : array();
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$defaults = self::get_default_settings();
		$settings = array(
			'api_url' => isset($input['api_url']) ? esc_url_raw(trim($input['api_url'])) : '',
			'items_path' => isset($input['items_path']) ? sanitize_text_field(trim($input['items_path'])) : '',
			'item_code_filter' => isset($input['item_code_filter']) ? sanitize_text_field(trim($input['item_code_filter'])) : '',
			'image_key' => isset($input['image_key']) ? sanitize_text_field(trim($input['image_key'])) : '',
			'title_key' => isset($input['title_key']) ? sanitize_text_field(trim($input['title_key'])) : '',
			'alt_key' => isset($input['alt_key']) ? sanitize_text_field(trim($input['alt_key'])) : '',
			'cache_minutes' => isset($input['cache_minutes']) ? max(1, absint($input['cache_minutes'])) : $defaults['cache_minutes'],
		);

		$settings = wp_parse_args(
			array_filter(
				$settings,
				static function ($value) {
					return !(is_string($value) && '' === $value);
				}
			),
			$defaults
		);

		if (empty($settings['api_url'])) {
			wp_send_json_error(array('message' => __('ดึงไม่สําเร็จ', 'api-catalog-images-viewer')), 400);
		}

		$result = $this->fetch_images($settings, 0);

		if (is_wp_error($result) || empty($result)) {
			wp_send_json_error(array('message' => __('ดึงไม่สําเร็จ', 'api-catalog-images-viewer')), 400);
		}

		wp_send_json_success(
			array(
				'html' => $this->render_editable_preview_items($result),
			)
		);
	}
}

register_activation_hook(__FILE__, array('API_Catalog_Images_Plugin', 'activate'));

new API_Catalog_Images_Plugin();
