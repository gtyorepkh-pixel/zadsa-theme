<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 2 — حاسبة استهلاك المكيفات في فاتورة الكهرباء.
 * kWh/month = Σ ( kW per ton[type, age] × tons × units ) × hours/day × days/month.
 * Cleaning effect, the residential tariff tiers and the season length are settings. NOTHING is guessed for those three: while empty the tool
 * shows kWh only (no riyals / no dirt effect / no season total). Logic twin of assets/js/ac-power.js (tests/tools-parity.test.mjs).
 */

add_action( 'zad_tools_register_settings', function () {
	$flag = 'قيمة افتراضية مقترحة من عندي كنقطة بداية (تقدير عام لكفاءة التبريد) — يعتمدها أو يعدّلها فني التكييف';
	zt_register_settings( 'ac-power', 'كهرباء المكيفات', array(
		array( 'key' => 'types', 'label' => 'أنواع المكيفات (مفتاح | الاسم)', 'type' => 'table', 'default' => "split | سبليت\nwindow | شباك\ncentral | مركزي" ),
		array( 'key' => 'ages', 'label' => 'عمر المكيف (مفتاح | الاسم)', 'type' => 'table', 'default' => "new | جديد\nmid | متوسط العمر\nold | قديم", 'desc' => 'أسماء وصفية بلا أرقام؛ عدّلها لو أردت.' ),
		array( 'key' => 'kw_per_ton', 'label' => 'قدرة الكهرباء لكل طن تبريد (kW/طن): النوع | العمر | القيمة', 'type' => 'table', 'approval' => true, 'source' => $flag,
			'default' => "split | new | 1.2\nsplit | mid | 1.4\nsplit | old | 1.7\nwindow | new | 1.5\nwindow | mid | 1.8\nwindow | old | 2.1\ncentral | new | 1.1\ncentral | mid | 1.3\ncentral | old | 1.6",
			'desc' => 'مجموعة غير مكتوبة هنا = «غير مسجّل» ولن يُحسب لها استهلاك.' ),
		array( 'key' => 'cleans', 'label' => 'آخر تنظيف: مفتاح | الاسم | نسبة زيادة الاستهلاك %', 'type' => 'table', 'required' => false,
			'default' => "lt3 | أقل من 3 شهور | \nm3_6 | من 3 إلى 6 شهور | \nm6_12 | من 6 إلى 12 شهراً | \ngt12 | أكثر من سنة | \nunknown | مش عارف | ",
			'desc' => '<strong>النسب فارغة عمداً</strong>: لا أملك مصدراً لها. اكتب النسبة (مثلاً 10) من مصدر تعتمده. فارغة = لا يظهر «الزيادة بسبب الاتساخ». النسبة المكتوبة تُطبَّق فوق استهلاك مكيف نظيف.' ),
		array( 'key' => 'tariff', 'label' => 'شرائح التعرفة السكنية الشهرية: من (ك.و.س) | إلى (فارغ = بلا حد) | السعر بالهللة', 'type' => 'table', 'required' => false, 'default' => '',
			'desc' => 'مثال للتنسيق فقط: <code>1 | 6000 | 18</code> ثم <code>6001 | | 30</code>. <strong>فارغ عمداً</strong>: ضع الشرائح الحالية من مصدرها الرسمي، وإلا تُعرض الكيلوواط ساعة فقط.' ),
		array( 'key' => 'tariff_label', 'label' => 'اسم التعرفة المعروض (مثل: التعرفة السكنية)', 'type' => 'text', 'default' => '', 'required' => false ),
		array( 'key' => 'tariff_source', 'label' => 'رابط مصدر التعرفة', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => 'يظهر في الصفحة مع تاريخ التحديث.' ),
		array( 'key' => 'tariff_updated', 'label' => 'تاريخ آخر تحديث للتعرفة (YYYY-MM-DD)', 'type' => 'text', 'default' => '', 'required' => false ),
		array( 'key' => 'season_months', 'label' => 'عدد شهور موسم الصيف (لحساب «موسم الصيف كامل»)', 'type' => 'number', 'default' => '', 'min' => 1, 'max' => 12, 'required' => false, 'desc' => 'فارغ عمداً (قرار تجاري منك). فارغ = لا يظهر إجمالي الموسم.' ),
		array( 'key' => 'clean_page', 'label' => 'صفحة خدمة تنظيف المكيفات (للمقارنة بـ«يبدأ من»)', 'type' => 'page', 'default' => 0, 'required' => false, 'desc' => 'يُقرأ أقل سعر من باقاتها عبر الثيم.' ),
		array( 'key' => 'related_page', 'label' => 'صفحة «حاسبة حجم المكيف» (للرابط تحت النتيجة)', 'type' => 'page', 'default' => 0, 'required' => false ),
	) );
} );

