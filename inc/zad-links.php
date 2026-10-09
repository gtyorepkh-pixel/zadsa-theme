<?php
/**
 * Internal-link components (all manual or strict; nothing is auto-created, nothing falls back to «latest»):
 *  - anchor bank per service page (`_zad_anchors`) + zad_anchor_for(): one fixed wording per source page, never random
 *  - «البطاقة الفنية» (visible table + Service.additionalProperty) from the page's own `_zad_spec` lines only
 *  - «أدلة تهمّك» (hand-picked guides, optional strict same-category match), «نفس الخدمة في مدن أخرى» (hand-picked)
 *  - «الخدمة المرتبطة» box + button for FAQ / guide / article pages (uses the existing link fields)
 *  - footer link lists
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/* ================= 1) anchor bank ================= */

/** Short wording of a page: Yoast breadcrumb title, else the title without «|» and what follows, without phone numbers. */
function zad_anchor_base( $id ) {
	$bc = trim( wp_strip_all_tags( (string) get_post_meta( $id, '_yoast_wpseo_bctitle', true ) ) );
	$t  = '' !== $bc ? $bc : (string) preg_split( '/\s*\|\s*/u', wp_strip_all_tags( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ), 2 )[0];
	$t  = zad_strip_phones( $t );
	$t  = preg_replace( '/^[\s\-–—|،,:·]+|[\s\-–—|،,:·]+$/u', '', preg_replace( '/\s+/u', ' ', $t ) ); // not trim(): its byte list would corrupt Arabic
	return '' !== $t ? $t : zad_card_title( $id );
}

/** The page's wording variants (one per line of `_zad_anchors`); none written = the short title only. */
function zad_anchor_variants( $id ) {
	$v = array();
	foreach ( zad_lines( (string) get_post_meta( $id, '_zad_anchors', true ) ) as $l ) {
		$l = preg_replace( '/^[\s\-–—|،,:·]+|[\s\-–—|،,:·]+$/u', '', preg_replace( '/\s+/u', ' ', zad_strip_phones( $l ) ) );
		if ( '' !== $l && ! in_array( $l, $v, true ) ) { $v[] = $l; }
	}
	return $v ? $v : array( zad_anchor_base( $id ) );
}

/** Wording used by $source_id when it links to $target_id: fixed per source page (variants[ source % count ]). */
function zad_anchor_for( $target_id, $source_id ) {
	$v = zad_anchor_variants( (int) $target_id );
	return $v[ (int) $source_id % count( $v ) ];
}

function zad_anchors_box( $post_id ) {
	echo '<h4>صيغ الرابط <small>(كل سطر صيغة؛ تُستخدم كنص الرابط عند ربط صفحات أخرى بهذه الصفحة، وكل صفحة مصدر تأخذ صيغة ثابتة. فارغ = العنوان المختصر)</small></h4>';
	echo '<p><textarea name="zad[anchors]" rows="3" style="width:100%" placeholder="صيغة 1&#10;صيغة 2&#10;صيغة 3">' . esc_textarea( (string) get_post_meta( $post_id, '_zad_anchors', true ) ) . '</textarea></p>';
}

/* ================= 2) fields: audience + same service in other cities ================= */

function zad_links_box( $post_id ) {
	$sel = array_map( 'intval', (array) get_post_meta( $post_id, '_zad_cities_svc', true ) );
	echo '<h4>الجمهور المستهدف <small>(اختياري؛ يظهر في بيانات Google فقط)</small></h4>';
	echo '<p><input type="text" name="zad[audience]" value="' . esc_attr( (string) get_post_meta( $post_id, '_zad_audience', true ) ) . '" style="width:100%"></p>';
	echo '<h4>نفس الخدمة في مدن أخرى <small>(اختيار يدوي، حتى 8؛ فارغ = لا يظهر القسم)</small></h4><input type="hidden" name="zad[cities_svc_present]" value="1"><select name="zad[cities_svc][]" multiple size="8" style="width:100%">';
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 400, 'orderby' => 'title', 'order' => 'ASC', 'post__not_in' => array( $post_id ) ) ) as $s ) {
		echo '<option value="' . (int) $s->ID . '"' . ( in_array( (int) $s->ID, $sel, true ) ? ' selected' : '' ) . '>' . esc_html( $s->post_title ) . '</option>';
	}
	echo '</select>';
}

