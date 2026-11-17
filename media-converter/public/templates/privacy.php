<?php
/**
 * MediaConverter Pro - Privacy Policy Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">Privacy & GDPR</span>
			<h1 class="mc-page-title">Privacy Policy</h1>
			<p class="mc-page-subtitle">Last updated: <?php echo date( 'F j, Y' ); ?> &bull; Committed to zero unauthorized data retention.</p>
		</div>

		<div class="mc-legal-content">
			<h2>1. Overview and Core Privacy Commitment</h2>
			<p>At PrismConvert, we believe utility tools should respect your privacy. We process video and audio files solely for the purpose of executing the conversion you request. We do not sell, inspect, train AI models on, or distribute your media files.</p>

			<h2>2. Automated File Deletion and Retention Windows</h2>
			<p>Uploaded media files and finalized outputs are automatically deleted from our servers via scheduled automated background processes:</p>
			<ul>
				<li><strong>Guest Conversions:</strong> Uploaded source files and generated output files are permanently destroyed within 12 to 24 hours.</li>
				<li><strong>Registered Users:</strong> Files are held for up to 72 hours to allow downloading across devices, after which they are purged.</li>
				<li>Users may immediately trigger manual job and file deletion via the interface.</li>
			</ul>

			<h2>3. IP Address Hashing and Anonymization</h2>
			<p>To defend against automated denial-of-service and brute-force abuse, we track conversion frequency using a cryptographically salted one-way hash (SHA-256) of your IP address. We never store raw IPv4 or IPv6 addresses in our conversion database.</p>

			<h2>4. No Permanent Storage of Personal Information</h2>
			<p>We do not require account registration for basic conversions. For guest conversions, zero personal information (such as name, email, or telephone number) is solicited or recorded.</p>

			<h2>5. Cookies and Local Storage</h2>
			<p>We use browser <code>localStorage</code> strictly for user experience preferences, such as remembering your preferred dark/light theme setting. No third-party behavioral tracking cookies are deployed.</p>
		</div>
	</div>
</div>
