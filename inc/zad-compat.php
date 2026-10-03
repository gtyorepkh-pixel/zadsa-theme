<?php
/**
 * Content compatibility: pages written with other plugins keep rendering cleanly in the theme.
 * - "WP Schema Pro / SEO FAQ" blocks (wpsp/faq) become the theme's own accordion (no plugin CSS needed),
 *   and their FAQPage schema is printed once, cleanly, by the theme.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** Find balanced <div class="wp-block-wpsp-faq ..."> ... </div> blocks. Returns array of array( start, length ). */
function zad_wpsp_blocks( $html ) {
	$out = array(); $off = 0;
	while ( preg_match( '/<div[^>]*class="[^"]*wp-block-wpsp-faq(?!-child)[^"]*"[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE, $off ) ) {
		$start = $m[0][1]; $depth = 0; $pos = $start; $end = false;
		while ( preg_match( '#<(/?)div\b#i', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
			$depth += $t[1][0] ? -1 : 1;
			$pos    = $t[0][1] + 4;
			if ( 0 === $depth ) { $end = strpos( $html, '>', $t[0][1] ) + 1; break; }
		}
		if ( false === $end ) { break; }
		$out[] = array( $start, $end - $start );
		$off   = $end;
	}
	return $out;
}

function zad_wpsp_pairs( $html ) {
	$pairs = array();
	foreach ( zad_wpsp_blocks( $html ) as $b ) {
		$blk = substr( $html, $b[0], $b[1] );
		if ( preg_match_all( '#class="wpsp-question"[^>]*>(.*?)</h[1-6]>.*?class="wpsp-faq-content"[^>]*>\s*(?:<span>)?(.*?)(?:</span>)?\s*</div>#su', $blk, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$q = trim( wp_strip_all_tags( $x[1] ) );
				$a = trim( $x[2] );
				if ( '' !== $q && '' !== wp_strip_all_tags( $a ) ) { $pairs[] = array( 'q' => $q, 'a' => $a ); }
			}
		}
	}
	return $pairs;
}

add_filter( 'the_content', function ( $html ) {
	if ( false === strpos( $html, 'wp-block-wpsp-faq' ) ) { return $html; }
	$blocks = zad_wpsp_blocks( $html );
	for ( $i = count( $blocks ) - 1; $i >= 0; $i-- ) {
		$blk   = substr( $html, $blocks[ $i ][0], $blocks[ $i ][1] );
		$pairs = zad_wpsp_pairs( $blk );
		if ( ! $pairs ) { continue; }
		ob_start(); zad_render_faq( $pairs ); $new = ob_get_clean();
		$html = substr_replace( $html, $new, $blocks[ $i ][0], $blocks[ $i ][1] );
	}
	return $html;
}, 8 );

add_action( 'wp_head', function () {
	if ( ! is_singular() || 'theme' !== zad_schema_owner() ) { return; }
	$id = get_queried_object_id();
	$has_own = function_exists( 'zad_hood_active' ) && zad_hood_active( $id )
		? array_filter( (array) get_post_meta( $id, '_zad_h_faq', true ), function ( $r ) { return ! empty( $r['q'] ); } )
		: ( zad_is_service() ? array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $f ) { return ! empty( $f['q'] ); } ) : array() );
	if ( $has_own ) { return; }
	$pairs = zad_wpsp_pairs( (string) get_post_field( 'post_content', $id ) );
	if ( ! $pairs ) { return; }
	$ents = array();
	foreach ( $pairs as $p ) { $ents[] = array( '@type' => 'Question', 'name' => $p['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( wp_strip_all_tags( $p['a'] ) ) ) ); }
	zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
}, 14 );
