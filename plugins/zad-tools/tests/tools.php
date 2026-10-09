<?php
/** Phase-2 PHP tests (settings gate, defaults, server-rendered pages, .ics) with the WordPress stub. Run: php tests/tools.php */
require __DIR__ . '/stub.php';
function zad_min_price( $rows ) { return $rows ? min( $rows ) : null; } function zad_price_rows( $id ) { return 77 === $id ? array( 180, 260 ) : array(); }
function zt_wa_number_stub() {}
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/reminders.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/ac-size.php'; require ZT_DIR . 'includes/tools/after-spray.php'; require ZT_DIR . 'includes/tools/tank.php';
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function approve_tab( $tab ) { $a = get_option( 'zad_tools_approved', array() ); foreach ( zt_pending_approvals( $tab ) as $p => $f ) { $a[ $p ] = array( 'by' => 1 ); } update_option( 'zad_tools_approved', $a ); }

/* the three tools register and wait for approval */
$tools = zt_tools();
ok( isset( $tools['ac-size'], $tools['after-spray'], $tools['tank'] ), 'three tools registered' );
ok( ! zt_tool_ready( 'ac-size' ) && ! zt_tool_ready( 'after-spray' ) && ! zt_tool_ready( 'tank' ), 'all three closed until approved' );
ok( count( zt_pending_approvals( 'ac-size' ) ) >= 10, 'AC: every coefficient is flagged «تحتاج اعتماد»' );
ok( isset( zt_pending_approvals( 'after-spray' )['after-spray.matrix'] ), 'spray matrix flagged' );
ok( isset( zt_pending_approvals( 'tank' )['tank.locations'] ), 'tank interval flagged' );
ok( ! isset( zt_pending_approvals( 'tank' )['tank.liters_per_person'] ), 'consumption is empty on purpose, not a flagged guess' );
ok( '' === (string) zt_opt( 'tank.liters_per_person' ) && '' === (string) zt_opt( 'tank.price_tiers' ), 'no consumption / tier prices invented' );
approve_tab( 'ac-size' ); approve_tab( 'after-spray' ); approve_tab( 'tank' );
ok( zt_tool_ready( 'ac-size' ) && zt_tool_ready( 'after-spray' ) && zt_tool_ready( 'tank' ), 'ready after approval' );
ok( empty( $tools['tank']['collects_data'] ) === false && empty( $tools['ac-size']['collects_data'] ), 'only the tank tool (reminder) is a collects_data tool' );

/* AC defaults */
$cfg = zt_ac_cfg();
ok( 7 === count( $cfg['sizes'] ) && 12000 === $cfg['sizes'][0] && 60000 === $cfg['sizes'][6], 'AC sizes parsed + sorted' );
ok( 4 === count( $cfg['rooms'] ) && 'bedroom' === $cfg['rooms'][0]['k'] && -10 === $cfg['sun'][1]['p'], 'AC tables parsed, negative % kept' );
$v = zt_ac_view( $cfg, array( 'a' => 16 ) ); ok( 'مكيف 1 طن' === $v['big'], 'AC 16 m² = 1 ton with the proposed defaults: ' . $v['big'] );
$ex = zt_ac_examples( array() ); ok( 32 + 3 + 1 <= substr_count( $ex, '<tr>' ), 'AC examples: 3 worked rows + 9–40 m² reference rows (' . substr_count( $ex, '<tr>' ) . ')' );
ok( false !== strpos( $ex, '<th scope="row">9</th>' ) && false !== strpos( $ex, '<th scope="row">40</th>' ) && false === strpos( $ex, '<th scope="row">41</th>' ), 'reference table spans exactly 9–40' );
$how = zt_ac_how( array() ); ok( false !== strpos( $how, '700' ) && false !== strpos( $how, 'الدور الأخير' ), 'how-we-calculate reads the settings live' );
update_option( 'zad_tools_opts', array( 'ac-size.base_btu_per_m2' => 800 ) ); ok( false !== strpos( zt_ac_how( array() ), '800' ), 'changing a setting changes the explanation' );
update_option( 'zad_tools_opts', array() );

