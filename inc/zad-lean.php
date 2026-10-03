<?php
/**
 * Lean front end: drops core assets a page does not need. Everything here is conditional and reversible:
 * - wp-block-library CSS is removed only on pages whose content uses none of the blocks that need it.
 * - wp-embed.js is removed unless the page embeds another WordPress post.
 * - harmless head clutter (rsd, wlwmanifest, shortlink) is removed; REST API, feeds and xmlrpc are NOT touched.
 * Master switch: theme option zad_lean (default on).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_lean_on() { return (bool) zad_opt( 'zad_lean', true ); }

/** Blocks/classes that really need the core block stylesheet. */
function zad_lean_needs_blocks( $html ) {
	return (bool) preg_match( '/wp-block-(columns?|group|cover|gallery|buttons?|media-text|image[^"]*\b(alignwide|alignfull)|embed|video|audio|file|table|pullquote|verse|code|latest|search|social|spacer|details)|\balign(wide|full)\b|is-layout-|wp-block-latest/i', $html );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! zad_lean_on() || is_admin() ) { return; }
	$html = '';
	if ( is_singular() ) { $html = (string) get_post_field( 'post_content', get_queried_object_id() ); }
	if ( ! $html || ! zad_lean_needs_blocks( $html ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'wc-blocks-style' );
	}
	if ( false === strpos( $html, 'wp-block-embed-wordpress' ) && false === strpos( $html, 'wp-embedded-content' ) ) {
		wp_deregister_script( 'wp-embed' );
	}
}, 100 );

add_action( 'init', function () {
	if ( ! zad_lean_on() ) { return; }
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
} );
