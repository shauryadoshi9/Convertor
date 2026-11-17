<?php
/**
 * MediaConverter Pro - Lightweight WordPress Runtime Simulator
 *
 * Implements WordPress API hooks, database layer with SQLite, options, nonces,
 * and REST dispatch so the plugin can be tested standalone or in CI/CD without full WP.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

define( 'WPINC', 'wp-includes' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/standalone/storage' );
define( 'WP_CONTENT_URL', '/standalone/storage' );

// Setup storage dir.
if ( ! file_exists( WP_CONTENT_DIR ) ) {
	@mkdir( WP_CONTENT_DIR, 0777, true );
}

/**
 * SQLite Database Mock for $wpdb
 */
class WP_Simulator_DB {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $dbh;
	public $insert_id = 0;

	public function __construct() {
		$db_file = WP_CONTENT_DIR . '/database.sqlite';
		$this->dbh = new PDO( 'sqlite:' . $db_file );
		$this->dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->dbh->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ );

		// Create tables if not exist.
		$this->dbh->exec( "CREATE TABLE IF NOT EXISTS {$this->prefix}options (
			option_id INTEGER PRIMARY KEY AUTOINCREMENT,
			option_name TEXT UNIQUE,
			option_value TEXT,
			autoload TEXT DEFAULT 'yes'
		)" );

