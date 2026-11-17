<?php
/**
 * MediaConverter Pro - Installer & Activation Handler
 *
 * Handles database table creation, initial settings, directories, and cron schedules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Installer {

	const DB_VERSION = '1.0.0';

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::create_storage_directories();
		self::schedule_crons();

		update_option( 'media_converter_db_version', self::DB_VERSION );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		self::clear_crons();
	}

	/**
	 * Create or update custom database tables using dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'media_conversion_jobs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_uuid varchar(64) NOT NULL,
			user_id bigint(20) unsigned DEFAULT 0,
			source_type varchar(20) NOT NULL DEFAULT 'upload',
			source_url text DEFAULT NULL,
			original_filename varchar(255) NOT NULL,
			input_path text DEFAULT NULL,
			output_filename varchar(255) DEFAULT NULL,
			output_path text DEFAULT NULL,
			output_format varchar(10) NOT NULL DEFAULT 'mp4',
			output_quality varchar(50) NOT NULL DEFAULT 'original',
			trim_start varchar(20) DEFAULT NULL,
			trim_end varchar(20) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			stage varchar(50) NOT NULL DEFAULT 'Queued',
			progress float NOT NULL DEFAULT 0,
			file_size bigint(20) unsigned DEFAULT 0,
			duration_seconds float DEFAULT 0,
			download_token varchar(64) DEFAULT NULL,
			download_count int(11) DEFAULT 0,
			error_message text DEFAULT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_uuid (job_uuid),
			KEY status (status),
			KEY user_id (user_id),
			KEY download_token (download_token),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		} else {
			$wpdb->query( $sql );
		}
	}

	/**
	 * Detect executable path for FFmpeg/FFprobe.
	 *
	 * @param string $binary 'ffmpeg' or 'ffprobe'
	 * @return string
	 */
	public static function auto_detect_binary( $binary ) {
		// Common binary locations.
		$is_windows = ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' );
		$bin_name   = $is_windows ? $binary . '.exe' : $binary;

		// Check system PATH first using where / which.
		$cmd = $is_windows ? "where {$bin_name} 2>nul" : "which {$bin_name} 2>/dev/null";
		$output = @shell_exec( $cmd );
		if ( ! empty( $output ) ) {
			$lines = explode( "\n", trim( $output ) );
			$path  = trim( $lines[0] );
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		// Windows common locations.
		if ( $is_windows ) {
			$search_paths = array(
				"C:\\ProgramData\\chocolatey\\bin\\{$bin_name}",
				"C:\\ffmpeg\\bin\\{$bin_name}",
				"C:\\Program Files\\ffmpeg\\bin\\{$bin_name}",
			);

			// Check winget package path if on Windows.
			$user_appdata = getenv( 'LOCALAPPDATA' );
			if ( $user_appdata ) {
				$winget_glob = glob( $user_appdata . "\\Microsoft\\WinGet\\Packages\\*ffmpeg*\\**\\{$bin_name}" );
				if ( ! empty( $winget_glob ) ) {
					return $winget_glob[0];
				}
			}

			foreach ( $search_paths as $candidate ) {
				if ( file_exists( $candidate ) ) {
					return $candidate;
				}
			}
		} else {
			// Linux/macOS common locations.
			$search_paths = array(
				"/usr/bin/{$bin_name}",
				"/usr/local/bin/{$bin_name}",
				"/opt/homebrew/bin/{$bin_name}",
			);
			foreach ( $search_paths as $candidate ) {
				if ( file_exists( $candidate ) ) {
					return $candidate;
				}
			}
		}

		return $bin_name; // Fallback to raw binary name.
	}

	/**
	 * Setup default settings in wp_options.
	 */
	public static function set_default_options() {
		$ffmpeg_path  = self::auto_detect_binary( 'ffmpeg' );
		$ffprobe_path = self::auto_detect_binary( 'ffprobe' );
		$ytdlp_path   = self::auto_detect_binary( 'yt-dlp' );

		// Check local bundled bin/ directory
		if ( 'yt-dlp' === $ytdlp_path || 'yt-dlp.exe' === $ytdlp_path ) {
			$bundled = MEDIA_CONVERTER_PATH . 'bin/yt-dlp.exe';
			if ( file_exists( $bundled ) ) {
				$ytdlp_path = $bundled;
			}
		}

		$defaults = array(
			'ffmpeg_path'                      => $ffmpeg_path,
			'ffprobe_path'                     => $ffprobe_path,
			'ytdlp_path'                       => $ytdlp_path,
			'max_upload_size_mb'               => 500,
			'max_conversion_duration_sec'      => 3600,
			'max_concurrent_jobs'              => 3,
			'allowed_input_formats'            => array(
				'mp4', 'mkv', 'avi', 'mov', 'webm', 'flv', 'wmv', 'm4v', '3gp',
				'mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a', 'wma'
			),
			'allowed_output_formats'           => array( 'mp4', 'mp3' ),
			'default_video_quality'            => 'original',
			'default_audio_bitrate'            => '320k',
			'retention_hours'                  => 24,
			'guest_retention_hours'            => 12,
			'user_retention_hours'             => 72,
			'guest_hourly_limit'               => 10,
			'user_hourly_limit'                => 50,
			'guest_max_upload_mb'              => 250,
			'user_max_upload_mb'               => 1000,
			'enable_rate_limiting'             => 1,
			'enable_legal_notice_checkbox'     => 1,
			'enable_dark_mode'                 => 1,
		);

		$existing = get_option( 'media_converter_options', array() );
		$merged   = wp_parse_args( $existing, $defaults );
		update_option( 'media_converter_options', $merged );
	}

	/**
	 * Create storage directories.
	 */
	public static function create_storage_directories() {
		$dirs = array(
			MEDIA_CONVERTER_STORAGE_DIR,
			MEDIA_CONVERTER_TEMP_DIR,
			MEDIA_CONVERTER_OUTPUT_DIR,
		);

		foreach ( $dirs as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$index = $dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php // Silence is golden." );
			}
		}
	}

	/**
	 * Schedule hourly cleanup and queue runner cron jobs.
	 */
	public static function schedule_crons() {
		if ( ! wp_next_scheduled( 'media_converter_cleanup_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'media_converter_cleanup_cron' );
		}

		if ( ! wp_next_scheduled( 'media_converter_process_queue_cron' ) ) {
			wp_schedule_event( time(), 'every_minute', 'media_converter_process_queue_cron' );
		}
	}

	/**
	 * Clear scheduled cron jobs.
	 */
	public static function clear_crons() {
		$cleanup = wp_next_scheduled( 'media_converter_cleanup_cron' );
		if ( $cleanup ) {
			wp_unschedule_event( $cleanup, 'media_converter_cleanup_cron' );
		}

		$queue = wp_next_scheduled( 'media_converter_process_queue_cron' );
		if ( $queue ) {
			wp_unschedule_event( $queue, 'media_converter_process_queue_cron' );
		}
	}
}
