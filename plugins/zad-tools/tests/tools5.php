<?php
/** Phase-5 PHP tests: WhatsApp Business API channel (locked), image identification (locked): gating, file sniffing, validation of the provider answer, deletion, limits. Run: php tests/tools5.php */
require __DIR__ . '/stub.php';
define( 'DAY_IN_SECONDS', 86400 ); define( 'MB_IN_BYTES', 1048576 );
class WP_REST_Response { public $d; function __construct( $d = null, $s = 200 ) { $this->d = $d; } }
class WP_REST_Request { public $p = array(); function __construct( $p ) { $this->p = $p; } function get_param( $k ) { return $this->p[ $k ] ?? null; } }
function wp_upload_dir() { return array( 'basedir' => sys_get_temp_dir() . '/zt-test-uploads' ); }
class TestDB { public $prefix = 'wp_'; public $updates = array(); public $rows = array(); public $args = array(); public $insert_id = 0;
	function prepare( $q ) { $this->args = array_slice( func_get_args(), 1 ); return $q; } function get_results() { return $this->rows; } function update( $t, $d, $w ) { $this->updates[] = array( $d, $w ); return 1; } function get_var() { return 0; } function insert() { return 1; } }
$wpdb = new TestDB();
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/reminders.php'; require ZT_DIR . 'includes/rest.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/ac-power.php'; require ZT_DIR . 'includes/pests.php'; require ZT_DIR . 'includes/tools/pest-id.php'; require ZT_DIR . 'includes/image-id.php'; require ZT_DIR . 'includes/waba.php';
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function setv( $a ) { update_option( 'zad_tools_opts', $a ); }
function approve_tab( $tab ) { $a = get_option( 'zad_tools_approved', array() ); foreach ( zt_pending_approvals( $tab ) as $p => $f ) { $a[ $p ] = array( 'by' => 1 ); } update_option( 'zad_tools_approved', $a ); }

/* ================= WABA ================= */
class MockWaba implements ZT_WABA_Provider { public $calls = array(); public $result = true;
	function id() { return 'mock'; } function label() { return 'مزود تجريبي'; } function key_constant() { return 'ZAD_WABA_TEST_TOKEN'; }
	function send_template( $phone, $tpl, $lang, $vars ) { $this->calls[] = array( $phone, $tpl, $lang, $vars ); return $this->result; } }
