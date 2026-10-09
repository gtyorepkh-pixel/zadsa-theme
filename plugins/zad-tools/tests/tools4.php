<?php
/** Phase-4 PHP tests: encyclopedia data/schema, identifier config + gates, the seasonal report (aggregation, privacy rules, charts, CSV, import, publish guard, page). Run: php tests/tools4.php */
require __DIR__ . '/stub.php';
define( 'DAY_IN_SECONDS', 86400 ); define( 'MB_IN_BYTES', 1048576 );
$GLOBALS['POSTS'] = array(); $GLOBALS['THUMB'] = array();
function get_posts( $a ) { $t = $a['post_type'] ?? ''; return array_values( array_filter( $GLOBALS['POSTS'], function ( $p ) use ( $t ) { return $p->post_type === $t && 'publish' === $p->post_status; } ) ); }
function get_post_thumbnail_id( $id ) { return $GLOBALS['THUMB'][ $id ] ?? 0; } function wp_get_attachment_image_url( $a ) { return 'https://zadksa.com/img-' . $a . '.jpg'; }
function zad_card_title( $id ) { return 'خدمة ' . $id; } function get_post_time() { return '2026-10-01T00:00:00+00:00'; } function get_post_modified_time() { return '2026-10-02T00:00:00+00:00'; }
function esc_textarea_s() {} function nl2br_s() {}
class WP_Post { public $ID; public $post_type; public $post_status = 'publish'; function __construct( $id, $t ) { $this->ID = $id; $this->post_type = $t; } }
function mkpest( $id, $name, $meta, $thumb = 0 ) { $GLOBALS['POSTS'][] = new WP_Post( $id, 'zad_pest' ); $GLOBALS['TITLE'][ $id ] = $name; foreach ( $meta as $k => $v ) { $GLOBALS['META'][ $id ][ '_zp_' . $k ] = $v; } if ( $thumb ) { $GLOBALS['THUMB'][ $id ] = $thumb; $GLOBALS['META'][ $thumb ]['_wp_attachment_image_alt'] = 'صورة ' . $name; } }
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/ac-power.php'; require ZT_DIR . 'includes/pests.php'; require ZT_DIR . 'includes/tools/pest-id.php'; require ZT_DIR . 'includes/report.php';
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function setv( $a ) { update_option( 'zad_tools_opts', $a ); }
function approve_tab( $tab ) { $a = get_option( 'zad_tools_approved', array() ); foreach ( zt_pending_approvals( $tab ) as $p => $f ) { $a[ $p ] = array( 'by' => 1 ); } update_option( 'zad_tools_approved', $a ); }

/* ---------- settings + vocab ---------- */
ok( isset( zt_tools()['pest-id'] ) && 'pests' === zt_tools()['pest-id']['settings_tab'], 'identifier registered on the «pests» tab' );
ok( isset( zt_pending_approvals( 'pests' )['pests.min_match'] ) && ! zt_tool_ready( 'pests' ), 'strong-match threshold is a flagged value → closed until approved' );
approve_tab( 'pests' ); ok( zt_tool_ready( 'pests' ), 'approval opens the settings gate' );
ok( 7 === count( zt_pest_vocab( 'places' ) ) && 'مطبخ' === zt_pest_vocab( 'places' )['kitchen'][0] && '🍳' === zt_pest_vocab( 'places' )['kitchen'][1], 'places vocabulary (7 from the order)' );
ok( 4 === count( zt_pest_vocab( 'sizes' ) ) && 5 === count( zt_pest_vocab( 'signs' ) ) && 3 === count( zt_pest_wings() ), 'size buckets, signs and wings from the order' );
$q = zt_pid_questions(); ok( 5 === count( $q ) && array( 'place', 'size', 'color', 'wings', 'sign' ) === array_column( $q, 'k' ), 'five questions (the order asks for 4–6)' );
ok( array( 'pl', 'sz', 'co', 'wg', 'sg' ) === array_column( $q, 'p' ), 'URL parameters' );

