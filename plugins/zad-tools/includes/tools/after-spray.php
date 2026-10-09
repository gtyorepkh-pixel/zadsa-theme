<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 4 — متى أرجع بعد الرش؟
 * The hours live only in the settings tab «بعد الرش»: a matrix pest × method (+ optional household factors). An empty cell is never guessed:
 * the answer is «الفني هيحدد المدة بعد المعاينة». Page stays closed until the matrix / instruction lists are approved by a person.
 * Logic twin of assets/js/after-spray.js (tests/tools-parity.test.mjs).
 */

add_action( 'zad_tools_register_settings', function () {
	$src = 'مقترح للمراجعة فقط: نص موجود فعلاً في الموقع (صفحة 22793: «تهوية الغرف المعالجة لمدة 6 ساعات على الأقل قبل استخدامها»). باقي الخلايا فارغة عمداً — أكملها أنت أو الفني';
	zt_register_settings( 'after-spray', 'متى أرجع بعد الرش', array(
		array( 'key' => 'pests', 'label' => 'الآفات (مفتاح | الاسم)', 'type' => 'table', 'default' => "roach | صراصير\nant | نمل\nbedbug | بق الفراش\ntermite | نمل أبيض\ngeneral | رش عام للحشرات الزاحفة", 'desc' => 'عدّلها لتطابق خدماتك. تغيير المفتاح يكسر الروابط المحفوظة.' ),
		array( 'key' => 'methods', 'label' => 'طرق المعالجة (مفتاح | الاسم)', 'type' => 'table', 'default' => "spray | رش سائل\ngel | جل / طُعم" ),
		array( 'key' => 'matrix', 'label' => 'جدول المدة: آفة | طريقة | ساعات الرجوع | ملاحظة اختيارية', 'type' => 'table', 'approval' => true, 'source' => $src,
			'default' => 'general | spray | 6 | ',
			'desc' => 'خلية غير مكتوبة هنا = «الفني هيحدد المدة». 24 = يوم، 48 = يومان. لا تكتب رقماً غير مؤكد.' ),
		array( 'key' => 'factors', 'label' => 'ظروف المنزل (مفتاح | الاسم | أقل ساعات (اختياري) | ما يفعله العميل)', 'type' => 'table', 'approval' => true,
			'source' => 'النصوص من صفحات الخدمات الحالية في الثيم (inc/zad-ix-packs.php)؛ «أقل ساعات» فارغ عمداً حتى يحددها الفني',
			'default' => "kids | يوجد أطفال | | يُبعد الأطفال عن الغرفة المعالجة أثناء الزيارة، وتُجفف الأسطح قبل عودتهم، وتغسل الألعاب والملابس بحرارة عالية.\npets | يوجد حيوانات أليفة | | تُخرج الحيوانات وأوانيها أثناء العمل، ولا تعود قبل التهوية والجفاف الذي يحدده الفني.\nasthma | ربو أو حساسية | | تُجرى التهوية الكافية بعد الزيارة، وتُخبر الفني بحالتك ليتجنب المواد ذات الرائحة القوية." ),
		array( 'key' => 'before', 'label' => 'قبل الرش (سطر لكل خطوة)', 'type' => 'textarea', 'approval' => true, 'source' => 'من مقال «التحضير قبل الرش» الموجود في الثيم (inc/zad-demo.php) — يراجعه الفني',
			'default' => "غطِّ الطعام والأواني أو أخرجها من المطبخ.\nأخرج الحيوانات الأليفة وأحواض السمك أو غطِّها.\nأبعد الأطفال عن المكان أثناء التنفيذ.\nأخبر الفني بأماكن ظهور الحشرات." ),
		array( 'key' => 'during', 'label' => 'أثناء الرش (سطر لكل خطوة)', 'type' => 'textarea', 'default' => '', 'required' => false, 'desc' => 'اختياري.' ),
		array( 'key' => 'after', 'label' => 'بعد الرش (سطر لكل خطوة)', 'type' => 'textarea', 'approval' => true, 'source' => 'من نفس المقال في الثيم — يراجعه الفني',
			'default' => "هوِّ المكان حسب توجيه الفني.\nلا تمسح الأسطح المعالَجة مباشرة؛ انتظر حتى الجفاف.\nامسح الأسطح الملامسة للطعام قبل استخدامها." ),
	) );
} );

zt_register_tool( 'after-spray', array(
	'title' => 'متى أرجع بعد الرش؟', 'desc' => 'اختر الآفة وطريقة المعالجة وظروف بيتك، وتعرف المدة المناسبة للرجوع وما تفعله قبل الرش وبعده.',
	'render' => 'zt_spray_render', 'result' => 'zt_spray_result', 'how' => 'zt_spray_how', 'examples' => 'zt_spray_examples',
	'js' => ZT_URL . 'assets/js/after-spray.js',
) );

