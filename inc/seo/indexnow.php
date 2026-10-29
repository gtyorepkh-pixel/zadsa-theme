<?php defined( 'ABSPATH' ) || exit;
/** Optional IndexNow dispatch; never enabled implicitly or sent from a private/local site. */
function zad_indexnow_key() {
 $key = get_option( 'zad_indexnow_key', '' );
 if ( ! $key && zad_opt( 'zad_indexnow_enabled', false ) ) { $key = wp_generate_password( 32, false, false ); update_option( 'zad_indexnow_key', $key, false ); }
 return $key;
}
add_action( 'parse_request', function () {
 $key = (string) get_option( 'zad_indexnow_key', '' );
 $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
 if ( $key && $path === $key . '.txt' && zad_opt( 'zad_indexnow_enabled', false ) && get_option( 'blog_public' ) ) { header( 'Content-Type: text/plain; charset=UTF-8' ); echo $key; exit; }
}, 0 );
add_action( 'save_post', function ( $id ) {
 if ( ! zad_opt( 'zad_indexnow_enabled', false ) || ! get_option( 'blog_public' ) || ! zad_seo_post_eligible( $id ) || wp_is_post_revision( $id ) ) { return; }
 $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
 if ( preg_match( '/(?:^localhost$|\.(?:local|test|localhost)$)/i', $host ) || filter_var( $host, FILTER_VALIDATE_IP ) ) { return; }
 $key = zad_indexnow_key();
 wp_remote_post( 'https://api.indexnow.org/indexnow', array( 'timeout' => 3, 'blocking' => false, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'host' => $host, 'key' => $key, 'keyLocation' => home_url( '/' . $key . '.txt' ), 'urlList' => array( get_permalink( $id ) ) ) ) ) );
}, 200 );
