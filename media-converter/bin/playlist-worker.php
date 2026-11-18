<?php
/**
 * MediaConverter Pro - Background Playlist & Batch Worker
 *
 * Sequentially converts playlist items in the background, updates real-time batch progress,
 * and packages all completed tracks into a single 1-click ZIP archive.
 */

if ( php_sapi_name() !== 'cli' ) {
	die( 'CLI access only.' );
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

$batch_uuid = '';
foreach ( $argv as $arg ) {
	if ( str_starts_with( $arg, '--batch_uuid=' ) ) {
		$batch_uuid = sanitize_text_field( substr( $arg, 13 ) );
	}
}

if ( empty( $batch_uuid ) ) {
	fwrite( STDERR, "Error: No --batch_uuid parameter provided.\n" );
	exit( 1 );
}

if ( ! function_exists( 'media_converter' ) ) {
	fwrite( STDERR, "Error: media_converter plugin not loaded.\n" );
	exit( 1 );
}

$database  = media_converter()->database;
$converter = media_converter()->converter;

$batch = $database->get_batch_by_uuid( $batch_uuid );
if ( ! $batch ) {
	fwrite( STDERR, "Error: Batch not found in database: {$batch_uuid}\n" );
	exit( 1 );
}

$database->update_batch(
	$batch_uuid,
	array(
		'status' => 'processing',
		'stage'  => 'Starting playlist batch conversion...',
	)
);

$jobs = $database->get_batch_jobs( $batch_uuid );
$total_jobs = count( $jobs );
$completed = 0;
$failed = 0;
$output_files = array();

foreach ( $jobs as $index => $job ) {
	$item_num = $index + 1;
	$stage_text = sprintf( 'Converting [%d/%d]: %s', $item_num, $total_jobs, $job->original_filename );

	$database->update_batch(
		$batch_uuid,
		array(
			'stage'           => $stage_text,
			'completed_items' => $completed,
			'failed_items'    => $failed,
		)
	);

	echo "Processing item {$item_num}/{$total_jobs}: {$job->original_filename} (Job #{$job->id})...\n";

	$result = $converter->process_job( $job->id );

	$refreshed_job = $database->get_job_by_id( $job->id );
	if ( $refreshed_job && 'completed' === $refreshed_job->status && ! empty( $refreshed_job->output_path ) && file_exists( $refreshed_job->output_path ) ) {
		$completed++;
		$output_files[] = $refreshed_job->output_path;
		echo "Item {$item_num} finished successfully.\n";
	} else {
		$failed++;
		echo "Item {$item_num} failed: " . ( $refreshed_job->error_message ?? 'Unknown' ) . "\n";
	}

	$database->update_batch(
		$batch_uuid,
		array(
			'completed_items' => $completed,
			'failed_items'    => $failed,
		)
	);
}

// All items processed - create ZIP archive of all completed media files
if ( $completed > 0 && ! empty( $output_files ) ) {
	$database->update_batch(
		$batch_uuid,
		array(
			'stage' => 'Packaging completed playlist files into ZIP archive...',
		)
	);

	$raw_title    = ! empty( $batch->playlist_title ) ? $batch->playlist_title : 'Playlist';
	$safe_title   = sanitize_file_name( $raw_title );
	$zip_filename = sprintf( '%s_%s_%s.zip', $safe_title, strtoupper( $batch->output_format ), substr( $batch_uuid, 0, 8 ) );
	$zip_path     = MEDIA_CONVERTER_OUTPUT_DIR . $zip_filename;

	$zip_created = false;

	// Method 1: PHP ZipArchive if enabled
	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true === $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			foreach ( $output_files as $file_path ) {
				if ( file_exists( $file_path ) ) {
					$zip->addFile( $file_path, basename( $file_path ) );
				}
			}
			$zip->close();
			$zip_created = file_exists( $zip_path ) && filesize( $zip_path ) > 0;
		}
	}

	// Method 2: System tar -a -cf (Standard on modern Windows, Linux, and macOS)
	if ( ! $zip_created ) {
		$file_basenames = array();
		foreach ( $output_files as $file_path ) {
			if ( file_exists( $file_path ) ) {
				$file_basenames[] = escapeshellarg( basename( $file_path ) );
			}
		}

		if ( ! empty( $file_basenames ) ) {
			$tar_cmd = sprintf(
				'tar -a -cf %s -C %s %s',
				escapeshellarg( $zip_path ),
				escapeshellarg( rtrim( MEDIA_CONVERTER_OUTPUT_DIR, '/\\' ) ),
				implode( ' ', $file_basenames )
			);
			@shell_exec( $tar_cmd );
			$zip_created = file_exists( $zip_path ) && filesize( $zip_path ) > 0;
		}
	}

	// Method 3: PowerShell Compress-Archive (Windows fallback)
	if ( ! $zip_created && stripos( PHP_OS, 'WIN' ) === 0 ) {
		$quoted_files = array_map( function( $f ) { return "'" . addslashes( $f ) . "'"; }, $output_files );
		$ps_cmd = sprintf(
			'powershell -NoProfile -Command "Compress-Archive -Path %s -DestinationPath \'%s\' -Force"',
			implode( ',', $quoted_files ),
			addslashes( $zip_path )
		);
		@shell_exec( $ps_cmd );
		$zip_created = file_exists( $zip_path ) && filesize( $zip_path ) > 0;
	}

	$zip_size = $zip_created ? filesize( $zip_path ) : 0;
	$database->mark_batch_completed( $batch_uuid, $zip_filename, $zip_path, $zip_size );

	echo "Batch {$batch_uuid} complete! Output ZIP: {$zip_filename} ({$zip_size} bytes).\n";
	exit( 0 );
} else {
	$database->update_batch(
		$batch_uuid,
		array(
			'status' => 'failed',
			'stage'  => 'All playlist items failed conversion.',
		)
	);
	echo "Batch {$batch_uuid} failed: 0 items completed.\n";
	exit( 1 );
}
