<?php
/**
 * MediaConverter Pro - Public Frontend Controller
 *
 * Registers shortcodes, page templates, scripts, stylesheets, and localized REST configs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Public {

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

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->register_shortcodes();
	}

	/**
	 * Enqueue public CSS and JavaScript.
	 */
	public function enqueue_assets() {
		// Google Fonts: Inter & Plus Jakarta Sans.
		wp_enqueue_style(
			'media-converter-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap',
			array(),
			null
		);

		// Main plugin frontend stylesheet.
		wp_enqueue_style(
			'media-converter-css',
			MEDIA_CONVERTER_URL . 'public/assets/css/style.css',
			array(),
			MEDIA_CONVERTER_VERSION
		);

		// Frontend conversion engine JavaScript.
		wp_enqueue_script(
			'media-converter-js',
			MEDIA_CONVERTER_URL . 'public/assets/js/converter.js',
			array(),
			MEDIA_CONVERTER_VERSION,
			true
		);

		$options = get_option( 'media_converter_options', array() );
		$is_guest = ! is_user_logged_in();
		$max_mb   = $is_guest
			? ( isset( $options['guest_max_upload_mb'] ) ? intval( $options['guest_max_upload_mb'] ) : 250 )
			: ( isset( $options['user_max_upload_mb'] ) ? intval( $options['user_max_upload_mb'] ) : 1000 );

		wp_localize_script(
			'media-converter-js',
			'mcConfig',
			array(
				'restUrl'          => esc_url_raw( rest_url( 'media-converter/v1' ) ),
				'nonce'            => MediaConverter_Security::create_nonce(),
				'isLoggedIn'       => is_user_logged_in(),
				'maxUploadMb'      => $max_mb,
				'maxUploadBytes'   => $max_mb * 1024 * 1024,
				'allowedFormats'   => (array) ( $options['allowed_input_formats'] ?? array( 'mp4', 'mp3', 'wav', 'webm', 'mov', 'mkv', 'avi' ) ),
				'enableLegalNotice'=> ! empty( $options['enable_legal_notice_checkbox'] ),
				'enableDarkMode'   => ! empty( $options['enable_dark_mode'] ),
			)
		);
	}

	/**
	 * Register shortcodes for all website sections and pages.
	 */
	private function register_shortcodes() {
		add_shortcode( 'media_converter', array( $this, 'render_converter_shortcode' ) );
		add_shortcode( 'media_converter_home', array( $this, 'render_homepage_shortcode' ) );
		add_shortcode( 'media_converter_video', array( $this, 'render_video_converter_shortcode' ) );
		add_shortcode( 'media_converter_audio', array( $this, 'render_audio_converter_shortcode' ) );
		add_shortcode( 'media_converter_how_it_works', array( $this, 'render_how_it_works_shortcode' ) );
		add_shortcode( 'media_converter_faq', array( $this, 'render_faq_shortcode' ) );
		add_shortcode( 'media_converter_about', array( $this, 'render_about_shortcode' ) );
		add_shortcode( 'media_converter_contact', array( $this, 'render_contact_shortcode' ) );
		add_shortcode( 'media_converter_privacy', array( $this, 'render_privacy_shortcode' ) );
		add_shortcode( 'media_converter_terms', array( $this, 'render_terms_shortcode' ) );
		add_shortcode( 'media_converter_copyright', array( $this, 'render_copyright_shortcode' ) );
		add_shortcode( 'media_converter_dashboard', array( $this, 'render_dashboard_shortcode' ) );
	}

	public function render_converter_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'default_format' => 'all' ), $atts );
		ob_start();
		$default_format = $atts['default_format'];
		require MEDIA_CONVERTER_PATH . 'public/templates/converter.php';
		return ob_get_clean();
	}

	public function render_homepage_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/homepage.php';
		return ob_get_clean();
	}

	public function render_video_converter_shortcode() {
		$default_format = 'mp4';
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/video-converter.php';
		return ob_get_clean();
	}

	public function render_audio_converter_shortcode() {
		$default_format = 'mp3';
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/audio-converter.php';
		return ob_get_clean();
	}

	public function render_how_it_works_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/how-it-works.php';
		return ob_get_clean();
	}

	public function render_faq_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/faq.php';
		return ob_get_clean();
	}

	public function render_about_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/about.php';
		return ob_get_clean();
	}

	public function render_contact_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/contact.php';
		return ob_get_clean();
	}

	public function render_privacy_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/privacy.php';
		return ob_get_clean();
	}

	public function render_terms_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/terms.php';
		return ob_get_clean();
	}

	public function render_copyright_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/copyright.php';
		return ob_get_clean();
	}

	public function render_dashboard_shortcode() {
		ob_start();
		require MEDIA_CONVERTER_PATH . 'public/templates/dashboard.php';
		return ob_get_clean();
	}
}
