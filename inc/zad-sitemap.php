<?php defined( 'ABSPATH' ) || exit;
/**
 * HTML sitemap (template "خريطة الموقع" and shortcode [zad_site_map]).
 * Sections, in this order: key pages · all services by category · services by city ·
 * question bank by topic · articles by topic. Published links use the XML eligibility policy.
 */

/** Navigation URLs may resolve to a real page or a public archive. */
function zad_sm_public_row( $url, $label ) {
 if ( ! $url || is_wp_error( $url ) || zad_seo_url_redirected( $url ) ) { return ''; }
 $id = url_to_postid( $url );
 if ( $id && ! zad_seo_post_eligible( $id ) ) { return ''; }
 return zad_sm_row( $url, $label );
}

/** Use the same complete candidate pool as XML, then retain the HTML display order. */
function zad_sm_posts( $types, $args = array() ) {
 $ids = array();
 foreach ( (array) $types as $type ) { $ids = array_merge( $ids, zad_seo_eligible_ids( $type ) ); }
 if ( ! $ids ) { return array(); }
 $posts = get_posts( array_merge( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => -1,
  'post__in' => array_unique( $ids ), 'suppress_filters' => true, 'zad_all' => true ), $args ) );
 return array_values( array_filter( $posts, 'zad_seo_post_eligible' ) );
}

/** Respect the XML taxonomy filters as well as the shared term eligibility. */
function zad_sm_terms( $taxonomy, $args = array() ) {
 $provider = new WP_Sitemaps_Taxonomies();
 if ( ! isset( $provider->get_object_subtypes()[ $taxonomy ] ) ) { return array(); }
 $query = apply_filters( 'wp_sitemaps_taxonomies_query_args', array_merge( array( 'taxonomy' => $taxonomy,
  'hide_empty' => true, 'hierarchical' => false, 'number' => 0 ), $args ), $taxonomy );
 $terms = get_terms( $query );
 return is_wp_error( $terms ) ? array() : array_values( array_filter( $terms, 'zad_seo_term_eligible' ) );
}

function zad_sm_row( $url, $label, $count = null ) {
	if ( ! $url || is_wp_error( $url ) ) { return ''; }
	return '<li class="smx__row"><a href="' . esc_url( $url ) . '"><span class="smx__q">' . esc_html( $label ) . '</span>' . ( null !== $count ? '<span class="smx__n">' . (int) $count . '</span>' : '' ) . '</a></li>';
}

function zad_sm_section( $kick, $title, $rows, $sub = false ) {
	if ( '' === $rows ) { return ''; }
	return '<section class="smx__sec' . ( $sub ? ' smx__sec--sub' : '' ) . '"><header class="smx__head"><span class="eyebrow">' . esc_html( $kick ) . '</span><h2>' . esc_html( $title ) . '</h2></header><ul class="smx__rows">' . $rows . '</ul></section>';
}

