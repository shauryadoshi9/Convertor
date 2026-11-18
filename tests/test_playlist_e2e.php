<?php
/**
 * End-to-End Test for Complete Playlist Batch Conversion & ZIP Packaging
 */

require_once __DIR__ . '/../standalone/wp-simulator.php';

echo "========================================================\n";
echo " TEST SUITE: COMPLETE PLAYLIST & 1-CLICK ZIP DOWNLOAD\n";
echo "========================================================\n\n";

$validator = media_converter()->validator;
$database  = media_converter()->database;
$rest_api  = media_converter()->rest_api;
$converter = media_converter()->converter;

$sample_playlist_url = 'https://www.youtube.com/playlist?list=PLbpi6ZahtOH6Blw3RGYpWkSByi_T7Rygb';

// Test 1: Playlist URL Detection
echo "[1] Testing playlist URL recognition...\n";
$is_pl1 = $validator->is_playlist_url( $sample_playlist_url );
$is_pl2 = $validator->is_playlist_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLbpi6ZahtOH6Blw3RGYpWkSByi_T7Rygb' );
$is_not_pl = $validator->is_playlist_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );

assert( $is_pl1 === true, 'Failed to detect playlist URL' );
assert( $is_pl2 === true, 'Failed to detect watch URL with list param' );
assert( $is_not_pl === false, 'Wrongly detected single video as playlist' );
echo "  ✓ Playlist URL detection passed.\n\n";

// Test 2: Playlist Info Extraction via Validator
echo "[2] Testing playlist info extraction via yt-dlp...\n";
$info = $validator->fetch_playlist_info( $sample_playlist_url, 5 ); // Limit to 5 for speed
assert( $info['success'] === true, 'Failed to extract playlist info' );
assert( ! empty( $info['playlist_title'] ), 'Playlist title is empty' );
assert( count( $info['items'] ) > 0, 'No items returned' );
echo "  ✓ Playlist extracted: '{$info['playlist_title']}' with " . count( $info['items'] ) . " items.\n";
echo "  ✓ First track: '{$info['items'][0]['title']}' ({$info['items'][0]['duration_formatted']})\n\n";

// Test 3: REST API POST /playlist-info
echo "[3] Testing REST API /playlist-info...\n";
$req = new WP_REST_Request( 'POST', array(), array( 'url' => $sample_playlist_url ) );
$res = $rest_api->handle_playlist_info( $req );
assert( $res->status === 200, 'REST playlist-info returned non-200' );
assert( $res->data['success'] === true, 'REST playlist-info success false' );
assert( count( $res->data['items'] ) > 0, 'REST playlist-info returned no items' );
echo "  ✓ REST API /playlist-info returned 200 OK with valid items.\n\n";

// Test 4: Batch Creation in Database
echo "[4] Testing database batch table and linking...\n";
$batch_uuid = wp_generate_uuid4();
$batch_id = $database->create_batch( array(
	'batch_uuid'     => $batch_uuid,
	'playlist_title' => 'Test Batch Playlist',
	'playlist_url'   => $sample_playlist_url,
	'total_items'    => 2,
	'output_format'  => 'mp3',
	'output_quality' => '320k',
) );
assert( $batch_id !== false, 'Failed to create batch in database' );

$retrieved_batch = $database->get_batch_by_uuid( $batch_uuid );
assert( $retrieved_batch !== null, 'Failed to retrieve batch by UUID' );
assert( $retrieved_batch->playlist_title === 'Test Batch Playlist', 'Batch title mismatch' );
echo "  ✓ Database batch created (#{$batch_id}) and verified by UUID.\n\n";

// Test 5: Child Jobs Creation and Batch Worker
echo "[5] Testing batch conversion execution & ZIP compilation...\n";
// Create 2 test jobs using sample audio files for fast verification
$job1_uuid = wp_generate_uuid4();
$job1_id = $database->create_job( array(
	'job_uuid'          => $job1_uuid,
	'batch_uuid'        => $batch_uuid,
	'source_type'       => 'upload',
	'original_filename' => 'Track 01 Sample Audio',
	'input_path'        => dirname( __DIR__ ) . '/sample-media/test_audio.wav',
	'output_format'     => 'mp3',
	'output_quality'    => '320k',
) );

$job2_uuid = wp_generate_uuid4();
$job2_id = $database->create_job( array(
	'job_uuid'          => $job2_uuid,
	'batch_uuid'        => $batch_uuid,
	'source_type'       => 'upload',
	'original_filename' => 'Track 02 Sample Video',
	'input_path'        => dirname( __DIR__ ) . '/sample-media/test_sample_720p.mp4',
	'output_format'     => 'mp3',
	'output_quality'    => '320k',
) );

$jobs = $database->get_batch_jobs( $batch_uuid );
assert( count( $jobs ) === 2, 'Expected 2 child jobs in batch' );

// Run playlist-worker via CLI directly
$worker_script = MEDIA_CONVERTER_PATH . 'bin/playlist-worker.php';
$php_bin = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
$worker_cmd = sprintf(
	'%s %s --batch_uuid=%s',
	escapeshellarg( $php_bin ),
	escapeshellarg( $worker_script ),
	escapeshellarg( $batch_uuid )
);

$worker_out = shell_exec( $worker_cmd );
echo "  Worker output: " . trim( $worker_out ) . "\n";

$final_batch = $database->get_batch_by_uuid( $batch_uuid );
assert( $final_batch->status === 'completed', 'Batch status not completed: ' . $final_batch->status );
assert( intval( $final_batch->completed_items ) === 2, 'Expected 2 completed items' );
assert( ! empty( $final_batch->zip_path ), 'ZIP path is empty' );
assert( file_exists( $final_batch->zip_path ), 'ZIP file does not exist on disk: ' . $final_batch->zip_path );
assert( filesize( $final_batch->zip_path ) > 0, 'ZIP file is empty' );
echo "  ✓ Batch completed successfully!\n";
echo "  ✓ Compiled ZIP: {$final_batch->zip_filename} (" . round( $final_batch->zip_size / 1024, 2 ) . " KB)\n\n";

// Test 6: REST API GET /playlist/{uuid}/progress
echo "[6] Testing REST API /playlist/{uuid}/progress...\n";
$prog_req = new WP_REST_Request( 'GET', array( 'uuid' => $batch_uuid ) );
$prog_res = $rest_api->handle_playlist_progress( $prog_req );
assert( $prog_res->status === 200, 'Progress endpoint returned non-200' );
assert( $prog_res->data['status'] === 'completed', 'Progress status not completed' );
assert( ! empty( $prog_res->data['zip_download_url'] ), 'Missing zip_download_url in response' );
assert( count( $prog_res->data['items'] ) === 2, 'Item count mismatch in response' );
echo "  ✓ Progress response verified: 100% completed, ZIP download URL provided.\n\n";

// Cleanup test output files
@unlink( $final_batch->zip_path );
$database->delete_job( $job1_id );
$database->delete_job( $job2_id );

echo "========================================================\n";
echo " ALL PLAYLIST & BATCH TESTS PASSED WITH 100% SUCCESS!\n";
echo "========================================================\n";