/* AC page: form + server-rendered result for a shared link */
$_GET = array();
ob_start(); zt_ac_render( array( 'url' => 'https://zadksa.com/tools/ac/' ) ); $form = ob_get_clean();
ok( preg_match( '/data-cfg="([^"]+)"/', $form, $m ) && is_array( json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true ) ), 'form carries valid JSON config' );
ok( false !== strpos( $form, 'method="get"' ), 'no-JS fallback is a GET form' );
ok( '' === zt_ac_result( array() ), 'no result without a query' );
$_GET = array( 'l' => '٤', 'w' => '4', 'top' => '1', 'p' => '4', 'hood' => 'النرجس' );
$r = zt_ac_result( array() ); ok( false !== strpos( $r, 'مكيف 1.5 طن' ) && false !== strpos( $r, 'zt-card' ), 'shared link prints the answer on the server (Arabic digits accepted)' );
ob_start(); zt_ac_render( array( 'url' => 'x' ) ); $form2 = ob_get_clean(); ok( false !== strpos( $form2, 'name="top" value="1" checked' ) && false !== strpos( $form2, 'value="4"' ), 'form is refilled from the URL' );
$_GET = array( 'a' => 'abc' ); ok( false !== strpos( zt_ac_result( array() ), 'role="alert"' ), 'bad input → inline error' );
$_GET = array( 'a' => array( 'x' ) ); ok( is_string( zt_ac_result( array() ) ), 'array params do not crash' );

/* spray defaults */
$_GET = array(); $sc = zt_spray_cfg();
ok( 6 === ( (array) $sc['matrix'] )['general|spray'] && 1 === count( (array) $sc['matrix'] ), 'matrix: only the one value that exists on the site (6 h)' );
ok( 'الفني هيحدد المدة بعد المعاينة' === zt_spray_view( $sc, array( 'ps' => 'roach', 'md' => 'spray', 'f' => array() ) )['big'], 'every other cell → technician decides' );
ok( 'ارجع بعد 6 ساعات' === zt_spray_view( $sc, array( 'ps' => 'general', 'md' => 'spray', 'f' => array( 'kids' ) ) )['big'], 'factor without minimum hours does not change the time' );
ok( 3 === count( $sc['factors'] ) && null === $sc['factors'][0]['min'], 'factors parsed, empty minimum stays null' );
ok( 4 === count( $sc['before'] ) && 0 === count( $sc['during'] ) && 3 === count( $sc['after'] ), 'instruction lists parsed' );
$_GET = array( 'ps' => 'general', 'md' => 'spray', 'f_kids' => '1', 't' => '21:00' ); $q = zt_spray_request( $sc ); ok( array( 'kids' ) === $q['f'], 'GET checkboxes → factors' );
$r = zt_spray_result( array() ); ok( false !== strpos( $r, 'ارجع بعد 6 ساعات' ) && false !== strpos( $r, '3:00 ص' ), 'spray shared link: 21:00 + 6h = 3:00 ص' );
ok( false !== strpos( zt_spray_how( array() ), 'الفني يحدد' ), 'matrix table shows empty cells as «الفني يحدد»' );
ok( 1 === substr_count( zt_spray_examples( array() ), '<th scope="row">' ), 'examples list only cells that have a value' );

/* tank */
$_GET = array(); $tc = zt_tank_cfg();
ok( 2 === count( $tc['locs'] ) && 6 === $tc['locs'][0]['m'] && null === $tc['lpp'] && array() === $tc['tiers'] && null === $tc['fromPrice'], 'tank defaults: no prices, no consumption' );
update_option( 'zad_tools_opts', array( 'tank.price_tiers' => "3000 | 220\n# comment\n1000 | 150\nbad", 'tank.liters_per_person' => '٢٠٠', 'tank.service_page' => 77 ) );
$tc = zt_tank_cfg(); ok( 1000 === $tc['tiers'][0]['max'] && 220 === $tc['tiers'][1]['price'] && 200 === $tc['lpp'] && null === $tc['fromPrice'], 'tiers sorted by capacity, junk ignored, tiers beat the page price' );
update_option( 'zad_tools_opts', array( 'tank.service_page' => 77 ) ); $tc = zt_tank_cfg(); ok( 180 === $tc['fromPrice'], '«يبدأ من» is read from the service page through the theme price reader' );
update_option( 'zad_tools_opts', array() );
$_GET = array( 'loc' => 'ground', 'shape' => 'cyl_v', 'a' => '1.5', 'b' => '2', 'unit' => 'm', 'last' => '2099-01-01' ); ok( false !== strpos( zt_tank_result( array() ), 'role="alert"' ), 'future last-cleaning date refused on the server' );
$_GET = array( 'loc' => 'ground', 'shape' => 'rect', 'a' => '2', 'b' => '1', 'c' => '1', 'unit' => 'm', 'last' => date( 'Y-m-d', strtotime( '-1 month' ) ) );
$r = zt_tank_result( array() ); ok( false !== strpos( $r, '2,000 لتر' ) && false !== strpos( $r, 'data-zt-optin' ) && false !== strpos( $r, 'hidden' ), 'tank shared link: litres + hidden opt-in form' );
ok( false === strpos( $r, 'name="consent" value="1" checked' ), 'consent checkbox is never pre-checked' );
ok( false !== strpos( $r, 'name="website"' ), 'honeypot present' );
ok( false !== strpos( zt_tank_examples( array() ), '2,000 لتر' ), 'reference sizes computed from the formula' );
ok( false !== strpos( zt_tank_how( array() ), 'غير محدد' ), 'consumption not set is explained' );

