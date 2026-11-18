/**
 * MediaConverter Pro (PrismConvert) - Frontend Client Engine
 * Handles Drag & Drop, URL Validation, Real-Time Progress, and Theme Switching
 */

(function() {
	'use strict';

	// Configuration fallback.
	const config = window.mcConfig || {
		restUrl: '/wp-json/media-converter/v1',
		nonce: '',
		isLoggedIn: false,
		maxUploadMb: 250,
		maxUploadBytes: 250 * 1024 * 1024,
		allowedFormats: ['mp4', 'mp3', 'wav', 'webm', 'mov', 'mkv', 'avi'],
		enableLegalNotice: true,
		enableDarkMode: true
	};

	// Application State.
	const state = {
		sourceType: 'url', // 'url' or 'upload'
		tempId: null,
		uploadedFile: null,
		url: '',
		probeData: null,
		selectedFormat: 'mp4',
		selectedQuality: 'original',
		jobUuid: null,
		pollInterval: null
	};

	// DOM Elements.
	let elements = {};

	document.addEventListener('DOMContentLoaded', () => {
		cacheElements();
		initTheme();
		initTabs();
		initClipboardPaste();
		initDropzone();
		initFormatToggles();
		initAccordion();
		initMobileMenu();
		bindActionEvents();

		// Check if URL parameter preselects format.
		const appEl = document.getElementById('mc-converter-app');
		if (appEl && appEl.dataset.defaultFormat && appEl.dataset.defaultFormat !== 'all') {
			setFormat(appEl.dataset.defaultFormat);
		}
	});

	function cacheElements() {
		elements = {
			root: document.getElementById('mc-page-root') || document.body,
			app: document.getElementById('mc-converter-app'),
			themeToggle: document.getElementById('mc-theme-toggle'),
			sunIcon: document.querySelector('.mc-icon-sun'),
			moonIcon: document.querySelector('.mc-icon-moon'),
			mobileToggle: document.getElementById('mc-mobile-toggle'),
			navMenu: document.getElementById('mc-nav-menu'),

			// State Panels
			panelIngest: document.getElementById('mc-panel-ingest'),
			panelConfig: document.getElementById('mc-panel-config'),
			panelProgress: document.getElementById('mc-panel-progress'),
			panelCompleted: document.getElementById('mc-panel-completed'),
			panelError: document.getElementById('mc-panel-error'),
			panelPlaylistConfig: document.getElementById('mc-panel-playlist-config'),
			panelPlaylistProgress: document.getElementById('mc-panel-playlist-progress'),
			panelPlaylistCompleted: document.getElementById('mc-panel-playlist-completed'),

			// Inputs
			urlInput: document.getElementById('mc-url-input'),
			pasteBtn: document.getElementById('mc-paste-btn'),
			clearUrlBtn: document.getElementById('mc-clear-url-btn'),
			fetchUrlBtn: document.getElementById('mc-fetch-url-btn'),
			fileInput: document.getElementById('mc-file-input'),
			dropzone: document.getElementById('mc-dropzone'),
			browseBtn: document.getElementById('mc-browse-btn'),
			uploadSpinner: document.getElementById('mc-upload-spinner'),
			uploadStatusText: document.getElementById('mc-upload-status-text'),

			// Config Elements
			thumbBox: document.getElementById('mc-thumb-box'),
			thumbPlaceholder: document.getElementById('mc-thumb-placeholder'),
			mediaThumb: document.getElementById('mc-media-thumb'),
			mediaDuration: document.getElementById('mc-media-duration'),
			mediaTitle: document.getElementById('mc-media-title'),
			metaSource: document.getElementById('mc-media-meta-source'),
			metaRes: document.getElementById('mc-media-meta-res'),
			metaCodec: document.getElementById('mc-media-meta-codec'),
			metaSize: document.getElementById('mc-media-meta-size'),

			formatBtns: document.querySelectorAll('.mc-format-btn'),
			videoQualityBox: document.getElementById('mc-video-quality-box'),
			videoQualitySelect: document.getElementById('mc-video-quality-select'),
			audioQualityBox: document.getElementById('mc-audio-quality-box'),
			audioQualitySelect: document.getElementById('mc-audio-quality-select'),
			legalCheckbox: document.getElementById('mc-legal-checkbox'),
			startConvertBtn: document.getElementById('mc-start-convert-btn'),
			cancelConfigBtn: document.getElementById('mc-cancel-config-btn'),

			// Long Video & Trimming Elements
			longVideoCard: document.getElementById('mc-long-video-card'),
			longVideoDuration: document.getElementById('mc-long-video-duration'),
			chip720p: document.getElementById('mc-chip-720p'),
			chip480p: document.getElementById('mc-chip-480p'),
			chipMp3: document.getElementById('mc-chip-mp3'),
			chipTrim: document.getElementById('mc-chip-trim'),
			trimCard: document.getElementById('mc-trim-card'),
			enableTrimCb: document.getElementById('mc-enable-trim-cb'),
			trimBody: document.getElementById('mc-trim-body'),
			trimStart: document.getElementById('mc-trim-start'),
			trimEnd: document.getElementById('mc-trim-end'),

			// Progress Elements
			progressTitle: document.querySelector('.mc-progress-title'),
			progressEta: document.getElementById('mc-progress-eta'),
			pctDisplay: document.getElementById('mc-progress-pct-display'),
			progressBarFill: document.getElementById('mc-progress-bar-fill'),
			stageValidation: document.getElementById('mc-stage-validation'),
			stageProcessing: document.getElementById('mc-stage-processing'),
			stageEncoding: document.getElementById('mc-stage-encoding'),
			stageEncodingTitle: document.getElementById('mc-stage-encoding-title'),
			stageFinalizing: document.getElementById('mc-stage-finalizing'),

			// Completion Elements
			completeFormat: document.getElementById('mc-complete-format'),
			completeQuality: document.getElementById('mc-complete-quality'),
			completeSize: document.getElementById('mc-complete-size'),
			completeDuration: document.getElementById('mc-complete-duration'),
			downloadBtn: document.getElementById('mc-download-btn'),
			downloadBtnText: document.getElementById('mc-download-btn-text'),
			resetBtn: document.getElementById('mc-reset-btn'),

			// Error Elements
			errorDescText: document.getElementById('mc-error-desc-text'),
			errorRetryBtn: document.getElementById('mc-error-retry-btn')
		};
	}

	/* -------------------------------------------------------------
	   Theme Switcher (Dark / Light)
	------------------------------------------------------------- */
	function initTheme() {
		if (!elements.themeToggle) return;

		const savedTheme = localStorage.getItem('mc_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
		applyTheme(savedTheme);

		elements.themeToggle.addEventListener('click', () => {
			const current = document.documentElement.getAttribute('data-mc-theme') || 'light';
			const next = current === 'dark' ? 'light' : 'dark';
			applyTheme(next);
		});
	}

	function applyTheme(theme) {
		document.documentElement.setAttribute('data-mc-theme', theme);
		localStorage.setItem('mc_theme', theme);

		if (elements.sunIcon && elements.moonIcon) {
			if (theme === 'dark') {
				elements.sunIcon.style.display = 'none';
				elements.moonIcon.style.display = 'block';
				document.body.classList.add('mc-dark-mode');
			} else {
				elements.sunIcon.style.display = 'block';
				elements.moonIcon.style.display = 'none';
				document.body.classList.remove('mc-dark-mode');
			}
		}
	}

	/* -------------------------------------------------------------
	   Tab Switcher (URL vs Upload)
	------------------------------------------------------------- */
	function initTabs() {
		const tabBtns = document.querySelectorAll('.mc-tab-btn');
		tabBtns.forEach(btn => {
			btn.addEventListener('click', () => {
				tabBtns.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');

				const tab = btn.dataset.tab;
				state.sourceType = tab;

				document.querySelectorAll('.mc-tab-pane').forEach(p => p.classList.remove('active'));
				const activePane = document.getElementById('mc-pane-' + tab);
				if (activePane) activePane.classList.add('active');
			});
		});
	}

	/* -------------------------------------------------------------
	   Clipboard Paste & URL Input Actions
	------------------------------------------------------------- */
	function initClipboardPaste() {
		if (elements.pasteBtn && elements.urlInput) {
			elements.pasteBtn.addEventListener('click', async () => {
				try {
					const text = await navigator.clipboard.readText();
					if (text) {
						elements.urlInput.value = text.trim();
						updateClearButtonVisibility();
						elements.urlInput.focus();
					}
				} catch (err) {
					elements.urlInput.focus();
				}
			});

			elements.urlInput.addEventListener('input', updateClearButtonVisibility);

			if (elements.clearUrlBtn) {
				elements.clearUrlBtn.addEventListener('click', () => {
					elements.urlInput.value = '';
					updateClearButtonVisibility();
					elements.urlInput.focus();
				});
			}

			elements.urlInput.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					fetchUrlSource();
				}
			});
		}
	}

	function updateClearButtonVisibility() {
		if (!elements.clearUrlBtn || !elements.urlInput) return;
		elements.clearUrlBtn.style.display = elements.urlInput.value.length > 0 ? 'block' : 'none';
	}

	/* -------------------------------------------------------------
	   File Drag & Drop Upload
	------------------------------------------------------------- */
	function initDropzone() {
		if (!elements.dropzone || !elements.fileInput) return;

		if (elements.browseBtn) {
			elements.browseBtn.addEventListener('click', (e) => {
				e.preventDefault();
				elements.fileInput.click();
			});
		}

		elements.dropzone.addEventListener('click', (e) => {
			if (e.target !== elements.browseBtn) {
				elements.fileInput.click();
			}
		});

		['dragenter', 'dragover'].forEach(event => {
			elements.dropzone.addEventListener(event, (e) => {
				e.preventDefault();
				e.stopPropagation();
				elements.dropzone.classList.add('dragover');
			});
		});

		['dragleave', 'drop'].forEach(event => {
			elements.dropzone.addEventListener(event, (e) => {
				e.preventDefault();
				e.stopPropagation();
				elements.dropzone.classList.remove('dragover');
			});
		});

		elements.dropzone.addEventListener('drop', (e) => {
			const files = e.dataTransfer.files;
			if (files.length > 0) {
				handleFileSelection(files[0]);
			}
		});

		elements.fileInput.addEventListener('change', (e) => {
			if (e.target.files.length > 0) {
				handleFileSelection(e.target.files[0]);
			}
		});
	}

	function handleFileSelection(file) {
		if (!file) return;

		// Client size check.
		if (file.size > config.maxUploadBytes) {
			showError(`File size exceeds maximum allowed limit of ${config.maxUploadMb} MB.`);
			return;
		}

		state.sourceType = 'upload';
		state.uploadedFile = file;

		uploadAndProbeFile(file);
	}

	async function uploadAndProbeFile(file) {
		showUploadLoading(true, 'Uploading file and analyzing multimedia streams...');

		const formData = new FormData();
		formData.append('file', file);

		try {
			const res = await fetch(`${config.restUrl}/upload`, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': config.nonce
				},
				body: formData
			});

			const data = await res.json();
			showUploadLoading(false);

			if (!res.ok || !data.success) {
				showError(data.message || 'File upload verification failed.');
				return;
			}

			state.tempId = data.temp_id;
			state.probeData = data.probe;

			// Populate Preview Card.
			populatePreview({
				title: data.filename,
				source: 'Uploaded File',
				duration: data.probe.duration_formatted || '00:00',
				size: formatBytes(data.size),
				resolution: data.probe.resolution || 'Direct Media',
				codec: (data.probe.video_codec !== 'none') ? `${data.probe.video_codec} / ${data.probe.audio_codec}` : data.probe.audio_codec,
				thumbnail: null,
				availableVideoQualities: data.available_video_qualities || ['original'],
				availableAudioQualities: data.available_audio_qualities || ['original', '320k', '256k', '192k', '128k']
			});

			switchPanel('config');
		} catch (err) {
			showUploadLoading(false);
			showError('Network error while uploading file. Please verify server connection.');
		}
	}

	function showUploadLoading(show, text) {
		if (!elements.uploadSpinner || !elements.dropzone) return;
		const dropContent = elements.dropzone.querySelector('.mc-dropzone-content');
		if (show) {
			if (dropContent) dropContent.style.display = 'none';
			elements.uploadSpinner.style.display = 'block';
			if (elements.uploadStatusText && text) elements.uploadStatusText.textContent = text;
		} else {
			if (dropContent) dropContent.style.display = 'block';
			elements.uploadSpinner.style.display = 'none';
		}
	}

	/* -------------------------------------------------------------
	   URL Fetch & Validate
	------------------------------------------------------------- */
	async function fetchUrlSource() {
		const url = elements.urlInput ? elements.urlInput.value.trim() : '';
		if (!url) {
			alert('Please enter a media URL.');
			return;
		}

		if (!url.startsWith('https://')) {
			alert('Security requirement: URL must use secure HTTPS protocol.');
			return;
		}

		state.sourceType = 'url';
		state.url = url;

		elements.fetchUrlBtn.disabled = true;
		elements.fetchUrlBtn.innerHTML = '<span>Verifying...</span>';

		try {
			const res = await fetch(`${config.restUrl}/validate-url`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify({ url: url })
			});

			const data = await res.json();
			elements.fetchUrlBtn.disabled = false;
			elements.fetchUrlBtn.innerHTML = '<span>Fetch Media</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';

			if (!res.ok || !data.success) {
				showError(data.message || 'The submitted URL could not be validated or is not supported.');
				return;
			}

			const provider = data.provider;

			if (provider && (provider.is_playlist || provider.type === 'playlist')) {
				showPlaylistConfig(provider);
				return;
			}

			populatePreview({
				title: provider.title || 'Remote Media',
				source: (provider.type === 'youtube') ? 'YouTube Video' : (provider.type === 'vimeo' ? 'Vimeo' : 'Direct HTTPS Stream'),
				duration: provider.duration || '--:--',
				durationSeconds: provider.duration_seconds || 0,
				size: provider.content_length ? formatBytes(provider.content_length) : 'Remote Stream',
				resolution: (provider.height ? `${provider.height}p HD` : 'Standard HD'),
				codec: provider.content_type || 'H.264 / AAC',
				thumbnail: provider.thumbnail || null,
				availableVideoQualities: provider.available_video_qualities || ['original', '1080p', '720p', '480p', '360p'],
				availableAudioQualities: ['original', '320k', '256k', '192k', '128k']
			});

			switchPanel('config');
		} catch (err) {
			elements.fetchUrlBtn.disabled = false;
			elements.fetchUrlBtn.innerHTML = '<span>Fetch Media</span>';
			showError('Network error while validating URL.');
		}
	}

	/* -------------------------------------------------------------
	   Preview & Dynamic Quality Setup
	------------------------------------------------------------- */
	function parseDurationStringToSeconds(str) {
		if (!str || typeof str !== 'string') return 0;
		const parts = str.split(':').map(Number);
		if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
		if (parts.length === 2) return parts[0] * 60 + parts[1];
		return 0;
	}

	function populatePreview(data) {
		if (elements.mediaTitle) elements.mediaTitle.textContent = data.title;
		if (elements.metaSource) elements.metaSource.textContent = data.source;
		if (elements.metaRes) elements.metaRes.textContent = data.resolution;
		if (elements.metaCodec) elements.metaCodec.textContent = data.codec;
		if (elements.metaSize) elements.metaSize.textContent = data.size;
		if (elements.mediaDuration) elements.mediaDuration.textContent = data.duration;

		if (data.thumbnail && elements.mediaThumb) {
			elements.mediaThumb.src = data.thumbnail;
			elements.mediaThumb.style.display = 'block';
			if (elements.thumbPlaceholder) elements.thumbPlaceholder.style.display = 'none';
		} else {
			if (elements.mediaThumb) elements.mediaThumb.style.display = 'none';
			if (elements.thumbPlaceholder) elements.thumbPlaceholder.style.display = 'block';
		}

		// Calculate total duration in seconds
		const durationSec = data.durationSeconds || (data.probe ? data.probe.duration : parseDurationStringToSeconds(data.duration));

		// Long Video Detection (>= 30 mins / 1800s)
		if (durationSec >= 1800) {
			if (elements.longVideoCard) {
				elements.longVideoCard.style.display = 'block';
				if (elements.longVideoDuration) elements.longVideoDuration.textContent = data.duration;
			}
		} else {
			if (elements.longVideoCard) elements.longVideoCard.style.display = 'none';
		}

		// Filter video qualities with dynamic estimated file size tags
		if (elements.videoQualitySelect && data.availableVideoQualities) {
			elements.videoQualitySelect.innerHTML = '';
			const qualityMeta = {
				'original': { label: 'Original Quality (Match Source)', factor: 0.40 },
				'1080p':    { label: '1080p Full HD (1920x1080)', factor: 0.45 },
				'720p':     { label: '720p HD (1280x720) — ⚡ Recommended (3x Faster)', factor: 0.20 },
				'480p':     { label: '480p SD (854x480) — ⚡ Fast Download (6x Faster)', factor: 0.08 },
				'360p':     { label: '360p (640x360) — ⚡ Ultra Fast', factor: 0.05 }
			};

			data.availableVideoQualities.forEach(q => {
				const opt = document.createElement('option');
				opt.value = q;
				const meta = qualityMeta[q] || { label: q, factor: 0.25 };
				let text = meta.label;
				if (durationSec > 0) {
					const estMb = Math.round(durationSec * meta.factor);
					const estStr = (estMb >= 1024) ? (estMb / 1024).toFixed(1) + ' GB' : estMb + ' MB';
					text = `${meta.label} [~${estStr}]`;
				}
				opt.textContent = text;
				elements.videoQualitySelect.appendChild(opt);
			});
		}

		// Update audio options with estimated sizes
		if (elements.audioQualitySelect && durationSec > 0) {
			const estMp3Mb = Math.max(5, Math.round(durationSec * 0.025));
			const mp3Opts = elements.audioQualitySelect.querySelectorAll('option');
			mp3Opts.forEach(opt => {
				if (!opt.dataset.origText) opt.dataset.origText = opt.textContent;
				opt.textContent = `${opt.dataset.origText} [~${estMp3Mb} MB — Instant in ~10s]`;
			});
		}

		// Hook up Quick Action Chips for long videos
		if (elements.chip720p) {
			elements.chip720p.onclick = () => {
				setFormat('mp4');
				if (elements.videoQualitySelect) elements.videoQualitySelect.value = '720p';
			};
		}
		if (elements.chip480p) {
			elements.chip480p.onclick = () => {
				setFormat('mp4');
				if (elements.videoQualitySelect) elements.videoQualitySelect.value = '480p';
			};
		}
		if (elements.chipMp3) {
			elements.chipMp3.onclick = () => {
				setFormat('mp3');
			};
		}
		if (elements.chipTrim) {
			elements.chipTrim.onclick = () => {
				if (elements.enableTrimCb) {
					elements.enableTrimCb.checked = true;
					if (elements.trimBody) elements.trimBody.style.display = 'block';
					if (elements.trimStart) elements.trimStart.focus();
				}
			};
		}
		if (elements.enableTrimCb) {
			elements.enableTrimCb.onchange = () => {
				if (elements.trimBody) {
					elements.trimBody.style.display = elements.enableTrimCb.checked ? 'block' : 'none';
				}
			};
		}
	}

	/* -------------------------------------------------------------
	   Format Toggles (MP4 Video vs MP3 Audio)
	------------------------------------------------------------- */
	function initFormatToggles() {
		elements.formatBtns.forEach(btn => {
			btn.addEventListener('click', () => {
				const format = btn.dataset.format;
				setFormat(format);
			});
		});
	}

	function setFormat(format) {
		state.selectedFormat = format;

		elements.formatBtns.forEach(b => {
			b.classList.toggle('active', b.dataset.format === format);
		});

		if (format === 'mp3') {
			if (elements.videoQualityBox) elements.videoQualityBox.style.display = 'none';
			if (elements.audioQualityBox) elements.audioQualityBox.style.display = 'block';
			if (elements.stageEncodingTitle) elements.stageEncodingTitle.textContent = 'MP3 audio encoding';
		} else {
			if (elements.videoQualityBox) elements.videoQualityBox.style.display = 'block';
			if (elements.audioQualityBox) elements.audioQualityBox.style.display = 'none';
			if (elements.stageEncodingTitle) elements.stageEncodingTitle.textContent = 'H.264 video transcoding';
		}
	}

	/* -------------------------------------------------------------
	   Conversion Dispatch & Real-Time Polling
	------------------------------------------------------------- */
	async function startConversion() {
		if (config.enableLegalNotice && elements.legalCheckbox && !elements.legalCheckbox.checked) {
			alert('You must confirm that you have legal permission to convert this media.');
			return;
		}

		const quality = (state.selectedFormat === 'mp3')
			? (elements.audioQualitySelect ? elements.audioQualitySelect.value : '320k')
			: (elements.videoQualitySelect ? elements.videoQualitySelect.value : 'original');

		state.selectedQuality = quality;

		const payload = {
			source_type: state.sourceType,
			output_format: state.selectedFormat,
			output_quality: quality,
			legal_confirmed: true,
			temp_id: state.tempId,
			url: state.url,
			filename: (elements.mediaTitle ? elements.mediaTitle.textContent : 'media')
		};

		if (elements.enableTrimCb && elements.enableTrimCb.checked) {
			const tStart = elements.trimStart ? elements.trimStart.value.trim() : '';
			const tEnd   = elements.trimEnd ? elements.trimEnd.value.trim() : '';
			if (tStart) payload.trim_start = tStart;
			if (tEnd) payload.trim_end = tEnd;
		}

		switchPanel('progress');
		resetStages();
		updateProgress(5, 'Starting ultra-fast conversion engine...');

		try {
			const controller = new AbortController();
			const timeoutId = setTimeout(() => controller.abort(), 12000);

			const res = await fetch(`${config.restUrl}/convert`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				signal: controller.signal,
				body: JSON.stringify(payload)
			});
			clearTimeout(timeoutId);

			const data = await res.json();
			if (!res.ok || !data.success) {
				showError(data.message || 'Failed to start conversion process.');
				return;
			}

			state.jobUuid = data.job_uuid;
			startPolling(data.job_uuid);
		} catch (err) {
			if (err.name === 'AbortError') {
				showError('Connection timed out while initializing conversion job. Please try again.');
			} else {
				showError('Network error during conversion request. Please check your connection.');
			}
		}
	}

	function startPolling(uuid) {
		if (state.pollInterval) clearInterval(state.pollInterval);

		const pollPass = async () => {
			try {
				const res = await fetch(`${config.restUrl}/jobs/${uuid}/progress?_t=${Date.now()}`);
				if (!res.ok) return;

				const data = await res.json();
				if (!data.success) return;

				updateProgress(data.progress, data.stage);
				updateStagesVisual(data.stage, data.progress);

				if (data.status === 'completed') {
					clearInterval(state.pollInterval);
					setTimeout(() => {
						showCompleted(data);
					}, 300);
				} else if (data.status === 'failed') {
					clearInterval(state.pollInterval);
					showError(data.error_message || 'Media conversion process failed.');
				}
			} catch (e) {
				// Retry silently on network jitter
			}
		};

		// Immediate check then poll every 800ms
		pollPass();
		state.pollInterval = setInterval(pollPass, 800);
	}

	function updateProgress(percent, stageText) {
		const cleanPercent = Math.min(100, Math.max(0, Math.round(percent)));
		if (elements.pctDisplay) elements.pctDisplay.textContent = cleanPercent + '%';
		if (elements.progressBarFill) elements.progressBarFill.style.width = cleanPercent + '%';
		if (elements.progressEta && stageText) elements.progressEta.textContent = stageText;
	}

	function resetStages() {
		[elements.stageValidation, elements.stageProcessing, elements.stageEncoding, elements.stageFinalizing].forEach(el => {
			if (!el) return;
			el.classList.remove('completed', 'active');
			const st = el.querySelector('.mc-stage-status');
			if (st) st.textContent = 'Pending';
		});
	}

	function updateStagesVisual(stageText, percent) {
		const text = (stageText || '').toLowerCase();

		// Stage 1: Validation
		if (percent >= 10 || text.includes('validation')) {
			setStageState(elements.stageValidation, percent > 15 ? 'completed' : 'active', percent > 15 ? 'Completed' : 'In progress');
		}

		// Stage 2: Media processing
		if (percent >= 15) {
			setStageState(elements.stageProcessing, percent > 35 ? 'completed' : 'active', percent > 35 ? 'Completed' : 'In progress');
		}

		// Stage 3: Encoding / Transcoding
		if (percent >= 35) {
			setStageState(elements.stageEncoding, percent > 90 ? 'completed' : 'active', percent > 90 ? 'Completed' : 'In progress');
		}

		// Stage 4: Finalizing
		if (percent >= 90) {
			setStageState(elements.stageFinalizing, percent >= 100 ? 'completed' : 'active', percent >= 100 ? 'Completed' : 'In progress');
		}
	}

	function setStageState(el, stateClass, text) {
		if (!el) return;
		el.classList.remove('completed', 'active');
		el.classList.add(stateClass);
		const st = el.querySelector('.mc-stage-status');
		if (st) st.textContent = text;
	}

	function showCompleted(data) {
		if (elements.completeFormat) elements.completeFormat.textContent = data.output_format.toUpperCase() + (data.output_format === 'mp4' ? ' Video' : ' Audio');
		if (elements.completeQuality) elements.completeQuality.textContent = data.output_quality;
		if (elements.completeSize) elements.completeSize.textContent = data.file_size_fmt || formatBytes(data.file_size);
		if (elements.completeDuration) elements.completeDuration.textContent = formatDuration(data.duration_seconds);

		if (elements.downloadBtn && data.download_url) {
			elements.downloadBtn.href = data.download_url;
			if (elements.downloadBtnText) {
				elements.downloadBtnText.textContent = `Download ${data.output_format.toUpperCase()}`;
			}
		}

		switchPanel('completed');
	}

	function showError(msg) {
		if (state.pollInterval) clearInterval(state.pollInterval);
		if (elements.errorDescText) elements.errorDescText.textContent = msg;
		switchPanel('error');
	}

	function resetToIngest() {
		if (state.pollInterval) clearInterval(state.pollInterval);
		state.tempId = null;
		state.uploadedFile = null;
		state.url = '';
		state.jobUuid = null;

		if (elements.urlInput) elements.urlInput.value = '';
		if (elements.fileInput) elements.fileInput.value = '';
		updateClearButtonVisibility();

		switchPanel('ingest');
	}

	/* -------------------------------------------------------------
	   Playlist Suite Engine (Batch Processing & 1-Click ZIP)
	------------------------------------------------------------- */
	const playlistState = {
		title: '',
		channel: '',
		url: '',
		items: [],
		selectedIndices: new Set(),
		format: 'mp3',
		quality: '320k',
		batchUuid: null,
		pollTimer: null
	};

	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function showPlaylistConfig(data) {
		playlistState.title = data.playlist_title || data.title || 'YouTube Playlist';
		playlistState.channel = data.playlist_channel || data.author || '';
		playlistState.url = state.url;
		playlistState.items = data.items || [];
		playlistState.selectedIndices = new Set(playlistState.items.map((_, i) => i));
		playlistState.format = 'mp3';
		playlistState.quality = '320k';

		const thumbEl  = document.getElementById('mc-playlist-thumb');
		const titleEl  = document.getElementById('mc-playlist-title');
		const authorEl = document.getElementById('mc-playlist-author');
		const durEl    = document.getElementById('mc-playlist-duration');
		const countEl  = document.getElementById('mc-playlist-count-badge');

		if (thumbEl) thumbEl.src = data.thumbnail || (playlistState.items[0] ? playlistState.items[0].thumbnail : '');
		if (titleEl) titleEl.textContent = playlistState.title;
		if (authorEl) authorEl.textContent = playlistState.channel ? `by ${playlistState.channel}` : 'YouTube Playlist';
		if (durEl) durEl.textContent = `Total: ${data.total_duration || data.duration || '--:--'}`;
		if (countEl) countEl.textContent = `${playlistState.items.length} Videos`;

		initPlaylistControls();
		renderPlaylistItems();
		updatePlaylistConvertButton();
		switchPanel('playlist-config');
	}

	function initPlaylistControls() {
		// Format Toggles (MP3 vs MP4)
		const plFormatBtns = document.querySelectorAll('[data-pl-format]');
		const plAudioBox   = document.getElementById('mc-pl-audio-quality-box');
		const plVideoBox   = document.getElementById('mc-pl-video-quality-box');
		const plAudioSel   = document.getElementById('mc-pl-audio-quality-select');
		const plVideoSel   = document.getElementById('mc-pl-video-quality-select');

		plFormatBtns.forEach(btn => {
			btn.onclick = () => {
				plFormatBtns.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				const fmt = btn.dataset.plFormat;
				playlistState.format = fmt;

				if (fmt === 'mp3') {
					if (plAudioBox) plAudioBox.style.display = 'block';
					if (plVideoBox) plVideoBox.style.display = 'none';
					playlistState.quality = plAudioSel ? plAudioSel.value : '320k';
				} else {
					if (plAudioBox) plAudioBox.style.display = 'none';
					if (plVideoBox) plVideoBox.style.display = 'block';
					playlistState.quality = plVideoSel ? plVideoSel.value : '720p';
				}
				updatePlaylistConvertButton();
			};
		});

		if (plAudioSel) {
			plAudioSel.onchange = () => {
				if (playlistState.format === 'mp3') {
					playlistState.quality = plAudioSel.value;
				}
			};
		}
		if (plVideoSel) {
			plVideoSel.onchange = () => {
				if (playlistState.format === 'mp4') {
					playlistState.quality = plVideoSel.value;
				}
			};
		}
	}

	function renderPlaylistItems() {
		const listEl = document.getElementById('mc-pl-scroll-list');
		if (!listEl) return;
		listEl.innerHTML = '';

		playlistState.items.forEach((item, idx) => {
			const isChecked = playlistState.selectedIndices.has(idx);
			const row = document.createElement('div');
			row.className = `mc-pl-item-row ${isChecked ? 'selected' : ''}`;
			row.innerHTML = `
				<label class="mc-pl-item-label">
					<input type="checkbox" class="mc-pl-item-cb" data-index="${idx}" ${isChecked ? 'checked' : ''} />
					<span class="mc-pl-item-index">${idx + 1}</span>
					${item.thumbnail ? `<img class="mc-pl-item-thumb" src="${escapeHtml(item.thumbnail)}" alt="" />` : '<div class="mc-pl-item-no-thumb">🎵</div>'}
					<div class="mc-pl-item-details">
						<span class="mc-pl-item-title">${escapeHtml(item.title)}</span>
						<span class="mc-pl-item-dur">${escapeHtml(item.duration_formatted || '--:--')}</span>
					</div>
				</label>
			`;
			listEl.appendChild(row);
		});

		// Checkbox click handlers
		listEl.querySelectorAll('.mc-pl-item-cb').forEach(cb => {
			cb.addEventListener('change', (e) => {
				const idx = parseInt(e.target.dataset.index, 10);
				const row = e.target.closest('.mc-pl-item-row');
				if (e.target.checked) {
					playlistState.selectedIndices.add(idx);
					if (row) row.classList.add('selected');
				} else {
					playlistState.selectedIndices.delete(idx);
					if (row) row.classList.remove('selected');
				}
				const selectAllCb = document.getElementById('mc-pl-select-all');
				if (selectAllCb) {
					selectAllCb.checked = (playlistState.selectedIndices.size === playlistState.items.length);
				}
				updatePlaylistConvertButton();
			});
		});

		const selectAllCb = document.getElementById('mc-pl-select-all');
		if (selectAllCb) {
			selectAllCb.checked = (playlistState.selectedIndices.size === playlistState.items.length);
			selectAllCb.onchange = (e) => {
				const checked = e.target.checked;
				if (checked) {
					playlistState.selectedIndices = new Set(playlistState.items.map((_, i) => i));
				} else {
					playlistState.selectedIndices.clear();
				}
				renderPlaylistItems();
				updatePlaylistConvertButton();
			};
		}
	}

	function updatePlaylistConvertButton() {
		const selCount = playlistState.selectedIndices.size;
		const totalCount = playlistState.items.length;
		const selCountEl = document.getElementById('mc-pl-selected-count');
		const totCountEl = document.getElementById('mc-pl-total-count');
		const btnTextEl  = document.getElementById('mc-pl-convert-btn-text');
		const btn        = document.getElementById('mc-start-playlist-convert-btn');

		if (selCountEl) selCountEl.textContent = selCount;
		if (totCountEl) totCountEl.textContent = totalCount;
		if (btnTextEl) {
			const label = (selCount === totalCount) ? 'Convert Entire Playlist' : 'Convert Selected Videos';
			btnTextEl.textContent = `${label} (${selCount} ${playlistState.format.toUpperCase()} ${selCount === 1 ? 'Track' : 'Tracks'})`;
		}
		if (btn) btn.disabled = (selCount === 0);
	}

	async function startPlaylistConversion() {
		const selected = Array.from(playlistState.selectedIndices).map(idx => playlistState.items[idx]);
		if (selected.length === 0) {
			alert('Please select at least 1 video from the playlist to convert.');
			return;
		}

		const btn = document.getElementById('mc-start-playlist-convert-btn');
		if (btn) {
			btn.disabled = true;
			btn.innerHTML = '<div class="mc-btn-spinner"></div><span>Initializing Batch Engine...</span>';
		}

		try {
			const res = await fetch(`${config.restUrl}/convert-playlist`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify({
					playlist_url: playlistState.url,
					playlist_title: playlistState.title,
					output_format: playlistState.format,
					output_quality: playlistState.quality,
					items: selected
				})
			});

			const data = await res.json();
			if (!res.ok || !data.success) {
				if (btn) {
					btn.disabled = false;
					btn.innerHTML = '<span>Convert Entire Playlist</span>';
				}
				showError(data.message || 'Failed to initialize playlist conversion.');
				return;
			}

			playlistState.batchUuid = data.batch_uuid;
			switchPanel('playlist-progress');
			renderPlaylistQueueInitial(selected);
			startPollingPlaylist(data.batch_uuid);
		} catch (err) {
			if (btn) {
				btn.disabled = false;
				btn.innerHTML = '<span>Convert Entire Playlist</span>';
			}
			showError('Network error while starting playlist batch conversion.');
		}
	}

	function renderPlaylistQueueInitial(items) {
		const qList = document.getElementById('mc-pl-queue-list');
		if (!qList) return;
		qList.innerHTML = '';
		items.forEach((item, idx) => {
			const div = document.createElement('div');
			div.className = `mc-pl-queue-item ${idx === 0 ? 'active' : ''}`;
			div.id = `mc-q-item-${idx}`;
			div.innerHTML = `
				<div class="mc-q-icon">${idx === 0 ? '<div class="mc-spinner-sm"></div>' : '<span class="mc-q-num">' + (idx + 1) + '</span>'}</div>
				<div class="mc-q-text">
					<span class="mc-q-title">${escapeHtml(item.title)}</span>
					<span class="mc-q-status">${idx === 0 ? 'Connecting stream...' : 'Queued'}</span>
				</div>
			`;
			qList.appendChild(div);
		});
	}

	function startPollingPlaylist(batchUuid) {
		if (playlistState.pollTimer) clearInterval(playlistState.pollTimer);

		const poll = async () => {
			try {
				const res = await fetch(`${config.restUrl}/playlist/${batchUuid}/progress`);
				if (!res.ok) return;
				const data = await res.json();
				if (!data.success) return;

				updatePlaylistProgressUI(data);

				if (data.status === 'completed') {
					clearInterval(playlistState.pollTimer);
					showPlaylistCompleted(data);
				} else if (data.status === 'failed') {
					clearInterval(playlistState.pollTimer);
					showError(data.stage || 'Playlist conversion encountered an error.');
				}
			} catch (e) {
				console.warn('Playlist poll status:', e);
			}
		};

		poll();
		playlistState.pollTimer = setInterval(poll, 1500);
	}

	function updatePlaylistProgressUI(data) {
		const pctEl       = document.getElementById('mc-pl-overall-pct');
		const barEl       = document.getElementById('mc-pl-overall-bar');
		const titleEl     = document.getElementById('mc-pl-progress-title');
		const subEl       = document.getElementById('mc-pl-progress-sub');
		const activeTitle = document.getElementById('mc-pl-active-title');
		const activeSub   = document.getElementById('mc-pl-active-sub');

		const pct = Math.min(100, Math.max(0, data.overall_progress || 0));
		if (pctEl) pctEl.textContent = `${Math.round(pct)}%`;
		if (barEl) barEl.style.width = `${pct}%`;

		if (titleEl) titleEl.textContent = `Converting Playlist (${data.completed_items}/${data.total_items} Completed)`;
		if (subEl) subEl.textContent = data.stage || 'Batch transcoding in progress...';

		if (data.current_item) {
			if (activeTitle) activeTitle.textContent = data.current_item.title || 'Processing track...';
			if (activeSub) activeSub.textContent = data.current_item.stage || `${data.current_item.progress}% (${data.current_item.status})`;
		}

		if (data.items) {
			const qList = document.getElementById('mc-pl-queue-list');
			if (qList) {
				data.items.forEach((item, idx) => {
					let el = document.getElementById(`mc-q-item-${idx}`);
					if (!el) {
						el = document.createElement('div');
						el.id = `mc-q-item-${idx}`;
						qList.appendChild(el);
					}

					let iconHtml = '<span class="mc-q-num">' + (idx + 1) + '</span>';
					let statusClass = '';

					if (item.status === 'completed') {
						iconHtml = '<span class="mc-q-check">✓</span>';
						statusClass = 'completed';
					} else if (['downloading', 'processing', 'validating'].includes(item.status)) {
						iconHtml = '<div class="mc-spinner-sm"></div>';
						statusClass = 'active';
					} else if (item.status === 'failed') {
						iconHtml = '<span class="mc-q-fail">✕</span>';
						statusClass = 'failed';
					}

					el.className = `mc-pl-queue-item ${statusClass}`;
					el.innerHTML = `
						<div class="mc-q-icon">${iconHtml}</div>
						<div class="mc-q-text">
							<span class="mc-q-title">${escapeHtml(item.title)}</span>
							<span class="mc-q-status">${escapeHtml(item.stage || item.status)}</span>
						</div>
					`;
				});
			}
		}
	}

	function showPlaylistCompleted(data) {
		switchPanel('playlist-completed');

		const titleEl      = document.getElementById('mc-pl-complete-title');
		const countEl      = document.getElementById('mc-pl-complete-count');
		const formatEl     = document.getElementById('mc-pl-complete-format');
		const sizeEl       = document.getElementById('mc-pl-complete-size');
		const zipBtn       = document.getElementById('mc-pl-download-zip-btn');
		const indivCountEl = document.getElementById('mc-pl-indiv-count');
		const indivListEl  = document.getElementById('mc-pl-individual-list');

		if (titleEl) titleEl.textContent = data.playlist_title || 'Playlist';
		if (countEl) countEl.textContent = `${data.completed_items} of ${data.total_items} Tracks`;
		if (formatEl) formatEl.textContent = `${data.output_format.toUpperCase()} (${data.output_quality})`;
		if (sizeEl) sizeEl.textContent = data.zip_size || 'Ready';

		if (zipBtn && data.zip_download_url) {
			zipBtn.href = data.zip_download_url;
			zipBtn.setAttribute('download', data.zip_filename || 'playlist.zip');
		}

		const completedItems = (data.items || []).filter(i => i.status === 'completed');
		if (indivCountEl) indivCountEl.textContent = completedItems.length;

		if (indivListEl && data.items) {
			indivListEl.innerHTML = '';
			data.items.forEach((item, idx) => {
				const isComp = (item.status === 'completed');
				const row = document.createElement('div');
				row.className = 'mc-pl-indiv-row';
				row.innerHTML = `
					<span class="mc-pl-indiv-num">${idx + 1}</span>
					<span class="mc-pl-indiv-title">${escapeHtml(item.title)}</span>
					<span class="mc-pl-indiv-meta">${item.file_size || (isComp ? 'Ready' : item.status)}</span>
					${isComp && item.download_url ? `
						<a href="${item.download_url}" class="mc-btn-indiv-dl" download>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
							<span>Download</span>
						</a>
					` : `<span class="mc-status-failed">Failed</span>`}
				`;
				indivListEl.appendChild(row);
			});
		}
	}

	function resetToIngest() {
		if (state.pollInterval) clearInterval(state.pollInterval);
		if (playlistState.pollTimer) clearInterval(playlistState.pollTimer);

		state.tempId = null;
		state.uploadedFile = null;
		state.url = '';
		state.jobUuid = null;

		if (elements.urlInput) elements.urlInput.value = '';
		if (elements.fileInput) elements.fileInput.value = '';
		updateClearButtonVisibility();

		switchPanel('ingest');
	}

	function switchPanel(panelName) {
		const panels = [
			elements.panelIngest,
			elements.panelConfig,
			elements.panelProgress,
			elements.panelCompleted,
			elements.panelError,
			elements.panelPlaylistConfig,
			elements.panelPlaylistProgress,
			elements.panelPlaylistCompleted
		];

		panels.forEach(p => {
			if (!p) return;
			p.style.display = 'none';
			p.classList.remove('active');
		});

		const target = {
			'ingest': elements.panelIngest,
			'config': elements.panelConfig,
			'progress': elements.panelProgress,
			'completed': elements.panelCompleted,
			'error': elements.panelError,
			'playlist-config': elements.panelPlaylistConfig,
			'playlist-progress': elements.panelPlaylistProgress,
			'playlist-completed': elements.panelPlaylistCompleted
		}[panelName];

		if (target) {
			target.style.display = 'block';
			target.classList.add('active');
		}
	}

	/* -------------------------------------------------------------
	   Accordion & Mobile Menu
	------------------------------------------------------------- */
	function initAccordion() {
		const items = document.querySelectorAll('.mc-accordion-item');
		items.forEach(item => {
			const header = item.querySelector('.mc-accordion-header');
			if (header) {
				header.addEventListener('click', () => {
					const wasActive = item.classList.contains('active');
					items.forEach(i => i.classList.remove('active'));
					if (!wasActive) {
						item.classList.add('active');
					}
				});
			}
		});
	}

	function initMobileMenu() {
		if (elements.mobileToggle && elements.navMenu) {
			elements.mobileToggle.addEventListener('click', () => {
				const isFlex = elements.navMenu.style.display === 'flex';
				elements.navMenu.style.display = isFlex ? 'none' : 'flex';
				if (!isFlex) {
					elements.navMenu.style.flexDirection = 'column';
					elements.navMenu.style.position = 'absolute';
					elements.navMenu.style.top = '72px';
					elements.navMenu.style.left = '0';
					elements.navMenu.style.right = '0';
					elements.navMenu.style.background = 'var(--mc-bg-card)';
					elements.navMenu.style.padding = '20px';
					elements.navMenu.style.boxShadow = 'var(--mc-shadow-lg)';
					elements.navMenu.style.borderBottom = '1px solid var(--mc-border)';
				}
			});
		}
	}

	function bindActionEvents() {
		if (elements.fetchUrlBtn) {
			elements.fetchUrlBtn.addEventListener('click', fetchUrlSource);
		}
		if (elements.startConvertBtn) {
			elements.startConvertBtn.addEventListener('click', startConversion);
		}
		if (elements.cancelConfigBtn) {
			elements.cancelConfigBtn.addEventListener('click', resetToIngest);
		}
		if (elements.resetBtn) {
			elements.resetBtn.addEventListener('click', resetToIngest);
		}
		if (elements.errorRetryBtn) {
			elements.errorRetryBtn.addEventListener('click', resetToIngest);
		}

		// Playlist Actions
		const startPlBtn = document.getElementById('mc-start-playlist-convert-btn');
		if (startPlBtn) {
			startPlBtn.addEventListener('click', startPlaylistConversion);
		}
		const cancelPlBtn = document.getElementById('mc-cancel-playlist-btn');
		if (cancelPlBtn) {
			cancelPlBtn.addEventListener('click', resetToIngest);
		}
		const resetPlBtn = document.getElementById('mc-pl-reset-btn');
		if (resetPlBtn) {
			resetPlBtn.addEventListener('click', resetToIngest);
		}
		const toggleIndivBtn = document.getElementById('mc-pl-toggle-individual');
		const indivListEl = document.getElementById('mc-pl-individual-list');
		if (toggleIndivBtn && indivListEl) {
			toggleIndivBtn.addEventListener('click', () => {
				const isNone = (indivListEl.style.display === 'none' || !indivListEl.style.display);
				indivListEl.style.display = isNone ? 'flex' : 'none';
				toggleIndivBtn.classList.toggle('active', isNone);
			});
		}
	}

	/* -------------------------------------------------------------
	   Helper Formatters
	------------------------------------------------------------- */
	function formatBytes(bytes, decimals = 1) {
		if (!bytes || bytes === 0) return '0 B';
		const k = 1024;
		const dm = decimals < 0 ? 0 : decimals;
		const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
	}

	function formatDuration(seconds) {
		if (!seconds) return '00:00';
		const sec = parseInt(seconds, 10);
		const hrs = Math.floor(sec / 3600);
		const mins = Math.floor((sec % 3600) / 60);
		const remainingSecs = sec % 60;

		const pad = (n) => String(n).padStart(2, '0');
		if (hrs > 0) {
			return `${pad(hrs)}:${pad(mins)}:${pad(remainingSecs)}`;
		}
		return `${pad(mins)}:${pad(remainingSecs)}`;
	}

})();
