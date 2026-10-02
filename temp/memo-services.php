<?php defined( 'ABSPATH' ) || exit; /* Template Name: Services */
get_header();
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$args  = array( 'post_type' => zad_service_types(), 'posts_per_page' => 12, 'paged' => $paged, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
if ( ! empty( $_GET['q'] ) ) { // phpcs:ignore
	$args['s'] = sanitize_text_field( wp_unslash( $_GET['q'] ) ); // phpcs:ignore
}
get_template_part( 'template-parts/service-archive', null, array( 'query' => new WP_Query( $args ), 'hub' => true ) );
get_footer();
