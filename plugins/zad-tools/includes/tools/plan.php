<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 9 — جدول صيانة البيت السنوي.
 * The rules are settings: task | interval (months, or «@tank_ground» / «@tank_roof» to reuse the tank tool's interval) | preferred month | conditions | service page | cost basis.
 * Output: 12-month calendar, tasks with dates, an estimated yearly cost from the service pages' lowest prices (never a discount), .ics with every date,
 * a «send to myself» WhatsApp link, and one-consent reminders (collects_data). A task without a service page has no link and no price → «بعد المعاينة».
 * Logic twin of assets/js/plan.js (tests/tools-parity.test.mjs).
 */

add_action( 'zad_tools_register_settings', function () {
	$flag = 'قيمة مقترحة من عندي كنقطة بداية — تحتاج اعتماد صاحب الخدمة';
	zt_register_settings( 'plan', 'جدول الصيانة السنوي', array(
		array( 'key' => 'housing', 'label' => 'أنواع السكن (مفتاح | الاسم)', 'type' => 'table', 'default' => "apartment | شقة\nduplex | دوبلكس\nvilla | فيلا\nrest | استراحة" ),
		array( 'key' => 'cities', 'label' => 'المدن (مفتاح | الاسم)', 'type' => 'table', 'default' => "riyadh | الرياض\njeddah | جدة\ndammam | الدمام" ),
		array( 'key' => 'tasks', 'label' => 'قواعد المهام: مفتاح | الاسم | كل كم شهر | الشهر المفضل 1–12 | الشرط | رقم صفحة الخدمة | يُحسب لكل (visit/ac/sofa)', 'type' => 'table', 'approval' => true,
			'source' => 'تنظيف المكيفات كل 6 شهور قبل الصيف (شهر 4) من مثال أمر التنفيذ؛ والخزانات تقرأ مدتها من تبويب «خزان المياه». غيرها (رش وقائي، كنب…) لم أخمّنه — أضفه أنت',
			'default' => "ac | تنظيف المكيفات | 6 | 4 | ac | | ac\ntank_g | تنظيف الخزان الأرضي | @tank_ground | | tank_ground | | visit\ntank_r | تنظيف الخزان العلوي | @tank_roof | | tank_roof | | visit",
			'desc' => 'الشروط (اجمعها بـ +): <code>always ac sofa garden pets pest tank_ground tank_roof tank_any h_villa</code> (h_ + مفتاح السكن). شرط غير معروف لا يطابق أبداً. الشهر فارغ = من أول الخطة. رقم الصفحة فارغ = بلا رابط وبلا سعر (بعد المعاينة).' ),
		array( 'key' => 'plans', 'label' => 'باقات الاشتراك (الاسم | وصف | رقم الصفحة | الشرط)', 'type' => 'table', 'default' => '', 'required' => false, 'desc' => 'فارغ = لا يظهر قسم «باقة مناسبة لبيتك». الاسم والشرط فقط يظهران في النتيجة.' ),
	) );
} );

zt_register_tool( 'plan', array(
	'title' => 'جدول صيانة البيت السنوي', 'desc' => 'جدول مواعيد صيانة بيتك على 12 شهرًا حسب مكيفاتك وخزاناتك، مع تذكيرات وملف تقويم.',
	'collects_data' => true,
	'render' => 'zt_plan_render', 'result' => 'zt_plan_result', 'how' => 'zt_plan_how', 'examples' => 'zt_plan_examples',
	'js' => ZT_URL . 'assets/js/plan.js',
) );

function zt_plan_pairs( $setting ) {
	$out = array();
	foreach ( zt_table( zt_opt( 'plan.' . $setting ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] ) { $out[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1] ); } }
	return $out;
}
function zt_plan_when( $s ) { return array_values( array_filter( array_map( function ( $t ) { return sanitize_key( trim( $t ) ); }, explode( '+', (string) $s ) ), 'strlen' ) ); }

