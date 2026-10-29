<?php defined( 'ABSPATH' ) || exit; ?>

	<?php if ( zad_ix_enabled( $id ) && isset( $ix_after ) && '' !== trim( wp_strip_all_tags( $ix_after ) ) ) : ?>
	<!-- Content that follows the "Read more" tag (new layout: right after the intro) -->
	<section class="sec"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo $ix_after; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div></section>
	<?php endif; ?>
