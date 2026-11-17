<?php
/**
 * MediaConverter Pro - Registered User Dashboard Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
$database     = media_converter()->database;

$user_jobs = $is_logged_in
	? $database->get_jobs( array( 'user_id' => $current_user->ID, 'per_page' => 20, 'orderby' => 'created_at', 'order' => 'DESC' ) )
	: array();
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">Member Area</span>
			<h1 class="mc-page-title">Personal Conversion Dashboard</h1>
			<p class="mc-page-subtitle">Access your 72-hour conversion history, active downloads, and higher performance quotas.</p>
		</div>

		<?php if ( ! $is_logged_in ) : ?>
			<div class="mc-card mc-guest-banner">
				<div class="mc-guest-banner-content">
					<svg class="mc-guest-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
					<h2>You are currently browsing as a Guest</h2>
					<p>Sign in or register an account to unlock 1,000 MB file uploads, extended 72-hour retention, and persistent conversion history.</p>
					<div class="mc-guest-actions">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="mc-btn-primary mc-btn-large">Sign In</a>
						<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="mc-btn-secondary">Create Free Account</a>
					</div>
				</div>
			</div>
		<?php else : ?>
			<!-- User Quota Summary -->
			<div class="mc-stats-grid">
				<div class="mc-stat-card">
					<div class="mc-stat-icon mc-icon-blue">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
					</div>
					<div class="mc-stat-info">
						<span class="mc-stat-label">Account Tier</span>
						<span class="mc-stat-value">Registered Pro</span>
					</div>
				</div>
				<div class="mc-stat-card">
					<div class="mc-stat-icon mc-icon-green">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
					</div>
					<div class="mc-stat-info">
						<span class="mc-stat-label">Upload Limit</span>
						<span class="mc-stat-value">1,000 MB</span>
					</div>
				</div>
				<div class="mc-stat-card">
					<div class="mc-stat-icon mc-icon-purple">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
					</div>
					<div class="mc-stat-info">
						<span class="mc-stat-label">File Retention</span>
						<span class="mc-stat-value">72 Hours</span>
					</div>
				</div>
			</div>

			<!-- User Conversion History Table -->
			<div class="mc-section-card">
				<div class="mc-section-header">
					<h2>Your Conversion History (Last 20)</h2>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mc-btn-primary mc-btn-small">Convert New File</a>
				</div>

				<div class="mc-table-responsive">
					<table class="mc-table">
						<thead>
							<tr>
								<th>Original File</th>
								<th>Output Format</th>
								<th>Quality</th>
								<th>Status</th>
								<th>Size</th>
								<th>Date</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $user_jobs ) ) : ?>
								<tr>
									<td colspan="7" class="mc-empty-cell">You have no conversions yet. Start converting today!</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $user_jobs as $job ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $job->original_filename ); ?></strong></td>
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
										<td><small><?php echo esc_html( $job->created_at ); ?></small></td>
										<td>
											<?php if ( 'completed' === $job->status && ! empty( $job->download_token ) ) : ?>
												<a href="<?php echo esc_url( rest_url( 'media-converter/v1/download?token=' . urlencode( $job->download_token ) ) ); ?>" class="mc-btn-primary mc-btn-small" target="_blank">
													Download
												</a>
											<?php else : ?>
												<span class="mc-meta-text"><?php echo esc_html( ucfirst( $job->status ) ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
