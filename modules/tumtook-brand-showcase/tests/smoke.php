<?php
/** Run: php modules/tumtook-brand-showcase/tests/smoke.php (no database). */
if ('cli' !== PHP_SAPI) exit;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('ABSPATH', dirname(__DIR__, 6) . '/');
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/shortcodes.php';

$GLOBALS['meta'] = array();
$GLOBALS['assets'] = array();
$GLOBALS['queried_id'] = 10;
$GLOBALS['singular'] = true;
$GLOBALS['can_edit'] = true;
$GLOBALS['checks'] = array();
function plugins_url($path, $file) { return '/' . $path; }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function wp_html_split($text) { return array($text); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function sanitize_text_field($value) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value))); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function esc_url_raw($value) { return preg_match('/^javascript:/i', $value) ? '' : $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_attr(esc_url_raw($value)); }
function esc_html($value) { return esc_attr($value); }
function esc_textarea($value) { return esc_attr($value); }
function __($value, $domain) { return $value; }
function esc_html_e($value, $domain) { echo esc_html($value); }
function esc_attr_e($value, $domain) { echo esc_attr($value); }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid">'; }
function wp_verify_nonce($nonce, $action) { return 'valid' === $nonce; }
function current_user_can(...$args) { return $GLOBALS['can_edit']; }
function absint($value) { return abs((int) $value); }
function get_queried_object_id() { return $GLOBALS['queried_id']; }
function is_singular($type) { return $GLOBALS['singular']; }
function wp_get_upload_dir() { return array('baseurl' => '/images'); }
function wp_get_attachment_url($id) { return ''; }
function wp_generate_uuid4() { static $id = 0; return 'test-' . ++$id; }
function wp_register_style(...$args) {}
function wp_register_script(...$args) {}
function wp_enqueue_style($handle) { $GLOBALS['assets']['styles'][] = $handle; }
function wp_enqueue_script($handle) { $GLOBALS['assets']['scripts'][] = $handle; }
function check($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	$GLOBALS['checks'][] = 'PASS: ' . $message;
}
function parse_html($html) {
	$doc = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	return new DOMXPath($doc);
}

require dirname(__DIR__) . '/tumtook-brand-showcase.php';
$plugin = $GLOBALS['shortcode_tags']['tumtook_brand_showcase'][0];
$key = Tumtook_Brand_Showcase_Plugin::META_KEY;
$GLOBALS['meta'][10][$key] = array('title' => '', 'slides' => array(
	array('image_url' => '/images/sample.webp', 'alt' => 'Karaked', 'link_url' => '#karaked'),
	array('image_url' => '/images/sample.webp', 'alt' => 'นัวนิยม', 'link_url' => '#nuaniyom'),
));
$legacy = do_shortcode('[tumtook_brand_showcase]');
check(substr_count($legacy, 'data-slide>') === 2 && strpos($legacy, 'ttbs-showcase--cards') === false, 'Existing shortcode retains the classic layout and images');
$html = do_shortcode('[tumtook_brand_showcase layout="cards"]');
check(strpos($html, 'ttbs-showcase--cards') !== false && strpos($html, '>Karaked</h3>') !== false, 'Existing data can use portrait cards with alt text as the name fallback');
check(strpos($html, 'ttbs-showcase__card-link-icon') === false, 'Cards omit the classic overlay arrow button');
check(in_array('tumtook-brand-showcase', $GLOBALS['assets']['styles'], true)
	&& in_array('tumtook-brand-showcase', $GLOBALS['assets']['scripts'], true), 'Cards enqueue slider styles and behavior');
check(strpos(do_shortcode('[tumtook_brand_showcase layout="unknown"]'), 'ttbs-showcase--cards') === false, 'An unsupported layout falls back to the classic design');
check(strpos(do_shortcode('[tumtook_brand_showCase layout="cards"]'), 'ttbs-showcase--cards') !== false, 'The legacy shortcode alias supports cards');

$_POST = array('tumtook_brand_showcase_nonce' => 'valid', 'tumtook_brand_showcase_data' => array('title' => '', 'slides' => array(
	array('image_url' => '/images/sample.webp', 'alt' => 'ถ้วยกระดาษ', 'link_url' => '#karaked', 'brand_name' => '<b>Karaked</b>', 'description' => "ถ้วยกระดาษพิมพ์ลายใส่อาหาร\n<b>สั่งผลิตได้</b>"),
	array('image_url' => '/images/sample.webp', 'alt' => '', 'link_url' => '#nuaniyom', 'brand_name' => 'นัวนิยม', 'description' => 'เดลิเวอรี่เซ็ต ถ้วยกระดาษ กระดาษรองอาหาร และถุงกระดาษ'),
	array('image_url' => '/images/sample.webp', 'alt' => '', 'link_url' => '', 'brand_name' => 'Chuga Rest', 'description' => 'ถ้วยกระดาษใส่ซุปไม่มีลิงก์'),
	array('image_url' => '/images/sample.webp', 'alt' => '', 'link_url' => 'javascript:alert(1)', 'brand_name' => 'โกโก้สยาม', 'description' => ''),
)));
$plugin->save_page_meta(20);
$saved = $GLOBALS['meta'][20][$key];
check($saved['slides'][0]['brand_name'] === 'Karaked' && $saved['slides'][0]['description'] === "ถ้วยกระดาษพิมพ์ลายใส่อาหาร\nสั่งผลิตได้", 'Save sanitizes the brand name and preserves description line breaks');
check(count($saved['slides']) === 6 && $saved['slides'][3]['link_url'] === '', 'Save preserves six slide slots and removes unsafe URLs');
$html = do_shortcode('[tumtook_brand_showcase page_id="20" layout="cards"]');
$xpath = parse_html($html);
check($xpath->query('//article/a[@class="ttbs-brand-card"]')->length === 2, 'Each linked card uses one anchor wrapping its content');
check($xpath->query('//a[@class="ttbs-brand-card"]/div/img')->length === 2
	&& $xpath->query('//a[@class="ttbs-brand-card"]//h3')->length === 2
	&& $xpath->query('//a[@class="ttbs-brand-card"]//p')->length === 2, 'Images, names and descriptions are all inside the card link');
