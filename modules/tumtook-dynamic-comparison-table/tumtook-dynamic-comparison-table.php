<?php
/**
 * Plugin Name: Tumtook Dynamic Comparison Table
 * Plugin URI: https://tumtook.local/
 * Description: Dynamic product comparison tables per Page with admin table builder, shortcode, and Gutenberg block.
 * Version: 1.0.1
 * Author: Tumtook
 * Text Domain: tumtook-dynamic-comparison-table
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TTCT_VERSION' ) ) {
	define( 'TTCT_VERSION', '1.0.1' );
}
if ( ! defined( 'TTCT_FILE' ) ) {
	define( 'TTCT_FILE', __FILE__ );
}
if ( ! defined( 'TTCT_DIR' ) ) {
	define( 'TTCT_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'TTCT_URL' ) ) {
	define( 'TTCT_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'TTCT_META_KEY' ) ) {
	define( 'TTCT_META_KEY', '_tumtook_comparison_table' );
}
if ( ! defined( 'TTCT_DELETE_OPTION' ) ) {
	define( 'TTCT_DELETE_OPTION', 'ttct_delete_data_on_uninstall' );
}

require_once TTCT_DIR . 'includes/class-ttct-save.php';
require_once TTCT_DIR . 'includes/class-ttct-renderer.php';
require_once TTCT_DIR . 'includes/class-ttct-admin.php';
require_once TTCT_DIR . 'includes/class-ttct-shortcode.php';
require_once TTCT_DIR . 'includes/class-ttct-block.php';
require_once TTCT_DIR . 'includes/class-ttct-plugin.php';

function ttct_bootstrap_plugin(): void {
	static $bootstrapped = false;

	if ( $bootstrapped ) {
		return;
	}

	$bootstrapped = true;
	load_plugin_textdomain( 'tumtook-dynamic-comparison-table', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	TTCT_Plugin::instance()->init();
}

if ( did_action( 'plugins_loaded' ) ) {
	ttct_bootstrap_plugin();
} else {
	add_action( 'plugins_loaded', 'ttct_bootstrap_plugin' );
}
