<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 5 — تقرير زاد الموسمي للحشرات.
 * Data: completed orders (zad_order) + an optional CSV import of OLD orders (date, service, hood, city — nothing personal: the importer refuses any other column).
 * Rules: only aggregates are shown; a district under N orders is never shown by name (N = setting, 5); a report cannot be published unless the month has at least
 * M orders (setting, EMPTY until the owner decides) AND the editor wrote the mandatory «ملاحظات المحرر والنصائح» — publishing is never automatic.
 * One CPT zad_report = one month at /pest-report/YYYY-MM/ (the numbers are frozen into the post when the draft is generated). Charts are server-generated SVG.
 * Pure parts (aggregation, SVG, CSV, CSV import parsing, summary, publish check) are tested without WordPress (tests/report.php).
 */

/* ---------------------------------------------- settings ---------------------------------------------- */

add_action( 'zad_tools_register_settings', function () {
	zt_register_settings( 'report', 'تقرير الحشرات الموسمي', array(
		array( 'key' => 'min_hood', 'label' => 'أقل عدد طلبات ليظهر الحي باسمه في التقرير', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 1000, 'source' => 'من أمر التنفيذ (الأداة 5): 5 طلبات' ),
		array( 'key' => 'min_month', 'label' => 'أقل عدد طلبات في الشهر لإمكان نشر التقرير', 'type' => 'number', 'default' => '', 'min' => 1, 'max' => 100000, 'must_fill' => true,
			'desc' => '<strong>فارغ عمداً</strong>: قرارك التجاري (الأمر لم يحدد رقماً). فارغ = لا يمكن توليد ولا نشر أي تقرير.' ),
		array( 'key' => 'service_map', 'label' => 'ربط اسم الخدمة في الطلب بالحشرة (كلمة | اسم الحشرة)', 'type' => 'table', 'required' => false,
			'default' => "نمل أبيض | النمل الأبيض\nبق الفراش | بق الفراش\nصراصير | الصراصير\nصرصور | الصراصير\nنمل | النمل\nفئران | الفئران\nقوارض | الفئران\nبعوض | البعوض\nذباب | الذباب\nعقارب | العقارب",
			'desc' => 'أول كلمة موجودة في اسم الخدمة تحسم الحشرة (ضع «نمل أبيض» قبل «نمل»). خدمة بلا تطابق لا تدخل التقرير. اسم الحشرة المطابق لعنوان صفحتها في الموسوعة يُربط تلقائياً.' ),
		array( 'key' => 'compare_cities', 'label' => 'المدن المقارنة (مفصولة بـ |)', 'type' => 'text', 'default' => 'الرياض | جدة', 'required' => false, 'source' => 'من أمر التنفيذ: مقارنة الرياض وجدة' ),
		array( 'key' => 'summary_tpl', 'label' => 'قالب جملة الملخص', 'type' => 'textarea', 'default' => 'سجّلت زاد في {الشهر} {العدد} طلبًا لمكافحة الحشرات، وكانت {أكثر_حشرة} الأكثر طلبًا بـ {عدد_أكثر_حشرة}.', 'required' => false, 'desc' => 'المتغيرات: {الشهر} {العدد} {أكثر_حشرة} {عدد_أكثر_حشرة}. الأرقام تُملأ من البيانات الفعلية.' ),
		array( 'key' => 'method_tpl', 'label' => 'نص «المنهجية»', 'type' => 'textarea', 'required' => false,
			'default' => "المصدر: طلبات زاد المكتملة فقط{مستورد}، مجمّعة بلا أي بيانات شخصية. ليست إحصائية رسمية ولا تمثل السوق كله، بل ما طُلب من زاد.\nلا يظهر أي حي باسمه إلا إذا بلغ {حد_الحي} طلبات في الشهر.\nلا يُنشر التقرير إلا بعد مراجعة بشرية وكتابة ملاحظات المحرر.", 'desc' => '{مستورد} يُستبدل بعبارة السجل المستورد إن وُجد؛ {حد_الحي} بالإعداد.' ),
		array( 'key' => 'license_url', 'label' => 'رابط ترخيص البيانات (للـ Dataset)', 'type' => 'text', 'default' => '', 'required' => false, 'desc' => 'مثلاً رابط رخصة Creative Commons التي تختارها. فارغ = لا يُذكر ترخيص في السكيما.' ),
		array( 'key' => 'index_page', 'label' => 'صفحة «تقارير الحشرات» (للمسار)', 'type' => 'page', 'default' => 0, 'required' => false, 'desc' => 'صفحة عادية رابطها /pest-report/ وفيها <code>[zad_pest_report]</code>.' ),
	) );
} );

/* ---------------------------------------------- table + CPT ---------------------------------------------- */

function zt_hist_table() { global $wpdb; return $wpdb->prefix . 'zad_hist'; }
function zt_report_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = zt_hist_table(); $cs = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE $t (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch char(12) NOT NULL DEFAULT '',
		d date NOT NULL,
		svc varchar(160) NOT NULL DEFAULT '',
		hood varchar(120) NOT NULL DEFAULT '',
		city varchar(80) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY d (d),
		KEY batch (batch)
	) $cs;" );
}

add_action( 'init', function () {
	register_post_type( 'zad_report', array(
		'labels' => array( 'name' => 'تقارير الحشرات', 'singular_name' => 'تقرير', 'add_new' => 'إضافة تقرير', 'all_items' => 'كل التقارير', 'edit_item' => 'تعديل التقرير', 'menu_name' => 'تقارير الحشرات' ),
		'public' => true, 'show_ui' => true, 'show_in_rest' => false, 'has_archive' => false, 'menu_icon' => 'dashicons-chart-bar', 'menu_position' => 28,
		'rewrite' => array( 'slug' => 'pest-report', 'with_front' => false ), 'supports' => array( 'title' ), 'capabilities' => array( 'create_posts' => 'do_not_allow' ), 'map_meta_cap' => true,
	) );
}, 6 );
add_action( 'init', function () {
	if ( get_option( 'zt_report_rules' ) !== 'v1' ) { flush_rewrite_rules( false ); update_option( 'zt_report_rules', 'v1', false ); }
}, 99 );

/* ---------------------------------------------- pure: aggregation ---------------------------------------------- */

