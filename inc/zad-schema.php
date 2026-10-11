<?php defined( 'ABSPATH' ) || exit;
/**
 * Structured data (JSON-LD): ONE printer (the wp_head callback below) builds one @graph per request:
 *  Organization, WebSite(+SearchAction), LocalBusiness + the page's own nodes (Service / AboutPage / ContactPage / CollectionPage+ItemList /
 *  Article / ProfilePage…) + BreadcrumbList. Node builders: inc/zad-sc.php and inc/zad-schema-extra.php.
 *  FAQPage (service pages, home, question pages) stays a separate script.
 */

function zad_current_url() {
	if ( is_singular() ) {
		return get_permalink();
	}
	if ( is_tax() || is_category() || is_tag() ) {
		return get_term_link( get_queried_object() );
	}
	if ( is_post_type_archive() ) {
		return get_post_type_archive_link( zad_query_pt() );
	}
	if ( is_author() ) {
		return get_author_posts_url( get_queried_object_id() );
	}
	return home_url( '/' );
}

/** Breadcrumb trail for the current request: [ [label, url|''], ... ]. */
function zad_current_crumbs() {
	return apply_filters( 'zad_current_crumbs', zad_current_crumbs_base() );
}

function zad_current_crumbs_base() {
	$h = array( 'الرئيسية', home_url( '/' ) );
	if ( is_front_page() ) {
		return array();
	}
	if ( zad_is_service() ) {
		$id = get_queried_object_id();
		$c  = zad_service_crumbs( $id );
		return $c;
	}
	if ( zad_is_faq() ) {
		$c = array( $h, array( 'الأسئلة', zad_faq_url() ) );
		$t = get_the_terms( get_queried_object_id(), 'faq_cat' );
		if ( $t && ! is_wp_error( $t ) ) { $c[] = array( $t[0]->name, get_term_link( $t[0] ) ); }
		$c[] = array( get_the_title(), '' );
		return $c;
	}
	if ( zad_is_services_archive() ) {
		return array( $h, array( 'الخدمات', '' ) );
	}
	if ( zad_is_faq_archive() ) {
		return array( $h, array( 'الأسئلة', '' ) );
	}
	if ( is_tax( 'service_cat' ) || is_tax( 'service_area' ) ) {
		return array( $h, array( 'الخدمات', zad_services_url() ), array( single_term_title( '', false ), '' ) );
	}
	if ( is_tax( 'faq_cat' ) ) {
		return array( $h, array( 'الأسئلة', zad_faq_url() ), array( single_term_title( '', false ), '' ) );
	}
	if ( is_home() && ! is_front_page() ) {
		return array( $h, array( 'المدونة', '' ) );
	}
	if ( is_category() || is_tag() ) {
		return array( $h, array( 'المدونة', get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : '' ), array( single_term_title( '', false ), '' ) );
	}
	if ( is_author() ) {
		return array( $h, array( get_the_author_meta( 'display_name', get_queried_object_id() ), '' ) );
	}
	if ( zad_is_article() && ! is_singular( 'post' ) ) {
		$o = get_post_type_object( get_post_type() );
		return array( $h, array( $o ? $o->labels->name : '', get_post_type_archive_link( get_post_type() ) ?: '' ), array( get_the_title(), '' ) );
	}
	if ( is_post_type_archive( zad_article_types() ) ) {
		return array( $h, array( post_type_archive_title( '', false ), '' ) );
	}
	if ( is_singular( 'post' ) ) {
		$c = array( $h );
		$cat = get_the_category();
		if ( $cat ) { $c[] = array( $cat[0]->name, get_category_link( $cat[0] ) ); }
		$c[] = array( get_the_title(), '' );
		return $c;
	}
	if ( is_page() ) {
		return array( $h, array( get_the_title(), '' ) );
	}
	return array();
}

/** URL of the first page using a template (e.g. contact), or of a page by slug. */
function zad_page_url( $template, $slugs = array() ) {
	$q = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'meta_key' => '_wp_page_template', 'meta_value' => $template, 'fields' => 'ids' ) );
	if ( $q ) {
		return get_permalink( $q[0] );
	}
	foreach ( $slugs as $sl ) {
		$p = get_page_by_path( $sl );
		if ( $p ) {
			return get_permalink( $p );
		}
	}
	return '';
}

/** Print one @graph made of nodes. */
function zad_print_graph( $nodes ) {
	zad_print_schema( array( '@context' => 'https://schema.org', '@graph' => array_values( $nodes ) ) );
}

