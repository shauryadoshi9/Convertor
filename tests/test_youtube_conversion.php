<?php
/**
 * Test Suite: YouTube Extraction & Transcoding Pipeline
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running YouTube Stream Extraction & Conversion Test...\n";

$validator = media_converter()->validator;
$test_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

// 1. Validate YouTube URL and extract metadata
$val_res = $validator->validate_remote_url( $test_url );
assert( $val_res['success'] === true, 'YouTube URL validation must succeed' );
assert( ! empty( $val_res['provider']['title'] ), 'YouTube title must be extracted' );
assert( ! empty( $val_res['provider']['thumbnail'] ), 'YouTube thumbnail must be extracted' );
echo "  [PASS] YouTube Metadata extracted: '{$val_res['provider']['title']}' (Duration: {$val_res['provider']['duration']})\n";

// 2. Test YouTube Conversion to MP4
$db = media_converter()->database;
$uuid_mp4 = wp_generate_uuid4();
$job_id_mp4 = $db->create_job( array(
	'job_uuid'          => $uuid_mp4,
	'source_type'       => 'url',
	'source_url'        => $test_url,
	'original_filename' => $val_res['provider']['title'],
	'output_format'     => 'mp4',
	'output_quality'    => '360p',
) );

$converter = media_converter()->converter;
echo "  [INFO] Processing YouTube conversion job #{$job_id_mp4} to MP4...\n";
$conv_res = $converter->process_job( $job_id_mp4 );

assert( $conv_res['success'] === true, 'YouTube to MP4 conversion must succeed: ' . ( $conv_res['error'] ?? '' ) );
assert( file_exists( $conv_res['job']->output_path ), 'Output MP4 file must exist on disk' );
assert( filesize( $conv_res['job']->output_path ) > 0, 'Output MP4 file must not be empty' );
assert( ! empty( $conv_res['job']->download_token ), 'Download token must be generated' );

$ffmpeg = media_converter()->ffmpeg;
$probe = $ffmpeg->probe_media( $conv_res['job']->output_path );
assert( $probe['success'] === true, 'Probing converted MP4 must succeed' );
assert( $probe['video_codec'] === 'h264', 'Video codec must be h264' );
echo "  [PASS] YouTube to MP4 Conversion succeeded! File: {$conv_res['job']->output_filename} (" . size_format( $conv_res['job']->file_size, 2 ) . ")\n";

// 3. Test Download Token Validation
$token = $conv_res['job']->download_token;
$security = media_converter()->security;
assert( $security->validate_download_token( $token, $job_id_mp4 ) === true, 'Download token must validate' );
echo "  [PASS] Download token verified: Ready for one-click browser download.\n";

// Clean up test job
$db->delete_job( $job_id_mp4 );

echo "\nYouTube Conversion & Download Pipeline Verified Successfully!\n";