check($xpath->query('//a//a | //a[@class="ttbs-brand-card"]//button')->length === 0, 'Card links contain no nested interactive controls');
check($xpath->query('//article/div[@class="ttbs-brand-card"]')->length === 2, 'Cards without safe URLs render as non-link content');
check($xpath->query('//p[@class="ttbs-brand-card__description"]')->length === 3, 'An empty description adds no empty paragraph');
check(strpos(do_shortcode('[tumtook_brand_showcase page_id="20"]'), 'ttbs-brand-card__name') === false, 'New text fields do not appear in the classic design');
check($GLOBALS['meta'][20][$key] === $saved, 'Rendering both designs does not change saved data');

$GLOBALS['singular'] = false;
check(strpos(do_shortcode('[tumtook_brand_showcase page_id="20" layout="cards"]'), '>นัวนิยม</h3>') !== false, 'Explicit page_id reuses source cards outside a page context');
check(do_shortcode('[tumtook_brand_showcase layout="cards"]') === '', 'A template with no source shows no unrelated cards');
check(do_shortcode('[tumtook_brand_showcase page_id="999" layout="cards"]') === '', 'An empty source renders nothing');
$html = do_shortcode('[tumtook_brand_showcase page_id="20"] [tumtook_brand_showcase page_id="20" layout="cards"]');
preg_match_all('/id="(ttbs-showcase-[^"]+)"/', $html, $ids);
check(count(array_unique($ids[1])) === 2, 'The same source can render both designs with separate slider IDs');

$_POST['tumtook_brand_showcase_nonce'] = 'invalid';
$_POST['tumtook_brand_showcase_data']['slides'][0]['brand_name'] = 'Changed';
$plugin->save_page_meta(20);
check($GLOBALS['meta'][20][$key] === $saved, 'An invalid nonce cannot overwrite the new fields');
$_POST['tumtook_brand_showcase_nonce'] = 'valid';
$GLOBALS['can_edit'] = false;
$plugin->save_page_meta(20);
check($GLOBALS['meta'][20][$key] === $saved, 'A user without edit permission cannot overwrite the new fields');
ob_start(); $plugin->render_page_meta_box((object) array('ID' => 20)); $admin = ob_get_clean();
check(strpos($admin, '[tumtook_brand_showcase page_id=&quot;20&quot; layout=&quot;cards&quot;]') !== false, 'The editor shows a copyable cards shortcode for its source page');
check(substr_count($admin, '][brand_name]') === 6 && substr_count($admin, '][description]') === 6, 'The editor includes brand and description inputs for every slide');

if (in_array('--preview', $argv, true)) {
	// A fixture for browser checks; no database or site content is changed.
	$GLOBALS['meta'][20][$key]['slides'][0]['description'] = 'ถ้วยกระดาษพิมพ์ลายใส่อาหาร';
	$GLOBALS['meta'][20][$key]['slides'][2]['link_url'] = '#chuga';
	$GLOBALS['meta'][20][$key]['slides'][3]['link_url'] = '#cocoa';
	$GLOBALS['meta'][20][$key]['slides'][3]['description'] = 'ถ้วยกระดาษใส่แกงสำหรับเดลิเวอรี่';
	$GLOBALS['meta'][20][$key]['slides'][4] = $GLOBALS['meta'][20][$key]['slides'][1];
	$GLOBALS['meta'][20][$key]['slides'][5] = $GLOBALS['meta'][20][$key]['slides'][0];
	echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<link rel="stylesheet" href="/assets/css/tumtook-brand-showcase.css">';
	echo '<style>body{margin:0;padding:18px 15px;font-family:sans-serif}main{max-width:1180px;margin:auto}.narrow{width:360px;max-width:100%;margin-top:60px}</style><main>';
	echo do_shortcode('[tumtook_brand_showcase page_id="20" layout="cards"]');
	echo '<div class="narrow">' . do_shortcode('[tumtook_brand_showcase page_id="20" layout="cards"]') . '</div>';
	echo '</main><script src="/assets/js/tumtook-brand-showcase.js"></script></html>';
} else {
	echo implode("\n", $GLOBALS['checks']) . "\n";
}
