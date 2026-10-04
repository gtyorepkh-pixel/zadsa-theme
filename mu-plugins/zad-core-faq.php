<?php
/**
 * Plugin Name: Zad Core FAQ
 * Description: Registers the FAQ post type "zad_faq" (URL /faq/{slug}/, archive /faq/) outside the theme, like the service type. Copy this file to wp-content/mu-plugins/.
 * The theme skips its own registration when this one is present. Keep the slug equal to the theme option «رابط الأسئلة» (default faq).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( post_type_exists( 'zad_faq' ) ) {
		return;
	}
	register_post_type( 'zad_faq', array(
		'labels'        => array( 'name' => 'الأسئلة الشائعة', 'singular_name' => 'سؤال', 'add_new' => 'إضافة سؤال', 'add_new_item' => 'إضافة سؤال جديد', 'edit_item' => 'تعديل السؤال', 'all_items' => 'كل الأسئلة', 'menu_name' => 'الأسئلة' ),
		'public'        => true,
		'has_archive'   => 'faq',
		'rewrite'       => array( 'slug' => 'faq', 'with_front' => false ),
		'menu_icon'     => 'dashicons-editor-help',
		'menu_position' => 7,
		'show_in_rest'  => true,
		'supports'      => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
	) );
}, 20 ); // after any registration of the old "faq" type, so its URLs keep working until the questions are converted
