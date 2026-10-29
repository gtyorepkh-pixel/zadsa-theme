<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 15. FAQ -->
	<?php if ( $faq ) : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<?php echo zad_sec_head( $id, 'faq' ); // phpcs:ignore ?>
		<?php zad_render_faq( $faq ); ?>
	</div></section>
	<?php endif; ?>
