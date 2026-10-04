<?php defined( 'ABSPATH' ) || exit;
/**
 * HS Tools — three free interactive tools (pest method chooser, moving bundle, stain first-aid).
 * Isolated module: everything lives in inc/hs-tools + assets/hs-tools; prefix "hs".
 * Loaded once from functions.php. No external libraries; assets load only on pages using a shortcode.
 */

define( 'HS_TOOLS_DIR', trailingslashit( __DIR__ ) );

/* ---------------- settings helpers ---------------- */
function hs_defaults() {
	return array(
		'whatsapp' => '', 'email' => '', 'cities' => '',
		'pest'     => array(), 'bundle' => array(),
		'floor_fee' => '', 'assemble_fee' => '', 'pack_fee' => '',
		'disc2' => 5, 'disc3' => 10, 'disc4' => 15,
	);
}
function hs_settings() {
	$o = get_option( 'hs_tools_settings' );
	return wp_parse_args( is_array( $o ) ? $o : array(), hs_defaults() );
}
/** Positive integer or null (empty / 0 / junk = "price to be determined"). */
function hs_num( $v ) {
	$n = (int) preg_replace( '/\D+/', '', (string) $v );
	return $n > 0 ? $n : null;
}
function hs_wa_number() {
	$s = hs_settings();
	$n = trim( (string) $s['whatsapp'] );
	if ( '' !== $n ) {
		return function_exists( 'zad_intl_number' ) ? zad_intl_number( $n ) : preg_replace( '/\D+/', '', $n );
	}
	return function_exists( 'zad_whatsapp' ) ? zad_whatsapp( 0 ) : '';
}
function hs_cities() {
	$s = hs_settings();
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) $s['cities'] ) ) ) );
}
function hs_fmt_range( $from, $to ) {
	$f = hs_num( $from ); $t = hs_num( $to );
	if ( $f && $t ) { return $f === $t ? $f . ' ر.س' : 'من ' . $f . ' إلى ' . $t . ' ر.س'; }
	if ( $f ) { return 'تبدأ من ' . $f . ' ر.س'; }
	if ( $t ) { return 'حتى ' . $t . ' ر.س'; }
	return 'يحدد بعد المعاينة';
}

/* ---------------- shared lists ---------------- */
function hs_sizes() {
	return array( 'studio' => 'استوديو', 'r1' => 'غرفة', 'r2' => 'غرفتان', 'r3' => '3 غرف', 'r4' => '4 غرف', 'r5' => '5 غرف فأكثر', 'villa' => 'فيلا' );
}
function hs_bundle_services() {
	return array( 'clean_old' => 'تنظيف الشقة القديمة', 'move' => 'نقل العفش داخل المدينة', 'clean_new' => 'تنظيف وتعقيم الشقة الجديدة', 'spray' => 'رش وقائي للشقة الجديدة' );
}

/* ---------------- includes ---------------- */
require_once HS_TOOLS_DIR . 'settings.php';
require_once HS_TOOLS_DIR . 'leads.php';
require_once HS_TOOLS_DIR . 'tools/pest-method.php';
require_once HS_TOOLS_DIR . 'tools/moving-bundle.php';
require_once HS_TOOLS_DIR . 'tools/stain-aid.php';

/* ---------------- assets (only on pages that use a tool) ---------------- */
function hs_tool_map() {
	return array( 'pest-method' => 'hs_pest_method', 'moving-bundle' => 'hs_moving_bundle', 'stain-aid' => 'hs_stain_aid' );
}
function hs_asset_ver( $rel ) {
	$f = get_template_directory() . '/assets/hs-tools/' . $rel;
	return is_readable( $f ) ? (string) filemtime( $f ) : '1';
}
function hs_register_assets() {
	$u = get_template_directory_uri() . '/assets/hs-tools/';
	wp_register_style( 'hs-tools-base', $u . 'hs-tools-base.css', array(), hs_asset_ver( 'hs-tools-base.css' ) );
	wp_register_script( 'hs-lead', $u . 'hs-lead.js', array(), hs_asset_ver( 'hs-lead.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	foreach ( array_keys( hs_tool_map() ) as $t ) {
		wp_register_style( 'hs-' . $t, $u . $t . '.css', array( 'hs-tools-base' ), hs_asset_ver( $t . '.css' ) );
		wp_register_script( 'hs-' . $t, $u . $t . '.js', array( 'hs-lead' ), hs_asset_ver( $t . '.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
}
add_action( 'wp_enqueue_scripts', function () {
	hs_register_assets();
	if ( ! is_singular() ) { return; }
	$p = get_post();
	if ( ! $p ) { return; }
	foreach ( hs_tool_map() as $t => $sc ) {
		if ( has_shortcode( $p->post_content, $sc ) ) { hs_enqueue( $t ); }
	}
} );
function hs_enqueue( $tool ) {
	if ( ! wp_style_is( 'hs-tools-base', 'registered' ) ) { hs_register_assets(); }
	wp_enqueue_style( 'hs-' . $tool );
	wp_enqueue_script( 'hs-' . $tool );
}

/* ---------------- shared markup ---------------- */
function hs_wrap( $tool, $title, $inner, $data ) {
	hs_enqueue( $tool );
	return '<div class="hs-tool hs-' . esc_attr( $tool ) . '" dir="rtl" data-tool="' . esc_attr( $tool ) . '" data-rest="' . esc_url( rest_url( 'hs-tools/v1/lead' ) ) . '" data-wa="' . esc_attr( hs_wa_number() ) . '">'
		. '<script type="application/json" class="hs-data">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>'
		. '<h2 class="hs-title">' . esc_html( $title ) . '</h2>' . $inner . '</div>';
}
/** Lead form shared by the three tools (hidden until the tool reveals it). */
function hs_lead_form( $title, $btn, $notes = false, $hood = true ) {
	$h  = '<form class="hs-form" hidden novalidate><h3 class="hs-form__t">' . esc_html( $title ) . '</h3>';
	$h .= '<div class="hs-hp" aria-hidden="true"><label>الموقع<input type="text" name="hs_website" tabindex="-1" autocomplete="off"></label></div>';
	$h .= '<div class="hs-field"><label>الاسم<input type="text" name="name" maxlength="100" autocomplete="name" required></label></div>';
	$h .= '<div class="hs-field"><label>رقم الجوال<input type="tel" name="phone" inputmode="numeric" maxlength="14" placeholder="05XXXXXXXX" autocomplete="tel" required></label></div>';
	if ( $hood ) { $h .= '<div class="hs-field"><label>الحي (اختياري)<input type="text" name="area" maxlength="80"></label></div>'; }
	if ( $notes ) { $h .= '<div class="hs-field"><label>ملاحظات (اختياري)<textarea name="notes" rows="2" maxlength="400"></textarea></label></div>'; }
	$h .= '<p class="hs-err" role="alert" hidden></p><button type="submit" class="hs-btn hs-btn--wa">' . esc_html( $btn ) . '</button>';
	$h .= '<p class="hs-fine">سيُحفظ طلبك ثم تُفتح محادثة واتساب برسالة جاهزة.</p><div class="hs-fallback" hidden></div></form>';
	return $h;
}