function zad_links_save( $post_id, $in ) {
	if ( array_key_exists( 'anchors', $in ) ) { update_post_meta( $post_id, '_zad_anchors', sanitize_textarea_field( $in['anchors'] ) ); }
	if ( array_key_exists( 'audience', $in ) ) { update_post_meta( $post_id, '_zad_audience', sanitize_text_field( $in['audience'] ) ); }
	if ( ! empty( $in['cities_svc_present'] ) ) {
		$ids = isset( $in['cities_svc'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $in['cities_svc'] ) ) ) ) : array();
		update_post_meta( $post_id, '_zad_cities_svc', array_slice( array_diff( $ids, array( (int) $post_id ) ), 0, 8 ) );
	}
}

/* ================= 3) technical card (visible table + schema) ================= */

/** Rows of «البطاقة الفنية»: only what the page's own `_zad_spec` lines say («الاسم | القيمة»). Empty = no section, no schema. */
function zad_spec_rows( $id ) {
	$rows = array();
	foreach ( zad_lines( (string) get_post_meta( $id, '_zad_spec', true ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l, 2 ) );
		if ( 2 === count( $c ) && '' !== $c[0] && '' !== $c[1] ) { $rows[] = array( $c[0], $c[1] ); }
	}
	$fixed = zad_warranty_bare( $id ); // a service with a fixed warranty policy: its «الضمان» row always shows the policy (without the repeated word «ضمان»)
	if ( '' !== $fixed ) {
		$done = false;
		foreach ( $rows as $i => $r ) {
			if ( preg_match( '/^(مدة\s+)?الضمان/u', $r[0] ) ) { $rows[ $i ][1] = $fixed; $done = true; }
		}
		if ( ! $done && $rows ) { $rows[] = array( 'الضمان', $fixed ); }
	}
	return $rows;
}

function zad_spec_html( $id ) {
	$rows = zad_spec_rows( $id );
	if ( ! $rows ) { return ''; }
	$o = '<section class="sec sec--mint"><div class="wrap wrap--narrow">' . zad_sec_head( $id, 'spec', 'check' ) . '<div class="tbl"><table><tbody>';
	foreach ( $rows as $r ) { $o .= '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . esc_html( $r[1] ) . '</td></tr>'; }
	return $o . '</tbody></table></div></div></section>';
}

/* ================= 4) guides (hand-picked, optional strict match) ================= */

/** The «أدلة تهمّك» block. '' when nothing was picked (and the strict auto-match, if switched on, found nothing). */
function zad_guides_html( $id ) {
	$gp = zad_service_guides( $id, 4 );
	if ( ! $gp ) { return ''; }
	$svc = zad_svc_label( $id );
	ob_start();
	echo '<section class="sec"><div class="wrap"><header class="sec__head"><span class="eyebrow">أدلة تهمّك</span><h2>' . esc_html( 'أدلة تهمّك عن ' . $svc ) . '</h2></header><div class="sgrid">';
	foreach ( $gp as $gpost ) { $GLOBALS['post'] = $gpost; setup_postdata( $gpost ); get_template_part( 'template-parts/post-card' ); }
	wp_reset_postdata();
	echo '</div>';
	$t = get_the_terms( $id, 'service_cat' );
	$u = ( $t && ! is_wp_error( $t ) ) ? get_term_link( $t[0] ) : '';
	if ( $u && ! is_wp_error( $u ) ) { echo '<p class="sec__more"><a class="btn btn--ghost-dark" href="' . esc_url( $u ) . '">' . esc_html( 'كل أدلة ' . $svc ) . '</a></p>'; }
	echo '</div></section>';
	return ob_get_clean();
}

/* ================= 5) same service in other cities (hand-picked) ================= */

function zad_other_cities_html( $id ) {
	$items = array();
	foreach ( array_slice( array_filter( array_map( 'intval', (array) get_post_meta( $id, '_zad_cities_svc', true ) ) ), 0, 8 ) as $t ) {
		if ( $t === (int) $id || 'publish' !== get_post_status( $t ) || ! in_array( get_post_type( $t ), zad_service_types(), true ) ) { continue; }
		$a    = zad_anchor_for( $t, $id );
		$city = zad_current_city( $t )['name'];
		if ( '' !== $city && false === mb_strpos( $a, $city ) ) { $a .= ' في ' . $city; } // wording of the service + the city name
		$items[ $t ] = '<a href="' . esc_url( get_permalink( $t ) ) . '"><span>' . esc_html( $a ) . '</span>' . zad_icon( 'arrow', 18 ) . '</a>';
	}
	if ( ! $items ) { return ''; }
	return '<section class="sec"><div class="wrap wrap--narrow"><header class="sec__head"><span class="eyebrow">مدن أخرى</span><h2>نفس الخدمة في مدن أخرى</h2></header><div class="faqlinks">' . implode( '', $items ) . '</div></div></section>';
}

/* ================= 6) «الخدمة المرتبطة» on FAQ / guide / article pages ================= */

/** The one published service page a FAQ / guide / article points to (existing fields: _zad_faq_services, _zad_post_service). */
function zad_linked_service( $post_id ) {
	if ( in_array( get_post_type( $post_id ), zad_faq_types(), true ) ) {
		$s   = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, '_zad_faq_services', true ) ) ) );
		$sid = $s ? $s[0] : 0;
	} else {
		$sid = (int) get_post_meta( $post_id, '_zad_post_service', true );
	}
	return ( $sid && 'publish' === get_post_status( $sid ) && in_array( get_post_type( $sid ), zad_service_types(), true ) ) ? $sid : 0;
}

