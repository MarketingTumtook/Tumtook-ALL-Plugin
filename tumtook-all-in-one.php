<?php
/**
 * Plugin Name: Tumtook All-in-One Modules
 * Description: Combined Tumtook page modules: API catalog viewer, brand showcase, PDF catalog, gallery, article recommendations, product cards, product recommendations, and video how-to slider.
 * Version: 1.0.15
 * Author: Tumtook
 * Text Domain: tumtook-all-in-one
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('TUMTOOK_AIO_VERSION')) {
	define('TUMTOOK_AIO_VERSION', '1.0.15');
}

if (!defined('TUMTOOK_AIO_PLUGIN_FILE')) {
	define('TUMTOOK_AIO_PLUGIN_FILE', __FILE__);
}

if (!defined('TUMTOOK_AIO_PLUGIN_DIR')) {
	define('TUMTOOK_AIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('TUMTOOK_AIO_MODULES_DIR')) {
	define('TUMTOOK_AIO_MODULES_DIR', TUMTOOK_AIO_PLUGIN_DIR . 'modules/');
}

function tumtook_aio_asset_version($module_file, $relative_path, $fallback_version = TUMTOOK_AIO_VERSION)
{
	static $versions = array();

	$cache_key = $module_file . '|' . $relative_path;
	if (isset($versions[$cache_key])) {
		return $versions[$cache_key];
	}

	if (defined('WP_DEBUG') && WP_DEBUG) {
		$file_path = plugin_dir_path($module_file) . ltrim($relative_path, '/');
		if (file_exists($file_path)) {
			$mtime = filemtime($file_path);
			if (false !== $mtime) {
				$versions[$cache_key] = (string) $mtime;
				return $versions[$cache_key];
			}
		}
	}

	$versions[$cache_key] = (string) $fallback_version;
	return $versions[$cache_key];
}

function tumtook_aio_register_kanit_font($handle)
{
	if (wp_style_is($handle, 'registered')) {
		return;
	}

	wp_register_style(
		$handle,
		'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap',
		array(),
		null
	);
}

/**
 * Modules are intentionally kept in their original folders so each module's
 * plugin_dir_url(__FILE__) and plugin_dir_path(__FILE__) calls continue to
 * resolve assets and vendor files correctly after being merged.
 */
function tumtook_aio_get_modules()
{
	return array(
		array(
			'name' => 'Tumtook API Catalog Viewer',
			'file' => 'tumtook-api-catalog-viewer/api-catalog-viewer.php',
			'guard' => array('type' => 'class', 'name' => 'API_Catalog_Images_Plugin'),
		),
		array(
			'name' => 'Tumtook Brand Showcase',
			'file' => 'tumtook-brand-showcase/tumtook-brand-showcase.php',
			'guard' => array('type' => 'class', 'name' => 'Tumtook_Brand_Showcase_Plugin'),
		),
		array(
			'name' => 'Tumtook Gallery',
			'file' => 'tumtook-gallery/tumtook-gallery.php',
			'guard' => array('type' => 'class', 'name' => 'Tumtook_Gallery_Plugin'),
		),
		array(
			'name' => 'Tumtook Page FAQ',
			'file' => 'tumtook-page-faq/tumtook-page-faq.php',
			'guard' => array('type' => 'class', 'name' => 'Tumtook_Page_FAQ'),
		),
		array(
			'name' => 'Tumtook Page Article Recommendations',
			'file' => 'tumtook-page-article-recommendations/tumtook-page-article-recommendations.php',
			'guard' => array('type' => 'class', 'name' => 'Tumtook_Page_Article_Recommendations'),
		),
		array(
			'name' => 'Tumtook Page Product Cards',
			'file' => 'tumtook-page-product-cards/tumtook-page-product-cards.php',
			'guard' => array('type' => 'class', 'name' => 'Tumtook_Page_Product_Cards'),
		),
		array(
			'name' => 'Tumtook Page Product Recommendations',
			'file' => 'tumtook-page-product-recommendations/tumtook-page-product-recommendations.php',
			'guard' => array('type' => 'class', 'name' => 'Tumtook_Page_Product_Recommendations'),
		),
		array(
			'name' => 'Tumtook Video How To Slider',
			'file' => 'tumtook-video-howtoknow-slider/tumtook-video-howtoknow-slider.php',
			'guard' => array('type' => 'class', 'name' => 'Video_Howtoknow_Slider_Plugin'),
		),
	);
}

function tumtook_aio_guard_exists($guard)
{
	if (!is_array($guard) || empty($guard['type']) || empty($guard['name'])) {
		return false;
	}

	if ('class' === $guard['type']) {
		return class_exists($guard['name'], false);
	}

	if ('function' === $guard['type']) {
		return function_exists($guard['name']);
	}

	if ('constant' === $guard['type']) {
		return defined($guard['name']);
	}

	return false;
}

function tumtook_aio_load_modules()
{
	global $tumtook_aio_loaded_modules, $tumtook_aio_skipped_modules;

	if (!is_array($tumtook_aio_loaded_modules ?? null)) {
		$tumtook_aio_loaded_modules = array();
	}

	if (!is_array($tumtook_aio_skipped_modules ?? null)) {
		$tumtook_aio_skipped_modules = array();
	}

	foreach (tumtook_aio_get_modules() as $module) {
		$module_file = TUMTOOK_AIO_MODULES_DIR . $module['file'];

		if (!empty($module['guard']) && tumtook_aio_guard_exists($module['guard'])) {
			$tumtook_aio_skipped_modules[$module['name']] = $module['guard']['name'];
			continue;
		}

		if (!file_exists($module_file)) {
			$tumtook_aio_skipped_modules[$module['name']] = 'missing file';
			continue;
		}

		require_once $module_file;
		$tumtook_aio_loaded_modules[] = $module['name'];
	}
}
add_action('plugins_loaded', 'tumtook_aio_load_modules', 1);

function tumtook_aio_activate()
{
	tumtook_aio_load_modules();

	if (class_exists('API_Catalog_Images_Plugin', false) && method_exists('API_Catalog_Images_Plugin', 'activate')) {
		API_Catalog_Images_Plugin::activate();
	}

	if (class_exists('Tumtook_Gallery_Plugin', false) && method_exists('Tumtook_Gallery_Plugin', 'activate')) {
		Tumtook_Gallery_Plugin::activate();
	}
}
register_activation_hook(__FILE__, 'tumtook_aio_activate');

function tumtook_aio_admin_notice_conflicts()
{
	global $tumtook_aio_skipped_modules;

	if (empty($tumtook_aio_skipped_modules) || !current_user_can('activate_plugins')) {
		return;
	}

	$items = array();
	foreach ($tumtook_aio_skipped_modules as $module_name => $reason) {
		$items[] = sprintf('%s (%s)', $module_name, $reason);
	}

	echo '<div class="notice notice-warning"><p><strong>Tumtook All-in-One Modules:</strong> Some bundled modules were skipped because the same class/function already exists. Please deactivate the old standalone Tumtook plugins before using the combined plugin.</p><p>' . esc_html(implode(', ', $items)) . '</p></div>';
}
add_action('admin_notices', 'tumtook_aio_admin_notice_conflicts');
