<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 1 — حاسبة حجم المكيف (BTU / طن).
 * Every coefficient lives in the settings tab «حاسبة المكيف» and is flagged until the AC technician approves it; the page stays closed until then.
 * Formula (identical in assets/js/ac-size.js — tests/tools-parity.test.mjs):
 *   base  = area × base_btu_per_m2 × (height ÷ ref_height)
 *   load  = round( base × (1 + Σ percentages ÷ 100) + extra_people × person_btu )     extra_people = max(0, people − people_free)
 *   size  = the smallest available size ≥ load ; above the largest size: the fewest units of one size that cover it (≤ max_units)
 */

/* ---------------------------------------------- settings ---------------------------------------------- */

add_action( 'zad_tools_register_settings', function () {
	$flag = 'قيمة افتراضية مقترحة من عندي كنقطة بداية (قاعدة عامة تقريبية) — يعتمدها أو يعدّلها فني التكييف قبل النشر';
	zt_register_settings( 'ac-size', 'حاسبة المكيف', array(
		array( 'key' => 'base_btu_per_m2', 'label' => 'وحدات حرارية (BTU/س) لكل م² — المعامل الأساسي', 'type' => 'number', 'default' => 700, 'min' => 100, 'max' => 3000, 'approval' => true, 'source' => $flag ),
		array( 'key' => 'ref_height', 'label' => 'ارتفاع السقف المرجعي (م) الذي يُحسب عنده المعامل الأساسي', 'type' => 'number', 'default' => 3, 'min' => 2, 'max' => 6, 'approval' => true, 'source' => 'الارتفاع الافتراضي 3 م من أمر التنفيذ؛ اعتماد كونه المرجع من فني التكييف' ),
		array( 'key' => 'rooms', 'label' => 'نوع الغرفة (مفتاح | الاسم | نسبة الإضافة %) — أول سطر هو الافتراضي', 'type' => 'table', 'approval' => true, 'source' => $flag,
			'default' => "bedroom | غرفة نوم | 0\nliving | صالة / معيشة | 10\nmajlis | مجلس | 10\nkitchen | مطبخ | 30" ),
		array( 'key' => 'sun', 'label' => 'التعرض للشمس (مفتاح | الاسم | %) — أول سطر هو الافتراضي', 'type' => 'table', 'approval' => true, 'source' => $flag,
			'default' => "normal | عادي | 0\nshade | في الظل / جهة شمالية | -10\nsunny | شمس مباشرة | 10" ),
		array( 'key' => 'top_floor_pct', 'label' => 'إضافة الدور الأخير (تحت السطح) %', 'type' => 'number', 'default' => 15, 'min' => 0, 'max' => 100, 'approval' => true, 'source' => $flag ),
		array( 'key' => 'win', 'label' => 'حجم النوافذ (مفتاح | الاسم | %) — أول سطر هو الافتراضي', 'type' => 'table', 'approval' => true, 'source' => $flag,
			'default' => "normal | عادية | 0\nlarge | كبيرة / واجهة زجاجية | 10\nsmall | صغيرة | -5" ),
		array( 'key' => 'ins', 'label' => 'العزل (مفتاح | الاسم | %) — أول سطر هو الافتراضي', 'type' => 'table', 'approval' => true, 'source' => $flag,
			'default' => "normal | عادي | 0\ngood | جيد (عزل حراري) | -10\nweak | ضعيف | 15" ),
		array( 'key' => 'people_free', 'label' => 'عدد الأشخاص المشمول في الأساس', 'type' => 'number', 'default' => 2, 'min' => 0, 'max' => 20, 'approval' => true, 'source' => $flag ),
		array( 'key' => 'person_btu', 'label' => 'إضافة كل شخص زيادة (BTU)', 'type' => 'number', 'default' => 600, 'min' => 0, 'max' => 5000, 'approval' => true, 'source' => $flag ),
		array( 'key' => 'sizes', 'label' => 'المقاسات المتاحة (BTU) مفصولة بفاصلة', 'type' => 'text', 'default' => '12000, 18000, 24000, 30000, 36000, 48000, 60000', 'approval' => true, 'source' => 'المقاسات المتداولة في السوق — يؤكدها فني التكييف حسب ما تركّبونه فعلاً' ),
		array( 'key' => 'max_units', 'label' => 'أقصى عدد مكيفات تُقترح إذا تجاوز الحمل أكبر مقاس', 'type' => 'number', 'default' => 4, 'min' => 1, 'max' => 10, 'approval' => true, 'source' => $flag ),
		array( 'key' => 'ton_btu', 'label' => 'وحدات BTU/س في الطن', 'type' => 'number', 'default' => 12000, 'min' => 1000, 'max' => 20000, 'source' => 'تعريف الطن التبريدي (12000 BTU/س) — ثابت فيزيائي لا يحتاج اعتماداً' ),
		array( 'key' => 'table_from', 'label' => 'جدول المراجع: من مساحة (م²)', 'type' => 'number', 'default' => 9, 'min' => 1, 'max' => 100, 'source' => 'من أمر التنفيذ (9 م²)' ),
		array( 'key' => 'table_to', 'label' => 'جدول المراجع: إلى مساحة (م²)', 'type' => 'number', 'default' => 40, 'min' => 2, 'max' => 200, 'source' => 'من أمر التنفيذ (40 م²)' ),
		array( 'key' => 'related_page', 'label' => 'صفحة «حاسبة كهرباء المكيف» (للرابط تحت النتيجة)', 'type' => 'page', 'default' => 0, 'required' => false, 'desc' => 'اختياري؛ يظهر الرابط فقط إن اخترت صفحة منشورة.' ),
	) );
} );

