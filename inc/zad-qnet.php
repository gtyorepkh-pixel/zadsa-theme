<?php
/**
 * «أسئلة تفصيلية ذات صلة» on service pages: cards linking to the independent question pages (zad_faq).
 * Source order: 1) the page's _zad_qnet list (IDs, kept as written) 2) automatic, from the matching faq_cat section
 * (pest / tanks / ac), ranked by the page's own pest/service keyword, then its city, then newest, minus questions that
 * duplicate the page's own FAQ 3) questions explicitly linked to this service (_zad_faq_services). Fewer than 2 = no section.
 * No FAQPage schema here (the question pages own theirs); the shown links go to WebPage.relatedLink instead.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** Which faq_cat section fits the page: [ kind, term-name, term-slug ] or null. */
function zad_qnet_section( $id ) {
	$style = function_exists( 'zad_pk_style' ) ? zad_pk_style( $id ) : 'clean';
	$map   = array(
		'pest' => array( 'pest', 'الأسئلة الشائعة عن مكافحة الحشرات', 'pest-control-faq' ),
		'tank' => array( 'tank', 'الأسئلة الشائعة عن تنظيف خزانات', 'tanks-faq' ),
		'ac'   => array( 'ac', 'الأسئلة الشائعة عن تنظيف مكيفات', 'ac-faq' ),
	);
	return $map[ $style ] ?? null;
}

/** Keyword group(s) of the page: pattern for a question title that talks about the same pest / service. */
function zad_qnet_keywords( $id ) {
	$p   = get_post( $id );
	$hay = $p ? urldecode( $p->post_name ) . ' ' . $p->post_title : '';
	$groups = array(
		'/بق\s|بق$|فراش|bed-?bug/iu'             => '/بق(?![\p{L}])|فراش|bed-?bug/iu',
		'/صراصير|صرصور|cockroach|roach/iu'      => '/صراصير|صرصور|cockroach|roach/iu',
		'/فئران|فأر|قوارض|rodent|rat(?![a-z])/iu' => '/فئران|فأر|قوارض|rodent|(?<![a-z])rat(?![a-z])/iu',
		'/نمل|أرضة|ارضة|termite|ant(?![a-z])/iu'  => '/نمل|أرضة|ارضة|termite|(?<![a-z])ants?(?![a-z])/iu',
		'/خزان|tank/iu'                          => '/خزان|tank/iu',
		'/مكيف|تكييف|air|(?<![a-z])ac(?![a-z])/iu' => '/مكيف|تكييف|air|(?<![a-z])ac(?![a-z])/iu',
	);
	$out = array();
	foreach ( $groups as $page_re => $q_re ) { if ( preg_match( $page_re, $hay ) ) { $out[] = $q_re; } }
	return $out;
}

/** Token overlap between a question title and the page's own FAQ questions (duplicate test). */
function zad_qnet_is_dup( $title, $own_tokens ) {
	$t = array_flip( zad_tokens( $title ) );
	if ( count( $t ) < 2 ) { return false; }
	foreach ( $own_tokens as $o ) {
		if ( count( $o ) < 2 ) { continue; }
		$common = count( array_intersect_key( $t, array_flip( $o ) ) );
		if ( $common / min( count( $t ), count( $o ) ) >= 0.7 ) { return true; }
	}
	return false;
}

/** array( 'items' => [ [id, title, url], … ], 'term' => [name, url]|null ) — cached 12h, cleared whenever a question/service is saved. */
function zad_qnet_data( $id ) {
	$key = 'zad_qnet_' . (int) get_option( 'zad_qnet_ver', 1 ) . '_' . (int) $id . ( function_exists( 'zad_city_key' ) ? '_' . zad_city_key( $id ) : '' ); // the page's city is part of the key
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) { return $hit; }
	$out = array( 'items' => array(), 'term' => null );
	$sec = zad_qnet_section( $id );
	$term = null;
	if ( $sec ) {
		$term = get_term_by( 'slug', $sec[2], 'faq_cat' ) ?: get_term_by( 'name', $sec[1], 'faq_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$out['term'] = array( trim( preg_replace( '/^الأسئلة الشائعة عن\s+/u', '', $term->name ) ), get_term_link( $term ) );
		} else { $term = null; }
	}
	$ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, '_zad_qnet', true ) ) ) ) );
	$posts = array();
	if ( $ids ) {
		$posts = get_posts( array( 'post_type' => zad_faq_types(), 'post__in' => $ids, 'orderby' => 'post__in', 'post_status' => 'publish', 'numberposts' => 6, 'suppress_filters' => true ) );
	} elseif ( $term ) {
		$cand = get_posts( array( 'post_type' => zad_faq_types(), 'post_status' => 'publish', 'numberposts' => 80, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => true,
			'tax_query' => array( array( 'taxonomy' => 'faq_cat', 'field' => 'term_id', 'terms' => array( (int) $term->term_id ) ) ) ) );
		$own = array();
		foreach ( (array) get_post_meta( $id, '_zad_faq', true ) as $r ) { if ( is_array( $r ) && ! empty( $r['q'] ) ) { $own[] = zad_tokens( $r['q'] ); } }
		$kws  = zad_qnet_keywords( $id );
		$city = function_exists( 'zad_current_city' ) ? zad_current_city( $id )['name'] : '';
		$rank = array();
		$csc = function_exists( 'zad_service_city_scope' ) ? zad_service_city_scope( $id ) : null;
		foreach ( $cand as $i => $p ) {
			if ( zad_qnet_is_dup( $p->post_title, $own ) ) { continue; }
			if ( null !== $csc && zad_city_mentions_other( $p->post_title, $csc ) ) { continue; } // a question about another city never fills this page's list
			$sc = 0;
			foreach ( $kws as $re ) { if ( preg_match( $re, $p->post_title ) ) { $sc += 3; break; } }
			if ( '' !== $city && false !== mb_strpos( $p->post_title, $city ) ) { $sc += 2; }
			$rank[] = array( $sc, $i, $p ); // $i keeps «newest first» inside the same score
		}
		usort( $rank, function ( $a, $b ) { return array( $b[0], $a[1] ) <=> array( $a[0], $b[1] ); } );
		foreach ( array_slice( $rank, 0, 6 ) as $r ) { $posts[] = $r[2]; }
	}
	if ( count( $posts ) < 2 && function_exists( 'zad_service_faqs' ) ) { // questions explicitly linked to this service
		$linked = zad_service_faqs( $id, 6 );
		$posts  = $linked->posts;
		if ( ! $ids && function_exists( 'zad_city_mentions_other' ) ) { $csc2 = zad_service_city_scope( $id ); $posts = array_values( array_filter( $posts, function ( $p ) use ( $csc2 ) { return ! zad_city_mentions_other( $p->post_title, $csc2 ); } ) ); }
	}
	foreach ( $posts as $p ) { $out['items'][] = array( (int) $p->ID, wp_strip_all_tags( get_the_title( $p ) ), get_permalink( $p ) ); }
	if ( count( $out['items'] ) < 2 ) { $out['items'] = array(); }
	set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

