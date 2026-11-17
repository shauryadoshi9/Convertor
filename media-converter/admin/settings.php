<?php
/**
 * MediaConverter Pro - Admin Settings View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = get_option( 'media_converter_options', array() );

$all_formats = array(
	'mp4'  => 'MP4 (H.264 / AAC Video)',
	'mkv'  => 'MKV (Matroska Video)',
	'webm' => 'WebM (VP8/VP9 / Opus)',
	'avi'  => 'AVI (Audio Video Interleave)',
	'mov'  => 'MOV (Apple QuickTime)',
	'flv'  => 'FLV (Flash Video)',
	'wmv'  => 'WMV (Windows Media Video)',
	'mp3'  => 'MP3 (MPEG Audio Layer 3)',
	'wav'  => 'WAV (Waveform Audio)',
	'aac'  => 'AAC (Advanced Audio Coding)',
	'ogg'  => 'OGG (Ogg Vorbis Audio)',
	'flac' => 'FLAC (Free Lossless Audio)',
	'm4a'  => 'M4A (Apple Audio)',
);

$selected_formats = (array) ( $options['allowed_input_formats'] ?? array( 'mp4', 'mkv', 'webm', 'mov', 'mp3', 'wav', 'ogg' ) );
?>
<div class="wrap mc-admin-wrap">
	<div class="mc-admin-header">
		<div class="mc-admin-header-title">
			<h1><?php esc_html_e( 'Media Converter Settings', 'media-converter' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configure FFmpeg binary paths, upload boundaries, queue concurrency, and security rules.', 'media-converter' ); ?></p>
		</div>
	</div>

	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'media-converter' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="mc-settings-form">
		<?php settings_fields( 'media_converter_settings_group' ); ?>

		<!-- Card 1: FFmpeg Engine -->
		<div class="mc-card">
			<div class="mc-card-header">
				<h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'FFmpeg & FFprobe Configuration', 'media-converter' ); ?></h2>
			</div>
			<div class="mc-card-body">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mc_ffmpeg_path"><?php esc_html_e( 'FFmpeg Executable Path', 'media-converter' ); ?></label></th>
						<td>
							<input type="text" id="mc_ffmpeg_path" name="media_converter_options[ffmpeg_path]" value="<?php echo esc_attr( $options['ffmpeg_path'] ?? '' ); ?>" class="regular-text" />
							<button type="button" class="button mc-test-binaries-btn"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Test Binaries', 'media-converter' ); ?></button>
							<p class="description"><?php esc_html_e( 'Absolute path to ffmpeg binary (e.g. /usr/bin/ffmpeg or C:\\ffmpeg\\bin\\ffmpeg.exe).', 'media-converter' ); ?></p>
							<div id="mc-binary-test-result" class="mc-test-result"></div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_ffprobe_path"><?php esc_html_e( 'FFprobe Executable Path', 'media-converter' ); ?></label></th>
						<td>
							<input type="text" id="mc_ffprobe_path" name="media_converter_options[ffprobe_path]" value="<?php echo esc_attr( $options['ffprobe_path'] ?? '' ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Absolute path to ffprobe binary (used for stream metadata and resolution probing).', 'media-converter' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- Card 2: Processing & Concurrency Limits -->
		<div class="mc-card">
			<div class="mc-card-header">
				<h2><span class="dashicons dashicons-performance"></span> <?php esc_html_e( 'Performance & Queue Limits', 'media-converter' ); ?></h2>
			</div>
			<div class="mc-card-body">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mc_max_upload_size"><?php esc_html_e( 'Max Upload Size (MB)', 'media-converter' ); ?></label></th>
						<td>
							<input type="number" id="mc_max_upload_size" name="media_converter_options[max_upload_size_mb]" value="<?php echo esc_attr( $options['max_upload_size_mb'] ?? 500 ); ?>" min="10" max="5000" class="small-text" /> MB
							<p class="description"><?php esc_html_e( 'Maximum allowable size for uploaded media files. Note: check php.ini upload_max_filesize.', 'media-converter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_max_duration"><?php esc_html_e( 'Max Conversion Timeout (Sec)', 'media-converter' ); ?></label></th>
						<td>
							<input type="number" id="mc_max_duration" name="media_converter_options[max_conversion_duration_sec]" value="<?php echo esc_attr( $options['max_conversion_duration_sec'] ?? 600 ); ?>" min="30" max="3600" class="small-text" /> <?php esc_html_e( 'seconds', 'media-converter' ); ?>
							<p class="description"><?php esc_html_e( 'Maximum time an FFmpeg process may run before being terminated (default 600 seconds = 10 mins).', 'media-converter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_max_concurrent"><?php esc_html_e( 'Max Concurrent Jobs', 'media-converter' ); ?></label></th>
						<td>
							<input type="number" id="mc_max_concurrent" name="media_converter_options[max_concurrent_jobs]" value="<?php echo esc_attr( $options['max_concurrent_jobs'] ?? 3 ); ?>" min="1" max="10" class="small-text" />
							<p class="description"><?php esc_html_e( 'Simultaneous active transcoding jobs allowed to run on the server.', 'media-converter' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- Card 3: Formats & Presets -->
		<div class="mc-card">
			<div class="mc-card-header">
				<h2><span class="dashicons dashicons-video-alt"></span> <?php esc_html_e( 'Supported Formats & Quality Defaults', 'media-converter' ); ?></h2>
			</div>
			<div class="mc-card-body">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed Input Formats', 'media-converter' ); ?></th>
						<td>
							<fieldset class="mc-checkbox-grid">
								<?php foreach ( $all_formats as $ext => $label ) : ?>
									<label class="mc-checkbox-label">
										<input type="checkbox" name="media_converter_options[allowed_input_formats][]" value="<?php echo esc_attr( $ext ); ?>" <?php checked( in_array( $ext, $selected_formats, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_default_video"><?php esc_html_e( 'Default Video Quality', 'media-converter' ); ?></label></th>
						<td>
							<select id="mc_default_video" name="media_converter_options[default_video_quality]">
								<option value="original" <?php selected( ( $options['default_video_quality'] ?? '' ), 'original' ); ?>><?php esc_html_e( 'Original Quality (Match Source)', 'media-converter' ); ?></option>
								<option value="1080p" <?php selected( ( $options['default_video_quality'] ?? '' ), '1080p' ); ?>>1080p (Full HD)</option>
								<option value="720p" <?php selected( ( $options['default_video_quality'] ?? '' ), '720p' ); ?>>720p (HD)</option>
								<option value="480p" <?php selected( ( $options['default_video_quality'] ?? '' ), '480p' ); ?>>480p (SD)</option>
								<option value="360p" <?php selected( ( $options['default_video_quality'] ?? '' ), '360p' ); ?>>360p</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_default_audio"><?php esc_html_e( 'Default Audio Bitrate', 'media-converter' ); ?></label></th>
						<td>
							<select id="mc_default_audio" name="media_converter_options[default_audio_bitrate]">
								<option value="320k" <?php selected( ( $options['default_audio_bitrate'] ?? '' ), '320k' ); ?>>320 kbps (High Quality)</option>
								<option value="256k" <?php selected( ( $options['default_audio_bitrate'] ?? '' ), '256k' ); ?>>256 kbps</option>
								<option value="192k" <?php selected( ( $options['default_audio_bitrate'] ?? '' ), '192k' ); ?>>192 kbps (Standard)</option>
								<option value="128k" <?php selected( ( $options['default_audio_bitrate'] ?? '' ), '128k' ); ?>>128 kbps (Voice / Low)</option>
							</select>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- Card 4: Retention, User Limits & Security -->
		<div class="mc-card">
			<div class="mc-card-header">
				<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Retention, User Tiers & Security', 'media-converter' ); ?></h2>
			</div>
			<div class="mc-card-body">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mc_guest_retention"><?php esc_html_e( 'Guest File Retention (Hours)', 'media-converter' ); ?></label></th>
						<td>
							<input type="number" id="mc_guest_retention" name="media_converter_options[guest_retention_hours]" value="<?php echo esc_attr( $options['guest_retention_hours'] ?? 12 ); ?>" min="1" max="168" class="small-text" /> <?php esc_html_e( 'hours', 'media-converter' ); ?>
							<p class="description"><?php esc_html_e( 'Hours before guest converted files are automatically purged from disk.', 'media-converter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_user_retention"><?php esc_html_e( 'Registered User Retention (Hours)', 'media-converter' ); ?></label></th>
						<td>
							<input type="number" id="mc_user_retention" name="media_converter_options[user_retention_hours]" value="<?php echo esc_attr( $options['user_retention_hours'] ?? 72 ); ?>" min="1" max="720" class="small-text" /> <?php esc_html_e( 'hours', 'media-converter' ); ?>
							<p class="description"><?php esc_html_e( 'Hours before registered user files are purged from disk.', 'media-converter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mc_guest_limit"><?php esc_html_e( 'Guest Hourly Rate Limit', 'media-converter' ); ?></label></th>
						<td>
							<input type="number" id="mc_guest_limit" name="media_converter_options[guest_hourly_limit]" value="<?php echo esc_attr( $options['guest_hourly_limit'] ?? 10 ); ?>" min="1" max="100" class="small-text" /> <?php esc_html_e( 'conversions / hour / IP', 'media-converter' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Security Protections', 'media-converter' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="media_converter_options[enable_rate_limiting]" value="1" <?php checked( ! empty( $options['enable_rate_limiting'] ) ); ?> />
								<?php esc_html_e( 'Enable IP-based rate limiting on conversion endpoints', 'media-converter' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="media_converter_options[enable_legal_notice_checkbox]" value="1" <?php checked( ! empty( $options['enable_legal_notice_checkbox'] ) ); ?> />
								<?php esc_html_e( 'Require explicit legal rights & terms confirmation before conversion starts', 'media-converter' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="media_converter_options[enable_dark_mode]" value="1" <?php checked( ! empty( $options['enable_dark_mode'] ) ); ?> />
								<?php esc_html_e( 'Enable dark/light mode toggle on public converter interface', 'media-converter' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save All Changes', 'media-converter' ) ); ?>
	</form>
</div>