zt_register_tool( 'ac-size', array(
	'title' => 'حاسبة حجم المكيف', 'desc' => 'احسب السعة المناسبة لغرفتك بالوحدات الحرارية والطن، وشاهد كيف وصلنا للرقم.',
	'render' => 'zt_ac_render', 'result' => 'zt_ac_result', 'how' => 'zt_ac_how', 'examples' => 'zt_ac_examples', 'related' => 'zt_ac_related',
	'js' => ZT_URL . 'assets/js/ac-size.js',
) );

/* ---------------------------------------------- config (the same array goes to JS as JSON) ---------------------------------------------- */

function zt_ac_list( $setting ) {
	$out = array();
	foreach ( zt_table( zt_opt( 'ac-size.' . $setting ) ) as $r ) {
		if ( count( $r ) < 2 ) { continue; }
		$p = isset( $r[2] ) ? zt_num( $r[2] ) : 0;
		$out[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1], 'p' => null === $p ? 0 : ( $p == (int) $p ? (int) $p : $p ) );
	}
	return $out;
}
function zt_ac_cfg() {
	$sizes = array();
	foreach ( explode( ',', zt_digits_en( (string) zt_opt( 'ac-size.sizes' ) ) ) as $s ) { $n = zt_num( $s ); if ( $n && $n > 0 ) { $sizes[] = (int) $n; } }
	sort( $sizes );
	$n = function ( $k ) { $v = (float) zt_opt( 'ac-size.' . $k ); return $v == (int) $v ? (int) $v : $v; };
	return array(
		'base' => $n( 'base_btu_per_m2' ), 'refH' => $n( 'ref_height' ), 'personBtu' => $n( 'person_btu' ), 'peopleFree' => $n( 'people_free' ), 'topPct' => $n( 'top_floor_pct' ),
		'tonBtu' => $n( 'ton_btu' ), 'maxUnits' => max( 1, (int) $n( 'max_units' ) ), 'sizes' => array_values( array_unique( $sizes ) ),
		'rooms' => zt_ac_list( 'rooms' ), 'sun' => zt_ac_list( 'sun' ), 'win' => zt_ac_list( 'win' ), 'ins' => zt_ac_list( 'ins' ),
	);
}

/* ---------------------------------------------- the formula (PHP twin of ac-size.js) ---------------------------------------------- */