add_action( 'wp_head', function () {
	if ( is_404() || is_search() || 'theme' !== zad_schema_owner() ) {
		return;
	}
	$site  = zsc_site_nodes();
	$id    = is_singular() ? get_queried_object_id() : 0;
	$ptype = $id ? get_post_type( $id ) : '';

	if ( is_front_page() ) {
		$nodes = array_merge( $site, zsc_home_nodes() );
		zad_print_graph( $nodes );
	} elseif ( $id && in_array( $ptype, zad_service_types(), true ) && 'none' !== zsc_meta( $id, 'mode' ) ) {
		$nodes = zsc_service_nodes( $id );
		$kids  = zsc_hub_children( $id ); // a hub page (pillar / city with published children): CollectionPage whose list is the children; the Service node stays (about)
		$list  = zsc_itemlist_node( get_permalink( $id ), get_the_title( $id ), $kids, 'service' );
		if ( $list ) {
			$nodes[0]['@type']      = 'CollectionPage';
			$nodes[0]['mainEntity'] = array( '@id' => $list['@id'] );
			$nodes[]                = $list;
		}
		zad_print_graph( zsc_with_breadcrumb( array_merge( $site, $nodes ), get_permalink( $id ) ) );
	} elseif ( is_author() ) {
		$uid    = get_queried_object_id();
		$person = zsc_person_node( $uid );
		$aurl   = get_author_posts_url( $uid );
		$page   = array( '@type' => 'ProfilePage', '@id' => $aurl . '#webpage', 'url' => $aurl, 'name' => get_the_author_meta( 'display_name', $uid ), 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'inLanguage' => 'ar' );
		if ( $person ) { $page['mainEntity'] = array( '@id' => $person['@id'] ); }
		$nodes = array_merge( array_slice( $site, 0, 2 ), array( $page ) );
		if ( $person ) { $nodes[] = $person; }
		zad_print_graph( zsc_with_breadcrumb( $nodes, $aurl ) );
	} elseif ( $id && 'zad_work' === $ptype ) { // «أعمالنا»: WebPage + ImageObject + VideoObject (+ Clip parts) + FAQPage + breadcrumb
		zad_print_graph( zsc_with_breadcrumb( array_merge( array_slice( $site, 0, 2 ), zsc_work_nodes( $id ) ), get_permalink( $id ) ) );
	} elseif ( $id && zsc_is_article_page( $id ) ) {
		zad_print_graph( zsc_with_breadcrumb( array_merge( array_slice( $site, 0, 2 ), zsc_article_nodes( $id ) ), get_permalink( $id ) ) );
	} elseif ( $id ) {
		$u    = get_permalink( $id );
		$role = zad_page_role( $id );
		$name = wp_strip_all_tags( get_the_title( $id ) );
		$nodes = array_slice( $site, 0, 2 );
		if ( 'about' === $role ) { // mainEntity: the organization
			$wp = zsc_page_node( 'AboutPage', $u, $name, $id ); $wp['mainEntity'] = array( '@id' => home_url( '/#organization' ) ); $nodes = array_merge( $nodes, array( $wp ) );
		} elseif ( 'contact' === $role ) { // mainEntity: the business (its node is in the graph, so the @id resolves)
			$wp = zsc_page_node( 'ContactPage', $u, $name, $id ); $wp['mainEntity'] = array( '@id' => home_url( '/#localbusiness' ) ); $nodes = array_merge( $site, array( $wp ) );
		} elseif ( 'services' === $role ) {
			$wp   = zsc_page_node( 'CollectionPage', $u, $name, $id );
			$top  = get_posts( array( 'post_type' => zad_service_types(), 'post_parent' => 0, 'post_status' => 'publish', 'numberposts' => 40, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
			$list = zsc_itemlist_node( $u, $name, $top, 'service' );
			if ( $list ) { $wp['mainEntity'] = array( '@id' => $list['@id'] ); }
			$nodes = array_merge( $nodes, array( $wp ), $list ? array( $list ) : array() );
		} elseif ( 'prices' === $role ) {
			$wp  = zsc_page_node( 'WebPage', $u, $name, $id );
			$cat = zsc_prices_catalog( $u );
			if ( $cat ) { $wp['mainEntity'] = array( '@id' => $cat['@id'] ); }
			$nodes = array_merge( $nodes, array( $wp ), $cat ? array( $cat ) : array() );
		} else {
			$nodes = array_merge( $nodes, array( zsc_page_node( 'WebPage', $u, $name, $id ) ) );
		}
		$nodes = apply_filters( 'zad_schema_page_nodes', $nodes, $id, $role ); // extra nodes of this page (the zad-tools plugin adds WebApplication + FAQPage here)
		zad_print_graph( zsc_with_breadcrumb( $nodes, $u ) );
	} elseif ( $arch = zsc_archive_nodes() ) {
		zad_print_graph( zsc_with_breadcrumb( array_merge( array_slice( $site, 0, 2 ), $arch ), $arch[0]['url'] ) );
	} else {
		zad_print_graph( zsc_with_breadcrumb( array_slice( $site, 0, 2 ), zad_current_url() ) );
	}

	if ( zad_is_service() && ! ( function_exists( 'zad_hood_active' ) && zad_hood_active( get_queried_object_id() ) ) ) {
		$id = get_queried_object_id();
		$faq = array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $f ) { return ! empty( $f['q'] ); } );
		if ( $faq ) {
			$ents = array();
			foreach ( $faq as $f ) {
				$ents[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ) );
			}
			zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
		}
	}
	if ( is_front_page() ) {
		$faq = array_filter( (array) zad_opt( 'zad_faq', array() ), function ( $f ) { return ! empty( $f['q'] ); } );
		if ( $faq ) {
			$ents = array();
			foreach ( $faq as $f ) {
				$ents[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ?? '' ) );
			}
			zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
		}
	}
}, 20 );
