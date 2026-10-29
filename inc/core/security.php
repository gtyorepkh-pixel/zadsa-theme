<?php defined( 'ABSPATH' ) || exit;
/* ==========================================================================
   1. التنظيف العميق للهيدر وحماية الأمان (Ported from rokn cleanup.php & security.php)
   ========================================================================== */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );

	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	add_filter( 'the_generator', '__return_empty_string' );
} );

add_action( 'wp_footer', function () {
	wp_dequeue_script( 'wp-embed' );
} );
add_filter( 'embed_oembed_discover', '__return_false' );

add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

// تعطيل XML-RPC وحماية REST API للمستخدمين (من security.php)
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );

add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

// حماية تسجيل الدخول المدمجة (5 محاولات كل 15 دقيقة — يغنيك عن إضافة Limit Login Attempts)
function zad_login_guard_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return 'zad_login_' . md5( $ip );
}
add_action( 'wp_login_failed', function () {
	$k = zad_login_guard_key();
	set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
} );
add_filter( 'authenticate', function ( $user ) {
	if ( (int) get_transient( zad_login_guard_key() ) >= 5 ) {
		return new WP_Error( 'zad_locked', '<strong>تنبيه أمني:</strong> تم تجاوز عدد محاولات الدخول المسموحة. يرجى الانتظار 15 دقيقة والمحاولة مجدداً.' );
	}
	return $user;
}, 30 );
add_action( 'wp_login', function () {
	delete_transient( zad_login_guard_key() );
} );

