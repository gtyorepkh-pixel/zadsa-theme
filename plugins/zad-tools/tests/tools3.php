<?php
/** Phase-3 PHP tests: settings gates + honest defaults, config parsing, server-rendered pages, coverage data, ETA rule, multi-event .ics, batch reminders. Run: php tests/tools3.php */
require __DIR__ . '/stub.php';
function zad_min_price( $rows ) { return $rows ? min( $rows ) : null; } function zad_price_rows( $id ) { return 77 === $id ? array( 180, 260 ) : array(); }
class WP_REST_Response { public $d; function __construct( $d = null, $s = 200 ) { $this->d = $d; } }
class WP_REST_Request { public $p = array(); function __construct( $p ) { $this->p = $p; } function get_param( $k ) { return $this->p[ $k ] ?? null; } }
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/reminders.php'; require ZT_DIR . 'includes/rest.php'; require ZT_DIR . 'includes/tool-page.php';
foreach ( array( 'ac-size', 'after-spray', 'tank', 'ac-power', 'moving', 'plan', 'coverage' ) as $t ) { require ZT_DIR . "includes/tools/$t.php"; }
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function approve_tab( $tab ) { $a = get_option( 'zad_tools_approved', array() ); foreach ( zt_pending_approvals( $tab ) as $p => $f ) { $a[ $p ] = array( 'by' => 1 ); } update_option( 'zad_tools_approved', $a ); }
function setv( $arr ) { update_option( 'zad_tools_opts', $arr ); }

/* ---------- gates ---------- */
$tools = zt_tools();
ok( isset( $tools['ac-power'], $tools['moving'], $tools['plan'], $tools['coverage'] ), 'four new tools registered' );
ok( ! empty( $tools['plan']['collects_data'] ) && empty( $tools['moving']['collects_data'] ) && empty( $tools['ac-power']['collects_data'] ), 'only the plan (reminders) collects data' );
ok( ! zt_tool_ready( 'ac-power' ) && isset( zt_pending_approvals( 'ac-power' )['ac-power.kw_per_ton'] ), 'electricity: kW/ton flagged, closed until approved' );
ok( ! isset( zt_pending_approvals( 'ac-power' )['ac-power.cleans'] ) && ! isset( zt_pending_approvals( 'ac-power' )['ac-power.tariff'] ), 'cleaning % and tariff are NOT flagged defaults — they are empty' );
ok( '' === trim( (string) zt_opt( 'ac-power.tariff' ) ) && '' === trim( (string) zt_opt( 'ac-power.season_months' ) ), 'tariff + season empty by default (nothing invented)' );
ok( ! preg_match( '/\|\s*\d/', preg_replace( '/^[^|]+\|[^|]+\|/m', '', (string) zt_opt( 'ac-power.cleans' ) ) ), 'cleaning table has labels only — every percentage blank' );
ok( ! zt_tool_ready( 'moving' ), 'moving closed while the rooms table is empty (must_fill)' );
setv( array( 'moving.rooms' => "2 | 1 | 2 | 300-400\n | 3 | 6 | " ) ); ok( zt_tool_ready( 'moving' ), 'moving opens once the rooms table is filled' ); setv( array() );
ok( ! zt_tool_ready( 'plan' ) && isset( zt_pending_approvals( 'plan' )['plan.tasks'] ), 'plan rules flagged' );
ok( zt_tool_ready( 'coverage' ), 'coverage has no flagged values (min orders = 10 comes from the order)' );
ok( 10 === (int) zt_opt( 'coverage.min_orders' ), 'min orders default 10' );
approve_tab( 'ac-power' ); approve_tab( 'plan' ); ok( zt_tool_ready( 'ac-power' ) && zt_tool_ready( 'plan' ), 'approval opens electricity + plan' );