/** (أ) fixed box inside the content. */
function zad_link_box_html( $post_id ) {
	$sid = zad_linked_service( $post_id );
	if ( ! $sid ) { return ''; }
	return '<aside class="svc-link-box"><strong>تحتاج حل احترافي؟</strong> <a href="' . esc_url( get_permalink( $sid ) ) . '">' . esc_html( zad_anchor_for( $sid, $post_id ) ) . '</a></aside>';
}

/** (ب) button at the end of the page. */
function zad_link_button_html( $post_id ) {
	$sid = zad_linked_service( $post_id );
	if ( ! $sid ) { return ''; }
	return '<p class="svc-link-btn"><a class="btn btn--accent" href="' . esc_url( get_permalink( $sid ) ) . '">' . esc_html( 'تعرّف على خدمة ' . zad_card_title( $sid ) ) . '</a></p>';
}

/** The page content with the (أ) box after its first section (before the 2nd H2; else after the 2nd paragraph; else at the end). */
function zad_content_with_box( $post_id ) {
	ob_start();
	the_content();
	$html = ob_get_clean();
	$box  = zad_link_box_html( $post_id );
	if ( '' === $box ) { return $html; }
	if ( preg_match_all( '/<h2\b/i', $html, $m, PREG_OFFSET_CAPTURE ) && count( $m[0] ) >= 2 ) { $pos = $m[0][1][1]; }
	elseif ( preg_match_all( '#</p>#i', $html, $m, PREG_OFFSET_CAPTURE ) && count( $m[0] ) >= 2 ) { $pos = $m[0][1][1] + 4; }
	else { return $html . $box; }
	return substr_replace( $html, $box, $pos, 0 );
}

/* ================= 7) footer ================= */

function zad_has_posts( $types ) {
	return (bool) get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
}

/** «روابط مهمة» when no menu is assigned: only pages that exist and are published; the privacy page is left to the legal links (never twice). */
function zad_footer_important_fallback() {
	$out = array();
	if ( zad_has_posts( zad_service_types() ) ) { $out[] = array( 'الخدمات', zad_services_url() ); }
	if ( zad_has_posts( zad_faq_types() ) ) { $out[] = array( 'الأسئلة الشائعة', zad_faq_url() ); }
	foreach ( array( array( 'خريطة الموقع', 'temp/zad-sitemap.php', array( 'site-map' ) ), array( 'من نحن', 'temp/memo-about.php', array( 'about' ) ), array( 'اتصل بنا', 'temp/memo-contact.php', array( 'contact' ) ) ) as $p ) {
		$u = zad_page_url( $p[1], $p[2] );
		if ( $u ) { $out[] = array( $p[0], $u ); }
	}
	$legal = zad_legal_items();
	if ( ! has_nav_menu( 'legalmenu' ) && ! $legal ) {
		$pp = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $pp && 'publish' === get_post_status( $pp ) ) { $out[] = array( 'سياسة الخصوصية', get_permalink( $pp ) ); }
	}
	return $out;
}

/** «المدن» when no menu is assigned: the top-level cities (service_area) that have published pages. */
function zad_footer_cities_fallback() {
	$out = array();
	$ts  = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => true, 'parent' => 0, 'number' => 12 ) );
	if ( $ts && ! is_wp_error( $ts ) ) {
		foreach ( $ts as $t ) { $u = get_term_link( $t ); if ( $u && ! is_wp_error( $u ) ) { $out[] = array( $t->name, $u ); } }
	}
	return $out;
}

function zad_footer_list( $items ) {
	if ( ! $items ) { return ''; }
	$o = '<ul class="ftr__list">';
	foreach ( $items as $i ) { $o .= '<li><a href="' . esc_url( $i[1] ) . '">' . esc_html( $i[0] ) . '</a></li>'; }
	return $o . '</ul>';
}

/** Educational-content hub (setting «العنوان | الرابط»): empty = nothing. */
function zad_footer_edu_item() {
	$l = zad_link_lines( 'zad_edu_hub' );
	return ( $l && '' !== $l[0]['url'] ) ? array( $l[0]['name'], $l[0]['url'] ) : null;
}
