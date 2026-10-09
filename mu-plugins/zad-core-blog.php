<?php
/**
 * Plugin Name: Zad Core Blog
 * Description: ONE blog post type "zad_blog" (URL /blog/{slug}/, archive /blog/) that replaces the three old types (sections / guide / pests-library). Copy this file to wp-content/mu-plugins/.
 * The theme registers the same type itself when this file is absent (inc/zad-blog.php). Move the posts with tools/db/zad-blog-migrate.php.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'zad_blog_register' ) ) {
	function zad_blog_register() {
		if ( ! post_type_exists( 'zad_blog' ) ) {
			register_post_type( 'zad_blog', array(
				'labels'          => array( 'name' => 'المدونة', 'singular_name' => 'مقال', 'menu_name' => 'المدونة', 'all_items' => 'كل المقالات', 'add_new' => 'إضافة مقال', 'add_new_item' => 'إضافة مقال جديد', 'edit_item' => 'تعديل المقال', 'view_item' => 'عرض المقال', 'search_items' => 'بحث في المدونة', 'not_found' => 'لا توجد مقالات' ),
				'public'          => true,
				'hierarchical'    => true, // keeps the nesting of the old types (/blog/parent/child/)
				'has_archive'     => 'blog',
				'rewrite'         => array( 'slug' => 'blog', 'with_front' => false, 'hierarchical' => true ),
				'menu_icon'       => 'dashicons-welcome-learn-more',
				'menu_position'   => 6,
				'show_in_rest'    => true,
				'capability_type' => 'page',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'comments', 'page-attributes' ),
			) );
		}
	}
	function zad_blog_attach_taxonomies() {
		if ( ! post_type_exists( 'zad_blog' ) ) { return; }
		$tax = array( 'best_sections', 'best_guide', 'category', 'post_tag' ); // the old types' classifications keep working on the new type
		foreach ( array( 'sections', 'guide', 'pests-library' ) as $old ) { $tax = array_merge( $tax, get_object_taxonomies( $old ) ); }
		$tax = array_merge( $tax, (array) get_option( 'zad_blog_taxonomies', array() ) ); // written by the migration tool: the classifications the moved posts really use
		foreach ( array_unique( $tax ) as $t ) { if ( taxonomy_exists( $t ) ) { register_taxonomy_for_object_type( $t, 'zad_blog' ); } }
	}
	add_action( 'init', 'zad_blog_register', 20 );
	add_action( 'init', 'zad_blog_attach_taxonomies', 60 ); // after every taxonomy of the old types exists
}
