<?php
/** Run: php modules/tumtook-home-product-recommendations/tests/smoke.php (no database). */
if (PHP_SAPI !== 'cli') {
	exit;
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('ABSPATH', __DIR__);
$GLOBALS['meta'] = array();
$GLOBALS['shortcodes'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['can_edit'] = true;
$GLOBALS['is_preview'] = false;
$GLOBALS['queried_id'] = 10;
$GLOBALS['query_count'] = 0;
$GLOBALS['revision'] = false;
$GLOBALS['assets'] = array();
$GLOBALS['pages'] = array();
foreach (array(10 => 'Home', 11 => 'หน้าหนึ่ง', 12 => 'หน้าสอง', 13 => 'หน้าสาม', 14 => 'Draft', 15 => 'Private', 16 => 'Protected', 17 => 'Article', 18 => 'Unselected') as $id => $title) {
	$GLOBALS['pages'][$id] = (object) array('ID' => $id, 'post_title' => $title, 'post_type' => 17 === $id ? 'post' : 'page', 'post_status' => 14 === $id ? 'draft' : (15 === $id ? 'private' : 'publish'), 'post_password' => 16 === $id ? 'password' : '');
}
function add_action($hook, $callback, ...$args) { $GLOBALS['actions'][$hook][] = $callback; }
function add_shortcode($name, $callback) { $GLOBALS['shortcodes'][$name] = $callback; }
function register_activation_hook(...$args) {}
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return '/modules/' . basename(dirname($file)) . '/'; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function shortcode_atts($defaults, $atts, $name) { return array_intersect_key($atts, $defaults) + $defaults; }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function esc_url_raw($value) { return preg_match('/^javascript:/i', $value) ? '' : $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_attr(esc_url_raw($value)); }
function esc_html($value) { return esc_attr($value); }
function __($value, $domain) { return $value; }
function esc_html__($value, $domain) { return esc_html($value); }
function esc_html_e($value, $domain) { echo esc_html($value); }
function esc_attr_e($value, $domain) { echo esc_attr($value); }
function checked($a, $b) { if ((string) $a === (string) $b) echo 'checked="checked"'; }
function selected($a, $b) { if ((string) $a === (string) $b) echo 'selected="selected"'; }
function absint($value) { return abs((int) $value); }
function wp_list_pluck($items, $field) { return array_map(function ($item) use ($field) { return $item->$field; }, $items); }
function wp_verify_nonce($value, $action) { return 'valid' === $value && 'tt_home_product_recommendations_save' === $action; }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid">'; }
function wp_is_post_revision($id) { return $GLOBALS['revision']; }
function current_user_can(...$args) { return $GLOBALS['can_edit']; }
function get_queried_object_id() { return $GLOBALS['queried_id']; }
function get_the_ID() { return $GLOBALS['queried_id']; }
function is_singular($type) { return true; }
function is_admin() { return false; }
function is_preview() { return $GLOBALS['is_preview']; }
function get_post($id) { return $GLOBALS['pages'][$id] ?? null; }
function get_post_type($id) { return get_post($id)->post_type ?? false; }
function is_post_publicly_viewable($page) { return 'publish' === $page->post_status; }
function post_password_required($page) { return '' !== $page->post_password; }
function get_the_title($page) { return is_object($page) ? $page->post_title : get_post($page)->post_title; }
function get_permalink($id) { return '/page/' . $id; }
function wp_get_attachment_image_url($id, $size) { return 999 === $id ? false : '/images/' . $id . '.jpg'; }
function get_the_post_thumbnail_url($id, $size) { return 12 === $id ? '/images/featured-12.jpg' : false; }
function number_format_i18n($number, $decimals) { return number_format($number, $decimals); }
function _prime_post_caches(...$args) {}
function wp_unique_id($prefix) { static $id = 0; return $prefix . ++$id; }
function wp_style_is(...$args) { return true; }
function wp_register_style(...$args) {}
function wp_enqueue_style(...$args) { $GLOBALS['assets'][] = $args; }
function wp_enqueue_script(...$args) { $GLOBALS['assets'][] = $args; }
function get_posts($args) {
	$GLOBALS['query_count']++;
	$pages = array_filter($GLOBALS['pages'], function ($page) use ($args) {
		return $page->post_type === $args['post_type'] && $page->post_status === $args['post_status']
			&& (!isset($args['has_password']) || (bool) $page->post_password === $args['has_password'])
			&& (empty($args['post__in']) || in_array($page->ID, $args['post__in'], true))
			&& !in_array($page->ID, $args['post__not_in'] ?? array(), true);
	});
	if ('post__in' === $args['orderby']) {
		$pages = array_replace(array_flip($args['post__in']), $pages);
		$pages = array_filter($pages, 'is_object');
	} else {
		usort($pages, function ($a, $b) { return strcmp($a->post_title, $b->post_title); });
	}
	if ($args['posts_per_page'] > 0) $pages = array_slice($pages, 0, $args['posts_per_page']);
	return 'ids' === ($args['fields'] ?? '') ? wp_list_pluck(array_values($pages), 'ID') : array_values($pages);
}
function check($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	echo "PASS: $message\n";
}
function set_selection($ids, $extra = array()) {
	$_POST = array('tt_home_product_recommendations_nonce' => 'valid', 'tthpr_settings' => array_merge(array('enabled' => '1', 'page_ids' => $ids), $extra));
	$GLOBALS['plugin']->save_meta(10);
}
function card_ids($html) {
	preg_match_all('~data-card-url="/page/(\d+)"~', $html, $matches);
	return array_map('intval', $matches[1]);
}

require dirname(__DIR__, 3) . '/tumtook-all-in-one.php';
require dirname(__DIR__, 2) . '/tumtook-page-product-recommendations/tumtook-page-product-recommendations.php';
require dirname(__DIR__) . '/tumtook-home-product-recommendations.php';
$plugin = $GLOBALS['shortcodes'][Tumtook_Home_Product_Recommendations::SHORTCODE][0];
$GLOBALS['plugin'] = $plugin;
$key = Tumtook_Home_Product_Recommendations::META_KEY;
check(in_array('tumtook-home-product-recommendations/tumtook-home-product-recommendations.php', array_column(tumtook_aio_get_modules(), 'file'), true), 'All-in-One registers the Home module');
check($GLOBALS['shortcodes']['tumtook_recommended_products'][0] instanceof Tumtook_Page_Product_Recommendations, 'Original recommendations shortcode keeps its own handler');
check($GLOBALS['shortcodes']['tumtook_product_cards'][0] instanceof Tumtook_Page_Product_Recommendations, 'Home module does not overwrite legacy shortcode handlers');

$GLOBALS['meta'][10]['_tt_page_product_recommendations'] = array('title' => 'Original', 'enabled' => '1');
$GLOBALS['meta'][11] = array('_ttpr_page_card_title' => 'การ์ดหนึ่ง <ปลอดภัย>', '_ttpr_page_price' => '1,250.50', '_ttpr_page_image_id' => 111, '_ttpr_page_badge' => 'best');
$GLOBALS['meta'][12] = array('_ttpr_page_image_id' => 999);
$GLOBALS['meta'][13] = array('_ttpc_page_card_title' => 'การ์ดเก่า', '_ttpc_page_price' => '0', '_ttpc_page_image_id' => 113);
$original_meta = $GLOBALS['meta'];
set_selection(array('13', '11', '13', '', '12', '10', '14', '15', '16', '17', '18x', '-18', array('18'), '9999'));
check($GLOBALS['meta'][10][$key]['page_ids'] === array(13, 11, 12), 'Save preserves chosen order, removes duplicates and rejects invalid/unpublished/protected/self/non-page IDs');
$expected_meta = $original_meta;
$expected_meta[10][$key] = $GLOBALS['meta'][10][$key];
check($GLOBALS['meta'] === $expected_meta, 'Save writes only Home settings and preserves all source card metadata');
$html = $plugin->render_shortcode();
check(card_ids($html) === array(13, 11, 12), 'Frontend renders only selected pages in their saved order');
check(strpos($html, 'การ์ดเก่า') !== false && strpos($html, '/images/113.jpg') !== false, 'Legacy source card title and image are reused');
check(strpos($html, 'หน้าสอง') !== false && strpos($html, '/images/featured-12.jpg') !== false, 'Missing card fields fall back to page title and featured image');
check(strpos($html, '฿1,250.50') !== false && strpos($html, '฿0') !== false && substr_count($html, 'class="tthpr-price"') === 2, 'Numeric and zero prices display correctly while missing prices are hidden');
check(strpos($html, 'tthpr-badge--best') !== false && strpos($html, 'data-ttpr-') === false && strpos($html, 'data-tthpr-dynamic') === false, 'Home has isolated markup with no random refresh endpoint');
check(card_ids($plugin->render_shortcode()) === array(13, 11, 12), 'Repeated renders keep the selected order and support multiple instances');

set_selection(array(13, 11, 12), array('limit' => '2'));
check(card_ids($plugin->render_shortcode()) === array(13, 11), 'Limit selects the first matching cards');
$GLOBALS['pages'][13]->post_status = 'draft';
check(card_ids($plugin->render_shortcode()) === array(11, 12), 'A selected page that becomes unpublished is excluded at render time');
$GLOBALS['pages'][13]->post_status = 'publish';

set_selection(array());
$queries = $GLOBALS['query_count'];
check($plugin->render_shortcode() === '' && $GLOBALS['query_count'] === $queries, 'Empty selection renders nothing without querying unrelated pages');
$GLOBALS['is_preview'] = true;
check(strpos($plugin->render_shortcode(), 'เลือกหน้าที่ต้องการแสดง') !== false, 'Editors see setup guidance when no pages are selected');
$GLOBALS['is_preview'] = false;
set_selection(array(11), array('enabled' => '0'));
check($plugin->render_shortcode() === '', 'Disabled Home section stays hidden');

set_selection(array(11), array('title' => '<b>สินค้า</b>', 'limit' => '999', 'view_all_url' => 'javascript:alert(1)'));
check($GLOBALS['meta'][10][$key]['title'] === 'สินค้า' && $GLOBALS['meta'][10][$key]['limit'] === '99' && $GLOBALS['meta'][10][$key]['view_all_url'] === '', 'Settings sanitize text, reject unsafe links, and clamp card limit');
set_selection(array(11), array('title' => array('bad'), 'enabled' => array('bad'), 'limit' => array('bad')));
check($GLOBALS['meta'][10][$key]['enabled'] === '0', 'Malformed settings do not produce warnings or enable the module');

set_selection(array(11));
$saved = $GLOBALS['meta'][10][$key];
$_POST['tt_home_product_recommendations_nonce'] = 'invalid';
$_POST['tthpr_settings']['page_ids'] = array(12);
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $saved, 'Invalid nonce prevents settings changes');
$_POST['tt_home_product_recommendations_nonce'] = 'valid';
$GLOBALS['can_edit'] = false;
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $saved, 'Users without edit permission cannot save settings');
$GLOBALS['can_edit'] = true;
$GLOBALS['revision'] = true;
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $saved, 'Revision saves leave settings untouched');
$GLOBALS['revision'] = false;
unset($_POST['tthpr_settings']);
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $saved, 'Saves without the Home form preserve settings');

