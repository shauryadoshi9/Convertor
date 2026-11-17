<?php
/**
 * MediaConverter Pro - File and Job Cleanup Routine
 *
 * Automatically purges expired media files from disk and marks database records expired.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Cleanup {

	/**
	 * Database instance.
	 *
	 * @var MediaConverter_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param MediaConverter_Database $database
	 */
	public function __construct( MediaConverter_Database $database ) {
		$this->database = $database;
		add_action( 'media_converter_cleanup_cron', array( $this, 'run_cleanup' ) );
	}

	/**
	 * Execute automated cleanup.
	 *
	 * @return int Number of cleaned jobs
	 */
	public function run_cleanup() {
		$expired_jobs = $this->database->get_expired_jobs( 100 );
		$cleaned_count = 0;

		foreach ( $expired_jobs as $job ) {
			// Delete output file.
			if ( ! empty( $job->output_path ) && file_exists( $job->output_path ) ) {
				@unlink( $job->output_path );
			}

			// Delete input file if remaining.
			if ( ! empty( $job->input_path ) && file_exists( $job->input_path ) ) {
				@unlink( $job->input_path );
			}

			$this->database->update_job(
				$job->id,
				array(
					'status'      => 'expired',
					'stage'       => 'File expired and cleaned up',
					'output_path' => null,
					'input_path'  => null,
				)
			);

			$cleaned_count++;
		}

		// Also clean orphaned temp files in MEDIA_CONVERTER_TEMP_DIR older than 3 hours.
		$this->clean_orphaned_temp_files();

		return $cleaned_count;
	}

	/**
	 * Clean orphaned files in temp directory older than 3 hours.
	 */
	private function clean_orphaned_temp_files() {
		if ( ! is_dir( MEDIA_CONVERTER_TEMP_DIR ) ) {
			return;
		}

		$files = glob( MEDIA_CONVERTER_TEMP_DIR . '*' );
		$threshold = time() - ( 3 * HOUR_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( is_file( $file ) && basename( $file ) !== 'index.php' && basename( $file ) !== '.htaccess' ) {
				if ( filemtime( $file ) < $threshold ) {
					@unlink( $file );
				}
			}
		}
	}
}
