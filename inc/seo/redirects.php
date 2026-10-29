<?php defined( 'ABSPATH' ) || exit;
/** 6.1 الراصد الذكي لتغيير الروابط (Auto-301 Redirect on Slug Change) */
add_action( 'post_updated', function ( $post_id, $post_after, $post_before ) {
	if ( wp_is_post_revision( $post_id ) || 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status ) {
		return;
	}
	if ( $post_before->post_name === $post_after->post_name && $post_before->post_parent === $post_after->post_parent ) {
		return;
	}
	$old_path = wp_parse_url( get_permalink( $post_before ), PHP_URL_PATH );
	$new_path = wp_parse_url( get_permalink( $post_after ), PHP_URL_PATH );
	if ( ! $old_path || ! $new_path || $old_path === $new_path ) {
		return;
	}
	$old_key = '/' . trim( rawurldecode( (string) $old_path ), '/' );
	$map     = get_option( 'zad_auto_redirects', array() );
	if ( ! is_array( $map ) ) { $map = array(); }
	$map[ $old_key ] = array(
		'to'   => (int) $post_id,
		'time' => time(),
	);
	// نحفظ آخر 500 تحويل تلقائي لمنع تضخم الخيار
	if ( count( $map ) > 500 ) {
		$map = array_slice( $map, -500, null, true );
	}
	update_option( 'zad_auto_redirects', $map, false );
}, 10, 3 );

add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$req_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	if ( ! $req_path ) { return; }
	$key = '/' . trim( rawurldecode( (string) $req_path ), '/' );
	$map = get_option( 'zad_auto_redirects', array() );
	if ( is_array( $map ) && isset( $map[ $key ]['to'] ) ) {
		$target_id = (int) $map[ $key ]['to'];
		if ( $target_id && 'publish' === get_post_status( $target_id ) ) {
			$target_url = get_permalink( $target_id );
			if ( $target_url && untrailingslashit( $target_url ) !== untrailingslashit( home_url( $req_path ) ) ) {
				wp_safe_redirect( $target_url, 301, 'Zad-Auto-301' );
				exit;
			}
		}
	}
}, 2 );


/** Saved work redirects survive retirement of the migration utility. */
add_action( 'template_redirect', function () {
 global $wp;
 if ( is_admin() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) || ! isset( $wp->request ) ) { return; }
 $map = get_option( 'zad_wm_map', array() );
 if ( ! is_array( $map ) || ! $map ) { return; }
 $key = mb_strtolower( rawurldecode( trim( (string) $wp->request, '/' ) ) );
 if ( ! isset( $map[ $key ] ) || ! is_string( $map[ $key ] ) ) { return; }
 $target = $map[ $key ];
 if ( wp_parse_url( $target, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) || untrailingslashit( $target ) === untrailingslashit( home_url( '/' . $wp->request ) ) ) { return; }
 if ( wp_safe_redirect( $target, 301, 'Zad-Works' ) ) { exit; }
}, 1 );
