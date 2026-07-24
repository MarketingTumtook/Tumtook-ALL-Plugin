<?php
/**
 * Dynamic block render template.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : ( get_the_ID() ? (int) get_the_ID() : 0 );
if ( ! $post_id || ! class_exists( 'TTCT_Renderer' ) ) {
	return;
}

wp_enqueue_style( 'ttct-frontend' );

$renderer = new TTCT_Renderer();
echo $renderer->render( $post_id );
