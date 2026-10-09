<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 3 — حاسبة سعة الخزان وموعد التنظيف.
 * Volume from the shape and the dimensions, days of supply (only when the owner fills the per-person consumption), an estimated cleaning price from the
 * capacity tiers (or the lowest price of the cleaning service page), the next cleaning date, a calendar file and an opt-in reminder (consent unchecked).
 * Logic twin of assets/js/tank.js (tests/tools-parity.test.mjs).
 */

add_action( 'zad_tools_register_settings', function () {
	zt_register_settings( 'tank', 'خزان المياه', array(
		array( 'key' => 'locations', 'label' => 'أنواع الخزان ومدة التنظيف الدوري (مفتاح | الاسم | عدد الشهور)', 'type' => 'table', 'approval' => true,
			'source' => 'مدة 6 شهور مقترحة من عندي كنقطة بداية — تحتاج اعتماد صاحب الخدمة (لا مصدر رسمي مرفق)',
			'default' => "ground | خزان أرضي | 6\nroof | خزان علوي | 6" ),
		array( 'key' => 'liters_per_person', 'label' => 'استهلاك الفرد اليومي (لتر) — لحساب «كم يكفي الخزان»', 'type' => 'number', 'default' => '', 'min' => 1, 'max' => 2000, 'required' => false,
			'desc' => 'فارغ عمداً: لم أضع رقماً غير موثّق. إذا تركته فارغاً فلن يظهر سطر «يكفي كم يوم». اكتب رقماً من مصدر تعتمده (مثلاً تقارير وزارة البيئة والمياه والزراعة).' ),
		array( 'key' => 'price_tiers', 'label' => 'أسعار التنظيف حسب السعة (أقصى لتر | السعر بالريال)', 'type' => 'table', 'default' => '', 'required' => false,
			'desc' => 'مثال: <code>1000 | 150</code> ثم <code>3000 | 220</code> — تُطبَّق أول فئة تكفي السعة. فارغ = يُستخدم أقل سعر من صفحة خدمة التنظيف، وإلا «بعد المعاينة». الأرقام من عندك.' ),
		array( 'key' => 'service_page', 'label' => 'صفحة خدمة تنظيف الخزانات (لقراءة «يبدأ من»)', 'type' => 'page', 'default' => 0, 'required' => false, 'desc' => 'يُقرأ أقل سعر من باقاتها عبر الثيم؛ لا يُنسخ ولا يُعدَّل هنا.' ),
		array( 'key' => 'ref_sizes', 'label' => 'جدول مرجعي لأحجام شائعة (الاسم | الشكل rect/cyl_v/cyl_h | البعد1 | البعد2 | البعد3 بالمتر)', 'type' => 'table', 'required' => false,
			'default' => "مستطيل 2 × 1 × 1 م | rect | 2 | 1 | 1\nمستطيل 3 × 2 × 1.5 م | rect | 3 | 2 | 1.5\nمستطيل 4 × 3 × 2 م | rect | 4 | 3 | 2\nأسطواني رأسي قطر 1.5 م وارتفاع 2 م | cyl_v | 1.5 | 2 | \nأسطواني أفقي قطر 1.2 م وطول 2.5 م | cyl_h | 1.2 | 2.5 | ",
			'desc' => 'الأحجام أمثلة هندسية تُحسب لترات بالمعادلة؛ ليست وعداً بأحجام معينة.' ),
	) );
} );

zt_register_tool( 'tank', array(
	'title' => 'حاسبة سعة الخزان وموعد التنظيف', 'desc' => 'احسب سعة خزانك باللتر من أبعاده، وتعرّف على موعد التنظيف القادم وتكلفته التقديرية، وفعّل تذكيراً بالموعد.',
	'collects_data' => true,
	'render' => 'zt_tank_render', 'result' => 'zt_tank_result', 'how' => 'zt_tank_how', 'examples' => 'zt_tank_examples',
	'js' => ZT_URL . 'assets/js/tank.js',
) );

