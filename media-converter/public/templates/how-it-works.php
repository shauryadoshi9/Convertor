<?php
/**
 * MediaConverter Pro - How It Works Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">Architecture & Pipeline</span>
			<h1 class="mc-page-title">How Our Media Conversion Works</h1>
			<p class="mc-page-subtitle">A transparent look at the underlying media engineering, security safeguards, and FFmpeg transcoding architecture.</p>
		</div>

		<div class="mc-content-block">
			<div class="mc-timeline">
				<div class="mc-timeline-step">
					<div class="mc-timeline-marker">1</div>
					<div class="mc-timeline-body">
						<h3>Source Validation & Ingestion</h3>
						<p>When you provide a media URL or upload a file, our server executes deep structural validation. Remote URLs are subjected to SSRF (Server-Side Request Forgery) protection, verifying that the DNS hostname never resolves to private network ranges (10.x, 192.168.x, 127.x, 169.254.x). Uploaded files are checked using MIME magic-byte analysis to guarantee only genuine multimedia streams are ingested.</p>
					</div>
				</div>

				<div class="mc-timeline-step">
					<div class="mc-timeline-marker">2</div>
					<div class="mc-timeline-body">
						<h3>Stream Probing with FFprobe</h3>
						<p>Before transcoding begins, our server calls FFprobe to inspect container streams, video codecs, audio sample rates, duration, and frame dimensions. Based on this technical probe, only realistic quality presets are offered. We strictly forbid artificial upscaling: if your input file is 720p, you will not see misleading 1080p options.</p>
					</div>
				</div>

				<div class="mc-timeline-step">
					<div class="mc-timeline-marker">3</div>
					<div class="mc-timeline-body">
						<h3>Asynchronous FFmpeg Processing Engine</h3>
						<p>Transcoding runs inside an isolated, non-blocking subprocess with strict CPU timeout controls. As FFmpeg encodes video frames into H.264 or extracts audio into MP3, progress output is read in real time and reflected on your dashboard. No simulated percentages or arbitrary timers are used.</p>
					</div>
				</div>

				<div class="mc-timeline-step">
					<div class="mc-timeline-marker">4</div>
					<div class="mc-timeline-body">
						<h3>Tokenized Secure Delivery & Automatic Purge</h3>
						<p>Upon conversion completion, a cryptographic HMAC-SHA256 download token is generated. You receive direct streaming downloads with HTTP Range support for resumable transfers. To respect privacy and prevent disk bloat, files are automatically purged by scheduled hourly cron routines.</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
