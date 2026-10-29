<?php defined( 'ABSPATH' ) || exit; ?>

	<?php if ( zad_ix_enabled( $id ) ) : ?>
		<!-- Interactive modules only (new layout: the text that follows the "Read more" tag is ix-after.php) -->
		<?php echo zad_ix_render( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php endif; ?>
