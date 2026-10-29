<?php defined( 'ABSPATH' ) || exit;
/** Read existing service data once; preserve the shared section scope. */
function zad_service_view_data( $id ) {
	$m    = function ( $k ) use ( $id ) { return get_post_meta( $id, '_zad_' . $k, true ); };
	$arr  = function ( $k ) use ( $m ) { return array_values( array_filter( (array) $m( $k ), function ( $r ) { return is_array( $r ); } ) ); };

	$title    = function_exists( 'zad_h1_title' ) ? zad_h1_title( $id ) : get_the_title();
	$first_city = '';
	foreach ( (array) get_the_terms( $id, 'service_area' ) as $ct ) {
		if ( $ct instanceof WP_Term && 0 === (int) $ct->parent ) { $first_city = $ct->name; break; }
	}
	$tagline  = $m( 'tagline' );
	$stats    = $arr( 'stats' );
	$subs     = $arr( 'subs' );
	$tools    = $arr( 'tools' );
	$steps    = $arr( 'steps' );
	$factors  = $arr( 'factors' );
	$faq      = $arr( 'faq' );
	$signs    = $arr( 'signs' );
	$harms    = $arr( 'harms' );
	$prices   = zad_price_rows( $id );                       // _zad_packages rows (old pages: the _zad_prices rows) → hero widget, "من" price
	$min      = zad_min_price( $prices ) ?: (int) $m( 'price' );
	$unit     = $m( 'price_unit' ) ?: 'ريال';
	$gallery  = array_filter( array_map( 'intval', explode( ',', (string) $m( 'gallery' ) ) ) );
	$ba_ids   = array_values( array_filter( array_map( 'intval', explode( ',', (string) $m( 'ba' ) ) ) ) );
	$ba_text  = zad_lines( $m( 'ba_text' ) );
	$video    = $m( 'video' );
	$rating   = $m( 'rating' );
	$reviews  = (int) $m( 'reviews' );
	$areas    = get_the_terms( $id, 'service_area' );
	$cats     = get_the_terms( $id, 'service_cat' );
	$cta      = $m( 'cta_text' ) ?: 'اطلب الخدمة الآن';
	$phone    = zad_phone( $id );
	$wa       = zad_wa_link( 'مرحباً، أرغب بطلب خدمة: ' . $title, $id );
	$facts    = array_filter( array(
		array( 'shield', zad_warranty_text( $id ) ),
		array( 'clock', $m( 'duration' ) ),
		array( 'bolt', $m( 'response' ) ),
	), function ( $c ) { return ! empty( $c[1] ); } );
	$thumb    = has_post_thumbnail() ? get_post_thumbnail_id() : 0;
	$provider = zad_opt( 'zad_provider', get_bloginfo( 'name' ) );
	$area_names = ( $areas && ! is_wp_error( $areas ) ) ? wp_list_pluck( $areas, 'name' ) : array();
	return compact( 'm', 'arr', 'title', 'first_city', 'tagline', 'stats', 'subs', 'tools', 'steps', 'factors', 'faq', 'signs', 'harms', 'prices', 'min', 'unit', 'gallery', 'ba_ids', 'ba_text', 'video', 'rating', 'reviews', 'areas', 'cats', 'cta', 'phone', 'wa', 'facts', 'thumb', 'provider', 'area_names' );
}

function zad_service_sections() {
	return array(
		'hero',
		'stats',
		'content',
		'interactive',
		'coverage',
		'subservices',
		'equipment',
		'steps',
		'price-factors',
		'pricing',
		'signs',
		'before-after',
		'video',
		'business',
		'clients',
		'works',
		'faq',
		'links',
		'related',
		'gallery',
	);
}

function zad_render_service( $id ) {
 extract( zad_service_view_data( $id ), EXTR_SKIP );
 foreach ( zad_service_sections() as $zad_service_section ) {
  $zad_service_template = locate_template( 'template-parts/service/' . $zad_service_section . '.php' );
  if ( $zad_service_template ) { include $zad_service_template; }
 }
 echo '</main>';
}
