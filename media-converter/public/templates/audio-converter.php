<?php
/**
 * MediaConverter Pro - Audio Converter Dedicated Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_format = 'mp3';
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">⚡ PRO Audio Suite &bull; Studio 320 kbps</span>
			<h1 class="mc-page-title">High-Fidelity MP3 Audio Converter</h1>
			<p class="mc-page-subtitle">Extract audio from any video or convert audio files into studio-quality MP3 with customizable bitrates up to 320 kbps. Unlimited conversions & file sizes.</p>
		</div>

		<div class="mc-converter-wrapper">
			<?php require MEDIA_CONVERTER_PATH . 'public/templates/converter.php'; ?>
		</div>

		<div class="mc-content-block">
			<h2>High-Definition Audio Transcoding</h2>
			<div class="mc-features-grid">
				<div class="mc-feature-box">
					<h4>Genuine 320 kbps Bitrate</h4>
					<p>Encoded with the industry standard <code>libmp3lame</code> codec for maximum dynamic range, clarity, and deep acoustic response.</p>
				</div>
				<div class="mc-feature-box">
					<h4>ID3v2 Metadata Preservation</h4>
					<p>Embedded song title, artist, album, and year tags are preserved and written into the ID3v2 container so music players display track details cleanly.</p>
				</div>
				<div class="mc-feature-box">
					<h4>44.1 kHz Studio Sampling</h4>
					<p>High sample rate audio filters preserve acoustic frequencies up to 20kHz, delivering rich audio playback on high-end headphones and speakers.</p>
				</div>
				<div class="mc-feature-box">
					<h4>Comprehensive Audio Extraction</h4>
					<p>Extract soundtrack audio directly from MP4, MKV, WebM, MOV, and AVI containers without degrading quality.</p>
				</div>
			</div>
		</div>
	</div>
</div>
