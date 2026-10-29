<?php defined( 'ABSPATH' ) || exit; ?>
	<!-- 16. Related + final quote -->
	<?php
	$rel = zad_related_services( $id, 3 ); // 3 cards at most
	if ( $rel->have_posts() ) : ?>
	<section class="sec sec--mint"><div class="wrap">
		<?php echo zad_sec_head( $id, 'related' ); // phpcs:ignore ?>
		<div class="sgrid"><?php while ( $rel->have_posts() ) { $rel->the_post(); $is_svc = in_array( get_post_type(), zad_service_types(), true ); get_template_part( 'template-parts/' . ( $is_svc ? 'service-card' : 'post-card' ), null, $is_svc ? array( 'lite' => true, 'anchor' => zad_anchor_for( get_the_ID(), $id ) ) : array( 'lite' => true ) ); } wp_reset_postdata(); ?></div>
	<?php if ( function_exists( 'zad_city_more_html' ) && ! get_post_meta( $id, '_zad_related', true ) ) { echo zad_city_more_html( $id, (int) $rel->post_count, 3 ); } // phpcs:ignore ?></div></section>
	<?php endif; ?>
