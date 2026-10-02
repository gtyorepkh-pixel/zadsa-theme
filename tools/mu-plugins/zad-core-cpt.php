<?php
/*
  Plugin Name: Zad Saudi Core Engine
  Description: المحرك الأساسي لتسجيل المقالات المخصصة والتصنيفات لضمان استقرار الأرشفة والروابط
*/

defined( 'ABSPATH' ) || exit;

add_action( 'init', function() {

    // 1. مدونة الحشرات
    register_post_type('pests-library', array(
        'hierarchical'        => true,
        'public'              => true,
        'show_ui'             => true,
        'has_archive'         => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'pests-library', 'with_front' => true),
        'capability_type'     => 'post',
        'show_in_rest'        => true,
        'labels'              => array('name' => 'مدونة الحشرات', 'singular_name' => 'مدونة الحشرات', 'menu_name' => 'مدونة الحشرات'),
        'supports'            => array('title', 'thumbnail', 'editor')
    ));

    // 2. الأقسام
    register_post_type('sections', array(
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'has_archive'         => true,
        'show_in_rest'        => true,
        'rewrite'             => array('slug' => 'sections', 'with_front' => true),
        'labels'              => array('name' => 'الاقسام', 'all_items' => 'كل الاقسام'),
        'supports'            => array('title', 'thumbnail', 'editor', 'comments')
    ));

    // 3. صفحات الخدمات (التنظيف)
    register_post_type('cleaning', array(
        'hierarchical'        => true,
        'public'              => true,
        'show_ui'             => true,
        'menu_icon'           => 'dashicons-broom',
        'has_archive'         => true,
        'show_in_rest'        => true,
        'capability_type'     => 'page',
        'map_meta_cap'        => true,
        'rewrite'             => array('slug' => 'cleaning', 'with_front' => false, 'hierarchical' => true),
        'labels'              => array('name' => 'صفحات الخدمات', 'menu_name' => 'الخدمات'),
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
    ));

    // 4. صفحات مكافحة الحشرات
    register_post_type('pest_control', array(
        'hierarchical'        => true,
        'public'              => true,
        'show_ui'             => true,
        'menu_icon'           => 'dashicons-shield-alt',
        'has_archive'         => true,
        'show_in_rest'        => true,
        'capability_type'     => 'page',
        'map_meta_cap'        => true,
        'rewrite'             => array('slug' => 'pest-control', 'with_front' => false, 'hierarchical' => true),
        'labels'              => array('name' => 'صفحات مكافحة الحشرات', 'menu_name' => 'مكافحة الحشرات'),
        'supports'            => array('title', 'thumbnail', 'editor', 'excerpt', 'page-attributes')
    ));

    // 5. الأسئلة الشائعة
    register_post_type('faq', array(
        'public'              => true,
        'show_ui'             => true,
        'has_archive'         => true,
        'rewrite'             => array('slug' => 'faq', 'with_front' => false),
        'show_in_rest'        => true,
        'labels'              => array('name' => 'الأسئلة الشائعة', 'menu_name' => 'الأسئلة الشائعة'),
        'supports'            => array('title', 'thumbnail', 'editor')
    ));

    // 6. الأدلة
    register_post_type('guide', array(
        'public'              => true,
        'show_ui'             => true,
        'menu_icon'           => 'dashicons-welcome-learn-more',
        'has_archive'         => 'guide',
        'rewrite'             => array('slug' => 'guide', 'with_front' => true),
        'show_in_rest'        => true,
        'labels'              => array('name' => 'الأدلة', 'menu_name' => 'الأدلة'),
        'supports'            => array('title', 'thumbnail', 'editor', 'excerpt')
    ));

    // --- تسجيل التصنيفات (Taxonomies) ---

    register_taxonomy('best_sections', array('sections'), array(
        'hierarchical' => true,
        'labels'       => array('name' => 'افضل الاقسام'),
        'show_in_rest' => true,
        'rewrite'      => array('slug' => 'best_sections'),
    ));

    register_taxonomy('cleaning-sections', array('cleaning'), array(
        'hierarchical' => true,
        'labels'       => array('name' => 'أقسام التنظيف'),
        'show_in_rest' => true,
        'rewrite'      => array('slug' => 'cleaning-sections', 'with_front' => false),
    ));

    register_taxonomy('pest_sections', array('pest_control'), array(
        'hierarchical' => true,
        'labels'       => array('name' => 'أقسام مكافحة الحشرات'),
        'show_in_rest' => true,
        'rewrite'      => array('slug' => 'pests-category', 'with_front' => false, 'hierarchical' => true),
    ));

    register_taxonomy('best_faqs', array('faq'), array(
        'hierarchical' => true,
        'labels'       => array('name' => 'تصنيفات الأسئلة'),
        'show_in_rest' => true,
        'rewrite'      => array('slug' => 'best-faqs', 'with_front' => false),
    ));

    register_taxonomy('best_guide', array('guide'), array(
        'hierarchical' => true,
        'labels'       => array('name' => 'أفضل الأدلة'),
        'show_in_rest' => true,
        'rewrite'      => array('slug' => 'best-guide', 'with_front' => true),
    ));

    // ربط التصنيفات والوسوم العامة بالكاستم بوست
    register_taxonomy_for_object_type('category', 'cleaning');
    register_taxonomy_for_object_type('category', 'pest_control');
    register_taxonomy_for_object_type('post_tag', 'cleaning');
    register_taxonomy_for_object_type('post_tag', 'pest_control');

// 7. صفحات نقل وتخزين الأثاث
register_post_type('moving', array(
    'hierarchical'      => true,
    'public'            => true,
    'show_ui'           => true,
    'menu_icon'         => 'dashicons-migrate',
    'has_archive'       => true,
    'show_in_rest'      => true,
    'capability_type'   => 'page',
    'map_meta_cap'      => true,
    'rewrite'           => array('slug' => 'moving', 'with_front' => false, 'hierarchical' => true),
    'labels'            => array('name' => 'نقل وتخزين الأثاث', 'menu_name' => 'نقل وتخزين الأثاث'),
    'supports'          => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes')
));

}, 0);

/**
 * وظائف حماية المحتوى والتعليقات الخاصة بالـ CPTs
 */
add_filter('wp_insert_post_data', function ($data, $postarr) {
    $cpts = array('cleaning', 'pest_control');
    if (in_array($data['post_type'], $cpts)) {
        $data['comment_status'] = 'closed';
        $data['ping_status']    = 'closed';
    }
    return $data;
}, 10, 2);
