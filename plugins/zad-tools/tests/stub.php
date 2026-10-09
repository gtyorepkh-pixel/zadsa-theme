<?php
/** Minimal WordPress stub shared by the PHP tests (no WordPress needed). */
define( 'ABSPATH', '/x/' ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 ); define( 'ZT_DIR', dirname( __DIR__ ) . '/' ); define( 'ZT_URL', '/p/' ); define( 'ZT_VERSION', 't' );
$GLOBALS['O'] = array(); $GLOBALS['T'] = array(); $GLOBALS['META'] = array(); $GLOBALS['F'] = array(); $GLOBALS['ins'] = array(); $GLOBALS['q'] = array();
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_code() { return $this->c; } function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function add_action( $t, $f ) { $GLOBALS['A'][ $t ][] = $f; } function add_shortcode() {} function register_activation_hook() {} function register_deactivation_hook() {}
function add_filter( $t, $f ) { $GLOBALS['F'][ $t ][] = $f; }
function apply_filters( $t, $v ) { foreach ( (array) ( $GLOBALS['F'][ $t ] ?? array() ) as $f ) { $v = call_user_func_array( $f, array_slice( func_get_args(), 1 ) ); } return $v; }
function do_action( $t ) { foreach ( (array) ( $GLOBALS['A'][ $t ] ?? array() ) as $f ) { call_user_func_array( $f, array_slice( func_get_args(), 1 ) ); } }
function get_option( $k, $d = false ) { return $GLOBALS['O'][ $k ] ?? $d; } function update_option( $k, $v ) { $GLOBALS['O'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['T'][ $k ] ?? false; } function set_transient( $k, $v ) { $GLOBALS['T'][ $k ] = $v; }
function wp_salt() { return 'salt'; } function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); } function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); } function is_email( $s ) { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $s ); }
function wp_unslash( $s ) { return $s; } function absint( $n ) { return abs( (int) $n ); } function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $s ) ); }
function wp_date( $f, $ts = null ) { return date( $f, $ts ?? time() ); } function current_time( $t ) { return date( 'Y-m-d H:i:s' ); }
function get_bloginfo() { return 'زاد'; } function home_url( $p = '' ) { return 'https://zadksa.com' . $p; } function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); } function esc_attr( $s ) { return htmlspecialchars( (string) $s ); } function esc_url( $s ) { return htmlspecialchars( (string) $s ); } function wp_kses_post( $s ) { return $s; }
function get_the_title( $i ) { return 'صفحة ' . $i; } function get_permalink( $i ) { return 'https://zadksa.com/page-' . $i . '/'; } function get_post_status() { return 'publish'; }
function get_post_meta( $id, $k ) { return $GLOBALS['META'][ $id ][ $k ] ?? ''; } function current_user_can() { return false; } function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function get_post_type() { return 'page'; } function get_page_template_slug() { return 'zt-tool-page.php'; } function has_shortcode() { return false; } function get_post_field() { return ''; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
class FakeDB { public $prefix = 'wp_'; public $insert_id = 0;
	function prepare( $q ) { return $q; } function get_var() { return array_shift( $GLOBALS['q'] ); }
	function insert( $t, $d ) { $GLOBALS['ins'][] = $d; $this->insert_id = 41; return 1; } }
$wpdb = new FakeDB();
$_SERVER['REMOTE_ADDR'] = '1.2.3.4'; $_SERVER['HTTP_USER_AGENT'] = 'UA';
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function nocache_headers() {} function status_header() {} function get_post_status_stub() {}
function wp_kses_post_stub() {}
function esc_textarea( $s ) { return htmlspecialchars( (string) $s ); }
function selected() { return ''; } function checked() { return ''; }
function get_queried_object_id() { return 1; }
function wp_dropdown_pages() {}
function is_page() { return false; }
function is_singular() { return false; }
