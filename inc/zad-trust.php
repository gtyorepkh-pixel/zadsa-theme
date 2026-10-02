<?php defined( 'ABSPATH' ) || exit;
/** Trust helpers: Google rating strip (shown only when real values are filled in the theme options). */

function zad_rating_strip() {
	$r = (float) str_replace( ',', '.', (string) zad_opt( 'zad_g_rating', '' ) );
	$n = (int) zad_opt( 'zad_g_count', 0 );
	$u = zad_opt( 'zad_g_url', '' );
	if ( $r <= 0 || $r > 5 || $n < 1 ) {
		return '';
	}
	return '<div class="gstrip"><div class="wrap gstrip__in"><span class="gstrip__stars" aria-hidden="true">★★★★★</span><b>' . esc_html( number_format_i18n( $r, 1 ) ) . '</b><span>على خرائط جوجل — من ' . (int) $n . ' ' . ( $n > 10 ? 'مراجعة' : ( $n > 2 ? 'مراجعات' : 'مراجعة' ) ) . '</span>' . ( $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">اقرأ المراجعات ←</a>' : '' ) . '</div></div>';
}
