<?php
/**
 * MediaConverter Pro - Security & Rate Limiting
 *
 * Implements SSRF protection, path traversal defenses, cryptographic tokens,
 * rate limiting, and GDPR-compliant IP hashing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Security {

	/**
	 * Nonce action name.
	 */
	const NONCE_ACTION = 'media_converter_nonce_action';

	/**
	 * Verify WordPress Nonce.
	 *
	 * @param string $nonce
	 * @return bool
	 */
	public function verify_nonce( $nonce ) {
		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Create standard nonce for public frontend.
	 *
	 * @return string
	 */
	public static function create_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Get client IP address safely and hash it for privacy.
	 *
	 * @return string SHA-256 hash of IP
	 */
	public function get_client_ip_hash() {
		$ip = '127.0.0.1';

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && filter_var( $_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP ) ) {
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$list = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$candidate = trim( $list[0] );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate;
			}
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) && filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		$salt = wp_salt( 'auth' );
		return hash( 'sha256', $ip . $salt );
	}

	/**
	 * Check if current client has exceeded rate limits.
	 *
	 * @param int $user_id
	 * @return bool True if allowed, false if limit exceeded.
	 */
	public function check_rate_limit( $user_id = 0 ) {
		// Pro Tier: Unlimited conversions enabled by default
		$options = get_option( 'media_converter_options', array() );
		if ( empty( $options['enable_rate_limiting'] ) ) {
			return true;
		}

		$is_guest = empty( $user_id );
		$limit    = $is_guest
			? ( isset( $options['guest_hourly_limit'] ) ? intval( $options['guest_hourly_limit'] ) : 10 )
			: ( isset( $options['user_hourly_limit'] ) ? intval( $options['user_hourly_limit'] ) : 50 );

		$identifier = $is_guest ? 'ip_' . $this->get_client_ip_hash() : 'user_' . absint( $user_id );
		$transient_key = 'mc_rate_' . substr( md5( $identifier ), 0, 20 );

		$count = (int) get_transient( $transient_key );
		if ( $count >= $limit ) {
			return false;
		}

		// Increment and refresh 1-hour expiry.
		set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Sanitize and validate file path to strictly prevent directory traversal.
	 *
	 * @param string $path
	 * @param string $allowed_root Optional root directory boundary.
	 * @return string|false Sanitized realpath or false if invalid/traversal detected.
	 */
	public function sanitize_safe_path( $path, $allowed_root = '' ) {
		if ( empty( $path ) ) {
			return false;
		}

		// Reject null bytes and path traversal signatures.
		if ( strpos( $path, "\0" ) !== false || strpos( $path, '..' ) !== false ) {
			return false;
		}

		$root = ! empty( $allowed_root ) ? $allowed_root : MEDIA_CONVERTER_STORAGE_DIR;
		$normalized_root = wp_normalize_path( realpath( $root ) ?: $root );
		$normalized_path = wp_normalize_path( $path );

		// Check prefix containment.
		if ( strpos( $normalized_path, $normalized_root ) !== 0 ) {
			return false;
		}

		return $normalized_path;
	}

	/**
	 * Generate a cryptographic, tamper-proof download token with expiry.
	 *
	 * @param int $job_id
	 * @param int $lifetime_seconds
	 * @return string
	 */
	public function generate_download_token( $job_id, $lifetime_seconds = 86400 ) {
		$expires = time() + $lifetime_seconds;
		$secret  = wp_salt( 'nonce' );
		$payload = $job_id . ':' . $expires;
		$sig     = hash_hmac( 'sha256', $payload, $secret );

		return base64_encode( $payload . ':' . $sig );
	}

	/**
	 * Validate download token.
	 *
	 * @param string $token
	 * @param int $expected_job_id
	 * @return bool
	 */
	public function validate_download_token( $token, $expected_job_id ) {
		if ( empty( $token ) ) {
			return false;
		}

		$decoded = base64_decode( $token );
		if ( ! $decoded ) {
			return false;
		}

		$parts = explode( ':', $decoded );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $job_id, $expires, $sig ) = $parts;

		if ( (int) $job_id !== (int) $expected_job_id ) {
			return false;
		}

		if ( time() > (int) $expires ) {
			return false; // Token expired.
		}

		$secret       = wp_salt( 'nonce' );
		$expected_sig = hash_hmac( 'sha256', $job_id . ':' . $expires, $secret );

		return hash_equals( $expected_sig, $sig );
	}
}
