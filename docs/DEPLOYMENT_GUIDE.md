# PrismConvert (MediaConverter Pro) - Enterprise Deployment & Production Guide

This comprehensive guide details how to install, configure, secure, and operate **MediaConverter Pro** in a production WordPress environment.

---

## 1. System Requirements

### Server & PHP
- **Operating System:** Linux (Ubuntu 22.04/24.04 LTS, Debian 12, RHEL/Rocky Linux 9), macOS, or Windows Server 2022.
- **PHP Version:** **PHP 8.2 or 8.3+** (ZTS or NTS).
- **Web Server:** Nginx 1.22+ (recommended) or Apache 2.4+ with `mod_rewrite` and `mod_headers`.
- **Database:** MySQL 8.0+ or MariaDB 10.6+.
- **WordPress:** WordPress 6.0 through 6.7+.
- **Media Engine:** **FFmpeg** & **FFprobe** (version 4.4, 5.x, 6.x, 7.x, 8.x, or 9.x) compiled with:
  - `libx264` (H.264 video encoder)
  - `aac` (Advanced Audio Coding)
  - `libmp3lame` (MP3 audio encoder)

### Required PHP Extensions
Ensure the following extensions are enabled in your `php.ini`:
- `fileinfo` (Deep MIME type verification and magic-byte safety)
- `curl` (Remote stream fetching and oEmbed querying)
- `mbstring` (Safe string sanitization)
- `openssl` (HTTPS verification & cryptographic HMAC token signing)
- `gd` or `imagick` (Image processing & thumbnail display)
- `mysqli` or `pdo_mysql` (Database communications)
- `zlib` (Gzip compression)

---

## 2. Server-Level Media Processing Setup (FFmpeg & FFprobe)

### Ubuntu / Debian
```bash
sudo apt update
sudo apt install -y ffmpeg

# Verify installation:
ffmpeg -version
ffprobe -version
```

### RHEL / Rocky Linux / AlmaLinux
```bash
sudo dnf install -y epel-release
sudo dnf install -y --nogpgcheck https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-$(rpm -E %rhel).noarch.rpm
sudo dnf install -y ffmpeg ffmpeg-devel

# Verify installation:
ffmpeg -version
ffprobe -version
```

