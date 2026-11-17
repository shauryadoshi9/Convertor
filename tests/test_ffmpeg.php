<?php
/**
 * Test Suite: FFmpeg & FFprobe Integration
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running FFmpeg Integration Tests...\n";

$ffmpeg = new MediaConverter_FFmpeg();

// 1. Check binaries availability
$bin_check = $ffmpeg->check_ffmpeg();
assert( $bin_check['available'] === true, 'FFmpeg must be available' );
echo "  [PASS] FFmpeg binary verified: {$bin_check['version']}\n";

$probe_check = $ffmpeg->check_ffprobe();
assert( $probe_check['available'] === true, 'FFprobe must be available' );
echo "  [PASS] FFprobe binary verified: {$probe_check['version']}\n";

// 2. Probe 720p video
$sample_720p = dirname( __DIR__ ) . '/sample-media/test_sample_720p.mp4';
$probe_720p = $ffmpeg->probe_media( $sample_720p );
assert( $probe_720p['success'] === true, 'Probe 720p must succeed' );
assert( $probe_720p['width'] === 1280, 'Width must be 1280' );
assert( $probe_720p['height'] === 720, 'Height must be 720' );
assert( in_array( '720p', $probe_720p['available_video_qualities'], true ), '720p must be in available qualities' );
assert( ! in_array( '1080p', $probe_720p['available_video_qualities'], true ), '1080p must NOT be in available qualities (no upscaling)' );
echo "  [PASS] Probe 720p media verified with strict no-upscaling rule.\n";

// 3. Probe 1080p video
$sample_1080p = dirname( __DIR__ ) . '/sample-media/test_sample_1080p.mkv';
$probe_1080p = $ffmpeg->probe_media( $sample_1080p );
assert( $probe_1080p['success'] === true, 'Probe 1080p must succeed' );
assert( $probe_1080p['height'] === 1080, 'Height must be 1080' );
assert( in_array( '1080p', $probe_1080p['available_video_qualities'], true ), '1080p must be in available qualities for 1080p source' );
echo "  [PASS] Probe 1080p media verified.\n";

// 4. Test MP4 Conversion (transcoding 720p to 480p)
$output_mp4 = MEDIA_CONVERTER_TEMP_DIR . 'test_out_' . time() . '.mp4';
$progress_called = false;
$progress_cb = function( $pct, $stage ) use ( &$progress_called ) {
	$progress_called = true;
};

$conv_mp4 = $ffmpeg->convert( $sample_720p, $output_mp4, 'mp4', '480p', $progress_cb, $probe_720p['duration'] );
assert( $conv_mp4['success'] === true, 'MP4 conversion must succeed' );
assert( file_exists( $output_mp4 ), 'Output MP4 file must exist' );
assert( filesize( $output_mp4 ) > 0, 'Output MP4 file size must be > 0' );
assert( $progress_called === true, 'Progress callback must have been triggered' );

// Probe generated MP4
$probe_out_mp4 = $ffmpeg->probe_media( $output_mp4 );
assert( $probe_out_mp4['height'] === 480, 'Output video height must be exactly 480' );
assert( $probe_out_mp4['video_codec'] === 'h264', 'Video codec must be h264' );
@unlink( $output_mp4 );
echo "  [PASS] Video Transcoding to MP4 (480p H.264/AAC) successful.\n";

// 5. Test MP3 Audio Extraction (320kbps)
$output_mp3 = MEDIA_CONVERTER_TEMP_DIR . 'test_out_' . time() . '.mp3';
$conv_mp3 = $ffmpeg->convert( $sample_720p, $output_mp3, 'mp3', '320k', $progress_cb, $probe_720p['duration'], array( 'title' => 'Test Track', 'artist' => 'Prism Audio' ) );
assert( $conv_mp3['success'] === true, 'MP3 extraction must succeed' );
assert( file_exists( $output_mp3 ), 'Output MP3 must exist' );
assert( filesize( $output_mp3 ) > 0, 'Output MP3 file size must be > 0' );

// Probe generated MP3
$probe_out_mp3 = $ffmpeg->probe_media( $output_mp3 );
assert( $probe_out_mp3['audio_codec'] === 'mp3', 'Audio codec must be mp3' );
assert( $probe_out_mp3['has_video'] === false, 'MP3 must not have video stream' );
@unlink( $output_mp3 );
echo "  [PASS] Audio Extraction to MP3 (320kbps LAME) with metadata successful.\n";

// 6. Test Instantaneous Fast Stream Copy (H.264/AAC passthrough)
$output_stream_copy = MEDIA_CONVERTER_TEMP_DIR . 'test_copy_' . time() . '.mp4';
$t_start = microtime( true );
$conv_copy = $ffmpeg->convert( $sample_720p, $output_stream_copy, 'mp4', '720p', $progress_cb, $probe_720p['duration'], array(), $probe_720p );
$copy_duration = microtime( true ) - $t_start;
assert( $conv_copy['success'] === true, 'Stream copy must succeed' );
assert( file_exists( $output_stream_copy ), 'Output copy must exist' );
assert( filesize( $output_stream_copy ) > 0, 'Output copy file size must be > 0' );
assert( $copy_duration < 3.0, 'Stream copy should complete in under 3 seconds' );
$probe_copy = $ffmpeg->probe_media( $output_stream_copy );
assert( $probe_copy['video_codec'] === 'h264', 'Video codec must remain h264' );
assert( $probe_copy['audio_codec'] === 'aac', 'Audio codec must remain aac' );
assert( $probe_copy['height'] === 720, 'Height must be preserved' );
@unlink( $output_stream_copy );
echo "  [PASS] Instantaneous Fast Stream Copy remuxing verified ({$copy_duration}s).\n";

echo "All FFmpeg tests passed successfully!\n\n";
