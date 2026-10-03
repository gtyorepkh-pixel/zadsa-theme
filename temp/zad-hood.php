<?php
/**
 * Template Name: صفحة حي
 * Template Post Type: page
 */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) {
	the_post();
	zad_hood_render( get_the_ID() );
}
get_footer();
