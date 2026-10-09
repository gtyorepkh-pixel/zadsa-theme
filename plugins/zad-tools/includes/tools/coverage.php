<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 8 — خريطة التغطية التفاعلية.
 * Source of truth: the theme's district library (zad_hood: name, city, side, lat/lng) + the published «صفحة حي» pages (district + service). Nothing is copied or typed here.
 *  1. The district list (grouped by city, then side) with links to each service page is printed by the SERVER — that is what Google reads and the internal-link hub.
 *  2. The Leaflet map (vendored in assets/vendor/leaflet, OpenStreetMap tiles with attribution) loads only when it scrolls into view or the visitor presses «اعرض الخريطة».
 *  3. Average arrival time: from completed orders (on_the_way → arrived), shown only when the district has at least N completed orders (setting, 10); otherwise the manual
 *     figure the owner typed on the district page (if any) is shown, labelled as such.
 * The dataset is cached in a transient that is deleted whenever a district or a district page is saved.
 */

add_action( 'zad_tools_register_settings', function () {
	zt_register_settings( 'coverage', 'خريطة التغطية', array(
		array( 'key' => 'min_orders', 'label' => 'أقل عدد طلبات مكتملة في الحي ليظهر متوسط وقت الوصول', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 1000, 'source' => 'من أمر التنفيذ (الأداة 8): 10 طلبات' ),
		array( 'key' => 'manual_eta', 'label' => 'استخدم رقم «متوسط وقت الوصول» المكتوب يدوياً في صفحة الحي عند عدم كفاية الطلبات', 'type' => 'checkbox', 'default' => 1 ),
		array( 'key' => 'tile_url', 'label' => 'عنوان بلاط الخريطة', 'type' => 'text', 'default' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'desc' => 'الافتراضي OpenStreetMap (الحقوق تظهر على الخريطة). يمكن استبداله بخادم بلاط خاص بك.' ),
	) );
} );

zt_register_tool( 'coverage', array(
	'title' => 'خريطة التغطية', 'desc' => 'الأحياء التي نخدمها والخدمات المتاحة في كل حي، على قائمة وخريطة تفاعلية.',
	'render' => 'zt_cov_render', 'how' => 'zt_cov_how', 'js' => ZT_URL . 'assets/js/coverage.js', 'css' => ZT_URL . 'assets/css/zt-coverage.css',
) );

function zt_cov_sides() { return function_exists( 'zad_hood_sides' ) ? zad_hood_sides() : array( 'north' => 'شمال', 'south' => 'جنوب', 'east' => 'شرق', 'west' => 'غرب', 'center' => 'وسط' ); }

/* ---------------------------------------------- pure parts (tested without WordPress) ---------------------------------------------- */

/**
 * Average minutes from «on the way» to «arrived» per district, only for districts with >= $min completed orders that have both timestamps.
 * $orders = array( array( 'hood' => 'النرجس', 'log' => array( array( status, ts, user ), … ) ), … )  → array( hood => array( 'm' => minutes, 'n' => orders ) )
 */
function zt_cov_eta_from_logs( $orders, $min ) {
	$sum = array(); $cnt = array();
	foreach ( $orders as $o ) {
		$h = trim( (string) ( $o['hood'] ?? '' ) );
		if ( '' === $h ) { continue; }
		$way = null; $arr = null;
		foreach ( (array) ( $o['log'] ?? array() ) as $e ) {
			if ( ! is_array( $e ) || count( $e ) < 2 ) { continue; }
			if ( 'on_the_way' === $e[0] && null === $way ) { $way = (int) $e[1]; }
			elseif ( 'arrived' === $e[0] && null !== $way && null === $arr && (int) $e[1] >= $way ) { $arr = (int) $e[1]; }
		}
		if ( null === $way || null === $arr ) { continue; }
		$sum[ $h ] = ( $sum[ $h ] ?? 0 ) + ( $arr - $way ) / 60; $cnt[ $h ] = ( $cnt[ $h ] ?? 0 ) + 1;
	}
	$out = array();
	foreach ( $cnt as $h => $n ) { if ( $n >= $min ) { $out[ $h ] = array( 'm' => (int) floor( $sum[ $h ] / $n + 0.5 ), 'n' => $n ); } }
	return $out;
}

