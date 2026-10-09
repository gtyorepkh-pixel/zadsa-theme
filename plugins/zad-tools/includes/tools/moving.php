<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 10 — حاسبة نقل العفش (+ قائمة تجهيز تفاعلية).
 * Every price is an owner's setting; a price cell may be one number («150») or a range («150-200»). A line item without a price is shown as
 * «بعد المعاينة» and never as a number. The tool stays closed until the rooms table (trucks / workers per number of rooms) is filled in.
 * Logic twin of assets/js/moving.js (tests/tools-parity.test.mjs).
 */

add_action( 'zad_tools_register_settings', function () {
	$pd = 'السعر بالريال: رقم واحد أو نطاق (مثل 150-200). فارغ = «بعد المعاينة».';
	zt_register_settings( 'moving', 'نقل العفش', array(
		array( 'key' => 'cities', 'label' => 'المدن (مفتاح | الاسم)', 'type' => 'table', 'default' => "riyadh | الرياض\njeddah | جدة\ndammam | الدمام", 'desc' => 'أول مدينة هي الافتراضية.' ),
		array( 'key' => 'rooms', 'label' => 'الحمولة والأسعار حسب عدد الغرف: حتى (عدد الغرف؛ فارغ = ما فوق) | سيارات | عمال | سعر النقل', 'type' => 'table', 'default' => '', 'must_fill' => true,
			'desc' => 'مطلوب لفتح الأداة، <strong>فارغ عمداً</strong> (أرقام الحمولة والعمال والأسعار من خبرتك). مثال للتنسيق فقط: <code>2 | 1 | 2 | 300-400</code> ثم <code>4 | 2 | 4 | 600-800</code>. أول صف يغطي عدد الغرف هو المستخدم؛ فوق آخر صف مكتوب = «يحتاج معاينة». ' . $pd ),
		array( 'key' => 'routes', 'label' => 'سعر النقل بين المدن (من | إلى | سعر السيارة الواحدة)', 'type' => 'table', 'default' => '', 'required' => false, 'desc' => 'المفتاح من جدول المدن. الاتجاهان متساويان. يُضرب في عدد السيارات. ' . $pd ),
		array( 'key' => 'floor_price', 'label' => 'سعر كل دور بدون أسانسير (لكل جهة)', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => $pd ),
		array( 'key' => 'appliance_price', 'label' => 'سعر نقل الجهاز الكبير (ثلاجة/غسالة)', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => $pd ),
		array( 'key' => 'ac_install_price', 'label' => 'سعر فك وتركيب المكيف الواحد', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => $pd ),
		array( 'key' => 'furniture_price', 'label' => 'سعر فك وتركيب الأثاث لكل غرفة', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => $pd ),
		array( 'key' => 'packing_price', 'label' => 'سعر التغليف لكل غرفة', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => $pd ),
		array( 'key' => 'storage_price', 'label' => 'سعر التخزين الشهري', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => $pd ),
		array( 'key' => 'checklist', 'label' => 'قائمة التجهيز للنقل: المرحلة | البند', 'type' => 'table', 'required' => false,
			'default' => "قبل بأسبوعين | احجز شركة النقل وحدّد موعد المعاينة\nقبل بأسبوعين | افرز الأغراض وتخلص من غير المستعمل\nقبل بأسبوعين | أبلغ شركة الكهرباء والمياه والإنترنت بموعد الانتقال\nقبل بأسبوع | ابدأ بتغليف الأغراض غير اليومية وسجّل محتوى كل صندوق\nقبل بأسبوع | افرغ الثلاجة والمجمّد وافصلهما قبل النقل بوقت كافٍ\nقبل بأسبوع | صوّر توصيلات الأجهزة قبل فكها\nيوم النقل | تأكد من بقاء الأوراق الثمينة والأدوية والشواحن معك\nيوم النقل | اشرح للعمال ترتيب الغرف في المكان الجديد\nيوم النقل | راجع الأثاث والأجهزة عند التسليم قبل المغادرة\nبعد النقل | افحص الأجهزة وشغّلها واحدة واحدة\nبعد النقل | سلّم مفاتيح المكان القديم وأغلق العدادات\nبعد النقل | حدّث عنوانك في التطبيقات والبنوك",
			'desc' => 'نصوص عامة بلا أرقام؛ عدّلها كما تشاء.' ),
	) );
} );