function zt_spray_pairs( $setting ) {
	$out = array();
	foreach ( zt_table( zt_opt( 'after-spray.' . $setting ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] ) { $out[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1] ); } }
	return $out;
}
function zt_spray_lines( $setting ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\n|\r/', (string) zt_opt( 'after-spray.' . $setting ) ) ), 'strlen' ) ); }
function zt_spray_hours_val( $s ) { $n = zt_num( $s ); return ( null === $n || $n <= 0 ) ? null : ( $n == (int) $n ? (int) $n : $n ); }

function zt_spray_cfg() {
	$matrix = array(); $notes = array();
	foreach ( zt_table( zt_opt( 'after-spray.matrix' ) ) as $r ) {
		if ( count( $r ) < 3 ) { continue; }
		$key = sanitize_key( $r[0] ) . '|' . sanitize_key( $r[1] );
		$h   = zt_spray_hours_val( $r[2] );
		if ( null !== $h ) { $matrix[ $key ] = $h; }
		if ( ! empty( $r[3] ) ) { $notes[ $key ] = $r[3]; }
	}
	$factors = array();
	foreach ( zt_table( zt_opt( 'after-spray.factors' ) ) as $r ) {
		if ( count( $r ) < 2 || '' === $r[0] ) { continue; }
		$factors[] = array( 'k' => sanitize_key( $r[0] ), 'l' => $r[1], 'min' => isset( $r[2] ) ? zt_spray_hours_val( $r[2] ) : null, 'txt' => isset( $r[3] ) ? implode( ' | ', array_slice( $r, 3 ) ) : '' );
	}
	return array( 'pests' => zt_spray_pairs( 'pests' ), 'methods' => zt_spray_pairs( 'methods' ), 'matrix' => (object) $matrix, 'notes' => (object) $notes, 'factors' => $factors,
		'before' => zt_spray_lines( 'before' ), 'during' => zt_spray_lines( 'during' ), 'after' => zt_spray_lines( 'after' ) );
}

/* ---------------------------------------------- the logic (PHP twin) ---------------------------------------------- */

function zt_spray_find( $list, $k ) { foreach ( $list as $it ) { if ( $it['k'] === $k ) { return $it; } } return null; }
function zt_spray_hours_label( $h ) {
	if ( $h >= 24 && fmod( (float) $h, 24.0 ) == 0.0 ) { return zt_ar_count( $h / 24, 'يوم', 'يومين', 'أيام', 'يوم' ); }
	return zt_ar_count( $h, 'ساعة', 'ساعتين', 'ساعات', 'ساعة' );
}
function zt_spray_clock( $min ) { $h = intdiv( $min, 60 ); $m = $min % 60; $h12 = 0 === $h % 12 ? 12 : $h % 12; return $h12 . ':' . sprintf( '%02d', $m ) . ' ' . ( $h >= 12 ? 'م' : 'ص' ); }
function zt_spray_day_label( $n ) { return 1 === $n ? 'اليوم التالي' : ( 2 === $n ? 'بعد يومين' : 'بعد ' . zt_ar_count( $n, 'يوم', 'يومين', 'أيام', 'يوم' ) ); }
function zt_spray_parse_time( $t ) {
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( zt_digits_en( (string) $t ) ), $m ) ) { return null; }
	$h = (int) $m[1]; $mi = (int) $m[2];
	return ( $h > 23 || $mi > 59 ) ? null : $h * 60 + $mi;
}

function zt_spray_calc( $cfg, $i ) {
	$pest = zt_spray_find( $cfg['pests'], $i['ps'] ?? '' ); $method = zt_spray_find( $cfg['methods'], $i['md'] ?? '' );
	if ( ! $pest || ! $method ) { return array( 'error' => 'اختر نوع الآفة وطريقة المعالجة.' ); }
	$matrix = (array) $cfg['matrix']; $key = $pest['k'] . '|' . $method['k'];
	$has = array_key_exists( $key, $matrix ); $base = $has ? $matrix[ $key ] : null;
	$fs = array(); $best = null;
	foreach ( (array) ( $i['f'] ?? array() ) as $k ) {
		$f = zt_spray_find( $cfg['factors'], $k );
		if ( $f ) { $fs[] = $f; if ( null !== $f['min'] && ( null === $best || $f['min'] > $best['min'] ) ) { $best = $f; } }
	}
	$hours = $has ? $base : null; $raised = null;
	if ( $has && $best && $best['min'] > $hours ) { $hours = $best['min']; $raised = $best; }
	$start = zt_spray_parse_time( $i['t'] ?? '' ); $ret = null;
	if ( null !== $hours && null !== $start ) { $tot = $start + (int) floor( $hours * 60 + 0.5 ); $ret = array( 'at' => $tot % 1440, 'day' => intdiv( $tot, 1440 ) ); }
	$notes = (array) ( $cfg['notes'] ?? array() );
	return array( 'pest' => $pest, 'method' => $method, 'hours' => $hours, 'raisedBy' => $raised, 'factors' => $fs, 'start' => $start, 'ret' => $ret, 'note' => $notes[ $key ] ?? '' );
}

