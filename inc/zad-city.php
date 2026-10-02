<?php defined( 'ABSPATH' ) || exit;
/**
 * Service + city landing pages:  /services/{service}/{city}/
 * Virtual pages built from the service data; valid only for cities assigned to the service.
 */

add_filter( 'query_vars', function ( $v ) {
	$v[] = 'zad_city';
	return $v;
} );

add_action( 'init', function () {
	foreach ( zad_service_types() as $pt ) {
		$base = zad_type_base( $pt );
		add_rewrite_rule( '^' . preg_quote( $base, '#' ) . '/([^/]+)/(?!attachment|feed|embed|page|trackback|comment-page)([^/]+)/?$', 'index.php?post_type=' . $pt . '&name=$matches[1]&zad_city=$matches[2]', 'top' );
	}
}, 60 );

/** Current city term for a city landing page, or null. */
function zad_current_city() {
	static $cache = array();
	$slug = get_query_var( 'zad_city' );
	if ( ! $slug || ! zad_is_service() ) {
		return null;
	}
	$id = get_queried_object_id();
	if ( array_key_exists( $id . $slug, $cache ) ) {
		return $cache[ $id . $slug ];
	}
	$term = null;
	foreach ( (array) get_the_terms( $id, 'service_area' ) as $t ) {
		if ( $t instanceof WP_Term && $t->slug === $slug && 0 === (int) $t->parent ) {
			$term = $t;
		}
	}
	$cache[ $id . $slug ] = $term;
	return $term;
}

/** 404 when the city does not belong to the service. */
add_action( 'template_redirect', function () {
	if ( get_query_var( 'zad_city' ) && zad_is_service() && ! zad_current_city() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}, 1 );

function zad_city_url( $service_id, $term ) {
	return trailingslashit( get_permalink( $service_id ) ) . $term->slug . '/';
}

/** "Name without city" for H1 composition: meta, else the title. */
function zad_service_base_name( $service_id ) {
	$b = get_post_meta( $service_id, '_zad_base_name', true );
	return $b ? $b : get_the_title( $service_id );
}

function zad_city_title( $service_id, $term ) {
	$base = zad_service_base_name( $service_id );
	return $base . ' في ' . $term->name;
}

/** Unique intro text for a city page (custom lines "city-slug | text", else generated). */
function zad_city_text( $service_id, $term ) {
	foreach ( zad_lines( get_post_meta( $service_id, '_zad_city_text', true ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l, 2 ) );
		if ( 2 === count( $c ) && ( $c[0] === $term->slug || $c[0] === $term->name ) ) {
			return $c[1];
		}
	}
	$districts = get_terms( array( 'taxonomy' => 'service_area', 'parent' => $term->term_id, 'hide_empty' => false, 'number' => 8 ) );
	$d = ( $districts && ! is_wp_error( $districts ) ) ? ' ونصل إلى أحياء مثل ' . implode( '، ', wp_list_pluck( $districts, 'name' ) ) . ' وغيرها.' : '';
	return sprintf( 'نقدّم %1$s في %2$s بفنيين معتمدين ومعاينة مجانية وسعر واضح قبل البدء.%3$s اطلب الخدمة الآن وسنحدد لك موعداً مناسباً في %2$s.', zad_service_base_name( $service_id ), $term->name, $d );
}
