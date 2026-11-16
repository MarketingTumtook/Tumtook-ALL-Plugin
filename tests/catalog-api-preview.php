<?php
// Run: php tests/catalog-api-preview.php. No database or network required.
require __DIR__ . '/api-cache.php';
define('MINUTE_IN_SECONDS', 60);
function register_activation_hook(...$args) {}
function add_shortcode(...$args) {}
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function __($message, ...$args) { return $message; }
function absint($value) { return abs((int) $value); }
function get_post_meta(...$args) { return ''; }
function check_ajax_referer(...$args) {}
function current_user_can(...$args) { return true; }
function wp_unslash($value) { return $value; }
function esc_url_raw($value) { return $value; }
function sanitize_text_field($value) { return $value; }
class Preview_Result extends RuntimeException {
    public $payload;
    function __construct($payload) { $this->payload = $payload; }
}
function wp_send_json_error($payload, $status = null) { throw new Preview_Result($payload); }
require dirname(__DIR__) . '/modules/tumtook-api-catalog-viewer/api-catalog-viewer.php';
$plugin = new API_Catalog_Images_Plugin();
$request = new ReflectionMethod($plugin, 'request_catalog');
$settings = array_merge($plugin::get_default_settings(), array('api_url' => 'https://example.test/preview'));
$GLOBALS['cache'] = $GLOBALS['options'] = array();
$GLOBALS['response'] = $good;
$request->invoke($plugin, $settings, true);
check($GLOBALS['last_timeout'] === 15, 'Explicit catalog preview allows a slow API fifteen seconds');
$settings['api_url'] .= '-public';
$request->invoke($plugin, $settings, false);
check($GLOBALS['last_timeout'] === 5, 'Public catalog keeps the five-second request budget');

// Invalid item paths must reach the editor with an actionable message.
$_POST = array('settings' => $settings, 'post_id' => 42);
$GLOBALS['response'] = array('status' => 200, 'body' => '{"unexpected":[]}');
try {
    $plugin->ajax_preview_page_images();
    throw new RuntimeException('Expected preview error');
} catch (Preview_Result $result) {
    check(strpos($result->payload['message'], 'configured items path') !== false, 'Preview returns the parsing error instead of a generic failure');
}

// A valid collection with no matching code has a distinct diagnosis.
$settings['item_code_filter'] = 'missing';
$_POST['settings'] = $settings;
$GLOBALS['response'] = array('status' => 200, 'body' => '{"items":[{"code":"A"}]}');
try {
    $plugin->ajax_preview_page_images();
    throw new RuntimeException('Expected filter error');
} catch (Preview_Result $result) {
    check(strpos($result->payload['message'], 'item code filter') !== false, 'Preview distinguishes a filter mismatch from connection failure');
}

foreach (array(
    array(array('status' => 401, 'body' => '{}'), 'HTTP 401'),
    array(array('status' => 503, 'body' => '{}'), 'HTTP 503'),
    array(array('status' => 200, 'body' => '<html>Error</html>'), 'JSON collection'),
    array(new WP_Error('http_request_failed', 'cURL error 28: timed out https://example.test/?key=SECRET'), 'timed out'),
    array(new WP_Error('http_request_failed', 'DNS error https://example.test/?key=SECRET'), 'server connection'),
    array(new WP_Error('tt_aio_api_busy', 'API refresh already in progress.'), 'already in progress'),
) as $case) {
    $GLOBALS['response'] = $case[0];
    try {
        $plugin->ajax_preview_page_images();
        throw new RuntimeException('Expected upstream error');
    } catch (Preview_Result $result) {
        check(strpos($result->payload['message'], $case[1]) !== false, 'Preview diagnosis: ' . $case[1]);
        check(strpos($result->payload['message'], 'SECRET') === false, 'Preview never exposes upstream credentials');
    }
}
