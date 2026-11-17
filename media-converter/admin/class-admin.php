<?php
/**
 * MediaConverter Pro - Admin Controller
 *
 * Registers admin menus, settings sections, AJAX actions, and admin assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Admin {

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

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Admin AJAX actions.
		add_action( 'wp_ajax_media_converter_admin_job_action', array( $this, 'ajax_job_action' ) );
		add_action( 'wp_ajax_media_converter_test_binaries', array( $this, 'ajax_test_binaries' ) );
	}

	/**
	 * Register WordPress admin menus.
	 */
	public function register_admin_menu() {
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>'
		);

		add_menu_page(
			__( 'Media Converter', 'media-converter' ),
			__( 'Media Converter', 'media-converter' ),
			'manage_options',
			'media-converter',
			array( $this, 'render_dashboard' ),
			$icon_svg,
			65
		);

		add_submenu_page(
			'media-converter',
			__( 'Dashboard', 'media-converter' ),
			__( 'Dashboard', 'media-converter' ),
			'manage_options',
			'media-converter',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'media-converter',
			__( 'Conversion Jobs', 'media-converter' ),
			__( 'Jobs', 'media-converter' ),
			'manage_options',
			'media-converter-jobs',
			array( $this, 'render_jobs' )
		);

		add_submenu_page(
			'media-converter',
			__( 'Converter Settings', 'media-converter' ),
			__( 'Settings', 'media-converter' ),
			'manage_options',
			'media-converter-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'media-converter',
			__( 'System Status', 'media-converter' ),
			__( 'System Status', 'media-converter' ),
			'manage_options',
			'media-converter-status',
			array( $this, 'render_system_status' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'media_converter_settings_group', 'media_converter_options', array( $this, 'sanitize_settings' ) );
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['ffmpeg_path']                 = sanitize_text_field( $input['ffmpeg_path'] ?? '' );
		$sanitized['ffprobe_path']                = sanitize_text_field( $input['ffprobe_path'] ?? '' );
		$sanitized['max_upload_size_mb']          = max( 10, absint( $input['max_upload_size_mb'] ?? 500 ) );
		$sanitized['max_conversion_duration_sec'] = max( 30, absint( $input['max_conversion_duration_sec'] ?? 600 ) );
		$sanitized['max_concurrent_jobs']         = max( 1, min( 10, absint( $input['max_concurrent_jobs'] ?? 3 ) ) );
		$sanitized['default_video_quality']       = sanitize_text_field( $input['default_video_quality'] ?? 'original' );
		$sanitized['default_audio_bitrate']       = sanitize_text_field( $input['default_audio_bitrate'] ?? '320k' );
		$sanitized['retention_hours']             = max( 1, absint( $input['retention_hours'] ?? 24 ) );
		$sanitized['guest_retention_hours']       = max( 1, absint( $input['guest_retention_hours'] ?? 12 ) );
		$sanitized['user_retention_hours']        = max( 1, absint( $input['user_retention_hours'] ?? 72 ) );
		$sanitized['guest_hourly_limit']          = max( 1, absint( $input['guest_hourly_limit'] ?? 10 ) );
		$sanitized['user_hourly_limit']           = max( 1, absint( $input['user_hourly_limit'] ?? 50 ) );
		$sanitized['enable_rate_limiting']        = ! empty( $input['enable_rate_limiting'] ) ? 1 : 0;
		$sanitized['enable_legal_notice_checkbox']= ! empty( $input['enable_legal_notice_checkbox'] ) ? 1 : 0;
		$sanitized['enable_dark_mode']            = ! empty( $input['enable_dark_mode'] ) ? 1 : 0;

		if ( ! empty( $input['allowed_input_formats'] ) && is_array( $input['allowed_input_formats'] ) ) {
			$sanitized['allowed_input_formats'] = array_map( 'sanitize_key', $input['allowed_input_formats'] );
		} else {
			$sanitized['allowed_input_formats'] = array( 'mp4', 'mp3', 'wav', 'webm', 'mov', 'mkv', 'avi' );
		}

		return $sanitized;
	}

	/**
	 * Enqueue admin CSS and JS.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( ! str_contains( $hook, 'media-converter' ) ) {
			return;
		}

		wp_enqueue_style(
			'media-converter-admin-css',
			MEDIA_CONVERTER_URL . 'admin/assets/css/admin.css',
			array(),
			MEDIA_CONVERTER_VERSION
		);

		wp_enqueue_script(
			'media-converter-admin-js',
			MEDIA_CONVERTER_URL . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			MEDIA_CONVERTER_VERSION,
			true
		);

		wp_localize_script(
			'media-converter-admin-js',
			'mediaConverterAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'media_converter_admin_nonce' ),
			)
		);
	}

	/**
	 * Render Dashboard submenu.
	 */
	public function render_dashboard() {
		$database = $this->plugin->database;
		$ffmpeg   = $this->plugin->ffmpeg;
		require MEDIA_CONVERTER_PATH . 'admin/dashboard.php';
	}

	/**
	 * Render Jobs submenu.
	 */
	public function render_jobs() {
		$database = $this->plugin->database;
		require MEDIA_CONVERTER_PATH . 'admin/jobs.php';
	}

	/**
	 * Render Settings submenu.
	 */
	public function render_settings() {
		require MEDIA_CONVERTER_PATH . 'admin/settings.php';
	}

	/**
	 * Render System Status submenu.
	 */
	public function render_system_status() {
		$ffmpeg = $this->plugin->ffmpeg;
		require MEDIA_CONVERTER_PATH . 'admin/system-status.php';
	}

	/**
	 * Handle admin AJAX job actions (delete, retry, cancel).
	 */
	public function ajax_job_action() {
		check_ajax_referer( 'media_converter_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'media-converter' ) ) );
		}

		$job_id = absint( $_POST['job_id'] ?? 0 );
		$action = sanitize_key( $_POST['job_action'] ?? '' );

		if ( ! $job_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'media-converter' ) ) );
		}

		switch ( $action ) {
			case 'delete':
				$ok = $this->plugin->database->delete_job( $job_id );
				if ( $ok ) {
					wp_send_json_success( array( 'message' => __( 'Job deleted.', 'media-converter' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Could not delete job.', 'media-converter' ) ) );
				}
				break;

			case 'retry':
				$ok = $this->plugin->job_manager->retry_job( $job_id );
				if ( $ok ) {
					wp_send_json_success( array( 'message' => __( 'Job retried successfully.', 'media-converter' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Retry failed.', 'media-converter' ) ) );
				}
				break;

			case 'cancel':
				$ok = $this->plugin->job_manager->cancel_job( $job_id );
				if ( $ok ) {
					wp_send_json_success( array( 'message' => __( 'Job cancelled.', 'media-converter' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Cancel failed.', 'media-converter' ) ) );
				}
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action requested.', 'media-converter' ) ) );
		}
	}

	/**
	 * Test FFmpeg & FFprobe paths via AJAX.
	 */
	public function ajax_test_binaries() {
		check_ajax_referer( 'media_converter_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$ffmpeg_res  = $this->plugin->ffmpeg->check_ffmpeg();
		$ffprobe_res = $this->plugin->ffmpeg->check_ffprobe();

		wp_send_json_success(
			array(
				'ffmpeg'  => $ffmpeg_res,
				'ffprobe' => $ffprobe_res,
			)
		);
	}
}