function zt_ac_sel( $list, $k ) { foreach ( $list as $it ) { if ( $it['k'] === $k ) { return $it; } } return $list ? $list[0] : array( 'k' => '', 'l' => '', 'p' => 0 ); }
function zt_ac_pick( $sizes, $need, $max ) {
	for ( $n = 1; $n <= $max; $n++ ) {
		$per = (int) ceil( $need / $n );
		foreach ( $sizes as $s ) { if ( $s >= $per ) { return array( 'units' => $n, 'size' => $s ); } }
	}
	return null;
}
function zt_ac_round( $x ) { return (int) floor( $x + 0.5 ); }

/** $i = array( a,l,w,h,rt,sn,wn,in,top,p ) numbers/null/strings — same keys as the JS input. */
function zt_ac_calc( $cfg, $i ) {
	$a = $i['a'] ?? null; $l = $i['l'] ?? null; $w = $i['w'] ?? null; $h = $i['h'] ?? null; $p = $i['p'] ?? null;
	$area = $a > 0 ? $a : ( $l > 0 && $w > 0 ? $l * $w : 0 );
	if ( ! ( $area > 0 ) || $area > 1000 ) { return array( 'error' => 'اكتب مساحة الغرفة (م²) أو الطول والعرض.' ); }
	$h = $h > 0 ? $h : $cfg['refH'];
	if ( $h > 20 ) { return array( 'error' => 'ارتفاع السقف غير منطقي، اكتبه بالمتر.' ); }
	$base = $area * $cfg['base'] * ( $h / $cfg['refH'] );
	$rm = zt_ac_sel( $cfg['rooms'], $i['rt'] ?? '' ); $sn = zt_ac_sel( $cfg['sun'], $i['sn'] ?? '' ); $wn = zt_ac_sel( $cfg['win'], $i['wn'] ?? '' ); $ins = zt_ac_sel( $cfg['ins'], $i['in'] ?? '' );
	$top   = ! empty( $i['top'] );
	$pct   = $rm['p'] + $sn['p'] + ( $top ? $cfg['topPct'] : 0 ) + $wn['p'] + $ins['p'];
	$people = $p > 0 ? (int) floor( $p ) : 0;
	$extra = max( 0, $people - $cfg['peopleFree'] );
	$total = zt_ac_round( $base * ( 1 + $pct / 100 ) + $extra * $cfg['personBtu'] );
	return array( 'area' => $area, 'h' => $h, 'base' => zt_ac_round( $base ), 'rm' => $rm, 'sn' => $sn, 'wn' => $wn, 'ins' => $ins, 'top' => $top, 'pct' => $pct, 'people' => $people, 'extra' => $extra,
		'total' => $total, 'fit' => zt_ac_pick( $cfg['sizes'], $total, $cfg['maxUnits'] ) );
}
function zt_ac_tons( $cfg, $size ) { return zt_fmt( $size / $cfg['tonBtu'] ); }
function zt_ac_size_label( $cfg, $fit ) {
	if ( ! $fit ) { return ''; }
	return 1 === $fit['units'] ? 'مكيف ' . zt_ac_tons( $cfg, $fit['size'] ) . ' طن' : zt_ar_count( $fit['units'], 'مكيف', 'مكيفان', 'مكيفات', 'مكيف' ) . '، كل واحد ' . zt_ac_tons( $cfg, $fit['size'] ) . ' طن';
}
function zt_ac_view( $cfg, $i ) {
	$r = zt_ac_calc( $cfg, $i );
	if ( isset( $r['error'] ) ) { return $r; }
	$big  = $r['fit'] ? zt_ac_size_label( $cfg, $r['fit'] ) : 'الحمل كبير ويحتاج معاينة';
	$rows = array( array( 'الأساس: ' . zt_fmt( $r['area'] ) . ' م² × ' . zt_fmt( $cfg['base'] ) . ' × (الارتفاع ' . zt_fmt( $r['h'] ) . ' ÷ ' . zt_fmt( $cfg['refH'] ) . ')', zt_fmt( $r['base'] ) . ' وحدة' ) );
	foreach ( array( array( 'نوع الغرفة', $r['rm'] ), array( 'التعرض للشمس', $r['sn'] ), array( 'النوافذ', $r['wn'] ), array( 'العزل', $r['ins'] ) ) as $x ) {
		if ( 0 != $x[1]['p'] ) { $rows[] = array( $x[0] . ': ' . $x[1]['l'], zt_pct_label( $x[1]['p'] ) ); }
	}
	if ( $r['top'] ) { $rows[] = array( 'دور أخير (تحت السطح)', zt_pct_label( $cfg['topPct'] ) ); }
	if ( $r['extra'] > 0 ) { $rows[] = array( 'أشخاص إضافيون فوق ' . zt_fmt( $cfg['peopleFree'] ) . ' (' . zt_fmt( $r['extra'] ) . ')', '+' . zt_fmt( $r['extra'] * $cfg['personBtu'] ) . ' وحدة' ); }
	$rows[]  = array( 'الحمل الإجمالي بعد التقريب', zt_fmt( $r['total'] ) . ' وحدة' );
	$lines   = array( 'المساحة المحسوبة: ' . zt_fmt( $r['area'] ) . ' م²' . ( $r['h'] != $cfg['refH'] ? ' — الارتفاع ' . zt_fmt( $r['h'] ) . ' م' : '' ), 'الحمل الحراري التقديري: ' . zt_fmt( $r['total'] ) . ' وحدة حرارية/ساعة' );
	$notes   = array( 'القيمة تقديرية؛ الفني يؤكد الحمل الفعلي في المعاينة.' );
	if ( $r['fit'] ) {
		$lines[] = 'السعة المقترحة: ' . zt_fmt( $r['fit']['size'] ) . ' وحدة حرارية' . ( $r['fit']['units'] > 1 ? ' لكل مكيف' : '' );
		if ( $r['fit']['units'] > 1 ) { array_unshift( $notes, 'الحمل أكبر من أكبر مكيف متاح، فنقترح توزيعه على أكثر من مكيف.' ); }
	} else { array_unshift( $notes, 'الحمل أكبر مما تغطيه المقاسات المعتادة، تواصل معنا لنحدد التجهيز المناسب.' ); }
	$sum = 'حاسبة المكيف: غرفة ' . zt_fmt( $r['area'] ) . ' م² (' . $r['rm']['l'] . ( $r['top'] ? '، دور أخير' : '' ) . ') ← الحمل التقديري ' . zt_fmt( $r['total'] ) . ' وحدة حرارية ← ' . ( $r['fit'] ? 'المقترح: ' . $big : $big ) . '. أحتاج استشارة بخصوص التركيب.';
	return array( 'badge' => 'تقديري', 'big' => $big, 'lines' => $lines, 'table' => array( 'title' => 'كيف وصلنا للرقم', 'head' => array( 'البند', 'الأثر' ), 'rows' => $rows ),
		'notes' => $notes, 'wa' => zt_wa_message( $sum, $i['hood'] ?? '' ), 'ga' => array( 'btu' => $r['total'], 'units' => $r['fit'] ? $r['fit']['units'] : 0 ) );
}

