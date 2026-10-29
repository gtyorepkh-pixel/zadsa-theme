<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 15a. Related detailed questions (right after the page's own FAQ; before «related services» when there is none) -->
	<?php $qn = zad_qnet_html( $id ); if ( $faq ) { echo $qn; } // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<!-- 15b. Guides (hand-picked / strict match) -->
	<?php echo zad_guides_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<?php echo function_exists( 'zad_bridges_html' ) ? zad_bridges_html( $id ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php if ( ! $faq ) { echo $qn; } // phpcs:ignore WordPress.Security.EscapeOutput ?>