<?php defined( 'ABSPATH' ) || exit; ?>

	<?php echo zad_coverage_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<?php if ( 'dx' !== $hc ) { echo zad_dx_section( $id, 'sec-b' ); } // phpcs:ignore ?>
