<?php
// Reuse the existing WordPress test doubles and gallery media fixtures.
require dirname(__DIR__) . '/modules/tumtook-gallery-auto/tests/smoke.php';
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function add_option($key, $value, ...$args) { if (isset($GLOBALS['options'][$key])) return false; $GLOBALS['options'][$key] = $value; return true; }
function wp_next_scheduled($hook, $args) { return isset($GLOBALS['events'][json_encode($args)]) ? time() : false; }
function wp_schedule_single_event($time, $hook, $args) { $GLOBALS['events'][json_encode($args)] = true; }
function wp_remote_get($url, $args) { return wp_safe_remote_get($url, $args); }
require dirname(__DIR__) . '/includes/class-tumtook-api-cache.php';
foreach (array(new Tumtook_Gallery_Plugin(), new Tumtook_Gallery_Auto_Plugin()) as $plugin) {
    $GLOBALS['cache'] = $GLOBALS['options'] = $GLOBALS['events'] = array();
    $GLOBALS['requests'] = 0;
    $settings = array_merge($plugin::get_default_settings(), array('api_url' => 'https://example.test/gallery', 'match_code' => 'A', 'items_path' => 'items', 'image_key' => 'images.fileUrl', 'alt_key' => 'images.altText'));
    $GLOBALS['api_data'] = array('items' => array(array('code' => 'A', 'images' => array(array('fileUrl' => 'https://example.test/a.jpg')))));
    $method = new ReflectionMethod($plugin, 'get_gallery_items');
    $first = $method->invoke($plugin, $settings['api_url'], $settings, 50, 42);
    check(is_array($first) && count($first) === 1, get_class($plugin) . ': shared cache response parses correctly');
    foreach (array_keys($GLOBALS['cache']) as $key) {
        if (strpos($key, '_last') === false && strpos($key, '_good_') === false) unset($GLOBALS['cache'][$key]);
    }
    $before = $GLOBALS['requests'];
    $stale = $method->invoke($plugin, $settings['api_url'], $settings, 50, 42);
    check($stale === $first && $GLOBALS['requests'] === $before && count($GLOBALS['events']) === 1, get_class($plugin) . ': stale gallery serves without waiting for API');
    foreach (array_keys($GLOBALS['cache']) as $key) {
        if (strpos($key, '_good_') === false) unset($GLOBALS['cache'][$key]);
    }
    $GLOBALS['api_data'] = null;
    $fallback = $method->invoke($plugin, $settings['api_url'], $settings, 50, 42);
    check($fallback === $first, get_class($plugin) . ': invalid upstream preserves processed last-good images');
    $settings['match_code'] = 'B';
    check(is_wp_error($method->invoke($plugin, $settings['api_url'], $settings, 50, 42)), get_class($plugin) . ': fallback cannot mix different item-code filters');
}
