<?php defined( 'ABSPATH' ) || exit;
/** args: title, sub, crumbs (array) */
$title = $args['title'] ?? get_the_title();
?>
<section class="phero">
	<div class="wrap">
		<?php if ( ! empty( $args['crumbs'] ) ) { zad_render_crumbs( $args['crumbs'] ); } ?>
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( ! empty( $args['sub'] ) ) : ?><div class="phero__sub"><?php echo wp_kses_post( $args['sub'] ); ?></div><?php endif; ?>
	</div>
</section>