/* .ics */
$ics = zt_ics_build( '2027-02-28', 'تنظيف خزان المياه', 'زاد', '20261009T100000Z', 'zadksa.com' );
ok( 0 === strpos( $ics, "BEGIN:VCALENDAR\r\n" ) && "END:VCALENDAR\r\n" === substr( $ics, -15 ), 'ics envelope + CRLF' );
ok( false !== strpos( $ics, "DTSTART;VALUE=DATE:20270228\r\n" ) && false !== strpos( $ics, "DTEND;VALUE=DATE:20270301\r\n" ), 'all-day event, end = next day (2027 is not a leap year)' );
$bad = false; foreach ( explode( "\r\n", $ics ) as $line ) { if ( strlen( $line ) > 75 ) { $bad = true; } } ok( ! $bad, 'every line ≤ 75 octets (folded)' );
ok( false !== strpos( $ics, 'SUMMARY:' ) && ! preg_match( '/05\d{8}/', $ics ), 'no personal data in the file' );
ok( '' === zt_ics_build( '2027-02-30', 'x', '', 'z' ) && '' === zt_ics_build( 'bad', 'x', '', 'z' ), 'invalid dates produce no file' );
$long = zt_ics_build( '2027-01-01', str_repeat( 'خزان ', 40 ), '', 'z' ); $bad = false; foreach ( explode( "\r\n", $long ) as $line ) { if ( strlen( $line ) > 75 ) { $bad = true; } } ok( ! $bad && false !== strpos( $long, "\r\n " ), 'long Arabic title folds on character boundaries' );
ok( false !== strpos( zt_ics_escape( "a,b;c\nd" ), 'a\,b\;c\nd' ), 'ics text escaping' );

/* shared renderer: the whole card is escaped */
$h = zt_result_html( array( 'big' => '<script>', 'lines' => array( '"x"' ), 'wa' => 'a b' ), array( 'wa' => '966555' ) );
ok( false === strpos( $h, '<script>' ) && false !== strpos( $h, 'href="https://wa.me/966555?text=a%20b"' ), 'renderer escapes; wa link built' );
ok( false === strpos( zt_result_html( array( 'big' => 'x', 'wa' => 'a' ), array() ), 'wa.me' ), 'no WhatsApp number → no button' );

/* schema: WebApplication (+ FAQPage) per tool, no duplicated theme nodes */
foreach ( array( 'ac-size', 'after-spray', 'tank' ) as $slug ) {
	$GLOBALS['META'][1] = array( '_zt_tool' => $slug, '_zt_faq' => "س؟ | ج." ); $ctx = zt_ctx( 1 ); $nodes = zt_schema_nodes( $ctx );
	$types = array_column( $nodes, '@type' ); ok( array( 'WebApplication', 'FAQPage' ) === $types, "$slug schema nodes: " . implode( ',', $types ) );
	ok( isset( $nodes[0]['@id'], $nodes[0]['provider']['@id'], $nodes[0]['mainEntityOfPage']['@id'] ) && ! isset( $nodes[0]['aggregateRating'] ) && ! isset( $nodes[0]['review'] ), "$slug: no rating/review invented" );
	ok( null !== json_decode( wp_json_encode( $nodes ) ), "$slug: valid JSON-LD" );
}

echo $fail ? "\n$fail of $n FAILED\n" : "tools: all $n passed\n";
exit( $fail ? 1 : 0 );
