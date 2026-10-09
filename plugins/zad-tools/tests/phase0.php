<?php
/** Phase-0 PHP tests with a minimal WordPress stub (no WordPress needed). Run: php tests/phase0.php */
define( 'ABSPATH', '/x/' ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 ); define( 'ZT_DIR', dirname( __DIR__ ) . '/' ); define( 'ZT_URL', '/p/' ); define( 'ZT_VERSION', 't' );
$GLOBALS['O'] = array(); $GLOBALS['T'] = array(); $GLOBALS['META'] = array(); $GLOBALS['F'] = array(); $GLOBALS['ins'] = array(); $GLOBALS['q'] = array();
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_code() { return $this->c; } function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function add_action() {} function add_shortcode() {} function register_activation_hook() {} function register_deactivation_hook() {}
function add_filter( $t, $f ) { $GLOBALS['F'][ $t ][] = $f; }
function apply_filters( $t, $v ) { foreach ( (array) ( $GLOBALS['F'][ $t ] ?? array() ) as $f ) { $v = call_user_func_array( $f, array_slice( func_get_args(), 1 ) ); } return $v; }
function do_action() {}
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
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/reminders.php'; require ZT_DIR . 'includes/tool-page.php';

$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }

/* phone */
foreach ( array( '0551234567' => '0551234567', '+966 55 123 4567' => '0551234567', '٠٥٥١٢٣٤٥٦٧' => '0551234567', '966551234567' => '0551234567', '123' => '', '0112345678' => '0112345678' ) as $in => $exp ) { ok( zt_normalize_phone( $in ) === $exp, "phone $in → " . zt_normalize_phone( $in ) ); }
ok( zt_intl_phone( '0551234567' ) === '966551234567', 'intl phone' );

/* settings: defaults need approval, approval opens the gate */
ok( 12 === (int) zt_opt( 'general.retention_months' ), 'default retention 12' );
ok( isset( zt_pending_approvals()['general.retention_months'] ), 'retention flagged' );
ok( isset( zt_pending_approvals()['general.token_ttl_minutes'] ), 'every numeric default is flagged, technical ones too' );
zt_register_settings( 'demo', 'تجريبي', array( array( 'key' => 'k', 'label' => 'k', 'type' => 'number', 'default' => 7, 'approval' => true, 'source' => 's' ) ) );
ok( ! zt_tool_ready( 'demo' ), 'tool blocked while unapproved' );
update_option( 'zad_tools_approved', array( 'demo.k' => array( 'by' => 1 ) ) );
ok( zt_tool_ready( 'demo' ), 'tool ready after approval' );
ok( zt_sanitize_field( array( 'type' => 'number', 'default' => 1, 'min' => 1, 'max' => 10 ), '٩٩' ) === 10, 'number sanitized, clamped, Arabic digits' );

/* token + rate limit */
$t = zt_token_make( 1000000 ); ok( zt_token_check( $t, 1000000 ), 'token valid' ); ok( zt_token_check( $t, 1000000 + 30 * 60 ), 'token valid in next bucket' );
ok( ! zt_token_check( $t, 1000000 + 3 * 30 * 60 ), 'token expired' ); ok( ! zt_token_check( 'x' . $t, 1000000 ), 'token tampered' ); ok( ! zt_token_check( '', 1 ), 'empty token' );
ok( zt_rate_limit( 'a', 2 ) && zt_rate_limit( 'a', 2 ) && ! zt_rate_limit( 'a', 2 ), 'rate limit: 2 then block' );

/* reminders: validation and DB flow */
$good = array( 'first_name' => 'أحمد', 'phone' => '0551234567', 'service' => 'تنظيف خزان', 'due_date' => date( 'Y-m-d', strtotime( '+30 days' ) ), 'tool' => 'tank', 'consent' => true );
ok( zt_reminder_add( array( 'consent' => false ) + $good )->get_error_code() === 'consent', 'no consent refused' );
ok( zt_reminder_add( array( 'first_name' => 'أ' ) + $good )->get_error_code() === 'name', 'short name refused' );
ok( zt_reminder_add( array( 'phone' => '12' ) + $good )->get_error_code() === 'phone', 'bad phone refused' );
ok( zt_reminder_add( array( 'due_date' => '2001-01-01' ) + $good )->get_error_code() === 'due', 'past date refused' );
$GLOBALS['q'] = array( 5 ); ok( zt_reminder_add( $good )->get_error_code() === 'cap', 'per-phone cap' );
$GLOBALS['q'] = array( 0, 77 ); ok( 77 === zt_reminder_add( $good ), 'duplicate returns existing id' );
$GLOBALS['q'] = array( 0, 0 ); $GLOBALS['ins'] = array(); $id = zt_reminder_add( $good );
ok( 41 === $id && 1 === count( $GLOBALS['ins'] ), 'inserted' );
$row = $GLOBALS['ins'][0]; ok( 'pending' === $row['status'] && 32 === strlen( $row['token'] ) && '' !== $row['consent_at'], 'row has status, token, consent time' );
$r = (object) array( 'first_name' => 'أحمد', 'service' => 'تنظيف خزان', 'hood' => 'النرجس', 'token' => str_repeat( 'a', 32 ) );
$msg = zt_reminder_message( $r ); ok( false !== strpos( $msg, 'أحمد' ) && false !== strpos( $msg, 'zad_unsub=' . $r->token ), 'message has name + unsubscribe link' );