zt_register_tool( 'ac-power', array(
	'title' => 'حاسبة استهلاك المكيفات في فاتورة الكهرباء', 'desc' => 'قدّر استهلاك مكيفاتك الشهري من مقاسها وعمرها وساعات تشغيلها، وشاهد أثر التنظيف على الفاتورة.',
	'render' => 'zt_pow_render', 'result' => 'zt_pow_result', 'how' => 'zt_pow_how', 'examples' => 'zt_pow_examples', 'related' => 'zt_pow_related',
	'js' => ZT_URL . 'assets/js/ac-power.js',
) );

const ZT_POW_ROWS = 8;

function zt_pow_pairs( $setting ) {
	$out = array();
	foreach ( zt_table( zt_opt( 'ac-power.' . $setting ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] ) { $out[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1] ); } }
	return $out;
}

function zt_pow_cfg() {
	$kw = array();
	foreach ( zt_table( zt_opt( 'ac-power.kw_per_ton' ) ) as $r ) { if ( count( $r ) >= 3 && null !== zt_num( $r[2] ) && zt_num( $r[2] ) > 0 ) { $kw[ sanitize_key( $r[0] ) . '|' . sanitize_key( $r[1] ) ] = zt_num( $r[2] ); } }
	$cleans = array(); $dirt = array();
	foreach ( zt_table( zt_opt( 'ac-power.cleans' ) ) as $r ) {
		if ( count( $r ) < 2 || '' === $r[0] ) { continue; }
		$k = sanitize_key( $r[0] ); $cleans[] = array( 'k' => $k, 'l' => $r[1] );
		$p = isset( $r[2] ) ? zt_num( $r[2] ) : null; if ( null !== $p && $p >= 0 ) { $dirt[ $k ] = zt_pow_intval( $p ); }
	}
	$tiers = array();
	foreach ( zt_table( zt_opt( 'ac-power.tariff' ) ) as $r ) {
		if ( count( $r ) < 3 ) { continue; }
		$f = zt_num( $r[0] ); $t = zt_num( $r[1] ); $rate = zt_num( $r[2] );
		if ( null !== $f && $f >= 0 && null !== $rate && $rate >= 0 ) { $tiers[] = array( 'from' => zt_pow_intval( $f ), 'to' => ( null !== $t && $t > 0 ) ? zt_pow_intval( $t ) : null, 'rate' => zt_pow_intval( $rate ) ); }
	}
	usort( $tiers, function ( $a, $b ) { return $a['from'] <=> $b['from']; } );
	$cp = (int) zt_opt( 'ac-power.clean_page' ); $from = null;
	if ( $cp && function_exists( 'zad_min_price' ) && function_exists( 'zad_price_rows' ) ) { $m = zad_min_price( zad_price_rows( $cp ) ); $from = $m ? zt_pow_intval( $m ) : null; }
	$sm = zt_num( zt_opt( 'ac-power.season_months' ) );
	$upd = trim( (string) zt_opt( 'ac-power.tariff_updated' ) );
	return array( 'types' => zt_pow_pairs( 'types' ), 'ages' => zt_pow_pairs( 'ages' ), 'kw' => (object) $kw, 'cleans' => $cleans, 'dirt' => (object) $dirt, 'tiers' => $tiers,
		'season' => ( $sm > 0 ) ? zt_pow_intval( $sm ) : null, 'cleanFrom' => $from,
		'tariff' => array( 'label' => trim( (string) zt_opt( 'ac-power.tariff_label' ) ), 'src' => trim( (string) zt_opt( 'ac-power.tariff_source' ) ), 'updated' => '' !== zt_add_months_str( $upd, 0 ) ? $upd : '' ) );
}

/* ---------------------------------------------- the logic (PHP twin) ---------------------------------------------- */

