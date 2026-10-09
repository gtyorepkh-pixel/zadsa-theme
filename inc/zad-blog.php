<?php defined( 'ABSPATH' ) || exit;
/**
 * The single blog type «zad_blog» (/blog/). The mu-plugin mu-plugins/zad-core-blog.php registers it outside the theme (like the
 * service and FAQ types); this file registers it only when that file is absent, so the URLs survive a theme change.
 * It is an «article» type: archive design = hub-articles, single design = the article design (see inc/zad-roles.php).
 */
if ( ! function_exists( 'zad_blog_register' ) ) {
	require_once get_template_directory() . '/mu-plugins/zad-core-blog.php';
}