/** page id → array( url, lowest price|null ). */
function zt_plan_page( $id ) {
	$id = (int) $id;
	if ( ! $id || 'publish' !== get_post_status( $id ) ) { return array( '', null ); }
	$p = ( function_exists( 'zad_min_price' ) && function_exists( 'zad_price_rows' ) ) ? zad_min_price( zad_price_rows( $id ) ) : null;
	return array( get_permalink( $id ), $p ? zt_pow_intval( $p ) : null );
}

function zt_plan_cfg() {
	$tank = function_exists( 'zt_tank_cfg' ) ? zt_tank_cfg() : array( 'locs' => array() );
	$months = array(); foreach ( $tank['locs'] as $l ) { $months[ $l['k'] ] = $l['m']; }
	$tasks = array();
	foreach ( zt_table( zt_opt( 'plan.tasks' ) ) as $r ) {
		if ( count( $r ) < 5 || '' === $r[0] ) { continue; }
		$ev = (string) $r[2];
		if ( 0 === strpos( $ev, '@tank_' ) ) { $every = $months[ substr( $ev, 6 ) ] ?? null; } else { $e = zt_num( $ev ); $every = ( null !== $e && $e >= 1 ) ? (int) $e : null; }
		if ( ! $every ) { continue; }
		$mo = zt_num( $r[3] ?? '' ); $per = in_array( $r[6] ?? '', array( 'ac', 'sofa' ), true ) ? $r[6] : 'visit';
		$pg = zt_plan_page( $r[5] ?? 0 );
		$tasks[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1], 'every' => $every, 'month' => ( null !== $mo && $mo >= 1 && $mo <= 12 ) ? (int) $mo : null, 'when' => zt_plan_when( $r[4] ), 'per' => $per, 'url' => $pg[0], 'price' => $pg[1] );
	}
	$plans = array();
	foreach ( zt_table( zt_opt( 'plan.plans' ) ) as $r ) { if ( count( $r ) >= 4 && '' !== $r[0] ) { $plans[] = array( 'l' => $r[0], 'when' => zt_plan_when( $r[3] ) ); } }
	return array( 'housing' => zt_plan_pairs( 'housing' ), 'cities' => zt_plan_pairs( 'cities' ), 'tasks' => $tasks, 'plans' => $plans, 'ics' => home_url( '/' ) );
}

/* ---------------------------------------------- the logic (PHP twin) ---------------------------------------------- */

function zt_plan_find( $list, $k ) { foreach ( $list as $it ) { if ( $it['k'] === $k ) { return $it; } } return $list ? $list[0] : null; }
function zt_plan_nz( $n ) { return $n > 0 ? (int) floor( $n ) : 0; }
function zt_plan_applies( $when, $c ) {
	foreach ( $when as $t ) {
		if ( 'always' === $t ) { $ok = true; }
		elseif ( 'tank_ground' === $t ) { $ok = 'ground' === $c['tank'] || 'both' === $c['tank']; }
		elseif ( 'tank_roof' === $t ) { $ok = 'roof' === $c['tank'] || 'both' === $c['tank']; }
		elseif ( 'tank_any' === $t ) { $ok = 'none' !== $c['tank'] && '' !== $c['tank']; }
		elseif ( 'ac' === $t ) { $ok = $c['ac'] > 0; } elseif ( 'sofa' === $t ) { $ok = $c['sofa'] > 0; }
		elseif ( 'garden' === $t ) { $ok = $c['garden']; } elseif ( 'pets' === $t ) { $ok = $c['pets']; } elseif ( 'pest' === $t ) { $ok = $c['pest']; }
		elseif ( 0 === strpos( $t, 'h_' ) ) { $ok = $c['hs'] === substr( $t, 2 ); }
		else { $ok = false; }
		if ( ! $ok ) { return false; }
	}
	return true;
}
function zt_plan_mult( $per, $c ) { return 'ac' === $per ? $c['ac'] : ( 'sofa' === $per ? $c['sofa'] : 1 ); }

