<?php defined( 'ABSPATH' ) || exit;
/** Saved FAQ conversion compatibility only; no conversion, undo or tool UI. */
function zad_legacy_faq_retired() { return (bool) get_option( 'zad_faqconv_retire', false ); }

add_action( 'init', function () {
 if ( ! zad_legacy_faq_retired() ) { return; }
 if ( post_type_exists( 'faq' ) ) { unregister_post_type( 'faq' ); }
 if ( taxonomy_exists( 'best_faqs' ) ) { unregister_taxonomy( 'best_faqs' ); }
}, 60 );

/** Resolve only previously saved destinations; never create or alter migration data. */
function zad_legacy_faq_redirect_url( $request ) {
 $map = get_option( 'zad_faqconv_map' );
 if ( ! is_array( $map ) || ! $map ) { return ''; }
 $path = mb_strtolower( urldecode( trim( (string) $request, '/' ) ) );
 $path = preg_replace( '#/(?:page/\d+|feed(?:/[a-z0-9]+)?)$#i', '', $path );
 if ( ! isset( $map[ $path ] ) || ! is_array( $map[ $path ] ) ) { return ''; }
 $to = $map[ $path ];
 if ( isset( $to['t'] ) ) {
  $term = get_term( (int) $to['t'], 'faq_cat' );
  $url = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : '';
 } else {
  $url = home_url( '/' . ltrim( (string) ( $to['u'] ?? '' ), '/' ) );
 }
 return ! is_wp_error( $url ) && $url ? $url : '';
}

add_action( 'template_redirect', function () {
 global $wp;
 if ( ! is_404() || ! isset( $wp->request ) ) { return; }
 $url = zad_legacy_faq_redirect_url( $wp->request );
 if ( $url && wp_safe_redirect( $url, 301 ) ) { exit; }
}, 1 );

if ( is_admin() ) {
/* ---------------- admin: review column for auto-filled excerpts ---------------- */
add_filter( 'manage_zad_faq_posts_columns', function ( $c ) { $c['zad_autoex'] = 'مقتطف تلقائي'; return $c; } );
add_action( 'manage_zad_faq_posts_custom_column', function ( $col, $id ) {
	if ( 'zad_autoex' === $col && get_post_meta( $id, '_zad_faq_auto_excerpt', true ) ) { echo '<span style="color:#b45309" title="مُلئ تلقائياً من أول فقرة — راجعه">⚠ يحتاج مراجعة</span>'; }
}, 10, 2 );
add_action( 'save_post_zad_faq', function ( $id ) { // reviewing = editing the excerpt: the flag clears itself
	$auto = get_post_meta( $id, '_zad_faq_auto_excerpt', true );
	if ( $auto && get_post_field( 'post_excerpt', $id ) !== $auto ) { delete_post_meta( $id, '_zad_faq_auto_excerpt' ); }
} );

}
