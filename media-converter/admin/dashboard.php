<?php
/**
 * MediaConverter Pro - Admin Dashboard View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats       = $database->get_stats();
$recent_jobs = $database->get_jobs( array( 'per_page' => 8, 'orderby' => 'created_at', 'order' => 'DESC' ) );
$ffmpeg_stat = $ffmpeg->check_ffmpeg();
?>
<div class="wrap mc-admin-wrap">
	<div class="mc-admin-header">
		<div class="mc-admin-header-title">
			<h1><?php esc_html_e( 'Media Converter Dashboard', 'media-converter' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Real-time media conversion analytics, storage utilization, and queue performance.', 'media-converter' ); ?></p>
		</div>
		<div class="mc-admin-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs' ) ); ?>" class="button button-primary">
				<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Manage Jobs', 'media-converter' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-settings' ) ); ?>" class="button">
				<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'media-converter' ); ?>
			</a>
		</div>
	</div>

	<!-- System Banner -->
	<?php if ( ! $ffmpeg_stat['available'] ) : ?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'FFmpeg Warning:', 'media-converter' ); ?></strong> <?php echo esc_html( $ffmpeg_stat['error'] ); ?> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-settings' ) ); ?>"><?php esc_html_e( 'Configure FFmpeg Path', 'media-converter' ); ?></a></p>
		</div>
	<?php endif; ?>

	<!-- Stats Grid -->
	<div class="mc-stats-grid">
		<div class="mc-stat-card">
			<div class="mc-stat-icon mc-icon-blue">
				<span class="dashicons dashicons-video-alt3"></span>
			</div>
			<div class="mc-stat-info">
				<span class="mc-stat-label"><?php esc_html_e( 'Total Conversions', 'media-converter' ); ?></span>
				<span class="mc-stat-value"><?php echo number_format( $stats['total'] ); ?></span>
			</div>
		</div>

		<div class="mc-stat-card">
			<div class="mc-stat-icon mc-icon-green">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="mc-stat-info">
				<span class="mc-stat-label"><?php esc_html_e( 'Successful Conversions', 'media-converter' ); ?></span>
				<span class="mc-stat-value"><?php echo number_format( $stats['completed'] ); ?></span>
				<span class="mc-stat-sub"><?php echo esc_html( $stats['success_rate'] ); ?>% <?php esc_html_e( 'success rate', 'media-converter' ); ?></span>
			</div>
		</div>

		<div class="mc-stat-card">
			<div class="mc-stat-icon mc-icon-red">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="mc-stat-info">
				<span class="mc-stat-label"><?php esc_html_e( 'Failed Conversions', 'media-converter' ); ?></span>
				<span class="mc-stat-value"><?php echo number_format( $stats['failed'] ); ?></span>
			</div>
		</div>

		<div class="mc-stat-card">
			<div class="mc-stat-icon mc-icon-orange">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="mc-stat-info">
				<span class="mc-stat-label"><?php esc_html_e( 'Active Conversion Jobs', 'media-converter' ); ?></span>
				<span class="mc-stat-value"><?php echo number_format( $stats['active'] ); ?></span>
			</div>
		</div>

		<div class="mc-stat-card">
			<div class="mc-stat-icon mc-icon-purple">
				<span class="dashicons dashicons-database"></span>
			</div>
			<div class="mc-stat-info">
				<span class="mc-stat-label"><?php esc_html_e( 'Storage Output Used', 'media-converter' ); ?></span>
				<span class="mc-stat-value"><?php echo size_format( $stats['storage_bytes'], 2 ); ?></span>
			</div>
		</div>

		<div class="mc-stat-card">
			<div class="mc-stat-icon mc-icon-teal">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="mc-stat-info">
				<span class="mc-stat-label"><?php esc_html_e( 'Total Media Transcoded', 'media-converter' ); ?></span>
				<span class="mc-stat-value"><?php echo gmdate( 'H\h i\m s\s', (int) $stats['duration_seconds'] ); ?></span>
			</div>
		</div>
	</div>

	<!-- Recent Activity Section -->
	<div class="mc-section-card">
		<div class="mc-section-header">
			<h2><?php esc_html_e( 'Recent Conversion Jobs', 'media-converter' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=media-converter-jobs' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'View All Jobs', 'media-converter' ); ?></a>
		</div>

		<table class="wp-list-table widefat fixed striped mc-table">
			<thead>
				<tr>
					<th width="80"><?php esc_html_e( 'ID', 'media-converter' ); ?></th>
					<th><?php esc_html_e( 'Original File', 'media-converter' ); ?></th>
					<th width="120"><?php esc_html_e( 'Format', 'media-converter' ); ?></th>
					<th width="120"><?php esc_html_e( 'Quality', 'media-converter' ); ?></th>
					<th width="140"><?php esc_html_e( 'Status', 'media-converter' ); ?></th>
					<th width="120"><?php esc_html_e( 'Size', 'media-converter' ); ?></th>
					<th width="180"><?php esc_html_e( 'Date Created', 'media-converter' ); ?></th>
					<th width="120"><?php esc_html_e( 'Actions', 'media-converter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recent_jobs ) ) : ?>
					<tr>
						<td colspan="8" class="mc-empty-cell"><?php esc_html_e( 'No conversion jobs found yet. Start converting on the frontend!', 'media-converter' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $recent_jobs as $job ) : ?>
						<tr id="mc-job-row-<?php echo esc_attr( $job->id ); ?>">
							<td><strong>#<?php echo esc_html( $job->id ); ?></strong></td>
							<td>
								<span class="mc-filename"><?php echo esc_html( $job->original_filename ); ?></span>
								<div class="mc-row-sub">UUID: <?php echo esc_html( substr( $job->job_uuid, 0, 16 ) ); ?>... | <?php echo esc_html( ucfirst( $job->source_type ) ); ?></div>
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
							<td><?php echo size_format( $job->file_size, 2 ); ?></td>
							<td><?php echo esc_html( $job->created_at ); ?></td>
							<td>
								<div class="mc-actions-group">
									<?php if ( 'completed' === $job->status && ! empty( $job->download_token ) ) : ?>
										<a href="<?php echo esc_url( rest_url( 'media-converter/v1/download?token=' . urlencode( $job->download_token ) ) ); ?>" class="button button-small" target="_blank" title="Download">
											<span class="dashicons dashicons-download"></span>
										</a>
									<?php endif; ?>
									<?php if ( 'failed' === $job->status ) : ?>
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
</div>
