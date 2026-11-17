<?php
/**
 * MediaConverter Pro - Interactive Converter Component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_format = isset( $default_format ) ? $default_format : 'all';
?>
<div class="mc-converter-app" id="mc-converter-app" data-default-format="<?php echo esc_attr( $default_format ); ?>">

	<!-- STATE 1: Ingestion Zone (URL or Upload) -->
	<div class="mc-state-panel mc-state-ingest active" id="mc-panel-ingest">
		
		<!-- Input Mode Tabs -->
		<div class="mc-tabs-header">
			<button type="button" class="mc-tab-btn active" data-tab="url">
				<svg class="mc-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
				<span>Convert from URL</span>
			</button>
			<button type="button" class="mc-tab-btn" data-tab="upload">
				<svg class="mc-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
				<span>Upload Media File</span>
			</button>
		</div>

		<!-- Tab Content: URL Input -->
		<div class="mc-tab-pane active" id="mc-pane-url">
			<div class="mc-input-group">
				<div class="mc-url-wrapper">
					<span class="mc-input-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
					</span>
					<input type="url" id="mc-url-input" class="mc-input-field" placeholder="Paste supported media URL (e.g. YouTube oEmbed, Direct HTTPS MP4/MP3)..." autocomplete="off" />
					<button type="button" id="mc-paste-btn" class="mc-btn-addon" title="Paste from clipboard">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
						<span>Paste</span>
					</button>
					<button type="button" id="mc-clear-url-btn" class="mc-btn-clear" title="Clear input" style="display: none;">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>
				<button type="button" id="mc-fetch-url-btn" class="mc-btn-primary">
					<span>Fetch Media</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
				</button>
			</div>
			<div class="mc-input-helper">
				<span class="mc-helper-badge">HTTPS Required</span>
				<span>Protected by strict SSRF filtering. Supports permissible direct video/audio URLs and authorized oEmbed metadata.</span>
			</div>
		</div>

		<!-- Tab Content: File Upload Drag & Drop -->
		<div class="mc-tab-pane" id="mc-pane-upload">
			<div class="mc-dropzone" id="mc-dropzone">
				<input type="file" id="mc-file-input" class="mc-file-hidden" accept="video/*,audio/*,.mkv,.webm,.flv,.wmv,.m4a" />
				<div class="mc-dropzone-content">
					<div class="mc-dropzone-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path><path d="M12 12v9"></path><path d="m16 16-4-4-4 4"></path></svg>
					</div>
					<h3 class="mc-dropzone-title">Drag & drop your video or audio file here</h3>
					<p class="mc-dropzone-sub">or <button type="button" class="mc-link-btn" id="mc-browse-btn">browse your computer</button></p>
					<div class="mc-dropzone-meta">
						<span>Supports MP4, MKV, WebM, AVI, MOV, MP3, WAV, FLAC, OGG & all formats</span>
						<span>File Size Limit: <strong class="mc-pro-badge-mini">⚡ UNLIMITED (Pro Active)</strong></span>
					</div>
				</div>
				<div class="mc-upload-spinner" id="mc-upload-spinner" style="display: none;">
					<div class="mc-spinner"></div>
					<p id="mc-upload-status-text">Uploading and analyzing media streams...</p>
				</div>
			</div>
		</div>

	</div>

	<!-- STATE 2: Configuration & Quality Selection Card -->
	<div class="mc-state-panel mc-state-config" id="mc-panel-config" style="display: none;">
		<div class="mc-media-preview-card">
			<div class="mc-media-thumb-box" id="mc-thumb-box">
				<div class="mc-thumb-placeholder" id="mc-thumb-placeholder">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
				</div>
				<img id="mc-media-thumb" class="mc-media-thumb-img" src="" alt="Media Thumbnail" style="display: none;" />
				<span class="mc-duration-badge" id="mc-media-duration">00:00</span>
			</div>
			<div class="mc-media-info">
				<h3 class="mc-media-title" id="mc-media-title">Media Title</h3>
				<div class="mc-media-details">
					<span id="mc-media-meta-source" class="mc-meta-pill">Direct Upload</span>
					<span id="mc-media-meta-res" class="mc-meta-pill">1920x1080</span>
					<span id="mc-media-meta-codec" class="mc-meta-pill">H.264 / AAC</span>
					<span id="mc-media-meta-size" class="mc-meta-pill">14.5 MB</span>
				</div>
			</div>
		</div>

		<!-- ⚡ 2-HOUR LONG VIDEO OPTIMIZER BANNER (Auto-activated for media > 30 minutes) -->
		<div class="mc-long-video-card" id="mc-long-video-card" style="display: none;">
			<div class="mc-long-video-badge">
				<span class="mc-pulse-dot"></span>
				<strong>⚡ Long Video Detected (<span id="mc-long-video-duration">--:--</span>)</strong>
			</div>
			<p class="mc-long-video-text">
				High-definition 1080p for full-length 2-hour videos can be <strong>~3.5 GB</strong>. Choose an optimized profile below for maximum conversion speed:
			</p>
			<div class="mc-long-video-chips">
				<button type="button" class="mc-opt-chip" id="mc-chip-720p">
					<span class="mc-chip-icon">⚡</span>
					<span><strong>720p HD (~1.3 GB)</strong> — 3x Faster Download</span>
				</button>
				<button type="button" class="mc-opt-chip" id="mc-chip-480p">
					<span class="mc-chip-icon">🚀</span>
					<span><strong>480p SD (~600 MB)</strong> — 6x Faster Download</span>
				</button>
				<button type="button" class="mc-opt-chip" id="mc-chip-mp3">
					<span class="mc-chip-icon">🎵</span>
					<span><strong>MP3 Audio (~120 MB)</strong> — Instant in ~10s</span>
				</button>
				<button type="button" class="mc-opt-chip" id="mc-chip-trim">
					<span class="mc-chip-icon">✂️</span>
					<span><strong>Clip Time Range</strong> — Convert Only Needed Minutes</span>
				</button>
			</div>
		</div>

		<!-- Options Grid -->
		<div class="mc-config-grid">
			
			<!-- Format Selector -->
			<div class="mc-config-col">
				<label class="mc-config-label">
					<span class="mc-label-number">1</span>
					<span>Target Output Format</span>
				</label>
				<div class="mc-format-toggle-group">
					<button type="button" class="mc-format-btn active" data-format="mp4">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
						<div class="mc-format-btn-text">
							<strong>MP4 Video</strong>
							<small>H.264 / AAC Universal</small>
						</div>
					</button>
					<button type="button" class="mc-format-btn" data-format="mp3">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
						<div class="mc-format-btn-text">
							<strong>MP3 Audio</strong>
							<small>LAME High-Fidelity Audio</small>
						</div>
					</button>
				</div>
			</div>

			<!-- Dynamic Quality Selector -->
			<div class="mc-config-col">
				<label class="mc-config-label">
					<span class="mc-label-number">2</span>
					<span>Select Output Quality</span>
				</label>
				
				<!-- Video Quality Options (filtered to match source without upscaling) -->
				<div class="mc-quality-container" id="mc-video-quality-box">
					<select id="mc-video-quality-select" class="mc-select-field">
						<option value="original">Original Quality (Match Source)</option>
						<option value="1080p">1080p Full HD (1920x1080)</option>
						<option value="720p">720p HD (1280x720)</option>
						<option value="480p">480p SD (854x480)</option>
						<option value="360p">360p (640x360)</option>
					</select>
					<p class="mc-quality-note">Qualities exceeding the source resolution are automatically hidden to avoid artificial upscaling.</p>
				</div>

				<!-- Audio Quality Options -->
				<div class="mc-quality-container" id="mc-audio-quality-box" style="display: none;">
					<select id="mc-audio-quality-select" class="mc-select-field">
						<option value="original">Original Audio Quality</option>
						<option value="320k" selected>320 kbps (High Quality Studio)</option>
						<option value="256k">256 kbps (Premium)</option>
						<option value="192k">192 kbps (Standard CD Quality)</option>
						<option value="128k">128 kbps (Voice / Fast)</option>
					</select>
					<p class="mc-quality-note">Extracted with precise LAME MP3 encoding and ID3 metadata preservation.</p>
				</div>
			</div>

		</div>

		<!-- Optional Video Trimming / Clipping (Crucial for 2-hour videos) -->
		<div class="mc-trim-card" id="mc-trim-card">
			<div class="mc-trim-header">
				<label class="mc-trim-toggle-label">
					<input type="checkbox" id="mc-enable-trim-cb" />
					<span class="mc-trim-toggle-text">
						<strong>✂️ Convert Specific Time Range / Clip (Optional)</strong>
						<small>Recommended for 2-hour videos: convert only the segment you need in seconds</small>
					</span>
				</label>
			</div>
			<div class="mc-trim-body" id="mc-trim-body" style="display: none;">
				<div class="mc-trim-row">
					<div class="mc-trim-group">
						<label for="mc-trim-start">Start Time (HH:MM:SS)</label>
						<input type="text" id="mc-trim-start" class="mc-trim-input" placeholder="00:00:00" />
						<small>e.g. 00:15:30</small>
					</div>
					<div class="mc-trim-divider">to</div>
					<div class="mc-trim-group">
						<label for="mc-trim-end">End Time (HH:MM:SS)</label>
						<input type="text" id="mc-trim-end" class="mc-trim-input" placeholder="00:30:00" />
						<small>e.g. 00:45:00 or blank for end</small>
					</div>
				</div>
				<p class="mc-trim-tip">⚡ Fast Trimming: Extracts only the desired minutes directly from the stream without downloading the entire 2-hour file.</p>
			</div>
		</div>

		<!-- Legal Notice & Confirmation Checkbox -->
		<div class="mc-legal-box">
			<label class="mc-legal-label">
				<input type="checkbox" id="mc-legal-checkbox" checked />
				<span>I confirm that this media is legally downloadable or that I possess the necessary rights and permissions to convert and format-shift this content.</span>
			</label>
		</div>

		<!-- Config Action Buttons -->
		<div class="mc-config-actions">
			<button type="button" id="mc-start-convert-btn" class="mc-btn-primary mc-btn-large">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
				<span>Convert Now</span>
			</button>
			<button type="button" id="mc-cancel-config-btn" class="mc-btn-secondary">
				<span>Choose Another File</span>
			</button>
		</div>
	</div>

	<!-- STATE 3: Conversion Progress Card (Section 5 Spec) -->
	<div class="mc-state-panel mc-state-progress" id="mc-panel-progress" style="display: none;">
		<div class="mc-progress-card">
			<div class="mc-progress-header">
				<div class="mc-spinner-pulse"></div>
				<div>
					<h3 class="mc-progress-title">Processing Your Media...</h3>
					<p class="mc-progress-sub" id="mc-progress-eta">Transcoding streams using FFmpeg engine</p>
				</div>
				<span class="mc-progress-percentage" id="mc-progress-pct-display">0%</span>
			</div>

			<!-- Main Progress Bar -->
			<div class="mc-progress-track">
				<div class="mc-progress-fill" id="mc-progress-bar-fill" style="width: 0%;"></div>
			</div>

			<!-- Stage by Stage Checklist -->
			<div class="mc-stages-list">
				<div class="mc-stage-item" id="mc-stage-validation">
					<div class="mc-stage-icon">
						<span class="mc-stage-dot"></span>
					</div>
					<div class="mc-stage-text">
						<strong>Source validation</strong>
						<span class="mc-stage-status">Completed</span>
					</div>
				</div>

				<div class="mc-stage-item" id="mc-stage-processing">
					<div class="mc-stage-icon">
						<span class="mc-stage-dot"></span>
					</div>
					<div class="mc-stage-text">
						<strong>Media processing</strong>
						<span class="mc-stage-status">In progress</span>
					</div>
				</div>

				<div class="mc-stage-item" id="mc-stage-encoding">
					<div class="mc-stage-icon">
						<span class="mc-stage-dot"></span>
					</div>
					<div class="mc-stage-text">
						<strong id="mc-stage-encoding-title">Video transcoding</strong>
						<span class="mc-stage-status">Pending</span>
					</div>
				</div>

				<div class="mc-stage-item" id="mc-stage-finalizing">
					<div class="mc-stage-icon">
						<span class="mc-stage-dot"></span>
					</div>
					<div class="mc-stage-text">
						<strong>Finalizing output</strong>
						<span class="mc-stage-status">Pending</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- STATE 4: Completion & Download Card (Section 5 Spec) -->
	<div class="mc-state-panel mc-state-completed" id="mc-panel-completed" style="display: none;">
		<div class="mc-completed-card">
			<div class="mc-success-icon-wrap">
				<svg class="mc-success-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
			</div>
			
			<h2 class="mc-completed-title">Your file is ready!</h2>
			<p class="mc-completed-sub">Conversion finished successfully with high-fidelity encoding.</p>

			<!-- Output Specs Overview -->
			<div class="mc-completed-specs">
				<div class="mc-spec-item">
					<span class="mc-spec-label">Format</span>
					<strong class="mc-spec-value" id="mc-complete-format">MP4 Video</strong>
				</div>
				<div class="mc-spec-item">
					<span class="mc-spec-label">Quality</span>
					<strong class="mc-spec-value" id="mc-complete-quality">1080p FHD</strong>
				</div>
				<div class="mc-spec-item">
					<span class="mc-spec-label">File Size</span>
					<strong class="mc-spec-value" id="mc-complete-size">18.4 MB</strong>
				</div>
				<div class="mc-spec-item">
					<span class="mc-spec-label">Duration</span>
					<strong class="mc-spec-value" id="mc-complete-duration">03:45</strong>
				</div>
			</div>

			<!-- Download Action Buttons -->
			<div class="mc-completed-actions">
				<a href="#" id="mc-download-btn" class="mc-btn-primary mc-btn-large" download>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
					<span id="mc-download-btn-text">Download File</span>
				</a>
				<button type="button" id="mc-reset-btn" class="mc-btn-secondary">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2v6h6"></path><path d="M21 12A9 9 0 0 0 6 5.3L3 8"></path><path d="M21 22v-6h-6"></path><path d="M3 12a9 9 0 0 0 15 6.7l3-2.7"></path></svg>
					<span>Convert Another File</span>
				</button>
			</div>

			<div class="mc-download-security-note">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
				<span>Secure temporary link. Protected with one-time cryptographic token. Auto-purged after retention window.</span>
			</div>
		</div>
	</div>

	<!-- STATE 5: Error Notification Card -->
	<div class="mc-state-panel mc-state-error" id="mc-panel-error" style="display: none;">
		<div class="mc-error-card">
			<div class="mc-error-icon-wrap">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
			</div>
			<h3 class="mc-error-heading">Conversion Could Not Be Completed</h3>
			<p class="mc-error-desc" id="mc-error-desc-text">An unexpected error occurred during media processing.</p>
			<div class="mc-error-actions">
				<button type="button" id="mc-error-retry-btn" class="mc-btn-primary">
					<span>Try Again</span>
				</button>
			</div>
		</div>
	</div>

</div>