function zt_tank_cfg() {
	$locs = array();
	foreach ( zt_table( zt_opt( 'tank.locations' ) ) as $r ) {
		if ( count( $r ) < 3 || '' === $r[0] ) { continue; }
		$m = zt_num( $r[2] );
		if ( null !== $m && $m >= 1 ) { $locs[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1], 'm' => (int) $m ); }
	}
	$tiers = array();
	foreach ( zt_table( zt_opt( 'tank.price_tiers' ) ) as $r ) {
		if ( count( $r ) < 2 ) { continue; }
		$mx = zt_num( $r[0] ); $pr = zt_num( $r[1] );
		if ( $mx > 0 && $pr > 0 ) { $tiers[] = array( 'max' => $mx == (int) $mx ? (int) $mx : $mx, 'price' => $pr == (int) $pr ? (int) $pr : $pr ); }
	}
	usort( $tiers, function ( $a, $b ) { return $a['max'] <=> $b['max']; } );
	$from = null;
	$sp   = (int) zt_opt( 'tank.service_page' );
	if ( ! $tiers && $sp && function_exists( 'zad_min_price' ) && function_exists( 'zad_price_rows' ) ) { $m = zad_min_price( zad_price_rows( $sp ) ); $from = $m ? ( $m == (int) $m ? (int) $m : $m ) : null; }
	$lpp = zt_num( zt_opt( 'tank.liters_per_person' ) );
	return array( 'locs' => $locs, 'lpp' => $lpp > 0 ? ( $lpp == (int) $lpp ? (int) $lpp : $lpp ) : null, 'tiers' => $tiers, 'fromPrice' => $from, 'ics' => home_url( '/' ) );
}

/* ---------------------------------------------- the logic (PHP twin) ---------------------------------------------- */

const ZT_TANK_SERVICE = 'تنظيف خزان المياه';

