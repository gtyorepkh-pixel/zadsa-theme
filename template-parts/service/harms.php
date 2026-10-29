<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 11c. Harms of postponing (own section file so the new layout can place it under the signs) -->
	<?php if ( $harms ) : ?>
	<section class="sec <?php echo ( isset( $zad_layout ) && 'new' === $zad_layout ) ? 'sec--tint' : 'sec--cream'; ?>"><div class="wrap">
		<?php echo zad_sec_head( $id, 'harms' ); // phpcs:ignore ?>
		<div class="cardgrid">
			<?php foreach ( $harms as $w ) : ?><div class="icard icard--warn"><span class="icard__ic"><?php echo zad_icon( 'bolt', 24 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
