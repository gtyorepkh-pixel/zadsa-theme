<?php defined( 'ABSPATH' ) || exit;
/**
 * Warranty policy — ONE place for the warranty duration of the services that have a fixed policy.
 *
 *   termite  → ضمان مكتوب 15 عاماً
 *   bedbug   → ضمان مكتوب 3 أشهر
 *   roach    → ضمان مكتوب 3 أو 6 أشهر حسب الاتفاق   (text only: the number depends on the agreement, so no numeric schema)
 *
 * The free follow-up visit after two weeks is part of the service, NOT the warranty.
 * Services that are not listed here keep whatever their own `_zad_warranty` says (the theme never edits them).
 * Override (theme options → «سياسة الضمان»): one line per service  «المفتاح | النص | الأشهر»  (months 0 = text only).
 *
 * Everything that prints a warranty duration reads it from here: the spec card, the facts strip, the service cards,
 * the hub table, the «ماذا يشمل الضمان» section, the packages and the Service schema (additionalProperty).
 */

/** key => array( keyword regex (title / slug), text, months (0 = no number) ). */
function zad_warranty_policy() {
	static $p = null;
	if ( null !== $p ) {
		return $p;
	}
	$p = array(
		'termite' => array( '/نمل\s*(?:ال)?[أا]بيض|[أا]رضة|termite|white-?ant/iu', 'ضمان مكتوب 15 عاماً', 180 ),
		'bedbug'  => array( '/بق\s*الفراش|(?<![\p{L}])البق(?![\p{L}])|bed-?bug/iu', 'ضمان مكتوب 3 أشهر', 3 ),
		'roach'   => array( '/صراصير|صرصور|حشرات|مبيد|عقارب|فئران|قوارض|cockroach|pest/iu', 'ضمان مكتوب 3 أو 6 أشهر حسب الباقة والسعر المتفق عليهما لنفس الآفة ونطاق العمل', 0 ),
		'ac'      => array( '/مكيف|سبليت|شباك|دكت|فريون|ac-clean|air-condition/iu', 'ضمان مراجعة مجانية ضد التسرب أو ضعف التصريف بعد الغسيل', 1 ),
		'tank'    => array( '/خزان|تنظيف خزانات|عزل خزانات|tank/iu', 'ضمان مكتوب على نظافة وتعقيم وعزل الخزانات', 12 ),
		'sofa'    => array( '/كنب|مجالس|موكيت|سجاد|مفروشات|مراتب|sofa/iu', 'ضمان سلامة الأقمشة وثبات الألوان وإزالة الروائح', 1 ),
		'clean'   => array( '/تنظيف منازل|تنظيف فلل|تنظيف شقق|تنظيف قصور|house-clean/iu', 'ضمان إعادة التنظيف الفوري لأي ملاحظة قبل استلام المكان', 1 ),
	);
	foreach ( (array) zad_opt( 'zad_warranty_builder', array() ) as $wb ) {
		$wk = $wb['service_key'] ?? '';
		if ( $wk && isset( $p[ $wk ] ) && ! empty( $wb['text'] ) ) {
			$p[ $wk ][1] = trim( (string) $wb['text'] );
			if ( isset( $wb['months'] ) && is_numeric( $wb['months'] ) ) {
				$p[ $wk ][2] = (int) $wb['months'];
			}
		}
	}
	foreach ( zad_lines( (string) zad_opt( 'zad_warranty_policy', '' ) ) as $l ) { // optional override
		$c = array_map( 'trim', explode( '|', $l ) );
		if ( isset( $p[ $c[0] ] ) && ! empty( $c[1] ) ) {
			$p[ $c[0] ][1] = $c[1];
			if ( isset( $c[2] ) && is_numeric( $c[2] ) ) { $p[ $c[0] ][2] = (int) $c[2]; }
		}
	}
	return $p;
}

/** Policy key of a service page (from its title + slug); '' when none or when it names more than one service (hub pages). */
function zad_warranty_key( $id ) {
	static $memo = array();
	$id = (int) $id;
	if ( ! $id ) { return ''; }
	if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
	$post = get_post( $id );
	$key  = '';
	if ( $post && in_array( $post->post_type, zad_service_types(), true ) ) {
		$hay = get_the_title( $id ) . ' ' . rawurldecode( $post->post_name );
		$hit = array();
		foreach ( zad_warranty_policy() as $k => $row ) {
			if ( preg_match( $row[0], $hay ) ) { $hit[] = $k; }
		}
		$key = 1 === count( $hit ) ? $hit[0] : '';
	}
	return $memo[ $id ] = $key;
}

/** The fixed warranty text of this page's service, or '' (no policy). */
function zad_warranty_fixed( $id ) {
	$k = zad_warranty_key( $id );
	return $k ? zad_warranty_policy()[ $k ][1] : '';
}

/** Months of the fixed policy (0 = text only / no policy). */
function zad_warranty_months( $id ) {
	$k = zad_warranty_key( $id );
	return $k ? (int) zad_warranty_policy()[ $k ][2] : 0;
}

/** What to print as this page's warranty: the policy text, else the page's own `_zad_warranty`. */
function zad_warranty_text( $id ) {
	$f = zad_warranty_fixed( $id );
	return '' !== $f ? $f : trim( (string) get_post_meta( $id, '_zad_warranty', true ) );
}

/** Policy text without the leading «ضمان » (for places that already print «ضمان:»). */
function zad_warranty_bare( $id ) {
	return preg_replace( '/^ضمان\s+/u', '', zad_warranty_fixed( $id ) );
}

/** The package is authoritative. The service policy is only a fallback for an empty cell. */
function zad_warranty_pkg( $text, $id ) {
	$text = trim( (string) $text );
	return '' !== $text ? $text : zad_warranty_bare( $id );
}

/** WarrantyPromise-ready array( value, 'MON'|'ANN' ) for a package, or null (policy without a number, or no clear number). */
function zad_warranty_pkg_promise( $text, $id ) {
	$text = trim( (string) $text );
	if ( '' === $text ) { $text = zad_warranty_bare( $id ); }
	return function_exists( 'zad_pk_warranty' ) ? zad_pk_warranty( $text ) : null;
}

/** Compact lines of every policy (for the admin note and the DB tool). */
function zad_warranty_summary() {
	$o = array();
	foreach ( zad_warranty_policy() as $k => $row ) { $o[ $k ] = $row[1]; }
	return $o;
}
