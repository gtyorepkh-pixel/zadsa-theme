<?php defined( 'ABSPATH' ) || exit;
/** Shared, visible data for the theme's schema. No external libraries or network calls. */

/** The homepage and its ItemList must use exactly the same public services. */
function zad_home_service_query() {
	static $query = null;
	if ( null === $query ) {
		$query = new WP_Query( array( 'post_type' => zad_service_types(), 'post_status' => 'publish',
			'has_password' => false, 'posts_per_page' => 12, 'no_found_rows' => true, 'suppress_filters' => false,
			'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
	}
	return $query;
}

function zad_home_service_posts() {
	return zad_home_service_query()->posts;
}

/** Text is retained for variable warranties; only an unambiguous duration gets a number. */
function zad_schema_warranty_promise( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( '' === $text || preg_match( '/بلا\s*ضمان|بدون\s*ضمان|لا\s*يوجد\s*ضمان/u', $text ) ) { return null; }
	$promise = array( '@type' => 'WarrantyPromise', 'description' => $text );
	$duration = zad_pk_warranty( $text );
	if ( $duration ) {
		$promise['durationOfWarranty'] = array( '@type' => 'QuantitativeValue', 'value' => $duration[0], 'unitCode' => $duration[1] );
	}
	return $promise;
}

/** Offer has defined support for additionalProperty and warranty; Service does not. */
function zad_schema_offer_catalog( $catalog, $id, $facts ) {
	$url = get_permalink( $id );
	$rows = zad_price_rows( $id );
	$packages = zad_parse_packages( get_post_meta( $id, '_zad_packages', true ) );
	$facts = array_values( array_filter( $facts, function ( $f ) { return ! preg_match( '/الضمان/u', $f['name'] ?? '' ); } ) );
	foreach ( $catalog['itemListElement'] as $i => &$offer ) {
		$offer['@id'] = $url . '#offer-' . ( $i + 1 );
		$offer['url'] = $packages ? $url . '#prices' : $url;
		if ( empty( $offer['name'] ) ) { $offer['name'] = $offer['itemOffered']['name'] ?? get_the_title( $id ); }
		$offer['itemOffered'] = array( '@id' => $url . '#service' );
		$offer['seller'] = array( '@id' => home_url( '/#localbusiness' ) );
		if ( $facts ) { $offer['additionalProperty'] = $facts; }
		$warranty = isset( $packages[ $i ] ) ? $packages[ $i ]['warranty'] : ( $rows[ $i ]['warranty'] ?? '' );
		if ( '' === trim( (string) $warranty ) ) { $warranty = zad_warranty_text( $id ); }
		$promise = zad_schema_warranty_promise( $warranty );
		if ( $promise ) { $offer['warranty'] = $promise; } else { unset( $offer['warranty'] ); }
		$unit = trim( (string) ( $packages[ $i ]['unit'] ?? '' ) );
		if ( $unit && ! preg_match( '/^(ريال|ر\.?س\.?|SAR)$/iu', $unit ) && isset( $offer['priceSpecification'] ) ) {
			$offer['priceSpecification']['unitText'] = $unit;
		}
	}
	unset( $offer );
	return $catalog;
}

/** Monthly/per-unit and quote offers are referenced separately rather than mixed into one range. */
function zad_schema_offer_summary( $offers, $url ) {
	$refs = array(); $low = null; $high = null; $priced = 0; $bounded = true; $separate = false;
	foreach ( $offers as $offer ) {
		$refs[] = array( '@id' => $offer['@id'] );
		$spec = $offer['priceSpecification'] ?? array();
		if ( 'UnitPriceSpecification' === ( $spec['@type'] ?? '' ) || ! empty( $spec['unitText'] ) ) { $separate = true; }
		$min = $offer['price'] ?? ( $spec['price'] ?? ( $spec['minPrice'] ?? null ) );
		$max = $offer['price'] ?? ( $spec['price'] ?? ( $spec['maxPrice'] ?? null ) );
		if ( null === $min ) { $separate = true; continue; }
		$priced++;
		$low = null === $low ? (float) $min : min( $low, (float) $min );
		if ( null === $max ) { $bounded = false; }
		else { $high = null === $high ? (float) $max : max( $high, (float) $max ); }
	}
	if ( $separate || ! $priced ) { return $refs; }
	$summary = array( '@type' => 'AggregateOffer', 'priceCurrency' => 'SAR', 'lowPrice' => $low,
		'offerCount' => $priced, 'url' => $url, 'offers' => $refs );
	if ( $bounded && null !== $high ) { $summary['highPrice'] = $high; }
	return $summary;
}

/** A modification date is not evidence of a content review. */
function zad_schema_review_date_valid( $date ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) { return false; }
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && $date <= current_time( 'Y-m-d' );
}

function zad_schema_review( $id ) {
	$date = (string) get_post_meta( $id, '_zad_reviewed_date', true );
	$user = absint( get_post_meta( $id, '_zad_reviewed_by', true ) );
	if ( ! zad_schema_review_date_valid( $date ) || ! $user || ! get_userdata( $user ) ) { return null; }
	return array( 'date' => $date, 'user' => $user );
}

function zad_schema_review_metabox( $post ) {
	wp_nonce_field( 'zad_schema_review', 'zad_schema_review_nonce' );
	$date = get_post_meta( $post->ID, '_zad_reviewed_date', true );
	$user = absint( get_post_meta( $post->ID, '_zad_reviewed_by', true ) );
	echo '<p>سجّل هذه البيانات عند إجراء مراجعة فعلية للمحتوى. تركها فارغة لا يعتبر تاريخ التعديل مراجعة فنية.</p>';
	echo '<p><label>تاريخ المراجعة <input type="date" name="zad_reviewed_date" value="' . esc_attr( $date ) . '" max="' . esc_attr( current_time( 'Y-m-d' ) ) . '"></label></p>';
	echo '<p><label>المراجع <select name="zad_reviewed_by"><option value="0">— اختر المراجع —</option>';
	foreach ( get_users( array( 'capability' => 'edit_posts' ) ) as $author ) {
		echo '<option value="' . (int) $author->ID . '"' . selected( $user, $author->ID, false ) . '>' . esc_html( $author->display_name ) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p><label><input type="checkbox" name="zad_review_confirm" value="1"> تمّت مراجعة المحتوى الحالي فعلياً (لتسجيل مراجعة جديدة في نفس اليوم)</label></p>';
}

add_action( 'save_post', function ( $id ) {
	if ( empty( $_POST['zad_schema_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_schema_review_nonce'] ) ), 'zad_schema_review' )
		|| ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
	$date = sanitize_text_field( wp_unslash( $_POST['zad_reviewed_date'] ?? '' ) );
	$user = absint( $_POST['zad_reviewed_by'] ?? 0 );
	if ( '' === $date && ! $user ) { delete_post_meta( $id, '_zad_reviewed_date' ); delete_post_meta( $id, '_zad_reviewed_by' ); return; }
	if ( ! zad_schema_review_date_valid( $date ) || ! $user || ! get_userdata( $user ) ) { return; }
	update_post_meta( $id, '_zad_reviewed_date', $date );
	update_post_meta( $id, '_zad_reviewed_by', $user );
} );