/* tool page */
$GLOBALS['META'][10] = array( '_zt_tool' => 'demo-tool', '_zt_faq' => "كم السعر؟ | حسب المعاينة\nسؤال بلا جواب", '_zt_services' => '20,21', '_zt_intro' => 'سطر', '_zt_cta' => 'اطلب' );
zt_register_settings( 'demo-tool', 'أداة', array( array( 'key' => 'v', 'label' => 'v', 'type' => 'number', 'default' => 3, 'approval' => true, 'source' => 's' ) ) );
add_filter( 'zad_tools_register', function ( $t ) { $t['demo-tool'] = array( 'title' => 'أداة تجريبية', 'desc' => 'وصف', 'settings_tab' => 'demo-tool',
	'collects_data' => true, 'render' => function ( $c ) { echo '<form>FORM</form>'; }, 'how' => function ( $c ) { return '<p>HOW</p>'; }, 'examples' => function ( $c ) { return '<p>EX</p>'; } ); return $t; } );
$ctx = zt_ctx( 10 );
ok( 'demo-tool' === $ctx['tool'] && 1 === count( $ctx['faq'] ) && array( 20, 21 ) === $ctx['services'], 'ctx: tool, faq (bad line dropped), services' );
ok( false === $ctx['ready'], 'not ready before approval' );
ob_start(); zt_render_body( $ctx ); $html = ob_get_clean();
ok( false !== strpos( $html, 'قيد المراجعة' ) && false === strpos( $html, 'FORM' ), 'unapproved tool shows the notice only' );
update_option( 'zad_tools_approved', array( 'demo.k' => 1, 'demo-tool.v' => 1 ) );
$ctx = zt_ctx( 10 ); ok( false === $ctx['ready'], 'a data-collecting tool still waits for the general privacy values' );
update_option( 'zad_tools_approved', array( 'demo.k' => 1, 'demo-tool.v' => 1, 'general.retention_months' => 1, 'general.reminder_max_per_phone' => 1, 'general.rate_per_ip_hour' => 1, 'general.token_ttl_minutes' => 1, 'general.reminder_max_years' => 1 ) );
$ctx = zt_ctx( 10 ); ob_start(); zt_render_body( $ctx ); $html = ob_get_clean();
$pos = array_map( function ( $s ) use ( $html ) { return strpos( $html, $s ); }, array( 'FORM', 'إزاي بنحسب', 'HOW', 'أمثلة محسوبة', 'EX', 'أسئلة شائعة', 'اطلب' ) );
ok( ! in_array( false, $pos, true ) && $pos === array_values( array_unique( $pos ) ) && $pos === ( function ( $p ) { sort( $p ); return $p; } )( $pos ), 'sections in the fixed order (tool, how, examples, faq, cta)' );
ok( false !== strpos( $html, 'id="zt-result"' ) && false !== strpos( $html, 'aria-live="polite"' ), 'reserved aria-live result area' );
$nodes = apply_filters( 'zad_schema_page_nodes', array(), 10 );
ok( 2 === count( $nodes ) && 'WebApplication' === $nodes[0]['@type'] && 'FAQPage' === $nodes[1]['@type'], 'the theme filter zad_schema_page_nodes returns WebApplication + FAQPage' );
$sn = zt_schema_nodes( $ctx );
ok( 'WebApplication' === $sn[0]['@type'] && 'UtilitiesApplication' === $sn[0]['applicationCategory'] && true === $sn[0]['isAccessibleForFree'] && 'ar-SA' === $sn[0]['inLanguage'] && 'https://zadksa.com/#localbusiness' === $sn[0]['provider']['@id'], 'WebApplication node' );
ok( 'FAQPage' === $sn[1]['@type'] && 1 === count( $sn[1]['mainEntity'] ), 'FAQPage node (only real Q&A)' );
echo $fail ? "\n$fail of $n FAILED\n" : "\nall $n checks passed\n"; exit( $fail ? 1 : 0 );
