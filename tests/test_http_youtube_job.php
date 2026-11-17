<?php
/**
 * Test Suite: HTTP REST API End-to-End YouTube Conversion
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Testing HTTP REST API YouTube Job Processing...\n";

// Clear transient rate limit
delete_option( '_transient_media_conv_rate_' . md5( '127.0.0.1' ) );

$base_url = 'http://127.0.0.1:8080/wp-json/media-converter/v1';

// 1. Submit Conversion Job via POST /convert
$ch = curl_init( $base_url . '/convert' );
curl_setopt_array( $ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_POST           => true,
	CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
	CURLOPT_POSTFIELDS     => json_encode( array(
		'source_type'     => 'url',
		'url'             => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		'output_format'   => 'mp4',
		'output_quality'  => '360p',
		'legal_confirmed' => true,
	) ),
) );

$resp = curl_exec( $ch );
$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

assert( $code === 200, "POST /convert returned HTTP {$code}: {$resp}" );
$data = json_decode( $resp, true );
assert( ! empty( $data['job_uuid'] ), 'job_uuid returned' );
$uuid = $data['job_uuid'];
echo "  [PASS] Job enqueued asynchronously via HTTP in milliseconds: UUID = {$uuid}\n";

// 2. Poll progress via GET /jobs/{uuid}/progress
$start = time();
$completed = false;
$job_data = array();

while ( ( time() - $start ) < 180 ) {
	$ch = curl_init( "{$base_url}/jobs/{$uuid}/progress" );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$p_resp = curl_exec( $ch );
	curl_close( $ch );

	$p_data = json_decode( $p_resp, true );
	if ( ! empty( $p_data['status'] ) ) {
		$status = $p_data['status'];
		$pct    = $p_data['progress'];
		$stage  = $p_data['stage'];
		echo "    Status: {$status} | Progress: {$pct}% | Stage: {$stage}\n";
		flush();

		if ( 'completed' === $status ) {
			$completed = true;
			$job_data  = $p_data;
			break;
		} elseif ( 'failed' === $status ) {
			throw new Exception( "Job failed: " . ( $p_data['error_message'] ?? 'Unknown' ) );
		}
	}
	sleep( 2 );
}

assert( $completed === true, 'Job must reach completed status' );
assert( ! empty( $job_data['download_url'] ), 'Download URL must be present' );
echo "  [PASS] Job successfully completed! Download URL: {$job_data['download_url']}\n";

// 3. Verify download URL works
$download_url = $job_data['download_url'];
if ( str_starts_with( $download_url, '/' ) ) {
	$download_url = 'http://127.0.0.1:8080' . $download_url;
}
$ch = curl_init( $download_url );
curl_setopt_array( $ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => true,
	CURLOPT_NOBODY         => true,
) );
$dl_resp = curl_exec( $ch );
$dl_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
$content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
curl_close( $ch );

assert( $dl_code === 200, "Download URL returned HTTP {$dl_code}" );
assert( str_contains( $content_type, 'video/mp4' ), "Content-Type must be video/mp4, got {$content_type}" );
echo "  [PASS] Download URL verified HTTP 200 with Content-Type: {$content_type}\n";

echo "\nFull Asynchronous YouTube Conversion & Download Verified Successfully!\n";
