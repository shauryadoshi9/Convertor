<?php
/**
 * MediaConverter Pro - Database Layer
 *
 * Provides safe, prepared queries for job tracking, pagination, stats, and lifecycle management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Database {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'media_conversion_jobs';

	/**
	 * Get full table name with WordPress prefix.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create a new conversion job record.
	 *
	 * @param array $data
	 * @return int|false Job ID on success, false on failure.
	 */
	public function create_job( array $data ) {
		global $wpdb;

		$uuid = ! empty( $data['job_uuid'] ) ? sanitize_text_field( $data['job_uuid'] ) : wp_generate_uuid4();

		$now     = current_time( 'mysql' );
		$options = get_option( 'media_converter_options', array() );
		$is_guest = empty( $data['user_id'] );
		$retention_hours = $is_guest
			? ( isset( $options['guest_retention_hours'] ) ? intval( $options['guest_retention_hours'] ) : 12 )
			: ( isset( $options['user_retention_hours'] ) ? intval( $options['user_retention_hours'] ) : 72 );

		$expires_at = date( 'Y-m-d H:i:s', strtotime( "+{$retention_hours} hours", strtotime( $now ) ) );

		$insert_data = array(
			'job_uuid'          => $uuid,
			'user_id'           => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
			'source_type'       => isset( $data['source_type'] ) ? sanitize_key( $data['source_type'] ) : 'upload',
			'source_url'        => isset( $data['source_url'] ) ? esc_url_raw( $data['source_url'] ) : null,
			'original_filename' => isset( $data['original_filename'] ) ? sanitize_file_name( $data['original_filename'] ) : 'file',
			'input_path'        => isset( $data['input_path'] ) ? sanitize_text_field( $data['input_path'] ) : null,
			'output_filename'   => isset( $data['output_filename'] ) ? sanitize_file_name( $data['output_filename'] ) : null,
			'output_path'       => isset( $data['output_path'] ) ? sanitize_text_field( $data['output_path'] ) : null,
			'output_format'     => isset( $data['output_format'] ) ? sanitize_key( $data['output_format'] ) : 'mp4',
			'output_quality'    => isset( $data['output_quality'] ) ? sanitize_text_field( $data['output_quality'] ) : 'original',
			'trim_start'        => ! empty( $data['trim_start'] ) ? sanitize_text_field( $data['trim_start'] ) : null,
			'trim_end'          => ! empty( $data['trim_end'] ) ? sanitize_text_field( $data['trim_end'] ) : null,
			'status'            => 'queued',
			'stage'             => 'Queued',
			'progress'          => 0,
			'file_size'         => 0,
			'duration_seconds'  => 0,
			'download_token'    => wp_generate_password( 32, false ),
			'download_count'    => 0,
			'error_message'     => null,
			'ip_hash'           => isset( $data['ip_hash'] ) ? sanitize_text_field( $data['ip_hash'] ) : null,
			'created_at'        => $now,
			'expires_at'        => $expires_at,
		);

		$result = $wpdb->insert( $this->get_table_name(), $insert_data );

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get a job record by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function get_job_by_id( $id ) {
		global $wpdb;
		$table = $this->get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Get a job record by UUID.
	 *
	 * @param string $uuid
	 * @return object|null
	 */
	public function get_job_by_uuid( $uuid ) {
		global $wpdb;
		$table = $this->get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE job_uuid = %s", sanitize_text_field( $uuid ) ) );
	}

	/**
	 * Get a job record by download token.
	 *
	 * @param string $token
	 * @return object|null
	 */
	public function get_job_by_token( $token ) {
		global $wpdb;
		$table = $this->get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE download_token = %s", sanitize_text_field( $token ) ) );
	}

	/**
	 * Update job by ID.
	 *
	 * @param int $id
	 * @param array $data
	 * @return bool
	 */
	public function update_job( $id, array $data ) {
		global $wpdb;
		$table = $this->get_table_name();
		$result = $wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
		return ( false !== $result );
	}

	/**
	 * Update job by UUID.
	 *
	 * @param string $uuid
	 * @param array $data
	 * @return bool
	 */
	public function update_job_by_uuid( $uuid, array $data ) {
		global $wpdb;
		$table = $this->get_table_name();
		$result = $wpdb->update( $table, $data, array( 'job_uuid' => sanitize_text_field( $uuid ) ) );
		return ( false !== $result );
	}

	/**
	 * Update conversion progress and stage.
	 *
	 * @param int $job_id
	 * @param float $progress
	 * @param string $stage
	 * @return bool
	 */
	public function update_progress( $job_id, $progress, $stage = '' ) {
		$data = array(
			'progress' => min( 100, max( 0, round( floatval( $progress ), 2 ) ) ),
		);
		if ( ! empty( $stage ) ) {
			$data['stage'] = sanitize_text_field( $stage );
		}
		return $this->update_job( $job_id, $data );
	}

	/**
	 * Mark job as started.
	 *
	 * @param int $job_id
	 * @return bool
	 */
	public function mark_started( $job_id ) {
		return $this->update_job(
			$job_id,
			array(
				'status'     => 'processing',
				'stage'      => 'Processing media',
				'started_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark job as completed.
	 *
	 * @param int $job_id
	 * @param array $extra
	 * @return bool
	 */
	public function mark_completed( $job_id, array $extra = array() ) {
		$data = array_merge(
			array(
				'status'       => 'completed',
				'stage'        => 'Completed',
				'progress'     => 100,
				'completed_at' => current_time( 'mysql' ),
			),
			$extra
		);
		return $this->update_job( $job_id, $data );
	}

	/**
	 * Mark job as failed.
	 *
	 * @param int $job_id
	 * @param string $error_message
	 * @return bool
	 */
	public function mark_failed( $job_id, $error_message ) {
		return $this->update_job(
			$job_id,
			array(
				'status'        => 'failed',
				'stage'         => 'Failed',
				'error_message' => sanitize_text_field( $error_message ),
				'completed_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Increment download counter for a job.
	 *
	 * @param int $job_id
	 */
	public function increment_download_count( $job_id ) {
		global $wpdb;
		$table = $this->get_table_name();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET download_count = download_count + 1 WHERE id = %d",
				absint( $job_id )
			)
		);
	}

	/**
	 * Retrieve a list of jobs with filters, pagination, and sorting.
	 *
	 * @param array $args
	 * @return array
	 */
	public function get_jobs( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'user_id'  => null,
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'page'     => 1,
			'per_page' => 20,
		);

		$params = wp_parse_args( $args, $defaults );
		$table  = $this->get_table_name();

		$where = array( '1=1' );
		$binds = array();

		if ( ! empty( $params['status'] ) ) {
			$where[] = 'status = %s';
			$binds[] = sanitize_text_field( $params['status'] );
		}

		if ( null !== $params['user_id'] ) {
			$where[] = 'user_id = %d';
			$binds[] = absint( $params['user_id'] );
		}

		if ( ! empty( $params['search'] ) ) {
			$where[] = '(original_filename LIKE %s OR output_filename LIKE %s OR job_uuid LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $params['search'] ) . '%';
			$binds[] = $like;
			$binds[] = $like;
			$binds[] = $like;
		}

		$where_clause = implode( ' AND ', $where );

		// Whitelist orderby.
		$allowed_orderby = array( 'id', 'created_at', 'status', 'file_size', 'original_filename' );
		$orderby = in_array( $params['orderby'], $allowed_orderby, true ) ? $params['orderby'] : 'id';
		$order   = ( strtoupper( $params['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

		$per_page = max( 1, absint( $params['per_page'] ) );
		$offset   = ( max( 1, absint( $params['page'] ) ) - 1 ) * $per_page;

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$binds[] = $per_page;
		$binds[] = $offset;

		$prepared = $wpdb->prepare( $query, $binds );
		return $wpdb->get_results( $prepared );
	}

	/**
	 * Count jobs by status or total.
	 *
	 * @param string $status
	 * @param int|null $user_id
	 * @return int
	 */
	public function count_jobs( $status = '', $user_id = null ) {
		global $wpdb;
		$table = $this->get_table_name();

		$where = array( '1=1' );
		$binds = array();

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$binds[] = sanitize_text_field( $status );
		}

		if ( null !== $user_id ) {
			$where[] = 'user_id = %d';
			$binds[] = absint( $user_id );
		}

		$where_clause = implode( ' AND ', $where );

		if ( empty( $binds ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", $binds ) );
	}

	/**
	 * Get aggregated statistics for admin dashboard.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;
		$table = $this->get_table_name();

		$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$completed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" );
		$failed     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" );
		$active     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('queued', 'validating', 'processing', 'encoding', 'finalizing')" );
		$storage    = (float) $wpdb->get_var( "SELECT SUM(file_size) FROM {$table} WHERE status = 'completed'" );
		$duration   = (float) $wpdb->get_var( "SELECT SUM(duration_seconds) FROM {$table} WHERE status = 'completed'" );

		return array(
			'total'             => $total,
			'completed'         => $completed,
			'failed'            => $failed,
			'active'            => $active,
			'storage_bytes'     => $storage ?: 0,
			'duration_seconds'  => $duration ?: 0,
			'success_rate'      => $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 100,
		);
	}

	/**
	 * Fetch expired jobs ready for cleanup.
	 *
	 * @param int $limit
	 * @return array
	 */
	public function get_expired_jobs( $limit = 50 ) {
		global $wpdb;
		$table = $this->get_table_name();
		$now   = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE (expires_at <= %s AND status != 'expired') OR (status = 'expired') LIMIT %d",
				$now,
				absint( $limit )
			)
		);
	}

	/**
	 * Delete job and associated records.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete_job( $id ) {
		global $wpdb;
		$job = $this->get_job_by_id( $id );
		if ( ! $job ) {
			return false;
		}

		// Delete physical temporary input and output files (only delete input if inside temp directory).
		if ( ! empty( $job->input_path ) && file_exists( $job->input_path ) && str_contains( wp_normalize_path( $job->input_path ), wp_normalize_path( MEDIA_CONVERTER_TEMP_DIR ) ) ) {
			@unlink( $job->input_path );
		}
		if ( ! empty( $job->output_path ) && file_exists( $job->output_path ) ) {
			@unlink( $job->output_path );
		}

		$table = $this->get_table_name();
		return (bool) $wpdb->delete( $table, array( 'id' => absint( $id ) ) );
	}
}
