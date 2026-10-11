<?php
/**
 * Smart cross-section links ("قد تحتاج أيضاً"). Off until the owner fills option zad_bridges:
 *   cleaning > pest-control      (one rule per line: from-section > to-sections)
 * On a page inside the "from" section it links to the main services of the "to" sections and,
 * for district pages, to the same district's pages in those sections (published ones only).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_bridge_rules() {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) zad_opt( 'zad_bridges', '' ) ) as $l ) {
		if ( false === strpos( $l, '>' ) ) { continue; }
		list( $from, $to ) = array_map( 'trim', explode( '>', $l, 2 ) );
		$to = array_values( array_filter( array_map( 'trim', explode( ',', $to ) ) ) );
		if ( $from && $to ) { $out[ trim( $from, '/' ) ] = array_map( function ( $x ) { return trim( $x, '/' ); }, $to ); }
	}
	return $out;
}

function zad_bridge_seg( $post ) {
	$p = wp_parse_url( get_permalink( $post ) );
	$seg = explode( '/', trim( rawurldecode( $p['path'] ?? '' ), '/' ) );
	return $seg[0] ?? '';
}

function zad_bridges_html( $id ) {
	$rules = zad_bridge_rules();
	if ( ! $rules ) { return ''; }
	$from = zad_bridge_seg( $id );
	if ( ! $from || empty( $rules[ $from ] ) ) { return ''; }
	$sc  = function_exists( 'zad_service_city_scope' ) ? zad_service_city_scope( $id ) : '';
	$key = 'zad_br_' . $id . '_' . (int) get_option( 'zad_hood_ver', 0 ) . ( function_exists( 'zad_city_key' ) ? '_' . zad_city_key( $id ) : '' ); // the page's city is part of the key
	$c   = get_transient( $key );
	if ( ! is_array( $c ) ) {
		$targets = $rules[ $from ]; $c = array( 'same' => array(), 'main' => array() );
		$ok = function ( $p ) use ( $sc ) { return ! function_exists( 'zad_city_allowed' ) || zad_city_allowed( $p->ID, $sc ); }; // same city (or «all») only
		$hood = (int) get_post_meta( $id, '_zad_h_hood', true );
		if ( $hood ) {
			foreach ( get_posts( array( 'post_type' => zad_hood_types(), 'post_status' => 'publish', 'numberposts' => 40, 'post__not_in' => array( $id ), 'suppress_filters' => true, 'zad_all' => true, 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_zad_h_hood', 'value' => $hood ), array( 'key' => '_zad_h_on', 'value' => '1' ) ) ) ) as $p ) {
				if ( in_array( zad_bridge_seg( $p ), $targets, true ) && $ok( $p ) ) { $c['same'][] = array( get_the_title( $p ), get_permalink( $p ) ); }
			}
		}
		// «خدماتنا الأخرى»: the main page of the SAME city in each target section (a city page, or a top-level page), never a page of another city
		$mq = array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 300, 'zad_all' => true, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
		if ( function_exists( 'zad_city_filter_args' ) ) { $mq = zad_city_filter_args( $mq, $sc ); }
		$cities = function_exists( 'zad_city_map' ) ? zad_city_map() : array();
		foreach ( get_posts( $mq ) as $p ) {
			if ( ! in_array( zad_bridge_seg( $p ), $targets, true ) ) { continue; }
			if ( 0 === (int) $p->post_parent || isset( $cities[ strtolower( urldecode( $p->post_name ) ) ] ) ) { $c['main'][] = array( get_the_title( $p ), get_permalink( $p ) ); }
		}
		$c['same'] = array_slice( $c['same'], 0, 6 ); $c['main'] = array_slice( $c['main'], 0, 8 );
		set_transient( $key, $c, 12 * HOUR_IN_SECONDS );
	}
	if ( ! $c['same'] && ! $c['main'] ) { return ''; }
	$o = '<section class="sec sec--tint zad-bridge"><div class="wrap"><header class="sec__head"><span class="eyebrow">خدمات أخرى</span><h2>قد تحتاج أيضاً</h2></header>';
	foreach ( array( 'same' => 'في نفس الحي', 'main' => 'خدماتنا الأخرى' ) as $k => $lbl ) {
		if ( ! $c[ $k ] ) { continue; }
		$o .= '<p class="hd-bl">' . esc_html( $lbl ) . '</p><ul class="hd-nb">';
		foreach ( $c[ $k ] as $r ) { $o .= '<li><a href="' . esc_url( $r[1] ) . '">' . esc_html( $r[0] ) . '</a></li>'; }
		$o .= '</ul>';
	}
	if ( function_exists( 'zad_city_more_html' ) ) { $o .= zad_city_more_html( $id, count( $c['main'] ), 8 ); }
	return $o . '</div></section>';
}
