<?php defined( 'ABSPATH' ) || exit;
/** Visible cross-city links and the city catalog share their ordered selection. Price catalogs remain intact. */
add_filter( 'zsc_service_nodes', function ( $nodes, $post ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$other_ids = zad_link_city_ids( $id );
	if ( ! $other_ids ) { return $nodes; }
	$city_offers = array();
	foreach ( $other_ids as $oid ) {
		$cname = zad_city_label( zad_link_concrete_city( $oid ) );
		$item  = array(
			'@type' => 'Service',
			'name'  => zad_card_title( $oid ),
			'url'   => get_permalink( $oid ),
		);
		if ( $cname ) {
			$wd = function_exists( 'zsc_city_wikidata' ) ? zsc_city_wikidata( $cname ) : '';
			$item['areaServed'] = array_filter( array( '@type' => 'City', 'name' => $cname, 'sameAs' => $wd ?: null ) );
		}
		$city_offers[] = array( '@type' => 'Offer', 'itemOffered' => $item );
	}
	if ( $city_offers ) {
		foreach ( $nodes as $k => $n ) {
			if ( isset( $n['@type'] ) && 'Service' === $n['@type'] && empty( $nodes[ $k ]['hasOfferCatalog'] ) ) {
				$nodes[ $k ]['hasOfferCatalog'] = array(
					'@type'           => 'OfferCatalog',
					'name'            => zad_svc_label( $id ),
					'itemListElement' => $city_offers,
				);
			}
		}
	}
	return $nodes;
}, 25, 2 );
