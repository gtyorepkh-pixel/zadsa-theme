<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id   = get_the_ID();
	if ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) { zad_hood_render( $id ); continue; }
	require_once ZAD_THEME_DIR . 'inc/core/service-view.php';
	zad_render_service( $id );
	echo "\n";

endwhile; get_footer();

