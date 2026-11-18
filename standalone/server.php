<?php
/**
 * MediaConverter Pro - Standalone Web Server & Router
 *
 * Allows instant local browsing, conversion testing, REST testing, and admin panel access
 * via: php -S localhost:8000 standalone/server.php
 */

require_once __DIR__ . '/wp-simulator.php';

$uri = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Static asset serving.
$file_map = array(
	'/media-converter/public/assets/css/style.css' => dirname( __DIR__ ) . '/media-converter/public/assets/css/style.css',
	'/media-converter/public/assets/js/converter.js' => dirname( __DIR__ ) . '/media-converter/public/assets/js/converter.js',
	'/media-converter/admin/assets/css/admin.css' => dirname( __DIR__ ) . '/media-converter/admin/assets/css/admin.css',
	'/media-converter/admin/assets/js/admin.js' => dirname( __DIR__ ) . '/media-converter/admin/assets/js/admin.js',
);

if ( isset( $file_map[ $uri ] ) && file_exists( $file_map[ $uri ] ) ) {
	$ext = pathinfo( $uri, PATHINFO_EXTENSION );
	if ( 'css' === $ext ) header( 'Content-Type: text/css' );
	if ( 'js' === $ext ) header( 'Content-Type: application/javascript' );
	readfile( $file_map[ $uri ] );
	exit;
}

