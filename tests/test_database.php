<?php
/**
 * Test Suite: Database & Job Lifecycle
 */

require_once dirname( __DIR__ ) . '/standalone/wp-simulator.php';

echo "Running Database & Job Lifecycle Tests...\n";

$db = new MediaConverter_Database();

// 1. Create Job
$job_uuid = wp_generate_uuid4();
$job_id = $db->create_job( array(
	'job_uuid'          => $job_uuid,
	'user_id'           => 1,
	'source_type'       => 'upload',
	'original_filename' => 'my_video.mp4',
	'output_format'     => 'mp4',
	'output_quality'    => '720p',
) );

assert( $job_id > 0, 'Job creation must return valid ID' );
echo "  [PASS] Job record created with ID #{$job_id}.\n";

// 2. Fetch Job by ID and by UUID
$job_by_id = $db->get_job_by_id( $job_id );
assert( $job_by_id !== null, 'Job must be retrievable by ID' );
assert( $job_by_id->job_uuid === $job_uuid, 'UUID must match' );

$job_by_uuid = $db->get_job_by_uuid( $job_uuid );
assert( $job_by_uuid !== null, 'Job must be retrievable by UUID' );
assert( (int) $job_by_uuid->id === (int) $job_id, 'ID must match' );
echo "  [PASS] Job retrieval by ID and UUID verified.\n";

// 3. Update Progress and State transitions
$db->update_progress( $job_id, 45.5, 'Transcoding H.264 video' );
$updated = $db->get_job_by_id( $job_id );
assert( round( $updated->progress, 1 ) == 45.5, 'Progress must be 45.5' );
assert( $updated->stage === 'Transcoding H.264 video', 'Stage text must update' );
echo "  [PASS] Real-time progress and stage updating verified.\n";

// 4. Mark Completed
$db->mark_completed( $job_id, array(
	'output_filename' => 'my_video_converted.mp4',
	'file_size'       => 15000000,
	'duration_seconds'=> 120.5,
) );
$completed = $db->get_job_by_id( $job_id );
assert( $completed->status === 'completed', 'Status must be completed' );
assert( (int) $completed->progress === 100, 'Progress must be 100 on completion' );
assert( (int) $completed->file_size === 15000000, 'File size must be saved' );
echo "  [PASS] Mark completed transition verified.\n";

// 5. Query Aggregated Stats
$stats = $db->get_stats();
assert( $stats['total'] >= 1, 'Total conversions must be at least 1' );
assert( $stats['completed'] >= 1, 'Completed conversions must be at least 1' );
echo "  [PASS] Aggregated dashboard statistics calculation verified.\n";

// 6. Delete Job
$db->delete_job( $job_id );
$deleted = $db->get_job_by_id( $job_id );
assert( $deleted === null, 'Deleted job must no longer exist in DB' );
echo "  [PASS] Job deletion and resource cleanup verified.\n";

echo "All Database & Job Lifecycle tests passed successfully!\n\n";