/** Arabic text for matching: no tashkeel, hamza forms → ا, the leading «ال» of each word dropped, spaces collapsed («النمل الأبيض» ≈ «نمل ابيض»). */
function zt_rep_norm( $s ) {
	$s = preg_replace( '/[\x{064B}-\x{065F}\x{0640}]/u', '', (string) $s );
	$s = str_replace( array( 'أ', 'إ', 'آ', 'ة', 'ى' ), array( 'ا', 'ا', 'ا', 'ه', 'ي' ), $s );
	$w = preg_split( '/\s+/u', trim( $s ), -1, PREG_SPLIT_NO_EMPTY ); $o = array();
	foreach ( $w as $x ) { $o[] = ( 0 === mb_strpos( $x, 'ال' ) && mb_strlen( $x ) > 3 ) ? mb_substr( $x, 2 ) : $x; }
	return mb_strtolower( implode( ' ', $o ) );
}
/** Pest label of an order's service text, or null (the first keyword found wins). */
function zt_rep_label( $svc, $map ) {
	$t = zt_rep_norm( $svc );
	foreach ( $map as $m ) { $k = zt_rep_norm( $m[0] ); if ( '' !== $k && false !== mb_strpos( $t, $k ) ) { return $m[1]; } }
	return null;
}
function zt_rep_map() {
	$out = array(); foreach ( zt_table( zt_opt( 'report.service_map' ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] && '' !== $r[1] ) { $out[] = array( $r[0], $r[1] ); } }
	return $out;
}
function zt_rep_cities() { return array_values( array_filter( array_map( 'trim', explode( '|', (string) zt_opt( 'report.compare_cities' ) ) ), 'strlen' ) ); }

function zt_rep_sort_pairs( $a ) { // count desc, then label ascending (byte order → deterministic)
	$o = array(); foreach ( $a as $k => $n ) { $o[] = array( (string) $k, (int) $n ); }
	usort( $o, function ( $x, $y ) { return ( $y[1] <=> $x[1] ) ?: strcmp( $x[0], $y[0] ); } );
	return $o;
}

/**
 * $rows = array( array( 'd' => 'YYYY-MM-DD', 'svc' => text, 'hood' => text, 'city' => text ), … ); $ym = 'YYYY-MM'.
 * Only the month's own rows feed pests / hoods / cities; the last 12 months (ending at $ym) feed the trend. Districts under $min_hood are only counted, never named.
 */
function zt_rep_aggregate( $rows, $ym, $map, $min_hood, $cities ) {
	$trend = array(); $start = zt_add_months_str( $ym . '-01', -11 );
	for ( $k = 0; $k < 12; $k++ ) { $trend[ substr( zt_add_months_str( $start, $k ), 0, 7 ) ] = 0; }
	$pests = array(); $hoods = array(); $cp = array(); $total = 0;
	foreach ( $cities as $c ) { $cp[ $c ] = array(); }
	foreach ( $rows as $r ) {
		$label = zt_rep_label( $r['svc'] ?? '', $map ); if ( null === $label ) { continue; }
		$m = substr( (string) ( $r['d'] ?? '' ), 0, 7 ); if ( ! isset( $trend[ $m ] ) ) { continue; }
		$trend[ $m ]++;
		if ( $m !== $ym ) { continue; }
		$total++; $pests[ $label ] = ( $pests[ $label ] ?? 0 ) + 1;
		$h = trim( (string) ( $r['hood'] ?? '' ) ); if ( '' !== $h ) { $hoods[ $h ] = ( $hoods[ $h ] ?? 0 ) + 1; }
		$c = trim( (string) ( $r['city'] ?? '' ) ); if ( isset( $cp[ $c ] ) ) { $cp[ $c ][ $label ] = ( $cp[ $c ][ $label ] ?? 0 ) + 1; }
	}
	$named = array(); $hidden = 0;
	foreach ( $hoods as $h => $n ) { if ( $n >= $min_hood ) { $named[ $h ] = $n; } else { $hidden++; } }
	$tr = array(); foreach ( $trend as $k => $n ) { $tr[] = array( $k, $n ); }
	$cities_out = array();
	foreach ( $cp as $c => $pp ) { $cities_out[] = array( 'city' => $c, 'total' => array_sum( $pp ), 'pests' => zt_rep_sort_pairs( $pp ) ); }
	return array( 'ym' => $ym, 'total' => $total, 'pests' => zt_rep_sort_pairs( $pests ), 'trend' => $tr, 'hoods' => zt_rep_sort_pairs( $named ), 'hoods_hidden' => $hidden, 'cities' => $cities_out, 'min_hood' => (int) $min_hood );
}

/** Summary sentence from the template; '' when there is no data. */
function zt_rep_summary( $tpl, $d ) {
	if ( empty( $d['pests'] ) ) { return ''; }
	$p = zt_ar_month( (int) substr( $d['ym'], 0, 4 ), (int) substr( $d['ym'], 5, 2 ) );
	return strtr( $tpl, array( '{الشهر}' => $p, '{العدد}' => zt_fmt( $d['total'] ), '{أكثر_حشرة}' => $d['pests'][0][0], '{عدد_أكثر_حشرة}' => zt_fmt( $d['pests'][0][1] ) ) );
}

/** Reasons a report cannot be published ( empty array = it can ). */
function zt_rep_publish_check( $d, $notes, $min_month ) {
	$p = array();
	if ( ! is_array( $d ) || empty( $d['ym'] ) ) { return array( 'لا توجد بيانات: ولّد المسودة من «تقارير الحشرات ← توليد» أولاً.' ); }
	if ( '' === trim( (string) $notes ) ) { $p[] = 'اكتب «ملاحظات المحرر والنصائح» — إجباري قبل النشر.'; }
	if ( ! $min_month || (int) $min_month < 1 ) { $p[] = 'لم يُحدد «أقل عدد طلبات في الشهر» في إعدادات التقرير.'; }
	elseif ( (int) $d['total'] < (int) $min_month ) { $p[] = 'عدد طلبات الشهر (' . (int) $d['total'] . ') أقل من الحد الأدنى (' . (int) $min_month . ').'; }
	return $p;
}

/* ---------------------------------------------- pure: charts (SVG) + CSV ---------------------------------------------- */

