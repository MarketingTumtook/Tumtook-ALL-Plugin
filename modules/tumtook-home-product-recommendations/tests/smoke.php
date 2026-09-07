<?php
/** Run: php modules/tumtook-home-product-recommendations/tests/smoke.php (no database). */
if (!in_array(PHP_SAPI, array('cli', 'cli-server'), true)) {
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
foreach (array(10 => 'Home', 11 => 'อาหารหนึ่ง', 12 => 'แบรนด์หนึ่ง', 13 => 'อาหารสอง', 14 => 'Draft', 15 => 'Private', 16 => 'Protected', 17 => 'Article', 18 => 'ใช้ซ้ำได้') as $id => $title) {
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
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
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
		$ordered = array();
		foreach ($args['post__in'] as $id) if (isset($pages[$id])) $ordered[$id] = $pages[$id];
		$pages = $ordered;
	} else {
		usort($pages, function ($a, $b) { return strcmp($a->post_title, $b->post_title); });
	}
	if ($args['posts_per_page'] > 0) $pages = array_slice($pages, 0, $args['posts_per_page'], true);
	return 'ids' === ($args['fields'] ?? '') ? wp_list_pluck(array_values($pages), 'ID') : array_values($pages);
}
function check($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	echo "PASS: $message\n";
}
function section($id, $title, $ids, $extra = array()) {
	return array_merge(array('id' => $id, 'enabled' => '1', 'title' => $title, 'page_ids' => $ids), $extra);
}
function save_sections($sections) {
	$_POST = array('tt_home_product_recommendations_nonce' => 'valid', 'tthpr_sections' => $sections);
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

check(in_array('tumtook-home-product-recommendations/tumtook-home-product-recommendations.php', array_column(tumtook_aio_get_modules(), 'file'), true), 'All-in-One registers the repeatable Home module');
check($GLOBALS['shortcodes']['tumtook_recommended_products'][0] instanceof Tumtook_Page_Product_Recommendations, 'Original recommendations shortcode keeps its handler');
$GLOBALS['meta'][11] = array('_ttpr_page_card_title' => 'การ์ดอาหารหนึ่ง', '_ttpr_page_price' => '1,250.50', '_ttpr_page_image_id' => 111, '_ttpr_page_badge' => 'best');
$GLOBALS['meta'][12] = array('_ttpr_page_image_id' => 999);
$GLOBALS['meta'][13] = array('_ttpc_page_card_title' => 'การ์ดอาหารสอง', '_ttpc_page_price' => '0', '_ttpc_page_image_id' => 113);

save_sections(array(
	'food' => section('food', '<b>อาหารและเดลิเวอรี่</b>', array('13', '11', '13', '10', '14', '15', '16', '17', '18x')),
	'branding' => section('branding', 'สร้างแบรนด์', array('12', '18'), array('view_all_url' => '/branding')),
));
$saved = $GLOBALS['meta'][10][$key];
check($saved['schema_version'] === 2 && count($saved['sections']) === 2, 'Save stores the repeatable Section schema');
check($saved['sections'][0]['title'] === 'อาหารและเดลิเวอรี่', 'Section text is sanitized');
check($saved['sections'][0]['page_ids'] === array(13, 11), 'Each Section preserves order and removes invalid Page IDs');
check($saved['sections'][1]['page_ids'] === array(12, 18), 'A second Section stores its own Card selection');
$html = $plugin->render_shortcode();
check(card_ids($html) === array(13, 11, 12, 18), 'One shortcode renders every Section and Card in saved order');
check(substr_count($html, 'class="tthpr-section"') === 2 && strpos($html, 'อาหารและเดลิเวอรี่') < strpos($html, 'สร้างแบรนด์'), 'Each saved Section renders with its own heading');
check(strpos($html, 'data-tthpr-section-id="food"') !== false && strpos($html, 'data-tthpr-section-id="branding"') !== false, 'Rendered sections expose stable Section IDs');
check(card_ids($plugin->render_shortcode(array('section' => 'branding'))) === array(12, 18), 'Section attribute renders only the requested Section');
check(strpos($plugin->render_shortcode(array('section' => 'branding')), 'อาหารและเดลิเวอรี่') === false, 'Requested Section does not include other headings');
check($plugin->render_shortcode(array('section' => 'missing')) === '', 'Unknown Section stays hidden publicly');

save_sections(array(
	'one' => section('same-id', 'หนึ่ง', array(18)),
	'two' => section('same-id', 'สอง', array(18), array('limit' => '1')),
	'three' => section('off', 'ปิด', array(11), array('enabled' => '0')),
));
$saved = $GLOBALS['meta'][10][$key];
check(array_column($saved['sections'], 'id') === array('same-id', 'same-id-2', 'off'), 'Duplicate Section IDs are made unique');
check(card_ids($plugin->render_shortcode()) === array(18, 18), 'The same Page can be selected independently in multiple Sections');
check(strpos($plugin->render_shortcode(), 'ปิด') === false, 'Disabled Sections are not rendered');

$GLOBALS['meta'][10][$key] = array('enabled' => '1', 'title' => 'ข้อมูลเดิม', 'page_ids' => array(13, 11), 'button_label' => 'ดูรายละเอียด');
$html = $plugin->render_shortcode();
check(card_ids($html) === array(13, 11) && strpos($html, 'ข้อมูลเดิม') !== false, 'Version 1 settings migrate to the default Section without data loss');

save_sections(array(
	'food' => section('food', 'อาหาร', array(13, 11)),
	'branding' => section('branding', 'แบรนด์', array(12, 18)),
));
ob_start(); $plugin->render_meta_box(get_post(10)); $admin = ob_get_clean();
check(substr_count($admin, 'data-tthpr-section data-section-id=') === 3, 'Editor renders two Sections plus its add-Section template');
check(strpos($admin, 'tthpr_sections[food][page_ids][]') !== false && strpos($admin, 'tthpr_sections[branding][page_ids][]') !== false, 'Card selects are scoped to their Section');
check(strpos($admin, '[tumtook_home_recommended_products section=&quot;food&quot;]') !== false, 'Editor shows a copyable shortcode for each Section');
check(strpos($admin, 'value="14"') === false && strpos($admin, 'value="15"') === false && strpos($admin, 'value="16"') === false && strpos($admin, 'value="10"') === false, 'Editor choices omit unavailable and current pages');

$unchanged = $GLOBALS['meta'][10][$key];
$_POST['tt_home_product_recommendations_nonce'] = 'invalid';
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $unchanged, 'Invalid nonce prevents changes');
$_POST['tt_home_product_recommendations_nonce'] = 'valid';
$GLOBALS['can_edit'] = false;
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $unchanged, 'Users without edit permission cannot save');
$GLOBALS['can_edit'] = true;
$GLOBALS['revision'] = true;
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $unchanged, 'Revision saves preserve settings');
$GLOBALS['revision'] = false;
unset($_POST['tthpr_sections']);
$plugin->save_meta(10);
check($GLOBALS['meta'][10][$key] === $unchanged, 'Saves without the module form preserve settings');

$GLOBALS['is_preview'] = true;
check(strpos($plugin->render_shortcode(array('section' => 'missing')), 'ไม่พบ Section') !== false, 'Editors receive guidance for an unknown Section ID');
$GLOBALS['is_preview'] = false;
$GLOBALS['meta'][14][$key] = $unchanged;
check($plugin->render_shortcode(array('page_id' => 14)) === '', 'Public shortcode does not expose draft settings pages');
$GLOBALS['is_preview'] = true;
check(card_ids($plugin->render_shortcode(array('page_id' => 14, 'section' => 'food'))) === array(13, 11), 'Authorized editors can preview a draft settings page');