/* ---------------------------------------------- request (GET) → input ---------------------------------------------- */

function zt_ac_request() {
	return array(
		'a' => zt_req_num( 'a' ), 'l' => zt_req_num( 'l' ), 'w' => zt_req_num( 'w' ), 'h' => zt_req_num( 'h' ), 'p' => zt_req_num( 'p' ),
		'rt' => zt_req( 'rt' ), 'sn' => zt_req( 'sn' ), 'wn' => zt_req( 'wn' ), 'in' => zt_req( 'in' ), 'top' => '1' === zt_req( 'top' ),
		'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ),
	);
}

/* ---------------------------------------------- rendering ---------------------------------------------- */

function zt_ac_select( $name, $label, $list, $cur ) {
	$h = '<label class="fld"><span>' . zt_esc( $label ) . '</span><select name="' . zt_esc( $name ) . '">';
	foreach ( $list as $it ) { $h .= '<option value="' . zt_esc( $it['k'] ) . '"' . ( $it['k'] === $cur ? ' selected' : '' ) . '>' . zt_esc( $it['l'] ) . '</option>'; }
	return $h . '</select></label>';
}
function zt_ac_num_input( $name, $label, $val, $ph = '' ) {
	return '<label class="fld"><span>' . zt_esc( $label ) . '</span><input type="text" inputmode="decimal" dir="ltr" data-zt-num name="' . zt_esc( $name ) . '" value="' . zt_esc( null === $val ? '' : zt_fmt( $val ) ) . '"' . ( '' !== $ph ? ' placeholder="' . zt_esc( $ph ) . '"' : '' ) . ' autocomplete="off"></label>';
}