function zt_svg_esc( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' ); }

/** Horizontal bars, RTL: labels on the right, bars grow to the left, the value sits at the bar's end. $items = array( array( label, n ) ). */
function zt_rep_svg_bars( $items, $title, $desc ) {
	$W = 640; $rowH = 36; $top = 48; $labW = 170; $H = $top + max( 1, count( $items ) ) * $rowH + 16; $max = 1; foreach ( $items as $it ) { $max = max( $max, $it[1] ); }
	$x0 = $W - 14 - $labW; $maxLen = $x0 - 60;
	$s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="' . $W . '" height="' . $H . '" role="img" direction="ltr" font-family="system-ui,Segoe UI,Tahoma,sans-serif">';
	$s .= '<title>' . zt_svg_esc( $title ) . '</title><desc>' . zt_svg_esc( $desc ) . '</desc><rect width="' . $W . '" height="' . $H . '" fill="#ffffff"/>';
	$s .= '<text x="' . ( $W - 14 ) . '" y="30" text-anchor="end" font-size="17" font-weight="700" fill="#14262e">' . zt_svg_esc( $title ) . '</text>';
	foreach ( $items as $i => $it ) {
		$y = $top + $i * $rowH; $len = (int) round( $it[1] / $max * $maxLen ); $len = $it[1] > 0 ? max( 3, $len ) : 0;
		$s .= '<text x="' . ( $W - 14 ) . '" y="' . ( $y + 22 ) . '" text-anchor="end" font-size="14" fill="#14262e">' . zt_svg_esc( $it[0] ) . '</text>';
		$s .= '<rect x="' . ( $x0 - $len ) . '" y="' . ( $y + 6 ) . '" width="' . $len . '" height="22" rx="4" fill="#1f6f8b"/>';
		$s .= '<text x="' . ( $x0 - $len - 8 ) . '" y="' . ( $y + 22 ) . '" text-anchor="end" font-size="14" font-weight="700" fill="#14262e">' . zt_svg_esc( zt_fmt( $it[1] ) ) . '</text>';
	}
	return $s . '</svg>';
}

/** 12-month line. $pts = array( array( 'YYYY-MM', n ) ) in order; x runs right → left in time (RTL reading). */
function zt_rep_svg_line( $pts, $title, $desc ) {
	$W = 640; $H = 280; $L = 40; $R = 24; $T = 56; $B = 44; $max = 1; foreach ( $pts as $p ) { $max = max( $max, $p[1] ); }
	$n = count( $pts ); $iw = $W - $L - $R; $ih = $H - $T - $B;
	$s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="' . $W . '" height="' . $H . '" role="img" direction="ltr" font-family="system-ui,Segoe UI,Tahoma,sans-serif">';
	$s .= '<title>' . zt_svg_esc( $title ) . '</title><desc>' . zt_svg_esc( $desc ) . '</desc><rect width="' . $W . '" height="' . $H . '" fill="#ffffff"/>';
	$s .= '<text x="' . ( $W - 14 ) . '" y="30" text-anchor="end" font-size="17" font-weight="700" fill="#14262e">' . zt_svg_esc( $title ) . '</text>';
	$s .= '<line x1="' . $L . '" y1="' . ( $T + $ih ) . '" x2="' . ( $W - $R ) . '" y2="' . ( $T + $ih ) . '" stroke="#9fb0b8" stroke-width="1"/>';
	$xy = array();
	foreach ( $pts as $i => $p ) { $x = $n > 1 ? ( $W - $R ) - $i * ( $iw / ( $n - 1 ) ) : ( $W - $R ); $y = $T + $ih - ( $p[1] / $max ) * $ih; $xy[] = array( round( $x, 1 ), round( $y, 1 ) ); }
	$s .= '<polyline fill="none" stroke="#1f6f8b" stroke-width="2.5" stroke-linejoin="round" points="' . implode( ' ', array_map( function ( $q ) { return $q[0] . ',' . $q[1]; }, $xy ) ) . '"/>';
	foreach ( $pts as $i => $p ) {
		$s .= '<circle cx="' . $xy[ $i ][0] . '" cy="' . $xy[ $i ][1] . '" r="4" fill="#1f6f8b"/>';
		$s .= '<text x="' . $xy[ $i ][0] . '" y="' . ( $xy[ $i ][1] - 9 ) . '" text-anchor="middle" font-size="12" font-weight="700" fill="#14262e">' . zt_svg_esc( zt_fmt( $p[1] ) ) . '</text>';
		$s .= '<text x="' . $xy[ $i ][0] . '" y="' . ( $T + $ih + 20 ) . '" text-anchor="middle" font-size="11" fill="#43555e">' . zt_svg_esc( substr( $p[0], 5, 2 ) . '/' . substr( $p[0], 2, 2 ) ) . '</text>';
	}
	return $s . '</svg>';
}

function zt_csv_cell( $v ) { $v = (string) $v; return preg_match( '/[",\r\n]/', $v ) ? '"' . str_replace( '"', '""', $v ) . '"' : $v; }
/** Aggregated numbers only. UTF-8 with a BOM so Excel reads Arabic. */
function zt_rep_csv( $d ) {
	$rows = array( array( 'section', 'key', 'value' ), array( 'meta', 'month', $d['ym'] ), array( 'meta', 'total_orders', $d['total'] ), array( 'meta', 'min_orders_to_name_a_district', $d['min_hood'] ) );
	foreach ( $d['pests'] as $p ) { $rows[] = array( 'pest', $p[0], $p[1] ); }
	foreach ( $d['trend'] as $p ) { $rows[] = array( 'trend', $p[0], $p[1] ); }
	foreach ( $d['hoods'] as $p ) { $rows[] = array( 'district', $p[0], $p[1] ); }
	foreach ( $d['cities'] as $c ) { $rows[] = array( 'city_total', $c['city'], $c['total'] ); foreach ( $c['pests'] as $p ) { $rows[] = array( 'city_pest', $c['city'] . ' / ' . $p[0], $p[1] ); } }
	return "\xEF\xBB\xBF" . implode( "\r\n", array_map( function ( $r ) { return implode( ',', array_map( 'zt_csv_cell', $r ) ); }, $rows ) ) . "\r\n";
}

/* ---------------------------------------------- pure: CSV import (no personal data) ---------------------------------------------- */

/**
 * → array( 'rows' => array( array( d, svc, hood, city ) ), 'errors' => array( text ), 'skipped' => n ).
 * Accepts only the headers date/service/hood/city (Arabic or English). ANY other column — above all a name, phone, e-mail or address — rejects the whole file.
 */
function zt_rep_parse_csv( $text ) {
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text );
	$lines = preg_split( '/\r\n|\n|\r/', $text, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $lines ) { return array( 'rows' => array(), 'errors' => array( 'الملف فارغ.' ), 'skipped' => 0 ); }
	$delim = substr_count( $lines[0], ';' ) > substr_count( $lines[0], ',' ) ? ';' : ',';
	$head = array_map( function ( $h ) { return mb_strtolower( trim( $h ) ); }, str_getcsv( $lines[0], $delim, '"', '' ) );
	$alias = array( 'date' => 'd', 'التاريخ' => 'd', 'تاريخ' => 'd', 'service' => 'svc', 'الخدمة' => 'svc', 'خدمة' => 'svc', 'hood' => 'hood', 'الحي' => 'hood', 'حي' => 'hood', 'city' => 'city', 'المدينة' => 'city', 'مدينة' => 'city' );
	$idx = array(); $bad = array();
	foreach ( $head as $i => $h ) { if ( isset( $alias[ $h ] ) ) { $idx[ $alias[ $h ] ] = $i; } else { $bad[] = $h; } }
	if ( $bad ) { return array( 'rows' => array(), 'errors' => array( 'أعمدة غير مسموحة: ' . implode( '، ', $bad ) . '. الملف يقبل فقط: التاريخ، الخدمة، الحي، المدينة (بلا أي بيانات شخصية). لم يُقبل شيء.' ), 'skipped' => 0 ); }
	foreach ( array( 'd' => 'التاريخ', 'svc' => 'الخدمة', 'hood' => 'الحي', 'city' => 'المدينة' ) as $k => $l ) { if ( ! isset( $idx[ $k ] ) ) { return array( 'rows' => array(), 'errors' => array( 'عمود «' . $l . '» ناقص.' ), 'skipped' => 0 ); } }
	$rows = array(); $errors = array(); $skipped = 0;
	foreach ( array_slice( $lines, 1 ) as $n => $line ) {
		$c = str_getcsv( $line, $delim, '"', '' );
		$d = str_replace( '/', '-', trim( zt_digits_en( (string) ( $c[ $idx['d'] ] ?? '' ) ) ) );
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $d, $m ) ) { $d = sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] ); }
		$svc = trim( (string) ( $c[ $idx['svc'] ] ?? '' ) );
		if ( '' === zt_add_months_str( $d, 0 ) || '' === $svc ) { $skipped++; if ( count( $errors ) < 10 ) { $errors[] = 'سطر ' . ( $n + 2 ) . ': تاريخ أو خدمة غير صالحة.'; } continue; }
		$rows[] = array( 'd' => $d, 'svc' => mb_substr( $svc, 0, 160 ), 'hood' => mb_substr( trim( (string) ( $c[ $idx['hood'] ] ?? '' ) ), 0, 120 ), 'city' => mb_substr( trim( (string) ( $c[ $idx['city'] ] ?? '' ) ), 0, 80 ) );
	}
	return array( 'rows' => $rows, 'errors' => $errors, 'skipped' => $skipped );
}