/* ---------- electricity cfg + pages ---------- */
$_GET = array(); $c = zt_pow_cfg();
ok( 3 === count( $c['types'] ) && 3 === count( $c['ages'] ) && 9 === count( (array) $c['kw'] ), 'kW table parsed' );
ok( array() === (array) $c['dirt'] && array() === $c['tiers'] && null === $c['season'] && null === $c['cleanFrom'], 'no dirt / tiers / season / price by default' );
$v = zt_pow_view( $c, array( 'acs' => array( array( 't' => 2, 'q' => 1, 'ty' => 'split', 'ag' => 'new' ) ), 'h' => 10, 'd' => 30, 'cl' => 'lt3' ) );
ok( '720 كيلوواط ساعة شهريًا' === $v['big'] && ! preg_match( '/ريال/', implode( ' ', $v['lines'] ) ), 'defaults give kWh only: ' . $v['big'] );
setv( array( 'ac-power.tariff' => "1 | 6000 | 18\n6001 | | 30", 'ac-power.cleans' => "lt3 | أقل | 0\ngt12 | أكثر | ١٥", 'ac-power.season_months' => '٥', 'ac-power.tariff_updated' => '2026-02-01', 'ac-power.tariff_source' => 'https://example.test', 'ac-power.clean_page' => 77 ) );
$c = zt_pow_cfg(); ok( 2 === count( $c['tiers'] ) && null === $c['tiers'][1]['to'] && 15 === ( (array) $c['dirt'] )['gt12'] && 5 === $c['season'] && 180 === $c['cleanFrom'] && '2026-02-01' === $c['tariff']['updated'], 'settings with values parse (Arabic digits, open tier, page price)' );
setv( array( 'ac-power.tariff_updated' => 'soon' ) ); ok( '' === zt_pow_cfg()['tariff']['updated'], 'an invalid tariff date is dropped' ); setv( array() );
$_GET = array( 't1' => '٢', 'q1' => '1', 'ty1' => 'split', 'ag1' => 'new', 'h' => '10', 'd' => '30' );
$r = zt_pow_result( array() ); ok( false !== strpos( $r, '720' ) && false !== strpos( $r, 'zt-card' ), 'electricity shared link renders on the server' );
ob_start(); zt_pow_render( array( 'url' => '/x/' ) ); $f = ob_get_clean(); ok( 8 === preg_match_all( '/name="ty\d"/', $f ), 'eight AC rows in the no-JS form' );
ok( false !== strpos( zt_pow_how( array() ), 'kW/طن' ) && false !== strpos( zt_pow_how( array() ), 'الكيلوواط ساعة فقط' ), 'how: kW table + «kWh only» notice while the tariff is empty' );
$_GET = array();

/* ---------- moving ---------- */
setv( array( 'moving.rooms' => "2 | 1 | 2 | ٣٠٠-٤٠٠\n4 | 2 | 4 | 600 – 800\n | 3 | 6 | ", 'moving.routes' => "riyadh | jeddah | 900", 'moving.floor_price' => '20-30', 'moving.packing_price' => '40' ) );
$m = zt_mv_cfg(); ok( 3 === count( $m['rooms'] ) && null === $m['rooms'][2]['max'] && array( 300, 400 ) === $m['rooms'][0]['price'] && array( 600, 800 ) === $m['rooms'][1]['price'] && null === $m['rooms'][2]['price'], 'rooms table: ranges, Arabic digits, en-dash, open last row' );
ok( array( 900, 900 ) === $m['routes'][0]['price'] && array( 20, 30 ) === $m['floor'] && array( 40, 40 ) === $m['packing'] && null === $m['storage'], 'single price → [x,x]; empty → null' );
ok( array( 5, 7 ) === zt_mv_price( '7 - 5' ) && null === zt_mv_price( 'بعد المعاينة' ) && null === zt_mv_price( '1-2-3' ), 'price parser edge cases' );
$_GET = array( 'ty' => 'inter', 'fc' => 'riyadh', 'tc' => 'jeddah', 'r' => '3' ); $r = zt_mv_result( array() ); ok( false !== strpos( $r, 'سيارتان' ) && false !== strpos( $r, '1,800' ), 'moving shared link: 2 trucks + route 900×2' );
ob_start(); zt_mv_render( array( 'url' => '/x/' ) ); $f = ob_get_clean(); ok( false !== strpos( $f, 'data-zt-checklist' ) && substr_count( $f, 'type="checkbox" data-k=' ) >= 10, 'checklist printed by the server' ); ok( false !== strpos( $f, 'قبل بأسبوعين' ) && false !== strpos( $f, 'يوم النقل' ), 'checklist groups' );
ok( false !== strpos( zt_mv_how( array() ), 'بعد المعاينة' ), 'how: unpriced items say «بعد المعاينة»' ); setv( array() ); $_GET = array();

