<?php defined( 'ABSPATH' ) || exit;
/** One frontend asset pipeline. No new dependencies; existing handles and priorities. */
function zad_enqueue_base_assets() {
        wp_deregister_script('wp-mediaelement');
        wp_deregister_style('wp-mediaelement');
        wp_dequeue_style('global-styles');

        wp_enqueue_style('zad-main', ZAD_THEME_URI . 'assets/css/zad.css', array(), zad_asset_ver('assets/css/zad.css'));

        wp_enqueue_script('zad-main', ZAD_THEME_URI . 'assets/js/zad.js', array(), zad_asset_ver('assets/js/zad.js'), true);
        wp_localize_script('zad-main', 'ZAD', array('ajax' => admin_url('admin-ajax.php')));
}

/** Assets for inc/zad-suite.php. */
function zad_enqueue_public_icons() {
	if ( is_user_logged_in() ) {
		return;
	}
	wp_dequeue_style( 'dashicons' );
}

/** Assets for inc/zad-ix.php. */
function zad_enqueue_interactive_assets() {
	if ( ! is_singular() ) {
		return;
	}
	$p = get_queried_object();
	if ( ! $p || ( ! zad_ix_enabled( $p->ID ) && ! has_shortcode( $p->post_content, 'zad_ix' ) ) ) {
		return;
	}
	wp_enqueue_style( 'zad-ix', get_template_directory_uri() . '/assets/css/zad-ix.css', array( 'zad-main' ), zad_asset_ver( 'assets/css/zad-ix.css' ) );
	wp_enqueue_script( 'zad-ix', get_template_directory_uri() . '/assets/js/zad-ix.js', array(), zad_asset_ver( 'assets/js/zad-ix.js' ), true );
}

/** Assets for inc/zad-works.php. */
function zad_enqueue_work_assets() {
	// إزالة مكتبة hoverintent-js غير الضرورية لضمان كود Pure 100%
	if ( ! is_admin() && ! is_user_logged_in() ) {
		wp_dequeue_script( 'hoverintent-js' );
	}
	$on = is_singular( ZAD_WK ) || is_post_type_archive( ZAD_WK ) || ( is_singular() && zad_is_service() ) || ( function_exists( 'zad_hub_ctx' ) && zad_hub_ctx() ); // section pages show work cards
	if ( ! $on ) { return; }
	wp_enqueue_style( 'zad-work', get_template_directory_uri() . '/assets/css/zad-work.css', array( 'zad-main' ), zad_asset_ver( 'assets/css/zad-work.css' ) );
	if ( is_singular( ZAD_WK ) ) {
		wp_enqueue_script( 'zad-work', get_template_directory_uri() . '/assets/js/zad-work.js', array(), zad_asset_ver( 'assets/js/zad-work.js' ), true );
	}
}

/** Assets for inc/zad-quick.php. */
function zad_enqueue_quick_assets() {
	if ( ! ( is_front_page() || zad_is_service() ) ) { return; }
	wp_enqueue_script( 'zad-quick', get_template_directory_uri() . '/assets/js/zad-quick.js', array(), zad_asset_ver( 'assets/js/zad-quick.js' ), true );
}

/** Assets for inc/zad-ticket.php. */
function zad_enqueue_ticket_assets() {
	if ( ! zad_is_service() ) { return; }
	wp_enqueue_script( 'zad-ticket', get_template_directory_uri() . '/assets/js/zad-ticket.js', array(), zad_asset_ver( 'assets/js/zad-ticket.js' ), true );
}

/** Assets for inc/zad-hubs.php. */
function zad_enqueue_hub_assets() {
	if ( ! zad_hub_ctx() && ! is_post_type_archive( 'zad_service' ) ) { return; } // + /services/
	wp_enqueue_style( 'zad-hub', get_template_directory_uri() . '/assets/css/zad-hub.css', array( 'zad-main' ), zad_asset_ver( 'assets/css/zad-hub.css' ) );
}

/** Assets for the «الأسعار الشاملة» page template (temp/zad-prices.php). */
function zad_enqueue_prices_assets() {
	if ( ! is_page_template( 'temp/zad-prices.php' ) ) { return; }
	wp_enqueue_style( 'zad-prices', get_template_directory_uri() . '/assets/css/zad-prices.css', array( 'zad-main' ), zad_asset_ver( 'assets/css/zad-prices.css' ) );
}

/** Assets for inc/hs-tools/hs-tools.php. */
function zad_enqueue_hs_assets() {
	hs_register_assets();
	if ( ! is_singular() ) { return; }
	$p = get_post();
	if ( ! $p ) { return; }
	foreach ( hs_tool_map() as $t => $sc ) {
		if ( has_shortcode( $p->post_content, $sc ) ) { hs_enqueue( $t ); }
	}
}

function zad_enqueue_lean_assets() {
	if ( ! zad_opt( 'zad_lean', false ) || is_admin() ) { return; }
	$html = is_singular() ? (string) get_post_field( 'post_content', get_queried_object_id() ) : '';
	if ( '' === $html || ! zad_lean_needs_blocks( $html ) ) {
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles' ) as $h ) { wp_dequeue_style( $h ); } // global-styles = WordPress's inline theme.json CSS (often 10–25KB)
	}
	if ( false === strpos( $html, 'wp-block-embed-wordpress' ) ) { wp_deregister_script( 'wp-embed' ); }
	// Owner rules, one per line:  handle > needle   (drop the handle unless the page content contains needle)
	foreach ( preg_split( '/\r\n|\r|\n/', (string) zad_opt( 'zad_lean_rules', '' ) ) as $l ) {
		if ( false === strpos( $l, '>' ) ) { continue; }
		list( $h, $needle ) = array_map( 'trim', explode( '>', $l, 2 ) );
		if ( '' === $h || '' === $needle ) { continue; }
		if ( false === stripos( $html, $needle ) ) { wp_dequeue_style( $h ); wp_dequeue_script( $h ); }
	}
}

add_action( 'wp_enqueue_scripts', 'zad_enqueue_base_assets', 100 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_public_icons', PHP_INT_MAX );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_interactive_assets', 110 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_work_assets', 30 );
add_action( 'wp_enqueue_scripts', 'zad_dequeue_core_css', 100 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_lean_assets', 100 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_quick_assets', 110 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_ticket_assets', 110 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_hub_assets', 30 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_hs_assets', 10 );
add_action( 'wp_enqueue_scripts', 'zad_enqueue_prices_assets', 30 );
