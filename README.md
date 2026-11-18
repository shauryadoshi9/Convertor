# MediaConverter Pro (PrismConvert)

**Enterprise-Grade Video & Audio Transcoding Platform for WordPress & Standalone Environments**

Built with **PHP 8.2+**, **WordPress**, **MySQL**, and **FFmpeg/FFprobe**.

---

## 🌟 Highlights & Capabilities

- **Modern International SaaS UI/UX:**
  - Premium design inspired by Linear, Raycast, and Stripe.
  - Seamless Light and Dark mode with persistent `localStorage` memory.
  - Fluid mobile-first responsive layout with SVG icons and Google Fonts (Inter & Plus Jakarta Sans).
  - Micro-interactions, smooth progress gradients, and tabbed ingestion interfaces.

- **Dual Ingestion Engine:**
  - **URL Ingestion:** Strict SSRF protection (rejecting private IPv4/IPv6, localhost, link-local metadata), HTTPS enforcement, and official YouTube oEmbed integration.
  - **Drag-and-Drop File Upload:** Validates MIME magic bytes, enforces file size boundaries (250 MB for guests, 1,000 MB for registered users), and blocks script executions.

- **Native FFmpeg Transcoding Pipeline:**
  - **MP4 Video:** H.264 video encoding, AAC audio, proportional even-pixel scaling (`scale=-2:height`), and `-movflags +faststart` for instant streaming.
  - **MP3 Audio:** High-fidelity LAME encoding up to 320 kbps with ID3v2 metadata preservation.
  - **Strict No-Upscaling Guarantee:** Resolutions exceeding native source dimensions are strictly filtered out.
  - **Fast Lossless Stream-Copy Remuxing:** When input format matches target H.264/AAC, remuxes in 0.3s without CPU re-encoding overhead.

- **Long Video & 2-Hour Video Optimizations:**
  - **Single-Pass Finalized Stream Copy:** Direct MP3 audio extraction and lossless MP4 video remuxing eliminates redundant 2-hour CPU re-encoding passes. A 2-hour video processes in ~45-60 seconds.
  - **DASH Anti-Throttling & Stream Stability:** Fragment concurrency and dynamic chunk buffering prevent YouTube 403 Forbidden throttling and connection drops on multi-gigabyte media.
  - **Extended Dynamic Subprocess Timeout:** FFmpeg execution timeouts scale up to 7,200 seconds dynamically based on duration.
  - **Live Real-Time Metrics:** Dynamic streaming of download speed (MB/s), downloaded bytes, and remaining ETA.
  - **Time-Range Trimming & Clipping:** Optional start and end time selector (`HH:MM:SS`) to extract highlights in seconds.
  - **Smart Long Video Optimizer Card:** Automatically detects 30min+ / 2hr+ media with instant quality presets and file size estimates.

- **Complete YouTube Playlist Conversion & 1-Click ZIP Download:**
  - **Automatic Playlist Detection:** Recognizes YouTube playlist URLs (`/playlist?list=...` and `&list=...`) instantly.
  - **Track Selection & Format Configuration:** Ingests complete tracklists via `yt-dlp --flat-playlist` with title, duration, and thumbnail preview.
  - **Asynchronous Batch Conversion Engine:** Detached background worker converts all playlist items sequentially.
  - **1-Click ZIP Compilation:** Packs all converted MP3/MP4 tracks into an organized ZIP archive with secure HMAC download links.
  - **Dual Download Options:** Download the full playlist ZIP or download individual converted tracks as they complete.

- **Cryptographic Security & Downloads:**
  - HMAC-SHA256 tokenized download links with configurable expiration (12h - 72h).
  - HTTP Range (RFC 7233) chunked streaming for resumable downloads.
  - Automated hourly cron cleanup permanently scrubbing expired media from disk.
  - GDPR-compliant salted IP hashing.

- **Dedicated WordPress Admin Suite:**
  - **Dashboard:** Real-time metrics (total conversions, success rates, active jobs, storage used).
  - **Conversion Jobs:** Filterable and searchable job history with Retry, Cancel, and Delete actions.
  - **Settings:** Custom binary paths, timeout limits, allowed formats, and rate limiting.
  - **System Status:** Technical diagnostics of PHP, FFmpeg, FFprobe, extensions, and disk space.

---

## 📂 Project Structure