/* ---------- plan ---------- */
setv( array( 'tank.locations' => "ground | أرضي | 4\nroof | علوي | 3" ) ); $p = zt_plan_cfg();
ok( 3 === count( $p['tasks'] ) && 4 === $p['tasks'][1]['every'] && 3 === $p['tasks'][2]['every'], 'tank tasks read their interval from the tank tab (one source)' );
setv( array( 'plan.tasks' => "ac | تنظيف | 6 | 4 | ac | 77 | ac\nbad | بلا مدة | | | ac | |\nrest | رش | 3 | 13 | pest+h_villa | |" ) ); $p = zt_plan_cfg();
ok( 2 === count( $p['tasks'] ) && 180 === $p['tasks'][0]['price'] && 'ac' === $p['tasks'][0]['per'] && null === $p['tasks'][1]['month'] && array( 'pest', 'h_villa' ) === $p['tasks'][1]['when'], 'plan rules parse: page price, per, bad month dropped, task without interval skipped' );
ok( '' === $p['tasks'][1]['url'] && null === $p['tasks'][1]['price'], 'no page → no link, no price' ); setv( array() );
$_GET = array( 'hs' => 'apartment', 'ct' => 'riyadh', 'tk' => 'ground', 'acn' => '3', 'sf' => '0' ); $r = zt_plan_result( array() );
ok( false !== strpos( $r, 'zt-cal' ) && false !== strpos( $r, 'data-items=' ) && false !== strpos( $r, 'zad_ics' ), 'plan shared link: calendar + batch opt-in + .ics' );
ok( false !== strpos( $r, 'https://wa.me/?text=' ), '«send to myself» link has no phone number' ); $_GET = array();
ob_start(); zt_plan_render( array( 'url' => '/x/' ) ); $f = ob_get_clean(); ok( false !== strpos( $f, 'data-last="ac"' ), 'form has a last-service date per task' );
ok( false === strpos( zt_plan_view( zt_plan_cfg(), array( 'hs' => 'apartment', 'ct' => 'riyadh', 'tk' => 'ground', 'acn' => 1, 'last' => array() ), '2026-10-09' )['lines'][2], 'باقة' ), 'no plans configured → no «باقة مناسبة» line' );

/* ---------- coverage: pure parts ---------- */
$now = 1700000000; $mk = function ( $h, $way, $arr ) use ( $now ) { return array( 'hood' => $h, 'log' => array( array( 'received', $now, 1 ), array( 'on_the_way', $now + $way * 60, 1 ), array( 'arrived', $now + $arr * 60, 1 ), array( 'done', $now + 7200, 1 ) ) ); };
$orders = array(); for ( $i = 0; $i < 10; $i++ ) { $orders[] = $mk( 'النرجس', 0, 30 + $i ); } for ( $i = 0; $i < 9; $i++ ) { $orders[] = $mk( 'الملقا', 0, 20 ); }
$orders[] = array( 'hood' => 'النرجس', 'log' => array( array( 'received', $now, 1 ), array( 'done', $now + 60, 1 ) ) ); $orders[] = array( 'hood' => '', 'log' => array() );
$orders[] = array( 'hood' => 'النرجس', 'log' => array( array( 'arrived', $now, 1 ), array( 'on_the_way', $now + 600, 1 ) ) ); // arrived before leaving: ignored
$e = zt_cov_eta_from_logs( $orders, 10 );
ok( isset( $e['النرجس'] ) && 35 === $e['النرجس']['m'] && 10 === $e['النرجس']['n'], 'ETA: mean of 30..39 min = 34.5 → 35 over exactly 10 orders' );
ok( ! isset( $e['الملقا'] ), 'ETA: 9 completed orders < 10 → not shown' );
ok( isset( zt_cov_eta_from_logs( $orders, 9 )['الملقا'] ) && 20 === zt_cov_eta_from_logs( $orders, 9 )['الملقا']['m'], 'ETA: the threshold is the setting' );
$hoods = array( 1 => array( 'name' => 'النرجس', 'city' => 'الرياض', 'side' => 'north', 'lat' => 24.8, 'lng' => 46.6 ), 2 => array( 'name' => 'الملقا', 'city' => 'الرياض', 'side' => 'north', 'lat' => null, 'lng' => null ), 3 => array( 'name' => 'الشاطئ', 'city' => 'جدة', 'side' => 'west', 'lat' => 21.6, 'lng' => 39.1 ), 4 => array( 'name' => 'بلا صفحة', 'city' => 'الرياض', 'side' => 'south', 'lat' => 1, 'lng' => 1 ) );
$pages = array( array( 'hood' => 1, 'svc' => 'تنظيف خزانات', 'url' => '/t', 'eta' => 0 ), array( 'hood' => 1, 'svc' => 'تنظيف مكيفات', 'url' => '/a', 'eta' => 0 ), array( 'hood' => 2, 'svc' => 'تنظيف مكيفات', 'url' => '/a2', 'eta' => 25 ), array( 'hood' => 3, 'svc' => 'خدمة جديدة', 'url' => '/n', 'eta' => 0 ), array( 'hood' => 9, 'svc' => 'x', 'url' => '/x', 'eta' => 0 ), array( 'hood' => 1, 'svc' => 'تنظيف كنب', 'url' => '', 'eta' => 0 ) );
$d = zt_cov_assemble( $pages, $hoods, $e, array( 'تنظيف مكيفات', 'تنظيف خزانات', 'تنظيف كنب' ) );
ok( 3 === count( $d['hoods'] ), 'only districts with a published service page appear (not «بلا صفحة», not unknown ids)' );
ok( array( 'الملقا', 'النرجس', 'الشاطئ' ) === array_column( $d['hoods'], 'n' ), 'sorted by city, then side, then name: ' . implode( ',', array_column( $d['hoods'], 'n' ) ) );
ok( array( array( 'تنظيف مكيفات', '/a' ), array( 'تنظيف خزانات', '/t' ) ) === $d['hoods'][1]['v'], 'services in the theme order; a page without URL is skipped' );
ok( 'orders' === $d['hoods'][1]['eta']['src'] && 'manual' === $d['hoods'][0]['eta']['src'] && 25 === $d['hoods'][0]['eta']['m'] && null === $d['hoods'][2]['eta'], 'ETA: orders when enough, else the manual figure, else nothing' );
ok( null === zt_cov_assemble( $pages, $hoods, array(), array(), false )['hoods'][0]['eta'], 'manual figure can be switched off' );
ok( array( 'تنظيف مكيفات', 'تنظيف خزانات', 'خدمة جديدة' ) === $d['services'], 'service chips: theme order, then services not in the theme list: ' . implode( ',', $d['services'] ) );
$html = zt_cov_list_html( $d );
ok( false !== strpos( $html, '<h3 class="zt-h3">الرياض</h3>' ) && false !== strpos( $html, '<h4 class="zt-h4">شمال</h4>' ) && false !== strpos( $html, '<a href="/a">تنظيف مكيفات</a>' ), 'server list: city → side → district → service links' );
ok( false !== strpos( $html, 'متوسط وقت الوصول نحو 35 دقيقة (من 10 طلب مكتمل)' ) && false !== strpos( $html, 'وقت الوصول المعتاد نحو 25 دقيقة' ), 'ETA text, orders vs manual' );
ok( false === strpos( zt_cov_list_html( array( 'hoods' => array() ) ), '<li' ), 'empty data prints nothing' );

