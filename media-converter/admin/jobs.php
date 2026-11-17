<?php
/**
 * MediaConverter Pro - Admin Jobs Management View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$current_page  = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;

$args = array(
	'status'   => $status_filter,
	'search'   => $search,
	'page'     => $current_page,
	'per_page' => $per_page,
	'orderby'  => 'id',
	'order'    => 'DESC',
);

$jobs       = $database->get_jobs( $args );
$total_jobs = $database->count_jobs( $status_filter );
$total_pages = ceil( $total_jobs / $per_page );

// Counts for filter tabs.
$all_count        = $database->count_jobs();
$queued_count     = $database->count_jobs( 'queued' );
$processing_count = $database->count_jobs( 'processing' );
$completed_count  = $database->count_jobs( 'completed' );
$failed_count     = $database->count_jobs( 'failed' );
$expired_count    = $database->count_jobs( 'expired' );
?>
<div class="wrap mc-admin-wrap">
	<div class="mc-admin-header">
		<div class="mc-admin-header-title">
			<h1><?php esc_html_e( 'Conversion Management', 'media-converter' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Monitor real-time job progress, retry failures, cancel active transcoding, and manage storage.', 'media-converter' ); ?></p>
		</div>
	</div>

	<!-- Status Filter Tabs -->
	<ul class="subsubsub mc-status-tabs">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs' ) ); ?>" class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">All <span class="count">(<?php echo esc_html( $all_count ); ?>)</span></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs&status=queued' ) ); ?>" class="<?php echo ( 'queued' === $status_filter ) ? 'current' : ''; ?>">Queued <span class="count">(<?php echo esc_html( $queued_count ); ?>)</span></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs&status=processing' ) ); ?>" class="<?php echo ( 'processing' === $status_filter ) ? 'current' : ''; ?>">Processing <span class="count">(<?php echo esc_html( $processing_count ); ?>)</span></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs&status=completed' ) ); ?>" class="<?php echo ( 'completed' === $status_filter ) ? 'current' : ''; ?>">Completed <span class="count">(<?php echo esc_html( $completed_count ); ?>)</span></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs&status=failed' ) ); ?>" class="<?php echo ( 'failed' === $status_filter ) ? 'current' : ''; ?>">Failed <span class="count">(<?php echo esc_html( $failed_count ); ?>)</span></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs&status=expired' ) ); ?>" class="<?php echo ( 'expired' === $status_filter ) ? 'current' : ''; ?>">Expired <span class="count">(<?php echo esc_html( $expired_count ); ?>)</span></a></li>
	</ul>

	<!-- Search bar -->
	<form method="get" class="mc-search-box">
		<input type="hidden" name="page" value="media-converter-jobs" />
		<?php if ( ! empty( $status_filter ) ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
		<?php endif; ?>
		<p class="search-box">
			<label class="screen-reader-text" for="mc-search-input">Search Jobs:</label>
			<input type="search" id="mc-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by filename or UUID...', 'media-converter' ); ?>" />
			<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search Jobs', 'media-converter' ); ?>" />
		</p>
	</form>

	<div class="tablenav top">
		<div class="tablenav-pages">
			<span class="displaying-num"><?php echo sprintf( _n( '%s item', '%s items', $total_jobs, 'media-converter' ), number_format_i18n( $total_jobs ) ); ?></span>
			<?php if ( $total_pages > 1 ) : ?>
				<span class="pagination-links">
					<?php echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $current_page,
					) ); ?>
				</span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Main Table -->
	<table class="wp-list-table widefat fixed striped mc-table">
		<thead>
			<tr>
				<th width="70"><?php esc_html_e( 'ID', 'media-converter' ); ?></th>
				<th><?php esc_html_e( 'Original File', 'media-converter' ); ?></th>
				<th width="100"><?php esc_html_e( 'Format', 'media-converter' ); ?></th>
				<th width="110"><?php esc_html_e( 'Quality', 'media-converter' ); ?></th>
				<th width="130"><?php esc_html_e( 'Status', 'media-converter' ); ?></th>
				<th width="150"><?php esc_html_e( 'Progress', 'media-converter' ); ?></th>
				<th width="110"><?php esc_html_e( 'File Size', 'media-converter' ); ?></th>
				<th width="170"><?php esc_html_e( 'Created At', 'media-converter' ); ?></th>
				<th width="140"><?php esc_html_e( 'Actions', 'media-converter' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $jobs ) ) : ?>
				<tr>
					<td colspan="9" class="mc-empty-cell"><?php esc_html_e( 'No conversion jobs match the criteria.', 'media-converter' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<tr id="mc-job-row-<?php echo esc_attr( $job->id ); ?>">
						<td><strong>#<?php echo esc_html( $job->id ); ?></strong></td>
						<td>
							<strong class="mc-filename"><?php echo esc_html( $job->original_filename ); ?></strong>
							<div class="mc-row-sub">
								<span>UUID: <?php echo esc_html( $job->job_uuid ); ?></span><br>
								<span>Source: <?php echo esc_html( ucfirst( $job->source_type ) ); ?></span>
								<?php if ( ! empty( $job->error_message ) ) : ?>
									<div class="mc-error-text"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $job->error_message ); ?></div>
								<?php endif; ?>
							</div>
						</td>
						<td>
							<span class="mc-format-tag mc-format-<?php echo esc_attr( $job->output_format ); ?>">
								<?php echo esc_html( strtoupper( $job->output_format ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $job->output_quality ); ?></td>
						<td>
							<span class="mc-status-pill mc-status-<?php echo esc_attr( $job->status ); ?>">
								<?php echo esc_html( ucfirst( $job->status ) ); ?>
							</span>
						</td>
						<td>
							<div class="mc-progress-bar-small">
								<div class="mc-progress-fill-small" style="width: <?php echo esc_attr( $job->progress ); ?>%;"></div>
							</div>
							<small><?php echo esc_html( round( $job->progress ) ); ?>% — <?php echo esc_html( $job->stage ); ?></small>
						</td>
						<td><?php echo size_format( $job->file_size, 2 ); ?></td>
						<td><small><?php echo esc_html( $job->created_at ); ?></small></td>
						<td>
							<div class="mc-actions-group">
								<?php if ( 'completed' === $job->status && ! empty( $job->download_token ) ) : ?>
									<a href="<?php echo esc_url( rest_url( 'media-converter/v1/download?token=' . urlencode( $job->download_token ) ) ); ?>" class="button button-small" target="_blank" title="Download">
										<span class="dashicons dashicons-download"></span>
									</a>
								<?php endif; ?>
								<?php if ( in_array( $job->status, array( 'queued', 'processing' ), true ) ) : ?>
									<button class="button button-small mc-btn-action" data-id="<?php echo esc_attr( $job->id ); ?>" data-action="cancel" title="Cancel">
										<span class="dashicons dashicons-no-alt"></span>
									</button>
								<?php endif; ?>
								<?php if ( in_array( $job->status, array( 'failed', 'cancelled' ), true ) ) : ?>
									<button class="button button-small mc-btn-action" data-id="<?php echo esc_attr( $job->id ); ?>" data-action="retry" title="Retry">
										<span class="dashicons dashicons-controls-repeat"></span>
									</button>
								<?php endif; ?>
								<button class="button button-small button-link-delete mc-btn-action" data-id="<?php echo esc_attr( $job->id ); ?>" data-action="delete" title="Delete">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