function zt_plan_calc( $cfg, $i, $today ) {
	$hs = zt_plan_find( $cfg['housing'], $i['hs'] ?? '' ); $ct = zt_plan_find( $cfg['cities'], $i['ct'] ?? '' );
	$tk = in_array( $i['tk'] ?? '', array( 'ground', 'roof', 'both' ), true ) ? $i['tk'] : 'none';
	$c  = array( 'tank' => $tk, 'ac' => zt_plan_nz( $i['acn'] ?? 0 ), 'sofa' => zt_plan_nz( $i['sf'] ?? 0 ), 'garden' => ! empty( $i['gd'] ), 'pets' => ! empty( $i['pt'] ), 'pest' => ! empty( $i['pe'] ), 'hs' => $hs ? $hs['k'] : '' );
	$sm = (string) ( $i['sm'] ?? '' );
	$start = ( preg_match( '/^\d{4}-\d{2}$/', $sm ) && '' !== zt_add_months_str( $sm . '-01', 0 ) ) ? $sm : substr( $today, 0, 7 );
	$startDay = $start . '-01'; $end = zt_add_months_str( $startDay, 12 ); $occ = array(); $tasks = array();
	$lastmap = (array) ( $i['last'] ?? array() );
	foreach ( $cfg['tasks'] as $t ) {
		if ( ! zt_plan_applies( $t['when'], $c ) ) { continue; }
		$ls = isset( $lastmap[ $t['k'] ] ) ? (string) $lastmap[ $t['k'] ] : '';
		$last = ( '' !== $ls && '' !== zt_add_months_str( $ls, 0 ) && $ls <= $today ) ? $ls : '';
		$overdue = false;
		if ( $last ) { $base = zt_add_months_str( $last, $t['every'] ); $overdue = $base < $today; }
		elseif ( $t['month'] ) { $base = substr( $startDay, 0, 4 ) . '-' . sprintf( '%02d', $t['month'] ) . '-01'; if ( $base < $startDay ) { $base = zt_add_months_str( $base, 12 ); } }
		else { $base = $startDay; }
		$dates = array();
		for ( $k = 0; $k < 400; $k++ ) {
			$raw = zt_add_months_str( $base, $k * $t['every'] );
			if ( $raw >= $end ) { break; }
			if ( $raw < $today && $k > 0 ) { continue; }
			$d = $raw < $today ? $today : $raw;
			if ( $d >= $end ) { break; }
			$dates[] = $d; $occ[] = array( 'k' => $t['k'], 'l' => $t['l'], 'date' => $d, 'url' => $t['url'], 'overdue' => 0 === $k && $overdue );
		}
		$q = zt_plan_mult( $t['per'], $c ); $cost = ( null !== $t['price'] && $dates ) ? $t['price'] * $q * count( $dates ) : null;
		$tasks[] = array( 'k' => $t['k'], 'l' => $t['l'], 'url' => $t['url'], 'dates' => $dates, 'qty' => $q, 'price' => $t['price'], 'cost' => $cost );
	}
	usort( $occ, function ( $a, $b ) { return $a['date'] <=> $b['date'] ?: strcmp( $a['k'], $b['k'] ); } );
	$months = array();
	for ( $m = 0; $m < 12; $m++ ) {
		$ms = zt_add_months_str( $startDay, $m ); $ym = substr( $ms, 0, 7 );
		$months[] = array( 'ym' => $ym, 'y' => (int) substr( $ms, 0, 4 ), 'm' => (int) substr( $ms, 5, 2 ), 'items' => array_values( array_filter( $occ, function ( $o ) use ( $ym ) { return substr( $o['date'], 0, 7 ) === $ym; } ) ) );
	}
	$cost = 0; $priced = false; $unpriced = array();
	foreach ( $tasks as $t ) { if ( $t['dates'] ) { if ( null !== $t['cost'] ) { $cost += $t['cost']; $priced = true; } else { $unpriced[] = $t['l']; } } }
	return array( 'hs' => $hs, 'ct' => $ct, 'tk' => $tk, 'c' => $c, 'start' => $start, 'occ' => $occ, 'tasks' => $tasks, 'months' => $months, 'cost' => $priced ? $cost : null, 'unpriced' => $unpriced );
}

