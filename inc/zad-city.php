<?php defined( 'ABSPATH' ) || exit;
/**
 * The virtual "service + city" pages were removed. This file keeps:
 *  - a 301 from the old flat URL  /{type base}/{service-slug}/{city}/  to the service page,
 *  - one rewrite flush when the theme version changes,
 *  - the link rules for coverage chips (real hierarchical page, else open area archive, else plain text).
 */

/** "Name without city": existing _zad_base_name data if present, else the title (field no longer editable). */
function zad_service_base_name( $service_id ) {
	$b = get_post_meta( $service_id, '_zad_base_name', true );
	return $b ? $b : get_the_title( $service_id );
}

/** One-time rewrite flush after the theme update that removed the city rewrite rules. */
add_action( 'init', function () {
	if ( get_option( 'zad_rw_ver' ) !== ZAD_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'zad_rw_ver', ZAD_VERSION, false );
	}
}, 100 );

/**
 * 301 for the old virtual URL. Only when the service exists, its type is non-hierarchical and the
 * city is a top-level service_area term assigned to it. Real hierarchical pages never reach here (they resolve normally).
 */
add_action( 'template_redirect', function () {
	global $wp;
	if ( ! is_404() || ! isset( $wp->request ) ) { return; }
	$path = trim( (string) $wp->request, '/' );
	foreach ( zad_service_types() as $pt ) {
		if ( is_post_type_hierarchical( $pt ) ) { continue; }
		if ( ! preg_match( '#^' . preg_quote( zad_type_base( $pt ), '#' ) . '/([^/]+)/([^/]+)$#', $path, $m ) ) { continue; }
		$posts = get_posts( array( 'post_type' => $pt, 'name' => sanitize_title( $m[1] ), 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => true ) );
		if ( ! $posts ) { continue; }
		foreach ( (array) get_the_terms( $posts[0]->ID, 'service_area' ) as $t ) {
			if ( $t instanceof WP_Term && 0 === (int) $t->parent && $t->slug === $m[2] ) {
				wp_safe_redirect( get_permalink( $posts[0] ), 301 );
				exit;
			}
		}
	}
}, 1 );

/** Published descendant page of a hierarchical service that matches an area term (city: child, hood: grandchild). */
function zad_area_page_url( $service_id, $term ) {
	if ( ! is_post_type_hierarchical( get_post_type( $service_id ) ) ) { return ''; }
	$kids = get_posts( array( 'post_type' => get_post_type( $service_id ), 'post_status' => 'publish', 'post_parent' => $service_id, 'numberposts' => -1, 'suppress_filters' => true ) );
	$match = function ( $posts, $t ) {
		foreach ( $posts as $p ) {
			if ( $p->post_name === $t->slug || trim( $p->post_title ) === $t->name ) { return $p; }
		}
		return null;
	};
	if ( 0 === (int) $term->parent ) {
		$p = $match( $kids, $term );
		return $p ? get_permalink( $p ) : '';
	}
	$parent_term = get_term( (int) $term->parent, $term->taxonomy );
	$city_page   = ( $parent_term && ! is_wp_error( $parent_term ) ) ? $match( $kids, $parent_term ) : null;
	if ( ! $city_page ) { return ''; }
	$hoods = get_posts( array( 'post_type' => $city_page->post_type, 'post_status' => 'publish', 'post_parent' => $city_page->ID, 'numberposts' => -1, 'suppress_filters' => true ) );
	$p = $match( $hoods, $term );
	return $p ? get_permalink( $p ) : '';
}

/** Is the area term's archive open to Google (exists, not noindexed by the theme or by Yoast)? */
function zad_area_term_open( $term ) {
	if ( ! $term || is_wp_error( $term ) ) { return false; }
	if ( function_exists( 'zad_term_noindexed' ) && zad_term_noindexed( $term ) ) { return false; }
	if ( 'yoast' === zad_seo_mode() ) {
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) && 'noindex' === WPSEO_Taxonomy_Meta::get_term_meta( $term, $term->taxonomy, 'noindex' ) ) { return false; }
		$ti = get_option( 'wpseo_titles' );
		if ( is_array( $ti ) && ! empty( $ti[ 'noindex-tax-' . $term->taxonomy ] ) ) { return false; }
	}
	return ! is_wp_error( get_term_link( $term ) );
}

/** Link for an area chip: real page → open area archive → '' (plain text). */
function zad_area_link( $service_id, $term ) {
	$u = zad_area_page_url( $service_id, $term );
	if ( $u ) { return $u; }
	return zad_area_term_open( $term ) ? (string) get_term_link( $term ) : '';
}
