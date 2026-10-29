<?php defined( 'ABSPATH' ) || exit;
/**
 * Old content shortcodes from the previous theme ([memo_qs_opt], [memo_relatedpost_opt] and their items). The tag names stay as they are because they
 * are written inside post content. Tools → «شورت كود ميمو القديمة» replaces them with this same HTML; after that this file can be removed.
 */
function zad_legacy_sc_grid( $atts, $content = null ) {
	return '<div class="flex space-between">' . do_shortcode( $content ) . '</div>';
}
function zad_legacy_sc_feature( $atts ) {
	$a = shortcode_atts( array( 'mqs_title' => '', 'mqs_img' => '', 'mqs_answer' => '' ), $atts );
	return '<div class="post-feature-box col-25"><img src="' . esc_html( $a['mqs_img'] ) . '" alt="' . esc_html( $a['mqs_title'] ) . '"><h3>' . esc_html( $a['mqs_title'] ) . '</h3><p>' . esc_html( $a['mqs_answer'] ) . '</p></div>';
}
function zad_legacy_sc_related( $atts ) {
	$a = shortcode_atts( array( 'relatedpost_textarea' => '', 'relatedpost_text' => '', 'relatedpost_link' => '', 'relatedpost_img' => '' ), $atts );
	return '<div class="post-feature-box col-25"><a href="' . esc_html( $a['relatedpost_link'] ) . '"><img src="' . esc_html( $a['relatedpost_img'] ) . '" alt="' . esc_html( $a['relatedpost_text'] ) . '"><h3>' . esc_html( $a['relatedpost_text'] ) . '</h3><p>' . esc_html( $a['relatedpost_textarea'] ) . '</p></a></div>';
}
add_shortcode( 'memo_qs_opt', 'zad_legacy_sc_grid' );
add_shortcode( 'memo_qs_opt_nested_foo', 'zad_legacy_sc_feature' );
add_shortcode( 'memo_relatedpost_opt', 'zad_legacy_sc_grid' );
add_shortcode( 'memo_relatedpost_opt_nested_foo', 'zad_legacy_sc_related' );
