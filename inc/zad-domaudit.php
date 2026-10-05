<?php
/**
 * Tool: «فحص DOM والحجم» (Tools menu). Fetches one page of this site and reports HTML size, gzip size, element count,
 * inline <style>/<script> weight, SVG count and the heaviest sections — run it before/after a change to compare.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** Pure: measures an HTML string. */
function zad_dom_measure( $html ) {
	$r = array( 'bytes' => strlen( $html ), 'gzip' => strlen( (string) gzencode( $html, 6 ) ), 'elements' => 0, 'svg' => 0, 'use' => 0, 'style_bytes' => 0, 'styles' => array(), 'script_inline' => 0, 'sections' => array() );
	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors(); libxml_use_internal_errors( $prev );
	$r['elements'] = $doc->getElementsByTagName( '*' )->length;
	$r['svg']      = $doc->getElementsByTagName( 'svg' )->length;
	$r['use']      = $doc->getElementsByTagName( 'use' )->length;
	foreach ( $doc->getElementsByTagName( 'style' ) as $s ) {
		$n = strlen( $s->textContent );
		$r['style_bytes'] += $n;
		$r['styles'][] = array( $s->getAttribute( 'id' ) ?: '(بلا id)', $n );
	}
	foreach ( $doc->getElementsByTagName( 'script' ) as $s ) { if ( ! $s->hasAttribute( 'src' ) ) { $r['script_inline'] += strlen( $s->textContent ); } }
	$main = $doc->getElementsByTagName( 'main' )->item( 0 );
	$root = $main ? $main : $doc->getElementsByTagName( 'body' )->item( 0 );
	if ( $root ) {
		foreach ( $root->childNodes as $c ) {
			if ( XML_ELEMENT_NODE !== $c->nodeType ) { continue; }
			$n = $c->getElementsByTagName( '*' )->length + 1;
			$label = $c->nodeName . ( $c->getAttribute( 'id' ) ? '#' . $c->getAttribute( 'id' ) : '' ) . ( $c->getAttribute( 'class' ) ? '.' . preg_replace( '/\s+/', '.', trim( $c->getAttribute( 'class' ) ) ) : '' );
			$r['sections'][] = array( mb_substr( $label, 0, 70 ), $n );
		}
		usort( $r['sections'], function ( $a, $b ) { return $b[1] <=> $a[1]; } );
		$r['sections'] = array_slice( $r['sections'], 0, 12 );
	}
	return $r;
}

add_action( 'admin_menu', function () {
	add_management_page( 'فحص DOM والحجم', 'فحص DOM والحجم (زاد)', 'manage_options', 'zad-domaudit', 'zad_domaudit_page' );
} );

function zad_domaudit_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$url = isset( $_POST['u'] ) ? esc_url_raw( wp_unslash( $_POST['u'] ) ) : ''; // phpcs:ignore
	echo '<div class="wrap" dir="rtl"><h1>فحص DOM والحجم</h1><p>يجلب صفحة من موقعك ويقيس: حجم HTML (وبعد gzip)، عدد عناصر DOM، عدد SVG، وزن الـ CSS/JS المضمَّن، وأثقل أقسام الصفحة. شغّله قبل التغيير وبعده للمقارنة.</p>';
	echo '<form method="post">'; wp_nonce_field( 'zad_dom' );
	echo '<p><input type="url" name="u" dir="ltr" class="regular-text" style="width:520px" value="' . esc_attr( $url ?: home_url( '/pest-control/riyadh/cockroach-control/' ) ) . '"> <button class="button button-primary">قياس</button></p></form>';
	if ( $url && check_admin_referer( 'zad_dom' ) ) {
		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) { echo '<div class="notice notice-error"><p>رابط من هذا الموقع فقط.</p></div></div>'; return; }
		$res = wp_remote_get( $url, array( 'timeout' => 25, 'sslverify' => false, 'user-agent' => 'ZadDomAudit/1.0', 'headers' => array( 'Accept-Encoding' => 'identity' ) ) );
		if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) >= 400 ) { echo '<div class="notice notice-error"><p>تعذّر الجلب: ' . esc_html( is_wp_error( $res ) ? $res->get_error_message() : 'استجابة ' . wp_remote_retrieve_response_code( $res ) ) . '</p></div></div>'; return; }
		$m = zad_dom_measure( (string) wp_remote_retrieve_body( $res ) );
		echo '<table class="widefat striped" style="max-width:640px"><tbody>';
		foreach ( array( 'حجم HTML' => round( $m['bytes'] / 1024, 1 ) . ' KB', 'بعد gzip' => round( $m['gzip'] / 1024, 1 ) . ' KB', 'عناصر DOM' => number_format_i18n( $m['elements'] ), 'عناصر SVG' => $m['svg'] . ' (منها <use>: ' . $m['use'] . ')', 'CSS مضمَّن' => round( $m['style_bytes'] / 1024, 1 ) . ' KB', 'JS مضمَّن' => round( $m['script_inline'] / 1024, 1 ) . ' KB' ) as $k => $v ) { echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>'; }
		echo '</tbody></table>';
		if ( $m['styles'] ) { echo '<h2>وسوم style</h2><ul style="list-style:disc;padding-inline-start:22px">'; foreach ( $m['styles'] as $s ) { echo '<li dir="ltr" style="text-align:right">' . esc_html( $s[0] ) . ' — ' . round( $s[1] / 1024, 1 ) . ' KB</li>'; } echo '</ul>'; }
		if ( $m['sections'] ) { echo '<h2>أثقل الأقسام (عدد العناصر)</h2><table class="widefat striped" style="max-width:640px"><tbody>'; foreach ( $m['sections'] as $s ) { echo '<tr><td dir="ltr" style="text-align:right">' . esc_html( $s[0] ) . '</td><td><b>' . (int) $s[1] . '</b></td></tr>'; } echo '</tbody></table>'; }
	}
	echo '</div>';
}