zt_register_tool( 'moving', array(
	'title' => 'حاسبة نقل العفش', 'desc' => 'قدّر عدد السيارات والعمال ونطاق سعر النقل، وجهّز نفسك بقائمة تفاعلية قبل يوم النقل.',
	'render' => 'zt_mv_render', 'result' => 'zt_mv_result', 'how' => 'zt_mv_how', 'examples' => 'zt_mv_examples',
	'js' => ZT_URL . 'assets/js/moving.js',
) );

/** «150» → [150,150], «150-200» (any dash) → [150,200], anything else → null. */
function zt_mv_price( $s ) {
	if ( ! preg_match_all( '/\d+(?:\.\d+)?/', zt_digits_en( (string) $s ), $m ) ) { return null; }
	$n = array_map( 'floatval', $m[0] );
	if ( 1 === count( $n ) ) { return array( zt_pow_intval( $n[0] ), zt_pow_intval( $n[0] ) ); }
	return 2 === count( $n ) ? array( zt_pow_intval( min( $n ) ), zt_pow_intval( max( $n ) ) ) : null;
}

function zt_mv_cfg() {
	$cities = array();
	foreach ( zt_table( zt_opt( 'moving.cities' ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] ) { $cities[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1] ); } }
	$rooms = array();
	foreach ( zt_table( zt_opt( 'moving.rooms' ) ) as $r ) {
		if ( count( $r ) < 3 ) { continue; }
		$max = zt_num( $r[0] ); $tr = zt_num( $r[1] ); $wk = zt_num( $r[2] );
		if ( null === $tr || null === $wk || $tr < 1 ) { continue; }
		$rooms[] = array( 'max' => ( null !== $max && $max > 0 ) ? (int) $max : null, 'trucks' => (int) $tr, 'workers' => (int) $wk, 'price' => isset( $r[3] ) ? zt_mv_price( $r[3] ) : null );
	}
	usort( $rooms, function ( $a, $b ) { return ( $a['max'] ?? PHP_INT_MAX ) <=> ( $b['max'] ?? PHP_INT_MAX ); } );
	$routes = array();
	foreach ( zt_table( zt_opt( 'moving.routes' ) ) as $r ) { if ( count( $r ) >= 3 ) { $p = zt_mv_price( $r[2] ); if ( $p ) { $routes[] = array( 'a' => sanitize_key( $r[0] ), 'b' => sanitize_key( $r[1] ), 'price' => $p ); } } }
	return array( 'cities' => $cities, 'rooms' => $rooms, 'routes' => $routes, 'floor' => zt_mv_price( zt_opt( 'moving.floor_price' ) ), 'appliance' => zt_mv_price( zt_opt( 'moving.appliance_price' ) ),
		'acInstall' => zt_mv_price( zt_opt( 'moving.ac_install_price' ) ), 'furniture' => zt_mv_price( zt_opt( 'moving.furniture_price' ) ), 'packing' => zt_mv_price( zt_opt( 'moving.packing_price' ) ), 'storage' => zt_mv_price( zt_opt( 'moving.storage_price' ) ) );
}

/* ---------------------------------------------- the logic (PHP twin) ---------------------------------------------- */

function zt_mv_find( $list, $k ) { foreach ( $list as $it ) { if ( $it['k'] === $k ) { return $it; } } return $list ? $list[0] : null; }
function zt_mv_nz( $n ) { return $n > 0 ? (int) floor( $n ) : 0; }
function zt_mv_mul( $p, $n ) { return null === $p ? null : array( $p[0] * $n, $p[1] * $n ); }
function zt_mv_pr( $p ) { return $p[0] == $p[1] ? zt_fmt( $p[0] ) : zt_fmt( $p[0] ) . ' – ' . zt_fmt( $p[1] ); }
function zt_mv_c( $n, $a ) { return zt_ar_count( $n, $a[0], $a[1], $a[2], $a[3] ); }
function zt_mv_w() {
	return array( 'room' => array( 'غرفة', 'غرفتين', 'غرف', 'غرفة' ), 'truck' => array( 'سيارة', 'سيارتين', 'سيارات', 'سيارة' ), 'app' => array( 'جهاز كبير', 'جهازين كبيرين', 'أجهزة كبيرة', 'جهازًا كبيرًا' ),
		'ac' => array( 'مكيف', 'مكيفين', 'مكيفات', 'مكيفًا' ), 'month' => array( 'شهر', 'شهرين', 'شهور', 'شهر' ) );
}