function zt_tank_find( $list, $k ) { foreach ( $list as $it ) { if ( $it['k'] === $k ) { return $it; } } return $list ? $list[0] : null; }
function zt_tank_months_label( $m ) { return zt_ar_count( $m, 'شهر', 'شهرين', 'شهور', 'شهر' ); }
function zt_tank_litres( $shape, $a, $b, $c, $unit ) {
	$f = 'cm' === $unit ? 0.01 : 1; $A = $a * $f; $B = $b * $f; $C = $c * $f;
	if ( 'rect' === $shape ) { if ( ! ( $A > 0 && $B > 0 && $C > 0 ) || $A > 100 || $B > 100 || $C > 100 ) { return null; } $m3 = $A * $B * $C; }
	else { if ( ! ( $A > 0 && $B > 0 ) || $A > 100 || $B > 100 ) { return null; } $m3 = M_PI * ( $A / 2 ) * ( $A / 2 ) * $B; }
	return (int) floor( $m3 * 1000 + 0.5 );
}
function zt_tank_price( $cfg, $l ) {
	foreach ( $cfg['tiers'] as $t ) { if ( $l <= $t['max'] ) { return array( 'kind' => 'tier', 'value' => $t['price'] ); } }
	return ( 0 === count( $cfg['tiers'] ) && $cfg['fromPrice'] ) ? array( 'kind' => 'from', 'value' => $cfg['fromPrice'] ) : null;
}
function zt_tank_calc( $cfg, $i, $today ) {
	$shape = in_array( $i['shape'] ?? '', array( 'cyl_v', 'cyl_h' ), true ) ? $i['shape'] : 'rect';
	$l = zt_tank_litres( $shape, $i['a'] ?? null, $i['b'] ?? null, $i['c'] ?? null, $i['unit'] ?? 'm' );
	if ( null === $l ) { return array( 'error' => 'اكتب أبعاد الخزان بأرقام صحيحة (وبالوحدة المختارة).' ); }
	$loc  = zt_tank_find( $cfg['locs'], $i['loc'] ?? '' );
	$last = '' !== zt_add_months_str( $i['last'] ?? '', 0 ) ? $i['last'] : '';
	if ( $last && $last > $today ) { return array( 'error' => 'تاريخ آخر تنظيف لا يمكن أن يكون في المستقبل.' ); }
	$next   = $last && $loc ? zt_add_months_str( $last, $loc['m'] ) : '';
	$people = ( $i['people'] ?? 0 ) > 0 ? (int) floor( $i['people'] ) : 0;
	$days   = $cfg['lpp'] && $people > 0 ? (int) floor( $l / ( $people * $cfg['lpp'] ) ) : null;
	return array( 'shape' => $shape, 'litres' => $l, 'm3' => $l / 1000, 'loc' => $loc, 'last' => $last, 'next' => $next, 'overdue' => '' !== $next && $next < $today, 'people' => $people, 'days' => $days, 'price' => zt_tank_price( $cfg, $l ) );
}
function zt_tank_view( $cfg, $i, $today ) {
	$r = zt_tank_calc( $cfg, $i, $today );
	if ( isset( $r['error'] ) ) { return $r; }
	$shapes = array( 'rect' => 'مستطيل', 'cyl_v' => 'أسطواني رأسي', 'cyl_h' => 'أسطواني أفقي' );
	$lines  = array( 'الحجم: ' . zt_fmt( $r['m3'] ) . ' م³', 'النوع: ' . ( $r['loc'] ? $r['loc']['l'] : '' ) . ' — ' . $shapes[ $r['shape'] ] );
	if ( null !== $r['days'] ) { $lines[] = $r['days'] >= 1 ? 'يكفي ' . zt_fmt( $r['people'] ) . ' أشخاص نحو ' . zt_ar_count( $r['days'], 'يوم', 'يومين', 'أيام', 'يوم' ) . ' إذا امتلأ.' : 'يكفي ' . zt_fmt( $r['people'] ) . ' أشخاص أقل من يوم إذا امتلأ.'; }
	if ( $r['price'] ) { $lines[] = 'tier' === $r['price']['kind'] ? 'تكلفة التنظيف التقديرية: ' . zt_fmt( $r['price']['value'] ) . ' ريال' : 'تكلفة التنظيف تبدأ من ' . zt_fmt( $r['price']['value'] ) . ' ريال'; }
	else { $lines[] = 'تكلفة التنظيف تتحدد بعد المعاينة.'; }
	$links = array(); $optin = null;
	if ( $r['loc'] ) {
		if ( $r['next'] && ! $r['overdue'] ) {
			$lines[] = 'موعد التنظيف القادم: ' . zt_ar_date( $r['next'] ) . ' (كل ' . zt_tank_months_label( $r['loc']['m'] ) . ' من آخر تنظيف)';
			$links[] = array( 'href' => ( $cfg['ics'] ? $cfg['ics'] . '?' . zt_qs_string( array( 'd' => $r['next'], 't' => ZT_TANK_SERVICE, 'zad_ics' => '1' ) ) : '' ), 'label' => 'أضف الموعد للتقويم (.ics)', 'event' => 'tool_ics' );
			$optin   = array( 'date' => $r['next'], 'service' => ZT_TANK_SERVICE, 'title' => 'ذكّرني بموعد التنظيف' );
		} elseif ( $r['next'] ) { $lines[] = 'موعد التنظيف الدوري كان في ' . zt_ar_date( $r['next'] ) . ' — يُنصح بحجز التنظيف الآن.'; }
		else { $lines[] = 'يُنصح بتنظيف الخزان كل ' . zt_tank_months_label( $r['loc']['m'] ) . '. أدخل تاريخ آخر تنظيف لنحسب لك الموعد القادم.'; }
	}
	$sum = 'حاسبة الخزان: ' . ( $r['loc'] ? $r['loc']['l'] : 'خزان' ) . ' سعته ' . zt_fmt( $r['litres'] ) . ' لتر' . ( $r['last'] ? '، وآخر تنظيف ' . zt_ar_date( $r['last'] ) : '' ) . '. أرغب في تنظيفه ومعرفة السعر.';
	return array( 'badge' => 'تقديري', 'big' => zt_fmt( $r['litres'] ) . ' لتر', 'lines' => $lines, 'notes' => array( 'الأرقام تقديرية من الأبعاد التي كتبتها؛ المعاينة تحدد السعة والسعر النهائيين.' ),
		'wa' => zt_wa_message( $sum, $i['hood'] ?? '' ), 'links' => $links, 'optin' => $optin, 'ga' => array( 'litres' => $r['litres'], 'overdue' => $r['overdue'] ? 1 : 0 ) );
}

