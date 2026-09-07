<?php
/** Run: php modules/tumtook-gallery-auto/tests/smoke.php (no database or external API). */
if (PHP_SAPI !== 'cli') {
	exit;
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['meta'] = array();
$GLOBALS['cache'] = array();
$GLOBALS['shortcodes'] = array();
$GLOBALS['routes'] = array();
$GLOBALS['can_edit'] = false;
$GLOBALS['requests'] = 0;
class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {
	private $params;
	function __construct($params) { $this->params = $params; }
	function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_Error {
	public $code;
	function __construct($code, $message, $data = array()) { $this->code = $code; }
}
function add_action(...$args) {}
function add_shortcode($name, $callback) { $GLOBALS['shortcodes'][$name] = $callback; }
function register_activation_hook(...$args) {}
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][$namespace . $path] = $args; }
function get_the_ID() { return 42; }
function get_post($id) { return $id ? (object) array('ID' => $id) : null; }
function current_user_can(...$args) { return $GLOBALS['can_edit']; }
function is_user_logged_in() { return false; }
function is_post_publicly_viewable($post) { return $post->ID === 42; }
function post_password_required($post) { return !empty($GLOBALS['password_required']); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['meta'][$id] ?? array()); }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function wp_unslash($value) { return $value; }
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function shortcode_atts($defaults, $atts, $name) { return array_merge($defaults, $atts); }
function sanitize_text_field($value) { return strip_tags((string) $value); }
function sanitize_textarea_field($value) { return strip_tags((string) $value); }
function sanitize_hex_color($value) { return preg_match('/^#([a-fA-F0-9]{3}){1,2}$/', $value) ? $value : null; }
function esc_url_raw($value) { return (string) $value; }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function __($text, $domain) { return $text; }
function esc_html__($text, $domain) { return esc_html($text); }
function esc_html_e($text, $domain) { echo esc_html($text); }
function esc_attr_e($text, $domain) { echo esc_attr($text); }
function absint($value) { return abs((int) $value); }
function wp_register_style(...$args) {}
function wp_register_script(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_localize_script(...$args) {}
function plugin_dir_url($file) { return 'https://example.test/assets/'; }
function rest_url($path) { return 'https://example.test/wp-json/' . $path; }
function wp_hash($value, $scheme) { return hash_hmac('sha256', $value, 'test-key'); }
function wp_json_encode($value) { return json_encode($value); }
function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
function wp_safe_remote_get($endpoint, $args) { $GLOBALS['requests']++; return array('body' => json_encode($GLOBALS['api_data'])); }
function wp_remote_retrieve_response_code($response) { return 200; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return $value; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_parse_url($value, $part = -1) { return parse_url($value, $part); }
function wp_basename($value) { return basename($value); }
function wp_rand($min, $max) { return mt_rand($min, $max); }
function check($value, $message) {
	if (!$value) throw new RuntimeException($message);
	echo "PASS: $message\n";
}
function request($params = array()) {
	return new WP_REST_Request(array_merge(array('page_id' => 42, 'endpoint' => '', 'endpoint_signature' => '', 'limit' => 50, 'page' => 1, 'per_page' => 12), $params));
}
require dirname(__DIR__, 2) . '/tumtook-gallery/tumtook-gallery.php';
require dirname(__DIR__) . '/tumtook-gallery-auto.php';
$plugin = $GLOBALS['shortcodes']['tumtook_gallery_auto'][0];
check(isset($GLOBALS['shortcodes']['tumtook_gallery']), 'Legacy and auto shortcodes coexist');
$plugin->register_rest_routes();
check(isset($GLOBALS['routes']['tumtook-gallery-auto/v1/items']), 'New REST namespace is registered');
$legacy = array('api_url' => 'https://example.test/gallery?x-api-key=secret', 'items_path' => 'items', 'image_key' => 'images.fileUrl', 'alt_key' => 'images.altText', 'match_code' => 'A,B,C');
$GLOBALS['meta'][42]['_tumtook_gallery_settings'] = $legacy;
$html = $plugin->render_shortcode(array());
check(strpos($html, 'ttga-gallery-shell') !== false && strpos($html, 'data-columns="0"') !== false, 'New shortcode inherits existing page configuration and defaults to auto columns');
check(strpos($html, 'secret') === false, 'Configured API URL stays on the server');
$html = $plugin->render_shortcode(array('columns' => 99, 'gap' => 999, 'min_width' => 1, 'radius' => 999));
check(strpos($html, 'data-columns="12"') !== false && strpos($html, 'data-gap="64"') !== false && strpos($html, 'data-min-width="140"') !== false, 'Layout settings stay within supported bounds');
ob_start(); $plugin->render_field(array('key' => 'cache_minutes')); $field = ob_get_clean();
check(strpos($field, 'type="number"') !== false, 'Cache duration field remains numeric');
check($plugin->can_view_gallery(request()), 'Published page is available to anonymous visitors');
check(!$plugin->can_view_gallery(request(array('page_id' => 0))) && !$plugin->can_view_gallery(request(array('page_id' => 9))), 'Missing and private pages are rejected');
$GLOBALS['password_required'] = true;
check(!$plugin->can_view_gallery(request()), 'Password-protected page is not exposed');
$GLOBALS['password_required'] = false;
$invalid = $plugin->rest_get_items(request(array('endpoint' => 'https://unconfigured.test/')));
check(is_wp_error($invalid) && $GLOBALS['requests'] === 0, 'Unsigned endpoint cannot make an outbound request');
$GLOBALS['api_data'] = array('items' => array());
foreach (array('A', 'B', 'C') as $code) {
	$images = array();
	for ($i = 0; $i < 60; $i++) {
		$images[] = array('fileUrl' => 'https://example.test/' . $code . '-' . $i . '.jpg', 'altText' => $code . $i, 'width' => 300, 'height' => 200 + $i);
	}
	$GLOBALS['api_data']['items'][] = array('code' => $code, 'images' => $images);
}
$all = array();
for ($page = 1; $page <= 5; $page++) {
	$result = $plugin->rest_get_items(request(array('page' => $page)));
	check(!is_wp_error($result), 'API page ' . $page . ' returns images');
	$all = array_merge($all, $result['items']);
}
check(count($all) === 50 && count(array_unique(array_column($all, 'key'))) === 50 && !$result['has_more'], 'Pagination returns 50 distinct images without omissions');
check(array_count_values(array_column($all, 'group')) == array('filter-1' => 25, 'filter-2' => 15, 'filter-3' => 10), 'Three Item Codes retain the 25/15/10 allocation');
check($GLOBALS['requests'] === 1, 'Subsequent pages use cached API data');
foreach ($all as $item) {
	preg_match('/-(\d+)\.jpg$/', $item['image'], $match);
	if ($item['width'] != 300 || $item['height'] != 200 + (int) $match[1]) throw new RuntimeException('Image dimensions do not match image');
}
check(true, 'Nested API dimensions match each image');
$_POST = array('tumtook_gallery_auto_nonce' => 'invalid', '_tumtook_gallery_auto_settings' => array('api_url' => 'https://example.test/new'));
$plugin->save_page_settings(42);
check(!metadata_exists('post', 42, '_tumtook_gallery_auto_settings'), 'Invalid nonce cannot change page settings');
$GLOBALS['can_edit'] = true;
$_POST['tumtook_gallery_auto_nonce'] = 'valid';
$plugin->save_page_settings(42);
check($GLOBALS['meta'][42]['_tumtook_gallery_settings'] === $legacy && $GLOBALS['meta'][42]['_tumtook_gallery_auto_settings']['api_url'] === 'https://example.test/new', 'Saving auto settings does not change the original gallery');
$GLOBALS['meta'][42]['_tumtook_gallery_auto_settings'] = array();
$html = $plugin->render_shortcode(array());
check(strpos($html, 'ttga-gallery-shell') === false, 'Explicitly cleared new settings do not fall back to legacy');
echo "All smoke checks passed.\n";