function zt_ac_render( $ctx ) {
	$cfg = zt_ac_cfg(); $q = zt_ac_request();
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '">';
	echo '<fieldset><legend>مقاسات الغرفة</legend><div class="zt-row">' . zt_ac_num_input( 'l', 'الطول (م)', $q['l'], '4' ) . zt_ac_num_input( 'w', 'العرض (م)', $q['w'], '4' ) . zt_ac_num_input( 'a', 'أو اكتب المساحة (م²)', $q['a'], '16' ) . zt_ac_num_input( 'h', 'ارتفاع السقف (م)', $q['h'], zt_fmt( $cfg['refH'] ) ) . '</div></fieldset>';
	echo '<div class="zt-row">' . zt_ac_select( 'rt', 'نوع الغرفة', $cfg['rooms'], $q['rt'] ) . zt_ac_select( 'sn', 'التعرض للشمس', $cfg['sun'], $q['sn'] ) . zt_ac_select( 'wn', 'النوافذ', $cfg['win'], $q['wn'] ) . zt_ac_select( 'in', 'العزل', $cfg['ins'], $q['in'] ) . '</div>';
	echo '<div class="zt-row">' . zt_ac_num_input( 'p', 'عدد الأشخاص المعتاد', $q['p'], zt_fmt( $cfg['peopleFree'] ) );
	echo '<label class="zt-consent"><input type="checkbox" name="top" value="1"' . ( $q['top'] ? ' checked' : '' ) . '><span>الغرفة في الدور الأخير (تحت السطح)</span></label></div>';
	echo '<label class="fld"><span>الحي (اختياري، يُضاف لرسالة واتساب)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label>';
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">احسب المكيف المناسب</button></div></form>';
}

