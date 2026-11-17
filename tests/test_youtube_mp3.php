<?php
/**
 * Test Suite: YouTube to MP3 Audio Extraction
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running YouTube to MP3 Extraction Test...\n";

$db = media_converter()->database;
$uuid = wp_generate_uuid4();
$job_id = $db->create_job( array(
	'job_uuid'          => $uuid,
	'source_type'       => 'url',
	'source_url'        => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
	'original_filename' => 'Rick Astley Audio',
	'output_format'     => 'mp3',
	'output_quality'    => '320k',
) );

$converter = media_converter()->converter;
echo "  [INFO] Processing YouTube conversion job #{$job_id} to MP3 (320kbps)...\n";
$res = $converter->process_job( $job_id );

assert( $res['success'] === true, 'Conversion to MP3 must succeed' );
assert( file_exists( $res['job']->output_path ), 'Output MP3 must exist on disk' );
assert( filesize( $res['job']->output_path ) > 0, 'Output MP3 size must be > 0' );

$ffmpeg = media_converter()->ffmpeg;
$probe = $ffmpeg->probe_media( $res['job']->output_path );
assert( $probe['audio_codec'] === 'mp3', 'Codec must be mp3' );
assert( $probe['has_video'] === false, 'Audio file must not contain video' );

echo "  [PASS] YouTube to MP3 Conversion succeeded! File: {$res['job']->output_filename} (" . size_format( $res['job']->file_size, 2 ) . ")\n";
echo "  [PASS] Download Token: {$res['job']->download_token}\n";

$db->delete_job( $job_id );
echo "YouTube to MP3 Verified Successfully!\n";