/* ---------- encyclopedia data ---------- */
$GLOBALS['META'][11]['_zad_hd'] = 1;
mkpest( 11, 'النمل الأبيض', array( 'names' => 'الأرضة، النمل الأبيض', 'sci' => 'Isoptera', 'signs' => "نشارة خشب\nطين على الجدار", 'activity' => 'night', 'season' => 'الربيع', 'danger' => 'high', 'prevent' => "أصلح التسريبات\n", 'service' => 55, 'faq' => "متى تظهر؟ | في الربيع.\nسطر ناقص", 'a_place' => array( 'wood' ), 'a_size' => array( 'small' ), 'a_color' => array( 'white' ), 'a_sign' => array( 'sawdust' ), 'a_wings' => 'some' ), 901 );
mkpest( 12, 'الصراصير', array( 'a_place' => array( 'kitchen' ), 'a_wings' => 'yes' ) );
mkpest( 13, 'حشرة فاضية', array() );   // no identification data yet
$GLOBALS['POSTS'][] = new WP_Post( 55, 'page' );
$d = zt_pest_data( 11 );
ok( array( 'الأرضة', 'النمل الأبيض' ) === $d['names'] && 2 === count( $d['signs'] ) && 1 === count( $d['prevent'] ) && 1 === count( $d['faq'] ) && 'الربيع' === $d['season'], 'pest fields parsed; empty lines and half rows dropped' );
ok( 'https://zadksa.com/img-901.jpg' === $d['img'] && 'صورة النمل الأبيض' === $d['alt'], 'image + its alt text come from the media library' );
ok( $d['service'] && 'خدمة 55' === $d['service'][0], 'related service resolved (published page only)' );
ok( null === zt_pest_data( 12 )['service'] && '' === zt_pest_data( 12 )['img'], 'no service / no image → null / empty (nothing invented)' );
$cfg = zt_pid_cfg(); ok( 2 === count( $cfg['pests'] ), 'identifier uses only pests that have identification data (the empty draft is left out)' );
ok( ! zt_pid_ready(), 'identifier closed with fewer pests than the minimum (3)' ); setv( array( 'pests.min_pests' => 2 ) ); delete_transient( 'zt_pest_cfg' ); $GLOBALS['T'] = array(); ok( zt_pid_ready(), 'opens once enough pests are published' );
$ctx = zt_ctx( 1 ); $GLOBALS['META'][1] = array( '_zt_tool' => 'pest-id' ); $ctx = zt_ctx( 1 ); ok( true === $ctx['ready'], 'page ready = settings approved AND ready_cb' ); setv( array() ); $GLOBALS['T'] = array();
$_GET = array( 'pl' => 'wood', 'sz' => 'small', 'co' => 'white' ); setv( array( 'pests.min_pests' => 2 ) );
$r = zt_pid_result( array() ); ok( false !== strpos( $r, 'zt-cards' ) && false !== strpos( $r, 'النمل الأبيض' ) && false !== strpos( $r, 'نسبة التطابق' ), 'shared link prints the cards on the server' );
ok( false !== strpos( $r, 'width="72" height="72"' ), 'card images have fixed size (no layout shift)' );
$_GET = array( 'pl' => '' ); ok( '' === zt_pid_result( array() ), 'no answers → nothing printed' ); $_GET = array();
ob_start(); zt_pid_render( array( 'url' => '/x/' ) ); $f = ob_get_clean(); ok( 5 === substr_count( $f, '<legend>' ) && 5 === substr_count( $f, 'value="" checked' ) && substr_count( $f, 'type="radio"' ) >= 25, 'form: 5 fieldsets, «مش متأكد» default checked, radios' );
ok( false !== strpos( zt_pid_how( array() ), '60%' ) && false !== strpos( zt_pid_how( array() ), 'نسبة التطابق' ), 'how: formula + the threshold from settings' );

/* ---------- schema ---------- */
$nodes = array( array( '@type' => 'WebPage', '@id' => 'https://zadksa.com/page-11/#webpage' ) );
$out = zt_pest_schema( $nodes, $d, 11 ); $types = array_column( $out, '@type' );
ok( in_array( 'Article', $types, true ) && in_array( 'FAQPage', $types, true ) && 1 === count( array_filter( $out, function ( $x ) { return '#webpage' === substr( $x['@id'] ?? '', -8 ); } ) ), 'Article + FAQPage added; the theme WebPage node is not repeated' );
$art = $out[1]; ok( 'Taxon' === $art['about']['@type'] && in_array( 'Isoptera', $art['about']['alternateName'], true ) && isset( $art['publisher'] ) && ! isset( $art['aggregateRating'] ), 'Taxon about, publisher, no rating' );
ok( null !== json_decode( wp_json_encode( $out ) ), 'valid JSON-LD' );
$out2 = zt_pest_schema( $nodes, zt_pest_data( 12 ), 12 ); ok( ! in_array( 'FAQPage', array_column( $out2, '@type' ), true ), 'no FAQ → no FAQPage' );
ob_start(); zt_pest_body( 11 ); $b = ob_get_clean();
ok( false !== strpos( $b, 'الأسماء الشائعة' ) && false !== strpos( $b, 'Isoptera' ) && false !== strpos( $b, 'مستوى الخطر' ) && false !== strpos( $b, 'علامات وجودها' ) && false !== strpos( $b, 'alt="صورة النمل الأبيض"' ), 'single body prints the filled fields + image alt' );
ob_start(); zt_pest_body( 12 ); $b2 = ob_get_clean(); ok( false === strpos( $b2, 'مستوى الخطر' ) && false === strpos( $b2, 'الاسم العلمي' ) && false === strpos( $b2, '<img' ), 'empty fields print nothing' );

