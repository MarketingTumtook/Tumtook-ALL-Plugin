<?php
/**
 * Main plugin coordinator.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TTCT_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var TTCT_Plugin|null
	 */
	private static ?TTCT_Plugin $instance = null;

	/**
	 * Get instance.
	 */
	public static function instance(): TTCT_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		$renderer  = new TTCT_Renderer();
		$save      = new TTCT_Save();
		$admin     = new TTCT_Admin( $renderer );
		$shortcode = new TTCT_Shortcode( $renderer );
		$block     = new TTCT_Block( $renderer );

		$admin->hooks();
		$save->hooks();
		$shortcode->hooks();
		$block->hooks();
	}

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {}
}