function zt_spray_view( $cfg, $i ) {
	$r = zt_spray_calc( $cfg, $i );
	if ( isset( $r['error'] ) ) { return $r; }
	$big   = null !== $r['hours'] ? 'ارجع بعد ' . zt_spray_hours_label( $r['hours'] ) : 'الفني هيحدد المدة بعد المعاينة';
	$lines = array( 'الآفة: ' . $r['pest']['l'], 'طريقة المعالجة: ' . $r['method']['l'] );
	if ( null !== $r['start'] ) { $lines[] = 'وقت الرش: ' . zt_spray_clock( $r['start'] ); }
	if ( $r['ret'] ) { $lines[] = 'تقدر ترجع الساعة ' . zt_spray_clock( $r['ret']['at'] ) . ( $r['ret']['day'] > 0 ? ' (' . zt_spray_day_label( $r['ret']['day'] ) . ')' : '' ); }
	$rows = array();
	foreach ( array( array( 'قبل الرش', $cfg['before'] ), array( 'أثناء الرش', $cfg['during'] ), array( 'بعد الرش', $cfg['after'] ) ) as $s ) { if ( ! empty( $s[1] ) ) { $rows[] = array( $s[0], implode( ' ', $s[1] ) ); } }
	foreach ( $r['factors'] as $f ) { if ( '' !== $f['txt'] ) { $rows[] = array( 'ملاحظة: ' . $f['l'], $f['txt'] ); } }
	$notes = array();
	if ( $r['raisedBy'] ) { $notes[] = 'رفعنا المدة إلى ' . zt_spray_hours_label( $r['hours'] ) . ' بسبب: ' . $r['raisedBy']['l'] . '.'; }
	if ( '' !== $r['note'] ) { $notes[] = 'ملاحظة: ' . $r['note']; }
	$notes[] = 'هذه إرشادات عامة؛ التزم بتوجيه الفني في زيارتك وبما هو مكتوب على عبوة المبيد.';
	$sum = 'استفسار بعد الرش: ' . $r['pest']['l'] . ' بطريقة ' . $r['method']['l'] . ( null !== $r['hours'] ? '، والأداة تقول أرجع بعد ' . zt_spray_hours_label( $r['hours'] ) . '. أريد تأكيد الفني.' : '. أريد معرفة المدة المناسبة لرجوعنا.' );
	return array( 'badge' => 'إرشادي', 'big' => $big, 'lines' => $lines, 'table' => $rows ? array( 'title' => 'الخطوات', 'head' => array( 'المرحلة', 'ما تفعله' ), 'rows' => $rows ) : null,
		'notes' => $notes, 'wa' => zt_wa_message( $sum, $i['hood'] ?? '' ), 'ga' => array( 'pest' => $r['pest']['k'], 'method' => $r['method']['k'], 'hours' => null === $r['hours'] ? -1 : $r['hours'] ) );
}

/* ---------------------------------------------- request + rendering ---------------------------------------------- */

