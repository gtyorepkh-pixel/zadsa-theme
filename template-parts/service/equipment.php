<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 7. Tools -->
	<?php if ( $tools ) : ?>
	<section class="sec sec--dark"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">أدواتنا ومعداتنا</span><h2>نعتمد على معدات ومواد حقيقية في كل زيارة</h2></header>
		<div class="cardgrid">
			<?php foreach ( $tools as $w ) : ?>
				<div class="icard icard--dark"><span class="icard__ic"><?php echo zad_icon( 'tool', 26 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
