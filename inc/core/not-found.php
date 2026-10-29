<?php
/**
 * 404 helpers: visitor suggestions and useful navigation. No request logging.
 * The theme never issues redirects: create them in your redirect plugin.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_404_norm( $url ) {
	$p = wp_parse_url( (string) $url );
	$path = rawurldecode( $p['path'] ?? '/' );
	return '/' . trim( mb_strtolower( $path, 'UTF-8' ), '/' ) . '/';
}

/** Similar published pages for a requested path (search by the words of its last segment). */
function zad_404_suggest( $path, $limit = 5 ) {
	$seg    = basename( trim( $path, '/' ) );
	$tokens = array_filter( preg_split( '/[-_\s+]+/u', $seg ), function ( $t ) { return mb_strlen( $t ) >= 3 && ! is_numeric( $t ); } );
	if ( ! $tokens ) { return array(); }
	$types = array_values( array_unique( array_merge( zad_service_types(), zad_faq_types(), array( 'page', 'post' ) ) ) );
	$q = new WP_Query( array( 's' => implode( ' ', array_slice( $tokens, 0, 4 ) ), 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'zad_all' => true ) );
	$out = array();
	foreach ( $q->posts as $p ) { $out[] = array( get_the_title( $p ), get_permalink( $p ), $p->ID ); }
	return $out;
}

/** Main services + a few district/city pages (cached) for the 404 template. */
function zad_404_lists() {
	$c = get_transient( 'zad_404_lists' );
	if ( is_array( $c ) ) { return $c; }
	$svc = array(); $kids = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_parent' => 0, 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $p ) {
		$svc[] = array( get_the_title( $p ), get_permalink( $p ) );
	}
	$ids = function_exists( 'zad_area_page_ids' ) ? array_slice( (array) zad_area_page_ids(), 0, 24 ) : array();
	if ( $ids ) {
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post__in' => $ids, 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => 'title', 'order' => 'ASC', 'zad_all' => true ) ) as $p ) { $kids[] = array( get_the_title( $p ), get_permalink( $p ) ); }
	}
	$c = array( 'svc' => $svc, 'kids' => $kids );
	set_transient( 'zad_404_lists', $c, 6 * HOUR_IN_SECONDS );
	return $c;
}
add_action( 'save_post', function () { delete_transient( 'zad_404_lists' ); } );

