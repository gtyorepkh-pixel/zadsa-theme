<?php defined( 'ABSPATH' ) || exit;
get_header();
$zpt = zad_query_pt();
if ( is_string( $zpt ) && 'zad_service' !== $zpt ) {
	get_template_part( 'template-parts/hub-service' ); // adopted type: category hub / pillar page
} else {
	get_template_part( 'template-parts/service-archive' );
}
get_footer();