function zt_mv_calc( $cfg, $i ) {
	$W = zt_mv_w(); $rooms = zt_mv_nz( $i['r'] ?? 0 );
	if ( $rooms < 1 || $rooms > 30 ) { return array( 'error' => 'اكتب عدد الغرف (من 1 إلى 30).' ); }
	$inter = ( $i['ty'] ?? '' ) === 'inter'; $fc = zt_mv_find( $cfg['cities'], $i['fc'] ?? '' ); $tc = zt_mv_find( $cfg['cities'], $i['tc'] ?? '' );
	if ( $inter && $fc && $tc && $fc['k'] === $tc['k'] ) { return array( 'error' => 'اختر مدينتين مختلفتين للنقل بين المدن.' ); }
	$row = null;
	foreach ( $cfg['rooms'] as $r ) { if ( null === $r['max'] || $rooms <= $r['max'] ) { $row = $r; break; } }
	$items = array(); $manual = array();
	$add = function ( $label, $p ) use ( &$items, &$manual ) { if ( null === $p ) { $manual[] = $label; } $items[] = array( $label, $p ); };
	if ( ! $row ) { $l = 'نقل ' . zt_mv_c( $rooms, $W['room'] ); return array( 'rooms' => $rooms, 'inter' => $inter, 'fc' => $fc, 'tc' => $tc, 'trucks' => null, 'workers' => null, 'items' => array( array( $l, null ) ), 'manual' => array( $l ), 'lo' => null, 'hi' => null, 'big' => true ); }
	$add( 'نقل ' . zt_mv_c( $rooms, $W['room'] ), $row['price'] );
	if ( $inter && $fc && $tc ) {
		$rp = null;
		foreach ( $cfg['routes'] as $t ) { if ( ( $t['a'] === $fc['k'] && $t['b'] === $tc['k'] ) || ( $t['a'] === $tc['k'] && $t['b'] === $fc['k'] ) ) { $rp = $t['price']; break; } }
		$add( 'النقل بين ' . $fc['l'] . ' و' . $tc['l'] . ' (' . zt_mv_c( $row['trucks'], $W['truck'] ) . ')', zt_mv_mul( $rp, $row['trucks'] ) );
	}
	foreach ( array( array( 'من', zt_mv_nz( $i['ff'] ?? 0 ), empty( $i['ef'] ) ), array( 'إلى', zt_mv_nz( $i['ft'] ?? 0 ), empty( $i['et'] ) ) ) as $s ) {
		if ( $s[1] > 0 && $s[2] ) { $add( 'الأدوار بدون أسانسير (' . $s[0] . ': الدور ' . $s[1] . ')', zt_mv_mul( $cfg['floor'], $s[1] ) ); }
	}
	$app = zt_mv_nz( $i['fr'] ?? 0 ) + zt_mv_nz( $i['wm'] ?? 0 ); if ( $app > 0 ) { $add( 'نقل ' . zt_mv_c( $app, $W['app'] ) . ' (ثلاجة/غسالة)', zt_mv_mul( $cfg['appliance'], $app ) ); }
	$ac = zt_mv_nz( $i['ac'] ?? 0 ); if ( $ac > 0 ) { $add( 'فك وتركيب ' . zt_mv_c( $ac, $W['ac'] ), zt_mv_mul( $cfg['acInstall'], $ac ) ); }
	if ( ! empty( $i['fu'] ) ) { $add( 'فك وتركيب الأثاث (' . zt_mv_c( $rooms, $W['room'] ) . ')', zt_mv_mul( $cfg['furniture'], $rooms ) ); }
	if ( ! empty( $i['pk'] ) ) { $add( 'التغليف (' . zt_mv_c( $rooms, $W['room'] ) . ')', zt_mv_mul( $cfg['packing'], $rooms ) ); }
	$st = zt_mv_nz( $i['st'] ?? 0 ); if ( $st > 0 ) { $add( 'التخزين ' . zt_mv_c( $st, $W['month'] ), zt_mv_mul( $cfg['storage'], $st ) ); }
	$lo = 0; $hi = 0; $any = false;
	foreach ( $items as $it ) { if ( null !== $it[1] ) { $lo += $it[1][0]; $hi += $it[1][1]; $any = true; } }
	return array( 'rooms' => $rooms, 'inter' => $inter, 'fc' => $fc, 'tc' => $tc, 'trucks' => $row['trucks'], 'workers' => $row['workers'], 'items' => $items, 'manual' => $manual, 'lo' => $any ? $lo : null, 'hi' => $any ? $hi : null, 'big' => false );
}