/**
 * $pages  = array( array( 'hood' => id, 'svc' => 'تنظيف مكيفات', 'url' => …, 'eta' => manual minutes|0 ), … )   (published, active district pages only)
 * $hoods  = array( id => array( 'name', 'city', 'side', 'lat', 'lng' ) )
 * $eta    = zt_cov_eta_from_logs(), keyed by district NAME.   $svc_order = the theme's service list (display order).
 * → array( 'services' => …, 'hoods' => array( array( id, n, c, s, lat, lng, v => array( array( svc, url ) ), eta ) ) ) sorted by city, side, name.
 */
function zt_cov_assemble( $pages, $hoods, $eta, $svc_order, $use_manual = true ) {
	$by = array();
	foreach ( $pages as $p ) {
		$id = (int) $p['hood'];
		if ( ! $id || ! isset( $hoods[ $id ] ) || '' === (string) $p['svc'] || '' === (string) $p['url'] ) { continue; }
		$by[ $id ]['v'][ $p['svc'] ] = $p['url'];
		if ( ! empty( $p['eta'] ) && empty( $by[ $id ]['man'] ) ) { $by[ $id ]['man'] = (int) $p['eta']; }
	}
	$sides = array_keys( zt_cov_sides() ); $out = array(); $used = array();
	foreach ( $by as $id => $d ) {
		$h = $hoods[ $id ]; $v = array(); $seen = array();
		foreach ( $svc_order as $s ) { if ( isset( $d['v'][ $s ] ) ) { $v[] = array( $s, $d['v'][ $s ] ); $seen[ $s ] = true; } }
		foreach ( $d['v'] as $s => $u ) { if ( ! isset( $seen[ $s ] ) ) { $v[] = array( $s, $u ); $seen[ $s ] = true; } }
		foreach ( $v as $x ) { $used[ $x[0] ] = true; }
		$e = null;
		if ( isset( $eta[ $h['name'] ] ) ) { $e = array( 'm' => $eta[ $h['name'] ]['m'], 'n' => $eta[ $h['name'] ]['n'], 'src' => 'orders' ); }
		elseif ( $use_manual && ! empty( $d['man'] ) ) { $e = array( 'm' => $d['man'], 'n' => 0, 'src' => 'manual' ); }
		$out[] = array( 'id' => (int) $id, 'n' => $h['name'], 'c' => $h['city'], 's' => $h['side'], 'lat' => $h['lat'], 'lng' => $h['lng'], 'v' => $v, 'eta' => $e );
	}
	usort( $out, function ( $a, $b ) use ( $sides ) {
		$sa = array_search( $a['s'], $sides, true ); $sb = array_search( $b['s'], $sides, true );
		return strcmp( (string) $a['c'], (string) $b['c'] ) ?: ( ( false === $sa ? 99 : $sa ) <=> ( false === $sb ? 99 : $sb ) ) ?: strcmp( $a['n'], $b['n'] );
	} );
	$svcs = array(); foreach ( $svc_order as $s ) { if ( isset( $used[ $s ] ) ) { $svcs[] = $s; } }
	foreach ( array_keys( $used ) as $s ) { if ( ! in_array( $s, $svcs, true ) ) { $svcs[] = $s; } }
	return array( 'services' => $svcs, 'hoods' => $out );
}

/* ---------------------------------------------- WordPress side ---------------------------------------------- */

function zt_cov_flush() { delete_transient( zt_cov_key() ); }
function zt_cov_key() { return 'zt_cov_' . md5( (string) get_option( 'zad_hood_ver', '0' ) . '|' . (int) zt_opt( 'coverage.min_orders' ) . '|' . (int) zt_opt( 'coverage.manual_eta' ) ); }
add_action( 'save_post', function ( $id, $post ) {
	if ( wp_is_post_revision( $id ) ) { return; }
	if ( 'zad_hood' === $post->post_type || ( function_exists( 'zad_hood_types' ) && in_array( $post->post_type, zad_hood_types(), true ) ) ) { zt_cov_flush(); }
}, 20, 2 );
add_action( 'transition_post_status', function ( $n, $o, $post ) { if ( 'zad_order' === $post->post_type ) { delete_transient( 'zt_cov_eta' ); } }, 10, 3 );