// 2. REST API Dispatcher.
if ( str_starts_with( $uri, '/api/media-converter/v1/' ) || str_starts_with( $uri, '/wp-json/media-converter/v1/' ) ) {
	$subpath = preg_replace( '#^/(api|wp-json)/media-converter/v1#', '', $uri );
	$json_body = json_decode( file_get_contents( 'php://input' ), true ) ?: array();
	$params = array_merge( $_GET, $_POST );
	$request = new WP_REST_Request( $method, $params, $json_body );
	$rest_api = media_converter()->rest_api;

	if ( '/validate-url' === $subpath && 'POST' === $method ) {
		$res = $rest_api->handle_validate_url( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( '/upload' === $subpath && 'POST' === $method ) {
		$res = $rest_api->handle_upload( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( '/convert' === $subpath && 'POST' === $method ) {
		$res = $rest_api->handle_convert( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( preg_match( '#^/jobs/([a-zA-Z0-9-]+)/progress$#', $subpath, $matches ) ) {
		$request = new WP_REST_Request( 'GET', array( 'uuid' => $matches[1] ) );
		$res = $rest_api->handle_progress( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( '/download' === $subpath || preg_match( '#^/download#', $subpath ) ) {
		$token = $_GET['token'] ?? '';
		$request = new WP_REST_Request( 'GET', array( 'token' => $token ) );
		$rest_api->handle_download( $request );
		exit;
	}

	if ( '/user/history' === $subpath ) {
		$res = $rest_api->handle_user_history( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( '/stats' === $subpath ) {
		$res = $rest_api->handle_stats( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( '/playlist-info' === $subpath && 'POST' === $method ) {
		$res = $rest_api->handle_playlist_info( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( '/convert-playlist' === $subpath && 'POST' === $method ) {
		$res = $rest_api->handle_convert_playlist( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( preg_match( '#^/playlist/([a-zA-Z0-9-]+)/progress$#', $subpath, $matches ) ) {
		$request = new WP_REST_Request( 'GET', array( 'uuid' => $matches[1] ) );
		$res = $rest_api->handle_playlist_progress( $request );
		header( 'Content-Type: application/json' );
		http_response_code( $res->status );
		echo json_encode( $res->data );
		exit;
	}

	if ( preg_match( '#^/playlist/([a-zA-Z0-9-]+)/download-zip$#', $subpath, $matches ) ) {
		$token = $_GET['token'] ?? '';
		$request = new WP_REST_Request( 'GET', array( 'uuid' => $matches[1], 'token' => $token ) );
		$rest_api->handle_playlist_download_zip( $request );
		exit;
	}
}

// 3. Admin AJAX Dispatcher.
if ( str_contains( $uri, 'admin-ajax.php' ) ) {
	$admin = media_converter()->admin;
	$action = $_POST['action'] ?? '';
	if ( 'media_converter_admin_job_action' === $action ) {
		$admin->ajax_job_action();
		exit;
	}
	if ( 'media_converter_test_binaries' === $action ) {
		$admin->ajax_test_binaries();
		exit;
	}
}

// 4. Admin Dashboard Subpages.
if ( str_starts_with( $uri, '/admin' ) ) {
	$page = $_GET['page'] ?? 'media-converter';
	$database = media_converter()->database;
	$ffmpeg = media_converter()->ffmpeg;
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Media Converter Admin</title>
		<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
		<link rel="stylesheet" href="/media-converter/admin/assets/css/admin.css">
		<style>
			body { background: #f0f2f5; margin: 0; padding: 20px; font-family: Inter, sans-serif; }
			.wp-admin-nav { background: #1e293b; padding: 12px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; gap: 16px; align-items: center; }
			.wp-admin-nav a { color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 600; }
			.wp-admin-nav a.active, .wp-admin-nav a:hover { color: #fff; }
			.wp-admin-nav .brand { color: #fff; font-weight: 800; margin-right: 20px; }
		</style>
		<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
		<script>
			window.mediaConverterAdmin = {
				ajaxUrl: '/admin/admin-ajax.php',
				nonce: '<?php echo wp_create_nonce( 'media_converter_admin_nonce' ); ?>'
			};
		</script>
	</head>
	<body>
		<div class="wp-admin-nav">
			<span class="brand">MediaConverter Pro</span>
			<a href="/admin/?page=media-converter" class="<?php echo ( 'media-converter' === $page ) ? 'active' : ''; ?>">Dashboard</a>
			<a href="/admin/?page=media-converter-jobs" class="<?php echo ( 'media-converter-jobs' === $page ) ? 'active' : ''; ?>">Jobs</a>
			<a href="/admin/?page=media-converter-settings" class="<?php echo ( 'media-converter-settings' === $page ) ? 'active' : ''; ?>">Settings</a>
			<a href="/admin/?page=media-converter-status" class="<?php echo ( 'media-converter-status' === $page ) ? 'active' : ''; ?>">System Status</a>
			<a href="/" style="margin-left: auto; color: #38bdf8;">← Back to Website</a>
		</div>
		<?php
		if ( 'media-converter-jobs' === $page ) {
			require dirname( __DIR__ ) . '/media-converter/admin/jobs.php';
		} elseif ( 'media-converter-settings' === $page ) {
			require dirname( __DIR__ ) . '/media-converter/admin/settings.php';
		} elseif ( 'media-converter-status' === $page ) {
			require dirname( __DIR__ ) . '/media-converter/admin/system-status.php';
		} else {
			require dirname( __DIR__ ) . '/media-converter/admin/dashboard.php';
		}
		?>
		<script src="/media-converter/admin/assets/js/admin.js"></script>
	</body>
	</html>
	<?php
	exit;
}

// 5. Frontend Pages.
$template_file = 'homepage.php';

switch ( trim( $uri, '/' ) ) {
	case 'video-converter':
		$template_file = 'video-converter.php';
		break;
	case 'audio-converter':
		$template_file = 'audio-converter.php';
		break;
	case 'how-it-works':
		$template_file = 'how-it-works.php';
		break;
	case 'about':
		$template_file = 'about.php';
		break;
	case 'contact':
		$template_file = 'contact.php';
		break;
	case 'faq':
		$template_file = 'faq.php';
		break;
	case 'privacy':
		$template_file = 'privacy.php';
		break;
	case 'terms':
		$template_file = 'terms.php';
		break;
	case 'copyright':
		$template_file = 'copyright.php';
		break;
	case 'dashboard':
		$template_file = 'dashboard.php';
		break;
	default:
		$template_file = 'homepage.php';
}

$options = get_option( 'media_converter_options', array() );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>PrismConvert - High-Quality Video & Audio Converter</title>
	<meta name="description" content="Convert supported video and audio files into high-quality MP4 and MP3 formats with a fast, simple and seamless experience.">
	
	<!-- Google Fonts -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
	
	<!-- Plugin Stylesheet -->
	<link rel="stylesheet" href="/media-converter/public/assets/css/style.css">

	<!-- Localized JS Config -->
	<script>
		window.mcConfig = {
			restUrl: '/api/media-converter/v1',
			nonce: '<?php echo esc_js( MediaConverter_Security::create_nonce() ); ?>',
			isLoggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
			maxUploadMb: <?php echo esc_js( $options['guest_max_upload_mb'] ?? 250 ); ?>,
			maxUploadBytes: <?php echo esc_js( ( $options['guest_max_upload_mb'] ?? 250 ) * 1024 * 1024 ); ?>,
			allowedFormats: <?php echo json_encode( $options['allowed_input_formats'] ?? array( 'mp4', 'mp3', 'wav', 'webm', 'mov', 'mkv', 'avi' ) ); ?>,
			enableLegalNotice: <?php echo ! empty( $options['enable_legal_notice_checkbox'] ) ? 'true' : 'false'; ?>,
			enableDarkMode: true
		};
	</script>
</head>
<body>
	<?php require dirname( __DIR__ ) . '/media-converter/public/templates/' . $template_file; ?>
	
	<!-- Plugin JavaScript Engine -->
	<script src="/media-converter/public/assets/js/converter.js"></script>
</body>
</html>
