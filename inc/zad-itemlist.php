<?php
/**
 * ItemList schema for hub pages: sections/archives, service categories, and pages that list children
 * (pillar → cities, city → neighbourhoods). Only items that are published and visible on the page.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	if ( is_404() || is_search() || 'theme' !== zad_schema_owner() ) { return; }
	$posts = array(); $name = ''; $url = '';
	if ( is_singular( zad_service_types() ) ) {
		$id = get_queried_object_id();
		if ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) { return; }
		$posts = get_posts( array( 'post_type' => get_post_type( $id ), 'post_parent' => $id, 'post_status' => 'publish', 'numberposts' => 60, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'zad_all' => true ) );
		$name  = get_the_title( $id ); $url = get_permalink( $id );
	} elseif ( is_post_type_archive( zad_service_types() ) || is_tax( array( 'service_cat', 'service_area' ) ) ) {
		global $wp_query;
		$posts = array_slice( (array) $wp_query->posts, 0, 40 );
		$name  = wp_strip_all_tags( get_the_archive_title() ); $url = zad_current_url();
	} elseif ( is_page_template( 'temp/memo-services.php' ) ) {
		$posts = get_posts( array( 'post_type' => zad_service_types(), 'post_parent' => 0, 'post_status' => 'publish', 'numberposts' => 40, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
		$name  = get_the_title(); $url = get_permalink();
	}
	$els = array(); $pos = 1;
	foreach ( $posts as $p ) {
		if ( ! $p instanceof WP_Post || 'publish' !== $p->post_status ) { continue; }
		$els[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => wp_strip_all_tags( get_the_title( $p ) ), 'url' => get_permalink( $p ) );
	}
	if ( count( $els ) < 2 ) { return; }
	zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'ItemList', '@id' => trailingslashit( $url ) . '#itemlist', 'name' => $name, 'numberOfItems' => count( $els ), 'itemListOrder' => 'https://schema.org/ItemListOrderAscending', 'itemListElement' => $els ) );
}, 13 );
