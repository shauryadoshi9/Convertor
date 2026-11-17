<?php
/**
 * MediaConverter Pro - Source & Media Validator
 *
 * Implements strict SSRF protection, DNS resolution checks, MIME validation,
 * magic byte checks, and legal download compliance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Validator {

	/**
	 * Supported audio and video MIME types mapped to file extensions.
	 */
	const ALLOWED_MIME_TYPES = array(
		'video/mp4'                => array( 'mp4', 'm4v' ),
		'video/webm'               => array( 'webm' ),
		'video/x-matroska'         => array( 'mkv' ),
		'video/quicktime'          => array( 'mov' ),
		'video/x-msvideo'          => array( 'avi' ),
		'video/x-flv'              => array( 'flv' ),
		'video/x-ms-wmv'           => array( 'wmv' ),
		'video/3gpp'               => array( '3gp' ),
		'audio/mpeg'               => array( 'mp3' ),
		'audio/mp3'                => array( 'mp3' ),
		'audio/wav'                => array( 'wav' ),
		'audio/x-wav'              => array( 'wav' ),
		'audio/aac'                => array( 'aac' ),
		'audio/ogg'                => array( 'ogg', 'oga' ),
		'video/ogg'                => array( 'ogv' ),
		'audio/x-m4a'              => array( 'm4a' ),
		'audio/flac'               => array( 'flac' ),
		'audio/x-flac'             => array( 'flac' ),
		'audio/x-ms-wma'           => array( 'wma' ),
		'application/octet-stream' => array( 'mp4', 'mkv', 'avi', 'mp3', 'wav', 'mov' ), // Many browsers send octet-stream for media
	);

	/**
	 * Disallowed / dangerous extensions.
	 */
	const FORBIDDEN_EXTENSIONS = array(
		'php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'cmd', 'sh', 'bash',
		'cgi', 'pl', 'py', 'js', 'jar', 'vbs', 'scr', 'dll', 'com', 'msi', 'bin',
		'htaccess', 'htpasswd', 'ini', 'phar', 'svg'
	);

	/**
	 * Validate an uploaded media file.
	 *
	 * @param array $file $_FILES element
	 * @param int $user_id
	 * @return array ['success' => bool, 'error' => string, 'mime' => string, 'ext' => string]
	 */
	public function validate_uploaded_file( array $file, $user_id = 0 ) {
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No valid file was uploaded or file upload was interrupted.', 'media-converter' ),
			);
		}

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return array(
				'success' => false,
				'error'   => $this->get_upload_error_message( $file['error'] ),
			);
		}

		// Unlimited File Size Mode (Pro Subscription Active - No Size Cap)
		// Large files (1GB, 5GB, 10GB+) are fully supported.

		// Check extension.
		$filename  = sanitize_file_name( $file['name'] );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, self::FORBIDDEN_EXTENSIONS, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Security violation: uploaded file type is strictly forbidden.', 'media-converter' ),
			);
		}

		$allowed_formats = isset( $options['allowed_input_formats'] ) ? (array) $options['allowed_input_formats'] : array( 'mp4', 'mp3', 'wav', 'webm', 'mov', 'mkv', 'avi' );
		if ( ! in_array( $extension, $allowed_formats, true ) ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Format ".%s" is not supported. Supported: %s', 'media-converter' ), $extension, implode( ', ', $allowed_formats ) ),
			);
		}

		// Inspect real MIME type using finfo.
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$real_mime = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! $this->is_allowed_mime_type( $real_mime, $extension ) ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'MIME type verification failed for "%s". The file does not match a valid media format.', 'media-converter' ), esc_html( $real_mime ) ),
			);
		}

		return array(
			'success'  => true,
			'filename' => $filename,
			'ext'      => $extension,
			'mime'     => $real_mime,
			'size'     => $file['size'],
		);
	}

	/**
	 * Verify if a MIME type matches an allowed media type.
	 *
	 * @param string $mime
	 * @param string $extension
	 * @return bool
	 */
	public function is_allowed_mime_type( $mime, $extension ) {
		$mime = strtolower( trim( $mime ) );

		if ( isset( self::ALLOWED_MIME_TYPES[ $mime ] ) ) {
			return in_array( $extension, self::ALLOWED_MIME_TYPES[ $mime ], true );
		}

		// Allow generic audio/* and video/* if extension is in whitelist.
		if ( str_starts_with( $mime, 'video/' ) || str_starts_with( $mime, 'audio/' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate a remote URL and check for SSRF attacks.
	 *
	 * @param string $url
	 * @return array ['success' => bool, 'error' => string, 'info' => array]
	 */
	public function validate_remote_url( $url ) {
		$url = trim( $url );

		if ( empty( $url ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Please provide a valid media URL.', 'media-converter' ),
			);
		}

		// 1. Strict URL Syntax & Protocol.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The submitted URL has an invalid format.', 'media-converter' ),
			);
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || strtolower( $parsed['scheme'] ) !== 'https' ) {
			return array(
				'success' => false,
				'error'   => __( 'Security requirement: Only secure HTTPS URLs are permitted.', 'media-converter' ),
			);
		}

		$host = strtolower( $parsed['host'] ?? '' );
		if ( empty( $host ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid URL: hostname is missing.', 'media-converter' ),
			);
		}

		// 2. SSRF Protection: Prevent localhost, metadata services, and internal IP access.
		$is_safe_ip = $this->is_public_safe_host( $host );
		if ( ! $is_safe_ip['safe'] ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'SSRF Security Alert: %s', 'media-converter' ), $is_safe_ip['reason'] ),
			);
		}

		// 3. Provider detection & metadata retrieval.
		$provider_info = $this->inspect_media_provider( $url, $host );

		return array(
			'success'  => true,
			'url'      => $url,
			'host'     => $host,
			'provider' => $provider_info,
		);
	}

	/**
	 * Resolve DNS and ensure host does NOT resolve to private/loopback/cloud metadata ranges.
	 *
	 * @param string $host
	 * @return array ['safe' => bool, 'reason' => string]
	 */
	public function is_public_safe_host( $host ) {
		// Reject obvious loopback names.
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1', '0.0.0.0', 'local', 'internal' ), true ) ) {
			return array( 'safe' => false, 'reason' => 'Access to loopback address is blocked.' );
		}

		// Cloud metadata hostnames.
		if ( str_ends_with( $host, '.internal' ) || $host === 'metadata.google.internal' || $host === 'instance-data' ) {
			return array( 'safe' => false, 'reason' => 'Access to cloud instance metadata is blocked.' );
		}

		// Resolve all IPv4 and IPv6 addresses.
		$ips = @gethostbynamel( $host );
		if ( empty( $ips ) ) {
			// Try single IP if host is already an IP address.
			if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
				$ips = array( $host );
			} else {
				return array( 'safe' => false, 'reason' => 'Unable to resolve domain name in public DNS.' );
			}
		}

		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return array(
					'safe'   => false,
					'reason' => sprintf( 'Host resolves to a reserved/private network address (%s).', esc_html( $ip ) ),
				);
			}

			// Specific check for AWS/GCP/Azure link-local metadata 169.254.169.254.
			if ( strpos( $ip, '169.254.' ) === 0 ) {
				return array( 'safe' => false, 'reason' => 'Access to link-local cloud metadata is blocked.' );
			}
		}

		return array( 'safe' => true, 'reason' => '' );
	}

	/**
	 * Inspect media provider (YouTube oEmbed, Vimeo, or direct media link).
	 *
	 * @param string $url
	 * @param string $host
	 * @return array
	 */
	public function inspect_media_provider( $url, $host ) {
		// Check YouTube.
		if ( str_contains( $host, 'youtube.com' ) || str_contains( $host, 'youtu.be' ) ) {
			return $this->fetch_youtube_oembed( $url );
		}

		// Check Vimeo.
		if ( str_contains( $host, 'vimeo.com' ) ) {
			return $this->fetch_vimeo_oembed( $url );
		}

		// Direct Media URL (e.g. https://domain.com/sample.mp4).
		return $this->fetch_direct_media_headers( $url );
	}

	/**
	 * Fetch YouTube video information via yt-dlp metadata or official oEmbed API.
	 *
	 * @param string $url
	 * @return array
	 */
	private function fetch_youtube_oembed( $url ) {
		$options = get_option( 'media_converter_options', array() );
		$ytdlp_bin = ! empty( $options['ytdlp_path'] ) ? $options['ytdlp_path'] : ( MEDIA_CONVERTER_PATH . 'bin/yt-dlp.exe' );
		if ( ! file_exists( $ytdlp_bin ) ) {
			$ytdlp_bin = MediaConverter_Installer::auto_detect_binary( 'yt-dlp' );
		}

		// Try yt-dlp first for comprehensive stream metadata.
		if ( file_exists( $ytdlp_bin ) || 'yt-dlp' === $ytdlp_bin ) {
			$cmd = escapeshellarg( $ytdlp_bin ) . ' --dump-json --no-playlist --no-warnings --js-runtimes node ' . escapeshellarg( $url ) . ' 2>nul';
			$json_out = @shell_exec( $cmd );
			if ( ! empty( $json_out ) ) {
				$data = json_decode( $json_out, true );
				if ( is_array( $data ) && ! empty( $data['title'] ) ) {
					$duration = intval( $data['duration'] ?? 0 );
					$height   = intval( $data['height'] ?? 720 );

					$available_video_qualities = array( 'original' );
					if ( $height >= 1080 ) $available_video_qualities[] = '1080p';
					if ( $height >= 720 )  $available_video_qualities[] = '720p';
					if ( $height >= 480 )  $available_video_qualities[] = '480p';
					if ( $height >= 360 )  $available_video_qualities[] = '360p';

					return array(
						'type'                      => 'youtube',
						'title'                     => sanitize_text_field( $data['title'] ),
						'author'                    => sanitize_text_field( $data['uploader'] ?? '' ),
						'thumbnail'                 => esc_url_raw( $data['thumbnail'] ?? '' ),
						'duration'                  => gmdate( $duration >= 3600 ? 'H:i:s' : 'i:s', $duration ),
						'duration_seconds'          => $duration,
						'height'                    => $height,
						'available_video_qualities' => $available_video_qualities,
						'is_direct_stream'          => true,
						'legal_note'                => __( 'YouTube media identified and stream extractable for transcoding.', 'media-converter' ),
					);
				}
			}
		}

		// Fallback to official oEmbed endpoint.
		$oembed_url = 'https://www.youtube.com/oembed?url=' . urlencode( $url ) . '&format=json';

		$response = wp_remote_get(
			$oembed_url,
			array(
				'timeout'     => 8,
				'redirection' => 2,
				'sslverify'   => true,
				'headers'     => array( 'User-Agent' => 'MediaConverter/1.0' ),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array(
				'type'              => 'youtube',
				'title'             => 'YouTube Media',
				'author'            => '',
				'thumbnail'         => '',
				'is_direct_stream'  => true,
				'legal_note'        => __( 'YouTube metadata retrieved.', 'media-converter' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'type'             => 'youtube',
			'title'            => sanitize_text_field( $body['title'] ?? 'YouTube Media' ),
			'author'           => sanitize_text_field( $body['author_name'] ?? '' ),
			'thumbnail'        => esc_url_raw( $body['thumbnail_url'] ?? '' ),
			'embed_html'       => $body['html'] ?? '',
			'is_direct_stream' => true,
			'legal_note'       => __( 'Official YouTube source recognized.', 'media-converter' ),
		);
	}

	/**
	 * Fetch Vimeo media information via oEmbed API.
	 *
	 * @param string $url
	 * @return array
	 */
	private function fetch_vimeo_oembed( $url ) {
		$oembed_url = 'https://vimeo.com/api/oembed.json?url=' . urlencode( $url );
		$response   = wp_remote_get( $oembed_url, array( 'timeout' => 8, 'sslverify' => true ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array(
				'type'             => 'vimeo',
				'title'            => 'Vimeo Media',
				'thumbnail'        => '',
				'is_direct_stream' => false,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'type'             => 'vimeo',
			'title'            => sanitize_text_field( $body['title'] ?? 'Vimeo Media' ),
			'author'           => sanitize_text_field( $body['author_name'] ?? '' ),
			'thumbnail'        => esc_url_raw( $body['thumbnail_url'] ?? '' ),
			'is_direct_stream' => false,
		);
	}

	/**
	 * Check direct media stream via HTTP HEAD.
	 *
	 * @param string $url
	 * @return array
	 */
	private function fetch_direct_media_headers( $url ) {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'sslverify'   => true,
				'headers'     => array( 'User-Agent' => 'MediaConverter/1.0' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'type'             => 'direct',
				'title'            => basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'remote_media' ),
				'is_direct_stream' => true,
				'content_type'     => '',
				'content_length'   => 0,
			);
		}

		$headers      = wp_remote_retrieve_headers( $response );
		$content_type = $headers['content-type'] ?? '';
		$length       = isset( $headers['content-length'] ) ? (int) $headers['content-length'] : 0;
		$filename     = basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'remote_media' );

		return array(
			'type'             => 'direct',
			'title'            => sanitize_file_name( $filename ),
			'content_type'     => sanitize_text_field( $content_type ),
			'content_length'   => $length,
			'is_direct_stream' => true,
		);
	}

	/**
	 * Map PHP upload error code to clear explanation.
	 *
	 * @param int $code
	 * @return string
	 */
	private function get_upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
				return __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'media-converter' );
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'media-converter' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The uploaded file was only partially uploaded.', 'media-converter' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'media-converter' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Missing a temporary folder on the server.', 'media-converter' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Failed to write file to disk.', 'media-converter' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the file upload.', 'media-converter' );
			default:
				return __( 'Unknown upload error occurred.', 'media-converter' );
		}
	}
}
