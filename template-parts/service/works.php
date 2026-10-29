<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 14. Coverage -->
	<!-- 14b. field works: up to 3 works linked to this service (nothing when there are none) -->
	<?php echo function_exists( 'zad_works_section_html' ) ? zad_works_section_html( $id ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