/** The answer for a shared link (?l=4&w=4…) or a no-JS submit: server-computed, same renderer as the JS one. */
function zt_ac_result( $ctx ) {
	if ( ! zt_has_req( array( 'a', 'l', 'w' ) ) ) { return ''; }
	return zt_result_html( zt_ac_view( zt_ac_cfg(), zt_ac_request() ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_ac_how( $ctx ) {
	$cfg = zt_ac_cfg(); $F = 'zt_fmt';
	$h  = '<p>نحسب الحمل الحراري للغرفة ثم نختار أصغر مكيف يغطيه. كل رقم هنا يُقرأ مباشرة من إعدادات الأداة ويعتمده فني التكييف:</p>';
	$h .= '<p><strong>الحمل = المساحة × ' . $F( $cfg['base'] ) . ' × (ارتفاع السقف ÷ ' . $F( $cfg['refH'] ) . ') × (1 + مجموع نسب التعديل) + إضافة الأشخاص</strong></p>';
	$rows = array(
		array( 'المعامل الأساسي', $F( $cfg['base'] ) . ' وحدة حرارية/س لكل م² عند سقف ' . $F( $cfg['refH'] ) . ' م' ),
		array( 'الدور الأخير', zt_pct_label( $cfg['topPct'] ) ),
		array( 'الأشخاص', 'كل شخص فوق ' . $F( $cfg['peopleFree'] ) . ': +' . $F( $cfg['personBtu'] ) . ' وحدة' ),
	);
	foreach ( array( 'نوع الغرفة' => 'rooms', 'التعرض للشمس' => 'sun', 'النوافذ' => 'win', 'العزل' => 'ins' ) as $lab => $k ) {
		$parts = array(); foreach ( $cfg[ $k ] as $it ) { $parts[] = $it['l'] . ' ' . zt_pct_label( $it['p'] ); }
		$rows[] = array( $lab, implode( '، ', $parts ) );
	}
	$sz = array(); foreach ( $cfg['sizes'] as $s ) { $sz[] = $F( $s ) . ' (' . zt_ac_tons( $cfg, $s ) . ' طن)'; }
	$rows[] = array( 'المقاسات المتاحة', implode( '، ', $sz ) );
	$rows[] = array( 'إذا تجاوز الحمل أكبر مقاس', 'نقترح حتى ' . $F( $cfg['maxUnits'] ) . ' مكيفات' );
	$h .= '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">العامل</th><th scope="col">القيمة</th></tr></thead><tbody>';
	foreach ( $rows as $r ) { $h .= '<tr><th scope="row">' . zt_esc( $r[0] ) . '</th><td>' . zt_esc( $r[1] ) . '</td></tr>'; }
	return $h . '</tbody></table></div><p>الناتج تقديري للمساعدة على الاختيار؛ معاينة الفني هي التي تحسم المقاس النهائي.</p>';
}

/** Worked examples + the reference table, all computed here with the same formula. */
function zt_ac_examples( $ctx ) {
	$cfg = zt_ac_cfg();
	$pick = function ( $list, $i ) { return $list ? $list[ min( $i, count( $list ) - 1 ) ]['k'] : ''; };
	$ex = array(
		array( 'غرفة نوم 4 × 4 م', array( 'l' => 4, 'w' => 4, 'rt' => $pick( $cfg['rooms'], 0 ) ) ),
		array( 'صالة 6 × 5 م، شمس مباشرة، 4 أشخاص', array( 'l' => 6, 'w' => 5, 'rt' => $pick( $cfg['rooms'], 1 ), 'sn' => $pick( $cfg['sun'], 2 ), 'p' => 4 ) ),
		array( 'مطبخ 3 × 3 م، دور أخير', array( 'l' => 3, 'w' => 3, 'rt' => $pick( $cfg['rooms'], 3 ), 'top' => true ) ),
	);
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الحالة</th><th scope="col">الحمل التقديري</th><th scope="col">المكيف المقترح</th></tr></thead><tbody>';
	foreach ( $ex as $e ) {
		$r = zt_ac_calc( $cfg, $e[1] );
		if ( isset( $r['error'] ) ) { continue; }
		$h .= '<tr><th scope="row">' . zt_esc( $e[0] ) . '</th><td>' . zt_esc( zt_fmt( $r['total'] ) ) . ' وحدة</td><td>' . zt_esc( $r['fit'] ? zt_ac_size_label( $cfg, $r['fit'] ) : 'يحتاج معاينة' ) . '</td></tr>';
	}
	$h .= '</tbody></table></div>';
	$from = max( 1, (int) zt_opt( 'ac-size.table_from' ) ); $to = max( $from, (int) zt_opt( 'ac-size.table_to' ) );
	$h .= '<h3 class="zt-h3">جدول مرجعي: المساحة والمكيف المناسب</h3><p>غرفة عادية بارتفاع ' . zt_esc( zt_fmt( $cfg['refH'] ) ) . ' م، بدون إضافات، بالقيم المعتمدة أعلاه.</p><div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">المساحة (م²)</th><th scope="col">دور عادي</th><th scope="col">دور أخير</th></tr></thead><tbody>';
	for ( $a = $from; $a <= $to; $a++ ) {
		$n = zt_ac_calc( $cfg, array( 'a' => $a ) ); $t = zt_ac_calc( $cfg, array( 'a' => $a, 'top' => true ) );
		$cell = function ( $r ) use ( $cfg ) { return $r['fit'] ? zt_ac_tons( $cfg, $r['fit']['size'] ) . ' طن' . ( $r['fit']['units'] > 1 ? ' × ' . $r['fit']['units'] : '' ) . ' (' . zt_fmt( $r['fit']['size'] ) . ')' : 'معاينة'; };
		$h .= '<tr><th scope="row">' . $a . '</th><td>' . zt_esc( $cell( $n ) ) . '</td><td>' . zt_esc( $cell( $t ) ) . '</td></tr>';
	}
	return $h . '</tbody></table></div>';
}

function zt_ac_related( $ctx ) {
	$id = (int) zt_opt( 'ac-size.related_page' );
	return $id && 'publish' === get_post_status( $id ) ? '<p>بعد اختيار المكيف: <a href="' . esc_url( get_permalink( $id ) ) . '" data-zt-event="tool_related">احسب استهلاك الكهرباء</a></p>' : '';
}