/* ---------------------------------------------- request + rendering ---------------------------------------------- */

function zt_tank_request() {
	$last = zt_req( 'last' );
	return array( 'loc' => zt_req( 'loc' ), 'shape' => zt_req( 'shape', 'rect' ), 'a' => zt_req_num( 'a' ), 'b' => zt_req_num( 'b' ), 'c' => zt_req_num( 'c' ),
		'unit' => 'cm' === zt_req( 'unit' ) ? 'cm' : 'm', 'last' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $last ) ? $last : '', 'people' => zt_req_num( 'people' ), 'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ) );
}

function zt_tank_render( $ctx ) {
	$cfg = zt_tank_cfg(); $q = zt_tank_request();
	$num = function ( $name, $label, $val, $hidden = false ) { return '<label class="fld"' . ( $hidden ? ' hidden' : '' ) . '><span>' . zt_esc( $label ) . '</span><input type="text" inputmode="decimal" dir="ltr" data-zt-num name="' . $name . '" value="' . zt_esc( null === $val ? '' : zt_fmt( $val ) ) . '" autocomplete="off"></label>'; };
	$shape = $q['shape'];
	$labels = array( 'rect' => array( 'الطول', 'العرض', 'العمق (ارتفاع الماء)' ), 'cyl_v' => array( 'القطر', 'الارتفاع', '' ), 'cyl_h' => array( 'القطر', 'الطول', '' ) );
	$lab = $labels[ isset( $labels[ $shape ] ) ? $shape : 'rect' ];
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '">';
	echo '<div class="zt-row"><label class="fld"><span>نوع الخزان</span><select name="loc">';
	foreach ( $cfg['locs'] as $l ) { echo '<option value="' . zt_esc( $l['k'] ) . '"' . ( $l['k'] === $q['loc'] ? ' selected' : '' ) . '>' . zt_esc( $l['l'] ) . '</option>'; }
	echo '</select></label><label class="fld"><span>شكل الخزان</span><select name="shape">';
	foreach ( array( 'rect' => 'مستطيل', 'cyl_v' => 'أسطواني رأسي', 'cyl_h' => 'أسطواني أفقي' ) as $k => $t ) { echo '<option value="' . $k . '"' . ( $k === $shape ? ' selected' : '' ) . '>' . zt_esc( $t ) . '</option>'; }
	echo '</select></label><label class="fld"><span>وحدة القياس</span><select name="unit"><option value="m"' . ( 'm' === $q['unit'] ? ' selected' : '' ) . '>متر</option><option value="cm"' . ( 'cm' === $q['unit'] ? ' selected' : '' ) . '>سنتيمتر</option></select></label></div>';
	echo '<div class="zt-row">' . $num( 'a', $lab[0], $q['a'] ) . $num( 'b', $lab[1], $q['b'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	echo $num( 'c', '' !== $lab[2] ? $lab[2] : 'البعد الثالث', $q['c'], '' === $lab[2] ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div><div class="zt-row"><label class="fld"><span>تاريخ آخر تنظيف (اختياري)</span><input type="date" name="last" dir="ltr" value="' . zt_esc( $q['last'] ) . '"></label>';
	echo $num( 'people', 'عدد أفراد الأسرة (اختياري)', $q['people'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<label class="fld"><span>الحي (اختياري)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label></div>';
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">احسب السعة والموعد</button></div></form>';
}

function zt_tank_result( $ctx ) {
	if ( ! zt_has_req( array( 'a', 'b', 'c' ) ) ) { return ''; }
	return zt_result_html( zt_tank_view( zt_tank_cfg(), zt_tank_request(), wp_date( 'Y-m-d' ) ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_tank_how( $ctx ) {
	$cfg = zt_tank_cfg();
	$h  = '<p>نحسب الحجم من أبعاد الخزان الداخلية ثم نحوله إلى لترات (المتر المكعب = 1000 لتر):</p><ul>';
	$h .= '<li><strong>مستطيل:</strong> الطول × العرض × العمق</li><li><strong>أسطواني رأسي:</strong> π × (القطر ÷ 2)² × الارتفاع</li><li><strong>أسطواني أفقي:</strong> π × (القطر ÷ 2)² × الطول</li></ul>';
	$h .= '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">العامل</th><th scope="col">القيمة</th></tr></thead><tbody>';
	foreach ( $cfg['locs'] as $l ) { $h .= '<tr><th scope="row">مدة التنظيف الدوري — ' . zt_esc( $l['l'] ) . '</th><td>كل ' . zt_esc( zt_tank_months_label( $l['m'] ) ) . '</td></tr>'; }
	$h .= '<tr><th scope="row">استهلاك الفرد اليومي</th><td>' . ( $cfg['lpp'] ? zt_esc( zt_fmt( $cfg['lpp'] ) ) . ' لتر' : 'غير محدد — لا نحسب «كم يكفي»' ) . '</td></tr>';
	if ( $cfg['tiers'] ) { foreach ( $cfg['tiers'] as $t ) { $h .= '<tr><th scope="row">تنظيف حتى ' . zt_esc( zt_fmt( $t['max'] ) ) . ' لتر</th><td>' . zt_esc( zt_fmt( $t['price'] ) ) . ' ريال</td></tr>'; } }
	elseif ( $cfg['fromPrice'] ) { $h .= '<tr><th scope="row">سعر التنظيف</th><td>يبدأ من ' . zt_esc( zt_fmt( $cfg['fromPrice'] ) ) . ' ريال (من صفحة الخدمة)</td></tr>'; }
	else { $h .= '<tr><th scope="row">سعر التنظيف</th><td>بعد المعاينة</td></tr>'; }
	return $h . '</tbody></table></div><p>موعد التنظيف القادم = تاريخ آخر تنظيف + المدة الدورية. السعة تقديرية لأنها من قياسك أنت.</p>';
}

/** Worked examples + the reference size table (litres computed by the formula). */
function zt_tank_examples( $ctx ) {
	$cfg = zt_tank_cfg(); $rows = array();
	foreach ( zt_table( zt_opt( 'tank.ref_sizes' ) ) as $r ) {
		if ( count( $r ) < 5 ) { continue; }
		$l = zt_tank_litres( $r[1], zt_num( $r[2] ), zt_num( $r[3] ), zt_num( $r[4] ), 'm' );
		if ( null !== $l ) { $rows[] = array( $r[0], $l ); }
	}
	if ( ! $rows ) { return ''; }
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الخزان</th><th scope="col">السعة التقريبية</th><th scope="col">التنظيف المتوقع</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$p = zt_tank_price( $cfg, $r[1] );
		$h .= '<tr><th scope="row">' . zt_esc( $r[0] ) . '</th><td>' . zt_esc( zt_fmt( $r[1] ) ) . ' لتر</td><td>' . zt_esc( $p ? ( 'tier' === $p['kind'] ? zt_fmt( $p['value'] ) . ' ريال' : 'يبدأ من ' . zt_fmt( $p['value'] ) . ' ريال' ) : 'بعد المعاينة' ) . '</td></tr>';
	}
	return $h . '</tbody></table></div>';
}