/* ---------- report: aggregation ---------- */
$map = array( array( 'نمل أبيض', 'النمل الأبيض' ), array( 'صراصير', 'الصراصير' ), array( 'نمل', 'النمل' ) );
ok( 'النمل الأبيض' === zt_rep_label( 'مكافحة النمل الأبيض بالرياض', $map ) && 'النمل الأبيض' === zt_rep_label( 'نمل ابيض', $map ) && 'النمل الأبيض' === zt_rep_label( 'مكافحة الأرضة والنمل الأبيض', $map ) && 'النمل' === zt_rep_label( 'رش النمل', $map ) && null === zt_rep_label( 'تنظيف مكيفات', $map ), 'service → pest label (first keyword wins; unmatched ignored)' );
$rows = array();
$add = function ( $d, $svc, $h, $c, $k = 1 ) use ( &$rows ) { for ( $i = 0; $i < $k; $i++ ) { $rows[] = array( 'd' => $d, 'svc' => $svc, 'hood' => $h, 'city' => $c ); } };
$add( '2026-10-03', 'مكافحة صراصير', 'النرجس', 'الرياض', 5 ); $add( '2026-10-09', 'مكافحة النمل الأبيض', 'النرجس', 'الرياض', 2 ); $add( '2026-10-12', 'رش نمل', 'الملقا', 'الرياض', 4 );
$add( '2026-10-20', 'مكافحة صراصير', 'الشاطئ', 'جدة', 3 ); $add( '2026-10-21', 'تنظيف مكيفات', 'النرجس', 'الرياض', 9 ); $add( '2026-09-15', 'مكافحة صراصير', 'النرجس', 'الرياض', 6 ); $add( '2025-10-10', 'صراصير', 'x', 'الرياض', 1 ); $add( '2025-09-30', 'صراصير', 'x', 'الرياض', 7 );
$a = zt_rep_aggregate( $rows, '2026-10', $map, 5, array( 'الرياض', 'جدة' ) );
ok( 14 === $a['total'], 'month total counts only matched pest orders in that month (cleaning orders excluded): ' . $a['total'] );
ok( array( array( 'الصراصير', 8 ), array( 'النمل', 4 ), array( 'النمل الأبيض', 2 ) ) === $a['pests'], 'pests sorted by count: ' . json_encode( $a['pests'], JSON_UNESCAPED_UNICODE ) );
ok( 12 === count( $a['trend'] ) && '2025-11' === $a['trend'][0][0] && '2026-10' === $a['trend'][11][0], 'trend = the 12 months ending at the report month' );
$tr = array_column( $a['trend'], 1, 0 ); ok( 6 === $tr['2026-09'] && 14 === $tr['2026-10'] && 0 === $tr['2025-11'] && ! isset( $tr['2025-10'] ) && ! isset( $tr['2025-09'] ), 'trend values; months outside the window are not counted' );
ok( array( array( 'النرجس', 7 ), array( 'الملقا', 4 ) ) === array_slice( $a['hoods'], 0, 1 ) + array( 1 => array( 'الملقا', 4 ) ) && 1 === count( $a['hoods'] ), 'districts under 5 orders are NOT named (الملقا 4, الشاطئ 3 hidden): ' . json_encode( $a['hoods'], JSON_UNESCAPED_UNICODE ) );
ok( 2 === $a['hoods_hidden'], 'two districts hidden (counted, not named)' );
ok( 'الرياض' === $a['cities'][0]['city'] && 11 === $a['cities'][0]['total'] && 3 === $a['cities'][1]['total'] && array( array( 'الصراصير', 3 ) ) === $a['cities'][1]['pests'], 'city comparison' );
$a0 = zt_rep_aggregate( array(), '2026-10', $map, 5, array( 'الرياض' ) ); ok( 0 === $a0['total'] && 12 === count( $a0['trend'] ) && '' === zt_rep_summary( 'x', $a0 ), 'no data → zeros and no summary' );
ok( 'سجّلت زاد في أكتوبر 2026 14 طلبًا لمكافحة الحشرات، وكانت الصراصير الأكثر طلبًا بـ 8.' === zt_rep_summary( 'سجّلت زاد في {الشهر} {العدد} طلبًا لمكافحة الحشرات، وكانت {أكثر_حشرة} الأكثر طلبًا بـ {عدد_أكثر_حشرة}.', $a ), 'summary sentence filled from the real numbers' );

