<?php defined( 'ABSPATH' ) || exit;
/** New layout: the page FAQ and the detailed-question links (qnet) in one section. */
$zad_qn = function_exists( 'zad_qnet_html' ) ? zad_qnet_html( $id ) : '';
$zad_qd = ''; $zad_qa = '';
if ( '' !== $zad_qn && preg_match( '#<div class="qnet">.*?</div>#s', $zad_qn, $zad_m ) ) {
	$zad_qd = $zad_m[0];
	if ( preg_match( '#<p class="qnet__all">.*?</p>#s', $zad_qn, $zad_m2 ) ) { $zad_qa = $zad_m2[0]; }
}
if ( $faq || $zad_qd ) : ?>
	<!-- FAQ + detailed questions (new layout) -->
	<section class="sec"><div class="wrap wrap--narrow">
		<?php if ( $faq ) : echo zad_sec_head( $id, 'faq' ); zad_render_faq( $faq ); endif; // phpcs:ignore ?>
		<?php if ( $zad_qd ) : ?><div class="need__grp"><header class="sec__head"><h3><?php echo esc_html( trim( (string) get_post_meta( $id, '_zad_qnet_title', true ) ) ?: 'أسئلة تفصيلية ذات صلة' ); ?></h3></header><?php echo $zad_qd . $zad_qa; // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
	</div></section>
<?php endif; ?>
