<?php
/**
 * Shortcode integration.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTCT_Shortcode {
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
		add_shortcode( 'tumtook_comparison', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render shortcode.
	 */
	public function render_shortcode( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'page_id' => 0,
				'id'      => 0,
			),
			is_array( $atts ) ? $atts : array(),
			'tumtook_comparison'
		);

		$post_id = absint( $atts['page_id'] ?: $atts['id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID() ? (int) get_the_ID() : 0;
		}
		if ( ! $post_id ) {
			$post_id = get_queried_object_id() ? (int) get_queried_object_id() : 0;
		}
		if ( ! $post_id ) {
			return '';
		}

		wp_enqueue_style( 'ttct-frontend' );

		return $this->renderer->render( $post_id );
	}
}
