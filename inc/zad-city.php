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

/** Is the area term's archive open to Google (exists, not noindexed by the theme)? */
function zad_area_term_open( $term ) {
	if ( ! $term || is_wp_error( $term ) ) { return false; }
	if ( function_exists( 'zad_term_noindexed' ) && zad_term_noindexed( $term ) ) { return false; }
	return ! is_wp_error( get_term_link( $term ) );
}

/** Link for an area chip: real page → open area archive → '' (plain text). */
function zad_area_link( $service_id, $term ) {
	$u = zad_area_page_url( $service_id, $term );
	if ( $u ) { return $u; }
	return zad_area_term_open( $term ) ? (string) get_term_link( $term ) : '';
}


/* ---------------- the city of a service page ---------------- */

/**
 * The city registry: one row per city we serve, and the single source for every place-dependent value
 * (page meta, schema, geo tags, the booking form and the local phone number).
 *
 * name   Arabic name as it is written on the pages.
 * lat/lng city centre, for geo.position and ICBM. A page outside any city falls back to the company's own.
 * region  administrative region, for geo.region (ISO 3166-2:SA).
 * phone   the local number answered for that city; empty means the company-wide number.
 * parent  slug of the governorate a town belongs to, so a town inherits its region's links and pricing.
 *
 * Extend with the filter `zad_city_data`.
 */
function zad_city_data() {
	static $rows = null;
	if ( null === $rows ) {
		$rows = apply_filters( 'zad_city_data', array(
			'riyadh'    => array( 'name' => 'الرياض',        'lat' => '24.7136', 'lng' => '46.6753', 'region' => 'SA-01', 'phone' => '' ),
			'kharj'     => array( 'name' => 'الخرج',          'lat' => '24.1554', 'lng' => '47.3346', 'region' => 'SA-01', 'phone' => '', 'parent' => 'riyadh' ),
			'jeddah'    => array( 'name' => 'جدة',            'lat' => '21.4858', 'lng' => '39.1925', 'region' => 'SA-02', 'phone' => '' ),
			'makkah'    => array( 'name' => 'مكة',            'lat' => '21.3891', 'lng' => '39.8579', 'region' => 'SA-02', 'phone' => '' ),
			'dammam'    => array( 'name' => 'الدمام',         'lat' => '26.4207', 'lng' => '50.0888', 'region' => 'SA-04', 'phone' => '' ),
			'qatif'     => array( 'name' => 'القطيف',         'lat' => '26.5196', 'lng' => '49.9962', 'region' => 'SA-04', 'phone' => '' ),
			'al-ahsa'   => array( 'name' => 'الأحساء',        'lat' => '25.3647', 'lng' => '49.5870', 'region' => 'SA-04', 'phone' => '' ),
			'qassim'    => array( 'name' => 'القصيم',         'lat' => '26.3260', 'lng' => '43.9750', 'region' => 'SA-05', 'phone' => '0545912579' ),
			'buraydah'  => array( 'name' => 'بريدة',          'lat' => '26.3260', 'lng' => '43.9750', 'region' => 'SA-05', 'phone' => '0545912579', 'parent' => 'qassim' ),
			'unaizah'   => array( 'name' => 'عنيزة',          'lat' => '26.0840', 'lng' => '43.9930', 'region' => 'SA-05', 'phone' => '0545912579', 'parent' => 'qassim' ),
			'najran'    => array( 'name' => 'نجران',          'lat' => '17.4924', 'lng' => '44.1277', 'region' => 'SA-10', 'phone' => '0500403084' ),
		) );
	}
	return $rows;
}

/** One city's row, by slug or by Arabic name. Empty array when the place is not in the registry. */
function zad_city_row( $city ) {
	$rows = zad_city_data();
	$key  = strtolower( trim( urldecode( (string) $city ), " \t\n\r/" ) );
	if ( isset( $rows[ $key ] ) ) { return $rows[ $key ] + array( 'slug' => $key ); }
	foreach ( $rows as $slug => $row ) {
		if ( $row['name'] === trim( (string) $city ) ) { return $row + array( 'slug' => $slug ); }
	}
	return array();
}

/** slug => Arabic name, derived from the registry (extend with the filter `zad_city_map`). */
function zad_city_map() {
	return apply_filters( 'zad_city_map', wp_list_pluck( zad_city_data(), 'name' ) );
}

/**
 * City of a service page, in this order:
 *  1. the page's own meta _zad_city (a slug such as jeddah, or an Arabic name);
 *  2. its ancestors, nearest first: an ancestor's _zad_city meta, else an ancestor whose slug is a known city (riyadh → الرياض …).
 *     Works for drafts and previews, which carry post_parent but no city in their URL;
 *  3. the permalink path (/pest-control/<city>/… or /cleaning/<…>/<city>/) and the page's own slug (the city page itself);
 *  4. the default: the site's city option, else «الرياض».
 * Returns array( 'name' => Arabic name, 'slug' => slug, 'source' => 'meta'|'ancestor'|'url'|'default' ).
 */
function zad_current_city( $post_id = 0 ) {
	static $memo = array();
	$post_id = (int) ( $post_id ? $post_id : get_the_ID() );
	if ( isset( $memo[ $post_id ] ) ) { return $memo[ $post_id ]; }
	$map  = zad_city_map();
	$norm = function ( $v ) { return strtolower( trim( urldecode( (string) $v ), " \t\n\r/" ) ); };
	$make = function ( $val, $source ) use ( $map, $norm ) { // $val: a slug or an Arabic name
		$k = $norm( $val );
		if ( isset( $map[ $k ] ) ) { return array( 'name' => $map[ $k ], 'slug' => $k, 'source' => $source ); }
		$slug = array_search( trim( (string) $val ), $map, true );
		return array( 'name' => trim( (string) $val ), 'slug' => false !== $slug ? $slug : sanitize_title( (string) $val ), 'source' => $source );
	};
	$found = null;
	if ( $post_id ) {
		$own = trim( (string) get_post_meta( $post_id, '_zad_city', true ) );
		if ( '' !== $own && 'all' !== strtolower( $own ) ) { $found = $make( $own, 'meta' ); } // «all» (every city) is not a city name: discovery continues
		if ( ! $found ) {
			foreach ( (array) get_post_ancestors( $post_id ) as $aid ) { // nearest parent first
				$m = trim( (string) get_post_meta( $aid, '_zad_city', true ) );
				if ( '' !== $m && 'all' !== strtolower( $m ) ) { $found = $make( $m, 'ancestor' ); break; }
				$p = get_post( $aid );
				if ( $p && isset( $map[ $norm( $p->post_name ) ] ) ) { $found = $make( $p->post_name, 'ancestor' ); break; }
			}
		}
		if ( ! $found ) {
			$segs = array_filter( explode( '/', (string) wp_parse_url( (string) get_permalink( $post_id ), PHP_URL_PATH ) ) );
			$p    = get_post( $post_id );
			if ( $p ) { $segs[] = $p->post_name; }
			foreach ( $segs as $seg ) { if ( isset( $map[ $norm( $seg ) ] ) ) { $found = $make( $seg, 'url' ); break; } }
		}
	}
	if ( ! $found ) {
		$def   = trim( (string) zad_opt( 'zad_city_name', '' ) );
		$found = $make( '' !== $def ? $def : 'الرياض', 'default' );
	}
	return $memo[ $post_id ] = $found;
}
