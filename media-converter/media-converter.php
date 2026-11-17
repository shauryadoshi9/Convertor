<?php
/**
 * Plugin Name:       MediaConverter Pro - High-Quality Video & Audio Converter
 * Plugin URI:        https://prismconvert.io
 * Description:       Enterprise-grade, high-quality video and audio converter for WordPress. Features real-time FFmpeg processing, multi-stage progress tracking, secure tokenized downloads, SSRF-protected remote validation, and comprehensive REST API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Prism Media Studio
 * Author URI:        https://prismconvert.io
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       media-converter
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants.
define( 'MEDIA_CONVERTER_VERSION', '1.0.0' );
define( 'MEDIA_CONVERTER_FILE', __FILE__ );
define( 'MEDIA_CONVERTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIA_CONVERTER_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDIA_CONVERTER_BASENAME', plugin_basename( __FILE__ ) );

// Uploads and Temp Directories.
$upload_dir = wp_upload_dir();
define( 'MEDIA_CONVERTER_STORAGE_DIR', trailingslashit( $upload_dir['basedir'] ) . 'media-converter/' );
define( 'MEDIA_CONVERTER_STORAGE_URL', trailingslashit( $upload_dir['baseurl'] ) . 'media-converter/' );
define( 'MEDIA_CONVERTER_TEMP_DIR', MEDIA_CONVERTER_STORAGE_DIR . 'temp/' );
define( 'MEDIA_CONVERTER_OUTPUT_DIR', MEDIA_CONVERTER_STORAGE_DIR . 'output/' );

/**
 * Autoload includes classes.
 */
require_once MEDIA_CONVERTER_PATH . 'includes/class-installer.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-database.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-security.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-validator.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-ffmpeg.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-converter.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-job-manager.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-download-manager.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-cleanup.php';
require_once MEDIA_CONVERTER_PATH . 'includes/class-rest-api.php';

if ( is_admin() ) {
	require_once MEDIA_CONVERTER_PATH . 'admin/class-admin.php';
}

require_once MEDIA_CONVERTER_PATH . 'public/class-public.php';

/**
 * Main Plugin Class (Singleton).
 */
class MediaConverter_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MediaConverter_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var MediaConverter_Database
	 */
	public $database;

	/**
	 * Security instance.
	 *
	 * @var MediaConverter_Security
	 */
	public $security;

	/**
	 * Validator instance.
	 *
	 * @var MediaConverter_Validator
	 */
	public $validator;

	/**
	 * FFmpeg instance.
	 *
	 * @var MediaConverter_FFmpeg
	 */
	public $ffmpeg;

	/**
	 * Converter instance.
	 *
	 * @var MediaConverter_Converter
	 */
	public $converter;

	/**
	 * Job Manager instance.
	 *
	 * @var MediaConverter_Job_Manager
	 */
	public $job_manager;

	/**
	 * Download Manager instance.
	 *
	 * @var MediaConverter_Download_Manager
	 */
	public $download_manager;

	/**
	 * Cleanup instance.
	 *
	 * @var MediaConverter_Cleanup
	 */
	public $cleanup;

	/**
	 * REST API instance.
	 *
	 * @var MediaConverter_REST_API
	 */
	public $rest_api;

	/**
	 * Admin instance.
	 *
	 * @var MediaConverter_Admin|null
	 */
	public $admin = null;

	/**
	 * Public instance.
	 *
	 * @var MediaConverter_Public
	 */
	public $public;

	/**
	 * Get singleton instance.
	 *
	 * @return MediaConverter_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Initialize all plugin components.
	 */
	private function init_components() {
		$this->database         = new MediaConverter_Database();
		$this->security         = new MediaConverter_Security();
		$this->validator        = new MediaConverter_Validator();
		$this->ffmpeg           = new MediaConverter_FFmpeg();
		$this->converter        = new MediaConverter_Converter( $this->ffmpeg, $this->database );
		$this->job_manager      = new MediaConverter_Job_Manager( $this->database, $this->converter );
		$this->download_manager = new MediaConverter_Download_Manager( $this->database, $this->security );
		$this->cleanup          = new MediaConverter_Cleanup( $this->database );
		$this->rest_api         = new MediaConverter_REST_API( $this );

		if ( is_admin() ) {
			$this->admin = new MediaConverter_Admin( $this );
		}

		$this->public = new MediaConverter_Public( $this );
	}

	/**
	 * Register general WordPress hooks.
	 */
	private function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_directories' ) );
	}

	/**
	 * Load translation domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'media-converter',
			false,
			dirname( MEDIA_CONVERTER_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Ensure secure directories exist with .htaccess and index.php protection.
	 */
	public function init_directories() {
		$dirs = array(
			MEDIA_CONVERTER_STORAGE_DIR,
			MEDIA_CONVERTER_TEMP_DIR,
			MEDIA_CONVERTER_OUTPUT_DIR,
		);

		foreach ( $dirs as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			// Add index.php to prevent directory indexing.
			$index_file = $dir . 'index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
			}

			// Add .htaccess to block direct web access to raw uploaded/temp media files.
			$htaccess_file = $dir . '.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents(
					$htaccess_file,
					"# Prevent direct file execution and downloads\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
				);
			}
		}
	}
}

// Register Activation & Deactivation hooks.
register_activation_hook( MEDIA_CONVERTER_FILE, array( 'MediaConverter_Installer', 'activate' ) );
register_deactivation_hook( MEDIA_CONVERTER_FILE, array( 'MediaConverter_Installer', 'deactivate' ) );

/**
 * Global helper function to get plugin instance.
 *
 * @return MediaConverter_Plugin
 */
function media_converter() {
	return MediaConverter_Plugin::get_instance();
}

// Bootstrap plugin.
media_converter();
