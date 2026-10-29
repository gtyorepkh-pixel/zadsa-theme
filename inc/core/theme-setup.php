<?php defined( 'ABSPATH' ) || exit;
/** Theme supports, image sizes and menu locations (was MemoSetupTheme in _inc/_memo.setup.php). */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );
	add_theme_support( 'title-tag' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'responsive-embeds' );
	add_image_size( 'zad-card', 640, 400, true );
	register_nav_menus( array(
		'mainmenu'     => 'القائمه العلوية',
		'footermenu'   => 'الفوتر: روابط مهمة',
		'footerinfo'   => 'الفوتر: الخدمات',
		'footercities' => 'الفوتر: المدن',
		'legalmenu'    => 'الروابط القانونية (أسفل الفوتر)',
	) );
} );
