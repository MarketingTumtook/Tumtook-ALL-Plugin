<?php
// Run: php tests/api-cache.php. No database or external network.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
class WP_Error { public $code; function __construct($code, $message) { $this->code = $code; } }
function add_action(...$args) {}
function wp_json_encode($v) { return json_encode($v); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_transient($k) { return $GLOBALS['cache'][$k] ?? false; }
function set_transient($k, $v, $ttl) { $GLOBALS['cache'][$k] = $v; $GLOBALS['ttls'][$k] = $ttl; }
function get_option($k, $v = false) { return $GLOBALS['options'][$k] ?? $v; }
function delete_option($k) { unset($GLOBALS['options'][$k]); }
function add_option($k, $v, ...$args) { if (isset($GLOBALS['options'][$k])) return false; $GLOBALS['options'][$k] = $v; return true; }
function wp_next_scheduled($hook, $args) { return isset($GLOBALS['events'][json_encode($args)]) ? time() : false; }
function wp_schedule_single_event($at, $hook, $args) { $GLOBALS['events'][json_encode($args)] = $args; }
function wp_remote_get($url, $args) {
    $GLOBALS['calls']++;
    $GLOBALS['last_timeout'] = $args['timeout'];
    check($args['timeout'] <= 15 && $args['redirection'] === 2, 'HTTP request has bounded transport settings');
    return $GLOBALS['response'];
}
function wp_safe_remote_get($url, $args) { $GLOBALS['safe_calls']++; return wp_remote_get($url, $args); }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function check($v, $message) { if (!$v) throw new RuntimeException($message); echo "PASS: $message\n"; }
require dirname(__DIR__) . '/includes/class-tumtook-api-cache.php';
$url = 'https://example.test/catalog';
$good = array('status' => 200, 'body' => '{"items":[{"image":"/one.webp"}]}');
$GLOBALS['calls'] = $GLOBALS['safe_calls'] = 0;
$GLOBALS['response'] = $good;
$key = Tumtook_AIO_API_Cache::key($url, false, 60);
check(Tumtook_AIO_API_Cache::get($url, 60) === $good, 'Cold cache fetches usable JSON');
check($GLOBALS['last_timeout'] === 5, 'Cold page response keeps a five-second per-request timeout');
Tumtook_AIO_API_Cache::get($url, 60);
check($GLOBALS['calls'] === 1, 'Fresh cache avoids another upstream request');
unset($GLOBALS['cache'][$key]);
check(Tumtook_AIO_API_Cache::get($url, 60) === $good && $GLOBALS['calls'] === 1, 'Expired cache serves stale data with zero blocking requests');
Tumtook_AIO_API_Cache::get($url, 60);
check(count($GLOBALS['events']) === 1, 'Repeated stale reads schedule a single background refresh');
$GLOBALS['response'] = new WP_Error('timeout', 'Timeout');
Tumtook_AIO_API_Cache::refresh($url, 60);
check(Tumtook_AIO_API_Cache::get($url, 60) === $good, 'Background failure retains last successful response');
check($GLOBALS['ttls'][$key] === 30, 'Upstream failure uses a short cooldown');
$before = $GLOBALS['calls'];
Tumtook_AIO_API_Cache::get($url, 60);
check($GLOBALS['calls'] === $before, 'Cooldown prevents repeated failed upstream calls');
check(is_wp_error(Tumtook_AIO_API_Cache::get($url, 60, false, true)), 'Forced admin preview reports real upstream failure');
check(!isset($GLOBALS['options'][$key . '_lock']), 'Failure releases database lock');
$GLOBALS['cache'] = array();
$GLOBALS['options'][$key . '_lock'] = time() + 30;
$before = $GLOBALS['calls'];
check(is_wp_error(Tumtook_AIO_API_Cache::get($url, 60)) && $GLOBALS['calls'] === $before, 'Concurrent cold request cannot duplicate the refresh');
$GLOBALS['options'][$key . '_lock'] = time() - 1;
$GLOBALS['response'] = $good;
check(Tumtook_AIO_API_Cache::get($url, 60) === $good, 'Expired lock recovers');
Tumtook_AIO_API_Cache::get($url, 60, true);
check($GLOBALS['safe_calls'] === 1, 'Gallery Auto preserves safe HTTP validation');
$GLOBALS['response'] = array('status' => 200, 'body' => '"not a collection"');
check(is_wp_error(Tumtook_AIO_API_Cache::get('https://example.test/invalid', 60)), 'Primitive JSON cannot overwrite a usable collection');
$GLOBALS['response'] = array('status' => 503, 'body' => '{}');
check(is_wp_error(Tumtook_AIO_API_Cache::get('https://example.test/unavailable', 60)), 'Cold HTTP failure remains an error');
check(Tumtook_AIO_API_Cache::key($url, false, 60) !== Tumtook_AIO_API_Cache::key('https://example.test/other', false, 60), 'Different endpoints cannot share data');

$GLOBALS['response'] = $good;
Tumtook_AIO_API_Cache::refresh_background($url, 60);
check($GLOBALS['last_timeout'] === 15, 'Background refresh can recover slow APIs without extending the page timeout');