/* ---------- multi-event .ics + batch REST ---------- */
$ev = array( array( '2026-11-01', 'تنظيف المكيفات' ), array( '2027-04-01', 'خزان' ), array( '2027-02-30', 'تاريخ خطأ' ) );
$ics = zt_ics_build_multi( zt_ics_parse_ev( zt_ics_ev_string( $ev ) ), 'زاد', '20261009T100000Z', 'zadksa.com' );
ok( 2 === substr_count( $ics, 'BEGIN:VEVENT' ) && 2 === substr_count( $ics, 'BEGIN:VALARM' ) && false !== strpos( $ics, 'DTSTART;VALUE=DATE:20270401' ), 'ics: two events, invalid date dropped' );
ok( '' === zt_ics_build_multi( array(), '', 'z' ) && 60 === count( zt_ics_parse_ev( implode( '~', array_fill( 0, 80, '2026-11-01|x' ) ) ) ), 'ics: empty → nothing; at most 60 events' );
$t = zt_token_make();
$base = array( 't' => $t, 'first_name' => 'أحمد', 'phone' => '0551234567', 'consent' => true, 'tool' => 'plan', 'hood' => 'النرجس', 'items' => array( array( 'service' => 'تنظيف المكيفات', 'date' => date( 'Y-m-d', strtotime( '+10 days' ) ) ), array( 'service' => 'خزان', 'date' => date( 'Y-m-d', strtotime( '+60 days' ) ) ), array( 'service' => 'قديم', 'date' => '2001-01-01' ) ) );
$GLOBALS['q'] = array( 0, 0, 0, 0, 0, 0 ); $GLOBALS['ins'] = array();
$r = zt_rest_reminder_batch( new WP_REST_Request( $base ) ); ok( $r instanceof WP_REST_Response && 2 === $r->d['added'] && 1 === $r->d['skipped'], 'batch: 2 future dates saved, the past one skipped' );
ok( 2 === count( $GLOBALS['ins'] ) && 'plan' === $GLOBALS['ins'][0]['source_tool'] && null !== $GLOBALS['ins'][0]['consent_at'], 'batch rows carry the consent time + source tool' );
$r = zt_rest_reminder_batch( new WP_REST_Request( array( 'consent' => false ) + $base ) ); ok( $r instanceof WP_Error && 'zt_consent' === $r->get_error_code(), 'batch without consent refused' );
$r = zt_rest_reminder_batch( new WP_REST_Request( array( 't' => 'bad' ) + $base ) ); ok( $r instanceof WP_Error && 'zt_token' === $r->get_error_code(), 'batch needs the cache-safe token' );
$r = zt_rest_reminder_batch( new WP_REST_Request( array( 'website' => 'spam' ) + $base ) ); ok( $r instanceof WP_REST_Response && ! isset( $r->d['added'] ), 'honeypot: looks like success, saves nothing' );
$r = zt_rest_reminder_batch( new WP_REST_Request( array( 'items' => array() ) + $base ) ); ok( $r instanceof WP_Error, 'batch without items refused' );

echo $fail ? "\n$fail of $n FAILED\n" : "tools3: all $n passed\n";
exit( $fail ? 1 : 0 );