function zad_sitemap_html() {
	$cached = get_transient( 'zad_sitemap_html' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$o = '';

	/* 1. key pages */
	$rows = zad_sm_public_row( home_url( '/' ), 'الرئيسية' ) . zad_sm_public_row( zad_services_url(), 'كل الخدمات' );
	if ( zad_faq_url() ) { $rows .= zad_sm_public_row( zad_faq_url(), 'الأسئلة الشائعة' ); }
	foreach ( zad_sm_posts( 'page', array( 'orderby' => 'menu_order title', 'order' => 'ASC', 'post_parent' => 0 ) ) as $p ) {
		if ( (int) get_option( 'page_on_front' ) === (int) $p->ID ) { continue; }
		$rows .= zad_sm_row( get_permalink( $p ), wp_strip_all_tags( get_the_title( $p ) ) );
	}
	$o .= zad_sm_section( 'الأساسيات', 'الصفحات الرئيسية', $rows );

	/* 2. services by category */
	$all = zad_sm_posts( zad_service_types(), array( 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	$groups = array();
	foreach ( $all as $p ) {
		$t = get_the_terms( $p->ID, 'service_cat' );
		if ( $t && ! is_wp_error( $t ) ) { $k = 'c' . $t[0]->term_id; $n = $t[0]->name; $u = zad_seo_term_eligible( $t[0] ) ? get_term_link( $t[0] ) : ''; }
		else { $po = get_post_type_object( $p->post_type ); $k = 'p' . $p->post_type; $n = $po ? $po->labels->name : 'خدمات أخرى'; $u = get_post_type_archive_link( $p->post_type ); }
		if ( $u && ! is_wp_error( $u ) && zad_seo_url_redirected( $u ) ) { $u = ''; }
		if ( ! isset( $groups[ $k ] ) ) { $groups[ $k ] = array( 'n' => $n, 'u' => $u, 'rows' => '' ); }
		$groups[ $k ]['rows'] .= zad_sm_row( get_permalink( $p ), wp_strip_all_tags( get_the_title( $p ) ) );
	}
	if ( $groups ) {
		$o .= '<div class="smx__sec"><header class="smx__head"><span class="eyebrow">الخدمات</span><h2>كل الخدمات حسب الفئة</h2></header>';
		foreach ( $groups as $g ) {
			$o .= '<h3 class="smx__h3">' . ( $g['u'] && ! is_wp_error( $g['u'] ) ? '<a href="' . esc_url( $g['u'] ) . '">' . esc_html( $g['n'] ) . '</a>' : esc_html( $g['n'] ) ) . '</h3><ul class="smx__rows">' . $g['rows'] . '</ul>';
		}
		$o .= '</div>';
	}

	/* 3. services by city */
	$rows = '';
	$cities = zad_sm_terms( 'service_area', array( 'parent' => 0 ) );
	if ( $cities && ! is_wp_error( $cities ) ) {
		foreach ( $cities as $c ) {
			$n = count( zad_sm_posts( zad_service_types(), array( 'tax_query' => array( array( 'taxonomy' => 'service_area', 'terms' => $c->term_id ) ) ) ) );
			$rows .= zad_sm_row( get_term_link( $c ), $c->name, $n );
		}
	}
	$o .= zad_sm_section( 'التغطية', 'الخدمات حسب المدينة', $rows, true );

	/* 3b. our work */
	$rows = '';
	foreach ( zad_sm_posts( 'zad_work', array( 'orderby' => 'date', 'order' => 'DESC' ) ) as $w ) { $rows .= zad_sm_row( get_permalink( $w ), wp_strip_all_tags( get_the_title( $w ) ) ); }
	if ( '' !== $rows && function_exists( 'zad_works_url' ) ) { $rows = zad_sm_public_row( zad_works_url(), 'كل أعمالنا' ) . $rows; }
	$o .= zad_sm_section( 'أعمالنا', 'قصص أعمال حقيقية', $rows, true );

	/* 4. question bank by topic */
	$rows = zad_faq_url() ? zad_sm_public_row( zad_faq_url(), 'كل الأسئلة' ) : '';
	foreach ( get_object_taxonomies( zad_faq_types(), 'objects' ) as $tx ) {
		if ( ! $tx->public ) { continue; }
		foreach ( zad_sm_terms( $tx->name ) as $t ) {
			if ( $t instanceof WP_Term ) { $rows .= zad_sm_row( get_term_link( $t ), $t->name, $t->count ); }
		}
	}
	$o .= zad_sm_section( 'بنك المعلومات', 'بنك الأسئلة حسب الموضوع', $rows, true );

	/* 5. articles by topic */
	$rows = '';
	foreach ( zad_article_types() as $pt ) {
		$po = get_post_type_object( $pt );
		if ( $po && get_post_type_archive_link( $pt ) ) { $rows .= zad_sm_public_row( get_post_type_archive_link( $pt ), $po->labels->name ); }
		foreach ( get_object_taxonomies( $pt, 'objects' ) as $tx ) {
			if ( ! $tx->public || in_array( $tx->name, array( 'service_cat', 'service_area', 'faq_cat', 'post_format' ), true ) ) { continue; }
			foreach ( zad_sm_terms( $tx->name ) as $t ) {
				if ( $t instanceof WP_Term ) { $rows .= zad_sm_row( get_term_link( $t ), $t->name, $t->count ); }
			}
		}
	}
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog && zad_seo_post_eligible( $blog ) ) { $rows .= zad_sm_row( get_permalink( $blog ), 'المدونة' ); }
	foreach ( zad_sm_terms( 'category' ) as $t ) {
		if ( $t instanceof WP_Term && 1 !== (int) $t->term_id ) { $rows .= zad_sm_row( get_term_link( $t ), $t->name, $t->count ); }
	}
	$o .= zad_sm_section( 'المحتوى', 'التدوينات والأدلة حسب الموضوع', $rows, true );

	$o = '<div class="smx" data-smx><label class="smx__search"><span class="sr">ابحث في الخريطة</span>' . zad_icon( 'search', 18 ) . '<input type="search" placeholder="ابحث عن صفحة أو خدمة…" data-smx-search autocomplete="off"></label>' . $o . '<p class="smx__none" data-smx-none hidden>لا توجد نتائج. جرّب كلمة أخرى أو اطلب معاينة مجانية.</p></div>';
	set_transient( 'zad_sitemap_html', $o, 6 * HOUR_IN_SECONDS );
	return $o;
}
