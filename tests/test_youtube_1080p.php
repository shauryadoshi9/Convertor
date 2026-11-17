<?php
/**
 * Test Suite: YouTube 1080p MP4 Fast Stream Copy
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running YouTube 1080p Conversion Test...\n";

$validator = media_converter()->validator;
// Test with a short high quality clip
$test_url = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'; // Big Buck Bunny 60fps 4K/1080p official clip

$val_res = $validator->validate_remote_url( $test_url );
assert( $val_res['success'] === true, 'URL validation failed' );
echo "  [PASS] YouTube Title: {$val_res['provider']['title']}\n";

$db = media_converter()->database;
$uuid = wp_generate_uuid4();
$job_id = $db->create_job( array(
	'job_uuid'          => $uuid,
	'source_type'       => 'url',
	'source_url'        => $test_url,
	'original_filename' => $val_res['provider']['title'],
	'output_format'     => 'mp4',
	'output_quality'    => '1080p',
) );

$t_start = microtime( true );
$converter = media_converter()->converter;
echo "  [INFO] Starting 1080p conversion job #{$job_id}...\n";
$conv_res = $converter->process_job( $job_id );

$elapsed = round( microtime( true ) - $t_start, 2 );
assert( $conv_res['success'] === true, '1080p conversion failed: ' . ( $conv_res['error'] ?? '' ) );
assert( file_exists( $conv_res['job']->output_path ), 'Output file missing' );

$ffmpeg = media_converter()->ffmpeg;
$probe = $ffmpeg->probe_media( $conv_res['job']->output_path );
assert( $probe['success'] === true, 'Probe failed' );
assert( $probe['video_codec'] === 'h264', 'Expected h264 codec' );
assert( $probe['audio_codec'] === 'aac', 'Expected aac audio codec' );

echo "  [PASS] Converted in {$elapsed}s! Output: {$conv_res['job']->output_filename} (" . size_format( $conv_res['job']->file_size, 2 ) . ")\n";
echo "  [PASS] Codecs verified: Video: {$probe['video_codec']}, Audio: {$probe['audio_codec']}, Resolution: {$probe['resolution']}\n";

$db->delete_job( $job_id );
echo "1080p Test Succeeded!\n";