function zt_plan_view( $cfg, $i, $today ) {
	$r = zt_plan_calc( $cfg, $i, $today ); $F = 'zt_fmt';
	if ( ! $r['occ'] ) { return array( 'error' => 'لا توجد مهام لبيتك حاليًا بحسب ما اخترته. جرّب إضافة مكيفات أو خزانًا، أو تواصل معنا لنرتب لك جدولًا.' ); }
	$big   = zt_ar_count( count( $r['occ'] ), 'مهمة واحدة', 'مهمتان', 'مهام', 'مهمة' ) . ' خلال 12 شهرًا';
	$lines = array( ( $r['hs'] ? $r['hs']['l'] : '' ) . ( $r['ct'] ? ' — ' . $r['ct']['l'] : '' ), 'تبدأ الخطة من ' . zt_ar_month( (int) substr( $r['start'], 0, 4 ), (int) substr( $r['start'], 5, 2 ) ) );
	if ( null !== $r['cost'] ) { $lines[] = 'التكلفة السنوية التقديرية: تبدأ من ' . $F( $r['cost'] ) . ' ريال' . ( $r['unpriced'] ? ' (بدون: ' . implode( '، ', $r['unpriced'] ) . ' — بعد المعاينة)' : '' ) . ' — بدون أي خصم'; }
	else { $lines[] = 'تكلفة الخطة تتحدد بعد المعاينة.'; }
	$plans = array(); foreach ( (array) ( $cfg['plans'] ?? array() ) as $p ) { if ( zt_plan_applies( $p['when'], $r['c'] ) ) { $plans[] = $p['l']; } }
	if ( $plans ) { $lines[] = 'باقة مناسبة لبيتك: ' . implode( '، ', $plans ); }
	$first = $r['occ'][0]; $rows = array(); $sumParts = array();
	foreach ( $r['tasks'] as $t ) {
		if ( ! $t['dates'] ) { continue; }
		$rows[] = array( $t['l'], implode( '، ', array_map( 'zt_ar_date', $t['dates'] ) ) );
		$sumParts[] = $t['l'] . ' (' . zt_ar_date( $t['dates'][0] ) . ')';
	}
	$cal = array();
	foreach ( $r['months'] as $mo ) { $items = array(); foreach ( $mo['items'] as $o ) { $items[] = array( $o['l'] . ( $o['overdue'] ? ' (متأخرة)' : '' ), $o['url'] ); } $cal[] = array( 'm' => zt_ar_month( $mo['y'], $mo['m'] ), 'items' => $items ); }
	$events = array(); foreach ( $r['occ'] as $o ) { $events[] = array( $o['date'], $o['l'] ); }
	$sum = 'جدول صيانة بيتي (' . ( $r['hs'] ? $r['hs']['l'] : '' ) . ( $r['ct'] ? ' في ' . $r['ct']['l'] : '' ) . '): ' . implode( '، ', $sumParts ) . '. أحتاج ترتيب المواعيد.';
	$wa = zt_wa_message( $sum, $i['hood'] ?? '' ); $future = array();
	foreach ( array_slice( $r['occ'], 0, 20 ) as $o ) { $future[] = array( 'date' => $o['date'], 'service' => $o['l'] ); }
	return array( 'badge' => 'تقديري', 'big' => $big, 'lines' => $lines, 'table' => array( 'title' => 'مهامك ومواعيدها', 'head' => array( 'المهمة', 'المواعيد' ), 'rows' => $rows ), 'cal' => $cal,
		'notes' => array( 'الخطة تقديرية مبنية على القواعد المعلنة في «إزاي بنحسب»؛ تعديل مواعيدك متاح عند الحجز.' ), 'wa' => $wa,
		'links' => array( array( 'href' => zt_ics_url_multi( $cfg['ics'], array_slice( $events, 0, 60 ) ), 'label' => 'حمّل المواعيد (.ics)', 'event' => 'tool_ics' ), array( 'href' => zt_wa_self_url( $wa ), 'label' => 'أرسل الجدول لنفسي على واتساب', 'event' => 'tool_share' ) ),
		'optin' => array( 'items' => $future, 'title' => 'فعّل تذكيرات المواعيد' ), 'ga' => array( 'tasks' => count( $r['occ'] ), 'first' => $first['k'] ) );
}

