<?php defined( 'ABSPATH' ) || exit; ?>

	<?php
	$zad_cov = zad_coverage_html( $id );
	if ( isset( $zad_layout ) && 'new' === $zad_layout ) { // the warranty tile repeats the hero and the price cards
		$zad_cov = preg_replace( '#<div class="cov__st"><b>[^<]*</b><span>[^<]*ضمان[^<]*</span></div>#u', '', $zad_cov );
	}
	echo $zad_cov; // phpcs:ignore WordPress.Security.EscapeOutput
	?>

	<?php if ( 'dx' !== $hc ) { echo zad_dx_section( $id, 'sec-b' ); } // phpcs:ignore ?>
