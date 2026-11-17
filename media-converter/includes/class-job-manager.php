<?php
/**
 * MediaConverter Pro - Background Job Manager
 *
 * Manages queued conversion jobs, concurrency throttling, and WP Cron queue runner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Job_Manager {

	/**
	 * Database instance.
	 *
	 * @var MediaConverter_Database
	 */
	private $database;

	/**
	 * Converter instance.
	 *
	 * @var MediaConverter_Converter
	 */
	private $converter;

	/**
	 * Constructor.
	 *
	 * @param MediaConverter_Database  $database
	 * @param MediaConverter_Converter $converter
	 */
	public function __construct( MediaConverter_Database $database, MediaConverter_Converter $converter ) {
		$this->database  = $database;
		$this->converter = $converter;

		add_action( 'media_converter_process_queue_cron', array( $this, 'process_queue' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
	}

	/**
	 * Register 1-minute custom cron schedule interval.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'media-converter' ),
		);
		return $schedules;
	}

	/**
	 * Enqueue a conversion job.
	 *
	 * @param array $job_data
	 * @param bool  $sync Whether to process synchronously (true for CLI/tests, false for HTTP requests)
	 * @return array ['success' => bool, 'job_id' => int, 'uuid' => string]
	 */
	public function enqueue( array $job_data, $sync = false ) {
		$job_id = $this->database->create_job( $job_data );
		if ( ! $job_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to create conversion job in database.', 'media-converter' ),
			);
		}

		$job = $this->database->get_job_by_id( $job_id );

		if ( $sync ) {
			// Trigger processing immediately in this process (for tests and CLI).
			$result = $this->converter->process_job( $job_id );
			return array(
				'success' => $result['success'],
				'job_id'  => $job_id,
				'uuid'    => $job->job_uuid,
				'error'   => $result['error'] ?? '',
				'job'     => $result['job'] ?? $job,
			);
		}

		// Asynchronous non-blocking dispatch: spawn background worker process.
		$spawned = $this->spawn_worker( $job_id );

		return array(
			'success' => true,
			'job_id'  => $job_id,
			'uuid'    => $job->job_uuid,
			'job'     => $job,
			'async'   => $spawned,
		);
	}

	/**
	 * Spawn background CLI worker process detached from current request.
	 *
	 * @param int $job_id
	 * @return bool
	 */
	public function spawn_worker( $job_id ) {
		$worker_script = MEDIA_CONVERTER_PATH . 'bin/worker.php';
		if ( ! file_exists( $worker_script ) ) {
			return false;
		}

		$php_bin = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';

		if ( stripos( PHP_OS, 'WIN' ) === 0 ) {
			// Windows background detached process using start /B
			$cmd = sprintf(
				'start "" /B %s %s --job_id=%d > NUL 2>&1',
				escapeshellarg( $php_bin ),
				escapeshellarg( $worker_script ),
				intval( $job_id )
			);
			$handle = @popen( $cmd, 'r' );
			if ( $handle ) {
				@pclose( $handle );
				return true;
			}
		} else {
			// Linux / macOS background detached process
			$cmd = sprintf(
				'%s %s --job_id=%d > /dev/null 2>&1 &',
				escapeshellarg( $php_bin ),
				escapeshellarg( $worker_script ),
				intval( $job_id )
			);
			@exec( $cmd );
			return true;
		}

		return false;
	}

	/**
	 * Process pending queued jobs obeying concurrency limit.
	 */
	public function process_queue() {
		$options = get_option( 'media_converter_options', array() );
		$max_concurrent = isset( $options['max_concurrent_jobs'] ) ? intval( $options['max_concurrent_jobs'] ) : 3;

		$active_count = $this->database->count_jobs( 'processing' );
		if ( $active_count >= $max_concurrent ) {
			return; // Max concurrency reached
		}

		$slots_available = $max_concurrent - $active_count;
		$queued_jobs     = $this->database->get_jobs(
			array(
				'status'   => 'queued',
				'per_page' => $slots_available,
				'orderby'  => 'id',
				'order'    => 'ASC',
			)
		);

		foreach ( $queued_jobs as $job ) {
			$this->converter->process_job( $job->id );
		}
	}

	/**
	 * Retry a failed job.
	 *
	 * @param int $job_id
	 * @return bool
	 */
	public function retry_job( $job_id ) {
		$job = $this->database->get_job_by_id( $job_id );
		if ( ! $job ) {
			return false;
		}

		$this->database->update_job(
			$job_id,
			array(
				'status'        => 'queued',
				'stage'         => 'Queued for retry',
				'progress'      => 0,
				'error_message' => null,
				'created_at'    => current_time( 'mysql' ),
			)
		);

		// Execute.
		$this->converter->process_job( $job_id );
		return true;
	}

	/**
	 * Cancel a pending or running job.
	 *
	 * @param int $job_id
	 * @return bool
	 */
	public function cancel_job( $job_id ) {
		$job = $this->database->get_job_by_id( $job_id );
		if ( ! $job ) {
			return false;
		}

		return $this->database->update_job(
			$job_id,
			array(
				'status'        => 'cancelled',
				'stage'         => 'Job Cancelled',
				'error_message' => __( 'Conversion was cancelled by user/admin.', 'media-converter' ),
			)
		);
	}
}