function zt_pow_find( $list, $k ) { foreach ( $list as $it ) { if ( $it['k'] === $k ) { return $it; } } return $list ? $list[0] : null; }
function zt_pow_round( $x ) { return (int) floor( $x + 0.5 ); }
function zt_pow_cost( $tiers, $kwh ) {
	if ( ! $tiers ) { return null; }
	$h = 0;
	foreach ( $tiers as $t ) { $hi = null === $t['to'] ? $kwh : min( $kwh, $t['to'] ); $n = $hi - ( max( $t['from'], 1 ) - 1 ); if ( $n > 0 ) { $h += $n * $t['rate']; } }
	return $h / 100;
}
function zt_pow_calc( $cfg, $i ) {
	$h = $i['h'] ?? null; $d = $i['d'] ?? null;
	if ( ! ( $h > 0 ) || $h > 24 ) { return array( 'error' => 'اكتب ساعات التشغيل يوميًا (من 1 إلى 24).' ); }
	if ( ! ( $d > 0 ) || $d > 31 ) { return array( 'error' => 'اكتب أيام التشغيل في الشهر (من 1 إلى 31).' ); }
	$kwmap = (array) $cfg['kw']; $rows = array(); $total = 0; $units = 0; $missing = false;
	foreach ( (array) ( $i['acs'] ?? array() ) as $a ) {
		$t = $a['t'] ?? null;
		if ( ! ( $t > 0 ) || $t > 20 ) { continue; }
		$q = ( $a['q'] ?? 0 ) > 0 ? (int) floor( $a['q'] ) : 1; $ty = zt_pow_find( $cfg['types'], $a['ty'] ?? '' ); $ag = zt_pow_find( $cfg['ages'], $a['ag'] ?? '' );
		$kw = $ty && $ag && array_key_exists( $ty['k'] . '|' . $ag['k'], $kwmap ) ? $kwmap[ $ty['k'] . '|' . $ag['k'] ] : null;
		if ( null === $kw ) { $missing = true; continue; }
		$kwh = $kw * $t * $q * $h * $d;
		$rows[] = array( 't' => $t, 'q' => $q, 'ty' => $ty, 'ag' => $ag, 'kwh' => $kwh ); $total += $kwh; $units += $q;
	}
	if ( ! $rows ) { return array( 'error' => $missing ? 'هذا النوع والعمر غير مسجّلين بعد، اختر غيرهما أو تواصل معنا.' : 'اكتب مقاس مكيف واحد على الأقل بالطن.' ); }
	$cl = zt_pow_find( $cfg['cleans'], $i['cl'] ?? '' ); $dirtmap = (array) $cfg['dirt'];
	$pct = $cl && array_key_exists( $cl['k'], $dirtmap ) ? $dirtmap[ $cl['k'] ] : null;
	$dirty = null !== $pct ? $total * ( 1 + $pct / 100 ) : $total; $other = ( $i['o'] ?? 0 ) > 0 ? $i['o'] : 0;
	$base = zt_pow_cost( $cfg['tiers'], $other );
	$cc = null === $base ? null : zt_pow_cost( $cfg['tiers'], $other + $total ) - $base;
	$cd = null === $base ? null : zt_pow_cost( $cfg['tiers'], $other + $dirty ) - $base;
	$ex = ( null === $cc || null === $pct ) ? null : $cd - $cc;
	return array( 'rows' => $rows, 'units' => $units, 'kwh' => $total, 'dirty' => $dirty, 'pct' => $pct, 'cl' => $cl, 'other' => $other, 'costClean' => $cc, 'costDirty' => $cd,
		'extraKwh' => $dirty - $total, 'extraCost' => $ex, 'season' => ( $cfg['season'] && null !== $ex ) ? $ex * $cfg['season'] : null );
}
function zt_pow_view( $cfg, $i ) {
	$r = zt_pow_calc( $cfg, $i );
	if ( isset( $r['error'] ) ) { return $r; }
	$F = 'zt_fmt'; $kwhShown = zt_pow_round( null !== $r['pct'] ? $r['dirty'] : $r['kwh'] );
	$big = $F( $kwhShown ) . ' كيلوواط ساعة شهريًا';
	$lines = array( 'عدد المكيفات: ' . $F( $r['units'] ) . ' — بمعدل ' . $F( $i['h'] ) . ' ساعة يوميًا لمدة ' . $F( $i['d'] ) . ' يومًا' );
	if ( null !== $r['pct'] ) { $lines[] = 'استهلاك مكيفاتها نظيفة: ' . $F( zt_pow_round( $r['kwh'] ) ) . ' ك.و.س — ومع حالة التنظيف («' . $r['cl']['l'] . '»): ' . $F( zt_pow_round( $r['dirty'] ) ) . ' ك.و.س'; }
	$money = null !== $r['pct'] ? $r['costDirty'] : $r['costClean'];
	if ( null !== $money ) { $lines[] = 'تكلفة المكيفات التقديرية في الفاتورة الشهرية: ' . $F( $money ) . ' ريال'; }
	if ( $r['extraKwh'] > 0 && null !== $r['pct'] ) {
		$lines[] = 'الزيادة التقديرية بسبب الاتساخ: ' . $F( zt_pow_round( $r['extraKwh'] ) ) . ' ك.و.س شهريًا' . ( null !== $r['extraCost'] ? ' (' . $F( $r['extraCost'] ) . ' ريال)' : '' );
		if ( null !== $r['season'] ) { $lines[] = 'وفي موسم الصيف كامل (' . $F( $cfg['season'] ) . ' شهور): ' . $F( $r['season'] ) . ' ريال تقريبًا'; }
	}
	if ( $cfg['cleanFrom'] ) { $lines[] = 'للمقارنة: تنظيف المكيفات يبدأ من ' . $F( $cfg['cleanFrom'] ) . ' ريال (راجع صفحة الخدمة لسعر عددك).'; }
	$rows = array();
	foreach ( $r['rows'] as $x ) { $rows[] = array( $F( $x['t'] ) . ' طن × ' . $F( $x['q'] ) . ' — ' . $x['ty']['l'] . '، ' . $x['ag']['l'], $F( zt_pow_round( $x['kwh'] ) ) . ' ك.و.س' ); }
	$notes = array( 'كل الأرقام تقديرية وتعتمد على بياناتك والمعاملات المعلنة في «إزاي بنحسب».' );
	if ( null === $money ) { $notes[] = 'نعرض الاستهلاك فقط؛ حساب الفاتورة بالريال غير مفعّل حاليًا.'; }
	else { $t = $cfg['tariff']; $notes[] = 'التعرفة: ' . ( '' !== $t['label'] ? $t['label'] : 'الشرائح المعلنة' ) . ( '' !== $t['src'] ? ' — المصدر: ' . $t['src'] : '' ) . ( '' !== $t['updated'] ? ' — آخر تحديث: ' . $t['updated'] : '' ) . '.'; }
	$sum = 'حاسبة كهرباء المكيفات: ' . $F( $r['units'] ) . ' مكيفات ← استهلاك تقديري ' . $F( $kwhShown ) . ' ك.و.س شهريًا' . ( null !== $money ? ' (حوالي ' . $F( $money ) . ' ريال)' : '' ) . '. أحتاج تنظيف مكيفاتي.';
	return array( 'badge' => 'تقديري', 'big' => $big, 'lines' => $lines, 'table' => array( 'title' => 'تفصيل الاستهلاك لكل مكيف', 'head' => array( 'المكيف', 'الاستهلاك الشهري' ), 'rows' => $rows ),
		'notes' => $notes, 'wa' => zt_wa_message( $sum, $i['hood'] ?? '' ), 'ga' => array( 'kwh' => $kwhShown, 'units' => $r['units'] ) );
}