$mw = new MockWaba(); add_filter( 'zt_waba_providers', function ( $p ) use ( $mw ) { $p[] = $mw; return $p; } );
$ch = new ZT_Channel_WABA(); $tplrow = "tank | zad_tank_reminder | ar | {الاسم},{الخدمة},{رابط_الإيقاف}";
ok( 'waba' === $ch->id() && false === $ch->enabled(), 'locked by default' );
ok( isset( zt_pending_approvals( 'waba' )['waba.enabled'] ) && isset( zt_pending_approvals( 'waba' )['waba.allow_transactional'] ) && ! zt_tool_ready( 'waba' ), 'enabling, transactional sending and the run cap are flagged decisions' );
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'mock', 'waba.templates' => $tplrow ) ); ok( false === $ch->enabled(), 'ticked but NOT approved → still locked' );
approve_tab( 'waba' ); ok( false === $ch->enabled(), 'approved but the credential constant is missing → locked' );
define( 'ZAD_WABA_TEST_TOKEN', 'secret-token' );
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'mock', 'waba.templates' => '' ) ); ok( false === $ch->enabled(), 'no template rows → locked' );
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'mock', 'waba.templates' => "tank | t1 | ar | {الاسم},{الخدمة}" ) ); ok( array() === zt_waba_templates() && false === $ch->enabled(), 'a template WITHOUT {رابط_الإيقاف} is ignored (every message carries the unsubscribe link)' );
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'other', 'waba.templates' => $tplrow ) ); ok( false === $ch->enabled(), 'unknown provider → locked' );
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'mock', 'waba.templates' => $tplrow . "\nplan | zad_plan | ar | {الاسم}، {رابط_الإيقاف}، {الحي}" ) );
ok( true === $ch->enabled(), 'everything holds → unlocked' ); $t = zt_waba_templates();
ok( array( '{الاسم}', '{الخدمة}', '{رابط_الإيقاف}' ) === $t['tank'][2] && array( '{الاسم}', '{رابط_الإيقاف}', '{الحي}' ) === $t['plan'][2] && 'ar' === $t['plan'][1], 'template rows parsed (ordered variables, Arabic comma)' );
$r = (object) array( 'id' => 5, 'first_name' => 'أحمد', 'phone' => '0551234567', 'service' => 'تنظيف خزان', 'hood' => 'النرجس', 'source_tool' => 'tank', 'consent_at' => '2026-10-01 10:00:00', 'token' => str_repeat( 'a', 32 ), 'email' => '' );
ok( true === $ch->send( $r, 'x' ), 'consented row with an approved template is sent' );
ok( '966551234567' === $mw->calls[0][0] && 'zad_tank_reminder' === $mw->calls[0][1] && 'أحمد' === $mw->calls[0][3][0] && 'تنظيف خزان' === $mw->calls[0][3][1] && false !== strpos( $mw->calls[0][3][2], 'zad_unsub=' . str_repeat( 'a', 32 ) ), 'international number, template name, ordered variables, unsubscribe link' );
$nc = clone $r; $nc->consent_at = null; $e = $ch->send( $nc, 'x' ); ok( $e instanceof WP_Error && 'consent' === $e->get_error_code() && 1 === count( $mw->calls ), 'no explicit consent → NOT sent' );
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'mock', 'waba.templates' => $tplrow, 'waba.allow_transactional' => 1 ) ); ok( true === $ch->send( $nc, 'x' ), 'a service (transactional) row goes only when that decision is ticked' );
$np = clone $r; $np->source_tool = 'orders-review'; $e = $ch->send( $np, 'x' ); ok( $e instanceof WP_Error && 'no_template' === $e->get_error_code(), 'no template for that tool → stays in the manual queue' );
$mw->result = new WP_Error( 'x', 'RAW provider secret failure' ); $e = $ch->send( $r, 'x' ); ok( $e instanceof WP_Error && 'send' === $e->get_error_code() && false === strpos( $e->get_error_message(), 'RAW' ), 'a provider failure never leaks its raw message' ); $mw->result = true;
/* the daily job */
setv( array( 'waba.enabled' => 1, 'waba.provider' => 'mock', 'waba.templates' => $tplrow, 'waba.max_per_run' => 7 ) );
$ok1 = clone $r; $ok1->id = 1; $bad = clone $nc; $bad->id = 2; $wpdb->rows = array( $ok1, $bad ); $wpdb->updates = array(); $mw->calls = array();
$sent = zt_reminders_send_auto(); ok( 1 === $sent && 1 === count( $wpdb->updates ) && 1 === $wpdb->updates[0][1]['id'] && 'sent' === $wpdb->updates[0][0]['status'], 'cron: only the consented reminder is delivered and marked sent' );
ok( 7 === end( $wpdb->args ), 'cron: the per-run cap setting limits the batch' );
setv( array() ); $wpdb->updates = array(); ok( 0 === zt_reminders_send_auto() && ! $wpdb->updates, 'cron: nothing automatic while locked' );

/* ================= image identification ================= */
$tmpd = sys_get_temp_dir() . '/zt-test-' . getmypid(); @mkdir( $tmpd, 0777, true );
function mkfile( $bytes ) { global $tmpd; $f = $tmpd . '/' . bin2hex( random_bytes( 6 ) ); file_put_contents( $f, $bytes ); return $f; }
$JPG = "\xFF\xD8\xFF\xE0" . str_repeat( 'x', 200 ); $PNG = "\x89PNG\r\n\x1A\n" . str_repeat( 'x', 100 ); $WEBP = 'RIFF' . "\x10\0\0\0" . 'WEBP' . str_repeat( 'x', 50 ); $HEIC = "\0\0\0\x18" . 'ftypheic' . str_repeat( 'x', 50 );
ok( 'image/jpeg' === zt_img_sniff( $JPG ) && 'image/png' === zt_img_sniff( $PNG ) && 'image/webp' === zt_img_sniff( $WEBP ) && 'image/heic' === zt_img_sniff( $HEIC ), 'sniff: jpg / png / webp / heic by bytes' );
ok( null === zt_img_sniff( '%PDF-1.4 ....' ) && null === zt_img_sniff( 'GIF89a......' ) && null === zt_img_sniff( '<?php echo 1;' ) && null === zt_img_sniff( "RIFF\0\0\0\0WAVE" ), 'sniff: pdf, gif, a php file renamed .jpg, a wav → refused' );
class MockVision implements ZT_Vision_Provider { public $ret = array(); public $got = null; public $mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
	function id() { return 'mockv'; } function label() { return 'تجريبي'; } function key_constant() { return 'ZAD_VISION_TEST_KEY'; } function supports( $m ) { return in_array( $m, $this->mimes, true ); }
	function identify( $b, $m, $c ) { $this->got = array( strlen( $b ), $m, $c ); return $this->ret; } }