		$this->dbh->exec( "CREATE TABLE IF NOT EXISTS {$this->prefix}media_conversion_jobs (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			job_uuid TEXT UNIQUE,
			user_id INTEGER DEFAULT 0,
			source_type TEXT DEFAULT 'upload',
			source_url TEXT,
			original_filename TEXT,
			input_path TEXT,
			output_filename TEXT,
			output_path TEXT,
			output_format TEXT DEFAULT 'mp4',
			output_quality TEXT DEFAULT 'original',
			status TEXT DEFAULT 'queued',
			stage TEXT DEFAULT 'Queued',
			progress REAL DEFAULT 0,
			file_size INTEGER DEFAULT 0,
			duration_seconds REAL DEFAULT 0,
			download_token TEXT,
			download_count INTEGER DEFAULT 0,
			error_message TEXT,
			ip_hash TEXT,
			created_at TEXT,
			started_at TEXT,
			completed_at TEXT,
			expires_at TEXT
		)" );
	}

	public function get_charset_collate() {
		return '';
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		// Replace %d, %s, %f with PDO placeholders.
		$pattern = '/(%[dsf]|%%)/';
		$i = 0;
		$prepared = preg_replace_callback( $pattern, function( $matches ) use ( &$i, $args ) {
			if ( $matches[0] === '%%' ) return '%';
			$val = $args[ $i ] ?? null;
			$i++;
			if ( is_null( $val ) ) return 'NULL';
			if ( is_numeric( $val ) ) return $val;
			return "'" . SQLite3::escapeString( (string) $val ) . "'";
		}, $query );

		return $prepared;
	}

	public function query( $query ) {
		try {
			return $this->dbh->exec( $query );
		} catch ( Exception $e ) {
			return false;
		}
	}

	public function get_var( $query ) {
		try {
			$stmt = $this->dbh->query( $query );
			$val = $stmt ? $stmt->fetchColumn() : null;
			return ( false !== $val ) ? $val : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	public function get_row( $query ) {
		try {
			$stmt = $this->dbh->query( $query );
			$row = $stmt ? $stmt->fetch() : null;
			return ( false !== $row ) ? $row : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	public function get_results( $query ) {
		try {
			$stmt = $this->dbh->query( $query );
			return $stmt ? $stmt->fetchAll() : array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	public function insert( $table, $data ) {
		$cols = array_keys( $data );
		$placeholders = array_fill( 0, count( $cols ), '?' );
		$sql = "INSERT INTO {$table} (" . implode( ',', $cols ) . ") VALUES (" . implode( ',', $placeholders ) . ")";
		try {
			$stmt = $this->dbh->prepare( $sql );
			$stmt->execute( array_values( $data ) );
			$this->insert_id = $this->dbh->lastInsertId();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	public function update( $table, $data, $where ) {
		$set_parts = array();
		$values = array();
		foreach ( $data as $k => $v ) {
			$set_parts[] = "{$k} = ?";
			$values[] = $v;
		}
		$where_parts = array();
		foreach ( $where as $k => $v ) {
			$where_parts[] = "{$k} = ?";
			$values[] = $v;
		}
		$sql = "UPDATE {$table} SET " . implode( ',', $set_parts ) . " WHERE " . implode( ' AND ', $where_parts );
		try {
			$stmt = $this->dbh->prepare( $sql );
			return $stmt->execute( $values );
		} catch ( Exception $e ) {
			return false;
		}
	}

	public function delete( $table, $where ) {
		$where_parts = array();
		$values = array();
		foreach ( $where as $k => $v ) {
			$where_parts[] = "{$k} = ?";
			$values[] = $v;
		}
		$sql = "DELETE FROM {$table} WHERE " . implode( ' AND ', $where_parts );
		try {
			$stmt = $this->dbh->prepare( $sql );
			return $stmt->execute( $values );
		} catch ( Exception $e ) {
			return false;
		}
	}

	public function db_version() {
		return 'SQLite 3.x (Mock WPDB)';
	}
}

global $wpdb;
$wpdb = new WP_Simulator_DB();

// Global hooks storage.
$GLOBALS['wp_actions'] = array();
$GLOBALS['wp_filters'] = array();
$GLOBALS['wp_rest_routes'] = array();

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_actions'][ $tag ][] = $callback;
}

function do_action( $tag, ...$args ) {
	if ( ! empty( $GLOBALS['wp_actions'][ $tag ] ) ) {
		foreach ( $GLOBALS['wp_actions'][ $tag ] as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_filters'][ $tag ][] = $callback;
}

function apply_filters( $tag, $value, ...$args ) {
	if ( ! empty( $GLOBALS['wp_filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['wp_filters'][ $tag ] as $cb ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
	}
	return $value;
}

function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return '/media-converter/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function trailingslashit( $string ) {
	return rtrim( $string, '/\\' ) . '/';
}

function wp_upload_dir() {
	$basedir = WP_CONTENT_DIR . '/uploads';
	if ( ! file_exists( $basedir ) ) {
		@mkdir( $basedir, 0777, true );
	}
	return array(
		'basedir' => $basedir,
		'baseurl' => '/standalone/storage/uploads',
	);
}

function wp_mkdir_p( $target ) {
	if ( file_exists( $target ) ) return true;
	return @mkdir( $target, 0777, true );
}

function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	$path = preg_replace( '|(?<=.)/+|', '/', $path );
	if ( ':' === substr( $path, 1, 1 ) ) {
		$path = ucfirst( $path );
	}
	return $path;
}

function wp_generate_uuid4() {
	return sprintf(
		'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0x0fff ) | 0x4000,
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	);
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	if ( $special_chars ) $chars .= '!@#$%^&*()';
	$str = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$str .= $chars[ mt_rand( 0, strlen( $chars ) - 1 ) ];
	}
	return $str;
}

function current_time( $type ) {
	return gmdate( 'Y-m-d H:i:s' );
}

function size_format( $bytes, $decimals = 0 ) {
	$quant = array( 'TB' => 1099511627776, 'GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024, 'B' => 1 );
	foreach ( $quant as $unit => $mag ) {
		if ( doubleval( $bytes ) >= $mag ) {
			return number_format( $bytes / $mag, $decimals ) . ' ' . $unit;
		}
	}
	return '0 B';
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( $number, $decimals );
}

function get_option( $option, $default = false ) {
	global $wpdb;
	$val = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
	if ( is_null( $val ) ) return $default;
	$unserialized = @unserialize( $val );
	return ( false !== $unserialized || 'b:0;' === $val ) ? $unserialized : $val;
}

function update_option( $option, $value, $autoload = null ) {
	global $wpdb;
	$serialized = is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", $option ) );
	if ( $exists ) {
		return $wpdb->update( $wpdb->options, array( 'option_value' => $serialized ), array( 'option_name' => $option ) );
	}
	return $wpdb->insert( $wpdb->options, array( 'option_name' => $option, 'option_value' => $serialized ) );
}

function delete_option( $option ) {
	global $wpdb;
	return $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
}

function get_transient( $transient ) {
	return get_option( '_transient_' . $transient );
}

function set_transient( $transient, $value, $expiration = 0 ) {
	return update_option( '_transient_' . $transient, $value );
}

function delete_transient( $transient ) {
	return delete_option( '_transient_' . $transient );
}

function wp_salt( $scheme = 'auth' ) {
	return 'prism_media_converter_secret_salt_2026';
}

function wp_create_nonce( $action = -1 ) {
	return substr( hash_hmac( 'md5', (string) $action, wp_salt() ), -12, 10 );
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return true; // Simplified for simulator
}

function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
	return true;
}

function sanitize_text_field( $str ) {
	return strip_tags( trim( (string) $str ) );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_file_name( $name ) {
	return preg_replace( '/[^a-zA-Z0-9_\.-]/', '_', (string) $name );
}

function sanitize_email( $email ) {
	return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
}

function sanitize_textarea_field( $str ) {
	return htmlspecialchars( strip_tags( (string) $str ) );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_js( $text ) {
	return addcslashes( (string) $text, "\\'\"&\n\r<>" );
}

function esc_url_raw( $url ) {
	return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
}

function esc_url( $url ) {
	return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function is_user_logged_in() {
	return ! empty( $_COOKIE['mc_simulated_user'] );
}

function get_current_user_id() {
	return is_user_logged_in() ? 1 : 0;
}

function wp_get_current_user() {
	return (object) array( 'ID' => 1, 'user_login' => 'demo_creator', 'display_name' => 'Demo User' );
}

function current_user_can( $cap ) {
	return true;
}

function home_url( $path = '' ) {
	return '/' . ltrim( $path, '/' );
}

function admin_url( $path = '' ) {
	return '/admin/' . ltrim( $path, '/' );
}

function rest_url( $path = '' ) {
	return '/api/' . ltrim( $path, '/' );
}

function wp_login_url( $redirect = '' ) {
	return '/?login=1';
}

function wp_registration_url() {
	return '/?register=1';
}

function get_bloginfo( $show = '' ) {
	if ( 'name' === $show ) return 'PrismConvert';
	if ( 'version' === $show ) return '6.7';
	return '';
}

function get_permalink() {
	return '/';
}

function is_admin() {
	return isset( $_GET['page'] ) || str_starts_with( $_SERVER['REQUEST_URI'] ?? '', '/admin' );
}

function __( $text, $domain = 'default' ) { return $text; }
function _e( $text, $domain = 'default' ) { echo $text; }
function _n( $single, $plural, $number, $domain = 'default' ) { return ( 1 === $number ) ? $single : $plural; }
function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = 'default' ) { echo esc_html( $text ); }
function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); }
function esc_attr_e( $text, $domain = 'default' ) { echo esc_attr( $text ); }
function load_plugin_textdomain( ...$args ) {}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {}
function wp_unschedule_event( $timestamp, $hook, $args = array() ) {}
function wp_next_scheduled( $hook, $args = array() ) { return false; }

function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) $args = get_object_vars( $args );
	return array_merge( $defaults, (array) $args );
}

function wp_die( $message = '', $title = '', $args = array() ) {
	$status = $args['response'] ?? 500;
	http_response_code( $status );
	echo "<h1>" . esc_html( $title ?: 'Error' ) . "</h1><p>" . esc_html( $message ) . "</p>";
	exit;
}

function wp_send_json_success( $data = null, $status_code = null ) {
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'success' => true, 'data' => $data ) );
	exit;
}

function wp_send_json_error( $data = null, $status_code = null ) {
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'success' => false, 'data' => $data ) );
	exit;
}

function wp_enqueue_style( ...$args ) {}
function wp_enqueue_script( ...$args ) {}
function wp_localize_script( ...$args ) {}

function add_shortcode( $tag, $callback ) {
	$GLOBALS['wp_shortcodes'][ $tag ] = $callback;
}

function add_menu_page( ...$args ) {}
function add_submenu_page( ...$args ) {}
function register_setting( ...$args ) {}
function settings_fields( ...$args ) {}
function submit_button( $text = 'Save Changes' ) {
	echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr( $text ) . '"></p>';
}
function checked( $checked, $current = true, $echo = true ) {
	$result = ( (string) $checked === (string) $current ) ? " checked='checked'" : '';
	if ( $echo ) echo $result;
	return $result;
}
function selected( $selected, $current = true, $echo = true ) {
	$result = ( (string) $selected === (string) $current ) ? " selected='selected'" : '';
	if ( $echo ) echo $result;
	return $result;
}
function paginate_links( $args = '' ) { return ''; }

class WP_REST_Server {
	const READABLE = 'GET';
	const CREATABLE = 'POST';
}

class WP_REST_Request {
	private $method;
	private $params;
	private $json_params;

	public function __construct( $method, $params = array(), $json_params = array() ) {
		$this->method = $method;
		$this->params = $params;
		$this->json_params = $json_params;
	}

	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}

	public function get_json_params() {
		return $this->json_params;
	}

	public function get_file_params() {
		return $_FILES;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data = $data;
		$this->status = $status;
	}
}

