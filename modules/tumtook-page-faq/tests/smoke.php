<?php
// Run with PHP CLI; no WordPress database or network required.
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', __DIR__);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$GLOBALS['meta'] = array();
$GLOBALS['preview'] = false;
$GLOBALS['can_edit'] = true;
$GLOBALS['uuid'] = 0;
function add_action(...$args) {}
function add_shortcode($tag, $callback) { $GLOBALS['plugin'] = $callback[0]; }
function __($text, $domain) { return $text; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id] = $value; }
function shortcode_atts($defaults, $atts, $tag) { return array_merge($defaults, $atts); }
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function wp_generate_uuid4() { return 'uuid-' . ++$GLOBALS['uuid']; }
function esc_url_raw($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($value, $action) { return $value === 'valid'; }
function current_user_can(...$args) { return $GLOBALS['can_edit']; }
function is_admin() { return $GLOBALS['preview']; }
function get_queried_object_id() { return 42; }
function is_singular(...$args) { return true; }
function get_the_ID() { return 42; }
function plugin_dir_url($file) { return '/assets/'; }
function wp_register_style(...$args) {}
function wp_register_script(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_rand($min, $max) { return 123; }
function do_shortcode($value) { return 'RENDERED_TEMPLATE'; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS: $message\n"; }
require dirname(__DIR__) . '/tumtook-page-faq.php';
$plugin = $GLOBALS['plugin'];
$settings = new ReflectionMethod($plugin, 'get_settings');
check($plugin->render_shortcode() === '', 'Unconfigured FAQ stays hidden publicly');
$_POST = array('tt_page_faq_nonce' => 'valid', 'ttfq_settings' => array('enabled' => 1, 'title' => 'FAQ', 'items' => array(
    array('item_id' => 'faq-original', 'question' => 'Real question', 'answer' => 'Real answer'),
    array('question' => 'New question', 'answer' => 'New answer'),
    array('item_id' => 'deleted', 'question' => 'Deleted question', 'deleted' => 1),
)));
$plugin->save_meta(42);
$first = $settings->invoke($plugin, 42);
$second = $settings->invoke($plugin, 42);
check($first['items'] === $second['items'] && $first['items'][0]['id'] === 'faq-original', 'Repeated reads preserve existing and newly generated question IDs');
check(count($first['items']) === 2, 'Deleted questions are not saved');
$html = $plugin->render_shortcode();
check(strpos($html, 'Real question') !== false && strpos($html, 'Real answer') !== false, 'Public FAQ renders saved content');
$GLOBALS['preview'] = true;
$html = $plugin->render_shortcode();
check(strpos($html, 'Real question') !== false && strpos($html, '590') === false, 'Editor preview uses real FAQ instead of example content');
$GLOBALS['meta'][42]['items'] = array();
check(strpos($plugin->render_shortcode(), '590') !== false, 'Empty editor preview still has example content');
$GLOBALS['preview'] = false;
check($plugin->render_shortcode() === '', 'Empty FAQ has no public example content');
$GLOBALS['meta'][42] = $first;
$GLOBALS['meta'][42]['support_actions_shortcode'] = '[elementor-template id="123"]';
check(strpos($plugin->render_shortcode(), 'RENDERED_TEMPLATE') !== false, 'Existing Elementor support template still renders');
check(!isset($first['contact_button_label'], $first['contact_button_url'], $first['empty_title'], $first['empty_text']), 'Retired contact and help controls are no longer saved');
$before = $GLOBALS['meta'][42];
$_POST['tt_page_faq_nonce'] = 'invalid';
$plugin->save_meta(42);
check($GLOBALS['meta'][42] === $before, 'Invalid nonce cannot overwrite FAQ');
$_POST['tt_page_faq_nonce'] = 'valid';
$GLOBALS['can_edit'] = false;
$plugin->save_meta(42);
check($GLOBALS['meta'][42] === $before, 'Unauthorized saves cannot overwrite FAQ');
function apply_filters($name, $value) { return $GLOBALS['schema_enabled'] ?? $value; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function schema_output($plugin) { ob_start(); $plugin->render_faq_schema(); return ob_get_clean(); }
$plugin = new Tumtook_Page_FAQ();
check(schema_output($plugin) === '', 'Pages without rendered FAQ have no schema');
$GLOBALS['meta'][42] = $first;
$GLOBALS['meta'][42]['items'][] = array('id' => 'incomplete', 'question' => 'No answer', 'answer' => '');
$GLOBALS['meta'][42]['items'][] = array('id' => 'escape', 'question' => 'ภาษาไทย "quote"', 'answer' => '</script><script>alert("x")</script> & text');
$plugin->render_shortcode();
$plugin->render_shortcode();
$output = schema_output($plugin);
preg_match('~<script[^>]*>(.*?)</script>~s', $output, $matches);
$schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
check($schema['@type'] === 'FAQPage' && count($schema['mainEntity']) === 3, 'One FAQPage contains complete, deduplicated visible questions');
check($schema['mainEntity'][0]['name'] === 'Real question' && $schema['mainEntity'][0]['acceptedAnswer']['text'] === 'Real answer', 'Question and acceptedAnswer match displayed content');
check(substr_count($output, '</script>') === 1 && strpos($output, 'ภาษาไทย') !== false, 'JSON-LD safely escapes script delimiters and preserves Thai text');
check(schema_output($plugin) === '', 'Schema is emitted only once per request');
$plugin = new Tumtook_Page_FAQ();
$GLOBALS['preview'] = true;
$plugin->render_shortcode();
$GLOBALS['preview'] = false;
check(schema_output($plugin) === '', 'Editor previews never contribute schema');
$plugin = new Tumtook_Page_FAQ();
$GLOBALS['meta'][42]['enabled'] = '0';
$plugin->render_shortcode();
check(schema_output($plugin) === '', 'Disabled FAQ emits no schema');
$GLOBALS['meta'][42]['enabled'] = '1';
$plugin->render_shortcode();
$GLOBALS['schema_enabled'] = false;
check(schema_output($plugin) === '', 'SEO integration can disable local FAQ schema');
