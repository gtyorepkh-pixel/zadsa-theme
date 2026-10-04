<?php
/**
 * Plugin Name: Zad Core Service
 * Description: Registers the service post type "zad_service" (URL /service/{slug}/, not hierarchical, no public archive) outside the theme, like zad_faq. Copy to wp-content/mu-plugins/.
 * Taxonomies (service_cat, service_area) are registered by the theme for this type and for the adopted types.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( post_type_exists( 'zad_service' ) ) {
		return;
	}
	register_post_type( 'zad_service', array(
		'labels'        => array( 'name' => 'الخدمات', 'singular_name' => 'خدمة', 'add_new' => 'إضافة خدمة', 'add_new_item' => 'إضافة خدمة جديدة', 'edit_item' => 'تعديل الخدمة', 'new_item' => 'خدمة جديدة', 'view_item' => 'عرض الخدمة', 'search_items' => 'بحث في الخدمات', 'not_found' => 'لا توجد خدمات', 'all_items' => 'كل الخدمات', 'menu_name' => 'الخدمات' ),
		'public'        => true,
		'hierarchical'  => false,
		'has_archive'   => false,
		'rewrite'       => array( 'slug' => 'service', 'with_front' => false ),
		'menu_icon'     => 'dashicons-hammer',
		'menu_position' => 5,
		'show_in_rest'  => true,
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions', 'author' ),
	) );
}, 20 );
