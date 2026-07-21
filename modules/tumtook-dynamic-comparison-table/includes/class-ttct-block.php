<?php
/**
 * Gutenberg block integration.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTCT_Block {
	/**
	 * Renderer.
	 *
	 * @var TTCT_Renderer
	 */
	private TTCT_Renderer $renderer;

	/**
	 * Constructor.
	 */
	public function __construct( TTCT_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register shared frontend assets.
	 */
	public function register_assets(): void {
		wp_register_style( 'ttct-frontend', TTCT_URL . 'assets/css/frontend.css', array( 'dashicons' ), TTCT_VERSION );
	}

	/**
	 * Register dynamic block.
	 */
	public function register_block(): void {
		register_block_type( TTCT_DIR . 'blocks/comparison-table' );
	}
}