function zt_mv_view( $cfg, $i ) {
	$r = zt_mv_calc( $cfg, $i );
	if ( isset( $r['error'] ) ) { return $r; }
	$W = zt_mv_w(); $F = 'zt_fmt';
	$big   = null === $r['trucks'] ? 'يحتاج معاينة لتحديد السيارات والعمال' : zt_ar_count( $r['trucks'], 'سيارة', 'سيارتان', 'سيارات', 'سيارة' ) . ' و' . zt_ar_count( $r['workers'], 'عامل', 'عاملان', 'عمال', 'عامل' );
	$lines = array( $r['inter'] && $r['fc'] && $r['tc'] ? 'النقل بين ' . $r['fc']['l'] . ' و' . $r['tc']['l'] : 'النقل داخل المدينة' . ( $r['fc'] ? ' (' . $r['fc']['l'] . ')' : '' ), 'عدد الغرف: ' . $F( $r['rooms'] ) );
	if ( null !== $r['lo'] ) { $lines[] = 'نطاق السعر التقديري: ' . zt_mv_pr( array( $r['lo'], $r['hi'] ) ) . ' ريال' . ( $r['manual'] ? ' (للبنود المسعّرة فقط)' : '' ); }
	else { $lines[] = 'السعر يتحدد بعد المعاينة.'; }
	$rows = array();
	foreach ( $r['items'] as $it ) { $rows[] = array( $it[0], null === $it[1] ? 'بعد المعاينة' : zt_mv_pr( $it[1] ) . ' ريال' ); }
	$notes = array( 'النطاق تقديري ولا يشمل أي بند مكتوب «بعد المعاينة»؛ السعر النهائي بعد المعاينة والاتفاق.' );
	$fr = zt_mv_nz( $i['fr'] ?? 0 ) + zt_mv_nz( $i['wm'] ?? 0 ); $ac = zt_mv_nz( $i['ac'] ?? 0 ); $st = zt_mv_nz( $i['st'] ?? 0 );
	$sum = 'حاسبة نقل العفش: ' . ( $r['inter'] && $r['fc'] && $r['tc'] ? 'من ' . $r['fc']['l'] . ' إلى ' . $r['tc']['l'] : 'داخل المدينة' ) . '، ' . zt_mv_c( $r['rooms'], $W['room'] ) . ( $ac > 0 ? '، ' . zt_mv_c( $ac, $W['ac'] ) . ' للفك والتركيب' : '' ) . ( $fr > 0 ? '، ' . zt_mv_c( $fr, $W['app'] ) : '' )
		. ( ! empty( $i['fu'] ) ? '، مع فك وتركيب الأثاث' : '' ) . ( ! empty( $i['pk'] ) ? '، مع التغليف' : '' ) . ( $st > 0 ? '، وتخزين ' . zt_mv_c( $st, $W['month'] ) : '' )
		. ( null !== $r['trucks'] ? ' ← التقدير: ' . $big : '' ) . ( null !== $r['lo'] ? '، السعر التقديري ' . zt_mv_pr( array( $r['lo'], $r['hi'] ) ) . ' ريال' : '' ) . '. أحتاج معاينة وعرض سعر.';
	return array( 'badge' => 'تقديري', 'big' => $big, 'lines' => $lines, 'table' => array( 'title' => 'تفصيل البنود', 'head' => array( 'البند', 'التقدير' ), 'rows' => $rows ), 'notes' => $notes,
		'wa' => zt_wa_message( $sum, $i['hood'] ?? '' ), 'ga' => array( 'rooms' => $r['rooms'], 'trucks' => null === $r['trucks'] ? -1 : $r['trucks'], 'inter' => $r['inter'] ? 1 : 0 ) );
}

/* ---------------------------------------------- request + rendering ---------------------------------------------- */

