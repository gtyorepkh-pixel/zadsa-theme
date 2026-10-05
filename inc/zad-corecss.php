<?php
/**
 * Front-end: drop WordPress's inline CSS (global-styles, classic-theme-styles, wp-block-library and the per-block inline styles)
 * and ship the few block rules the content needs in the cached theme stylesheet (assets/css/zad.css, "Core block rules").
 * One switch (default ON): Theme options → «تقليل CSS ووردبريس». Admin and the block editor are never touched.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_strip_wp_css_on() { return ! is_admin() && (bool) zad_opt( 'zad_strip_wp_css', true ); }

/* core registers these on the default priority; remove them before they run */
add_action( 'init', function () {
	if ( ! zad_strip_wp_css_on() ) { return; }
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles_css_custom_properties' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
	remove_action( 'wp_head', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
}, 30 );

/** Block styles enqueued while the content renders are printed in the footer: dequeue at every stage. */
function zad_dequeue_core_css() {
	if ( ! zad_strip_wp_css_on() ) { return; }
	$st = wp_styles();
	foreach ( array_unique( array_merge( (array) $st->queue, array_keys( (array) $st->registered ) ) ) as $h ) {
		if ( in_array( $h, array( 'global-styles', 'classic-theme-styles', 'wp-block-library', 'wp-block-library-theme' ), true ) || 0 === strpos( $h, 'wp-block-' ) ) { wp_dequeue_style( $h ); }
	}
}
add_action( 'wp_enqueue_scripts', 'zad_dequeue_core_css', 100 );
add_action( 'wp_print_styles', 'zad_dequeue_core_css', 100 );
add_action( 'wp_footer', 'zad_dequeue_core_css', 1 );
