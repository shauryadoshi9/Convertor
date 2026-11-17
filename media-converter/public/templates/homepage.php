<?php
/**
 * MediaConverter Pro - Homepage Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name = get_bloginfo( 'name' );
$home_url  = home_url( '/' );
?>
<div class="mc-wrapper mc-theme-auto" id="mc-page-root">

	<!-- Top Pro Announcement Bar -->
	<div class="mc-pro-announcement">
		<div class="mc-container mc-pro-announcement-inner">
			<span class="mc-pro-pill-glow">⚡ PRO UNLOCKED</span>
			<span class="mc-pro-msg">Unlimited File Size &bull; Dedicated Multi-Core FFmpeg Engine &bull; Lossless 4K/1080p & 320k Audio</span>
			<span class="mc-pro-active-chip">⚡ 100% Free Pro Tier</span>
		</div>
	</div>

	<!-- Header / Navigation Bar -->
	<header class="mc-header">
		<div class="mc-container mc-nav-container">
			<a href="<?php echo esc_url( $home_url ); ?>" class="mc-logo">
				<span class="mc-logo-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
				</span>
				<span class="mc-logo-text">Prism<strong>Convert</strong></span>
				<span class="mc-logo-pro-badge">PRO</span>
			</a>

			<nav class="mc-nav-menu" id="mc-nav-menu">
				<a href="<?php echo esc_url( $home_url ); ?>" class="mc-nav-link active">Home</a>
				<a href="<?php echo esc_url( home_url( '/video-converter/' ) ); ?>" class="mc-nav-link">Video Converter</a>
				<a href="<?php echo esc_url( home_url( '/audio-converter/' ) ); ?>" class="mc-nav-link">Audio Converter</a>
				<a href="#mc-pro-tier" class="mc-nav-link"><span class="mc-nav-highlight">Pro Perks</span></a>
				<a href="#mc-how-it-works" class="mc-nav-link">How It Works</a>
				<a href="#mc-faq" class="mc-nav-link">FAQ</a>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="mc-nav-link">Contact</a>
			</nav>

			<div class="mc-nav-actions">
				<!-- Pro Status Pill -->
				<div class="mc-pro-status-pill" title="Pro Plan Active: Unlimited Conversions">
					<span class="mc-status-dot"></span>
					<span class="mc-status-text">Pro Active</span>
				</div>

				<!-- Dark / Light Mode Switcher -->
				<button type="button" id="mc-theme-toggle" class="mc-icon-btn" title="Toggle Light/Dark Theme" aria-label="Toggle Theme">
					<svg class="mc-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
					<svg class="mc-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
				</button>

				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="mc-btn-nav-outline">
						<span>Dashboard</span>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url() ); ?>" class="mc-btn-nav-outline">
						<span>Sign In</span>
					</a>
				<?php endif; ?>

				<!-- Mobile Hamburger Button -->
				<button type="button" id="mc-mobile-toggle" class="mc-mobile-btn" aria-label="Open menu">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
				</button>
			</div>
		</div>
	</header>

	<!-- Hero Section -->
	<section class="mc-hero-section">
		<div class="mc-hero-bg-glow"></div>
		<div class="mc-container mc-hero-container">
			<div class="mc-hero-content">
				<div class="mc-badge-pill">
					<span class="mc-badge-pulse"></span>
					<span>⚡ Pro Pipeline Active &bull; Multi-Threaded Engine &bull; Zero Limits</span>
				</div>
				<h1 class="mc-hero-title">
					Convert Media Without Limits.<br>
					<span class="mc-gradient-text">Your Way.</span>
				</h1>
				<p class="mc-hero-subtitle">
					Convert supported video and audio files into high-quality formats with lightning-fast async processing. Unlimited file sizes, 4K video, 320 kbps studio audio, and zero queues.
				</p>

				<!-- Pro Capability Tags -->
				<div class="mc-pro-tags-row">
					<span class="mc-pro-tag-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> No File Size Limit</span>
					<span class="mc-pro-tag-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Ultra-Fast Async Engine</span>
					<span class="mc-pro-tag-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 4K & 1080p HD Video</span>
					<span class="mc-pro-tag-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 320 kbps Studio Audio</span>
				</div>
			</div>

			<!-- Main Converter Component -->
			<div class="mc-converter-wrapper">
				<?php require MEDIA_CONVERTER_PATH . 'public/templates/converter.php'; ?>
			</div>

			<!-- Trust Metrics Row -->
			<div class="mc-trust-row">
				<div class="mc-trust-item">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
					<span>SSRF & Malware Protected</span>
				</div>
				<div class="mc-trust-item">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
					<span>H.264 & MP3 LAME Encoders</span>
				</div>
				<div class="mc-trust-item">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
					<span>No Artificial Upscaling</span>
				</div>
				<div class="mc-trust-item">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
					<span>Automated File Purge (24h)</span>
				</div>
			</div>
		</div>
	</section>

	<!-- How It Works Section -->
	<section class="mc-section mc-how-section" id="mc-how-it-works">
		<div class="mc-container">
			<div class="mc-section-header-center">
				<span class="mc-section-sub">Simple 4-Step Process</span>
				<h2 class="mc-section-heading">How PrismConvert Works</h2>
				<p class="mc-section-desc">Experience a frictionless, lightning-fast transcoding workflow engineered for clarity, speed, and privacy.</p>
			</div>

			<div class="mc-steps-grid">
				<div class="mc-step-card">
					<div class="mc-step-num">01</div>
					<div class="mc-step-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
					</div>
					<h3>Submit Source</h3>
					<p>Paste a secure media URL or drag & drop your media file. Supported formats include MP4, MKV, WebM, AVI, WAV, FLAC, and more.</p>
				</div>

				<div class="mc-step-card">
					<div class="mc-step-num">02</div>
					<div class="mc-step-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
					</div>
					<h3>Select Quality</h3>
					<p>Choose MP4 Video (1080p, 720p, 480p) or MP3 Audio (320kbps, 256kbps). Only true source-supported resolutions are presented.</p>
				</div>

				<div class="mc-step-card">
					<div class="mc-step-num">03</div>
					<div class="mc-step-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
					</div>
					<h3>Real-Time Processing</h3>
					<p>Our server processes your media using native FFmpeg. Watch real-time stage progression without fake progress indicators.</p>
				</div>

				<div class="mc-step-card">
					<div class="mc-step-num">04</div>
					<div class="mc-step-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>
					</div>
					<h3>Instant Download</h3>
					<p>Download your converted file via a secure, tokenized URL with HTTP Range support for resumable, fast downloading.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Feature Highlights -->
	<section class="mc-section mc-features-section">
		<div class="mc-container">
			<div class="mc-section-header-center">
				<span class="mc-section-sub">Engineered For Quality</span>
				<h2 class="mc-section-heading">Why Choose PrismConvert</h2>
			</div>

			<div class="mc-features-grid">
				<div class="mc-feature-box">
					<div class="mc-feature-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
					</div>
					<h4>H.264 Video Encoding</h4>
					<p>Outputs universally compatible MP4 video encoded with H.264 and faststart flags for instantaneous web streaming playback.</p>
				</div>

				<div class="mc-feature-box">
					<div class="mc-feature-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
					</div>
					<h4>LAME 320kbps MP3 Audio</h4>
					<p>Extract crystal-clear sound at up to 320kbps with full ID3 metadata tags (Title, Artist, Album) preserved automatically.</p>
				</div>

				<div class="mc-feature-box">
					<div class="mc-feature-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
					</div>
					<h4>Strict SSRF & Privacy Protection</h4>
					<p>Remote URLs are checked against private IP networks, internal cloud metadata, and loopback ranges before any request is made.</p>
				</div>

				<div class="mc-feature-box">
					<div class="mc-feature-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
					</div>
					<h4>No Artificial Upscaling</h4>
					<p>Honest quality choices: we inspect native video height and bitrate so 480p files are never misleadingly upscaled to 1080p.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Pro Tier Showcase Section (Section 9 & Pro Spec) -->
	<section class="mc-section mc-pro-tier-section" id="mc-pro-tier">
		<div class="mc-container">
			<div class="mc-section-header-center">
				<span class="mc-section-sub mc-pro-pill-accent">⚡ Pro Subscription Tier Included</span>
				<h2 class="mc-section-heading">Built Like a Professional SaaS Subscription</h2>
				<p class="mc-section-lead">Experience unlocked throughput with zero artificial caps, dedicated multi-threaded FFmpeg nodes, and lossless studio encoding.</p>
			</div>

			<div class="mc-pro-pricing-grid">
				<!-- Free Plan Comparison -->
				<div class="mc-pro-plan-card mc-free-plan">
					<div class="mc-plan-header">
						<span class="mc-plan-badge">Standard Free</span>
						<h3 class="mc-plan-name">Community</h3>
						<div class="mc-plan-price">
							<span class="mc-price-num">$0</span>
							<span class="mc-price-term">/ forever</span>
						</div>
						<p class="mc-plan-desc">Basic single-file conversions with typical web limitations.</p>
					</div>
					<ul class="mc-plan-features">
						<li class="mc-feat-disabled"><span class="mc-feat-cross">&times;</span> File sizes capped at 250 MB</li>
						<li class="mc-feat-disabled"><span class="mc-feat-cross">&times;</span> Hourly conversion limits</li>
						<li class="mc-feat-enabled"><span class="mc-feat-check">&check;</span> Up to 720p HD resolution</li>
						<li class="mc-feat-enabled"><span class="mc-feat-check">&check;</span> 128 kbps standard audio</li>
						<li class="mc-feat-disabled"><span class="mc-feat-cross">&times;</span> Standard queue wait times</li>
					</ul>
					<div class="mc-plan-action">
						<span class="mc-btn-plan-inactive">Basic Tier</span>
					</div>
				</div>

				<!-- Pro Plan Active Card -->
				<div class="mc-pro-plan-card mc-pro-plan-featured">
					<div class="mc-featured-ribbon">CURRENTLY ACTIVE</div>
					<div class="mc-plan-header">
						<span class="mc-pro-pill-glow">⚡ UNLIMITED PRO</span>
						<h3 class="mc-plan-name">Pro Acceleration</h3>
						<div class="mc-plan-price">
							<span class="mc-price-num">$0</span>
							<span class="mc-price-term">/ All Perks Unlocked</span>
						</div>
						<p class="mc-plan-desc">Maximum conversion speed, zero restrictions, and studio quality.</p>
					</div>
					<ul class="mc-plan-features">
						<li class="mc-feat-pro"><span class="mc-feat-check">⚡</span> <strong>Unlimited File Sizes</strong> (No 250MB/1GB caps &bull; 10GB+ supported)</li>
						<li class="mc-feat-pro"><span class="mc-feat-check">⚡</span> <strong>Unlimited Conversions</strong> (Zero hourly rate limits)</li>
						<li class="mc-feat-pro"><span class="mc-feat-check">⚡</span> <strong>Full 1080p & 4K Ultra-HD</strong> (H.264 & AAC high profile)</li>
						<li class="mc-feat-pro"><span class="mc-feat-check">⚡</span> <strong>320 kbps Studio Master MP3</strong> (With rich ID3 tags & artwork)</li>
						<li class="mc-feat-pro"><span class="mc-feat-check">⚡</span> <strong>Multi-Threaded Async Workers</strong> (Fast detached execution)</li>
						<li class="mc-feat-pro"><span class="mc-feat-check">⚡</span> <strong>Instant Resumable Downloads</strong> (RFC 7233 byte-range streaming)</li>
					</ul>
					<div class="mc-plan-action">
						<div class="mc-pro-active-indicator">
							<span class="mc-pulse-dot"></span>
							<span>PRO PLAN ACTIVE ON THIS INSTANCE</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- FAQ Section -->
	<section class="mc-section mc-faq-section" id="mc-faq">
		<div class="mc-container mc-faq-container">
			<div class="mc-section-header-center">
				<span class="mc-section-sub">Have Questions?</span>
				<h2 class="mc-section-heading">Frequently Asked Questions</h2>
			</div>

			<div class="mc-accordion">
				<div class="mc-accordion-item">
					<button type="button" class="mc-accordion-header">
						<span>Which output formats are supported?</span>
						<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</button>
					<div class="mc-accordion-body">
						<p>PrismConvert supports MP4 video (with H.264 video codec and AAC audio) and MP3 audio (with LAME encoding up to 320 kbps). These formats offer 100% universal compatibility across all modern browsers, smartphones, tablets, TVs, and car audio systems.</p>
					</div>
				</div>

				<div class="mc-accordion-item">
					<button type="button" class="mc-accordion-header">
						<span>How does video quality selection work?</span>
						<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</button>
					<div class="mc-accordion-body">
						<p>We analyze the source video using FFprobe. You can choose from Original Quality, 1080p, 720p, 480p, or 360p. In accordance with ethical standards, options that exceed the native resolution of your source file are disabled so files are never artificially upscaled.</p>
					</div>
				</div>

				<div class="mc-accordion-item">
					<button type="button" class="mc-accordion-header">
						<span>How long are converted files stored on the server?</span>
						<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</button>
					<div class="mc-accordion-body">
						<p>Converted files for guest users are held for 12 to 24 hours, after which an automated hourly cleanup cron permanently purges the files from disk. Registered users enjoy extended 72-hour retention and conversion history.</p>
					</div>
				</div>

				<div class="mc-accordion-item">
					<button type="button" class="mc-accordion-header">
						<span>What are the legal compliance and copyright rules?</span>
						<svg class="mc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</button>
					<div class="mc-accordion-body">
						<p>Users are strictly required to ensure they own or have the explicit authorization, fair use right, or public domain license to convert submitted media. We respect intellectual property and strictly disallow unauthorized downloading of copyrighted streams.</p>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Professional Footer -->
	<footer class="mc-footer">
		<div class="mc-container mc-footer-container">
			<div class="mc-footer-col mc-footer-brand">
				<a href="<?php echo esc_url( $home_url ); ?>" class="mc-logo">
					<span class="mc-logo-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
					</span>
					<span class="mc-logo-text">Prism<strong>Convert</strong></span>
				</a>
				<p class="mc-footer-tagline">High-fidelity, secure media conversion powered by FFmpeg. Convert video and audio legally, seamlessly and fast.</p>
			</div>

			<div class="mc-footer-col">
				<h4>Converters</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/video-converter/' ) ); ?>">Video to MP4</a></li>
					<li><a href="<?php echo esc_url( home_url( '/audio-converter/' ) ); ?>">Video to MP3</a></li>
					<li><a href="<?php echo esc_url( home_url( '/audio-converter/' ) ); ?>">Audio Converter</a></li>
					<li><a href="<?php echo esc_url( home_url( '/video-converter/' ) ); ?>">1080p Transcoder</a></li>
				</ul>
			</div>

			<div class="mc-footer-col">
				<h4>Platform</h4>
				<ul>
					<li><a href="#mc-how-it-works">How It Works</a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About Us</a></li>
					<li><a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">User Dashboard</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact Support</a></li>
				</ul>
			</div>

			<div class="mc-footer-col">
				<h4>Legal & Privacy</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms of Service</a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Privacy Policy</a></li>
					<li><a href="<?php echo esc_url( home_url( '/copyright/' ) ); ?>">Copyright & DMCA</a></li>
					<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a></li>
				</ul>
			</div>
		</div>

		<div class="mc-footer-bottom">
			<div class="mc-container mc-footer-bottom-flex">
				<p>&copy; <?php echo date( 'Y' ); ?> PrismConvert. All rights reserved. Built with WordPress & PHP.</p>
				<p class="mc-footer-notice">This platform is intended solely for converting media you own or have explicit permission to transcode.</p>
			</div>
		</div>
	</footer>

</div>