function zt_spray_request( $cfg ) {
	$f = array();
	foreach ( $cfg['factors'] as $x ) { if ( '1' === zt_req( 'f_' . $x['k'] ) ) { $f[] = $x['k']; } }
	return array( 'ps' => zt_req( 'ps' ), 'md' => zt_req( 'md' ), 'f' => $f, 't' => zt_req( 't' ), 'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ) );
}

function zt_spray_render( $ctx ) {
	$cfg = zt_spray_cfg(); $q = zt_spray_request( $cfg );
	$sel = function ( $name, $label, $list, $cur ) {
		$h = '<label class="fld"><span>' . zt_esc( $label ) . '</span><select name="' . zt_esc( $name ) . '" required><option value="">— اختر —</option>';
		foreach ( $list as $it ) { $h .= '<option value="' . zt_esc( $it['k'] ) . '"' . ( $it['k'] === $cur ? ' selected' : '' ) . '>' . zt_esc( $it['l'] ) . '</option>'; }
		return $h . '</select></label>';
	};
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '">';
	echo '<div class="zt-row">' . $sel( 'ps', 'نوع الآفة', $cfg['pests'], $q['ps'] ) . $sel( 'md', 'طريقة المعالجة', $cfg['methods'], $q['md'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	if ( $cfg['factors'] ) {
		echo '<fieldset><legend>ظروف البيت (اختياري)</legend>';
		foreach ( $cfg['factors'] as $f ) { echo '<label class="zt-consent"><input type="checkbox" name="f_' . zt_esc( $f['k'] ) . '" value="1" data-zt-factor="' . zt_esc( $f['k'] ) . '"' . ( in_array( $f['k'], $q['f'], true ) ? ' checked' : '' ) . '><span>' . zt_esc( $f['l'] ) . '</span></label>'; }
		echo '</fieldset>';
	}
	echo '<div class="zt-row"><label class="fld"><span>وقت الرش (اختياري، لنحسب لك ساعة الرجوع)</span><input type="time" name="t" dir="ltr" value="' . zt_esc( $q['t'] ) . '"></label>';
	echo '<label class="fld"><span>الحي (اختياري، يُضاف لرسالة واتساب)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label></div>';
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">اعرف متى أرجع</button></div></form>';
}

function zt_spray_result( $ctx ) {
	if ( ! zt_has_req( array( 'ps', 'md' ) ) ) { return ''; }
	$cfg = zt_spray_cfg();
	return zt_result_html( zt_spray_view( $cfg, zt_spray_request( $cfg ) ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_spray_how( $ctx ) {
	$cfg = zt_spray_cfg(); $matrix = (array) $cfg['matrix'];
	$h  = '<p>المدة تأتي من جدول يعتمده الفني لكل آفة وطريقة معالجة. لو حدّدت ظروفاً في بيتك (مثل وجود أطفال) وكان لها حد أدنى أعلى من الجدول، نأخذ الأعلى. ولو الخلية غير محددة في الجدول فلن نخمّن رقماً: الفني هو من يحدد المدة بعد المعاينة.</p>';
	$h .= '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الآفة</th>';
	foreach ( $cfg['methods'] as $m ) { $h .= '<th scope="col">' . zt_esc( $m['l'] ) . '</th>'; }
	$h .= '</tr></thead><tbody>';
	foreach ( $cfg['pests'] as $p ) {
		$h .= '<tr><th scope="row">' . zt_esc( $p['l'] ) . '</th>';
		foreach ( $cfg['methods'] as $m ) { $k = $p['k'] . '|' . $m['k']; $h .= '<td>' . zt_esc( array_key_exists( $k, $matrix ) ? zt_spray_hours_label( $matrix[ $k ] ) : 'الفني يحدد' ) . '</td>'; }
		$h .= '</tr>';
	}
	$h .= '</tbody></table></div>';
	$mins = array_filter( $cfg['factors'], function ( $f ) { return null !== $f['min']; } );
	if ( $mins ) {
		$h .= '<p>حدود دنيا حسب ظروف البيت: ';
		$parts = array(); foreach ( $mins as $f ) { $parts[] = $f['l'] . ' — ' . zt_spray_hours_label( $f['min'] ); }
		$h .= zt_esc( implode( '، ', $parts ) ) . '.</p>';
	}
	return $h;
}

function zt_spray_examples( $ctx ) {
	$cfg = zt_spray_cfg(); $matrix = (array) $cfg['matrix'];
	$rows = array();
	foreach ( $cfg['pests'] as $p ) { foreach ( $cfg['methods'] as $m ) { if ( array_key_exists( $p['k'] . '|' . $m['k'], $matrix ) ) { $rows[] = array( $p, $m ); } } }
	if ( ! $rows ) { return ''; }
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">الحالة</th><th scope="col">وقت الرش</th><th scope="col">النتيجة</th></tr></thead><tbody>';
	foreach ( array_slice( $rows, 0, 3 ) as $x ) {
		$v = zt_spray_view( $cfg, array( 'ps' => $x[0]['k'], 'md' => $x[1]['k'], 'f' => array(), 't' => '14:00' ) );
		$h .= '<tr><th scope="row">' . zt_esc( $x[0]['l'] . ' — ' . $x[1]['l'] ) . '</th><td>2:00 م</td><td>' . zt_esc( $v['big'] . ' — ' . $v['lines'][3] ) . '</td></tr>';
	}
	return $h . '</tbody></table></div>';
}