/* ---------------------------------------------- data sources ---------------------------------------------- */

/** All completed pest-relevant history between two months: array( rows, orders_n, hist_n ). */
function zt_rep_rows( $from_ym, $to_ym ) {
	global $wpdb;
	$from = $from_ym . '-01'; $to = zt_add_days_str( zt_add_months_str( $to_ym . '-01', 1 ), -1 );
	$rows = array(); $on = 0; $hn = 0;
	$ids = get_posts( array( 'post_type' => 'zad_order', 'post_status' => 'publish', 'numberposts' => 20000, 'fields' => 'ids', 'no_found_rows' => true,
		'meta_query' => array( array( 'key' => '_zt_status', 'value' => 'done' ), array( 'key' => '_zt_service_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) ) ) );
	foreach ( $ids as $id ) { $rows[] = array( 'd' => (string) get_post_meta( $id, '_zt_service_date', true ), 'svc' => (string) get_post_meta( $id, '_zt_service', true ), 'hood' => (string) get_post_meta( $id, '_zt_hood', true ), 'city' => (string) get_post_meta( $id, '_zt_city', true ) ); $on++; }
	$t = zt_hist_table();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT d, svc, hood, city FROM $t WHERE d BETWEEN %s AND %s", $from, $to ), ARRAY_A ) as $r ) { $rows[] = $r; $hn++; }
	return array( $rows, $on, $hn );
}

function zt_rep_generate( $ym ) {
	if ( ! preg_match( '/^\d{4}-\d{2}$/', $ym ) || '' === zt_add_months_str( $ym . '-01', 0 ) ) { return new WP_Error( 'ym', 'شهر غير صالح.' ); }
	if ( ! zt_tool_ready( 'report' ) ) { return new WP_Error( 'cfg', 'حدّد «أقل عدد طلبات في الشهر» في إعدادات التقرير أولاً.' ); }
	$ex = get_posts( array( 'post_type' => 'zad_report', 'post_status' => 'any', 'name' => $ym, 'numberposts' => 1, 'no_found_rows' => true ) );
	if ( $ex && 'publish' === $ex[0]->post_status ) { return new WP_Error( 'pub', 'هذا التقرير منشور ومجمّد؛ حوّله إلى مسودة أولاً إن أردت إعادة توليده.' ); }
	list( $rows, $on, $hn ) = zt_rep_rows( substr( zt_add_months_str( $ym . '-01', -11 ), 0, 7 ), $ym );
	$d = zt_rep_aggregate( $rows, $ym, zt_rep_map(), max( 1, (int) zt_opt( 'report.min_hood' ) ), zt_rep_cities() );
	$d['src'] = array( 'orders' => $on, 'hist' => $hn ); $d['generated'] = wp_date( 'Y-m-d H:i' );
	$title = 'تقرير الحشرات — ' . zt_ar_month( (int) substr( $ym, 0, 4 ), (int) substr( $ym, 5, 2 ) );
	$id = $ex ? $ex[0]->ID : wp_insert_post( array( 'post_type' => 'zad_report', 'post_status' => 'draft', 'post_title' => $title, 'post_name' => $ym ), true );
	if ( is_wp_error( $id ) ) { return $id; }
	update_post_meta( $id, '_zt_rep_data', $d );
	update_post_meta( $id, '_zt_rep_summary', zt_rep_summary( (string) zt_opt( 'report.summary_tpl' ), $d ) );
	return (int) $id;
}

/* ---------------------------------------------- meta box + publish guard ---------------------------------------------- */

add_action( 'add_meta_boxes', function () { add_meta_box( 'zt_rep', 'محتوى التقرير', 'zt_rep_box', 'zad_report', 'normal', 'high' ); } );
function zt_rep_box( $post ) {
	wp_nonce_field( 'zt_rep_box', 'zt_rep_nonce' );
	$d = get_post_meta( $post->ID, '_zt_rep_data', true ); $notes = (string) get_post_meta( $post->ID, '_zt_rep_notes', true ); $sum = (string) get_post_meta( $post->ID, '_zt_rep_summary', true );
	if ( ! is_array( $d ) ) { echo '<p>لا توجد بيانات. ولّد المسودة من «تقارير الحشرات ← توليد وتصدير».</p>'; return; }
	$probs = zt_rep_publish_check( $d, $notes, (int) zt_opt( 'report.min_month' ) );
	echo $probs ? '<div class="notice notice-warning inline"><p><strong>لا يمكن النشر بعد:</strong><br>' . implode( '<br>', array_map( 'esc_html', $probs ) ) . '</p></div>' : '<div class="notice notice-success inline"><p>جاهز للنشر بعد مراجعتك.</p></div>';
	echo '<p>الشهر: <strong>' . esc_html( $d['ym'] ) . '</strong> — طلبات الحشرات: <strong>' . (int) $d['total'] . '</strong> (من طلبات زاد: ' . (int) ( $d['src']['orders'] ?? 0 ) . '، سجل مستورد: ' . (int) ( $d['src']['hist'] ?? 0 ) . ') — وُلّد: ' . esc_html( $d['generated'] ?? '' ) . '</p>';
	echo '<p><label><strong>جملة الملخص (من قالب وبيانات فعلية، عدّلها إن شئت)</strong><textarea class="large-text" rows="2" name="zt_rep[summary]">' . esc_textarea( $sum ) . '</textarea></label></p>';
	echo '<p><label><strong>ملاحظات المحرر والنصائح (إجباري)</strong><textarea class="large-text" rows="8" name="zt_rep[notes]">' . esc_textarea( $notes ) . '</textarea></label></p>';
}
add_action( 'save_post_zad_report', function ( $id ) {
	if ( ! isset( $_POST['zt_rep_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zt_rep_nonce'] ) ), 'zt_rep_box' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_autosave( $id ) ) { return; }
	$in = isset( $_POST['zt_rep'] ) ? (array) wp_unslash( $_POST['zt_rep'] ) : array();
	update_post_meta( $id, '_zt_rep_notes', sanitize_textarea_field( $in['notes'] ?? '' ) );
	update_post_meta( $id, '_zt_rep_summary', sanitize_text_field( $in['summary'] ?? '' ) );
} );
/** Never publish a report that fails the check (no matter how it was triggered): it is saved as a draft with the reasons. */
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( 'zad_report' !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) { return $data; }
	$id = (int) ( $postarr['ID'] ?? 0 );
	$notes = isset( $_POST['zt_rep']['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['zt_rep']['notes'] ) ) : (string) get_post_meta( $id, '_zt_rep_notes', true );
	$p = zt_rep_publish_check( get_post_meta( $id, '_zt_rep_data', true ), $notes, (int) zt_opt( 'report.min_month' ) );
	if ( $p ) { $data['post_status'] = 'draft'; set_transient( 'zt_rep_msg_' . get_current_user_id(), $p, 120 ); }
	return $data;
}, 10, 2 );
add_action( 'admin_notices', function () {
	$m = get_transient( 'zt_rep_msg_' . get_current_user_id() );
	if ( $m ) { delete_transient( 'zt_rep_msg_' . get_current_user_id() ); echo '<div class="notice notice-error"><p><strong>لم يُنشر التقرير:</strong><br>' . implode( '<br>', array_map( 'esc_html', (array) $m ) ) . '</p></div>'; }
} );

/* ---------------------------------------------- admin: generate + export + import ---------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=zad_report', 'توليد واستيراد', 'توليد واستيراد', 'manage_options', 'zt-report-tools', 'zt_rep_admin_page' );
} );
function zt_rep_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	echo '<div class="wrap" dir="rtl"><h1>تقارير الحشرات الموسمية</h1>';
	foreach ( array( 'made' => 'وُلّدت المسودة.', 'imported' => 'تم الاستيراد.', 'deleted' => 'حُذفت الدفعة.' ) as $k => $t ) { if ( isset( $_GET[ $k ] ) ) { echo '<div class="notice notice-success"><p>' . esc_html( $t ) . '</p></div>'; } }
	if ( isset( $_GET['err'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( wp_unslash( (string) $_GET['err'] ) ) ) . '</p></div>'; }
	if ( ! zt_tool_ready( 'report' ) ) { echo '<div class="notice notice-warning"><p>اكتب «أقل عدد طلبات في الشهر» في <a href="' . esc_url( admin_url( 'admin.php?page=zad-tools&tab=report' ) ) . '">إعدادات التقرير</a> أولاً.</p></div>'; }
	echo '<h2>1) توليد مسودة شهر</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'zt_rep_gen' ); echo '<input type="hidden" name="action" value="zt_rep_generate"><select name="ym">';
	$cur = wp_date( 'Y-m' );
	for ( $k = 1; $k <= 24; $k++ ) { $ym = substr( zt_add_months_str( $cur . '-01', -$k ), 0, 7 ); echo '<option value="' . esc_attr( $ym ) . '">' . esc_html( zt_ar_month( (int) substr( $ym, 0, 4 ), (int) substr( $ym, 5, 2 ) ) ) . '</option>'; }
	echo '</select> '; submit_button( 'ولّد / حدّث المسودة', 'primary', 'submit', false ); echo '</form><p class="description">يُجمّع طلبات الشهر المكتملة + السجل المستورد، ويحفظ الأرقام في مسودة. التقرير المنشور لا يُعاد توليده. بعد التوليد افتح المسودة، اكتب ملاحظات المحرر، ثم انشر.</p>';
	echo '<h2>2) استيراد طلبات قديمة (CSV)</h2><p>أعمدة مسموحة فقط: <code>التاريخ, الخدمة, الحي, المدينة</code> (أو date, service, hood, city). أي عمود آخر (اسم/جوال/إيميل/عنوان) يُرفض الملف كله. <strong>لا تستورد فترة موجودة أصلاً في الطلبات المكتملة</strong> وإلا تتكرر.</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'zt_rep_imp' ); echo '<input type="hidden" name="action" value="zt_rep_import"><input type="file" name="csv" accept=".csv,text/csv" required> '; submit_button( 'معاينة الاستيراد', 'secondary', 'submit', false ); echo '</form>';
	if ( isset( $_GET['preview'] ) ) {
		$tok = preg_replace( '/[^a-f0-9]/', '', (string) $_GET['preview'] ); $p = get_transient( 'zt_rep_imp_' . $tok );
		if ( is_array( $p ) ) {
			echo '<div class="notice notice-info"><p>سطور صالحة: <strong>' . count( $p['rows'] ) . '</strong> — مرفوضة: <strong>' . (int) $p['skipped'] . '</strong>. نطاق التواريخ: ' . esc_html( $p['rows'] ? min( array_column( $p['rows'], 'd' ) ) . ' → ' . max( array_column( $p['rows'], 'd' ) ) : '—' ) . '</p>' . ( $p['errors'] ? '<p>' . implode( '<br>', array_map( 'esc_html', $p['errors'] ) ) . '</p>' : '' ) . '</div>';
			if ( $p['rows'] ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'zt_rep_imp2' ); echo '<input type="hidden" name="action" value="zt_rep_import_confirm"><input type="hidden" name="tok" value="' . esc_attr( $tok ) . '">'; submit_button( 'تأكيد الاستيراد', 'primary', 'submit', false ); echo '</form>'; }
		}
	}
	$b = (array) $wpdb->get_results( 'SELECT batch, MIN(d) a, MAX(d) z, COUNT(*) n FROM ' . zt_hist_table() . ' GROUP BY batch ORDER BY MAX(id) DESC', ARRAY_A );
	echo '<h3>دفعات مستوردة</h3>';
	if ( ! $b ) { echo '<p>لا توجد.</p>'; } else {
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>الدفعة</th><th>من</th><th>إلى</th><th>سطور</th><th></th></tr></thead><tbody>';
		foreach ( $b as $r ) { echo '<tr><td><code>' . esc_html( $r['batch'] ) . '</code></td><td>' . esc_html( $r['a'] ) . '</td><td>' . esc_html( $r['z'] ) . '</td><td>' . (int) $r['n'] . '</td><td><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zt_rep_batch_delete&batch=' . $r['batch'] ), 'zt_rep_del' ) ) . '" onclick="return confirm(\'حذف الدفعة؟\')">حذف</a></td></tr>'; }
		echo '</tbody></table>';
	}
	echo '</div>';
}
function zt_rep_back( $args ) { wp_safe_redirect( add_query_arg( $args + array( 'post_type' => 'zad_report', 'page' => 'zt-report-tools' ), admin_url( 'edit.php' ) ) ); exit; }
add_action( 'admin_post_zt_rep_generate', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	check_admin_referer( 'zt_rep_gen' );
	$r = zt_rep_generate( sanitize_text_field( wp_unslash( $_POST['ym'] ?? '' ) ) );
	if ( is_wp_error( $r ) ) { zt_rep_back( array( 'err' => rawurlencode( $r->get_error_message() ) ) ); }
	wp_safe_redirect( get_edit_post_link( $r, 'raw' ) ); exit;
} );
add_action( 'admin_post_zt_rep_import', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	check_admin_referer( 'zt_rep_imp' );
	if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) || $_FILES['csv']['size'] > 5 * MB_IN_BYTES ) { zt_rep_back( array( 'err' => rawurlencode( 'ملف غير صالح أو أكبر من 5MB.' ) ) ); }
	$r = zt_rep_parse_csv( (string) file_get_contents( $_FILES['csv']['tmp_name'] ) );
	if ( ! $r['rows'] ) { zt_rep_back( array( 'err' => rawurlencode( implode( ' ', $r['errors'] ?: array( 'لا سطور صالحة.' ) ) ) ) ); }
	$tok = bin2hex( random_bytes( 8 ) ); set_transient( 'zt_rep_imp_' . $tok, $r, HOUR_IN_SECONDS );
	zt_rep_back( array( 'preview' => $tok ) );
} );
add_action( 'admin_post_zt_rep_import_confirm', function () {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	check_admin_referer( 'zt_rep_imp2' );
	$tok = preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['tok'] ?? '' ) ); $p = get_transient( 'zt_rep_imp_' . $tok );
	if ( ! is_array( $p ) ) { zt_rep_back( array( 'err' => rawurlencode( 'انتهت صلاحية المعاينة، ارفع الملف مجدداً.' ) ) ); }
	$batch = substr( bin2hex( random_bytes( 6 ) ), 0, 12 );
	foreach ( $p['rows'] as $r ) { $wpdb->insert( zt_hist_table(), array( 'batch' => $batch, 'd' => $r['d'], 'svc' => $r['svc'], 'hood' => $r['hood'], 'city' => $r['city'] ) ); }
	delete_transient( 'zt_rep_imp_' . $tok ); zt_rep_back( array( 'imported' => 1 ) );
} );
add_action( 'admin_post_zt_rep_batch_delete', function () {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	check_admin_referer( 'zt_rep_del' );
	$wpdb->delete( zt_hist_table(), array( 'batch' => preg_replace( '/[^a-f0-9]/', '', (string) ( $_GET['batch'] ?? '' ) ) ) ); zt_rep_back( array( 'deleted' => 1 ) );
} );

/* ---------------------------------------------- public: single report, endpoints, shortcode, schema ---------------------------------------------- */

add_filter( 'template_include', function ( $tpl ) {
	if ( is_singular( 'zad_report' ) ) { $t = locate_template( 'zad-tools/report-single.php' ); return $t ? $t : ZT_DIR . 'templates/report-single.php'; }
	return $tpl;
}, 30 );

function zt_rep_state( $id ) { return array( 'd' => get_post_meta( $id, '_zt_rep_data', true ), 'summary' => (string) get_post_meta( $id, '_zt_rep_summary', true ), 'notes' => (string) get_post_meta( $id, '_zt_rep_notes', true ) ); }
function zt_rep_endpoint( $id, $what, $chart = '' ) { return home_url( '/' ) . '?' . zt_qs_string( array_filter( array( 'zt_rep_' . $what => $id, 'c' => $chart ) ) ); }

/** label → array( url, service ) for pests that have an encyclopedia page. */
function zt_rep_pest_links() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'zad_pest', 'post_status' => 'publish', 'numberposts' => 200, 'no_found_rows' => true ) ) as $p ) { $d = zt_pest_data( $p->ID ); $out[ $d['name'] ] = array( $d['url'], $d['service'] ); }
	return $out;
}

