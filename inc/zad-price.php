<?php
/**
 * ONE price reader for the whole theme (loaded first). Every place that turns the text a person typed into a price —
 * packages section, hero widget, price rows, schema — goes through zad_price_parse().
 *
 *   «349»            → fixed  (min = 349)
 *   «250-450»        → range  (min = 250, max = 450; also «250 – 450», «٢٥٠–٤٥٠»)
 *   «من 600»         → from   (min = 600)
 *   «بعد المعاينة»…  → quote  (any text without a number: no price, never 0)
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'zad_price_parse' ) ) {
	/** Price text → array( kind, min, max, raw_a, raw_b ). raw_* keep the digits exactly as typed (for display). */
	function zad_price_parse( $text ) {
		$num = '[\d٠-٩][\d٠-٩,٬.]*';
		$out = array( 'kind' => 'quote', 'min' => 0, 'max' => 0, 'raw_a' => '', 'raw_b' => '' );
		$val = function ( $t ) { return (float) str_replace( ',', '', zad_digits_en( $t ) ); };
		$t   = (string) $text;
		if ( preg_match( '/(' . $num . ')\s*[-–—]\s*(' . $num . ')/u', $t, $m ) ) {
			$a = $val( $m[1] ); $b = $val( $m[2] );
			$out = array( 'kind' => 'range', 'min' => min( $a, $b ), 'max' => max( $a, $b ), 'raw_a' => $m[1], 'raw_b' => $m[2] );
		} elseif ( preg_match( '/^\s*من\s+(' . $num . ')/u', $t, $m ) ) {
			$out = array( 'kind' => 'from', 'min' => $val( $m[1] ), 'max' => 0, 'raw_a' => $m[1], 'raw_b' => '' );
		} elseif ( preg_match( '/(' . $num . ')/u', $t, $m ) ) {
			$out = array( 'kind' => 'fixed', 'min' => $val( $m[1] ), 'max' => 0, 'raw_a' => $m[1], 'raw_b' => '' );
		}
		if ( 'quote' !== $out['kind'] && $out['min'] <= 0 ) { // never a zero price
			$out = array( 'kind' => 'quote', 'min' => 0, 'max' => 0, 'raw_a' => '', 'raw_b' => '' );
		}
		return $out;
	}
}

if ( ! function_exists( 'zad_price_hero_text' ) ) {
	/** The number the hero widget shows for a parsed price: the only number, the first of a range, the X of «من X»; '' for a quote. */
	function zad_price_hero_text( $pp ) {
		return 'quote' === $pp['kind'] ? '' : $pp['raw_a'];
	}
}

if ( ! function_exists( 'zad_price_offer' ) ) {
	/** Schema price fields of an Offer for a parsed price ('quote' → array(): no price at all). $monthly adds referenceQuantity {1, MON}. */
	function zad_price_offer( $pp, $monthly = false ) {
		if ( 'quote' === $pp['kind'] ) { return array(); }
		$n   = function ( $v ) { return ( floor( $v ) == $v ) ? (int) $v : (float) $v; };
		$ref = array( '@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => 'MON' );
		$f   = array( 'priceCurrency' => 'SAR' );
		if ( 'fixed' === $pp['kind'] ) {
			$f['price'] = $n( $pp['min'] );
			if ( $monthly ) { $f['priceSpecification'] = array( '@type' => 'UnitPriceSpecification', 'price' => $n( $pp['min'] ), 'priceCurrency' => 'SAR', 'referenceQuantity' => $ref ); }
			return $f;
		}
		$spec = array( '@type' => $monthly ? 'UnitPriceSpecification' : 'PriceSpecification', 'minPrice' => $n( $pp['min'] ) );
		if ( 'range' === $pp['kind'] ) { $spec['maxPrice'] = $n( $pp['max'] ); }
		$spec['priceCurrency'] = 'SAR';
		if ( $monthly ) { $spec['referenceQuantity'] = $ref; }
		$f['priceSpecification'] = $spec;
		return $f;
	}
}