add_action( 'save_post', function ( $post_id ) {
	$pt = get_post_type( $post_id );
	if ( in_array( $pt, array_merge( zad_faq_types(), zad_service_types() ), true ) ) { update_option( 'zad_qnet_ver', time(), false ); }
} );
add_action( 'deleted_post', function () { update_option( 'zad_qnet_ver', time(), false ); } );
add_action( 'set_object_terms', function () { update_option( 'zad_qnet_ver', time(), false ); } );

function zad_qnet_html( $id ) {
	$d = zad_qnet_data( $id );
	if ( count( $d['items'] ) < 2 ) { return ''; }
	$style = function_exists( 'zad_pk_style' ) ? zad_pk_style( $id ) : 'clean';
	$eb    = trim( (string) get_post_meta( $id, '_zad_qnet_eyebrow', true ) ) ?: 'شبكة الأسئلة';
	$tt    = trim( (string) get_post_meta( $id, '_zad_qnet_title', true ) ) ?: 'أسئلة تفصيلية ذات صلة';
	$o  = '<section class="sec qnetsec qnetsec--' . esc_attr( $style ) . '"><div class="wrap"><header class="sec__head"><span class="eyebrow">' . zad_icon( 'check', 14 ) . ' ' . esc_html( $eb ) . '</span><h2>' . esc_html( $tt ) . '</h2></header><div class="qnet">';
	foreach ( $d['items'] as $it ) { $o .= '<a class="qnet__c" href="' . esc_url( $it[2] ) . '"><span>' . esc_html( $it[1] ) . '</span><i aria-hidden="true">‹</i></a>'; }
	$o .= '</div>';
	if ( $d['term'] && ! is_wp_error( $d['term'][1] ) ) { $o .= '<p class="qnet__all"><a href="' . esc_url( $d['term'][1] ) . '">كل أسئلة ' . esc_html( $d['term'][0] ) . '</a></p>'; }
	return $o . '</div></section>';
}

/** URLs of the shown questions (WebPage.relatedLink). */
function zad_qnet_links( $id ) {
	$d = zad_qnet_data( $id );
	return count( $d['items'] ) >= 2 ? array_values( wp_list_pluck( array_map( function ( $x ) { return array( 'u' => $x[2] ); }, $d['items'] ), 'u' ) ) : array();
}

/* ---------- editor ---------- */
function zad_qnet_box( $post_id ) {
	$g = function ( $k ) use ( $post_id ) { return (string) get_post_meta( $post_id, '_zad_' . $k, true ); };
	echo '<h4>أسئلة تفصيلية ذات صلة <small>(اختياري؛ فارغ = اختيار تلقائي من قسم الأسئلة المناسب)</small></h4><div class="zad-grid"><p><label>الوسم الصغير<input type="text" name="zad[qnet_eyebrow]" value="' . esc_attr( $g( 'qnet_eyebrow' ) ) . '" placeholder="شبكة الأسئلة"></label></p><p><label>العنوان<input type="text" name="zad[qnet_title]" value="' . esc_attr( $g( 'qnet_title' ) ) . '" placeholder="أسئلة تفصيلية ذات صلة"></label></p></div>';
	echo '<p><label>أرقام الأسئلة (IDs مفصولة بفاصلة، حتى 6، بالترتيب الذي تكتبه)<input type="text" name="zad[qnet]" value="' . esc_attr( $g( 'qnet' ) ) . '" dir="ltr" style="width:100%" placeholder="26101, 26102, 26107"></label></p>';
}
function zad_qnet_save( $post_id, $in ) {
	if ( ! array_key_exists( 'qnet', $in ) ) { return; }
	$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $in['qnet'] ) ) ) ) ), 0, 6 );
	update_post_meta( $post_id, '_zad_qnet', implode( ',', $ids ) );
	update_post_meta( $post_id, '_zad_qnet_title', sanitize_text_field( $in['qnet_title'] ?? '' ) );
	update_post_meta( $post_id, '_zad_qnet_eyebrow', sanitize_text_field( $in['qnet_eyebrow'] ?? '' ) );
}
