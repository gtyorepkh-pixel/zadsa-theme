<?php defined( 'ABSPATH' ) || exit;
/**
 * Front-end side of «نقل الأسئلة والأزرار»: the [zad_cta] buttons, and the theme FAQ (_zad_faq) under the content of pages and articles
 * with its FAQPage schema. Service pages show their FAQ in their own section and are skipped here.
 * (Until 3.45.2 this lived in inc/zad-faqmigrate.php, which loads only in the admin, so migrated questions and buttons did not show on the site.)
 */

/* ---------- [zad_cta] ---------- */
add_shortcode( 'zad_cta', function ( $atts ) {
	$a  = shortcode_atts( array( 'call' => '1', 'wa' => '1', 'price' => '' ), $atts, 'zad_cta' );
	$id = get_the_ID();
	$ph = zad_phone( $id ); $wa = zad_wa_link( 'مرحباً، أرغب بطلب خدمة: ' . get_the_title( $id ), $id );
	$o  = '<div class="zad-cta">';
	if ( '0' !== $a['call'] && $ph ) { $o .= '<a class="btn btn--primary" href="' . esc_url( zad_tel_href( $ph ) ) . '">' . zad_icon( 'phone', 20 ) . ' اتصل الآن</a>'; }
	if ( '0' !== $a['wa'] && $wa ) { $o .= '<a class="btn btn--wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . zad_icon( 'whatsapp', 20 ) . ' تواصل عبر واتساب</a>'; }
	if ( $a['price'] ) { $o .= '<a class="btn btn--accent" href="' . esc_url( $a['price'] ) . '">' . zad_icon( 'bolt', 20 ) . ' احصل على سعر مبدئي</a>'; }
	return $o . '</div>';
} );

/* ---------- Pages/Posts that carry theme FAQ data ---------- */
add_filter( 'the_content', function ( $c ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $c; }
	$id = get_the_ID();
	if ( in_array( get_post_type( $id ), zad_service_types(), true ) || ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) ) { return $c; }
	$f = array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $r ) { return ! empty( $r['q'] ); } );
	if ( ! $f ) { return $c; }
	ob_start(); echo '<section class="zad-faq"><h2>الأسئلة الشائعة</h2>'; zad_render_faq( $f ); echo '</section>';
	return $c . ob_get_clean();
}, 30 );

add_action( 'wp_head', function () {
	if ( ! is_singular() || 'theme' !== zad_schema_owner() ) { return; }
	$id = get_queried_object_id();
	if ( in_array( get_post_type( $id ), zad_service_types(), true ) || ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) ) { return; }
	$f = array_values( array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $r ) { return ! empty( $r['q'] ) && ! empty( $r['a'] ); } ) );
	if ( ! $f ) { return; }
	$ents = array();
	foreach ( $f as $r ) { $ents[] = array( '@type' => 'Question', 'name' => $r['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( wp_strip_all_tags( $r['a'] ) ) ) ); }
	zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
}, 15 );
