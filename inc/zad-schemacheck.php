<?php
/**
 * Schema inspector (Tools → فاحص السكيما): fetch a page of this site and report the JSON-LD it really prints.
 * Read-only.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'فاحص السكيما', 'فاحص السكيما (زاد)', 'manage_options', 'zad-schemacheck', 'zad_schemacheck_page' );
} );

function zad_sc_source( $attrs ) {
	if ( false !== stripos( $attrs, 'yoast-schema-graph' ) ) { return 'Yoast SEO'; }
	if ( false !== stripos( $attrs, 'rank-math' ) ) { return 'Rank Math'; }
	if ( false !== stripos( $attrs, 'aioseo' ) ) { return 'All in One SEO'; }
	if ( false !== stripos( $attrs, 'schema-pro' ) || false !== stripos( $attrs, 'wpsp' ) ) { return 'Schema Pro / WPSP'; }
	return 'غير محدد (الثيم أو أخرى)';
}

function zad_sc_walk( $data, &$nodes ) {
	if ( ! is_array( $data ) ) { return; }
	if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) { foreach ( $data['@graph'] as $n ) { zad_sc_walk( $n, $nodes ); } return; }
	if ( isset( $data['@type'] ) ) { $nodes[] = $data; return; }
	foreach ( $data as $n ) { if ( is_array( $n ) ) { zad_sc_walk( $n, $nodes ); } }
}

function zad_schemacheck_run( $url ) {
	$res = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 3, 'user-agent' => 'ZadSchemaCheck/1.0', 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	if ( is_wp_error( $res ) ) { return array( 'error' => $res->get_error_message() ); }
	$code = wp_remote_retrieve_response_code( $res ); $html = (string) wp_remote_retrieve_body( $res );
	if ( $code >= 400 || '' === $html ) { return array( 'error' => 'استجابة ' . $code ); }
	$r = array( 'code' => $code, 'scripts' => array(), 'nodes' => array(), 'problems' => array(), 'meta' => array() );
	if ( preg_match_all( '#<script([^>]*)type=["\']application/ld\+json["\']([^>]*)>(.*?)</script>#is', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $i => $x ) {
			$src = zad_sc_source( $x[1] . ' ' . $x[2] );
			$json = trim( html_entity_decode( $x[3], ENT_QUOTES, 'UTF-8' ) === $x[3] ? $x[3] : $x[3] );
			$d = json_decode( $json, true );
			if ( null === $d ) { $r['problems'][] = array( 'hi', 'JSON-LD رقم ' . ( $i + 1 ) . ' غير صالح (خطأ في الصياغة) — المصدر: ' . $src ); continue; }
			$nodes = array(); zad_sc_walk( $d, $nodes );
			foreach ( $nodes as $n ) { $n['__src'] = $src; $n['__script'] = $i + 1; $r['nodes'][] = $n; }
			$r['scripts'][] = array( $i + 1, $src, count( $nodes ) );
		}
	}
	$types = array();
	foreach ( $r['nodes'] as $n ) { foreach ( (array) $n['@type'] as $t ) { $types[ $t ][] = $n; } }
	$single = array( 'Organization', 'LocalBusiness', 'WebSite', 'WebPage', 'BreadcrumbList', 'FAQPage', 'Article', 'BlogPosting', 'Service', 'HomeAndConstructionBusiness', 'AboutPage', 'ContactPage', 'ItemList' );
	foreach ( $types as $t => $list ) {
		if ( in_array( $t, $single, true ) && count( $list ) > 1 ) {
			$srcs = array_unique( array_map( function ( $n ) { return $n['__src']; }, $list ) );
			$r['problems'][] = array( 'hi', "النوع {$t} مكرر " . count( $list ) . ' مرات (المصادر: ' . implode( '، ', $srcs ) . ')' );
		}
	}
	$ids = array();
	foreach ( $r['nodes'] as $n ) { if ( ! empty( $n['@id'] ) ) { $ids[ $n['@id'] ][] = $n['__src']; } }
	foreach ( $ids as $id => $s ) { if ( count( $s ) > 1 ) { $r['problems'][] = array( 'hi', 'معرّف @id مكرر: ' . $id ); } }
	$srcs_all = array_unique( array_column( $r['scripts'], 1 ) );
	if ( count( $srcs_all ) > 1 ) { $r['problems'][] = array( 'hi', 'أكثر من مصدر يطبع سكيما: ' . implode( '، ', $srcs_all ) ); }
	foreach ( $r['nodes'] as $n ) {
		$ts = (array) $n['@type'];
		if ( in_array( 'FAQPage', $ts, true ) ) {
			$q = (array) ( $n['mainEntity'] ?? array() );
			if ( ! $q ) { $r['problems'][] = array( 'hi', 'FAQPage بلا أسئلة' ); }
			foreach ( $q as $qq ) {
				$a = $qq['acceptedAnswer']['text'] ?? '';
				if ( '' === trim( (string) $a ) ) { $r['problems'][] = array( 'hi', 'سؤال بلا إجابة: ' . ( $qq['name'] ?? '' ) ); }
				if ( false !== strpos( (string) $a, '&lt;' ) || false !== strpos( (string) ( $qq['name'] ?? '' ), '&lt;' ) ) { $r['problems'][] = array( 'lo', 'نص سؤال/جواب فيه وسوم HTML مهرّبة (&lt;) — يظهر لجوجل كنص مكسور' ); break; }
			}
		}
		if ( in_array( 'Service', $ts, true ) ) {
			foreach ( array( 'provider', 'areaServed' ) as $f ) { if ( empty( $n[ $f ] ) ) { $r['problems'][] = array( 'lo', "Service بلا {$f}" ); } }
		}
		if ( in_array( 'BreadcrumbList', $ts, true ) ) {
			foreach ( (array) ( $n['itemListElement'] ?? array() ) as $it ) { if ( empty( $it['position'] ) ) { $r['problems'][] = array( 'hi', 'عنصر في BreadcrumbList بلا position' ); break; } }
		}
	}
	if ( ! $r['nodes'] ) { $r['problems'][] = array( 'hi', 'لا توجد بيانات JSON-LD في هذه الصفحة' ); }
	// quick page checks
	$r['meta']['canonical'] = preg_match( '#<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)#i', $html, $c ) ? $c[1] : '';
	$r['meta']['robots']    = preg_match( '#<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']+)#i', $html, $c ) ? $c[1] : '';
	$r['meta']['title']     = preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $c ) ? trim( wp_strip_all_tags( $c[1] ) ) : '';
	$r['meta']['desc']      = preg_match( '#<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)#i', $html, $c ) ? $c[1] : '';
	$r['meta']['h1']        = preg_match_all( '#<h1\b#i', $html );
	if ( 1 !== $r['meta']['h1'] ) { $r['problems'][] = array( 'lo', 'عدد وسوم H1 = ' . $r['meta']['h1'] . ' (المفروض 1)' ); }
	if ( false !== stripos( $r['meta']['robots'], 'noindex' ) ) { $r['problems'][] = array( 'hi', 'الصفحة معلّمة noindex' ); }
	if ( ! $r['meta']['canonical'] ) { $r['problems'][] = array( 'lo', 'لا يوجد canonical' ); }
	return $r;
}

function zad_schemacheck_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$url = ''; $r = null;
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['zad_sc_url'] ) && check_admin_referer( 'zad_sc' ) ) { // phpcs:ignore
		$url = esc_url_raw( trim( wp_unslash( $_POST['zad_sc_url'] ) ) ); // phpcs:ignore
		$h = wp_parse_url( home_url(), PHP_URL_HOST ); $t = wp_parse_url( $url, PHP_URL_HOST );
		$r = ( $t && strtolower( $t ) === strtolower( $h ) ) ? zad_schemacheck_run( $url ) : array( 'error' => 'أدخل رابطاً من موقعك فقط.' );
	}
	echo '<div class="wrap" dir="rtl"><h1>فاحص السكيما</h1><p>يجلب الصفحة كما يراها جوجل ويحلّل بيانات JSON-LD الفعلية: الأنواع، التكرار، مصدر كل كتلة، وأخطاء شائعة. للقراءة فقط.</p>';
	echo '<form method="post">'; wp_nonce_field( 'zad_sc' ); echo '<input type="url" name="zad_sc_url" dir="ltr" size="70" required placeholder="' . esc_attr( home_url( '/…' ) ) . '" value="' . esc_attr( $url ) . '"> <button class="button button-primary">افحص</button></form>';
	if ( $r ) {
		if ( ! empty( $r['error'] ) ) { echo '<div class="notice notice-error"><p>تعذّر الفحص: ' . esc_html( $r['error'] ) . '</p></div></div>'; return; }
		echo '<h2>الخلاصة</h2>';
		if ( ! $r['problems'] ) { echo '<div class="notice notice-success inline"><p>لم أجد مشاكل واضحة.</p></div>'; } else {
			echo '<ul>'; foreach ( $r['problems'] as $p ) { echo '<li style="color:' . ( 'hi' === $p[0] ? '#b91c1c' : '#b45f00' ) . '">' . ( 'hi' === $p[0] ? '● ' : '○ ' ) . esc_html( $p[1] ) . '</li>'; } echo '</ul>';
		}
		echo '<h2>كتل JSON-LD</h2><table class="widefat striped" style="max-width:700px"><thead><tr><th>#</th><th>المصدر</th><th>عدد العقد</th></tr></thead><tbody>';
		foreach ( $r['scripts'] as $s ) { echo '<tr><td>' . (int) $s[0] . '</td><td>' . esc_html( $s[1] ) . '</td><td>' . (int) $s[2] . '</td></tr>'; }
		echo '</tbody></table><h2>العقد</h2><table class="widefat striped"><thead><tr><th>النوع</th><th>الاسم</th><th>@id</th><th>المصدر</th></tr></thead><tbody>';
		foreach ( $r['nodes'] as $n ) { echo '<tr><td>' . esc_html( implode( ', ', (array) $n['@type'] ) ) . '</td><td>' . esc_html( is_string( $n['name'] ?? '' ) ? ( $n['name'] ?? '' ) : '' ) . '</td><td dir="ltr"><small>' . esc_html( $n['@id'] ?? '' ) . '</small></td><td>' . esc_html( $n['__src'] ) . '</td></tr>'; }
		echo '</tbody></table><h2>فحوص الصفحة</h2><table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( array( 'title' => 'العنوان', 'desc' => 'الوصف', 'canonical' => 'canonical', 'robots' => 'robots', 'h1' => 'عدد H1' ) as $k => $l ) { echo '<tr><td style="width:120px">' . esc_html( $l ) . '</td><td dir="auto">' . esc_html( (string) $r['meta'][ $k ] ?: '—' ) . '</td></tr>'; }
		echo '</tbody></table><p class="description">للتحقق الرسمي استخدم أيضاً Rich Results Test من جوجل. هذا الفاحص يكشف التكرار والتعارض بين المصادر بسرعة.</p>';
	}
	echo '</div>';
}
