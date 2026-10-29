<?php defined( 'ABSPATH' ) || exit; ?>

	<?php if ( $gallery ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<?php echo zad_sec_head( $id, 'gallery' ); // phpcs:ignore ?>
		<div class="gal"><?php foreach ( $gallery as $gid ) : ?><a href="<?php echo esc_url( wp_get_attachment_image_url( $gid, 'full' ) ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $gid, 'medium', false, array( 'loading' => 'lazy' ) ); ?></a><?php endforeach; ?></div>
	</div></section>
	<?php endif; ?>
