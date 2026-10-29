<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 11b. Signs (harms live in harms.php) -->
	<?php if ( $signs ) : ?>
	<section class="sec"><div class="wrap">
		<?php echo zad_sec_head( $id, 'signs' ); // phpcs:ignore ?>
		<div class="cardgrid cardgrid--2">
			<?php foreach ( $signs as $w ) : ?><div class="icard icard--row"><span class="icard__ic"><?php echo zad_icon( 'search', 24 ); // phpcs:ignore ?></span><div><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
