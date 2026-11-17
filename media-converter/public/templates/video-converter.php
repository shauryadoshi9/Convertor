<?php
/**
 * MediaConverter Pro - Video Converter Dedicated Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_format = 'mp4';
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">⚡ PRO Video Suite &bull; Unlimited File Size</span>
			<h1 class="mc-page-title">High-Quality MP4 Video Converter</h1>
			<p class="mc-page-subtitle">Transcode videos from YouTube, direct URLs, MKV, WebM, AVI, MOV, FLV and more into pristine MP4 with H.264 video and AAC audio encoding.</p>
		</div>

		<div class="mc-converter-wrapper">
			<?php require MEDIA_CONVERTER_PATH . 'public/templates/converter.php'; ?>
		</div>

		<div class="mc-content-block">
			<h2>Video Transcoding Capabilities</h2>
			<div class="mc-features-grid">
				<div class="mc-feature-box">
					<h4>Aspect Ratio Preservation</h4>
					<p>Our FFmpeg scaling engine automatically computes proportional even-pixel dimensions (divisible by 2) to maintain crystal-clear aspect ratios without distortion.</p>
				</div>
				<div class="mc-feature-box">
					<h4>Stream-Ready Web Optimization</h4>
					<p>All MP4 files are muxed with the <code>-movflags +faststart</code> parameter, relocating the moov atom to the front of the file so users can stream instantly in their browser.</p>
				</div>
				<div class="mc-feature-box">
					<h4>Frame Rate Consistency</h4>
					<p>We preserve original video frame rates up to 60fps with synchronization controls (<code>-vsync 2</code>) to eliminate audio drift and stuttering.</p>
				</div>
				<div class="mc-feature-box">
					<h4>Lossless Pixel Color Formats</h4>
					<p>Uses standard <code>yuv420p</code> planar color profiles, ensuring 100% universal compatibility across Apple iOS, Android, Windows, macOS, and Linux media players.</p>
				</div>
			</div>
		</div>
	</div>
</div>