/* ---------- publish rules ---------- */
ok( 1 === count( zt_rep_publish_check( null, 'x', 10 ) ), 'no data → cannot publish' );
ok( 2 === count( zt_rep_publish_check( $a, '  ', 0 ) ), 'empty editor notes AND no month minimum → two reasons' );
ok( 1 === count( zt_rep_publish_check( $a, 'ملاحظات', 15 ) ) && false !== strpos( zt_rep_publish_check( $a, 'ملاحظات', 15 )[0], '14' ), 'fewer orders than the minimum → refused (with the numbers)' );
ok( array() === zt_rep_publish_check( $a, 'ملاحظات', 14 ), 'notes + enough orders → can be published' );
ok( ! zt_tool_ready( 'report' ), 'report closed while «أقل عدد طلبات في الشهر» is empty (must_fill)' ); ok( 20 === zt_sanitize_field( zt_field( 'report.min_month' ), '٢٠' ), 'the settings form turns Arabic digits into the number' ); setv( array( 'report.min_month' => 20 ) ); ok( zt_tool_ready( 'report' ), 'opens when the owner sets it' );
$bad = zt_rep_generate( '2026-13' ); ok( $bad instanceof WP_Error, 'bad month refused' ); setv( array() ); $bad = zt_rep_generate( '2026-10' ); ok( $bad instanceof WP_Error && 'cfg' === $bad->get_error_code(), 'generation refused until the minimum is set' );

/* ---------- charts (valid SVG, escaped) + CSV ---------- */
$svg = zt_rep_svg_bars( array( array( 'الصراصير', 8 ), array( 'نمل <b> & "x"', 4 ) ), 'عنوان', 'وصف' );
$dom = new DOMDocument(); ok( @$dom->loadXML( $svg ), 'bar chart is well-formed XML' ); ok( false === strpos( $svg, '<b>' ) && false !== strpos( $svg, '&lt;b&gt;' ), 'bar chart escapes labels' );
ok( false !== strpos( $svg, 'role="img"' ) && false !== strpos( $svg, '<title>عنوان</title>' ) && false !== strpos( $svg, '<desc>وصف</desc>' ), 'bar chart has title + desc (accessible)' );
$w = array(); preg_match_all( '/<rect x="[\d.]+" y="[\d.]+" width="(\d+)"/', $svg, $w ); ok( 2 === count( $w[1] ) && (int) $w[1][0] > (int) $w[1][1] && abs( (int) $w[1][1] * 2 - (int) $w[1][0] ) <= 1, 'bar lengths are proportional (8 : 4)' );
$line = zt_rep_svg_line( $a['trend'], 'اتجاه', 'وصف' ); $dom = new DOMDocument(); ok( @$dom->loadXML( $line ) && 12 === substr_count( $line, '<circle' ), 'line chart: well-formed, 12 points' );
ok( false !== strpos( zt_rep_svg_bars( array(), 't', 'd' ), '</svg>' ), 'empty data still gives a valid chart' );
$csv = zt_rep_csv( $a ); ok( 0 === strpos( $csv, "\xEF\xBB\xBF" ) && false !== strpos( $csv, "pest,الصراصير,8\r\n" ) && false !== strpos( $csv, "district,النرجس,7" ) && false === strpos( $csv, 'الملقا' ) && false === strpos( $csv, 'الشاطئ' ), 'CSV: BOM + aggregates; hidden districts absent' );
ok( 'a' === zt_csv_cell( 'a' ) && '"a,b"' === zt_csv_cell( 'a,b' ) && '"say ""hi"""' === zt_csv_cell( 'say "hi"' ), 'CSV quoting' );

