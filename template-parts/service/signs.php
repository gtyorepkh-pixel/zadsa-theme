<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 11b. Signs + harms -->
	<?php if ( $signs ) : ?>
	<section class="sec"><div class="wrap">
		<?php echo zad_sec_head( $id, 'signs' ); // phpcs:ignore ?>
		<div class="cardgrid cardgrid--2">
			<?php foreach ( $signs as $w ) : ?><div class="icard icard--row"><span class="icard__ic"><?php echo zad_icon( 'search', 24 ); // phpcs:ignore ?></span><div><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
	<?php if ( $harms ) : ?>
	<section class="sec sec--cream"><div class="wrap">
		<?php echo zad_sec_head( $id, 'harms' ); // phpcs:ignore ?>
		<div class="cardgrid">
			<?php foreach ( $harms as $w ) : ?><div class="icard icard--warn"><span class="icard__ic"><?php echo zad_icon( 'bolt', 24 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
