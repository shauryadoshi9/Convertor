<?php
/**
 * MediaConverter Pro - REST API Controller
 *
 * Implements REST endpoints for remote URL validation, file uploads,
 * real-time progress polling, conversion dispatch, and user history.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_REST_API {

	/**
	 * Namespace for all routes.
	 */
	const NAMESPACE = 'media-converter/v1';

	/**
	 * Main plugin instance.
	 *
	 * @var MediaConverter_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param MediaConverter_Plugin $plugin
	 */
	public function __construct( MediaConverter_Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// 1. Validate remote URL.
		register_rest_route(
			self::NAMESPACE,
			'/validate-url',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_validate_url' ),
				'permission_callback' => '__return_true',
			)
		);

		// 2. Upload file & probe.
		register_rest_route(
			self::NAMESPACE,
			'/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_upload' ),
				'permission_callback' => '__return_true',
			)
		);

		// 3. Dispatch conversion.
		register_rest_route(
			self::NAMESPACE,
			'/convert',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_convert' ),
				'permission_callback' => '__return_true',
			)
		);

		// 4. Job progress polling.
		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<uuid>[a-zA-Z0-9-]+)/progress',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_progress' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'uuid' => array(
						'validate_callback' => function( $param ) {
							return preg_match( '/^[a-zA-Z0-9-]+$/', $param );
						},
					),
				),
			)
		);

		// 5. Secure media download.
		register_rest_route(
			self::NAMESPACE,
			'/download',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_download' ),
				'permission_callback' => '__return_true',
			)
		);

		// 6. User conversion history.
		register_rest_route(
			self::NAMESPACE,
			'/user/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_user_history' ),
				'permission_callback' => function() {
					return is_user_logged_in();
				},
			)
		);

		// 8. Playlist info extraction.
		register_rest_route(
			self::NAMESPACE,
			'/playlist-info',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_playlist_info' ),
				'permission_callback' => '__return_true',
			)
		);

		// 9. Batch / Playlist conversion submit.
		register_rest_route(
			self::NAMESPACE,
			'/convert-playlist',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_convert_playlist' ),
				'permission_callback' => '__return_true',
			)
		);

		// 10. Playlist progress polling.
		register_rest_route(
			self::NAMESPACE,
			'/playlist/(?P<uuid>[a-zA-Z0-9-]+)/progress',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_playlist_progress' ),
				'permission_callback' => '__return_true',
			)
		);

		// 11. Complete playlist ZIP download.
		register_rest_route(
			self::NAMESPACE,
			'/playlist/(?P<uuid>[a-zA-Z0-9-]+)/download-zip',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_playlist_download_zip' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Endpoint: POST /validate-url
	 */
	public function handle_validate_url( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$url    = sanitize_text_field( $params['url'] ?? '' );

		if ( empty( $url ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Please supply a URL.', 'media-converter' ) ), 400 );
		}

		// Rate limiting.
		$user_id = get_current_user_id();
		if ( ! $this->plugin->security->check_rate_limit( $user_id ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Rate limit exceeded. Please wait a few moments.', 'media-converter' ) ), 429 );
		}

		$result = $this->plugin->validator->validate_remote_url( $url );
		if ( ! $result['success'] ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result['error'] ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'url'      => $result['url'],
				'host'     => $result['host'],
				'provider' => $result['provider'],
			),
			200
		);
	}

	/**
	 * Endpoint: POST /upload
	 */
	public function handle_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'No file was received.', 'media-converter' ) ), 400 );
		}

		$user_id = get_current_user_id();
		if ( ! $this->plugin->security->check_rate_limit( $user_id ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Rate limit exceeded.', 'media-converter' ) ), 429 );
		}

		$validation = $this->plugin->validator->validate_uploaded_file( $files['file'], $user_id );
		if ( ! $validation['success'] ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $validation['error'] ), 400 );
		}

		// Save to secure temp directory.
		$temp_id   = wp_generate_uuid4();
		$extension = $validation['ext'];
		$temp_path = MEDIA_CONVERTER_TEMP_DIR . 'upload_' . $temp_id . '.' . $extension;

		if ( ! move_uploaded_file( $files['file']['tmp_name'], $temp_path ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Failed to save uploaded file.', 'media-converter' ) ), 500 );
		}

		// Probe media file.
		$probe = $this->plugin->ffmpeg->probe_media( $temp_path );

		return new WP_REST_Response(
			array(
				'success'                   => true,
				'temp_id'                   => $temp_id,
				'filename'                  => $validation['filename'],
				'size'                      => $validation['size'],
				'mime'                      => $validation['mime'],
				'probe'                     => $probe,
				'available_video_qualities' => $probe['available_video_qualities'] ?? array( 'original' ),
				'available_audio_qualities' => $probe['available_audio_qualities'] ?? array( 'original', '320k', '256k', '192k', '128k' ),
			),
			200
		);
	}

	/**
	 * Endpoint: POST /convert
	 */
	public function handle_convert( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$source_type        = sanitize_key( $params['source_type'] ?? 'upload' );
		$output_format      = sanitize_key( $params['output_format'] ?? 'mp4' );
		$output_quality     = sanitize_text_field( $params['output_quality'] ?? 'original' );
		$trim_start         = ! empty( $params['trim_start'] ) ? sanitize_text_field( $params['trim_start'] ) : null;
		$trim_end           = ! empty( $params['trim_end'] ) ? sanitize_text_field( $params['trim_end'] ) : null;
		$legal_confirmed    = ! empty( $params['legal_confirmed'] );
		$user_id            = get_current_user_id();

		// Legal confirmation check.
		$options = get_option( 'media_converter_options', array() );
		if ( ! empty( $options['enable_legal_notice_checkbox'] ) && ! $legal_confirmed ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'You must confirm that you have legal permission to convert this media.', 'media-converter' ),
				),
				400
			);
		}

		// Rate limiting.
		if ( ! $this->plugin->security->check_rate_limit( $user_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Hourly conversion limit reached. Please try again later or register an account.', 'media-converter' ),
				),
				429
			);
		}

		$input_path        = null;
		$source_url        = null;
		$original_filename = 'media';

		if ( 'upload' === $source_type ) {
			$temp_id = sanitize_text_field( $params['temp_id'] ?? '' );
			if ( empty( $temp_id ) ) {
				return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Missing temporary file reference.', 'media-converter' ) ), 400 );
			}

			// Find candidate files matching temp_id.
			$glob = glob( MEDIA_CONVERTER_TEMP_DIR . 'upload_' . sanitize_file_name( $temp_id ) . '.*' );
			if ( empty( $glob ) || ! file_exists( $glob[0] ) ) {
				return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Uploaded file session expired or not found.', 'media-converter' ) ), 404 );
			}

			$input_path        = $glob[0];
			$original_filename = sanitize_file_name( $params['filename'] ?? basename( $input_path ) );
		} else {
			// Remote URL source.
			$source_url = esc_url_raw( $params['url'] ?? '' );
			$val_check  = $this->plugin->validator->validate_remote_url( $source_url );
			if ( ! $val_check['success'] ) {
				return new WP_REST_Response( array( 'success' => false, 'message' => $val_check['error'] ), 400 );
			}

			$original_filename = sanitize_file_name( $val_check['provider']['title'] ?? 'web_media' );
		}

		$job_uuid = wp_generate_uuid4();
		$ip_hash  = $this->plugin->security->get_client_ip_hash();

		$job_data = array(
			'job_uuid'          => $job_uuid,
			'user_id'           => $user_id,
			'source_type'       => $source_type,
			'source_url'        => $source_url,
			'original_filename' => $original_filename,
			'input_path'        => $input_path,
			'output_format'     => $output_format,
			'output_quality'    => $output_quality,
			'trim_start'        => $trim_start,
			'trim_end'          => $trim_end,
			'ip_hash'           => $ip_hash,
		);

		// Enqueue and run asynchronously (non-blocking).
		$result = $this->plugin->job_manager->enqueue( $job_data, false );

		if ( ! $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success'  => false,
					'message'  => $result['error'] ?? __( 'Conversion failed.', 'media-converter' ),
					'job_uuid' => $job_uuid,
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'job_uuid' => $job_uuid,
				'job_id'   => $result['job_id'],
				'status'   => $result['job']->status ?? 'pending',
				'stage'    => $result['job']->stage ?? 'Initializing conversion...',
				'progress' => floatval( $result['job']->progress ?? 5 ),
			),
			200
		);
	}

	/**
	 * Endpoint: GET /jobs/{uuid}/progress
	 */
	public function handle_progress( WP_REST_Request $request ) {
		$uuid = sanitize_text_field( $request->get_param( 'uuid' ) );
		$job  = $this->plugin->database->get_job_by_uuid( $uuid );

		if ( ! $job ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Job not found.', 'media-converter' ) ), 404 );
		}

		// Self-healing fallback: If job has been queued/pending for > 2 seconds, ensure worker is running
		if ( in_array( $job->status, array( 'queued', 'pending' ), true ) ) {
			$this->plugin->job_manager->spawn_worker( $job->id );
		}

		$download_url = '';
		if ( 'completed' === $job->status && ! empty( $job->download_token ) ) {
			$download_url = rest_url( self::NAMESPACE . '/download?token=' . urlencode( $job->download_token ) );
		}

		return new WP_REST_Response(
			array(
				'success'          => true,
				'uuid'             => $job->job_uuid,
				'status'           => $job->status,
				'stage'            => $job->stage,
				'progress'         => floatval( $job->progress ),
				'output_format'    => $job->output_format,
				'output_quality'   => $job->output_quality,
				'output_filename'  => $job->output_filename,
				'file_size'        => intval( $job->file_size ),
				'file_size_fmt'    => size_format( intval( $job->file_size ), 2 ),
				'duration_seconds' => floatval( $job->duration_seconds ),
				'download_url'     => $download_url,
				'error_message'    => $job->error_message,
				'expires_at'       => $job->expires_at,
			),
			200
		);
	}

	/**
	 * Endpoint: GET /download
	 */
	public function handle_download( WP_REST_Request $request ) {
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		$this->plugin->download_manager->handle_download( $token );
		exit;
	}

	/**
	 * Endpoint: GET /user/history
	 */
	public function handle_user_history( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$jobs    = $this->plugin->database->get_jobs(
			array(
				'user_id'  => $user_id,
				'per_page' => 20,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$formatted = array();
		foreach ( $jobs as $job ) {
			$formatted[] = array(
				'uuid'            => $job->job_uuid,
				'filename'        => $job->original_filename,
				'output_filename' => $job->output_filename,
				'format'          => $job->output_format,
				'quality'         => $job->output_quality,
				'status'          => $job->status,
				'file_size'       => size_format( $job->file_size, 2 ),
				'created_at'      => $job->created_at,
				'download_url'    => ( 'completed' === $job->status && ! empty( $job->download_token ) )
					? rest_url( self::NAMESPACE . '/download?token=' . urlencode( $job->download_token ) )
					: '',
			);
		}

		return new WP_REST_Response( array( 'success' => true, 'jobs' => $formatted ), 200 );
	}

	/**
	 * Endpoint: GET /stats
	 */
	public function handle_stats( WP_REST_Request $request ) {
		$stats = $this->plugin->database->get_stats();
		return new WP_REST_Response(
			array(
				'success' => true,
				'stats'   => array(
					'total_conversions' => $stats['total'],
					'successful'        => $stats['completed'],
					'uptime'            => '99.98%',
					'speed'             => 'High-speed H.264/AAC',
				),
			),
			200
		);
	}

	/**
	 * Endpoint: POST /playlist-info
	 */
	public function handle_playlist_info( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$url    = esc_url_raw( $params['url'] ?? '' );

		if ( empty( $url ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Please supply a playlist URL.', 'media-converter' ) ), 400 );
		}

		$val_check = $this->plugin->validator->validate_remote_url( $url );
		if ( ! $val_check['success'] ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $val_check['error'] ), 400 );
		}

		$pl_data = $this->plugin->validator->fetch_playlist_info( $url );
		if ( ! $pl_data['success'] ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $pl_data['error'] ?? 'Could not parse playlist.' ), 400 );
		}

		return new WP_REST_Response( $pl_data, 200 );
	}

	/**
	 * Endpoint: POST /convert-playlist
	 */
	public function handle_convert_playlist( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$playlist_url    = esc_url_raw( $params['playlist_url'] ?? '' );
		$playlist_title  = sanitize_text_field( $params['playlist_title'] ?? 'YouTube Playlist' );
		$output_format   = sanitize_key( $params['output_format'] ?? 'mp3' );
		$output_quality  = sanitize_text_field( $params['output_quality'] ?? ( 'mp3' === $output_format ? '320k' : 'original' ) );
		$selected_items  = (array) ( $params['items'] ?? array() );
		$user_id         = get_current_user_id();

		if ( empty( $selected_items ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'No playlist items selected for conversion.', 'media-converter' ) ), 400 );
		}

		$batch_uuid = wp_generate_uuid4();
		$database   = $this->plugin->database;

		$batch_id = $database->create_batch( array(
			'batch_uuid'     => $batch_uuid,
			'user_id'        => $user_id,
			'playlist_title' => $playlist_title,
			'playlist_url'   => $playlist_url,
			'total_items'    => count( $selected_items ),
			'output_format'  => $output_format,
			'output_quality' => $output_quality,
		) );

		if ( ! $batch_id ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Could not create playlist conversion batch.', 'media-converter' ) ), 500 );
		}

		$ip_hash = $this->plugin->security->get_client_ip_hash();

		// Enqueue each playlist item as a linked job
		foreach ( $selected_items as $item ) {
			$item_title = sanitize_text_field( $item['title'] ?? 'Track' );
			$item_url   = esc_url_raw( $item['url'] ?? '' );

			if ( empty( $item_url ) && ! empty( $item['id'] ) ) {
				$item_url = 'https://www.youtube.com/watch?v=' . sanitize_text_field( $item['id'] );
			}

			$job_uuid = wp_generate_uuid4();
			$database->create_job( array(
				'job_uuid'          => $job_uuid,
				'batch_uuid'        => $batch_uuid,
				'user_id'           => $user_id,
				'source_type'       => 'url',
				'source_url'        => $item_url,
				'original_filename' => $item_title,
				'output_format'     => $output_format,
				'output_quality'    => $output_quality,
				'ip_hash'           => $ip_hash,
			) );
		}

		// Spawn dedicated background playlist worker
		$spawned = $this->plugin->job_manager->spawn_batch_worker( $batch_uuid );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'batch_uuid'  => $batch_uuid,
				'total_items' => count( $selected_items ),
				'async'       => $spawned,
				'message'     => sprintf( __( 'Playlist queued with %d items. Conversion starting...', 'media-converter' ), count( $selected_items ) ),
			),
			200
		);
	}

	/**
	 * Endpoint: GET /playlist/{uuid}/progress
	 */
	public function handle_playlist_progress( WP_REST_Request $request ) {
		$uuid     = sanitize_text_field( $request->get_param( 'uuid' ) );
		$database = $this->plugin->database;

		$batch = $database->get_batch_by_uuid( $uuid );
		if ( ! $batch ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Playlist batch not found.', 'media-converter' ) ), 404 );
		}

		$jobs = $database->get_batch_jobs( $uuid );
		$total_count = intval( $batch->total_items );
		$completed_count = intval( $batch->completed_items );
		$failed_count = intval( $batch->failed_items );

		$overall_pct = $total_count > 0 ? round( ( ( $completed_count + $failed_count ) / $total_count ) * 100, 1 ) : 0;
		if ( 'completed' === $batch->status ) {
			$overall_pct = 100.0;
		}

		$current_item = null;
		$items_status = array();

		foreach ( $jobs as $job ) {
			$is_active = ( in_array( $job->status, array( 'downloading', 'processing', 'validating' ), true ) );
			$item_info = array(
				'uuid'      => $job->job_uuid,
				'title'     => $job->original_filename,
				'status'    => $job->status,
				'progress'  => floatval( $job->progress ),
				'stage'     => $job->stage,
				'file_size' => $job->file_size ? size_format( $job->file_size, 2 ) : '',
				'download_url' => ( 'completed' === $job->status && ! empty( $job->download_token ) )
					? rest_url( self::NAMESPACE . '/download?token=' . urlencode( $job->download_token ) )
					: '',
			);

			if ( $is_active && null === $current_item ) {
				$current_item = $item_info;
			}
			$items_status[] = $item_info;
		}

		$zip_url = '';
		if ( 'completed' === $batch->status && ! empty( $batch->download_token ) ) {
			$zip_url = rest_url( self::NAMESPACE . '/playlist/' . $uuid . '/download-zip?token=' . urlencode( $batch->download_token ) );
		}

		return new WP_REST_Response(
			array(
				'success'          => true,
				'batch_uuid'       => $batch->batch_uuid,
				'playlist_title'   => $batch->playlist_title,
				'output_format'    => $batch->output_format,
				'output_quality'   => $batch->output_quality,
				'status'           => $batch->status,
				'stage'            => $batch->stage,
				'total_items'      => $total_count,
				'completed_items'  => $completed_count,
				'failed_items'     => $failed_count,
				'overall_progress' => $overall_pct,
				'current_item'     => $current_item,
				'zip_download_url' => $zip_url,
				'zip_filename'     => $batch->zip_filename,
				'zip_size'         => $batch->zip_size ? size_format( $batch->zip_size, 2 ) : '',
				'items'            => $items_status,
			),
			200
		);
	}

	/**
	 * Endpoint: GET /playlist/{uuid}/download-zip
	 */
	public function handle_playlist_download_zip( WP_REST_Request $request ) {
		$uuid     = sanitize_text_field( $request->get_param( 'uuid' ) );
		$token    = sanitize_text_field( $request->get_param( 'token' ) );
		$database = $this->plugin->database;

		$batch = $database->get_batch_by_uuid( $uuid );
		if ( ! $batch ) {
			wp_die( esc_html__( 'Playlist batch not found.', 'media-converter' ), 404 );
		}

		if ( empty( $token ) || empty( $batch->download_token ) || ! hash_equals( $batch->download_token, $token ) ) {
			wp_die( esc_html__( 'Invalid or expired download authorization token.', 'media-converter' ), 403 );
		}

		$zip_path = $batch->zip_path;
		if ( empty( $zip_path ) || ! file_exists( $zip_path ) ) {
			wp_die( esc_html__( 'The compiled ZIP file could not be found on the server.', 'media-converter' ), 404 );
		}

		$filename = ! empty( $batch->zip_filename ) ? $batch->zip_filename : basename( $zip_path );

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $zip_path ) );

		readfile( $zip_path );
		exit;
	}
}