function zt_cov_eta_orders() {
	$c = get_transient( 'zt_cov_eta' );
	if ( is_array( $c ) ) { return $c; }
	$ids = get_posts( array( 'post_type' => 'zad_order', 'post_status' => 'publish', 'numberposts' => 5000, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_zt_status', 'meta_value' => 'done' ) );
	$rows = array();
	foreach ( $ids as $id ) { $rows[] = array( 'hood' => (string) get_post_meta( $id, '_zt_hood', true ), 'log' => (array) get_post_meta( $id, '_zt_status_log', true ) ); }
	set_transient( 'zt_cov_eta', $rows, 6 * HOUR_IN_SECONDS );
	return $rows;
}

function zt_cov_dataset() {
	$f = apply_filters( 'zt_coverage_dataset', null ); // lets a theme / a test supply the data
	if ( is_array( $f ) ) { return $f; }
	$key = zt_cov_key(); $c = get_transient( $key );
	if ( is_array( $c ) ) { return $c; }
	if ( ! function_exists( 'zad_hood_library' ) || ! function_exists( 'zad_hood_types' ) || ! function_exists( 'zad_hood_active' ) ) { return array( 'services' => array(), 'hoods' => array(), 'missing' => true ); }
	$hoods = array();
	foreach ( zad_hood_library() as $p ) {
		$t = get_the_terms( $p->ID, defined( 'ZAD_HOOD_TAX' ) ? ZAD_HOOD_TAX : 'zad_hood_city' );
		$lat = get_post_meta( $p->ID, '_zad_hd_lat', true ); $lng = get_post_meta( $p->ID, '_zad_hd_lng', true );
		$hoods[ $p->ID ] = array( 'name' => get_the_title( $p ), 'city' => ( $t && ! is_wp_error( $t ) ) ? $t[0]->name : '', 'side' => (string) get_post_meta( $p->ID, '_zad_hd_side', true ),
			'lat' => '' === $lat ? null : (float) $lat, 'lng' => '' === $lng ? null : (float) $lng );
	}
	$pages = array();
	foreach ( get_posts( array( 'post_type' => zad_hood_types(), 'post_status' => 'publish', 'numberposts' => 2000, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_zad_h_hood', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ) as $p ) {
		if ( ! zad_hood_active( $p->ID ) ) { continue; }
		$pages[] = array( 'hood' => (int) get_post_meta( $p->ID, '_zad_h_hood', true ), 'svc' => (string) get_post_meta( $p->ID, '_zad_h_svc', true ), 'url' => get_permalink( $p ), 'eta' => (int) get_post_meta( $p->ID, '_zad_h_eta', true ) );
	}
	$d = zt_cov_assemble( $pages, $hoods, zt_cov_eta_from_logs( zt_cov_eta_orders(), max( 1, (int) zt_opt( 'coverage.min_orders' ) ) ), function_exists( 'zad_hood_services' ) ? zad_hood_services() : array(), (bool) zt_opt( 'coverage.manual_eta' ) );
	set_transient( $key, $d, 6 * HOUR_IN_SECONDS );
	return $d;
}

/* ---------------------------------------------- rendering ---------------------------------------------- */

function zt_cov_eta_text( $e ) {
	if ( ! $e ) { return ''; }
	return 'orders' === $e['src'] ? 'متوسط وقت الوصول نحو ' . zt_fmt( $e['m'] ) . ' دقيقة (من ' . zt_fmt( $e['n'] ) . ' طلب مكتمل)' : 'وقت الوصول المعتاد نحو ' . zt_fmt( $e['m'] ) . ' دقيقة';
}

/** The server-rendered hub: city → side → district with its service links. */
function zt_cov_list_html( $d ) {
	$sides = zt_cov_sides(); $h = ''; $city = null; $side = null;
	foreach ( $d['hoods'] as $x ) {
		if ( $x['c'] !== $city ) { if ( null !== $city ) { $h .= '</ul>'; } $h .= '<h3 class="zt-h3">' . zt_esc( '' !== $x['c'] ? $x['c'] : 'مدن أخرى' ) . '</h3>'; $city = $x['c']; $side = null; }
		if ( $x['s'] !== $side ) { if ( null !== $side ) { $h .= '</ul>'; } $h .= '<h4 class="zt-h4">' . zt_esc( $sides[ $x['s'] ] ?? 'أحياء' ) . '</h4><ul class="zt-hoods">'; $side = $x['s']; }
		$h .= '<li data-hood-id="' . (int) $x['id'] . '" data-services="' . zt_esc( implode( '|', array_column( $x['v'], 0 ) ) ) . '"><strong>' . zt_esc( $x['n'] ) . '</strong> — ';
		$links = array(); foreach ( $x['v'] as $v ) { $links[] = '<a href="' . zt_esc( $v[1] ) . '">' . zt_esc( $v[0] ) . '</a>'; }
		$h .= implode( ' · ', $links );
		if ( $x['eta'] ) { $h .= '<br><span class="zt-noteline">' . zt_esc( zt_cov_eta_text( $x['eta'] ) ) . '</span>'; }
		$h .= '</li>';
	}
	return $h . ( null !== $side ? '</ul>' : '' );
}

function zt_cov_render( $ctx ) {
	$d = zt_cov_dataset();
	if ( ! empty( $d['missing'] ) ) { echo '<p class="zt-note">بيانات الأحياء غير متاحة (ثيم زاد غير مفعّل).</p>'; return; }
	if ( ! $d['hoods'] ) { echo '<p class="zt-note">لم تُنشر صفحات أحياء بعد.</p>'; return; }
	$pts = array_values( array_filter( $d['hoods'], function ( $x ) { return null !== $x['lat'] && null !== $x['lng']; } ) );
	$cfg = array( 'services' => $d['services'], 'hoods' => $pts, 'tiles' => (string) zt_opt( 'coverage.tile_url' ), 'leaflet' => ZT_URL . 'assets/vendor/leaflet/leaflet.js', 'leafletCss' => ZT_URL . 'assets/vendor/leaflet/leaflet.css' );
	echo '<div data-zt-coverage data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '">';
	echo '<div class="zt-cov-tools zt-jsonly"><div class="zt-chips" role="group" aria-label="فلتر الخدمة"><button type="button" class="zt-chip is-on" data-svc="">كل الخدمات</button>';
	foreach ( $d['services'] as $s ) { echo '<button type="button" class="zt-chip" data-svc="' . zt_esc( $s ) . '">' . zt_esc( $s ) . '</button>'; }
	echo '</div><button type="button" class="btn btn--ghost" data-zt-locate>حدد موقعي</button></div>';
	echo '<p class="zt-cov-msg" role="status" aria-live="polite"></p>';
	if ( $pts ) { echo '<div class="zt-map" id="zt-map" role="region" aria-label="خريطة الأحياء"><button type="button" class="btn btn--accent" data-zt-showmap>اعرض الخريطة</button></div><p class="zt-noteline">© مساهمو <a href="https://www.openstreetmap.org/copyright" rel="noopener" target="_blank">OpenStreetMap</a>. قائمة الأحياء أدناه تحتوي نفس المعلومات.</p>'; }
	echo '<div class="zt-hoodlist" id="zt-hoodlist">' . zt_cov_list_html( $d ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	$wa = zt_wa_number();
	if ( $wa ) { echo '<p><a class="btn btn--wa" target="_blank" rel="noopener" data-zt-event="tool_whatsapp_click" href="' . zt_esc( zt_wa_url( $wa, zt_wa_message( 'حيّي مش في القائمة، هل تغطّونه؟', '' ) ) ) . '">حيّك مش في القائمة؟ اسألنا على واتساب</a></p>'; }
}

function zt_cov_how( $ctx ) {
	$min = max( 1, (int) zt_opt( 'coverage.min_orders' ) );
	return '<p>القائمة والخريطة تُبنيان من مكتبة الأحياء وصفحات الأحياء المنشورة فقط: لا يظهر حي إلا إذا كانت له صفحة خدمة منشورة، وتظهر له الخدمات التي لها صفحة.</p>'
		. '<p>متوسط وقت وصول الفني يُحسب من الطلبات المكتملة (من لحظة «الفني في الطريق» إلى «وصل الفني») ولا يظهر إلا إذا كان في الحي <strong>' . zt_esc( zt_fmt( $min ) ) . ' طلبات مكتملة أو أكثر</strong>. إن لم يتوفر العدد يظهر الرقم المسجّل يدويًا في صفحة الحي (إن وُجد) ويُكتب بوضوح أنه رقم معتاد.</p>'
		. '<p>خرائط OpenStreetMap تُحمَّل عند ظهورها على الشاشة أو عند الضغط على «اعرض الخريطة»، وزر «حدد موقعي» اختياري ولا يُرسل موقعك إلى أي خادم؛ يُحسب أقرب حي داخل متصفحك.</p>';
}
