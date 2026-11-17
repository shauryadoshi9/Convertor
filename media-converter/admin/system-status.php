<?php
/**
 * MediaConverter Pro - System Status & Environment Diagnostics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$php_version = phpversion();
$php_min_ok  = version_compare( $php_version, '8.2.0', '>=' );

$ffmpeg_check  = $ffmpeg->check_ffmpeg();
$ffprobe_check = $ffmpeg->check_ffprobe();

$required_extensions = array(
	'fileinfo' => 'MIME type identification and security validation',
	'curl'     => 'Remote media download and oEmbed querying',
	'mbstring' => 'Multi-byte string sanitization and international characters',
	'gd'       => 'Thumbnail creation and image processing',
	'openssl'  => 'HTTPS verification and cryptographic security tokens',
	'mysqli'   => 'WordPress database engine connection',
);

$storage_writable = is_writable( MEDIA_CONVERTER_STORAGE_DIR );
$temp_writable    = is_writable( MEDIA_CONVERTER_TEMP_DIR );
$output_writable  = is_writable( MEDIA_CONVERTER_OUTPUT_DIR );

$free_bytes = @disk_free_space( MEDIA_CONVERTER_STORAGE_DIR );
$total_bytes = @disk_total_space( MEDIA_CONVERTER_STORAGE_DIR );

$upload_max_filesize = ini_get( 'upload_max_filesize' );
$post_max_size       = ini_get( 'post_max_size' );
$memory_limit        = ini_get( 'memory_limit' );
$max_exec_time       = ini_get( 'max_execution_time' );
$proc_open_available = function_exists( 'proc_open' ) && ! in_array( 'proc_open', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ), true );
?>
<div class="wrap mc-admin-wrap">
	<div class="mc-admin-header">
		<div class="mc-admin-header-title">
			<h1><?php esc_html_e( 'System Status & Environment Diagnostics', 'media-converter' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Comprehensive technical assessment of server capabilities, media binaries, and storage quotas.', 'media-converter' ); ?></p>
		</div>
	</div>

	<!-- Core Engine Diagnostics -->
	<div class="mc-card">
		<div class="mc-card-header">
			<h2><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Media Processing Engines', 'media-converter' ); ?></h2>
		</div>
		<div class="mc-card-body">
			<table class="widefat striped mc-status-table">
				<tbody>
					<tr>
						<th width="260">FFmpeg Executable</th>
						<td>
							<?php if ( $ffmpeg_check['available'] ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Available</span>
								<code><?php echo esc_html( $ffmpeg->get_ffmpeg_path() ); ?></code>
								<div class="mc-meta-text"><?php echo esc_html( $ffmpeg_check['version'] ); ?></div>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Missing / Inaccessible</span>
								<div class="mc-error-text"><?php echo esc_html( $ffmpeg_check['error'] ); ?></div>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>FFprobe Executable</th>
						<td>
							<?php if ( $ffprobe_check['available'] ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Available</span>
								<code><?php echo esc_html( $ffmpeg->get_ffprobe_path() ); ?></code>
								<div class="mc-meta-text"><?php echo esc_html( $ffprobe_check['version'] ); ?></div>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Missing / Inaccessible</span>
								<div class="mc-error-text"><?php echo esc_html( $ffprobe_check['error'] ); ?></div>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>PHP <code>proc_open</code> Execution</th>
						<td>
							<?php if ( $proc_open_available ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Enabled</span>
								<span class="mc-meta-text">Required to run FFmpeg background conversion subprocesses.</span>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Disabled</span>
								<span class="mc-error-text">proc_open is listed in disable_functions in php.ini. FFmpeg transcoding requires proc_open.</span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Server & PHP Configuration -->
	<div class="mc-card">
		<div class="mc-card-header">
			<h2><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'Server Environment & PHP Directives', 'media-converter' ); ?></h2>
		</div>
		<div class="mc-card-body">
			<table class="widefat striped mc-status-table">
				<tbody>
					<tr>
						<th width="260">PHP Version</th>
						<td>
							<?php if ( $php_min_ok ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> v<?php echo esc_html( $php_version ); ?> (>= 8.2 Supported)</span>
							<?php else : ?>
								<span class="mc-badge mc-badge-warn"><span class="dashicons dashicons-warning"></span> v<?php echo esc_html( $php_version ); ?> (PHP 8.2+ recommended)</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>WordPress Version</th>
						<td>
							<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> WordPress <?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
						</td>
					</tr>
					<tr>
						<th>Database Connection</th>
						<td>
							<?php if ( ! empty( $wpdb->dbh ) ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Connected</span>
								<span class="mc-meta-text">Server: <?php echo esc_html( $wpdb->db_version() ); ?></span>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Disconnected</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>PHP <code>upload_max_filesize</code></th>
						<td>
							<code><?php echo esc_html( $upload_max_filesize ); ?></code>
						</td>
					</tr>
					<tr>
						<th>PHP <code>post_max_size</code></th>
						<td>
							<code><?php echo esc_html( $post_max_size ); ?></code>
						</td>
					</tr>
					<tr>
						<th>PHP <code>memory_limit</code></th>
						<td>
							<code><?php echo esc_html( $memory_limit ); ?></code>
						</td>
					</tr>
					<tr>
						<th>PHP <code>max_execution_time</code></th>
						<td>
							<code><?php echo esc_html( $max_exec_time ); ?>s</code>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- PHP Extensions -->
	<div class="mc-card">
		<div class="mc-card-header">
			<h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'Required PHP Extensions', 'media-converter' ); ?></h2>
		</div>
		<div class="mc-card-body">
			<table class="widefat striped mc-status-table">
				<thead>
					<tr>
						<th width="200">Extension</th>
						<th width="150">Status</th>
						<th>Purpose</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $required_extensions as $ext => $purpose ) : ?>
						<?php $loaded = extension_loaded( $ext ); ?>
						<tr>
							<td><strong><?php echo esc_html( $ext ); ?></strong></td>
							<td>
								<?php if ( $loaded ) : ?>
									<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Loaded</span>
								<?php else : ?>
									<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Missing</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $purpose ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Storage & Directories -->
	<div class="mc-card">
		<div class="mc-card-header">
			<h2><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Storage Permissions & Disk Space', 'media-converter' ); ?></h2>
		</div>
		<div class="mc-card-body">
			<table class="widefat striped mc-status-table">
				<tbody>
					<tr>
						<th width="260">Storage Base Directory</th>
						<td>
							<?php if ( $storage_writable ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Writable</span>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Read-Only</span>
							<?php endif; ?>
							<code><?php echo esc_html( MEDIA_CONVERTER_STORAGE_DIR ); ?></code>
						</td>
					</tr>
					<tr>
						<th>Temp Ingestion Directory</th>
						<td>
							<?php if ( $temp_writable ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Writable</span>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Read-Only</span>
							<?php endif; ?>
							<code><?php echo esc_html( MEDIA_CONVERTER_TEMP_DIR ); ?></code>
						</td>
					</tr>
					<tr>
						<th>Output Final Directory</th>
						<td>
							<?php if ( $output_writable ) : ?>
								<span class="mc-badge mc-badge-pass"><span class="dashicons dashicons-yes-alt"></span> Writable</span>
							<?php else : ?>
								<span class="mc-badge mc-badge-fail"><span class="dashicons dashicons-dismiss"></span> Read-Only</span>
							<?php endif; ?>
							<code><?php echo esc_html( MEDIA_CONVERTER_OUTPUT_DIR ); ?></code>
						</td>
					</tr>
					<tr>
						<th>Available Free Disk Space</th>
						<td>
							<strong><?php echo size_format( $free_bytes, 2 ); ?></strong>
							<?php if ( $total_bytes > 0 ) : ?>
								<span class="mc-meta-text">(<?php echo size_format( $total_bytes, 2 ); ?> total)</span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
