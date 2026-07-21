<?php
/**
 * Plugin uninstall.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete = (bool) get_option( 'ttct_delete_data_on_uninstall', false );

if ( $delete ) {
	global $wpdb;
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_tumtook_comparison_table' ), array( '%s' ) );
}

delete_option( 'ttct_delete_data_on_uninstall' );