/* ---------------------------------------------- request + rendering ---------------------------------------------- */

function zt_pow_request() {
	$acs = array();
	for ( $k = 1; $k <= ZT_POW_ROWS; $k++ ) { $acs[] = array( 't' => zt_req_num( 't' . $k ), 'q' => zt_req_num( 'q' . $k ), 'ty' => zt_req( 'ty' . $k ), 'ag' => zt_req( 'ag' . $k ) ); }
	return array( 'acs' => $acs, 'h' => zt_req_num( 'h' ), 'd' => zt_req_num( 'd' ), 'cl' => zt_req( 'cl' ), 'o' => zt_req_num( 'o' ), 'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ) );
}

function zt_pow_row_html( $k, $cfg, $a ) {
	$sel = function ( $name, $label, $list, $cur ) {
		$h = '<label class="fld"><span>' . zt_esc( $label ) . '</span><select name="' . $name . '">';
		foreach ( $list as $it ) { $h .= '<option value="' . zt_esc( $it['k'] ) . '"' . ( $it['k'] === $cur ? ' selected' : '' ) . '>' . zt_esc( $it['l'] ) . '</option>'; }
		return $h . '</select></label>';
	};
	$num = function ( $name, $label, $val, $ph ) { return '<label class="fld"><span>' . zt_esc( $label ) . '</span><input type="text" inputmode="decimal" dir="ltr" data-zt-num name="' . $name . '" value="' . zt_esc( null === $val ? '' : zt_fmt( $val ) ) . '" placeholder="' . zt_esc( $ph ) . '" autocomplete="off"></label>'; };
	return '<div class="zt-row zt-acrow">' . $num( 't' . $k, 'مقاس المكيف ' . $k . ' (طن)', $a['t'], '2' ) . $num( 'q' . $k, 'العدد', $a['q'], '1' ) . $sel( 'ty' . $k, 'النوع', $cfg['types'], $a['ty'] ) . $sel( 'ag' . $k, 'العمر', $cfg['ages'], $a['ag'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
}

function zt_pow_render( $ctx ) {
	$cfg = zt_pow_cfg(); $q = zt_pow_request();
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '">';
	echo '<fieldset><legend>مكيفاتك</legend>';
	for ( $k = 1; $k <= 3; $k++ ) { echo zt_pow_row_html( $k, $cfg, $q['acs'][ $k - 1 ] ); } // phpcs:ignore WordPress.Security.EscapeOutput
	$open = false; for ( $k = 4; $k <= ZT_POW_ROWS; $k++ ) { if ( $q['acs'][ $k - 1 ]['t'] > 0 ) { $open = true; } }
	echo '<details' . ( $open ? ' open' : '' ) . '><summary>مكيفات أخرى</summary>';
	for ( $k = 4; $k <= ZT_POW_ROWS; $k++ ) { echo zt_pow_row_html( $k, $cfg, $q['acs'][ $k - 1 ] ); } // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</details></fieldset>';
	$sel = '<label class="fld"><span>آخر تنظيف للمكيفات</span><select name="cl">';
	foreach ( $cfg['cleans'] as $c ) { $sel .= '<option value="' . zt_esc( $c['k'] ) . '"' . ( $c['k'] === $q['cl'] ? ' selected' : '' ) . '>' . zt_esc( $c['l'] ) . '</option>'; }
	$sel .= '</select></label>';
	$num = function ( $name, $label, $val, $ph ) { return '<label class="fld"><span>' . zt_esc( $label ) . '</span><input type="text" inputmode="decimal" dir="ltr" data-zt-num name="' . $name . '" value="' . zt_esc( null === $val ? '' : zt_fmt( $val ) ) . '" placeholder="' . zt_esc( $ph ) . '" autocomplete="off"></label>'; };
	echo '<div class="zt-row">' . $num( 'h', 'ساعات التشغيل يوميًا', $q['h'], '10' ) . $num( 'd', 'أيام التشغيل في الشهر', $q['d'], '30' ) . $sel . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="zt-row">' . $num( 'o', 'استهلاك باقي البيت شهريًا (ك.و.س، اختياري)', $q['o'], '' ) . '<label class="fld"><span>الحي (اختياري)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">احسب الاستهلاك</button></div></form>';
}

function zt_pow_result( $ctx ) {
	if ( ! zt_has_req( array( 't1', 't2', 't3', 't4', 't5', 't6', 't7', 't8', 'h', 'd' ) ) ) { return ''; }
	return zt_result_html( zt_pow_view( zt_pow_cfg(), zt_pow_request() ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_pow_how( $ctx ) {
	$cfg = zt_pow_cfg(); $kw = (array) $cfg['kw']; $dirt = (array) $cfg['dirt'];
	$h  = '<p><strong>الاستهلاك الشهري (ك.و.س) = قدرة الكهرباء لكل طن × المقاس بالطن × العدد × ساعات التشغيل يوميًا × أيام الشهر</strong></p>';
	$h .= '<p>قدرة الكهرباء لكل طن تختلف حسب نوع المكيف وعمره (يعتمدها فني التكييف):</p><div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">النوع</th>';
	foreach ( $cfg['ages'] as $a ) { $h .= '<th scope="col">' . zt_esc( $a['l'] ) . '</th>'; }
	$h .= '</tr></thead><tbody>';
	foreach ( $cfg['types'] as $t ) { $h .= '<tr><th scope="row">' . zt_esc( $t['l'] ) . '</th>'; foreach ( $cfg['ages'] as $a ) { $k = $t['k'] . '|' . $a['k']; $h .= '<td>' . ( isset( $kw[ $k ] ) ? zt_esc( zt_fmt( $kw[ $k ] ) ) . ' kW/طن' : '—' ) . '</td>'; } $h .= '</tr>'; }
	$h .= '</tbody></table></div>';
	if ( $dirt ) {
		$h .= '<p>أثر آخر تنظيف على الاستهلاك:</p><ul>'; foreach ( $cfg['cleans'] as $c ) { if ( isset( $dirt[ $c['k'] ] ) ) { $h .= '<li>' . zt_esc( $c['l'] ) . ': +' . zt_esc( zt_fmt( $dirt[ $c['k'] ] ) ) . '%</li>'; } } $h .= '</ul>';
	}
	if ( $cfg['tiers'] ) {
		$h .= '<p>التكلفة تُحسب بالشرائح الشهرية (فوق استهلاك باقي البيت إن كتبته):</p><div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">من (ك.و.س)</th><th scope="col">إلى</th><th scope="col">السعر (هللة/ك.و.س)</th></tr></thead><tbody>';
		foreach ( $cfg['tiers'] as $t ) { $h .= '<tr><td>' . zt_esc( zt_fmt( $t['from'] ) ) . '</td><td>' . ( null === $t['to'] ? 'وما فوق' : zt_esc( zt_fmt( $t['to'] ) ) ) . '</td><td>' . zt_esc( zt_fmt( $t['rate'] ) ) . '</td></tr>'; }
		$h .= '</tbody></table></div>';
		$tf = $cfg['tariff'];
		$h .= '<p>' . zt_esc( '' !== $tf['label'] ? $tf['label'] : 'التعرفة' ) . ( '' !== $tf['src'] ? ' — المصدر: <a href="' . esc_url( $tf['src'] ) . '" rel="nofollow noopener" target="_blank">' . zt_esc( $tf['src'] ) . '</a>' : '' ) . ( '' !== $tf['updated'] ? ' — آخر تحديث: ' . zt_esc( $tf['updated'] ) : '' ) . '</p>';
	} else { $h .= '<p>نعرض الاستهلاك بالكيلوواط ساعة فقط؛ تحويله إلى ريال يحتاج شرائح التعرفة المعلنة وسنضيفه فور اعتمادها.</p>'; }
	return $h . '<p>كل الأرقام تقديرية وتختلف باختلاف حالة المكيف وحرارة الجو وطريقة الاستخدام.</p>';
}

function zt_pow_examples( $ctx ) {
	$cfg = zt_pow_cfg(); if ( ! $cfg['types'] || ! $cfg['ages'] ) { return ''; }
	$t0 = $cfg['types'][0]['k']; $a0 = $cfg['ages'][0]['k']; $a1 = $cfg['ages'][ min( 1, count( $cfg['ages'] ) - 1 ) ]['k'];
	$tl = $cfg['types'][0]['l']; $al0 = $cfg['ages'][0]['l']; $al1 = $cfg['ages'][ min( 1, count( $cfg['ages'] ) - 1 ) ]['l'];
	$ex = array(
		array( 'مكيف 2 طن واحد (' . $tl . '، ' . $al0 . ')، 10 ساعات يوميًا لمدة 30 يومًا', array( 'acs' => array( array( 't' => 2, 'q' => 1, 'ty' => $t0, 'ag' => $a0 ) ), 'h' => 10, 'd' => 30, 'cl' => '' ) ),
		array( '3 مكيفات 2 طن (' . $tl . '، ' . $al1 . ')، 8 ساعات يوميًا لمدة 30 يومًا', array( 'acs' => array( array( 't' => 2, 'q' => 3, 'ty' => $t0, 'ag' => $a1 ) ), 'h' => 8, 'd' => 30, 'cl' => '' ) ),
	);
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الحالة</th><th scope="col">الاستهلاك الشهري التقديري</th></tr></thead><tbody>';
	foreach ( $ex as $e ) { $r = zt_pow_calc( $cfg, $e[1] ); if ( isset( $r['error'] ) ) { continue; } $h .= '<tr><th scope="row">' . zt_esc( $e[0] ) . '</th><td>' . zt_esc( zt_fmt( zt_pow_round( $r['kwh'] ) ) ) . ' ك.و.س</td></tr>'; }
	return $h . '</tbody></table></div>';
}

function zt_pow_related( $ctx ) {
	$id = (int) zt_opt( 'ac-power.related_page' );
	return $id && 'publish' === get_post_status( $id ) ? '<p>لتختار مقاس مكيف مناسب: <a href="' . esc_url( get_permalink( $id ) ) . '" data-zt-event="tool_related">حاسبة حجم المكيف</a></p>' : '';
}
