<?php defined( 'ABSPATH' ) || exit;
/** Front-end output: CSS variables, schema, body classes. */

add_action( 'wp_head', function () {
	$p = sanitize_hex_color( zad_opt( 'zad_color_primary', '#0b4f5c' ) ) ?: '#0b4f5c';
	$a = sanitize_hex_color( zad_opt( 'zad_color_accent', '#f59e0b' ) ) ?: '#f59e0b';
	echo '<style id="zad-vars">:root{--primary:' . $p . ';--accent:' . $a . ';}</style>' . "\n"; // phpcs:ignore
	echo '<meta name="theme-color" content="' . esc_attr( $p ) . '">' . "\n";
}, 5 );

add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		zad_print_schema( zad_business_schema() );
	}
	if ( is_singular( 'zad_service' ) ) {
		$id    = get_the_ID();
		$price = get_post_meta( $id, '_zad_price', true );
		$s     = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'name'        => get_the_title(),
			'description' => wp_strip_all_tags( get_the_excerpt() ),
			'url'         => get_permalink(),
			'provider'    => array( '@id' => home_url( '/#business' ) ),
			'areaServed'  => 'SA',
		);
		$terms = get_the_terms( $id, 'service_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$s['serviceType'] = $terms[0]->name;
		}
		if ( has_post_thumbnail() ) {
			$s['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
		if ( $price ) {
			$s['offers'] = array( '@type' => 'Offer', 'price' => (string) $price, 'priceCurrency' => 'SAR' );
		}
		$rating  = get_post_meta( $id, '_zad_rating', true );
		$reviews = (int) get_post_meta( $id, '_zad_reviews', true );
		if ( $rating && $reviews > 0 ) {
			$s['aggregateRating'] = array( '@type' => 'AggregateRating', 'ratingValue' => (string) $rating, 'reviewCount' => $reviews );
		}
		zad_print_schema( $s );
		zad_print_schema( zad_business_schema() );
		zad_print_schema( zad_crumbs_schema( zad_service_crumbs( $id ) ) );

		$faq = array_filter( (array) get_post_meta( $id, '_zad_faq', true ) );
		if ( $faq ) {
			$ents = array();
			foreach ( $faq as $f ) {
				$ents[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ) );
			}
			zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
		}
	}
	if ( is_front_page() ) {
		$faq = array_filter( (array) zad_opt( 'zad_faq', array() ) );
		if ( $faq ) {
			$ents = array();
			foreach ( $faq as $f ) {
				if ( ! empty( $f['q'] ) ) {
					$ents[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ?? '' ) );
				}
			}
			if ( $ents ) {
				zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
			}
		}
	}
}, 20 );

/** Render an FAQ accordion from [ ['q'=>, 'a'=>], ... ]. */
function zad_render_faq( $items ) {
	$items = array_values( array_filter( (array) $items, function ( $f ) { return ! empty( $f['q'] ); } ) );
	if ( ! $items ) {
		return;
	}
	echo '<div class="faq">';
	foreach ( $items as $f ) {
		echo '<details class="faq__item"><summary>' . esc_html( $f['q'] ) . zad_icon( 'chevron', 20 ) . '</summary><div class="faq__a">' . wp_kses_post( wpautop( $f['a'] ?? '' ) ) . '</div></details>'; // phpcs:ignore
	}
	echo '</div>';
}

function zad_stars( $rating ) {
	$r = max( 0, min( 5, (float) $rating ) );
	return '<span class="stars" role="img" aria-label="تقييم ' . esc_attr( $r ) . ' من 5"><span class="stars__fill" style="width:' . esc_attr( $r * 20 ) . '%"></span></span>';
}

/** Posts that are not services keep their classic look (menu, WA bar etc. come from header/footer). */
add_filter( 'body_class', function ( $c ) {
	$c[] = 'zad';
	return $c;
} );

add_filter( 'excerpt_length', function () { return 24; } );
add_filter( 'excerpt_more', function () { return '…'; } );