/* ---------- CSV import: no personal data ---------- */
$ok = zt_rep_parse_csv( "\xEF\xBB\xBFالتاريخ,الخدمة,الحي,المدينة\n٢٠٢٥/١٢/٣,مكافحة صراصير,النرجس,الرياض\n2025-12-04,رش نمل,الملقا,الرياض\nbad,رش,x,y\n2025-12-05,,x,y\n" );
ok( 2 === count( $ok['rows'] ) && '2025-12-03' === $ok['rows'][0]['d'] && 2 === $ok['skipped'] && 2 === count( $ok['errors'] ), 'import: Arabic digits + slashes normalised; bad rows skipped with reasons' );
foreach ( array( "date,service,hood,city,phone\n2025-12-03,x,y,z,0551234567", "التاريخ,الخدمة,الحي,المدينة,الاسم\n2025-12-03,x,y,z,أحمد", "date,service,hood,city,email\n2025-12-03,x,y,z,a@b.c", "date,service,hood,city,notes\n2025-12-03,x,y,z,n" ) as $i => $f ) {
	$r = zt_rep_parse_csv( $f ); ok( ! $r['rows'] && $r['errors'] && false !== strpos( $r['errors'][0], 'أعمدة غير مسموحة' ), "import #$i: any extra column rejects the whole file" );
}
ok( ! zt_rep_parse_csv( "date,service,hood\n2025-12-03,x,y" )['rows'], 'import: a missing column is refused' );
ok( 1 === count( zt_rep_parse_csv( "date;service;hood;city\n2025-12-03;رش نمل;النرجس;الرياض" )['rows'] ), 'import: semicolon delimiter accepted' ); ok( ! zt_rep_parse_csv( '' )['rows'], 'import: empty file' );

/* ---------- report page + schema ---------- */
$GLOBALS['META'][77] = array( '_zt_rep_data' => $a + array( 'src' => array( 'orders' => 10, 'hist' => 4 ), 'generated' => '2026-11-01 09:00' ), '_zt_rep_summary' => 'ملخص الشهر.', '_zt_rep_notes' => "نصيحة أولى.\n\nنصيحة ثانية." );
setv( array( 'report.min_month' => 10 ) );
ob_start(); zt_rep_body( 77 ); $h = ob_get_clean();
ok( false !== strpos( $h, 'ملخص الشهر.' ) && false !== strpos( $h, 'ملاحظات المحرر والنصائح' ) && false !== strpos( $h, 'نصيحة ثانية.' ), 'page: summary + editor notes' );
ok( 2 === substr_count( $h, '<svg' ) && false !== strpos( $h, 'أكثر الأحياء نشاطاً' ) && false !== strpos( $h, 'مقارنة بين المدن' ) && false !== strpos( $h, 'المنهجية' ), 'page: both charts, districts, city comparison, methodology' );
ok( false === strpos( $h, 'الملقا' ) && false === strpos( $h, 'الشاطئ' ) && false !== strpos( $h, 'النرجس' ), 'page: districts under the minimum are never named' );
ok( false !== strpos( $h, 'أحياء أخرى لم تبلغها: 2' ) && false !== strpos( $h, 'بلغ 5 طلبات' ), 'page: states the rule and how many were hidden' );
ok( false !== strpos( $h, 'zt_rep_csv=77' ) && 2 === substr_count( $h, 'zt_rep_svg=77' ) && false !== strpos( $h, 'مع سجل مستورد' ), 'page: CSV link, two embed codes, imported-history disclosure' );
ok( false !== strpos( $h, 'المصدر: <a href=' ) || false !== strpos( $h, 'المصدر: &lt;a href=' ), 'embed code links back to the report' );
$ds = zt_rep_schema( array(), $a, 'ملخص', 77, 'https://zadksa.com/pest-report/2026-10/', '' ); $dsn = $ds[0];
ok( 'Dataset' === $dsn['@type'] && '2026-10-01/2026-10-31' === $dsn['temporalCoverage'] && 'text/csv' === $dsn['distribution'][0]['encodingFormat'] && ! isset( $dsn['license'] ) && 'Place' === $dsn['spatialCoverage'][0]['@type'], 'Dataset: coverage, CSV distribution, no invented license' );
ok( 'https://x/l' === zt_rep_schema( array(), $a, '', 77, 'u', 'https://x/l' )[0]['license'], 'license only when the owner sets it' ); ok( 'Article' === $ds[1]['@type'] && '#dataset' === substr( $ds[1]['about']['@id'], -8 ), 'Article about the Dataset' );
ok( ! isset( $ds[1]['aggregateRating'] ) && null !== json_decode( wp_json_encode( $ds ) ), 'no ratings; valid JSON-LD' );
ok( array( 'الصراصير', 'الصراصير' ) === array( 'الصراصير', zt_rep_label( 'صراصير', $map ) ) , 'label map sanity' );
$m = zt_rep_method_lines( $a + array( 'src' => array( 'hist' => 0 ) ) ); ok( 3 === count( $m ) && false !== strpos( $m[0], 'طلبات زاد المكتملة فقط،' ) && false !== strpos( $m[1], '5 طلبات' ), 'methodology template filled (5 = the setting)' );

echo $fail ? "\n$fail of $n FAILED\n" : "tools4: all $n passed\n";
exit( $fail ? 1 : 0 );