function zt_mv_request() {
	return array( 'ty' => 'inter' === zt_req( 'ty' ) ? 'inter' : 'in', 'fc' => zt_req( 'fc' ), 'tc' => zt_req( 'tc' ), 'r' => zt_req_num( 'r' ), 'fr' => zt_req_num( 'fr' ), 'wm' => zt_req_num( 'wm' ), 'ac' => zt_req_num( 'ac' ),
		'ff' => zt_req_num( 'ff' ), 'ef' => '1' === zt_req( 'ef' ), 'ft' => zt_req_num( 'ft' ), 'et' => '1' === zt_req( 'et' ), 'fu' => '1' === zt_req( 'fu' ), 'pk' => '1' === zt_req( 'pk' ), 'st' => zt_req_num( 'st' ), 'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ) );
}

function zt_mv_render( $ctx ) {
	$cfg = zt_mv_cfg(); $q = zt_mv_request();
	$num = function ( $name, $label, $val, $ph = '' ) { return '<label class="fld"><span>' . zt_esc( $label ) . '</span><input type="text" inputmode="numeric" dir="ltr" data-zt-num name="' . $name . '" value="' . zt_esc( null === $val ? '' : zt_fmt( $val ) ) . '" placeholder="' . zt_esc( $ph ) . '" autocomplete="off"></label>'; };
	$chk = function ( $name, $label, $on ) { return '<label class="zt-consent"><input type="checkbox" name="' . $name . '" value="1"' . ( $on ? ' checked' : '' ) . '><span>' . zt_esc( $label ) . '</span></label>'; };
	$city = function ( $name, $label, $cur, $hidden = false ) use ( $cfg ) {
		$h = '<label class="fld"' . ( $hidden ? ' hidden' : '' ) . '><span>' . zt_esc( $label ) . '</span><select name="' . $name . '">';
		foreach ( $cfg['cities'] as $c ) { $h .= '<option value="' . zt_esc( $c['k'] ) . '"' . ( $c['k'] === $cur ? ' selected' : '' ) . '>' . zt_esc( $c['l'] ) . '</option>'; }
		return $h . '</select></label>';
	};
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '">';
	echo '<div class="zt-row"><label class="fld"><span>نوع النقل</span><select name="ty"><option value="in"' . ( 'in' === $q['ty'] ? ' selected' : '' ) . '>داخل المدينة</option><option value="inter"' . ( 'inter' === $q['ty'] ? ' selected' : '' ) . '>بين مدينتين</option></select></label>';
	echo $city( 'fc', 'المدينة (أو المدينة المنقول منها)', $q['fc'] ) . $city( 'tc', 'المدينة المنقول إليها', $q['tc'], 'inter' !== $q['ty'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="zt-row">' . $num( 'r', 'عدد الغرف', $q['r'], '3' ) . $num( 'fr', 'عدد الثلاجات', $q['fr'], '0' ) . $num( 'wm', 'عدد الغسالات', $q['wm'], '0' ) . $num( 'ac', 'مكيفات للفك والتركيب', $q['ac'], '0' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<fieldset><legend>الأدوار</legend><div class="zt-row">' . $num( 'ff', 'الدور في المكان الحالي', $q['ff'], '0' ) . $chk( 'ef', 'يوجد أسانسير في المكان الحالي', $q['ef'] ) . $num( 'ft', 'الدور في المكان الجديد', $q['ft'], '0' ) . $chk( 'et', 'يوجد أسانسير في المكان الجديد', $q['et'] ) . '</div></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="zt-row">' . $chk( 'fu', 'فك وتركيب الأثاث', $q['fu'] ) . $chk( 'pk', 'التغليف', $q['pk'] ) . $num( 'st', 'التخزين (عدد الشهور)', $q['st'], '0' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<label class="fld"><span>الحي (اختياري)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label>';
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">قدّر النقل</button></div></form>';
	zt_mv_checklist_html();
}

function zt_mv_checklist() {
	$g = array();
	foreach ( zt_table( zt_opt( 'moving.checklist' ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] && '' !== $r[1] ) { $g[ $r[0] ][] = implode( ' | ', array_slice( $r, 1 ) ); } }
	return $g;
}
function zt_mv_checklist_html() {
	$g = zt_mv_checklist(); if ( ! $g ) { return; }
	echo '<section class="zt-check" data-zt-checklist><h3 class="zt-h3">قائمة تجهيزك للنقل</h3><p class="zt-noteline">علّم على ما أنجزته؛ يُحفظ في متصفحك فقط على هذا الجهاز.</p>';
	$n = 0;
	foreach ( $g as $period => $items ) {
		echo '<div data-group="' . zt_esc( $period ) . '"><h4 class="zt-h4">' . zt_esc( $period ) . '</h4><ul class="zt-checklist">';
		foreach ( $items as $it ) { $n++; echo '<li><label><input type="checkbox" data-k="c' . $n . '"> <span>' . zt_esc( $it ) . '</span></label></li>'; }
		echo '</ul></div>';
	}
	echo '<div class="zt-actions zt-jsonly" data-zt-chk-actions><button type="button" class="btn btn--ghost" data-act="print">اطبع القائمة</button><button type="button" class="btn btn--ghost" data-act="download">حمّل القائمة (txt)</button></div></section>';
}

function zt_mv_result( $ctx ) {
	if ( ! zt_has_req( array( 'r' ) ) ) { return ''; }
	return zt_result_html( zt_mv_view( zt_mv_cfg(), zt_mv_request() ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_mv_how( $ctx ) {
	$cfg = zt_mv_cfg(); $W = zt_mv_w();
	$h  = '<p>نحدد السيارات والعمال من عدد الغرف، ثم نجمع بنود الخدمة التي اخترتها. كل سعر معلن هنا يُقرأ من إعدادات الشركة، وأي بند بلا سعر معلن نكتب له «بعد المعاينة» بدل أن نخمّن رقماً.</p>';
	$h .= '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">عدد الغرف</th><th scope="col">السيارات</th><th scope="col">العمال</th><th scope="col">سعر النقل</th></tr></thead><tbody>';
	$prev = 0;
	foreach ( $cfg['rooms'] as $r ) { $lab = null === $r['max'] ? 'أكثر من ' . $prev : ( $prev + 1 === $r['max'] ? (string) $r['max'] : ( $prev + 1 ) . ' – ' . $r['max'] ); $h .= '<tr><th scope="row">' . zt_esc( $lab ) . '</th><td>' . zt_esc( zt_fmt( $r['trucks'] ) ) . '</td><td>' . zt_esc( zt_fmt( $r['workers'] ) ) . '</td><td>' . zt_esc( $r['price'] ? zt_mv_pr( $r['price'] ) . ' ريال' : 'بعد المعاينة' ) . '</td></tr>'; if ( null !== $r['max'] ) { $prev = $r['max']; } }
	$h .= '</tbody></table></div><div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">البند</th><th scope="col">السعر</th></tr></thead><tbody>';
	foreach ( array( 'كل دور بدون أسانسير (لكل جهة)' => 'floor', 'نقل الجهاز الكبير' => 'appliance', 'فك وتركيب المكيف' => 'acInstall', 'فك وتركيب الأثاث لكل غرفة' => 'furniture', 'التغليف لكل غرفة' => 'packing', 'التخزين الشهري' => 'storage' ) as $l => $k ) {
		$h .= '<tr><th scope="row">' . zt_esc( $l ) . '</th><td>' . zt_esc( $cfg[ $k ] ? zt_mv_pr( $cfg[ $k ] ) . ' ريال' : 'بعد المعاينة' ) . '</td></tr>';
	}
	foreach ( $cfg['routes'] as $t ) { $a = zt_mv_find( $cfg['cities'], $t['a'] ); $b = zt_mv_find( $cfg['cities'], $t['b'] ); $h .= '<tr><th scope="row">نقل بين ' . zt_esc( $a['l'] . ' و' . $b['l'] ) . ' (للسيارة)</th><td>' . zt_esc( zt_mv_pr( $t['price'] ) ) . ' ريال</td></tr>'; }
	return $h . '</tbody></table></div><p>النطاق تقديري، والسعر النهائي بعد المعاينة والاتفاق.</p>';
}

function zt_mv_examples( $ctx ) {
	$cfg = zt_mv_cfg(); if ( ! $cfg['rooms'] || ! $cfg['cities'] ) { return ''; }
	$ex = array(
		array( 'نقل شقة 2 غرف داخل المدينة', array( 'ty' => 'in', 'r' => 2, 'ef' => true, 'et' => true ) ),
		array( 'نقل 4 غرف داخل المدينة مع تغليف وفك وتركيب الأثاث', array( 'ty' => 'in', 'r' => 4, 'ef' => true, 'et' => true, 'pk' => true, 'fu' => true ) ),
	);
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الحالة</th><th scope="col">التقدير</th></tr></thead><tbody>';
	foreach ( $ex as $e ) { $v = zt_mv_view( $cfg, $e[1] + array( 'fc' => $cfg['cities'][0]['k'] ) ); if ( isset( $v['error'] ) ) { continue; } $h .= '<tr><th scope="row">' . zt_esc( $e[0] ) . '</th><td>' . zt_esc( $v['big'] . ' — ' . $v['lines'][2] ) . '</td></tr>'; }
	return $h . '</tbody></table></div>';
}
