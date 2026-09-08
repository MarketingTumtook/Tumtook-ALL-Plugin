<?php
/** Run: php modules/tumtook-page-article-recommendations/tests/smoke.php (no database). */
if ('cli' !== PHP_SAPI) {
	exit;
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('ABSPATH', dirname(__DIR__, 6) . '/');
define('MINUTE_IN_SECONDS', 60);
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/shortcodes.php';

$GLOBALS['meta'] = array();
$GLOBALS['assets'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['queried_id'] = 10;
$GLOBALS['loop_id'] = 10;
$GLOBALS['singular'] = true;
$GLOBALS['editor'] = false;
$GLOBALS['checks'] = array();
$GLOBALS['articles'] = array();
foreach (range(101, 115) as $id) {
	$GLOBALS['articles'][$id] = (object) array('ID' => $id, 'post_type' => 'post', 'post_status' => 'publish');
}
$GLOBALS['articles'][116] = (object) array('ID' => 116, 'post_type' => 'post', 'post_status' => 'draft');
$GLOBALS['articles'][117] = (object) array('ID' => 117, 'post_type' => 'post', 'post_status' => 'private');

// Only environment/database helpers are stubbed; registration and parsing use WordPress core.
function plugins_url($path, $file) { return '/modules/' . basename(dirname($file)) . '/' . $path; }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function wp_html_split($text) { return array($text); } // Test inputs contain plain-text shortcodes only.
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function esc_url_raw($value) { return preg_match('/^javascript:/i', $value) ? '' : $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_attr(esc_url_raw($value)); }
function esc_html($value) { return esc_attr($value); }
function __($value, $domain) { return $value; }
function esc_html_e($value, $domain) { echo esc_html($value); }
function esc_attr_e($value, $domain) { echo esc_attr($value); }
function checked($a, $b) { if ((string) $a === (string) $b) echo 'checked="checked"'; }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="test">'; }
function absint($value) { return abs((int) $value); }
function get_queried_object_id() { return $GLOBALS['queried_id']; }
function get_the_ID() { return $GLOBALS['loop_id']; }
function is_singular() { return $GLOBALS['singular']; }
function is_admin() { return $GLOBALS['editor']; }
function get_post_type($id) { return isset($GLOBALS['articles'][$id]) ? 'post' : 'page'; }
function get_the_title($id) { return 'บทความ ' . $id; }
function get_permalink($id) { return '/article/' . $id; }
function get_the_post_thumbnail_url($id, $size) { return '/images/' . $id . '.jpg'; }
function get_the_category($id) { return array((object) array('name' => 'คู่มือ')); }
function _prime_post_caches(...$args) {}
function wp_reset_postdata() {}
function wp_unique_id($prefix) { static $id = 0; return $prefix . ++$id; }
function wp_register_style(...$args) {}
function wp_register_script(...$args) {}
function wp_enqueue_style($handle) { $GLOBALS['assets']['styles'][] = $handle; }
function wp_enqueue_script($handle) { $GLOBALS['assets']['scripts'][] = $handle; }
function wp_json_encode($value) { return json_encode($value); }
function get_option($key, $default = false) { return $default; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $expiry) { $GLOBALS['transients'][$key] = $value; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function home_url($path) { return 'https://example.test' . $path; }
function nocache_headers() {}
class TTAR_Test_Json_Response extends RuntimeException {
	public $payload;
	public function __construct($payload) { parent::__construct('JSON response'); $this->payload = $payload; }
}
function wp_send_json_success($payload) { throw new TTAR_Test_Json_Response($payload); }
class WP_Query {
	public $posts;
	public function __construct($args) {
		$articles = array_filter($GLOBALS['articles'], function ($article) use ($args) {
			return $article->post_type === $args['post_type'] && $article->post_status === $args['post_status']
				&& !in_array($article->ID, $args['post__not_in'], true);
		});
		$this->posts = array_slice(array_keys($articles), 0, $args['posts_per_page']);
	}
	public function have_posts() { return !empty($this->posts); }
}
function check($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	$GLOBALS['checks'][] = 'PASS: ' . $message;
}
function card_ids($html) {
	preg_match_all('~data-card-url="/article/(\d+)"~', $html, $matches);
	return array_map('intval', $matches[1]);
}
function refresh($html) {
	preg_match('/data-ttar-post-id="(\d+)"/', $html, $post);
	preg_match('/data-ttar-limit="(\d+)"/', $html, $limit);
	$_REQUEST = array('post_id' => $post[1], 'limit' => $limit[1]);
	try {
		do_action('wp_ajax_nopriv_ttar_refresh_items');
	} catch (TTAR_Test_Json_Response $response) {
		return $response->payload;
	}
	throw new RuntimeException('AJAX handler did not send a response');
}

require dirname(__DIR__) . '/tumtook-page-article-recommendations.php';
$plugin = $GLOBALS['shortcode_tags']['tumtook_recommended_articles'][0];
$key = Tumtook_Page_Article_Recommendations::META_KEY;

$html = do_shortcode('[tumtook_recommended_articles]');
check(count(card_ids($html)) === 10 && strpos($html, 'บทความน่าสนใจ') !== false, 'A new page renders default recommendations without saving settings');
check(strpos($html, 'data-ttar-post-id="10"') !== false, 'A bare shortcode uses the current page');
check(in_array('tt-page-article-recommendations', $GLOBALS['assets']['styles'], true)
	&& in_array('tt-page-article-recommendations', $GLOBALS['assets']['scripts'], true), 'Rendering enqueues slider CSS and JavaScript');
$payload = refresh($html);
check($payload['enabled'] && $payload['count'] === 10 && count(card_ids($payload['html'])) === 10, 'Guest AJAX refresh keeps default recommendations visible');
check(!array_intersect(card_ids($html . $payload['html']), array(116, 117)), 'Initial and AJAX cards include only published articles');

$GLOBALS['meta'][20][$key] = array(
	'enabled' => '1', 'title' => 'บทความจากหน้าต้นทาง', 'limit' => '4',
	'view_all_label' => 'รวมบทความ', 'view_all_url' => 'https://example.test/all', 'button_label' => 'อ่านต่อ',
);
$GLOBALS['meta'][10][$key] = array('enabled' => '0', 'title' => 'หน้าปลายทางปิดไว้');
$original_meta = $GLOBALS['meta'];
$html = do_shortcode('[tumtook_recommended_articles page_id="20"]');
check(count(card_ids($html)) === 4 && strpos($html, 'บทความจากหน้าต้นทาง') !== false
	&& strpos($html, 'รวมบทความ') !== false && strpos($html, 'https://example.test/all') !== false
	&& strpos($html, 'อ่านต่อ') !== false, 'page_id reuses all source settings even when the destination section is disabled');
check(strpos($html, 'data-ttar-post-id="20"') !== false, 'The source settings ID is carried into the AJAX request');
$payload = refresh($html);
check($payload['enabled'] && $payload['count'] === 4 && strpos($payload['html'], 'อ่านต่อ') !== false, 'AJAX refresh preserves source count and button text');
check(count(card_ids(do_shortcode('[tumtook_recommended_articles post_id="20"]'))) === 4, 'Legacy post_id shortcodes remain supported');
check(count(card_ids(do_shortcode('[tumtook_recommended_articles post_id="20" page_id="10"]'))) === 4, 'A positive post_id takes precedence when both source attributes are supplied');

$html = do_shortcode('[tumtook_recommended_articles page_id="20" limit="6"]');
check(count(card_ids($html)) === 6 && refresh($html)['count'] === 6, 'A per-instance limit survives AJAX refresh');
check(count(card_ids(do_shortcode('[tumtook_recommended_articles page_id="20" limit="99"]'))) === 10, 'The maximum remains ten articles');
check(count(card_ids(do_shortcode('[tumtook_recommended_articles page_id="20" limit="1"]'))) === 1, 'A one-card limit is supported');
check(count(card_ids(do_shortcode('[tumtook_recommended_articles page_id="20" limit="0"]'))) === 4, 'A zero limit retains the saved source count');
check($GLOBALS['meta'] === $original_meta, 'Shortcode and AJAX rendering do not modify page settings');

$GLOBALS['meta'][20][$key]['enabled'] = '0';
check(do_shortcode('[tumtook_recommended_articles page_id="20"]') === '', 'An explicitly disabled source stays hidden');
check(do_shortcode('[tumtook_recommended_articles]') === '', 'An explicitly disabled current page stays hidden');
$payload = refresh($html);
check(!$payload['enabled'] && $payload['html'] === '' && $payload['count'] === 0, 'AJAX hides a cached section after its source is disabled');
$GLOBALS['editor'] = true;
$html = do_shortcode('[tumtook_recommended_articles page_id="20"]');
check(count(card_ids($html)) === 4 && strpos($html, 'data-ttar-dynamic="0"') !== false, 'Editor preview still shows disabled sections without dynamic refresh');
$GLOBALS['editor'] = false;
$GLOBALS['meta'][20][$key]['enabled'] = '1';

$GLOBALS['queried_id'] = 30;
$GLOBALS['loop_id'] = 30;
$html = do_shortcode('[tumtook_recommended_articles limit="3"]');
check(count(card_ids($html)) === 3 && refresh($html)['count'] === 3, 'An unconfigured page also supports a custom limit');
$GLOBALS['queried_id'] = 0;
check(strpos(do_shortcode('[tumtook_recommended_articles]'), 'data-ttar-post-id="30"') !== false, 'Singular rendering falls back to the current post ID');

$GLOBALS['singular'] = false;
$GLOBALS['queried_id'] = 10; // Term ID collides with a disabled page ID.
$html = do_shortcode('[tumtook_recommended_articles limit="2"]');
check(count(card_ids($html)) === 2 && strpos($html, 'data-ttar-post-id="0"') !== false, 'An archive uses defaults rather than unrelated post meta with the same numeric ID');
check(refresh($html)['count'] === 2, 'Archive recommendations refresh without a source post');
$GLOBALS['queried_id'] = 0;
$GLOBALS['loop_id'] = 0;
check(count(card_ids(do_shortcode('[tumtook_recommended_articles limit="3"]'))) === 3, 'Templates without a current post can render defaults');
check(count(card_ids(do_shortcode('[tumtook_recommended_articles page_id="20"]'))) === 4, 'Templates can explicitly reuse a source page');

$html = do_shortcode('[tumtook_recommended_articles page_id="20" limit="2"] [tumtook_recommended_articles page_id="20" limit="3"]');
preg_match_all('/\sid="(ttar-[^"]+)"/', $html, $ids);
check(count(card_ids($html)) === 5 && count(array_unique($ids[1])) === 2, 'Repeated shortcodes render separate sliders with unique IDs and independent limits');

$GLOBALS['singular'] = true;
$GLOBALS['queried_id'] = 101;
$html = do_shortcode('[tumtook_recommended_articles]');
$payload = refresh($html);
check(!in_array(101, card_ids($html . $payload['html']), true), 'A shortcode on an article still excludes that article from recommendations');
ob_start();
$plugin->render_meta_box((object) array('ID' => 20));
$admin = ob_get_clean();
check(strpos($admin, '[tumtook_recommended_articles page_id=&quot;20&quot;]') !== false, 'The editor offers a copyable shortcode using the actual source ID');

echo implode("\n", $GLOBALS['checks']) . "\n";
