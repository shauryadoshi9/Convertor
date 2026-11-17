<?php
/**
 * Test Suite: Long Video Optimization & Time-Range Trimming
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Testing Long Video Optimization & Time-Range Trimming Pipeline...\n";

// Clear transient rate limit
delete_option( '_transient_media_conv_rate_' . md5( '127.0.0.1' ) );

$base_url = 'http://127.0.0.1:8080/wp-json/media-converter/v1';

// 1. Submit Conversion Job with Time-Range Trimming (clip 00:00:05 to 00:00:20)
$ch = curl_init( $base_url . '/convert' );
curl_setopt_array( $ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_POST           => true,
	CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
	CURLOPT_POSTFIELDS     => json_encode( array(
		'source_type'     => 'url',
		'url'             => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
		'output_format'   => 'mp4',
		'output_quality'  => '360p',
		'trim_start'      => '00:00:05',
		'trim_end'        => '00:00:20',
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
echo "  [PASS] Trimmed job enqueued asynchronously via HTTP in milliseconds: UUID = {$uuid}\n";

// Verify DB record has trim_start and trim_end stored
$db = media_converter()->database;
$job = $db->get_job_by_uuid( $uuid );
assert( $job->trim_start === '00:00:05', "trim_start should be 00:00:05, got {$job->trim_start}" );
assert( $job->trim_end === '00:00:20', "trim_end should be 00:00:20, got {$job->trim_end}" );
echo "  [PASS] Database job record verified with trim_start = {$job->trim_start} and trim_end = {$job->trim_end}\n";

// 2. Poll progress via GET /jobs/{uuid}/progress
$start = time();
$completed = false;
$job_data = array();

while ( ( time() - $start ) < 120 ) {
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
echo "  [PASS] Trimmed job completed in " . ( time() - $start ) . "s! File: {$job_data['output_filename']} ({$job_data['file_size_fmt']})\n";

// 3. Verify output duration is ~15 seconds (trimmed)
$full_job = $db->get_job_by_uuid( $uuid );
$ffmpeg = media_converter()->ffmpeg;
$probe = $ffmpeg->probe_media( $full_job->output_path );
assert( $probe['success'] === true, 'Probe converted file' );
assert( $probe['duration'] >= 10 && $probe['duration'] <= 25, "Duration should be ~15 seconds for trimmed clip, got {$probe['duration']}" );
assert( $probe['video_codec'] === 'h264', 'Video codec must be h264' );
echo "  [PASS] Clip duration verified: {$probe['duration']}s (Expected ~15s from full 10-minute source).\n";

// 4. Verify Download URL
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
curl_close( $ch );

assert( $dl_code === 200, "Download URL returned HTTP {$dl_code}" );
echo "  [PASS] Download URL verified HTTP 200 OK.\n";

// Cleanup
$db->delete_job( $full_job->id );
echo "\nAll Long Video Optimization & Trimming Tests Passed Successfully!\n";