$pv = new MockVision(); $cand = array( 11 => 'النمل الأبيض / Isoptera', 12 => 'الصراصير', 13 => 'النمل' );
$opts = array( 'max_bytes' => 1000, 'min_conf' => 50, 'max_results' => 2, 'keep' => false, 'store_dir' => $tmpd . '/store' );
$pv->ret = array( array( 'id' => 12, 'confidence' => 0.62 ), array( 'id' => 999, 'confidence' => 0.99 ), array( 'id' => 11, 'confidence' => 0.91 ), array( 'id' => 11, 'confidence' => 0.5 ), array( 'id' => 13, 'confidence' => 0.2 ), array( 'id' => 12, 'confidence' => 1.4 ) );
$f = mkfile( $JPG ); $r = zt_img_process( array( 'tmp' => $f, 'size' => strlen( $JPG ) ), $pv, $cand, $opts );
ok( array( array( 'id' => 11, 'pct' => 91 ), array( 'id' => 12, 'pct' => 62 ) ) === $r, 'answer validated: unknown id dropped, duplicate dropped, 1.4 dropped, 20% below the minimum, sorted by confidence: ' . json_encode( $r ) );
ok( ! file_exists( $f ), 'the uploaded file is DELETED right after the analysis' ); ok( 'image/jpeg' === $pv->got[1] && 3 === count( $pv->got[2] ), 'the provider got the real mime and only the encyclopedia candidates' );
$f = mkfile( $JPG ); $r = zt_img_process( array( 'tmp' => $f, 'size' => 2000 ), $pv, $cand, $opts ); ok( $r instanceof WP_Error && 'size' === $r->get_error_code() && ! file_exists( $f ), 'too big → refused and deleted' );
$f = mkfile( str_repeat( 'x', 1500 ) . $JPG ); $r = zt_img_process( array( 'tmp' => $f, 'size' => 10 ), $pv, $cand, $opts ); ok( $r instanceof WP_Error && 'size' === $r->get_error_code(), 'a lying size field is not trusted (real file size is checked)' ); @unlink( $f );
$f = mkfile( '<?php system($_GET[1]);' ); $r = zt_img_process( array( 'tmp' => $f, 'size' => 30 ), $pv, $cand, $opts ); ok( $r instanceof WP_Error && 'type' === $r->get_error_code() && ! file_exists( $f ), 'a script uploaded as an image → refused and deleted' );
$f = mkfile( $HEIC ); $r = zt_img_process( array( 'tmp' => $f, 'size' => 100 ), $pv, $cand, $opts ); ok( $r instanceof WP_Error && false !== strpos( $r->get_error_message(), 'JPG' ) && ! file_exists( $f ), 'a type the provider cannot read → asks for JPG (file deleted)' );
$pv->ret = new WP_Error( 'provider', 'API KEY INVALID sk-xxxx' ); $f = mkfile( $PNG ); $r = zt_img_process( array( 'tmp' => $f, 'size' => 100 ), $pv, $cand, $opts );
ok( $r instanceof WP_Error && 'provider' === $r->get_error_code() && false === strpos( $r->get_error_message(), 'sk-' ) && false !== strpos( $r->get_error_message(), 'واتساب' ) && ! file_exists( $f ), 'provider failure → friendly message with the WhatsApp fallback; nothing leaked; file deleted' );
$pv->ret = array( array( 'id' => 12, 'confidence' => 0.8 ) ); $r = zt_img_process( array( 'tmp' => mkfile( $PNG ), 'size' => 100 ), $pv, array(), $opts ); ok( $r instanceof WP_Error && 'cfg' === $r->get_error_code(), 'no encyclopedia pests → nothing to match' );
$f = mkfile( $WEBP ); $r = zt_img_process( array( 'tmp' => $f, 'size' => 100 ), $pv, $cand, array_merge( $opts, array( 'keep' => true ) ) ); $s = glob( $tmpd . '/store/*.webp' );
ok( is_array( $r ) && ! file_exists( $f ) && 1 === count( $s ) && preg_match( '/^[a-f0-9]{24}\.webp$/', basename( $s[0] ) ), 'keep ticked → moved to the private folder under a random name' );
ok( false !== strpos( (string) file_get_contents( $tmpd . '/store/.htaccess' ), 'Deny' ) && file_exists( $tmpd . '/store/index.html' ), 'the private folder denies web access' );
$pv->ret = new WP_Error( 'x', 'fail' ); $f = mkfile( $JPG ); zt_img_process( array( 'tmp' => $f, 'size' => 100 ), $pv, $cand, array_merge( $opts, array( 'keep' => true ) ) ); ok( ! file_exists( $f ), 'a failed analysis deletes the file even when keep was ticked' );
ok( 1 === count( glob( $tmpd . '/store/*.{jpg,png,webp,heic}', GLOB_BRACE ) ), 'only the successful kept photo is stored' );
touch( $s[0], time() - 400 * 86400 ); ok( 1 === zt_img_cleanup( $tmpd . '/store', 12 ) && ! glob( $tmpd . '/store/*.webp' ), 'retention: a photo older than the retention months is removed by the daily job' );
ok( 0 === zt_img_cleanup( $tmpd . '/none', 12 ), 'cleanup of a missing folder is harmless' );
/* parser + Claude adapter */
ok( array( array( 'id' => 3, 'confidence' => 0.7 ) ) === zt_vision_parse( "Sure!\n{\"matches\":[{\"id\":3,\"confidence\":0.7},{\"id\":\"x\",\"confidence\":1},{\"id\":4}]} bye" ), 'parser: JSON found inside prose; malformed entries dropped' );
ok( zt_vision_parse( 'no json here' ) instanceof WP_Error && zt_vision_parse( '{"a":1}' ) instanceof WP_Error && array() === zt_vision_parse( '{"matches":[]}' ), 'parser: no JSON / wrong shape → error; empty matches → empty' );
setv( array( 'pest-image.model' => 'model-x' ) ); define( 'ZAD_VISION_API_KEY', 'KEY-123' ); $cap = null;
add_filter( 'zt_http_post_override', function ( $o, $url, $args ) use ( &$cap ) { $cap = array( $url, $args ); return array( 'code' => 200, 'body' => json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => '{"matches":[{"id":11,"confidence":0.77}]}' ) ) ) ) ); }, 10, 3 );
$cl = new ZT_Vision_Claude(); $res = $cl->identify( $JPG, 'image/jpeg', $cand ); $body = json_decode( $cap[1]['body'], true );
ok( array( array( 'id' => 11, 'confidence' => 0.77 ) ) === $res, 'Claude adapter parses the reply' ); ok( 'https://api.anthropic.com/v1/messages' === $cap[0] && 'KEY-123' === $cap[1]['headers']['x-api-key'] && 'model-x' === $body['model'], 'adapter: endpoint, key from the wp-config constant, model from settings' );
ok( false === strpos( $cap[1]['body'], 'KEY-123' ) && 'base64' === $body['messages'][0]['content'][0]['source']['type'] && false !== strpos( $body['messages'][0]['content'][1]['text'], '11: النمل الأبيض / Isoptera' ) && false !== strpos( $body['messages'][0]['content'][1]['text'], 'Never invent ids' ), 'adapter: the key is not in the body; the prompt lists only the candidates' );
ok( ! $cl->supports( 'image/heic' ) && $cl->supports( 'image/webp' ), 'adapter supports jpg/png/webp only' );
add_filter( 'zt_http_post_override', function () { return array( 'code' => 500, 'body' => 'x' ); }, 20, 3 ); ok( $cl->identify( $JPG, 'image/jpeg', $cand ) instanceof WP_Error, 'HTTP 500 → error' );
/* the unlock gate */
ok( false === zt_img_available(), 'locked by default' ); ok( isset( zt_pending_approvals( 'pest-image' )['pest-image.enabled'] ) && isset( zt_pending_approvals( 'pest-image' )['pest-image.daily_cap'] ) && isset( zt_pending_approvals( 'pest-image' )['pest-image.per_ip_hour'] ), 'enabling and every cost limit are flagged' );
add_filter( 'zt_vision_providers', function ( $p ) use ( $pv ) { $p[] = $pv; return $p; } ); approve_tab( 'pest-image' );
setv( array( 'pest-image.enabled' => 1, 'pest-image.provider' => 'mockv' ) ); ok( false === zt_img_available(), 'enabled + approved + provider but NO key constant → locked' );
define( 'ZAD_VISION_TEST_KEY', 'k' ); ok( true === zt_img_available(), 'all conditions → unlocked' ); setv( array( 'pest-image.enabled' => 0, 'pest-image.provider' => 'mockv' ) ); ok( false === zt_img_available(), 'switched off → locked' );
setv( array( 'pest-image.enabled' => 1, 'pest-image.provider' => '' ) ); ok( false === zt_img_available(), 'no provider chosen → locked' );
/* page section + REST gate */
setv( array() ); ok( '' === zt_img_section_html(), 'locked: the identifier page prints nothing about images' );
setv( array( 'pest-image.enabled' => 1, 'pest-image.provider' => 'mockv' ) ); $h = zt_img_section_html();
ok( false !== strpos( $h, 'data-zt-image' ) && false !== strpos( $h, 'zt-jsonly' ) && false !== strpos( $h, 'accept="image/jpeg,image/png,image/webp,image/heic"' ), 'unlocked: upload section (JS-only)' );
ok( 2 === substr_count( $h, 'type="checkbox"' ) && false === strpos( $h, 'checked' ) && false !== strpos( $h, 'name="website"' ) && false !== strpos( $h, 'خدمة تحليل خارجية' ), 'two UNCHECKED consents (analysis, keep), honeypot, external-service disclosure' );
$t = zt_token_make(); $base = array( 't' => $t, 'consent_analyze' => true );
setv( array() ); $e = zt_rest_pest_image( new WP_REST_Request( $base ) ); ok( $e instanceof WP_Error && 'zt_off' === $e->get_error_code(), 'REST: locked → 404' );
setv( array( 'pest-image.enabled' => 1, 'pest-image.provider' => 'mockv', 'pest-image.per_ip_hour' => 2, 'pest-image.daily_cap' => 2 ) );
$e = zt_rest_pest_image( new WP_REST_Request( array( 't' => 'bad' ) ) ); ok( $e instanceof WP_Error && 'zt_token' === $e->get_error_code(), 'REST: needs the cache-safe token' );
$e = zt_rest_pest_image( new WP_REST_Request( array( 't' => $t ) ) ); ok( $e instanceof WP_Error && 'zt_consent' === $e->get_error_code(), 'REST: refuses without consent to send the photo' );
$r2 = zt_rest_pest_image( new WP_REST_Request( array( 'website' => 'spam' ) + $base ) ); ok( $r2 instanceof WP_REST_Response && ! isset( $r2->d['cards'] ), 'REST: honeypot looks like success, analyses nothing' );
$e = zt_rest_pest_image( new WP_REST_Request( $base ) ); ok( $e instanceof WP_Error && 'zt_file' === $e->get_error_code(), 'REST: no file → refused' );
$e = zt_rest_pest_image( new WP_REST_Request( $base ) ); ok( $e instanceof WP_Error && 'zt_rate' === $e->get_error_code(), 'REST: per-IP hourly limit (2) is enforced' );
$GLOBALS['T'] = array(); ok( true === zt_img_daily_ok() && true === zt_img_daily_ok() && false === zt_img_daily_ok(), 'daily cap: 2 then stop' );
exec( 'rm -rf ' . escapeshellarg( $tmpd ) );
echo $fail ? "\n$fail of $n FAILED\n" : "tools5: all $n passed\n";
exit( $fail ? 1 : 0 );