### Windows Server
Using Windows Package Manager (WinGet):
```powershell
winget install --id Gyan.FFmpeg.Essentials --silent --accept-package-agreements
```
Or download static binaries from [Gyan.dev](https://www.gyan.dev/ffmpeg/builds/) and place in `C:\ffmpeg\bin\`. Add to system `PATH`.

---

## 3. PHP Configuration (`php.ini`)

Edit your production `php.ini` (e.g. `/etc/php/8.3/fpm/php.ini`):

```ini
; File Upload Directives
upload_max_filesize = 500M
post_max_size = 500M
memory_limit = 512M

; Execution Timeouts for Transcoding
max_execution_time = 600
max_input_time = 600

; Ensure proc_open and shell_exec are NOT disabled
; Make sure 'proc_open' does NOT appear in disable_functions
disable_functions = 
```

Reload PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

---

## 4. Web Server Configuration

### Nginx (Recommended)
Add the following directives inside your `server { ... }` block:

```nginx
# Allow large file uploads
client_max_body_size 500M;
client_body_buffer_size 128k;
client_body_timeout 300s;

# Protect secure storage directories from direct browser execution
location ~* /wp-content/uploads/media-converter/(temp|output)/ {
    internal; # Only accessible via PHP X-Accel-Redirect or PHP readfile
    # or deny all direct web access:
    deny all;
    return 403;
}

# Standard WordPress Pretty Permalinks
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

### Apache (`.htaccess`)
Ensure `mod_rewrite` and `mod_headers` are enabled:
```bash
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```
The plugin automatically generates secure `.htaccess` deny rules inside `wp-content/uploads/media-converter/` to prevent direct unauthenticated downloads or PHP script execution.

---

## 5. Plugin Installation in WordPress

1. Copy the `media-converter` folder to your WordPress plugins directory:
   ```bash
   cp -r media-converter /var/www/html/wp-content/plugins/
   ```
2. Set proper ownership and permissions:
   ```bash
   chown -R www-data:www-data /var/www/html/wp-content/plugins/media-converter
   chmod -R 755 /var/www/html/wp-content/plugins/media-converter
   ```
3. In the WordPress Admin, navigate to **Plugins > Installed Plugins** and click **Activate** under **MediaConverter Pro**.
4. The installer automatically creates the database table `wp_media_conversion_jobs` and initializes the storage directories under `wp-content/uploads/media-converter/`.

---

## 6. Configuring Background Job Processing (WP-Cron)

By default, WordPress executes cron jobs when a visitor loads a page. For a high-performance converter, configure a real system crontab:

1. Disable default WP-Cron by adding this to `wp-config.php`:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```
2. Set up a system cron job to run every minute:
   ```bash
   sudo crontab -u www-data -e
   ```
   Add the following line:
   ```cron
   * * * * * /usr/bin/php /var/www/html/wp-cron.php >/dev/null 2>&1
   ```

---

## 7. Shortcodes & Page Setup

Create the primary pages in **Pages > Add New** using the following shortcodes:

| Page Name | Slug | Shortcode | Description |
|---|---|---|---|
| **Home** | `/` | `[media_converter_home]` | Full hero, converter, features, FAQ, and footer |
| **Video Converter** | `/video-converter/` | `[media_converter_video]` | Dedicated MP4 H.264/AAC Transcoder |
| **Audio Converter** | `/audio-converter/` | `[media_converter_audio]` | Dedicated MP3 LAME 320kbps Extractor |
| **How It Works** | `/how-it-works/` | `[media_converter_how_it_works]` | Technical architecture walkthrough |
| **About Us** | `/about/` | `[media_converter_about]` | Mission and engineering values |
| **Contact** | `/contact/` | `[media_converter_contact]` | Functional contact form with nonces |
| **FAQ** | `/faq/` | `[media_converter_faq]` | Searchable accordion knowledge base |
| **Privacy Policy** | `/privacy/` | `[media_converter_privacy]` | GDPR-compliant retention disclosures |
| **Terms of Service** | `/terms/` | `[media_converter_terms]` | Acceptable use & legal boundaries |
| **Copyright Policy** | `/copyright/` | `[media_converter_copyright]` | DMCA protocol and fair-use guidance |
| **User Dashboard** | `/dashboard/` | `[media_converter_dashboard]` | Member conversion history & quotas |

---

## 8. Standalone Local Testing (Without WordPress)

If testing on a local machine, development laptop, or CI/CD runner:
```bash
# Start standalone runner:
php -S 127.0.0.1:8080 standalone/server.php
```
Open your browser to:
- Website: `http://127.0.0.1:8080/`
- Video Converter: `http://127.0.0.1:8080/video-converter`
- Audio Converter: `http://127.0.0.1:8080/audio-converter`
- Admin Dashboard: `http://127.0.0.1:8080/admin/?page=media-converter`
- Admin System Status: `http://127.0.0.1:8080/admin/?page=media-converter-status`

---

## 9. Troubleshooting & FAQ

### Problem: "FFmpeg executable not found or not executable"
- **Solution:** Verify the exact path by running `which ffmpeg` on Linux or `where ffmpeg` on Windows. In **Media Converter > Settings**, paste the absolute binary path (e.g. `/usr/bin/ffmpeg`) and click **Test Binaries**. Ensure the web server user (`www-data`) has execute permissions (`chmod +x /usr/bin/ffmpeg`).

### Problem: "proc_open is disabled"
- **Solution:** In your `php.ini`, locate `disable_functions` and remove `proc_open` and `shell_exec` from the comma-separated list, then restart PHP-FPM.

### Problem: "File exceeds upload_max_filesize"
- **Solution:** Verify that both `upload_max_filesize` and `post_max_size` in `php.ini` are greater than or equal to the desired upload size (e.g. `500M`). In Nginx, ensure `client_max_body_size 500M;` is configured.
