<?php
/**
 * MediaConverter Pro - Background CLI Worker
 *
 * Executes conversion jobs asynchronously in a detached process,
 * ensuring the HTTP server remains fully responsive to polling requests.
 */

if ( php_sapi_name() !== 'cli' ) {
	die( 'CLI access only.' );
}

$job_id = 0;
foreach ( $argv as $arg ) {
	if ( strpos( $arg, '--job_id=' ) === 0 ) {
		$job_id = intval( substr( $arg, 9 ) );
	}
}

if ( ! $job_id ) {
	fwrite( STDERR, "Error: No --job_id parameter provided.\n" );
	exit( 1 );
}

// Locate WordPress runtime or standalone emulator
$wp_load   = dirname( __DIR__, 3 ) . '/wp-load.php';
$simulator = dirname( __DIR__, 2 ) . '/standalone/wp-simulator.php';

if ( file_exists( $wp_load ) ) {
	require_once $wp_load;
} elseif ( file_exists( $simulator ) ) {
	require_once $simulator;
} else {
	fwrite( STDERR, "Error: Could not locate WordPress runtime or standalone environment.\n" );
	exit( 1 );
}

if ( ! function_exists( 'media_converter' ) ) {
	fwrite( STDERR, "Error: media_converter plugin not loaded.\n" );
	exit( 1 );
}

// Process the job
$converter = media_converter()->converter;
$result    = $converter->process_job( $job_id );

if ( ! empty( $result['success'] ) ) {
	echo "Job #{$job_id} completed successfully.\n";
	exit( 0 );
} else {
	fwrite( STDERR, "Job #{$job_id} failed: " . ( $result['error'] ?? 'Unknown error' ) . "\n" );
	exit( 1 );
}
