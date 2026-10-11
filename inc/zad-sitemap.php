<?php defined( 'ABSPATH' ) || exit;
/**
 * HTML sitemap (template "خريطة الموقع" and shortcode [zad_site_map]).
 * Sections, in this order: key pages · all services by category · services by city ·
 * question bank by topic · articles by topic. Neighbourhood pages are left out of the lists.
 */

add_action( 'save_post', function () { delete_transient( 'zad_sitemap_html' ); } );
add_action( 'created_term', function () { delete_transient( 'zad_sitemap_html' ); } );
add_action( 'edited_term', function () { delete_transient( 'zad_sitemap_html' ); } );

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
	$rows = zad_sm_row( home_url( '/' ), 'الرئيسية' ) . zad_sm_row( zad_services_url(), 'كل الخدمات' );
	if ( zad_faq_url() ) { $rows .= zad_sm_row( zad_faq_url(), 'الأسئلة الشائعة' ); }
	$self = get_the_ID();
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 80, 'orderby' => 'menu_order title', 'order' => 'ASC', 'post_parent' => 0 ) ) as $p ) {
		if ( (int) $p->ID === (int) $self || (int) get_option( 'page_on_front' ) === (int) $p->ID || '1' === (string) get_post_meta( $p->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) { continue; }
		if ( function_exists( 'zad_is_area_page' ) && zad_is_area_page( $p->ID ) ) { continue; }
		$rows .= zad_sm_row( get_permalink( $p ), wp_strip_all_tags( get_the_title( $p ) ) );
	}
	$o .= zad_sm_section( 'الأساسيات', 'الصفحات الرئيسية', $rows );

	/* 2. services by category */
	$all = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 400, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	$groups = array();
	foreach ( $all as $p ) {
		$t = get_the_terms( $p->ID, 'service_cat' );
		if ( $t && ! is_wp_error( $t ) ) { $k = 'c' . $t[0]->term_id; $n = $t[0]->name; $u = get_term_link( $t[0] ); }
		else { $po = get_post_type_object( $p->post_type ); $k = 'p' . $p->post_type; $n = $po ? $po->labels->name : 'خدمات أخرى'; $u = get_post_type_archive_link( $p->post_type ); }
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
	$cities = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => true, 'parent' => 0 ) );
	if ( $cities && ! is_wp_error( $cities ) ) {
		foreach ( $cities as $c ) {
			$n = count( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'service_area', 'terms' => $c->term_id ) ) ) ) );
			$rows .= zad_sm_row( get_term_link( $c ), $c->name, $n );
		}
	}
	$o .= zad_sm_section( 'التغطية', 'الخدمات حسب المدينة', $rows, true );

	/* 3b. our work */
	$rows = '';
	foreach ( get_posts( array( 'post_type' => 'zad_work', 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => true ) ) as $w ) { $rows .= zad_sm_row( get_permalink( $w ), wp_strip_all_tags( get_the_title( $w ) ) ); }
	if ( '' !== $rows && function_exists( 'zad_works_url' ) ) { $rows = zad_sm_row( zad_works_url(), 'كل أعمالنا' ) . $rows; }
	$o .= zad_sm_section( 'أعمالنا', 'قصص أعمال حقيقية', $rows, true );

	/* 4. question bank by topic */
	$rows = zad_faq_url() ? zad_sm_row( zad_faq_url(), 'كل الأسئلة' ) : '';
	foreach ( get_object_taxonomies( zad_faq_types(), 'objects' ) as $tx ) {
		if ( ! $tx->public ) { continue; }
		foreach ( (array) get_terms( array( 'taxonomy' => $tx->name, 'hide_empty' => true ) ) as $t ) {
			if ( $t instanceof WP_Term ) { $rows .= zad_sm_row( get_term_link( $t ), $t->name, $t->count ); }
		}
	}
	$o .= zad_sm_section( 'بنك المعلومات', 'بنك الأسئلة حسب الموضوع', $rows, true );

	/* 5. articles by topic */
	$rows = '';
	foreach ( zad_article_types() as $pt ) {
		$po = get_post_type_object( $pt );
		if ( $po && get_post_type_archive_link( $pt ) ) { $rows .= zad_sm_row( get_post_type_archive_link( $pt ), $po->labels->name ); }
		foreach ( get_object_taxonomies( $pt, 'objects' ) as $tx ) {
			if ( ! $tx->public || in_array( $tx->name, array( 'service_cat', 'service_area', 'faq_cat', 'post_format' ), true ) ) { continue; }
			foreach ( (array) get_terms( array( 'taxonomy' => $tx->name, 'hide_empty' => true ) ) as $t ) {
				if ( $t instanceof WP_Term ) { $rows .= zad_sm_row( get_term_link( $t ), $t->name, $t->count ); }
			}
		}
	}
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog ) { $rows .= zad_sm_row( get_permalink( $blog ), 'المدونة' ); }
	foreach ( (array) get_categories( array( 'hide_empty' => true ) ) as $t ) {
		if ( $t instanceof WP_Term && 1 !== (int) $t->term_id ) { $rows .= zad_sm_row( get_term_link( $t ), $t->name, $t->count ); }
	}
	$o .= zad_sm_section( 'المحتوى', 'التدوينات والأدلة حسب الموضوع', $rows, true );

	$o = '<div class="smx" data-smx><label class="smx__search"><span class="sr">ابحث في الخريطة</span>' . zad_icon( 'search', 18 ) . '<input type="search" placeholder="ابحث عن صفحة أو خدمة…" data-smx-search autocomplete="off"></label>' . $o . '<p class="smx__none" data-smx-none hidden>لا توجد نتائج. جرّب كلمة أخرى أو اطلب معاينة مجانية.</p></div>';
	set_transient( 'zad_sitemap_html', $o, 6 * HOUR_IN_SECONDS );
	return $o;
}
