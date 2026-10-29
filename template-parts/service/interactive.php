<?php defined( 'ABSPATH' ) || exit; ?>

	<?php if ( zad_ix_enabled( $id ) ) : ?>
		<!-- 3b. Interactive modules (optional) + content that follows the "Read more" tag -->
		<?php echo zad_ix_render( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( '' !== trim( wp_strip_all_tags( $ix_after ) ) ) : ?>
		<section class="sec"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo $ix_after; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div></section>
		<?php endif; ?>
	<?php endif; ?>
