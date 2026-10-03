<?php
/**
 * Assets: (1) inspector (Tools → فاحص الأصول): which CSS/JS each page really loads, from which plugin, which version.
 *         (2) optional lean-up of core/plugin assets a page does not use (OFF by default).
 *         (3) optional unification of ?ver= strings (OFF by default).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/* ================= (1) Inspector ================= */
add_action( 'admin_menu', function () {
	add_management_page( 'فاحص الأصول', 'فاحص الأصول (زاد)', 'manage_options', 'zad-assets', 'zad_assets_page' );
} );

function zad_assets_source( $url ) {
	$h = wp_parse_url( $url, PHP_URL_HOST ); $home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $h && strtolower( $h ) !== strtolower( $home ) ) { return array( 'خارجي: ' . $h, false ); }
	if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $url, $m ) ) { return array( 'إضافة: ' . $m[1], false ); }
	if ( preg_match( '#/wp-content/themes/([^/]+)/#', $url, $m ) ) { return array( 'ثيم: ' . $m[1], false ); }
	if ( false !== strpos( $url, '/wp-content/uploads/' ) ) { return array( 'ملف مولّد (uploads)', true ); }
	if ( false !== strpos( $url, '/wp-includes/' ) ) { return array( 'ووردبريس (core)', false ); }
	return array( 'غير محدد', false );
}

function zad_assets_fetch( $url ) {
	$res = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'user-agent' => 'ZadAssetCheck/1.0' ) );
	if ( is_wp_error( $res ) ) { return array( 'error' => $res->get_error_message() ); }
	if ( wp_remote_retrieve_response_code( $res ) >= 400 ) { return array( 'error' => 'استجابة ' . wp_remote_retrieve_response_code( $res ) ); }
	$html = (string) wp_remote_retrieve_body( $res ); $out = array( 'css' => array(), 'js' => array(), 'inline_css' => 0, 'inline_js' => 0 );
	if ( preg_match_all( '#<link\b[^>]*>#i', $html, $m ) ) {
		foreach ( $m[0] as $t ) { if ( preg_match( '#rel=["\']stylesheet["\']#i', $t ) && preg_match( '#href=["\']([^"\']+)#i', $t, $h ) ) { $out['css'][] = html_entity_decode( $h[1] ); } }
	}
	if ( preg_match_all( '#<script\b[^>]*\bsrc=["\']([^"\']+)#i', $html, $m ) ) { foreach ( $m[1] as $s ) { $out['js'][] = html_entity_decode( $s ); } }
	$out['inline_css'] = preg_match_all( '#<style\b#i', $html );
	$out['inline_js']  = preg_match_all( '#<script\b(?![^>]*\bsrc=)(?![^>]*ld\+json)#i', $html );
	return $out;
}

function zad_assets_sample_urls() {
	$urls = array( home_url( '/' ) );
	foreach ( array( array( zad_service_types(), 3 ), array( array( 'post' ), 2 ), array( array( 'page' ), 2 ) ) as $s ) {
		foreach ( get_posts( array( 'post_type' => $s[0], 'post_status' => 'publish', 'numberposts' => $s[1], 'orderby' => 'rand', 'suppress_filters' => true, 'zad_all' => true ) ) as $p ) { $urls[] = get_permalink( $p ); }
	}
	return array_values( array_unique( $urls ) );
}

