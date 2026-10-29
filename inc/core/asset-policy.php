<?php defined( 'ABSPATH' ) || exit;
/** Existing optional asset policy and versioning. */
/** Asset version = the file's own modified time (changes on every upload, so caches/CDNs/browsers refetch); falls back to ZAD_VERSION. */
function zad_asset_ver( $rel ) {
	$f = get_template_directory() . '/' . ltrim( $rel, '/' );
	$t = is_readable( $f ) ? filemtime( $f ) : false;
	return $t ? (string) $t : ZAD_VERSION;
}

/* ================= (2) Lean-up (OFF by default) ================= */
function zad_lean_needs_blocks( $html ) {
	return (bool) preg_match( '/wp-block-(columns?|group|cover|gallery|buttons?|media-text|embed|video|audio|file|table|pullquote|verse|code|latest|search|social|spacer|details)|\balign(wide|full)\b|is-layout-/i', $html );
}

// Enqueue policy is registered by inc/core/assets.php.


/* ================= (3) ?ver= unification (OFF by default) ================= */
function zad_ver_filter( $src ) {
	$mode = zad_opt( 'zad_ver_mode', 'off' );
	if ( 'off' === $mode || false === strpos( $src, 'ver=' ) || 0 !== strpos( $src, home_url() ) && 0 !== strpos( $src, '/' ) ) { return $src; }
	$src = remove_query_arg( 'ver', $src );
	return 'theme' === $mode ? add_query_arg( 'ver', defined( 'ZAD_VERSION' ) ? ZAD_VERSION : '1', $src ) : $src;
}
add_filter( 'style_loader_src', 'zad_ver_filter', 99 );
add_filter( 'script_loader_src', 'zad_ver_filter', 99 );
