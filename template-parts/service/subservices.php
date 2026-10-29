<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 6. Sub-services -->
	<?php if ( $subs ) : ?>
	<section class="sec sec--mint"><div class="wrap">
		<?php echo zad_sec_head( $id, 'subs' ); // phpcs:ignore ?>
		<div class="cardgrid cardgrid--4">
			<?php foreach ( $subs as $w ) : ?>
				<div class="icard icard--line"><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p>
				<?php $wa_at = zad_wa_attrs( 'مرحباً، أرغب بخدمة: ' . $w['t'], $id ); if ( $wa_at ) : ?><button type="button" class="more"<?php echo $wa_at; // phpcs:ignore ?>>اطلب الآن <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></button><?php endif; ?></div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
