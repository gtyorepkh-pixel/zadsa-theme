<?php
/** Phase-1 tests (orders, tracking, warranty, verification) with a WordPress stub. Run: php tests/orders.php */
define( 'ABSPATH', '/x/' ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 ); define( 'ZT_DIR', dirname( __DIR__ ) . '/' ); define( 'ZT_URL', '/p/' ); define( 'ZT_VERSION', 't' );
$GLOBALS['O'] = array(); $GLOBALS['T'] = array(); $GLOBALS['M'] = array(); $GLOBALS['P'] = array(); $GLOBALS['F'] = array(); $GLOBALS['REM'] = array(); $GLOBALS['CAP'] = true; $GLOBALS['NEXT'] = 100;
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_code() { return $this->c; } function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function add_action( $t, $f ) { $GLOBALS['F'][ $t ][] = $f; } function add_shortcode() {} function register_activation_hook() {} function register_deactivation_hook() {}
function add_filter( $t, $f ) { $GLOBALS['F'][ $t ][] = $f; }
function apply_filters( $t, $v ) { foreach ( (array) ( $GLOBALS['F'][ $t ] ?? array() ) as $f ) { $v = call_user_func_array( $f, array_slice( func_get_args(), 1 ) ); } return $v; }
function do_action( $t ) { foreach ( (array) ( $GLOBALS['F'][ $t ] ?? array() ) as $f ) { call_user_func_array( $f, array_slice( func_get_args(), 1 ) ); } }
function get_option( $k, $d = false ) { return $GLOBALS['O'][ $k ] ?? $d; } function update_option( $k, $v ) { $GLOBALS['O'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['T'][ $k ] ?? false; } function set_transient( $k, $v ) { $GLOBALS['T'][ $k ] = $v; }
function wp_salt() { return 's'; } function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); } function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); } function is_email( $s ) { return true; } function wp_unslash( $s ) { return $s; } function absint( $n ) { return abs( (int) $n ); } function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $s ) ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); } function current_time() { return gmdate( 'Y-m-d H:i:s' ); }
function get_bloginfo() { return 'زاد'; } function home_url( $p = '' ) { return 'https://zadksa.com' . $p; } function add_query_arg( $k, $v, $u = '' ) { return ( $u ?: 'https://zadksa.com/' ) . ( false === strpos( $u ?: '?', '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); } function esc_attr( $s ) { return htmlspecialchars( (string) $s ); } function esc_url( $s ) { return htmlspecialchars( (string) $s ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); } function current_user_can( $c ) { return $GLOBALS['CAP']; } function get_current_user_id() { return 1; }
function get_post_type( $id ) { return $GLOBALS['P'][ $id ]['type'] ?? ''; } function get_the_title( $id ) { return $GLOBALS['P'][ $id ]['title'] ?? ''; }
function wp_insert_post( $a ) { $id = $GLOBALS['NEXT']++; $GLOBALS['P'][ $id ] = array( 'type' => $a['post_type'], 'title' => $a['post_title'] ); $GLOBALS['M'][ $id ] = array(); return $id; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['M'][ $id ][ $k ] = $v; } function get_post_meta( $id, $k ) { return $GLOBALS['M'][ $id ][ $k ] ?? ''; }
function get_post_thumbnail_url() { return ''; } function get_the_post_thumbnail_url() { return 'https://zadksa.com/t.jpg'; } function get_edit_post_link() { return ''; }
function get_posts( $a ) { // enough of get_posts for token / code / technician lookups
	$out = array(); $mk = $a['meta_key'] ?? null; $mv = $a['meta_value'] ?? null;
	foreach ( $GLOBALS['P'] as $id => $p ) { if ( ( $a['post_type'] ?? '' ) !== $p['type'] ) { continue; } if ( $mk && ( $GLOBALS['M'][ $id ][ $mk ] ?? null ) != $mv ) { continue; } $out[] = $id; }
	return $out; }
function get_role() { return null; } function add_role() {} function wp_is_post_revision() { return false; } function nocache_headers() {} function get_query_var() { return ''; }
function zad_warranty_months( $sid ) { return array( 1 => 180, 2 => 3, 3 => 0 )[ $sid ] ?? 0; } // 1 termite, 2 bedbug, 3 roach (no fixed number)
function zad_opt( $k, $d = '' ) { return array( 'zad_legal_name' => 'شركة زاد', 'zad_cr' => '123', 'zad_vat' => '456' )[ $k ] ?? $d; }
function zad_card_title( $id ) { return 'خدمة ' . $id; }
class FakeDB { public $prefix = 'wp_'; public $insert_id = 0; public $q = array(); function prepare( $q ) { return $q; } function get_var() { return array_shift( $this->q ); } function insert( $t, $d ) { $GLOBALS['REM'][] = $d; $this->insert_id = count( $GLOBALS['REM'] ); return 1; } }
$wpdb = new FakeDB(); $_SERVER['REMOTE_ADDR'] = '1.1.1.1'; $_SERVER['HTTP_USER_AGENT'] = 'UA';
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/reminders.php'; require ZT_DIR . 'includes/tool-page.php'; require ZT_DIR . 'includes/orders.php'; require ZT_DIR . 'includes/tracking.php';

$fail = 0; $n = 0; function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }

/* pure helpers */
ok( '2026-02-28' === zt_add_months( '2026-01-31', 1 ), 'Jan 31 + 1 month clamps to Feb 28' );
ok( '2028-02-29' === zt_add_months( '2027-08-31', 6 ), 'Aug 31 2027 + 6 months = Feb 29 2028 (leap)' );
ok( '2041-10-09' === zt_add_months( '2026-10-09', 180 ), '180 months = 15 years' );
ok( '2027-01-09' === zt_add_months( '2026-10-09', 3 ), '3 months across the year end' );
ok( 'أ***د' === zt_mask_name( 'أحمد' ) && 'م***' === zt_mask_name( 'مي' ) && '***' === zt_mask_name( '' ), 'name masking' );
ok( 'أحمد' === zt_first_name( '  أحمد  بن علي ' ), 'first name only' );
$c = zt_code_make( '2610' ); ok( preg_match( '/^ZAD-2610-[A-HJ-NP-Z2-9]{6}$/', $c ) === 1, "code shape $c" );
ok( $c === zt_code_normalize( strtolower( str_replace( '-', ' ', $c ) ) ), 'code normalises (case, spaces, dashes)' );
ok( 'ZAD-2610-8K4Q7M' === zt_code_normalize( 'zad٢٦١٠8k4q7m' ), 'Arabic digits in a code' );
$codes = array(); for ( $i = 0; $i < 200; $i++ ) { $codes[ zt_code_make( '2610' ) ] = 1; } ok( count( $codes ) > 195, 'codes are (practically) unique' );
ok( array( 'شهر', 'شهران', '3 أشهر', '6 أشهر', 'سنة', 'سنتان', '3 سنوات', '15 عاماً', '14 شهراً' ) === array_map( 'zt_months_label', array( 1, 2, 3, 6, 12, 24, 36, 180, 14 ) ), 'months label (Arabic number agreement)' );
ok( 'received' === zt_order_flow()[0] && 'confirmed' === zt_order_next( 'received' ) && '' === zt_order_next( 'done' ) && '' === zt_order_next( 'cancelled' ), 'flow' );

/* create from a lead (the theme action) — the visitor never sees a failure */
do_action( 'zad_lead_created', 7, array( 'name' => 'أحمد بن علي', 'phone' => '٠٥٥١٢٣٤٥٦٧', 'service_id' => 2, 'service' => 'بق الفراش', 'section' => '', 'city' => 'الرياض', 'hood' => 'النرجس', 'date' => '2026-10-20', 'time' => 'صباحاً', 'message' => 'x', 'wizard' => true ) );
$oid = 100; ok( 'zad_order' === get_post_type( $oid ), 'order created from the lead' );
$o = zt_order_get( $oid ); ok( 'أحمد' === $o['first_name'] && '0551234567' === $o['phone'] && 'wizard' === $o['source'] && 'received' === $o['status'] && 32 === strlen( $o['token'] ), 'first name only, normalised phone, wizard, token' );
do_action( 'zad_lead_created', 8, array( 'name' => 'x', 'phone' => '12', 'service_id' => 0, 'service' => '', 'city' => '', 'hood' => '', 'date' => '', 'time' => '', 'message' => '', 'wizard' => false, 'section' => '' ) );
ok( 101 === $GLOBALS['NEXT'], 'a bad phone creates nothing and breaks nothing' );
ok( $oid === zt_order_by_token( $o['token'] ) && 0 === zt_order_by_token( 'zz' ) && 0 === zt_order_by_token( str_repeat( 'a', 32 ) ), 'token lookup' );

/* status flow + warranty issue */
ok( is_wp_error( zt_order_set_status( $oid, 'assigned', 1 ) ) && 'tech' === zt_order_set_status( $oid, 'assigned', 1 )->get_error_code(), 'assigned needs a technician' );
$GLOBALS['P'][500] = array( 'type' => 'zad_technician', 'title' => 'سعد' ); update_post_meta( $oid, '_zt_technician_id', 500 ); update_post_meta( 500, '_zt_years', 7 );
foreach ( array( 'confirmed', 'assigned', 'on_the_way', 'arrived' ) as $s ) { ok( true === zt_order_set_status( $oid, $s, 1 ), "to $s" ); }
$GLOBALS['wpdb']->q = array( 0, 0 ); // reminder cap + duplicate checks
$orders_ready_before = count( $GLOBALS['REM'] );
ok( true === zt_order_set_status( $oid, 'done', 1 ), 'done (bed bug: 3 months from the theme policy)' );
$o = zt_order_get( $oid ); $today = wp_date( 'Y-m-d' );
ok( 'done' === $o['status'] && 3 === $o['warranty_months'] && $o['warranty_end'] === zt_add_months( $today, 3 ) && preg_match( '/^ZAD-\d{4}-[A-Z2-9]{6}$/', $o['warranty_code'] ), 'warranty issued: months from policy, end date, code' );
ok( count( $GLOBALS['REM'] ) === $orders_ready_before + 1 && 'orders-review' === end( $GLOBALS['REM'] )['source_tool'] && 'service' === end( $GLOBALS['REM'] )['consent_ver'] && null === end( $GLOBALS['REM'] )['consent_at'], 'one review reminder, transactional (no marketing consent)' );
ok( 7 === count( $o['log'] ) - 0 || 6 === count( $o['log'] ) || count( $o['log'] ) >= 6, 'status log kept' );

/* termite (15 years) and roach (must be entered) */
$mk = function ( $sid ) { $id = zt_order_create( array( 'name' => 'سعد', 'phone' => '0551234567', 'service_id' => $sid, 'service' => 's' ) ); update_post_meta( $id, '_zt_technician_id', 500 ); foreach ( array( 'confirmed', 'assigned', 'on_the_way', 'arrived' ) as $s ) { zt_order_set_status( $id, $s, 1 ); } return $id; };
$t = $mk( 1 ); $GLOBALS['wpdb']->q = array( 0, 0 ); zt_order_set_status( $t, 'done', 1 ); ok( 180 === zt_order_get( $t )['warranty_months'] && zt_order_get( $t )['warranty_end'] === zt_add_months( $today, 180 ), 'termite: 15 years from the unified policy' );
$r = $mk( 3 ); $res = zt_order_set_status( $r, 'done', 1 ); ok( is_wp_error( $res ) && 'warranty_months' === $res->get_error_code() && 'arrived' === zt_order_get( $r )['status'], 'roach: «done» refused until the agreed months are entered' );
update_post_meta( $r, '_zt_warranty_months', 6 ); $GLOBALS['wpdb']->q = array( 0, 0 ); ok( true === zt_order_set_status( $r, 'done', 1 ) && 6 === zt_order_get( $r )['warranty_months'], 'roach: the entered 6 months is used' );

/* technician rules (not an admin) */
$GLOBALS['CAP'] = false;
$GLOBALS['P'][501] = array( 'type' => 'zad_technician', 'title' => 'خالد' ); update_post_meta( 501, '_zt_user_id', 1 );
$x = zt_order_create( array( 'name' => 'ليلى', 'phone' => '0551234567', 'service_id' => 2, 'service' => 's' ) ); update_post_meta( $x, '_zt_technician_id', 500 );
ok( 'cap' === zt_order_set_status( $x, 'confirmed', 1 )->get_error_code(), 'a technician cannot touch an order assigned to someone else' );
update_post_meta( $x, '_zt_technician_id', 501 );
ok( true === zt_order_set_status( $x, 'confirmed', 1 ) && is_wp_error( zt_order_set_status( $x, 'cancelled', 1 ) ) && is_wp_error( zt_order_set_status( $x, 'received', 1 ) ), 'a technician moves forward only, never cancels / goes back' );
$GLOBALS['CAP'] = true;

/* the public view: no phone, no address, no full name */
$v = zt_track_public( $oid ); $json = json_encode( $v, JSON_UNESCAPED_UNICODE );
ok( false === strpos( $json, '0551234567' ) && false === strpos( $json, 'بن علي' ) && 'سعد' === $v['technician']['name'] && 7 === $v['technician']['years'], 'public view: technician shown, no phone / full name' );
ok( true === $v['warranty']['valid'] && $v['warranty']['code'] === zt_order_get( $oid )['warranty_code'], 'public view: warranty with code' );

/* verification */
$code = zt_order_get( $oid )['warranty_code'];
$ver = zt_verify_lookup( strtolower( $code ) ); ok( 'valid' === $ver['state'] && 'أ***د' === $ver['name'] && false === strpos( json_encode( $ver, JSON_UNESCAPED_UNICODE ), '0551' ), 'verify: valid, name masked, no phone' );
ok( 'invalid' === zt_verify_lookup( 'ZAD-2610-AAAAAA' )['state'] && 'invalid' === zt_verify_lookup( '' )['state'] && 'invalid' === zt_verify_lookup( "' OR 1=1" )['state'], 'verify: unknown / empty / injection → the same generic answer' );
update_post_meta( $oid, '_zt_warranty_end', '2020-01-01' ); ok( 'expired' === zt_verify_lookup( $code )['state'], 'verify: expired' );

/* settings: the order pages wait for approvals */
ok( ! zt_orders_ready(), 'order pages blocked until the privacy + order values are approved' );
$a = array(); foreach ( zt_pending_approvals() as $p => $f ) { $a[ $p ] = 1; } update_option( 'zad_tools_approved', $a ); ok( zt_orders_ready(), 'ready after approval' );
ok( 24 === (int) zt_opt( 'orders.review_delay_hours' ) && 14 === (int) zt_opt( 'orders.warranty_notice_days' ) && 10 === (int) zt_opt( 'orders.verify_per_hour' ), 'the order\'s own numbers (24h, 14 days, 10/hour) are settings' );

/* Google review link: shown to everyone, from the Place ID only */
ok( '' === zt_review_url(), 'no Place ID → no review button' ); update_option( 'zad_tools_opts', array( 'general.google_place_id' => 'ChIJabc' ) ); ok( 'https://search.google.com/local/writereview?placeid=ChIJabc' === zt_review_url(), 'review link from Place ID' );
ob_start(); zt_render_after_service( zt_order_get( $oid ), $v ); $h = ob_get_clean();
ok( false !== strpos( $h, 'قيّمنا على Google' ) && false === strpos( $h, 'راضٍ' ) && false === strpos( $h, 'هل أنت' ), 'review button rendered without any satisfaction question' );
echo $fail ? "\n$fail of $n FAILED\n" : "\nall $n checks passed\n"; exit( $fail ? 1 : 0 );
