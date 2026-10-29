<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 2. Stats strip -->
	<?php if ( $stats ) : ?>
	<section class="stats stats--svc"><div class="wrap stats__grid">
		<?php foreach ( $stats as $s ) : ?><div class="stat"><b><?php echo esc_html( $s['n'] ); ?></b><span><?php echo esc_html( $s['l'] ); ?></span></div><?php endforeach; ?>
	</div></section>
	<?php endif; ?>

	<?php echo zad_rating_strip(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
