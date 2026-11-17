<?php
/**
 * MediaConverter Pro - FAQ Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">Knowledge Base</span>
			<h1 class="mc-page-title">Frequently Asked Questions</h1>
			<p class="mc-page-subtitle">Find immediate answers to questions about conversion formats, download speeds, file limits, and legal policies.</p>
		</div>

		<div class="mc-accordion">
			<div class="mc-accordion-item">
				<button type="button" class="mc-accordion-header">
					<span>Do I need to install any software on my computer or phone?</span>
					<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<div class="mc-accordion-body">
					<p>No software or browser extensions are required. All video transcoding and audio extractions are performed in our secure high-speed cloud infrastructure. You only need a modern web browser to upload, monitor, and download your files.</p>
				</div>
			</div>

			<div class="mc-accordion-item">
				<button type="button" class="mc-accordion-header">
					<span>What is the maximum file size I can convert?</span>
					<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<div class="mc-accordion-body">
					<p>Guest users can convert media files up to 250 MB. Registered users with free accounts can convert larger files up to 1,000 MB (1 GB) with extended retention windows and full conversion dashboard history.</p>
				</div>
			</div>

			<div class="mc-accordion-item">
				<button type="button" class="mc-accordion-header">
					<span>Why does the quality dropdown not show 1080p for my video?</span>
					<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<div class="mc-accordion-body">
					<p>Our platform adheres to strict engineering honesty: we never artificially upscale low-resolution files (e.g., stretching a 480p file into 1080p). Upscaling only bloats file size without adding real image detail. As a result, our interface only displays quality options supported by the original stream.</p>
				</div>
			</div>

			<div class="mc-accordion-item">
				<button type="button" class="mc-accordion-header">
					<span>Are my uploaded and converted files private?</span>
					<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<div class="mc-accordion-body">
					<p>Yes, completely. Files are stored with randomized UUID identifiers inside non-browsable, protected directories blocked by access control policies. Download links are secured with cryptographic HMAC tokens. Unclaimed or processed files are permanently purged on a rolling hourly cron.</p>
				</div>
			</div>

			<div class="mc-accordion-item">
				<button type="button" class="mc-accordion-header">
					<span>Why was my submitted URL rejected?</span>
					<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<div class="mc-accordion-body">
					<p>URLs are rejected if they use insecure HTTP (only HTTPS is permitted), if they point to an invalid hostname, or if they resolve to private or internal network IP addresses (SSRF defense). If converting from video platforms, only authorized metadata and permissible media sources can be converted.</p>
				</div>
			</div>
		</div>
	</div>
</div>