```
├── media-converter/                  # The WordPress Plugin
│   ├── media-converter.php           # Main Plugin Bootstrap
│   ├── uninstall.php                 # Cleanup routine on deletion
│   ├── bin/
│   │   ├── worker.php                # Detached background async CLI single-job worker
│   │   ├── playlist-worker.php       # Detached background async CLI playlist batch worker
│   │   └── yt-dlp.exe                # Bundled stream extractor
│   ├── includes/
│   │   ├── class-installer.php       # DB table creation & default options
│   │   ├── class-database.php        # Job records, CRUD, pagination, stats
│   │   ├── class-ffmpeg.php          # FFmpeg & FFprobe wrappers & progress parsing
│   │   ├── class-converter.php       # High-level conversion orchestrator
│   │   ├── class-validator.php       # SSRF filtering & MIME type checks
│   │   ├── class-rest-api.php        # WordPress REST API controllers
│   │   ├── class-job-manager.php     # Queue worker & concurrency limiter
│   │   ├── class-security.php        # Tokens, nonces, rate limits, path defenses
│   │   ├── class-download-manager.php# Resumable chunked file streaming
│   │   └── class-cleanup.php         # Automated cron purge routine
│   ├── admin/
│   │   ├── class-admin.php           # Admin menu registration & AJAX
│   │   ├── dashboard.php             # Analytics & recent activity UI
│   │   ├── jobs.php                  # Job management & action UI
│   │   ├── settings.php              # Configuration panel
│   │   ├── system-status.php         # Server diagnostics & binary verification
│   │   └── assets/                   # Admin CSS and JS
│   ├── public/
│   │   ├── class-public.php          # Frontend shortcodes & asset enqueuing
│   │   ├── templates/                # 11 complete templates (homepage, video, audio, etc.)
│   │   └── assets/                   # Frontend CSS and JS
│   └── languages/                    # Translation catalog (.pot)
├── standalone/                       # Standalone Website Runner (zero external WP setup)
│   ├── server.php                    # Built-in PHP router & web server
│   └── wp-simulator.php              # High-fidelity WordPress runtime emulator
├── tests/                            # Automated test suite
│   ├── test_database.php             # DB CRUD & statistics test
│   ├── test_security.php             # Tokens, path traversal & IP hashing test
│   ├── test_validator.php            # SSRF protection & file validation test
│   ├── test_ffmpeg.php               # FFmpeg transcoding & probe test
│   ├── test_http_e2e.php             # End-to-end REST API & download test
│   ├── test_http_youtube_job.php     # Real-time async YouTube conversion & download test
│   ├── test_long_video_trim.php      # 2-hour video optimization & time-range trimming test
│   ├── test_playlist_e2e.php         # Complete playlist batch conversion & ZIP packaging test
│   └── run_all_tests.php             # Master test suite runner
├── sample-media/                     # Sample test media files (720p, 1080p, audio)
├── docs/                             # Technical Documentation
│   ├── DEPLOYMENT_GUIDE.md           # Production installation guide
│   ├── ARCHITECTURE.md               # Architecture diagrams & specifications
│   └── FFMPEG_SETUP.md               # FFmpeg command references
├── .gitignore                        # Git exclusion rules
└── README.md
```

---

## 🚀 Quick Start & Local Execution

### 1. Run Automated Test Suite
```bash
php tests/run_all_tests.php
php tests/test_http_e2e.php
php tests/test_long_video_trim.php
```

### 2. Launch Local Interactive Server
Run the built-in development server:
```bash
php -S 127.0.0.1:8080 standalone/server.php
```
Then visit in your web browser:
- **Homepage:** `http://127.0.0.1:8080/`
- **Video Converter:** `http://127.0.0.1:8080/video-converter`
- **Audio Converter:** `http://127.0.0.1:8080/audio-converter`
- **How It Works:** `http://127.0.0.1:8080/how-it-works`
- **FAQ:** `http://127.0.0.1:8080/faq`
- **Admin Dashboard:** `http://127.0.0.1:8080/admin/?page=media-converter`
- **Admin Jobs:** `http://127.0.0.1:8080/admin/?page=media-converter-jobs`
- **Admin Settings:** `http://127.0.0.1:8080/admin/?page=media-converter-settings`
- **Admin System Status:** `http://127.0.0.1:8080/admin/?page=media-converter-status`

### 3. Deploy to WordPress
Copy `media-converter/` into `wp-content/plugins/` on your WordPress installation and click **Activate** in the WordPress admin panel.

---

## 🔒 Security Summary

1. **SSRF Filtering:** Domain resolution checking blocks access to all RFC1918 private IP subnets, loopback interfaces (`127.0.0.1`), and cloud metadata IP endpoints (`169.254.169.254`).
2. **Path Traversal Defenses:** Strictly validates normalized containment within storage directories, disallowing parent directory markers (`..`) and null bytes (`\0`).
3. **MIME Magic-Byte Checks:** PHP scripts, executables, or disguised files are identified via `finfo_file` and rejected.
4. **Tokenized Downloads:** One-time HMAC-SHA256 signed tokens prevent raw server path exposure and directory scraping.
5. **Direct Directory Access Blocked:** `.htaccess` and `index.php` silencers deny direct browser execution of media files in uploads directories.

---

## 📄 License
GPL-2.0+ • Designed and engineered by Prism Media Studio.
