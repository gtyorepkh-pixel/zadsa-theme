<?php defined( 'ABSPATH' ) || exit; ?>

	<?php if ( function_exists( 'zad_ix_enabled' ) && zad_ix_enabled( $id ) ) : ?>
		<!-- New layout: the interactive modules that are not the self-check (map, safety, story), printed once -->
		<?php echo zad_ix_render( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php endif; ?>
