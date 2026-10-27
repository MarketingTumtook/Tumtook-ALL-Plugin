<?php
/** Shared JSON response cache. Stale responses refresh through WordPress cron. */
if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_AIO_API_Cache
{
	const HOOK = 'tumtook_aio_refresh_api_response';

	public static function get($url, $ttl, $safe = false, $force = false)
	{
		$ttl = max(60, (int) $ttl);
		$key = self::key($url, $safe, $ttl);
		$fresh = get_transient($key);
		$last = get_transient($key . '_last');
		if (!$force) {
			if (false !== $fresh) {
				return is_wp_error($fresh) && false !== $last ? $last : $fresh;
			}
			if (false !== $last) {
				self::schedule($url, $ttl, $safe);
				// Serve usable data without waiting for an upstream request.
				return $last;
			}
		}
		$response = self::refresh($url, $ttl, $safe);
		if (!$force && is_wp_error($response)) self::schedule($url, $ttl, $safe);
		return !$force && is_wp_error($response) && false !== $last ? $last : $response;
	}

	private static function schedule($url, $ttl, $safe)
	{
		$args = array($url, $ttl, (bool) $safe);
		if (!wp_next_scheduled(self::HOOK, $args)) {
			wp_schedule_single_event(time(), self::HOOK, $args);
		}
	}

	public static function refresh_background($url, $ttl, $safe = false)
	{
		// A slow-but-working API gets a longer window outside the page response.
		return self::refresh($url, $ttl, $safe, 15);
	}

	public static function key($url, $safe, $ttl)
	{
		return 'tt_aio_http_' . md5(wp_json_encode(array($url, (bool) $safe, (int) $ttl)));
	}

	public static function refresh($url, $ttl, $safe = false, $timeout = 5)
	{
		$key = self::key($url, $safe, $ttl);
		$lock = $key . '_lock';
		$expires = (int) get_option($lock, 0);
		if ($expires && $expires < time()) {
			delete_option($lock);
		}
		if (!add_option($lock, time() + 90, '', false)) {
			return new WP_Error('tt_aio_api_busy', 'API refresh already in progress.');
		}
		try {
			$args = array(
				'headers' => array('Accept' => 'application/json'),
				'timeout' => max(1, min(15, (int) $timeout)),
				'redirection' => 2,
			);
			$response = $safe ? wp_safe_remote_get($url, $args) : wp_remote_get($url, $args);
			if (!is_wp_error($response)) {
				$status = wp_remote_retrieve_response_code($response);
				$data = json_decode(wp_remote_retrieve_body($response), true);
				if ($status < 200 || $status >= 300) {
					$response = new WP_Error('tt_aio_api_http', 'API returned HTTP ' . (int) $status . '.');
				} elseif (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
					$response = new WP_Error('tt_aio_api_json', 'API did not return a JSON collection.');
				}
			}
			if (is_wp_error($response)) {
				set_transient($key, $response, 30);
			} else {
				set_transient($key, $response, max(60, (int) $ttl));
				set_transient($key . '_last', $response, 7 * DAY_IN_SECONDS);
			}
			return $response;
		} finally {
			delete_option($lock);
		}
	}
}
add_action(Tumtook_AIO_API_Cache::HOOK, array('Tumtook_AIO_API_Cache', 'refresh_background'), 10, 3);
