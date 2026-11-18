<?php
/** Run: php modules/tumtook-gallery-code/tests/smoke.php */
if (PHP_SAPI !== 'cli') exit;
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['shortcodes'] = array();
$GLOBALS['options'] = array('tumtook_gallery_settings' => array('api_url' => 'https://old.test/private'));
$GLOBALS['can_manage'] = false;
function get_the_ID() { return 42; }
function get_post_meta($post_id, $key, $single) { return $GLOBALS['post_meta'][$post_id][$key] ?? ''; }
class WP_REST_Server { const READABLE = 'GET'; }
class WP_Error { public $code; function __construct($code, ...$args) { $this->code = $code; } function get_error_code() { return $this->code; } }
class WP_Http { static function make_absolute_url($url, $base) { return strpos($url, '/') === 0 ? 'https://api.test' . $url : $url; } }
class Tumtook_AIO_API_Cache {
    static $calls = array();
    static function get($url, $ttl, $safe, $fresh = false) { self::$calls[] = array($url, $ttl, $safe, $fresh); return $GLOBALS['response']; }
}
class Request { private $data; function __construct($data) { $this->data = $data; } function get_param($key) { return $this->data[$key] ?? null; } }
function add_action(...$args) {}
function add_shortcode($name, $callback) { $GLOBALS['shortcodes'][$name] = $callback; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function shortcode_atts($defaults, $atts, $name) { return array_intersect_key($atts, $defaults) + $defaults; }
function current_user_can(...$args) { return $GLOBALS['can_manage']; }
function wp_enqueue_style(...$args) { $GLOBALS['style'] = $args; }
function wp_enqueue_script(...$args) { $GLOBALS['script'] = $args; }
function plugin_dir_url($file) { return 'https://site.test/modules/tumtook-gallery-code/'; }
function rest_url($path) { return 'https://site.test/wp-json/' . $path; }
function wp_hash($value, $scheme) { return hash_hmac('sha256', $value, 'test-secret'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function esc_url_raw($value, $protocols = null) { return preg_match('~^https?://~', $value) ? $value : ''; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_retrieve_response_code($response) { return 200; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return $value; }
function register_rest_route($namespace, $path, $args) { $GLOBALS['route'] = array($namespace, $path, $args); }
function check($value, $message) { if (!$value) throw new RuntimeException($message); echo "PASS: $message\n"; }
require dirname(__DIR__) . '/tumtook-gallery-code.php';
$plugin = $GLOBALS['shortcodes']['tumtook_gallery_code'][0];
check(count($GLOBALS['shortcodes']) === 1 && !class_exists('Tumtook_Gallery_Plugin'), 'New module runs independently without registering or loading old Gallery');
check($plugin->render_shortcode(array('code' => 'VN')) === '', 'Old Gallery settings are never used implicitly');
$GLOBALS['options'][Tumtook_Gallery_Code::OPTION] = array('api_url' => 'https://api.test/galleries?x-api-key=secret');
$html = $plugin->render_shortcode(array('code' => ' vn '));
check(strpos($html, 'data-ttgc-code="VN"') !== false, 'Only code is needed after one-time API setup');
check(strpos($html, 'secret') === false && strpos($html, 'api.test') === false, 'API credentials stay server-side');
check(strpos($html, 'ttg-gallery-shell') === false && $GLOBALS['script'][0] === 'tumtook-gallery-code', 'Frontend markup and asset handles are independent');
check($plugin->render_shortcode(array('code' => array('VN'))) === '' && $plugin->render_shortcode(array('code' => 'VN MT')) === '', 'Malformed codes fail without a request');
$GLOBALS['post_meta'][42]['tt_gallery_code'] = 'vn';
check(strpos($plugin->render_shortcode(array()), 'data-ttgc-code="VN"') !== false, 'Default shortcode reads tt_gallery_code from the current post');
$GLOBALS['post_meta'][42]['custom_gallery_code'] = 'MT';
check(strpos($plugin->render_shortcode(array('field' => 'custom_gallery_code')), 'data-ttgc-code="MT"') !== false, 'Field attribute selects the post-meta source');
check(strpos($plugin->render_shortcode(array('code' => 'MT')), 'data-ttgc-code="MT"') !== false, 'Explicit code takes precedence over post meta');
check($plugin->render_shortcode(array('field' => array('tt_gallery_code'))) === '', 'Invalid field attributes cannot cause a fatal error');
check($plugin->render_shortcode(array('code' => 'VN MT')) === '', 'Invalid explicit code does not silently fall back to post meta');
unset($GLOBALS['post_meta'][42]['tt_gallery_code']);
check($plugin->render_shortcode(array()) === '', 'An empty field produces no public Gallery or API request');
$GLOBALS['post_meta'][42]['tt_gallery_code'] = 'VN';
class WP_Post { public $ID; function __construct($id) { $this->ID = $id; } }
class JetData {
    public $object;
    function get_current_object() { return $this->object; }
    function get_meta($key, $object, $source) { return $GLOBALS['jet_meta'][$object->ID][$key] ?? ''; }
}
$jet_data = new JetData();
$jet_data->object = new WP_Post(99);
$GLOBALS['jet_engine'] = (object) array('listings' => (object) array('data' => $jet_data));
// Declare at runtime so the fallback tests above genuinely run without JetEngine.
if (!function_exists('jet_engine')) {
    function jet_engine() { return $GLOBALS['jet_engine']; }
}
$GLOBALS['jet_meta'][99]['tt_gallery_code'] = 'MT';
check(strpos($plugin->render_shortcode(array()), 'data-ttgc-code="MT"') !== false, 'JetEngine Listing context and custom meta storage take precedence over parent post');
unset($GLOBALS['jet_meta'][99]['tt_gallery_code']);
check($plugin->render_shortcode(array()) === '', 'Empty Listing fields cannot accidentally use the parent page code');
$jet_data->object = (object) array('term_id' => 42);
check($plugin->render_shortcode(array()) === '', 'Non-post Listing objects cannot read metadata from a post with the same ID');
$jet_data->object = null;
check(strpos($plugin->render_shortcode(array()), 'data-ttgc-code="VN"') !== false, 'A missing JetEngine object falls back safely to current post meta');
$plugin->register_routes();
check($GLOBALS['route'][0] === 'tumtook-gallery-code/v1', 'New REST namespace is isolated');
$request = new Request(array('code' => 'VN', 'signature' => wp_hash('ttgc|VN', 'auth')));
check($plugin->can_view($request), 'A signature generated by shortcode authorizes its code');
check(!$plugin->can_view(new Request(array('code' => 'MT', 'signature' => wp_hash('ttgc|VN', 'auth')))), 'Changing a code invalidates its signature');
check(!$plugin->can_view(new Request(array('code' => 'VN', 'signature' => array('bad')))), 'Non-string signatures cannot cause a fatal error');
$images = array();
for ($i = 0; $i < 60; $i++) $images[] = array('fileUrl' => '/image-' . $i . '.webp', 'altText' => '<b>Image</b>', 'width' => 800, 'height' => 1000);
array_unshift($images, $images[0], array('fileUrl' => 'javascript:alert(1)'));
$GLOBALS['response'] = array('body' => json_encode(array('items' => array(array('code' => 'MT', 'images' => array(array('fileUrl' => 'https://api.test/wrong.webp'))), array('code' => 'vn', 'images' => $images)))));
$data = $plugin->rest_items($request);
check(count($data['items']) === 50 && count(array_unique(array_column($data['items'], 'image'))) === 50, 'Only matching code images are returned, unique and capped at 50');
check($data['items'][0]['alt'] === 'Image' && $data['items'][0]['width'] === 800 && $data['items'][0]['height'] === 1000, 'Image text is cleaned and dimensions preserved');
check(Tumtook_AIO_API_Cache::$calls[0][1] === 1800 && Tumtook_AIO_API_Cache::$calls[0][2] === true, 'Shared cache uses safe HTTP requests and default 30-minute TTL');
$GLOBALS['response'] = new WP_Error('timeout');
check(is_wp_error($plugin->rest_items($request)), 'API failure becomes a controlled REST error');
$GLOBALS['response'] = array('body' => '{invalid');
check(is_wp_error($plugin->rest_items($request)), 'Malformed API data cannot cause a fatal error');
check($GLOBALS['options']['tumtook_gallery_settings']['api_url'] === 'https://old.test/private', 'Existing Gallery configuration stays untouched');
// Diagnostics are available only to an editor of the saved post, never to public REST users.
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['post_meta'][$id] ?? array()); }
function add_meta_box($id, ...$args) { $GLOBALS['diag_boxes'][] = $id; }
function wp_create_nonce($action) { return 'test-nonce'; }
function admin_url($path) { return 'https://site.test/wp-admin/' . $path; }
function get_post($id) { return $id > 0 ? new WP_Post($id) : null; }
function check_ajax_referer($action, $key) {
    $GLOBALS['nonce_action'] = $action;
    if (empty($GLOBALS['nonce_valid'])) throw new RuntimeException('nonce-rejected');
}
class JsonResult extends RuntimeException { public $success, $data, $status; function __construct($success, $data, $status) { $this->success = $success; $this->data = $data; $this->status = $status; } }
function wp_send_json_success($data) { throw new JsonResult(true, $data, 200); }
function wp_send_json_error($data, $status) { throw new JsonResult(false, $data, $status); }
function diagnose($plugin) { try { $plugin->ajax_diagnostics(); } catch (JsonResult $result) { return $result; } throw new RuntimeException('No JSON response'); }
$GLOBALS['can_manage'] = true;
$GLOBALS['nonce_valid'] = true;
$GLOBALS['jet_meta'][99]['tt_gallery_code'] = 'KH';
$plugin->add_diagnostics_box('portfolio', new WP_Post(99));
check($GLOBALS['diag_boxes'] === array('ttgc-diagnostics'), 'Custom Post Types with a Gallery field receive a diagnostics box');
$calls_before = count(Tumtook_AIO_API_Cache::$calls);
ob_start(); $plugin->diagnostics_box(new WP_Post(99)); $box = ob_get_clean();
check(strpos($box, 'KH') !== false && strpos($box, 'secret') === false && count(Tumtook_AIO_API_Cache::$calls) === $calls_before, 'Opening the editor shows saved code without API calls or credentials');
$_POST = array('post_id' => 99, 'fresh' => '0');
$GLOBALS['response'] = array('body' => json_encode(array('items' => array(array('code' => 'kh', 'images' => array(array('fileUrl' => 'https://images.test/kh.webp')))))));
$diagnosis = diagnose($plugin);
check($diagnosis->success && $diagnosis->data['code'] === 'KH' && $diagnosis->data['matched_records'] === 1 && $diagnosis->data['display_image_count'] === 1, 'Diagnostics confirms an exact code match and counts usable images');
check(count($diagnosis->data['preview']) === 1 && strpos(json_encode($diagnosis->data), 'secret') === false, 'Preview returns sample images without disclosing API credentials');
check($GLOBALS['nonce_action'] === 'ttgc_diagnostics_99' && end(Tumtook_AIO_API_Cache::$calls)[3] === false, 'Nonce is post-bound and diagnostics uses cache by default');
$_POST['fresh'] = '1';
diagnose($plugin);
check(end(Tumtook_AIO_API_Cache::$calls)[3] === true, 'Only an explicit fresh check bypasses cache');
$GLOBALS['response'] = array('body' => json_encode(array('items' => array(array('code' => 'VN', 'images' => array())))));
$diagnosis = diagnose($plugin);
check($diagnosis->success && $diagnosis->data['matched_records'] === 0, 'A valid API response with no matching code is distinguishable from connection failure');
$GLOBALS['response'] = new WP_Error('timeout');
check(!diagnose($plugin)->success, 'API failures produce an admin-only diagnostic error');
$GLOBALS['can_manage'] = false;
$calls_before = count(Tumtook_AIO_API_Cache::$calls);
check(diagnose($plugin)->status === 403 && count(Tumtook_AIO_API_Cache::$calls) === $calls_before, 'Unauthorized users cannot make diagnostic API requests');
$GLOBALS['can_manage'] = true;
$GLOBALS['nonce_valid'] = false;
try { $plugin->ajax_diagnostics(); throw new RuntimeException('Invalid nonce accepted'); }
catch (RuntimeException $error) { check($error->getMessage() === 'nonce-rejected', 'Invalid diagnostic nonces are rejected before accessing the API'); }
$GLOBALS['nonce_valid'] = true;
function settings_test($plugin) { try { $plugin->ajax_test_api(); } catch (JsonResult $result) { return $result; } throw new RuntimeException('No JSON response'); }
$stored_options = $GLOBALS['options'];
$_POST = array('api_url' => 'https://new-api.test/gallery?x-api-key=unsaved-secret', 'code' => '');
$GLOBALS['response'] = array('body' => json_encode(array('items' => array(
    array('code' => 'KH', 'images' => array(array('fileUrl' => 'https://images.test/kh.webp'))),
    array('code' => 'VN', 'images' => array(array('fileUrl' => 'https://images.test/vn.webp'), array('fileUrl' => 'https://images.test/vn2.webp')))
))));
$result = settings_test($plugin);
check($result->success && $result->data['code_count'] === 2 && $result->data['image_count'] === 3, 'Settings test summarizes Gallery data and available codes');
check(end(Tumtook_AIO_API_Cache::$calls)[0] === $_POST['api_url'] && end(Tumtook_AIO_API_Cache::$calls)[2] === true && end(Tumtook_AIO_API_Cache::$calls)[3] === true, 'URL test uses the unsaved URL with safe requests and fresh data');
check($GLOBALS['options'] === $stored_options && strpos(json_encode($result->data), 'unsaved-secret') === false, 'Testing URL never saves options or echoes credentials in the response');
$_POST['code'] = 'KH';
$result = settings_test($plugin);
check($result->data['matched_records'] === 1 && $result->data['display_image_count'] === 1 && count($result->data['preview']) === 1, 'Optional code test previews only matching images');
$_POST['code'] = 'NOTFOUND';
$result = settings_test($plugin);
check($result->success && $result->data['matched_records'] === 0, 'Unknown codes are distinguishable from API failure');
$GLOBALS['can_manage'] = false;
$calls_before = count(Tumtook_AIO_API_Cache::$calls);
check(settings_test($plugin)->status === 403 && count(Tumtook_AIO_API_Cache::$calls) === $calls_before, 'Only administrators may test arbitrary API URLs');
$GLOBALS['can_manage'] = true;
$_POST = array('api_url' => array('bad'));
check(settings_test($plugin)->status === 400, 'Malformed URL input is rejected before fetching');
echo "All Gallery Code checks passed.\n";