function register_rest_route( $namespace, $route, $args = array() ) {
	$GLOBALS['wp_rest_routes'][] = array(
		'namespace' => $namespace,
		'route'     => $route,
		'args'      => $args,
	);
}

function wp_remote_get( $url, $args = array() ) {
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 10 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
	$body = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	if ( false === $body ) return new WP_Error( 'http_error', 'Curl failed' );
	return array( 'response' => array( 'code' => $code ), 'body' => $body );
}

function wp_remote_head( $url, $args = array() ) {
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_NOBODY, true );
	curl_setopt( $ch, CURLOPT_HEADER, true );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 10 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
	$headers = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
	$len  = curl_getinfo( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD );
	curl_close( $ch );

	if ( false === $headers ) return new WP_Error( 'http_error', 'Curl failed' );
	return array(
		'response' => array( 'code' => $code ),
		'headers'  => array( 'content-type' => $type, 'content-length' => $len ),
	);
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'] ?? 500;
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'] ?? '';
}

function wp_remote_retrieve_headers( $response ) {
	return $response['headers'] ?? array();
}

function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code, $message ) {
		$this->code = $code;
		$this->message = $message;
	}
}

function absint( $maybeint ) {
	return abs( intval( $maybeint ) );
}

function dbDelta( $queries = '', $execute = true ) {
	global $wpdb;
	if ( ! empty( $queries ) && $execute ) {
		$wpdb->query( $queries );
	}
	return array();
}

// Bootstrap actual plugin.
require_once dirname( __DIR__ ) . '/media-converter/media-converter.php';

// Initialize defaults on first run.
MediaConverter_Installer::activate();
