<?php
/**
 * Test Suite: Security & Media Validator
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running Validator & SSRF Security Tests...\n";

$validator = new MediaConverter_Validator();

// 1. Insecure HTTP check
$http_test = $validator->validate_remote_url( 'http://example.com/video.mp4' );
assert( $http_test['success'] === false, 'HTTP URLs must be rejected' );
echo "  [PASS] Insecure HTTP URL successfully rejected.\n";

// 2. Loopback SSRF Check
$loopback_test = $validator->validate_remote_url( 'https://127.0.0.1/video.mp4' );
assert( $loopback_test['success'] === false, '127.0.0.1 loopback must be rejected' );
echo "  [PASS] Loopback IP 127.0.0.1 blocked.\n";

$local_host_test = $validator->validate_remote_url( 'https://localhost/video.mp4' );
assert( $local_host_test['success'] === false, 'localhost must be rejected' );
echo "  [PASS] Hostname localhost blocked.\n";

// 3. RFC1918 Private Network Range SSRF Check
$private_ip1 = $validator->validate_remote_url( 'https://192.168.1.50/video.mp4' );
assert( $private_ip1['success'] === false, '192.168.x.x must be rejected' );
echo "  [PASS] Private IP 192.168.x.x blocked.\n";

$private_ip2 = $validator->validate_remote_url( 'https://10.0.0.1/video.mp4' );
assert( $private_ip2['success'] === false, '10.x.x.x must be rejected' );
echo "  [PASS] Private IP 10.x.x.x blocked.\n";

// 4. Cloud Metadata SSRF Check
$meta_test = $validator->validate_remote_url( 'https://169.254.169.254/latest/meta-data/' );
assert( $meta_test['success'] === false, 'Cloud metadata IP 169.254.169.254 must be blocked' );
echo "  [PASS] Cloud link-local metadata 169.254.169.254 blocked.\n";

// 5. Valid Public HTTPS URL check
$public_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
$public_test = $validator->validate_remote_url( $public_url );
assert( $public_test['success'] === true, 'Public HTTPS YouTube URL should validate' );
assert( $public_test['provider']['type'] === 'youtube', 'Provider must be identified as YouTube' );
echo "  [PASS] Valid public HTTPS YouTube URL validated with oEmbed metadata.\n";

// 6. Forbidden Extension Upload Check
$dummy_php = array(
	'name'     => 'shell.php',
	'type'     => 'application/x-php',
	'tmp_name' => tempnam( sys_get_temp_dir(), 'php_test' ),
	'error'    => UPLOAD_ERR_OK,
	'size'     => 1024,
);
file_put_contents( $dummy_php['tmp_name'], '<?php echo "evil"; ?>' );

$fake_is_uploaded = function( $file ) { return true; };
// Test MIME check directly:
$finfo = finfo_open( FILEINFO_MIME_TYPE );
$real_mime = finfo_file( $finfo, $dummy_php['tmp_name'] );
finfo_close( $finfo );
assert( ! $validator->is_allowed_mime_type( $real_mime, 'php' ), 'PHP extension and MIME must be rejected' );
@unlink( $dummy_php['tmp_name'] );
echo "  [PASS] Malicious executable/PHP upload rejected.\n";

echo "All Validator & SSRF tests passed successfully!\n\n";