add_action( 'template_redirect', function () {
	foreach ( array( 'csv', 'svg' ) as $what ) {
		if ( ! isset( $_GET[ 'zt_rep_' . $what ] ) ) { continue; }
		$id = absint( $_GET[ 'zt_rep_' . $what ] ); $st = zt_rep_state( $id );
		if ( 'zad_report' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || ! is_array( $st['d'] ) ) { status_header( 404 ); exit; }
		header( 'Cache-Control: public, max-age=86400' ); header( 'X-Robots-Tag: noindex' );
		$d = $st['d']; $mon = zt_ar_month( (int) substr( $d['ym'], 0, 4 ), (int) substr( $d['ym'], 5, 2 ) );
		if ( 'csv' === $what ) { header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="zad-pest-report-' . $d['ym'] . '.csv"' ); echo zt_rep_csv( $d ); exit; } // phpcs:ignore WordPress.Security.EscapeOutput
		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		echo 'trend' === ( $_GET['c'] ?? '' ) ? zt_rep_svg_line( $d['trend'], 'طلبات مكافحة الحشرات — آخر 12 شهراً', 'عدد الطلبات المكتملة شهرياً حتى ' . $mon . ' (زاد)' ) : zt_rep_svg_bars( array_slice( $d['pests'], 0, 8 ), 'أكثر الحشرات طلباً — ' . $mon, 'عدد الطلبات المكتملة لكل حشرة في ' . $mon . ' (زاد)' ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
} );

function zt_rep_table( $head, $rows, $row_html = false ) {
	$h = '<div class="zt-tblwrap"><table class="zt-table"><thead><tr>'; foreach ( $head as $c ) { $h .= '<th scope="col">' . zt_esc( $c ) . '</th>'; } $h .= '</tr></thead><tbody>';
	foreach ( $rows as $r ) { $h .= '<tr>'; foreach ( $r as $i => $c ) { $cell = $row_html && is_array( $c ) ? $c[0] : zt_esc( $c ); $h .= 0 === $i ? '<th scope="row">' . $cell . '</th>' : '<td>' . $cell . '</td>'; } $h .= '</tr>'; }
	return $h . '</tbody></table></div>';
}

/** The report body (everything below the H1). Numbers come from the frozen post meta only. */
function zt_rep_body( $id ) {
	$st = zt_rep_state( $id ); $d = $st['d'];
	if ( ! is_array( $d ) ) { return; }
	$mon = zt_ar_month( (int) substr( $d['ym'], 0, 4 ), (int) substr( $d['ym'], 5, 2 ) ); $links = zt_rep_pest_links(); $url = get_permalink( $id );
	echo '<section class="sec"><div class="wrap wrap--narrow zt-report">';
	if ( '' !== $st['summary'] ) { echo '<p class="zt-lead">' . esc_html( $st['summary'] ) . '</p>'; }
	if ( '' !== trim( $st['notes'] ) ) { echo '<h2>ملاحظات المحرر والنصائح</h2>'; foreach ( preg_split( '/\r\n\r\n|\n\n/', trim( $st['notes'] ) ) as $p ) { echo '<p>' . nl2br( esc_html( $p ) ) . '</p>'; } }
	echo '<h2>أكثر الحشرات طلباً في ' . esc_html( $mon ) . '</h2><figure class="zt-chart">' . zt_rep_svg_bars( array_slice( $d['pests'], 0, 8 ), 'أكثر الحشرات طلباً — ' . $mon, 'عدد الطلبات المكتملة لكل حشرة في ' . $mon ) . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	$rows = array();
	foreach ( $d['pests'] as $p ) {
		$l = $links[ $p[0] ] ?? null;
		$rows[] = array( array( $l ? '<a href="' . esc_url( $l[0] ) . '">' . esc_html( $p[0] ) . '</a>' : esc_html( $p[0] ) ), array( esc_html( zt_fmt( $p[1] ) ) ), array( $l && $l[1] ? '<a href="' . esc_url( $l[1][1] ) . '">' . esc_html( $l[1][0] ) . '</a>' : '—' ) );
	}
	echo zt_rep_table( array( 'الحشرة', 'عدد الطلبات', 'الخدمة' ), $rows, true ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<h2>الاتجاه خلال آخر 12 شهراً</h2><figure class="zt-chart">' . zt_rep_svg_line( $d['trend'], 'طلبات مكافحة الحشرات — آخر 12 شهراً', 'عدد الطلبات المكتملة شهرياً حتى ' . $mon ) . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$tr = array(); foreach ( $d['trend'] as $t ) { $tr[] = array( zt_ar_month( (int) substr( $t[0], 0, 4 ), (int) substr( $t[0], 5, 2 ) ), zt_fmt( $t[1] ) ); }
	echo zt_rep_table( array( 'الشهر', 'عدد الطلبات' ), $tr ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<h2>أكثر الأحياء نشاطاً</h2>';
	if ( $d['hoods'] ) { $hr = array(); foreach ( array_slice( $d['hoods'], 0, 10 ) as $h ) { $hr[] = array( $h[0], zt_fmt( $h[1] ) ); } echo zt_rep_table( array( 'الحي', 'عدد الطلبات' ), $hr ); } // phpcs:ignore WordPress.Security.EscapeOutput
	else { echo '<p>لم يبلغ أي حي الحد الأدنى للظهور باسمه.</p>'; }
	echo '<p class="zt-noteline">لا يُعرض حي باسمه إلا إذا بلغ ' . esc_html( zt_fmt( $d['min_hood'] ) ) . ' طلبات في الشهر' . ( $d['hoods_hidden'] ? ' (أحياء أخرى لم تبلغها: ' . esc_html( zt_fmt( $d['hoods_hidden'] ) ) . ')' : '' ) . '.</p>';
	$cs = array_filter( $d['cities'], function ( $c ) { return $c['total'] > 0; } );
	if ( $cs ) {
		echo '<h2>مقارنة بين المدن</h2>'; $names = array_column( $cs, 'city' ); $labels = array(); foreach ( $cs as $c ) { foreach ( $c['pests'] as $p ) { $labels[ $p[0] ] = ( $labels[ $p[0] ] ?? 0 ) + $p[1]; } } arsort( $labels );
		$rows = array(); foreach ( array_keys( $labels ) as $l ) { $r = array( $l ); foreach ( $cs as $c ) { $n = 0; foreach ( $c['pests'] as $p ) { if ( $p[0] === $l ) { $n = $p[1]; } } $r[] = zt_fmt( $n ); } $rows[] = $r; }
		$tot = array( 'الإجمالي' ); foreach ( $cs as $c ) { $tot[] = zt_fmt( $c['total'] ); } $rows[] = $tot;
		echo zt_rep_table( array_merge( array( 'الحشرة' ), $names ), $rows ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<h2>المنهجية</h2>'; foreach ( zt_rep_method_lines( $d ) as $l ) { echo '<p>' . esc_html( $l ) . '</p>'; }
	echo '<h2>حمّل أو ضمّن</h2><p><a class="btn btn--accent" href="' . esc_url( zt_rep_endpoint( $id, 'csv' ) ) . '" data-zt-event="tool_download">حمّل الأرقام (CSV)</a></p>';
	foreach ( array( 'top' => 'رسم أكثر الحشرات طلباً', 'trend' => 'رسم الاتجاه' ) as $c => $lab ) {
		$h = ( 'top' === $c ? 36 * max( 1, min( 8, count( $d['pests'] ) ) ) + 64 : 280 );
		$code = '<a href="' . esc_url( $url ) . '"><img src="' . esc_url( zt_rep_endpoint( $id, 'svg', $c ) ) . '" alt="' . esc_attr( ( 'top' === $c ? 'أكثر الحشرات طلباً' : 'الاتجاه خلال 12 شهراً' ) . ' — ' . $mon . ' — زاد' ) . '" width="640" height="' . $h . '"></a><br><small>المصدر: <a href="' . esc_url( $url ) . '">تقرير زاد الموسمي للحشرات — ' . esc_html( $mon ) . '</a></small>';
		echo '<p><label><strong>كود تضمين ' . esc_html( $lab ) . '</strong> (يرجع برابط للتقرير)<textarea class="large-text code" rows="3" readonly onclick="this.select()">' . esc_textarea( $code ) . '</textarea></label></p>';
	}
	echo '</div></section>';
}

function zt_rep_method_lines( $d ) {
	$tpl = (string) zt_opt( 'report.method_tpl' );
	$imp = ! empty( $d['src']['hist'] ) ? ' (مع سجل مستورد من طلبات سابقة)' : '';
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\n|\r/', strtr( $tpl, array( '{مستورد}' => $imp, '{حد_الحي}' => zt_fmt( $d['min_hood'] ) ) ) ) ), 'strlen' ) );
}

add_filter( 'zad_current_crumbs', function ( $c ) {
	if ( ! is_singular( 'zad_report' ) ) { return $c; }
	$out = array( array( 'الرئيسية', home_url( '/' ) ) ); $ix = (int) zt_opt( 'report.index_page' );
	if ( $ix && 'publish' === get_post_status( $ix ) ) { $out[] = array( get_the_title( $ix ), get_permalink( $ix ) ); }
	$out[] = array( get_the_title(), '' );
	return $out;
} );

add_filter( 'zad_schema_page_nodes', function ( $nodes, $id ) {
	if ( 'zad_report' !== get_post_type( $id ) ) { return $nodes; }
	$st = zt_rep_state( $id ); if ( ! is_array( $st['d'] ) ) { return $nodes; }
	return zt_rep_schema( $nodes, $st['d'], $st['summary'], $id, get_permalink( $id ), (string) zt_opt( 'report.license_url' ) );
}, 10, 2 );

/** Dataset (+ Article) for a published report. */
function zt_rep_schema( $nodes, $d, $summary, $id, $u, $license ) {
	$home = home_url( '/' ); $ym = $d['ym']; $last = zt_add_days_str( zt_add_months_str( $ym . '-01', 1 ), -1 );
	$mon = zt_ar_month( (int) substr( $ym, 0, 4 ), (int) substr( $ym, 5, 2 ) ); $name = 'بيانات طلبات مكافحة الحشرات لدى زاد — ' . $mon;
	$ds = array( '@type' => 'Dataset', '@id' => $u . '#dataset', 'name' => $name, 'description' => '' !== $summary ? $summary : 'أعداد الطلبات المكتملة لمكافحة الحشرات لدى زاد في ' . $mon . '، مجمّعة بلا بيانات شخصية.',
		'url' => $u, 'inLanguage' => 'ar', 'temporalCoverage' => $ym . '-01/' . $last, 'creator' => array( '@id' => $home . '#organization' ), 'publisher' => array( '@id' => $home . '#organization' ), 'isAccessibleForFree' => true,
		'distribution' => array( array( '@type' => 'DataDownload', 'encodingFormat' => 'text/csv', 'contentUrl' => zt_rep_endpoint( $id, 'csv' ) ) ), 'isBasedOn' => 'طلبات زاد المكتملة', 'datePublished' => get_post_time( 'Y-m-d', true, $id ) );
	$cities = array_values( array_filter( array_column( (array) $d['cities'], 'city' ) ) ); if ( $cities ) { $ds['spatialCoverage'] = array_map( function ( $c ) { return array( '@type' => 'Place', 'name' => $c ); }, $cities ); }
	if ( '' !== trim( $license ) ) { $ds['license'] = $license; }
	$person = function_exists( 'zsc_person_node' ) ? zsc_person_node( (int) get_post_field( 'post_author', $id ) ) : null;
	$art = array( '@type' => 'Article', '@id' => $u . '#article', 'headline' => mb_substr( get_the_title( $id ), 0, 110 ), 'url' => $u, 'mainEntityOfPage' => array( '@id' => $u . '#webpage' ), 'isPartOf' => array( '@id' => $u . '#webpage' ),
		'datePublished' => get_post_time( 'c', true, $id ), 'dateModified' => get_post_modified_time( 'c', true, $id ), 'publisher' => array( '@id' => $home . '#organization' ), 'inLanguage' => 'ar', 'about' => array( '@id' => $u . '#dataset' ) );
	if ( $person ) { $art['author'] = array( '@id' => $person['@id'] ); }
	$nodes[] = $ds; $nodes[] = $art; if ( $person ) { $nodes[] = $person; }
	return $nodes;
}

/** [zad_pest_report] — for the owner's /pest-report/ Page: the latest published report in brief + the archive. */
add_shortcode( 'zad_pest_report', function () {
	$q = get_posts( array( 'post_type' => 'zad_report', 'post_status' => 'publish', 'numberposts' => 60, 'orderby' => 'name', 'order' => 'DESC', 'no_found_rows' => true ) );
	if ( ! $q ) { return '<p>لم يُنشر أي تقرير بعد.</p>'; }
	$st = zt_rep_state( $q[0]->ID ); $d = $st['d']; $h = '';
	if ( is_array( $d ) ) {
		$h .= '<h2><a href="' . esc_url( get_permalink( $q[0] ) ) . '">' . esc_html( get_the_title( $q[0] ) ) . '</a></h2>' . ( '' !== $st['summary'] ? '<p>' . esc_html( $st['summary'] ) . '</p>' : '' );
		$rows = array(); foreach ( array_slice( $d['pests'], 0, 3 ) as $p ) { $rows[] = array( $p[0], zt_fmt( $p[1] ) ); } $h .= zt_rep_table( array( 'الحشرة', 'عدد الطلبات' ), $rows );
		$h .= '<p><a class="btn btn--accent" href="' . esc_url( get_permalink( $q[0] ) ) . '">اقرأ التقرير كاملاً</a></p>';
	}
	$h .= '<h2>أرشيف التقارير</h2><ul>'; foreach ( $q as $p ) { $h .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>'; }
	wp_enqueue_style( 'zt-tool', ZT_URL . 'assets/css/zt-tool.css', array(), ZT_VERSION );
	return $h . '</ul>';
} );
add_action( 'wp_enqueue_scripts', function () { if ( is_singular( 'zad_report' ) ) { wp_enqueue_style( 'zt-tool', ZT_URL . 'assets/css/zt-tool.css', array(), ZT_VERSION ); } } );
