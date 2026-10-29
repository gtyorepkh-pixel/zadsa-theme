<?php defined( 'ABSPATH' ) || exit;
/** 6.2 ترويسات HTTP Last-Modified & Link Canonical لتسريع زحف Googlebot */
add_action( 'send_headers', function () {
	if ( is_admin() || is_404() || is_search() || is_user_logged_in() || zad_seo_active() ) {
		return;
	}
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		if ( $post_id ) {
			$mod_gmt = get_post_modified_time( 'D, d M Y H:i:s', true, $post_id );
			if ( $mod_gmt ) {
				header( 'Last-Modified: ' . $mod_gmt . ' GMT' );
			}
			$canon = function_exists( 'zad_seo_canonical_url' ) ? zad_seo_canonical_url() : get_permalink( $post_id );
			if ( $canon ) {
				header( 'Link: <' . esc_url_raw( $canon ) . '>; rel="canonical"', false );
			}
		}
	}
} );

// Speakable is not automatically added to Arabic commercial service pages.

