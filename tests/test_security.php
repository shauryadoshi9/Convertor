<?php
/**
 * Test Suite: Security & Cryptographic Tokens
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running Security & Cryptographic Token Tests...\n";

$security = new MediaConverter_Security();

// 1. Token generation & verification
$job_id = 42;
$token = $security->generate_download_token( $job_id, 3600 );
assert( ! empty( $token ), 'Download token must not be empty' );
assert( $security->validate_download_token( $token, $job_id ) === true, 'Token must validate for job 42' );
assert( $security->validate_download_token( $token, 999 ) === false, 'Token must NOT validate for different job 999' );
echo "  [PASS] Cryptographic download token generation and validation verified.\n";

// 2. Tampered token rejection
$tampered_token = base64_encode( base64_decode( $token ) . 'tampered' );
assert( $security->validate_download_token( $tampered_token, $job_id ) === false, 'Tampered token must be rejected' );
echo "  [PASS] Tampered token successfully rejected.\n";

// 3. Expired token rejection
$expired_token = $security->generate_download_token( $job_id, -100 ); // expired in past
assert( $security->validate_download_token( $expired_token, $job_id ) === false, 'Expired token must be rejected' );
echo "  [PASS] Expired token successfully rejected.\n";

// 4. Directory Traversal Path Sanitization
$traversal_attempt = MEDIA_CONVERTER_STORAGE_DIR . '../test.php';
assert( $security->sanitize_safe_path( $traversal_attempt ) === false, 'Relative traversal .. must be rejected' );

$null_byte_attempt = MEDIA_CONVERTER_STORAGE_DIR . "output/test.mp4\0.php";
assert( $security->sanitize_safe_path( $null_byte_attempt ) === false, 'Null byte attack must be rejected' );

$valid_path = MEDIA_CONVERTER_STORAGE_DIR . 'output/test.mp4';
assert( $security->sanitize_safe_path( $valid_path ) !== false, 'Valid contained path must be accepted' );
echo "  [PASS] Path traversal defenses (../ containment and null bytes) verified.\n";

// 5. IP Anonymization & Hashing
$hash1 = $security->get_client_ip_hash();
assert( strlen( $hash1 ) === 64, 'IP hash must be valid SHA-256 (64 hex characters)' );
assert( ctype_xdigit( $hash1 ), 'IP hash must be hexadecimal' );
echo "  [PASS] GDPR-compliant salted IP hashing verified.\n";

echo "All Security tests passed successfully!\n\n";
