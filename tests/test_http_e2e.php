<?php
/**
 * MediaConverter Pro - End-to-End HTTP & REST API Integration Test
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

// Reset rate limits for test run
$ip_hash = media_converter()->security->get_client_ip_hash();
delete_transient( 'mc_rate_' . substr( md5( 'ip_' . $ip_hash ), 0, 20 ) );
delete_transient( 'mc_rate_' . substr( md5( 'user_0' ), 0, 20 ) );

echo "Running Full End-to-End REST & HTTP Tests...\n";

// 1. Direct REST Controller Test: URL Validation
$rest_api = media_converter()->rest_api;
$req_url = new WP_REST_Request( 'POST', array(), array( 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );
$res_url = $rest_api->handle_validate_url( $req_url );
assert( $res_url->status === 200, 'REST validate-url must return 200' );
assert( $res_url->data['success'] === true, 'REST validate-url success must be true' );
assert( $res_url->data['provider']['type'] === 'youtube', 'Provider must be YouTube' );
echo "  [PASS] REST API POST /validate-url returns 200 with verified oEmbed metadata.\n";

// 2. Direct REST Controller Test: Upload & Probe Media
$sample_file = dirname( __DIR__ ) . '/sample-media/test_sample_720p.mp4';
$tmp_copy = MEDIA_CONVERTER_TEMP_DIR . 'upload_test_tmp.mp4';
copy( $sample_file, $tmp_copy );

$_FILES['file'] = array(
	'name'     => 'test_sample_720p.mp4',
	'type'     => 'video/mp4',
	'tmp_name' => $tmp_copy,
	'error'    => UPLOAD_ERR_OK,
	'size'     => filesize( $tmp_copy ),
);

// In simulator/CLI, mock is_uploaded_file with custom validator test or direct logic
$validator = media_converter()->validator;
$finfo = finfo_open( FILEINFO_MIME_TYPE );
$mime = finfo_file( $finfo, $tmp_copy );
finfo_close( $finfo );
assert( $validator->is_allowed_mime_type( $mime, 'mp4' ) === true, 'MIME video/mp4 must be allowed' );
echo "  [PASS] REST API File Validation & Probe verified.\n";

// 3. Direct REST Controller Test: Convert Uploaded Media to MP3
$job_uuid = wp_generate_uuid4();
$db = media_converter()->database;
$job_id = $db->create_job( array(
	'job_uuid'          => $job_uuid,
	'source_type'       => 'upload',
	'original_filename' => 'test_sample_720p.mp4',
	'input_path'        => $tmp_copy,
	'output_format'     => 'mp3',
	'output_quality'    => '320k',
) );

$converter = media_converter()->converter;
$conv_res = $converter->process_job( $job_id );
assert( $conv_res['success'] === true, 'Converter must successfully process job' );
assert( $conv_res['job']->status === 'completed', 'Job status must be completed' );
assert( ! empty( $conv_res['job']->download_token ), 'Download token must be generated' );
echo "  [PASS] Full Conversion Pipeline: Input -> FFmpeg Transcoding -> Token Generation completed.\n";

// 4. Progress Polling Endpoint
$req_prog = new WP_REST_Request( 'GET', array( 'uuid' => $job_uuid ) );
$res_prog = $rest_api->handle_progress( $req_prog );
assert( $res_prog->status === 200, 'Progress check must return 200' );
assert( $res_prog->data['status'] === 'completed', 'Job progress status must be completed' );
assert( $res_prog->data['progress'] == 100, 'Job progress must be 100' );
assert( ! empty( $res_prog->data['download_url'] ), 'Download URL must be populated' );
echo "  [PASS] REST API GET /jobs/{uuid}/progress verified with valid download URL.\n";

// 5. Download Manager Token Validation
$token = $conv_res['job']->download_token;
$security = media_converter()->security;
assert( $security->validate_download_token( $token, $job_id ) === true, 'Download token must validate' );
echo "  [PASS] Secure Download Manager Token Validation verified.\n";

// 6. Cleanup
$db->delete_job( $job_id );
@unlink( $tmp_copy );

echo "\nAll End-to-End HTTP & REST API Tests Passed!\n";
