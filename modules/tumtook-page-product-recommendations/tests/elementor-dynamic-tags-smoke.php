<?php
/** Run: php modules/tumtook-page-product-recommendations/tests/elementor-dynamic-tags-smoke.php */
if (!in_array(PHP_SAPI, array('cli', 'cli-server'), true)) {
	exit;
}

define('ABSPATH', __DIR__);
$GLOBALS['meta'] = array();
$GLOBALS['posts'] = array(
	11 => (object) array('ID' => 11, 'post_type' => 'page', 'post_title' => 'Fallback title'),
	12 => (object) array('ID' => 12, 'post_type' => 'page', 'post_title' => 'Legacy page'),
);
$GLOBALS['context_id'] = 11;
$GLOBALS['actions'] = array();

function add_action($hook, $callback) { $GLOBALS['actions'][$hook][] = $callback; }
function __($text, $domain) { return $text; }
function absint($value) { return abs((int) $value); }
function get_the_ID() { return $GLOBALS['context_id']; }
function get_queried_object_id() { return $GLOBALS['context_id']; }
function get_post_type($id) { return isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id]->post_type : false; }
function get_post_meta($id, $key, $single) { return isset($GLOBALS['meta'][$id][$key]) ? $GLOBALS['meta'][$id][$key] : ''; }
function get_post_thumbnail_id($id) { return 11 === $id ? 911 : 0; }
function wp_get_attachment_image_url($id, $size) { return 999 === $id ? false : '/image/' . $id . '.jpg'; }
function get_the_title($id) { return isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id]->post_title : ''; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function number_format_i18n($number, $decimals) { return number_format($number, $decimals); }

function check($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}

require dirname(__DIR__) . '/includes/class-tumtook-elementor-loop-dynamic-tags.php';
$integration = new Tumtook_Elementor_Loop_Dynamic_Tags();

check(isset($GLOBALS['actions']['elementor/dynamic_tags/register']), 'Elementor dynamic-tag registration hook is added');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_title() === 'Fallback title', 'Title falls back to the current Loop Item title');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_image() === array('id' => 911, 'url' => '/image/911.jpg'), 'Image falls back to the featured image');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_price() === '', 'An empty price stays empty');

$GLOBALS['meta'][11] = array(
	'_ttpr_page_card_title' => '<b>Custom title</b>',
	'_ttpr_page_image_id' => 111,
	'_ttpr_page_price' => '1,250.5',
);
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_title() === 'Custom title', 'Current product-card title is returned and sanitized');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_image() === array('id' => 111, 'url' => '/image/111.jpg'), 'Current product-card image is returned');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_price() === '฿1,250.50', 'Numeric prices are formatted consistently');

$GLOBALS['meta'][12] = array(
	'_ttpc_page_card_title' => 'Legacy title',
	'_ttpc_page_image_id' => 112,
	'_ttpc_page_price' => '99 บาท',
);
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_title(12) === 'Legacy title', 'Legacy product-card title remains supported');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_image(12) === array('id' => 112, 'url' => '/image/112.jpg'), 'Legacy product-card image remains supported');
check(Tumtook_Elementor_Loop_Dynamic_Tags::get_price(12) === '99 บาท', 'Already formatted legacy prices remain unchanged');

eval('namespace Elementor\\Core\\DynamicTags; class Tag {} class Data_Tag extends Tag {}');
eval('namespace Elementor\\Modules\\DynamicTags; class Module { const IMAGE_CATEGORY = "image"; const MEDIA_CATEGORY = "media"; const TEXT_CATEGORY = "text"; }');

class Test_Dynamic_Tags_Manager
{
	public $groups = array();
	public $tags = array();

	public function register_group($name, $settings)
	{
		$this->groups[$name] = $settings;
	}

	public function register($tag)
	{
		$this->tags[$tag->get_name()] = $tag;
	}
}

$manager = new Test_Dynamic_Tags_Manager();
$integration->register_tags($manager);
check(isset($manager->groups['tumtook-product-card']), 'Tumtook Product Card group is registered');
check(array_keys($manager->tags) === array('tthpr-image', 'tthpr-product-title', 'tthpr-price'), 'All three Elementor Loop Item tags are registered');

echo "All Elementor dynamic-tag smoke tests passed.\n";
