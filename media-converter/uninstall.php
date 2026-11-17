<?php
/**
 * MediaConverter Pro - Uninstall Script
 *
 * Cleans up options, database tables, and scheduled crons when plugin is deleted.
 */

// Exit if accessed directly or uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Unschedule cron jobs.
$timestamp = wp_next_scheduled( 'media_converter_cleanup_cron' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'media_converter_cleanup_cron' );
}

$worker_timestamp = wp_next_scheduled( 'media_converter_process_queue_cron' );
if ( $worker_timestamp ) {
	wp_unschedule_event( $worker_timestamp, 'media_converter_process_queue_cron' );
}

// Delete database table.
$table_name = $wpdb->prefix . 'media_conversion_jobs';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete plugin options.
delete_option( 'media_converter_options' );
delete_option( 'media_converter_db_version' );

// Clear transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_media_converter_%' OR option_name LIKE '_transient_timeout_media_converter_%'" );