function zad_assets_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$urls = array(); $res = null;
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'zad_assets' ) ) { // phpcs:ignore
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! empty( $_POST['sample'] ) ) { $urls = zad_assets_sample_urls(); } else {
			foreach ( preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['urls'] ?? '' ) ) as $u ) { $u = esc_url_raw( trim( $u ) ); if ( $u && strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ) === strtolower( (string) $home ) ) { $urls[] = $u; } }
			$urls = array_slice( array_unique( $urls ), 0, 12 );
		}
		@set_time_limit( 120 ); // phpcs:ignore
		$files = array(); $n = 0; $errs = array();
		foreach ( $urls as $u ) {
			$r = zad_assets_fetch( $u );
			if ( ! empty( $r['error'] ) ) { $errs[] = $u . ' — ' . $r['error']; continue; }
			$n++;
			foreach ( array( 'css', 'js' ) as $k ) {
				foreach ( $r[ $k ] as $f ) {
					$base = preg_replace( '/\?.*$/', '', $f ); $ver = ( preg_match( '/[?&]ver=([^&]+)/', $f, $m ) ? $m[1] : '' );
					if ( ! isset( $files[ $base ] ) ) { $files[ $base ] = array( 'type' => $k, 'pages' => 0, 'vers' => array() ); }
					$files[ $base ]['pages']++; $files[ $base ]['vers'][ $ver ] = 1;
				}
			}
			$inl[ $u ] = array( $r['inline_css'], $r['inline_js'] );
		}
		$res = array( 'files' => $files, 'n' => $n, 'errs' => $errs, 'inl' => $inl ?? array() );
	}
	echo '<div class="wrap" dir="rtl"><h1>فاحص الأصول</h1><p>يجلب صفحات من موقعك ويعرض ملفات CSS وJS التي تحمّلها فعلاً: مصدر كل ملف (الإضافة)، والإصدار (<code>ver</code>)، وفي كم صفحة ظهر، وهل هو خاص بصفحة معيّنة. للقراءة فقط.</p>';
	echo '<form method="post">'; wp_nonce_field( 'zad_assets' );
	echo '<p><button class="button button-primary" name="sample" value="1">فحص عينة تلقائية (الرئيسية + خدمات + مقالات + صفحات)</button></p><p>أو الصق روابط (حتى 12، رابط في كل سطر):<br><textarea name="urls" rows="4" cols="80" dir="ltr"></textarea><br><button class="button">فحص الروابط</button></p></form>';
	if ( $res ) {
		foreach ( $res['errs'] as $e ) { echo '<div class="notice notice-error"><p>تعذّر: ' . esc_html( $e ) . '</p></div>'; }
		if ( ! $res['n'] ) { echo '</div>'; return; }
		uasort( $res['files'], function ( $a, $b ) { return array( $a['pages'], count( $b['vers'] ) ) <=> array( $b['pages'], count( $a['vers'] ) ); } );
		echo '<p>فُحصت <b>' . (int) $res['n'] . '</b> صفحة · <b>' . count( $res['files'] ) . '</b> ملف مختلف.</p>';
		$multi = 0; $gen = 0; $single = 0;
		echo '<table class="widefat striped"><thead><tr><th>الملف</th><th>النوع</th><th>المصدر</th><th>الإصدارات (ver)</th><th>الصفحات</th><th>ملاحظات</th></tr></thead><tbody>';
		foreach ( $res['files'] as $f => $d ) {
			list( $src, $generated ) = zad_assets_source( $f ); $notes = array();
			if ( count( $d['vers'] ) > 1 ) { $notes[] = 'نسخ إصدار متعددة'; $multi++; }
			if ( $generated ) { $notes[] = 'ملف مولّد لصفحة'; $gen++; }
			if ( $d['pages'] < $res['n'] && 1 === $d['pages'] && $res['n'] > 2 ) { $notes[] = 'صفحة واحدة فقط'; $single++; }
			echo '<tr><td dir="ltr"><small>' . esc_html( preg_replace( '#^https?://[^/]+#', '', $f ) ) . '</small></td><td>' . esc_html( strtoupper( $d['type'] ) ) . '</td><td>' . esc_html( $src ) . '</td><td dir="ltr">' . esc_html( implode( ', ', array_filter( array_keys( $d['vers'] ) ) ) ?: '—' ) . '</td><td>' . (int) $d['pages'] . ' / ' . (int) $res['n'] . '</td><td style="color:#b45f00">' . esc_html( implode( '، ', $notes ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>الخلاصة</h2><ul><li>ملفات بإصدارات متعددة: <b>' . $multi . '</b></li><li>ملفات مولّدة لصفحات (uploads): <b>' . $gen . '</b></li><li>ملفات تظهر في صفحة واحدة فقط: <b>' . $single . '</b></li></ul>';
		echo '<p class="description">الملفات الخاصة بصفحة معيّنة أو الإضافات التي تظهر في بعض الصفحات فقط هي مصدر المشكلة غالباً. الحل: استبدال بلوكاتها بمكونات الثيم، أو تفعيل «تخفيف الأصول» و«توحيد الإصدار» من خيارات الثيم.</p>';
	}
	echo '</div>';
}

/* ================= (2) Lean-up (OFF by default) ================= */
function zad_lean_needs_blocks( $html ) {
	return (bool) preg_match( '/wp-block-(columns?|group|cover|gallery|buttons?|media-text|embed|video|audio|file|table|pullquote|verse|code|latest|search|social|spacer|details)|\balign(wide|full)\b|is-layout-/i', $html );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! zad_opt( 'zad_lean', false ) || is_admin() ) { return; }
	$html = is_singular() ? (string) get_post_field( 'post_content', get_queried_object_id() ) : '';
	if ( '' === $html || ! zad_lean_needs_blocks( $html ) ) {
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles' ) as $h ) { wp_dequeue_style( $h ); }
	}
	if ( false === strpos( $html, 'wp-block-embed-wordpress' ) ) { wp_deregister_script( 'wp-embed' ); }
	// Owner rules, one per line:  handle > needle   (drop the handle unless the page content contains needle)
	foreach ( preg_split( '/\r\n|\r|\n/', (string) zad_opt( 'zad_lean_rules', '' ) ) as $l ) {
		if ( false === strpos( $l, '>' ) ) { continue; }
		list( $h, $needle ) = array_map( 'trim', explode( '>', $l, 2 ) );
		if ( '' === $h || '' === $needle ) { continue; }
		if ( false === stripos( $html, $needle ) ) { wp_dequeue_style( $h ); wp_dequeue_script( $h ); }
	}
}, 100 );

/* ================= (3) ?ver= unification (OFF by default) ================= */
function zad_ver_filter( $src ) {
	$mode = zad_opt( 'zad_ver_mode', 'off' );
	if ( 'off' === $mode || false === strpos( $src, 'ver=' ) || 0 !== strpos( $src, home_url() ) && 0 !== strpos( $src, '/' ) ) { return $src; }
	$src = remove_query_arg( 'ver', $src );
	return 'theme' === $mode ? add_query_arg( 'ver', defined( 'ZAD_VERSION' ) ? ZAD_VERSION : '1', $src ) : $src;
}
add_filter( 'style_loader_src', 'zad_ver_filter', 99 );
add_filter( 'script_loader_src', 'zad_ver_filter', 99 );
