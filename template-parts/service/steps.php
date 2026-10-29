<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 8. Execution steps (design chosen per page) -->
	<?php
	$zad_steps_html = zad_steps_html( $id, $steps );
	if ( isset( $zad_layout ) && 'new' === $zad_layout && $tools && '' !== $zad_steps_html ) { // the equipment section is not printed in the new layout: its titles sit under the steps instead
		$zad_tools_line = '<p class="hsteps__tools"><b>المعدات والمواد المستخدمة:</b> ' . esc_html( implode( ' · ', array_filter( wp_list_pluck( $tools, 't' ) ) ) ) . '</p>';
		$zad_steps_html = preg_replace( '#</ol></div></section>\s*$#', '</ol>' . $zad_tools_line . '</div></section>', $zad_steps_html, 1, $zad_n );
	}
	echo $zad_steps_html; // phpcs:ignore WordPress.Security.EscapeOutput
	?>
