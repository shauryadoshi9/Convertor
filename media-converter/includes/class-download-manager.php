<?php
/**
 * MediaConverter Pro - Secure Download Manager
 *
 * Implements chunked streaming, HTTP Range requests (RFC 7233), token verification,
 * and path containment checks for media downloads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Download_Manager {

	/**
	 * Database instance.
	 *
	 * @var MediaConverter_Database
	 */
	private $database;

	/**
	 * Security instance.
	 *
	 * @var MediaConverter_Security
	 */
	private $security;

	/**
	 * Constructor.
	 *
	 * @param MediaConverter_Database $database
	 * @param MediaConverter_Security $security
	 */
	public function __construct( MediaConverter_Database $database, MediaConverter_Security $security ) {
		$this->database = $database;
		$this->security = $security;
	}

	/**
	 * Stream media file to client securely.
	 *
	 * @param string $token
	 */
	public function handle_download( $token ) {
		if ( empty( $token ) ) {
			wp_die( esc_html__( 'Invalid or missing download token.', 'media-converter' ), 'Error', array( 'response' => 400 ) );
		}

		$job = $this->database->get_job_by_token( $token );
		if ( ! $job ) {
			wp_die( esc_html__( 'Conversion job not found.', 'media-converter' ), 'Error', array( 'response' => 404 ) );
		}

		if ( 'completed' !== $job->status ) {
			wp_die( esc_html__( 'File conversion is not yet complete.', 'media-converter' ), 'Error', array( 'response' => 400 ) );
		}

		// Check expiry.
		if ( ! empty( $job->expires_at ) && strtotime( $job->expires_at ) < time() ) {
			wp_die( esc_html__( 'This download link has expired. Please convert the file again.', 'media-converter' ), 'Expired', array( 'response' => 410 ) );
		}

		$filepath = $job->output_path;
		if ( empty( $filepath ) || ! file_exists( $filepath ) ) {
			wp_die( esc_html__( 'File not found on server or already cleaned up.', 'media-converter' ), 'File Not Found', array( 'response' => 404 ) );
		}

		// Prevent path traversal.
		$safe_path = $this->security->sanitize_safe_path( $filepath, MEDIA_CONVERTER_STORAGE_DIR );
		if ( ! $safe_path ) {
			wp_die( esc_html__( 'Access denied: invalid file path.', 'media-converter' ), 'Forbidden', array( 'response' => 403 ) );
		}

		// Increment download count.
		$this->database->increment_download_count( $job->id );

		$filesize = filesize( $safe_path );
		$filename = ! empty( $job->output_filename ) ? $job->output_filename : basename( $safe_path );

		$mime_type = ( 'mp3' === strtolower( $job->output_format ) ) ? 'audio/mpeg' : 'video/mp4';

		// Clean output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Handle HTTP Range header for resumable downloads & in-browser seeking.
		$start  = 0;
		$end    = $filesize - 1;
		$length = $filesize;

		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$range_header = sanitize_text_field( $_SERVER['HTTP_RANGE'] );
			if ( preg_match( '/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range_header, $matches ) ) {
				$start = intval( $matches[1] );
				if ( ! empty( $matches[2] ) ) {
					$end = intval( $matches[2] );
				}
			}

			if ( $start > $end || $start >= $filesize ) {
				header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
				header( "Content-Range: bytes */{$filesize}" );
				exit;
			}

			$length = $end - $start + 1;
			header( 'HTTP/1.1 206 Partial Content' );
			header( "Content-Range: bytes {$start}-{$end}/{$filesize}" );
		} else {
			header( 'HTTP/1.1 200 OK' );
		}

		// Headers.
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . $length );
		header( 'Accept-Ranges: bytes' );
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'X-Content-Type-Options: nosniff' );

		// If HEAD request, exit after sending headers.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			exit;
		}

		// Stream file in 64KB chunks.
		$fp = @fopen( $safe_path, 'rb' );
		if ( ! $fp ) {
			exit;
		}

		if ( $start > 0 ) {
			fseek( $fp, $start );
		}

		$bytes_to_send = $length;
		$buffer_size   = 65536; // 64KB

		while ( ! feof( $fp ) && $bytes_to_send > 0 && ! connection_aborted() ) {
			$chunk_size = min( $buffer_size, $bytes_to_send );
			$buffer     = fread( $fp, $chunk_size );
			echo $buffer;
			flush();
			$bytes_to_send -= strlen( $buffer );
		}

		fclose( $fp );
		exit;
	}
}
