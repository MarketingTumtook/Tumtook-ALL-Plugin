<?php
// Run: php tests/server-query-bounds.php.
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
function add_action(...$args) {}
function add_shortcode(...$args) {}
function absint($value) { return abs((int) $value); }
function wp_json_encode($v) { return json_encode($v); }
function get_transient($k) { return $GLOBALS['cache'][$k] ?? false; }
function set_transient($k, $v, $ttl) { $GLOBALS['cache'][$k] = $v; }
function get_option($k, $default) { return $default; }
function get_posts($args) { $GLOBALS['queries'][] = $args; return array(); }
function _prime_post_caches(...$args) {}
function wp_reset_postdata() {}
class WP_Query { public $posts = array(); function __construct($args) { $GLOBALS['queries'][] = $args; } }
function check($value, $message) { if (!$value) throw new RuntimeException($message); echo "PASS: $message\n"; }
require dirname(__DIR__) . '/modules/tumtook-job-working-cards/tumtook-job-working-cards.php';
require dirname(__DIR__) . '/modules/tumtook-page-product-recommendations/tumtook-page-product-recommendations.php';
$jobs = new Tumtook_Job_Working_Cards();
$method = new ReflectionMethod($jobs, 'get_job_items');
$settings = array('limit' => 0, 'post_type' => 'job', 'orderby' => 'rand', 'include' => '', 'exclude' => '', 'taxonomy' => '', 'category' => '', 'category_id' => '');
$GLOBALS['queries'] = array();
$method->invoke($jobs, $settings);
check($GLOBALS['queries'][0]['posts_per_page'] === 12, 'Jobs default is bounded to twelve instead of all posts');
$method->invoke($jobs, $settings);
check(count($GLOBALS['queries']) === 1, 'Repeated Jobs request uses cache even for an empty result');
$settings['limit'] = 100000;
$method->invoke($jobs, $settings);
check(end($GLOBALS['queries'])['posts_per_page'] === 50, 'Large Jobs limits cannot exceed fifty posts');
$settings['limit'] = 2;
$settings['include'] = '1,2,3,4,5';
$method->invoke($jobs, $settings);
check(end($GLOBALS['queries'])['posts_per_page'] === 2, 'Include lists cannot expand the query beyond the display limit');
$product = new Tumtook_Page_Product_Recommendations();
$method = new ReflectionMethod($product, 'get_recommended_pages');
$before = count($GLOBALS['queries']);
$method->invoke($product, 42, array('limit' => 6));
$method->invoke($product, 42, array('limit' => 6));
check(count($GLOBALS['queries']) === $before + 1, 'Product recommendations reuse a short cache for repeated requests');
$method->invoke($product, 43, array('limit' => 6));
check(count($GLOBALS['queries']) === $before + 2, 'Different source pages keep distinct recommendations');