$GLOBALS['meta'][14][$key] = $saved;
check($plugin->render_shortcode(array('page_id' => 14)) === '', 'Public shortcode cannot expose a draft settings page');
$GLOBALS['is_preview'] = true;
check(card_ids($plugin->render_shortcode(array('page_id' => 14))) === array(11), 'Authorized editors can preview draft Home settings');
$GLOBALS['is_preview'] = false;
$GLOBALS['queried_id'] = 18;
check(card_ids($plugin->render_shortcode(array('page_id' => 10))) === array(11), 'Explicit page_id uses the selected settings page');
$GLOBALS['queried_id'] = 10;

set_selection(array(13, 11, 12));
ob_start();
$plugin->render_meta_box(get_post(10));
$admin = ob_get_clean();
check(strpos($admin, 'name="tthpr_settings[page_ids][]"') !== false && strpos($admin, '<template data-tthpr-row-template>') !== false, 'Editor renders native Select rows and the add-row template');
check(strpos($admin, 'value="13" selected="selected"') < strpos($admin, 'value="11" selected="selected"'), 'Editor restores saved row order');
check(strpos($admin, 'value="14"') === false && strpos($admin, 'value="15"') === false && strpos($admin, 'value="16"') === false && strpos($admin, 'value="10"') === false, 'Editor choices omit draft, private, password-protected, and current pages');
$GLOBALS['pages'][13]->post_status = 'draft';
ob_start(); $plugin->render_meta_box(get_post(10)); $admin = ob_get_clean();
check(strpos($admin, 'หน้า #13 ไม่พร้อมแสดง') !== false, 'Unavailable saved pages are shown with a clear replacement/removal label');
$GLOBALS['pages'][13]->post_status = 'publish';
define('DOING_AUTOSAVE', true);
set_selection(array(12));
check($GLOBALS['meta'][10][$key]['page_ids'] === array(13, 11, 12), 'Autosaves leave the saved selection untouched');