/* ---------------------------------------------- request + rendering ---------------------------------------------- */

function zt_plan_request( $cfg ) {
	$last = array();
	foreach ( $cfg['tasks'] as $t ) { $v = zt_req( 'l_' . $t['k'] ); if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) { $last[ $t['k'] ] = $v; } }
	$sm = zt_req( 'sm' );
	return array( 'hs' => zt_req( 'hs' ), 'ct' => zt_req( 'ct' ), 'tk' => zt_req( 'tk' ), 'acn' => zt_req_num( 'acn' ), 'gd' => '1' === zt_req( 'gd' ), 'pt' => '1' === zt_req( 'pt' ), 'pe' => '1' === zt_req( 'pe' ),
		'sf' => zt_req_num( 'sf' ), 'sm' => preg_match( '/^\d{4}-\d{2}$/', $sm ) ? $sm : '', 'last' => $last, 'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ) );
}

function zt_plan_render( $ctx ) {
	$cfg = zt_plan_cfg(); $q = zt_plan_request( $cfg );
	$sel = function ( $name, $label, $list, $cur ) {
		$h = '<label class="fld"><span>' . zt_esc( $label ) . '</span><select name="' . $name . '">';
		foreach ( $list as $it ) { $h .= '<option value="' . zt_esc( $it['k'] ) . '"' . ( $it['k'] === $cur ? ' selected' : '' ) . '>' . zt_esc( $it['l'] ) . '</option>'; }
		return $h . '</select></label>';
	};
	$num = function ( $name, $label, $val, $ph = '0' ) { return '<label class="fld"><span>' . zt_esc( $label ) . '</span><input type="text" inputmode="numeric" dir="ltr" data-zt-num name="' . $name . '" value="' . zt_esc( null === $val ? '' : zt_fmt( $val ) ) . '" placeholder="' . zt_esc( $ph ) . '" autocomplete="off"></label>'; };
	$chk = function ( $name, $label, $on ) { return '<label class="zt-consent"><input type="checkbox" name="' . $name . '" value="1"' . ( $on ? ' checked' : '' ) . '><span>' . zt_esc( $label ) . '</span></label>'; };
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '">';
	echo '<div class="zt-row">' . $sel( 'hs', 'نوع السكن', $cfg['housing'], $q['hs'] ) . $sel( 'ct', 'المدينة', $cfg['cities'], $q['ct'] ) . $sel( 'tk', 'الخزانات', array( array( 'k' => 'none', 'l' => 'لا يوجد' ), array( 'k' => 'ground', 'l' => 'أرضي' ), array( 'k' => 'roof', 'l' => 'علوي' ), array( 'k' => 'both', 'l' => 'أرضي وعلوي' ) ), $q['tk'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="zt-row">' . $num( 'acn', 'عدد المكيفات', $q['acn'] ) . $num( 'sf', 'عدد الكنب والمجالس (تقريباً)', $q['sf'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="zt-row">' . $chk( 'gd', 'عندي حديقة أو زرع', $q['gd'] ) . $chk( 'pt', 'عندي حيوانات أليفة', $q['pt'] ) . $chk( 'pe', 'عانيت من مشكلة حشرات سابقة', $q['pe'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $cfg['tasks'] ) {
		echo '<details><summary>تاريخ آخر خدمة لكل بند (اختياري)</summary><div class="zt-row">';
		foreach ( $cfg['tasks'] as $t ) { echo '<label class="fld"><span>' . zt_esc( $t['l'] ) . '</span><input type="date" dir="ltr" name="l_' . zt_esc( $t['k'] ) . '" data-last="' . zt_esc( $t['k'] ) . '" value="' . zt_esc( $q['last'][ $t['k'] ] ?? '' ) . '"></label>'; }
		echo '</div></details>';
	}
	echo '<label class="fld"><span>الحي (اختياري)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label>';
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">جهّز جدول بيتي</button></div></form>';
}

function zt_plan_result( $ctx ) {
	if ( ! zt_has_req( array( 'hs', 'ct', 'tk', 'acn', 'sf' ) ) ) { return ''; }
	$cfg = zt_plan_cfg();
	return zt_result_html( zt_plan_view( $cfg, zt_plan_request( $cfg ), wp_date( 'Y-m-d' ) ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_plan_how( $ctx ) {
	$cfg = zt_plan_cfg();
	$h  = '<p>لكل مهمة قاعدة: كل كم شهر تتكرر، وهل لها شهر مفضّل (مثل تنظيف المكيفات قبل الصيف)، وفي أي حالات تظهر لبيتك. نبدأ من الشهر الحالي ونوزّع المواعيد على 12 شهرًا، وإن أدخلت تاريخ آخر خدمة فالموعد القادم = آخر خدمة + المدة.</p>';
	$h .= '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">المهمة</th><th scope="col">تتكرر كل</th><th scope="col">الشهر المفضل</th><th scope="col">تظهر إذا</th><th scope="col">أقل سعر معلن</th></tr></thead><tbody>';
	$cond = array( 'always' => 'دائمًا', 'ac' => 'عندك مكيفات', 'sofa' => 'عندك كنب', 'garden' => 'عندك حديقة', 'pets' => 'عندك حيوانات', 'pest' => 'عانيت من حشرات', 'tank_ground' => 'عندك خزان أرضي', 'tank_roof' => 'عندك خزان علوي', 'tank_any' => 'عندك خزان' );
	foreach ( $cfg['tasks'] as $t ) {
		$w = array(); foreach ( $t['when'] as $x ) { $w[] = $cond[ $x ] ?? ( 0 === strpos( $x, 'h_' ) ? 'نوع السكن ' . ( zt_plan_find( $cfg['housing'], substr( $x, 2 ) )['l'] ?? '' ) : $x ); }
		$h .= '<tr><th scope="row">' . ( $t['url'] ? '<a href="' . esc_url( $t['url'] ) . '">' . zt_esc( $t['l'] ) . '</a>' : zt_esc( $t['l'] ) ) . '</th><td>' . zt_esc( zt_ar_count( $t['every'], 'شهر', 'شهرين', 'شهور', 'شهر' ) ) . '</td><td>' . ( $t['month'] ? zt_esc( zt_ar_month( 2000, $t['month'] ) ) : '—' ) . '</td><td>' . zt_esc( implode( ' + ', $w ) ) . '</td><td>' . ( null !== $t['price'] ? zt_esc( zt_fmt( $t['price'] ) ) . ' ريال' : 'بعد المعاينة' ) . '</td></tr>';
	}
	return $h . '</tbody></table></div><p>التكلفة السنوية = أقل سعر معلن لكل خدمة × العدد × مرات التكرار؛ بلا أي خصم، وتقديرية.</p>';
}

function zt_plan_examples( $ctx ) {
	$cfg = zt_plan_cfg(); if ( ! $cfg['tasks'] || ! $cfg['housing'] || ! $cfg['cities'] ) { return ''; }
	$base = array( 'hs' => $cfg['housing'][0]['k'], 'ct' => $cfg['cities'][0]['k'], 'last' => array() ); $today = wp_date( 'Y-m-d' );
	$ex = array( array( 'شقة بها 3 مكيفات وخزان أرضي', $base + array( 'tk' => 'ground', 'acn' => 3 ) ), array( 'بيت بخزانين و5 مكيفات', $base + array( 'tk' => 'both', 'acn' => 5 ) ) );
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الحالة</th><th scope="col">الخطة</th></tr></thead><tbody>';
	foreach ( $ex as $e ) { $v = zt_plan_view( $cfg, $e[1], $today ); if ( isset( $v['error'] ) ) { continue; } $h .= '<tr><th scope="row">' . zt_esc( $e[0] ) . '</th><td>' . zt_esc( $v['big'] . ' — ' . $v['lines'][2] ) . '</td></tr>'; }
	return $h . '</tbody></table></div>';
}
