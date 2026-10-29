<?php defined( 'ABSPATH' ) || exit;
/**
 * Sections (3.46.0): the data behind the section design (hub-section.php) and /services/ (services-index.php).
 * A section is the archive of a hierarchical service type (/cleaning/, /pest-control/) or a service page that has pages under it
 * (/cleaning/tanks/, /services/furniture-moving/). Everything is read from the published pages; nothing is typed twice.
 * Cached per section with the city version (changes whenever a service page is saved) + the question/works versions.
 */

/** id => array( pt, parent, order, title, name ) for every published page of the hierarchical service types. */
function zad_sec_index() {
	static $mem = null;
	if ( null !== $mem ) { return $mem; }
	$key = 'zad_sec_idx_' . zad_city_ver() . '_' . md5( ZAD_VERSION );
	$mem = get_transient( $key );
	if ( is_array( $mem ) ) { return $mem; }
	$mem = array();
	foreach ( get_posts( array( 'post_type' => zad_hub_pt_hier(), 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true, 'no_found_rows' => true, 'zad_all' => true, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $p ) {
		$mem[ (int) $p->ID ] = array( 'pt' => $p->post_type, 'parent' => (int) $p->post_parent, 'order' => (int) $p->menu_order, 'title' => $p->post_title, 'name' => rawurldecode( $p->post_name ) );
	}
	set_transient( $key, $mem, 12 * HOUR_IN_SECONDS );
	return $mem;
}

/** Published pages directly under a page (or at the top of a type when $id = 0). */
function zad_sec_children( $pt, $id ) {
	$out = array();
	foreach ( zad_sec_index() as $pid => $r ) { if ( $r['pt'] === $pt && $r['parent'] === (int) $id ) { $out[] = $pid; } }
	return $out;
}

/** Every published page below a page (or the whole type when $id = 0), nearest first. */
function zad_sec_tree( $pt, $id ) {
	$out = array(); $queue = array( (int) $id ); $guard = 0;
	while ( $queue && $guard++ < 2000 ) {
		$cur = array_shift( $queue );
		foreach ( zad_sec_children( $pt, $cur ) as $c ) { $out[] = $c; $queue[] = $c; }
	}
	return $out;
}

function zad_sec_is_section( $id ) { return function_exists( 'zad_page_kind' ) && 'section' === zad_page_kind( $id ); }

/** A page that is neither a section nor tied to a city (e.g. /cleaning/how-to-choose-a-tank-company/) reads as a guide, not as a service. */
function zad_sec_is_guide( $id ) {
	if ( zad_sec_is_section( $id ) ) { return false; }
	$sc = zad_service_city_scope( $id );
	return in_array( $sc, array( '', 'all' ), true ) && (bool) preg_match( '/^\s*(?:كيف|خطوات|دليل|ما\s|ماذا|متى|هل|لماذا|طريقة|أسباب|اسباب|نصائح|أفضل طريقة)/u', get_the_title( $id ) );
}

/** Short name for links and trails: «أفضل شركة تنظيف خزانات بالرياض 150 ريال غسيل الخزان» → «تنظيف خزانات بالرياض». */
function zad_sec_short( $title ) {
	$t = function_exists( 'zad_bc_clean_label' ) ? zad_bc_clean_label( $title ) : wp_strip_all_tags( (string) $title );
	if ( function_exists( 'zad_anchor_trim' ) ) { $t = zad_anchor_trim( $t ); }
	$w = preg_split( '/\s+/u', trim( $t ), -1, PREG_SPLIT_NO_EMPTY );
	while ( count( $w ) > 3 && in_array( $w[0], array( 'أفضل', 'افضل', 'أرخص', 'ارخص', 'أحسن', 'احسن' ), true ) ) { array_shift( $w ); }
	while ( count( $w ) > 3 && in_array( $w[0], array( 'شركة', 'شركات', 'خدمة', 'خدمات' ), true ) ) { array_shift( $w ); }
	$t = implode( ' ', $w );
	return '' !== $t ? $t : wp_strip_all_tags( (string) $title );
}

/** Name of a section: «نوع الصفحة» H1 / bread-crumb label, else the cleaned title. */
function zad_sec_name( $node ) {
	if ( 'type' === $node['kind'] ) { return zad_hub_type_h1( $node['pt'] ); }
	$id = (int) $node['id'];
	$h  = trim( (string) get_post_meta( $id, ZAD_HUB_H1, true ) );
	if ( '' !== $h ) { return $h; }
	$l = trim( (string) get_post_meta( $id, defined( 'ZAD_BC_LABEL' ) ? ZAD_BC_LABEL : '_zad_breadcrumb_label', true ) );
	if ( '' !== $l ) { return $l; }
	$c = zad_hub_clean_name( get_the_title( $id ) );
	return '' !== $c ? $c : zad_sec_short( get_the_title( $id ) );
}

/** city slug => array( url, id ) for the pages below a section; the city's own landing page (slug = city) wins, then the nearest. */
function zad_sec_city_cells( $pt, $id ) {
	$cells = array(); $rank = array();
	foreach ( zad_sec_tree( $pt, $id ) as $i => $pid ) {
		if ( zad_sec_is_section( $pid ) ) { continue; } // districts come after their city page in the tree, so the city page wins
		$sc = zad_service_city_scope( $pid );
		if ( in_array( $sc, array( '', 'all' ), true ) ) { continue; }
		$r = ( zad_sec_index()[ $pid ]['name'] === $sc ? 0 : 1000 ) + $i;
		if ( ! isset( $rank[ $sc ] ) || $r < $rank[ $sc ] ) { $rank[ $sc ] = $r; $cells[ $sc ] = array( get_permalink( $pid ), $pid ); }
	}
	return $cells;
}

/** The sections of the site, flat, in menu order: each type's archive, then the sections under it (two levels), then /services/ sections. */
function zad_sec_nodes() {
	$out = array();
	foreach ( zad_hub_pt_hier() as $pt ) {
		$o = get_post_type_object( $pt );
		$tops = zad_sec_children( $pt, 0 );
		if ( ! $tops ) { continue; }
		if ( 'zad_service' !== $pt && get_post_type_archive_link( $pt ) ) {
			$out[] = array( 'kind' => 'type', 'pt' => $pt, 'id' => 0, 'url' => get_post_type_archive_link( $pt ) );
		}
		foreach ( $tops as $t ) {
			if ( zad_sec_is_section( $t ) ) { $out[] = array( 'kind' => 'page', 'pt' => $pt, 'id' => $t, 'url' => get_permalink( $t ) ); }
			foreach ( zad_sec_children( $pt, $t ) as $c ) { if ( zad_sec_is_section( $c ) ) { $out[] = array( 'kind' => 'page', 'pt' => $pt, 'id' => $c, 'url' => get_permalink( $c ) ); } }
		}
	}
	return $out;
}

/** Services directly under a section (sub-sections excluded: they are their own cards), guides apart. */
function zad_sec_members( $node ) {
	$svc = array(); $guides = array(); $subs = array();
	foreach ( zad_sec_children( $node['pt'], (int) $node['id'] ) as $c ) {
		if ( zad_sec_is_section( $c ) ) { $subs[] = $c; }
		elseif ( zad_sec_is_guide( $c ) ) { $guides[] = $c; }
		else { $svc[] = $c; }
	}
	return array( $svc, $subs, $guides );
}

/** Short lead of a section (≤ 30 words): the page excerpt / first paragraph, the hub settings lead, else ''. */
function zad_sec_desc( $node, $words = 30 ) {
	if ( 'type' === $node['kind'] ) {
		$hub = zad_hub_opt( $node['pt'] );
		return ! empty( $hub['lead'] ) ? zad_hub_limit_words( $hub['lead'], $words ) : '';
	}
	$p = get_post( $node['id'] );
	if ( ! $p ) { return ''; }
	$t = trim( (string) get_post_meta( $p->ID, '_zad_tagline', true ) );
	if ( '' === $t ) { $t = trim( wp_strip_all_tags( (string) $p->post_excerpt ) ); }
	if ( '' === $t ) { $t = zad_hub_first_para( $p->post_content, $words ); }
	return zad_hub_limit_words( $t, $words );
}

/** Image of a page: featured image, else the first photo of its gallery / before-after. */
function zad_sec_image( $id ) {
	$t = (int) get_post_thumbnail_id( $id );
	if ( $t ) { return $t; }
	foreach ( array( '_zad_gallery', '_zad_ba' ) as $k ) {
		$ids = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, $k, true ) ) ) );
		if ( $ids ) { return (int) reset( $ids ); }
	}
	return 0;
}

/** Works (zad_work) tied to any page of a section, newest first. */
function zad_sec_works( $ids, $n = 6 ) {
	if ( ! $ids || ! defined( 'ZAD_WK' ) ) { return array(); }
	return get_posts( array( 'post_type' => ZAD_WK, 'post_status' => 'publish', 'numberposts' => $n, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => true,
		'meta_query' => array( array( 'key' => '_zad_wk_service', 'value' => array_map( 'intval', $ids ), 'compare' => 'IN' ) ) ) );
}

/** Before/after pairs and photos from the service pages of a section: array( 'ba' => [ [before, after, title, url] ], 'img' => [ [id, title, url] ] ). */
function zad_sec_media( $ids, $max_ba = 6, $max_img = 8 ) {
	$ba = array(); $img = array();
	foreach ( $ids as $id ) {
		$pairs = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, '_zad_ba', true ) ) ) ) );
		$txt   = zad_lines( get_post_meta( $id, '_zad_ba_text', true ) );
		for ( $i = 0; $i + 1 < count( $pairs ) && count( $ba ) < $max_ba; $i += 2 ) { $ba[] = array( $pairs[ $i ], $pairs[ $i + 1 ], $txt[ $i / 2 ] ?? zad_sec_short( get_the_title( $id ) ), get_permalink( $id ) ); }
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, '_zad_gallery', true ) ) ) ) as $a ) {
			if ( count( $img ) >= $max_img ) { break; }
			$img[] = array( $a, (string) ( wp_get_attachment_caption( $a ) ?: zad_sec_short( get_the_title( $id ) ) ), get_permalink( $id ) );
		}
		if ( count( $ba ) >= $max_ba && count( $img ) >= $max_img ) { break; }
	}
	return array( 'ba' => $ba, 'img' => $img );
}

/** Question pages linked to the section's pages (_zad_faq_services), else the matching question category. */
function zad_sec_faqs( $ids, $term = null, $n = 6 ) {
	$out = array();
	if ( $ids ) {
		$mq = array( 'relation' => 'OR' );
		foreach ( array_slice( $ids, 0, 60 ) as $i ) { $mq[] = array( 'key' => '_zad_faq_services', 'value' => '"' . (int) $i . '"', 'compare' => 'LIKE' ); }
		$out = get_posts( array( 'post_type' => zad_faq_types(), 'post_status' => 'publish', 'numberposts' => $n, 'suppress_filters' => true, 'meta_query' => $mq ) );
	}
	if ( count( $out ) < 3 && $term instanceof WP_Term ) {
		$more = get_posts( array( 'post_type' => zad_faq_types(), 'post_status' => 'publish', 'numberposts' => $n, 'suppress_filters' => true, 'post__not_in' => wp_list_pluck( $out, 'ID' ),
			'tax_query' => array( array( 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => (int) $term->term_id ) ) ) );
		$out = array_slice( array_merge( $out, $more ), 0, $n );
	}
	return $out;
}

/** Articles tied to the section's pages (_zad_post_service), else the matching article category; plus the section's own guide pages. */
function zad_sec_articles( $ids, $term = null, $guides = array(), $n = 6 ) {
	$out = array_map( 'get_post', array_slice( $guides, 0, $n ) );
	if ( $ids && count( $out ) < $n ) {
		$out = array_merge( $out, get_posts( array( 'post_type' => zad_article_types(), 'post_status' => 'publish', 'numberposts' => $n - count( $out ), 'suppress_filters' => true, 'ignore_sticky_posts' => true,
			'meta_query' => array( array( 'key' => '_zad_post_service', 'value' => array_map( 'intval', $ids ), 'compare' => 'IN' ) ) ) ) );
	}
	if ( count( $out ) < 3 && $term instanceof WP_Term ) {
		$out = array_merge( $out, get_posts( array( 'post_type' => zad_article_types(), 'post_status' => 'publish', 'numberposts' => $n - count( $out ), 'suppress_filters' => true, 'ignore_sticky_posts' => true, 'post__not_in' => array_filter( wp_list_pluck( $out, 'ID' ) ),
			'tax_query' => array( array( 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => (int) $term->term_id ) ) ) ) );
	}
	return array_values( array_filter( $out ) );
}

/** Lowest «from» price among pages. */
function zad_sec_min_price( $ids ) {
	$min = 0;
	foreach ( $ids as $id ) {
		$m = zad_min_price( zad_price_rows( $id ) ) ?: (int) get_post_meta( $id, '_zad_price', true );
		if ( $m > 0 && ( ! $min || $m < $min ) ) { $min = $m; }
	}
	return $min;
}

/** The context of the current section request as a node (type archive or section page), null otherwise. */
function zad_sec_current_node() {
	$ctx = zad_hub_ctx();
	if ( ! $ctx ) { return null; }
	if ( 'type' === $ctx['kind'] ) { return array( 'kind' => 'type', 'pt' => $ctx['pt'], 'id' => 0, 'url' => get_post_type_archive_link( $ctx['pt'] ) ); }
	if ( 'page' === $ctx['kind'] ) { return array( 'kind' => 'page', 'pt' => $ctx['post']->post_type, 'id' => (int) $ctx['post']->ID, 'url' => get_permalink( $ctx['post'] ) ); }
	return null;
}

/** Everything the section design shows, cached. */
function zad_sec_data( $node ) {
	$key = 'zad_sec_' . md5( ZAD_VERSION . '|' . filemtime( __FILE__ ) . '|' . $node['pt'] . '|' . $node['id'] ) . '_' . zad_city_ver() . '_' . (int) get_option( 'zad_qnet_ver', 1 ) . '_' . (int) get_option( 'zad_wk_ver', 1 );
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) { return $hit; }
	list( $svc, $subs, $guides ) = zad_sec_members( $node );
	$tree  = zad_sec_tree( $node['pt'], (int) $node['id'] );
	$tree_ids = array_values( array_diff( $tree, $guides ) );
	if ( $node['id'] ) { array_unshift( $tree_ids, (int) $node['id'] ); }
	// rows of the city table: the sub-sections, plus this section itself when it has city pages of its own
	$rows = array();
	if ( $svc ) { $own = array(); foreach ( $svc as $s ) { $sc = zad_service_city_scope( $s ); if ( ! in_array( $sc, array( '', 'all' ), true ) && ! isset( $own[ $sc ] ) ) { $own[ $sc ] = array( get_permalink( $s ), $s ); } } if ( $own ) { $rows[] = array( 'name' => zad_sec_name( $node ), 'url' => $node['url'], 'cells' => $own ); } }
	foreach ( $subs as $s ) { $sn = array( 'kind' => 'page', 'pt' => $node['pt'], 'id' => $s, 'url' => get_permalink( $s ) ); $cells = zad_sec_city_cells( $node['pt'], $s ); if ( $cells ) { $rows[] = array( 'name' => zad_sec_name( $sn ), 'url' => $sn['url'], 'cells' => $cells ); } }
	$cols = array();
	foreach ( $rows as $r ) { foreach ( array_keys( $r['cells'] ) as $c ) { $cols[ $c ] = ( $cols[ $c ] ?? 0 ) + 1; } }
	arsort( $cols );
	$d = array(
		'svc'    => $svc,
		'subs'   => $subs,
		'guides' => $guides,
		'tree'   => $tree_ids,
		'rows'   => count( $rows ) > 1 ? $rows : array(),
		'cols'   => array_keys( $cols ),
		'works'  => wp_list_pluck( zad_sec_works( $tree_ids ), 'ID' ),
		'media'  => zad_sec_media( $tree_ids ),
		'min'    => zad_sec_min_price( $tree_ids ),
	);
	set_transient( $key, $d, 6 * HOUR_IN_SECONDS );
	return $d;
}
add_action( 'save_post_' . ( defined( 'ZAD_WK' ) ? ZAD_WK : 'zad_work' ), function () { update_option( 'zad_wk_ver', time(), false ); } );

/* ------------------------------------------------------------------ */
/* /services/: one page (no /page/2/), and the links that lead to it    */
/* ------------------------------------------------------------------ */
add_action( 'template_redirect', function () {
	if ( is_post_type_archive( 'zad_service' ) && is_paged() ) { wp_safe_redirect( get_post_type_archive_link( 'zad_service' ), 301 ); exit; }
}, 4 );

/** /services-category/{x}/ only repeated the archive of a whole type (/cleaning/, /pest-control/): 301 there. */
add_action( 'template_redirect', function () {
	if ( ! is_tax( 'service_cat' ) || ! function_exists( 'zad_hub_mirror_type' ) ) { return; }
	$pt = zad_hub_mirror_type( get_queried_object() );
	$to = $pt ? get_post_type_archive_link( $pt ) : '';
	if ( $to ) { wp_safe_redirect( $to, 301, 'Zad' ); exit; }
}, 3 );
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	if ( 'service_cat' !== $taxonomy || ! function_exists( 'zad_hub_mirror_type' ) ) { return $args; }
	$ex = array();
	foreach ( (array) get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => false ) ) as $t ) { if ( $t instanceof WP_Term && zad_hub_mirror_type( $t ) ) { $ex[] = (int) $t->term_id; } }
	if ( $ex ) { $args['exclude'] = array_values( array_unique( array_merge( (array) ( $args['exclude'] ?? array() ), $ex ) ) ); }
	return $args;
}, 20, 2 );

/** Trail: Home > الخدمات > … for section pages and service pages; the labels short («تنظيف خزانات بالرياض», not the full SEO title). */
add_filter( 'zad_current_crumbs', function ( $c ) {
	if ( ! $c || is_front_page() ) { return $c; }
	$is_svc = ( is_singular() && zad_is_service() ) || ( function_exists( 'zad_hub_ctx' ) && zad_hub_ctx() && in_array( zad_hub_ctx()['kind'], array( 'type', 'page' ), true ) );
	if ( ! $is_svc ) { return $c; }
	$idx = zad_services_url();
	if ( $idx ) {
		$has = false;
		foreach ( $c as $x ) { if ( ! empty( $x[1] ) && untrailingslashit( $x[1] ) === untrailingslashit( $idx ) ) { $has = true; break; } }
		if ( ! $has ) { array_splice( $c, 1, 0, array( array( 'الخدمات', $idx ) ) ); }
		foreach ( $c as $i => $x ) { if ( ! empty( $x[1] ) && untrailingslashit( $x[1] ) === untrailingslashit( $idx ) ) { $c[ $i ][0] = 'الخدمات'; } }
	}
	foreach ( $c as $i => $x ) { if ( $i > 1 ) { $c[ $i ][0] = zad_sec_short( $x[0] ); } }
	return $c;
}, 60 );

/** Footer «الخدمات» column: one link to /services/ at its end (the main menu stays as the owner set it). */
add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	if ( empty( $args->theme_location ) || 'footerinfo' !== $args->theme_location ) { return $items; }
	$idx = zad_services_url();
	if ( ! $idx || false !== strpos( $items, 'href="' . esc_url( $idx ) . '"' ) ) { return $items; }
	return $items . '<li class="menu-item zad-all-services"><a href="' . esc_url( $idx ) . '">كل الخدمات</a></li>';
}, 10, 2 );

/** /services/ lists the sections of every service type, so it is a real page even while no zad_service page exists yet. */
add_filter( 'pre_handle_404', function ( $pre, $q ) {
	if ( $q->is_main_query() && $q->is_post_type_archive( 'zad_service' ) && ! $q->is_paged() ) { status_header( 200 ); return true; }
	return $pre;
}, 10, 2 );

/** Card name of a section: without the leading «خدمات/شركة», at most 4 words, no dangling «بجميع/في/و…» at the end. */
function zad_sec_card_name( $name ) {
	$w = preg_split( '/\s+/u', trim( wp_strip_all_tags( (string) $name ) ), -1, PREG_SPLIT_NO_EMPTY );
	while ( count( $w ) > 1 && in_array( $w[0], array( 'خدمات', 'خدمة', 'شركة', 'شركات', 'أفضل', 'افضل' ), true ) ) { array_shift( $w ); }
	$w = array_slice( $w, 0, 4 );
	while ( count( $w ) > 1 && in_array( end( $w ), array( 'بجميع', 'جميع', 'في', 'و', 'من', 'مع', 'لكل', 'بكل' ), true ) ) { array_pop( $w ); }
	return implode( ' ', $w );
}

/** Icon and accent colour of a section, from words in its name / slug: array( icon, colour ). Filter: zad_sec_look. */
function zad_sec_look( $text ) {
	$t   = mb_strtolower( (string) $text );
	$map = array(
		array( array( 'حشر', 'مبيد', 'pest', 'termite', 'نمل', 'صراصير' ), 'bug', '#d6453d' ),
		array( array( 'خزان', 'tank' ), 'drop', '#2f6fdb' ),
		array( array( 'مكيف', 'تكييف', 'air', 'ac-' ), 'snow', '#0ea5c6' ),
		array( array( 'كنب', 'مجالس', 'موكيت', 'سجاد', 'مفروش', 'sofa', 'carpet' ), 'home', '#8b5cf6' ),
		array( array( 'نقل', 'moving' ), 'truck', '#ea7a1a' ),
		array( array( 'تخزين', 'storage' ), 'badge', '#b45309' ),
		array( array( 'تسليك', 'مجاري', 'صرف', 'drain', 'sewer' ), 'tool', '#15935b' ),
		array( array( 'عزل', 'insulat' ), 'shield', '#475569' ),
		array( array( 'دهان', 'paint' ), 'paint', '#db2777' ),
	);
	$out = array( 'sparkle', '#0f7c8c' );
	foreach ( $map as $m ) { foreach ( $m[0] as $k ) { if ( false !== mb_strpos( $t, $k ) ) { $out = array( $m[1], $m[2] ); break 2; } } }
	return apply_filters( 'zad_sec_look', $out, $text );
}

/** Link label inside a /services/ card: the short name without the city (shown as a tag beside it) or «في السعودية», ≤ 5 words. */
function zad_sec_link_label( $title ) {
	$t = zad_sec_short( $title );
	$t = preg_replace( '/(?<![\p{L}])(?:في|ب)\s*(?:ال)?(?:سعودية|مملكة)(?![\p{L}])/u', ' ', $t );
	foreach ( array_values( zad_city_map() ) as $city ) { $t = preg_replace( '/(?<![\p{L}])(?:في\s+|ب|ل)?' . preg_quote( $city, '/' ) . '(?![\p{L}])/u', ' ', $t ); }
	$t = preg_replace( '/\s+في\s+مكان\S*.*$/u', '', $t );
	$w = preg_split( '/\s+/u', trim( $t ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $w ) > 2 && in_array( $w[0], array( 'شركة', 'شركات' ), true ) ) { array_shift( $w ); }
	$w = array_slice( $w, 0, 5 );
	while ( count( $w ) > 1 && in_array( end( $w ), array( 'بجميع', 'جميع', 'في', 'و', 'من', 'مع', 'لكل', 'بكل', 'ـ', '-', '|' ), true ) ) { array_pop( $w ); }
	$t = implode( ' ', $w );
	return '' !== $t ? $t : zad_sec_short( $title );
}

/* ======================================================================================================
 * Services catalog (3.46.2): /services/ groups EVERY service of the site in fixed families, whatever its type today:
 * the hierarchical service pages (cleaning, pest_control, zad_service) and the service articles not converted yet (posts).
 * When an article is converted its new page takes its place by itself (the article is no longer published).
 * Families, their words, landing page, colour, icon and short text: filter zad_catalog_families.
 * ====================================================================================================== */

function zad_catalog_families() {
	return apply_filters( 'zad_catalog_families', array(
		'cleaning'   => array( 'name' => 'التنظيف', 'icon' => 'sparkle', 'color' => '#0f7c8c', 're' => '/تنظيف|غسيل|نظافة|نظيف/u', 'land' => array( array( 'type', 'cleaning' ) ),
			'desc' => 'تنظيف شامل للمنازل والشقق والفلل والمساجد والمكاتب والمطابخ، بفريق مدرّب ومواد آمنة، مع معاينة مجانية قبل البدء.' ),
		'tanks'      => array( 'name' => 'تنظيف الخزانات', 'icon' => 'drop', 'color' => '#2f6fdb', 're' => '/خزان/u', 'land' => array( array( 'cleaning', 'tanks' ) ),
			'desc' => 'غسيل وتعقيم خزانات المياه الأرضية والعلوية وعزلها، لمياه نظيفة وآمنة للشرب والاستخدام اليومي.' ),
		'furnishing' => array( 'name' => 'الكنب والمفروشات', 'icon' => 'home', 'color' => '#8b5cf6', 're' => '/كنب|مجالس|موكيت|سجاد|مفروشات|ستائر|مراتب|زوالي/u', 'land' => array( array( 'cleaning', 'sofa' ) ),
			'desc' => 'تنظيف الكنب والمجالس والموكيت والسجاد والستائر في مكانها، بالبخار والمواد المناسبة لكل نوع قماش.' ),
		'ac'         => array( 'name' => 'المكيفات', 'icon' => 'snow', 'color' => '#0ea5c6', 're' => '/مكيف|تكييف/u', 'land' => array( array( 'cleaning', 'air-conditioning' ) ),
			'desc' => 'تنظيف المكيفات السبليت والشباك والمركزية في مكانها، وتركيبها وتعبئة الفريون، بفنيين متخصصين.' ),
		'pest'       => array( 'name' => 'مكافحة الحشرات', 'icon' => 'bug', 'color' => '#d6453d', 're' => '/مكافحة|حشرات|مبيد|صراصير|صرصور|نمل|(?:^|\s)بق(?:\s|$)|فئران|(?:^|\s)رش(?:\s|$)|طارد/u', 'land' => array( array( 'type', 'pest_control' ) ),
			'desc' => 'رش ومكافحة الصراصير والنمل الأبيض والبق والفئران وطارد الحمام، بمبيدات آمنة ومعتمدة للمنازل والمنشآت.' ),
		'drain'      => array( 'name' => 'تسليك المجاري', 'icon' => 'tool', 'color' => '#15935b', 're' => '/تسليك|مجاري|المجارير|بيار|شفط|صرف|سباك/u', 'land' => array( array( 'zad_service', 'drain-cleaning' ) ),
			'desc' => 'تسليك مجاري المطابخ والحمامات والبيارات بالضغط والمعدات الحديثة، وشفط البيارات دون تكسير.' ),
		'tiles'      => array( 'name' => 'جلي البلاط والرخام', 'icon' => 'star', 'color' => '#c2410c', 're' => '/جلي|بلاط|رخام|جرانيت|ارضيات|أرضيات/u', 'land' => array( array( 'zad_service', 'tile-polishing' ) ),
			'desc' => 'جلي وتلميع البلاط والرخام والجرانيت بأحدث المكائن، لأرضيات تعود لمعتها من جديد في الداخل والحوش.' ),
		'moving'     => array( 'name' => 'نقل الأثاث', 'icon' => 'truck', 'color' => '#ea7a1a', 're' => '/نقل|عفش/u', 'land' => array( array( 'zad_service', 'furniture-moving' ) ),
			'desc' => 'نقل العفش والأثاث داخل المدينة وبين المدن، مع الفك والتركيب والتغليف وسيارات مجهزة.' ),
		'storage'    => array( 'name' => 'تخزين الأثاث', 'icon' => 'badge', 'color' => '#a16207', 're' => '/تخزين|مستودع/u', 'land' => array( array( 'zad_service', 'furniture-storage' ) ),
			'desc' => 'تخزين الأثاث والعفش في مستودعات نظيفة ومؤمّنة، مع التغليف والنقل من وإلى المستودع.' ),
		'insulation' => array( 'name' => 'العزل', 'icon' => 'shield', 'color' => '#475569', 're' => '/عزل/u', 'land' => array( array( 'zad_service', 'insulation' ) ),
			'desc' => 'عزل الأسطح والخزانات ضد الماء والحرارة بمواد معتمدة وفنيين متخصصين.' ),
		'leaks'      => array( 'name' => 'كشف التسربات', 'icon' => 'search', 'color' => '#0369a1', 're' => '/تسرب|تسريب/u', 'land' => array( array( 'zad_service', 'leak-detection' ) ),
			'desc' => 'كشف تسربات المياه بالأجهزة الإلكترونية دون تكسير، وتحديد مكان التسرب بدقة.' ),
	) );
}

/** Order in which a title is matched (the first family whose words appear wins): specific families before «التنظيف». */
function zad_catalog_match_order() {
	return apply_filters( 'zad_catalog_match_order', array( 'tanks', 'drain', 'tiles', 'ac', 'furnishing', 'storage', 'moving', 'insulation', 'leaks', 'pest', 'cleaning' ) );
}

/** The head of a title: up to the first phone / number / separator («شركة تنظيف مساجد بالدمام 055… تنظيف سجاد» → «شركة تنظيف مساجد بالدمام»). */
function zad_catalog_head( $title ) {
	$t = wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
	$t = preg_split( '/\s*(?:\d{4,}|[|:–—]|\s-\s|\sـ\s|خصم|بخصم)/u', $t )[0] ?? $t;
	return trim( preg_replace( '/\s+/u', ' ', $t ) );
}

function zad_catalog_family_of( $title, $default = '' ) {
	$h = zad_catalog_head( $title );
	$f = zad_catalog_families();
	foreach ( zad_catalog_match_order() as $k ) { if ( isset( $f[ $k ] ) && preg_match( $f[ $k ]['re'], $h ) ) { return $k; } }
	return $default;
}

/** Arabic city name in a title (longest first), '' when none. */
function zad_catalog_city_in( $text ) {
	static $list = null;
	if ( null === $list ) {
		$list = array_values( array_unique( array_merge( array_values( zad_city_map() ), array( 'الرياض', 'جدة', 'الدمام', 'القطيف', 'الخبر', 'الظهران', 'الجبيل', 'الأحساء', 'الاحساء', 'مكة', 'المدينة المنورة', 'الطائف', 'الخرج', 'القصيم', 'بريدة', 'عنيزة', 'المذنب', 'الزلفي', 'المجمعة', 'حفر الباطن', 'سيهات', 'صفوى', 'رأس تنورة', 'نجران', 'أبها', 'خميس مشيط', 'جازان', 'تبوك', 'حائل', 'ينبع' ) ) ) );
		usort( $list, function ( $a, $b ) { return mb_strlen( $b ) - mb_strlen( $a ); } );
	}
	foreach ( $list as $c ) { if ( preg_match( '/(?<![\p{L}])(?:في\s+|ب|ل)?' . preg_quote( $c, '/' ) . '(?![\p{L}])/u', (string) $text ) ) { return 'الاحساء' === $c ? 'الأحساء' : $c; } }
	return '';
}

/** Card label: the short name without the city / «شركة» («شركة جلي بلاط شمال الرياض» → «جلي بلاط شمال»). */
function zad_catalog_label( $title, $city ) {
	$t = zad_catalog_head( $title );
	$t = preg_replace( '/[^\p{L}\p{N}\s%]/u', ' ', $t ); // emoji, ☎️, punctuation
	$cre = '' !== $city ? preg_quote( $city, '/' ) . ( 'الأحساء' === $city ? '|الاحساء' : '' ) : '';
	$district = '' !== $city && preg_match( '/(?<![\p{L}])(?:شمال|جنوب|شرق|غرب|وسط)\s+(?:' . $cre . ')(?![\p{L}])/u', $t );
	$t = $district ? trim( preg_replace( '/(?<![\p{L}])(?:شركة|شركات|أفضل|افضل)(?![\p{L}])/u', ' ', $t ) ) : zad_sec_link_label( $t );
	if ( '' !== $city && ! $district ) { $t = preg_replace( '/(?<![\p{L}])(?:في\s+|ب|ل)?(?:' . $cre . ')(?![\p{L}])/u', ' ', $t ); }
	$w = preg_split( '/\s+/u', trim( preg_replace( '/(?<![\p{L}])(?:شركة|شركات|أفضل|افضل|مؤسسة|ارخص|أرخص)(?![\p{L}])/u', ' ', $t ) ), -1, PREG_SPLIT_NO_EMPTY );
	$cut = array( 'فك', 'حل', 'غسيل', 'خصم', 'بخصم', 'ضمان', 'مع', 'رش', 'نظافة', 'تلميع', 'باقات', 'خدمة', 'عروض', 'اسعار', 'أسعار', 'تطهير', 'الفنيين', 'فنيين', 'جلي', 'خلي' );
	if ( $district ) { foreach ( $w as $i => $x ) { if ( $x === $city || ( 'الأحساء' === $city && 'الاحساء' === $x ) ) { $w = array_slice( $w, 0, $i + 1 ); break; } } }
	foreach ( $w as $i => $x ) { if ( $i > 1 && in_array( $x, $cut, true ) ) { $w = array_slice( $w, 0, $i ); break; } }
	$w = array_slice( $w, 0, $district ? 5 : 4 );
	while ( count( $w ) > 1 && in_array( end( $w ), array( 'في', 'و', 'من', 'مع', 'ب', 'على' ), true ) ) { array_pop( $w ); }
	if ( 1 === count( $w ) ) { array_unshift( $w, 'شركة' ); } // «شركة تنظيف بجدة» → «شركة تنظيف», not a bare «تنظيف»
	$t = implode( ' ', $w );
	return '' !== $t ? $t : zad_sec_short( $title );
}

/** A question / guide title («كيف تختار…», «هل…؟») is an article, never a service card. */
function zad_catalog_is_article( $t ) {
	return (bool) preg_match( '/[؟?]\s*$|^\s*(?:كيف|كيفية|ما|ماذا|هل|لماذا|متى|كم|أسباب|اسباب|نصائح|طرق|طريقة|خطوات|دليل|أفضل طريقة|افضل طريقة)\s/u', wp_strip_all_tags( (string) $t ) );
}

function zad_catalog_ver() { return (int) get_option( 'zad_catalog_ver', 1 ); }
add_action( 'save_post_post', function () { update_option( 'zad_catalog_ver', time(), false ); } );
foreach ( array( 'deleted_post', 'trashed_post', 'untrashed_post', 'transition_post_status' ) as $zad_h ) {
	add_action( $zad_h, function () { static $done = false; if ( ! $done ) { $done = true; update_option( 'zad_catalog_ver', time(), false ); } } );
}

/** Landing node of a family: array( kind, pt, id, url ) or null. */
function zad_catalog_landing( $fam ) {
	foreach ( (array) ( $fam['land'] ?? array() ) as $l ) {
		if ( 'type' === $l[0] ) {
			if ( post_type_exists( $l[1] ) && get_post_type_archive_link( $l[1] ) ) { return array( 'kind' => 'type', 'pt' => $l[1], 'id' => 0, 'url' => get_post_type_archive_link( $l[1] ) ); }
			continue;
		}
		if ( ! post_type_exists( $l[0] ) ) { continue; }
		$p = get_page_by_path( $l[1], OBJECT, $l[0] );
		if ( $p && 'publish' === $p->post_status ) { return array( 'kind' => 'page', 'pt' => $l[0], 'id' => (int) $p->ID, 'url' => get_permalink( $p ) ); }
	}
	return null;
}

/**
 * The catalog: key => array( name, icon, color, desc, url, landed, items => [ array( id, url, label, title, city, img, min ) ], cities => [ city => url ] ).
 * Families without items are left out. Cached with the city version (service pages) + zad_catalog_ver (articles).
 */
function zad_catalog() {
	static $mem = null;
	if ( null !== $mem ) { return $mem; }
	$key = 'zad_cat_' . md5( ZAD_VERSION . '|' . filemtime( __FILE__ ) . '|' . zad_city_ver() . '|' . zad_catalog_ver() );
	$mem = get_transient( $key );
	if ( is_array( $mem ) ) { return $mem; }
	$fams  = zad_catalog_families();
	$land  = array(); $land_ids = array();
	foreach ( $fams as $k => $f ) { $land[ $k ] = zad_catalog_landing( $f ); if ( $land[ $k ] && $land[ $k ]['id'] ) { $land_ids[ $land[ $k ]['id'] ] = $k; } }
	$areas = function_exists( 'zad_area_page_ids' ) ? array_flip( array_map( 'intval', (array) zad_area_page_ids() ) ) : array();
	$idx   = zad_sec_index();
	$items = array_fill_keys( array_keys( $fams ), array() );
	$hood  = array(); $hoods = array_fill_keys( array_keys( $fams ), array() );
	$add   = function ( $fk, $id, $title, $city, $img ) use ( &$items ) {
		$items[ $fk ][] = array( 'id' => $id, 'url' => get_permalink( $id ), 'label' => zad_catalog_label( $title, $city ), 'title' => zad_catalog_head( $title ), 'city' => $city, 'img' => $img, 'min' => zad_sec_min_price( array( $id ) ) );
	};
	// 1. service pages of the hierarchical types
	foreach ( $idx as $id => $r ) {
		if ( isset( $areas[ $id ] ) && ! zad_sec_children( $r['pt'], $id ) && $r['name'] !== zad_service_city_scope( $id ) ) { $hood[] = $id; continue; } // /cleaning/tanks/jeddah/ (slug = its city) is the city's page // neighbourhood pages: links under their city, not cards (a page with pages under it is the city's main page)
		if ( isset( $land_ids[ $id ] ) || zad_sec_is_guide( $id ) || zad_catalog_is_article( $r['title'] ) ) { continue; }
		if ( zad_sec_is_section( $id ) && zad_sec_children( $r['pt'], $id ) ) { continue; } // a section page is the family's own page, not a card
		$fk = '';
		foreach ( array_merge( array( $id ), (array) get_post_ancestors( $id ) ) as $a ) { if ( isset( $land_ids[ $a ] ) && $a !== $id ) { $fk = $land_ids[ $a ]; break; } }
		if ( '' === $fk ) { $fk = 'pest_control' === $r['pt'] ? 'pest' : zad_catalog_family_of( $r['title'], 'cleaning' === $r['pt'] ? 'cleaning' : '' ); }
		if ( '' === $fk || ! isset( $fams[ $fk ] ) ) { continue; }
		$sc   = zad_service_city_scope( $id );
		$city = in_array( $sc, array( '', 'all' ), true ) ? zad_catalog_city_in( zad_catalog_head( $r['title'] ) ) : zad_city_label( $sc );
		$add( $fk, $id, zad_card_title( $id ), $city, zad_sec_image( $id ) );
	}
	// 2. service articles not converted yet (same test as the converter's suggestion: a «خدمات …» category or «شركة/مؤسسة» in the title, never a question / guide)
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true, 'no_found_rows' => true ) ) as $p ) {
		$t = (string) $p->post_title;
		if ( zad_catalog_is_article( $t ) || preg_match( '/^\s*(?:أسعار|اسعار|سعر|تكلفة)\s/u', $t ) ) { continue; }
		$svc = (bool) preg_match( '/شركة|مؤسسة/u', $t );
		if ( ! $svc ) { foreach ( (array) get_the_category( $p->ID ) as $c ) { if ( preg_match( '/^\s*خدمات/u', $c->name ) ) { $svc = true; break; } } }
		if ( ! $svc ) { continue; }
		$fk = zad_catalog_family_of( $t );
		if ( '' === $fk ) { continue; }
		$add( $fk, (int) $p->ID, $t, zad_catalog_city_in( zad_catalog_head( $t ) ), (int) get_post_thumbnail_id( $p->ID ) );
	}
	foreach ( $hood as $id ) {
		$r  = $idx[ $id ];
		$fk = '';
		foreach ( (array) get_post_ancestors( $id ) as $a ) { if ( isset( $land_ids[ $a ] ) ) { $fk = $land_ids[ $a ]; break; } }
		if ( '' === $fk ) { $fk = 'pest_control' === $r['pt'] ? 'pest' : zad_catalog_family_of( $r['title'], 'cleaning' === $r['pt'] ? 'cleaning' : '' ); }
		if ( '' === $fk || ! isset( $fams[ $fk ] ) ) { continue; }
		$sc   = zad_service_city_scope( $id );
		$city = in_array( $sc, array( '', 'all' ), true ) ? zad_catalog_city_in( zad_catalog_head( $r['title'] ) ) : zad_city_label( $sc );
		$hoods[ $fk ][ '' !== $city ? $city : '-' ][] = array( zad_catalog_hood_label( $r['title'], $city ), get_permalink( $id ), $id );
	}
	$main = array_values( zad_city_map() );
	$mem  = array();
	foreach ( $fams as $k => $f ) {
		if ( ! $items[ $k ] && ! $hoods[ $k ] ) { continue; }
		$list = $items[ $k ];
		usort( $list, function ( $a, $b ) use ( $main ) {
			$ra = '' === $a['city'] ? 99 : ( false !== ( $i = array_search( $a['city'], $main, true ) ) ? $i : 50 );
			$rb = '' === $b['city'] ? 99 : ( false !== ( $j = array_search( $b['city'], $main, true ) ) ? $j : 50 );
			return $ra === $rb ? strcmp( $a['label'], $b['label'] ) : $ra - $rb;
		} );
		$cities = array();
		foreach ( $list as $it ) { if ( '' !== $it['city'] && ! isset( $cities[ $it['city'] ] ) ) { $cities[ $it['city'] ] = $it['url']; } }
		$l    = $land[ $k ];
		$desc = $l ? zad_sec_desc( $l, 30 ) : '';
		$mins = array_filter( wp_list_pluck( $list, 'min' ) );
		$mem[ $k ] = array(
			'name'   => $f['name'], 'icon' => $f['icon'], 'color' => $f['color'],
			'desc'   => '' !== $desc ? $desc : $f['desc'],
			'url'    => $l ? $l['url'] : ( $list ? $list[0]['url'] : reset( $hoods[ $k ] )[0][1] ),
			'hoods'  => $hoods[ $k ],
			'landed' => (bool) $l,
			'img'    => $l && $l['id'] ? zad_sec_image( $l['id'] ) : 0,
			'items'  => $list,
			'cities' => $cities,
			'min'    => $mins ? min( $mins ) : 0,
		);
	}
	set_transient( $key, $mem, 12 * HOUR_IN_SECONDS );
	return $mem;
}

/** Neighbourhood link label: «شركة تنظيف مكيفات حي النرجس بالرياض» → «حي النرجس» when the title names one, else the short card label. */
function zad_catalog_hood_label( $title, $city ) {
	$h = zad_catalog_head( $title );
	if ( preg_match( '/(?<![\p{L}])(?:ب|ل)?((?:شمال|جنوب|شرق|غرب|وسط)\s+(?:ال)?[\p{L}]+)/u', $h, $m ) && ( '' === $city || false !== mb_strpos( zad_city_norm_name( $m[1] ), zad_city_norm_name( $city ) ) ) ) { return trim( $m[1] ); }
	if ( preg_match( '/(?<![\p{L}])(?:ب|ل)?((?:حي|أحياء|احياء)\s+[\p{L}]+(?:\s+(?:ال)?[\p{L}]+)?)/u', $h, $m ) ) {
		$t = trim( $m[1] );
		if ( '' !== $city ) { $t = trim( preg_replace( '/(?<![\p{L}])(?:في\s+|ب|ل)?' . preg_quote( $city, '/' ) . '(?![\p{L}])/u', ' ', $t ) ); }
		$t = trim( preg_replace( '/\s(?:في|و|من|ب)$/u', '', $t ) );
		if ( mb_strlen( $t ) > 4 ) { return $t; }
	}
	return zad_catalog_label( $title, $city );
}

/* ======================================================================================================
 * City pages (3.46.3): /areas/{city}/ (service_area terms) get their own design (template-parts/hub-city.php).
 * The opening text is «نص صفحة المدينة» (term meta, allows headings), else the term description (Posts → المدن والأحياء → edit); until it is written a suggested text
 * for the city is shown (zad_city_intro_default()), and Tools → «صفحات المدن (زاد)» can save the suggestions in one click.
 * ====================================================================================================== */

const ZAD_CITY_BODY = '_zad_city_body';

/** One spelling for one city: alef variants unified, and a city known by two names reduced to the name the city list uses. Filter: zad_city_name_aliases. */
function zad_city_norm_name( $n ) {
	$n = str_replace( array( 'أ', 'إ', 'آ' ), 'ا', trim( wp_strip_all_tags( (string) $n ) ) );
	$alias = apply_filters( 'zad_city_name_aliases', array( 'مكة المكرمة' => 'مكة', 'المدينة المنورة' => 'المدينة' ) );
	return $alias[ $n ] ?? $n;
}

/** Arabic city name => WP_Term of «المدن والأحياء» (top level and children). */
function zad_city_terms() {
	static $m = null;
	if ( null !== $m ) { return $m; }
	$m = array();
	if ( ! taxonomy_exists( 'service_area' ) ) { return $m; }
	foreach ( (array) get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false ) ) as $t ) { if ( $t instanceof WP_Term ) { $m[ zad_city_norm_name( $t->name ) ] = $t; } }
	return $m;
}
function zad_city_term( $name ) { $m = zad_city_terms(); $k = zad_city_norm_name( $name ); return $m[ $k ] ?? null; }
/** URL of a city page, '' when the city has none yet. */
function zad_city_hub_url( $name ) { $t = zad_city_term( $name ); if ( ! $t ) { return ''; } $u = get_term_link( $t ); return is_wp_error( $u ) ? '' : (string) $u; }

/** The catalog of one city (and the cities under it): family key => array( fam, items, hoods ). */
function zad_city_catalog( $term ) {
	$names = array( zad_city_norm_name( $term->name ) );
	foreach ( (array) get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'child_of' => $term->term_id ) ) as $c ) { if ( $c instanceof WP_Term ) { $names[] = zad_city_norm_name( $c->name ); } }
	$out = array();
	foreach ( zad_catalog() as $k => $f ) {
		$items = array_values( array_filter( $f['items'], function ( $it ) use ( $names ) { return '' !== $it['city'] && in_array( zad_city_norm_name( $it['city'] ), $names, true ); } ) );
		$hoods = array();
		foreach ( (array) ( $f['hoods'] ?? array() ) as $c => $list ) { if ( in_array( zad_city_norm_name( $c ), $names, true ) ) { $hoods = array_merge( $hoods, $list ); } }
		if ( $items || $hoods ) { $out[ $k ] = array( 'fam' => $f, 'items' => $items, 'hoods' => $hoods ); }
	}
	return $out;
}

/** Suggested opening text of a city page (two short paragraphs, written for each city). Filter: zad_city_intro_default. */
function zad_city_intro_default( $name ) {
	$t = array(
		'الرياض'  => array( 'الرياض مدينة واسعة تمتد أحياؤها من الشمال الجديد مثل النرجس والملقا والياسمين إلى أحياء الشرق والغرب والجنوب، وتتنوع فيها المساكن بين الفلل والشقق والعمائر والاستراحات. لذلك نوزّع فرقنا على جهات المدينة لنصل إليك في موعد قريب، ونسألك عن نوع المكان ومساحته قبل الزيارة حتى نجهّز المعدات المناسبة.', 'الغبار والعواصف الرملية وحرارة الصيف تجعل تنظيف المكيفات والخزانات والمفروشات حاجة متكررة في بيوت الرياض، كما يزيد نشاط الحشرات مع ارتفاع الحرارة. ستجد هنا كل خدماتنا في الرياض مرتبة حسب القسم، ولكل خدمة صفحتها بالتفاصيل والأسعار، وتحتها صفحات الأحياء.' ),
		'جدة'     => array( 'تجمع جدة بين أحياء الشمال مثل أبحر والشاطئ والحمدانية وأحياء الوسط والجنوب، وتغلب عليها الشقق والعمائر إلى جانب الفلل والاستراحات. نرتّب الزيارة حسب الحي وطبيعة المبنى، وننسّق مع إدارة العمارة عند الحاجة لاستخدام المصاعد أو السطح.', 'الرطوبة العالية وقرب البحر يرفعان الحاجة إلى تنظيف المكيفات بانتظام، ومعالجة الروائح والعفن في الكنب والسجاد والستائر، ويساعدان على انتشار الصراصير والبق. في هذه الصفحة خدماتنا في جدة مرتبة حسب القسم، مع رابط لكل خدمة بتفاصيلها.' ),
		'الدمام'  => array( 'الدمام قلب المنطقة الشرقية، وتتصل أحياؤها بالخبر والظهران، ويكثر فيها السكن في الفلل والدبلكسات والشقق. نغطي أحياء الدمام بفرق قريبة، ونحدد معك موعد الزيارة ونوع الخدمة قبل الحضور.', 'الحرارة مع الرطوبة الساحلية تُتعب المكيفات وتزيد الأتربة في الفلاتر، وارتفاع منسوب المياه في بعض الأحياء يزيد من مشكلات البيارات والصرف. ستجد هنا خدماتنا في الدمام من التنظيف والخزانات حتى التسليك والجلي والنقل، ولكل خدمة صفحتها.' ),
		'القطيف'  => array( 'تضم محافظة القطيف مدناً وبلدات متقاربة مثل سيهات وصفوى وتاروت والعوامية، وتنتشر فيها البيوت المستقلة والفلل إلى جانب الشقق. نصل إلى مدن المحافظة وبلداتها، ونرتّب الزيارة حسب موقعك.', 'قرب المزارع والنخيل والرطوبة يزيدان من نشاط الحشرات والقوارض، كما تحتاج الخزانات والمكيفات إلى عناية دورية مع الحرارة. في هذه الصفحة خدماتنا في القطيف مرتبة حسب القسم، مع رابط لتفاصيل كل خدمة.' ),
		'القصيم'  => array( 'تمتد خدماتنا في منطقة القصيم إلى مدنها الرئيسية بريدة وعنيزة، حيث تغلب البيوت المستقلة والفلل والاستراحات والمزارع. نحدد مع كل طلب المدينة والحي ونوع المكان قبل إرسال الفريق.', 'الغبار وفصل الصيف الحار يجعلان تنظيف المكيفات والخزانات والمفروشات حاجة متكررة، وتزيد المزارع القريبة من نشاط الحشرات والحمام حول البيوت. هنا تجد خدماتنا في القصيم ومدنها مرتبة حسب القسم.' ),
		'نجران'   => array( 'نخدم أحياء نجران المختلفة في البيوت والفلل والشقق والاستراحات، ونرتّب الزيارة مسبقاً حسب الحي ونوع الخدمة حتى يصل الفريق بالمعدات المناسبة.', 'الجو الحار والجاف والغبار يرفع حاجة المكيفات والخزانات والسجاد إلى تنظيف منتظم، ويزيد نشاط الحشرات في الصيف. في هذه الصفحة خدماتنا في نجران مرتبة حسب القسم، ولكل خدمة صفحتها.' ),
		'بريدة'   => array( 'بريدة عاصمة منطقة القصيم وأكبر مدنها، وتتنوع فيها المساكن بين الفلل والبيوت الشعبية والشقق والاستراحات. نصل إلى أحياء بريدة ونحدد معك موعد الزيارة قبل الحضور.', 'الغبار وحرارة الصيف يجعلان تنظيف المكيفات والمفروشات والخزانات حاجة متكررة، وتزيد المزارع المحيطة من الحشرات والحمام. هنا خدماتنا في بريدة مرتبة حسب القسم.' ),
		'عنيزة'   => array( 'عنيزة من أكبر مدن منطقة القصيم، وتنتشر فيها الفلل والبيوت المستقلة والمزارع والاستراحات. نخدم أحياء عنيزة بمواعيد يتم الاتفاق عليها مسبقاً.', 'قرب المزارع والنخيل يرفع نشاط الحشرات والحمام حول البيوت، ويحتاج المكيف والخزان إلى عناية دورية مع حرارة الصيف. في هذه الصفحة خدماتنا في عنيزة مرتبة حسب القسم.' ),
		'الخرج'   => array( 'الخرج محافظة جنوب الرياض، وتجمع بين أحياء سكنية ومزارع واستراحات. نحدد معك موعد الزيارة ونوع المكان قبل إرسال الفريق.', 'الحرارة والغبار وقرب المزارع تزيد الحاجة إلى تنظيف الكنب والخزانات ومكافحة الحشرات. هنا خدماتنا في الخرج مرتبة حسب القسم.' ),
		'مكة'     => array( 'نخدم أحياء مكة المكرمة في البيوت والشقق والعمائر، ونرتّب الزيارة مسبقاً حسب الحي ومواعيد الدخول المتاحة.', 'حرارة الجو أغلب العام تجعل تنظيف المكيفات وصيانتها حاجة متكررة، إلى جانب تنظيف المفروشات والخزانات. في هذه الصفحة خدماتنا في مكة المكرمة مرتبة حسب القسم.' ),
		'الاحساء' => array( 'الأحساء في المنطقة الشرقية، وتشتهر بواحات النخيل والمزارع، وتضم مدينتي الهفوف والمبرز وعدداً من البلدات. نرتّب الزيارة حسب المدينة والحي.', 'الرطوبة والحرارة وقرب المزارع ترفع الحاجة إلى تنظيف المكيفات ومكافحة الحشرات والعناية بالخزانات. هنا خدماتنا في الأحساء مرتبة حسب القسم.' ),
	);
	$k = zad_city_norm_name( $name );
	$v = $t[ $k ] ?? array( 'نقدّم خدماتنا في ' . $name . ' للبيوت والفلل والشقق والمنشآت، ونحدد معك موعد الزيارة ونوع المكان قبل الحضور.', 'في هذه الصفحة خدماتنا في ' . $name . ' مرتبة حسب القسم، ولكل خدمة صفحتها بالتفاصيل والسعر، مع معاينة مجانية قبل البدء.' );
	return apply_filters( 'zad_city_intro_default', $v, $name );
}

/** Body text of a city page written in «نص صفحة المدينة» (allows headings and lists; the term description does not). */
function zad_city_body( $term_id ) { return trim( (string) get_term_meta( (int) $term_id, ZAD_CITY_BODY, true ) ); }

/** Opening text of a city page: «نص صفحة المدينة», else the term description, else the suggested text. array( html, is_suggested ). */
function zad_city_intro( $term ) {
	$d = zad_city_body( $term->term_id );
	if ( '' === wp_strip_all_tags( $d ) ) { $d = trim( (string) $term->description ); }
	if ( '' !== wp_strip_all_tags( $d ) ) { return array( wpautop( wp_kses_post( $d ) ), false ); }
	return array( '<p>' . implode( '</p><p>', array_map( 'esc_html', zad_city_intro_default( $term->name ) ) ) . '</p>', true );
}

/** «نص صفحة المدينة» field on the city term screen. WordPress strips headings from the term description, so the page body lives in its own meta. */
function zad_city_body_field( $term ) {
	echo '<tr class="form-field"><th scope="row"><label for="zad_city_body">نص صفحة المدينة</label></th><td>';
	wp_nonce_field( 'zad_city_body', 'zad_city_body_nonce' );
	echo '<textarea name="zad_city_body" id="zad_city_body" rows="14" class="large-text code">' . esc_textarea( zad_city_body( $term->term_id ) ) . '</textarea>';
	echo '<p class="description">يظهر في أعلى صفحة المدينة ويسمح بـ h2 وقوائم وروابط. الفارغ = يُستخدم «الوصف»، ثم النص المقترح.</p></td></tr>';
}
add_action( 'service_area_edit_form_fields', 'zad_city_body_field' );

function zad_city_body_save( $term_id ) {
	if ( ! isset( $_POST['zad_city_body_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_city_body_nonce'] ) ), 'zad_city_body' ) ) { return; }
	if ( ! current_user_can( 'edit_term', (int) $term_id ) ) { return; }
	$v = isset( $_POST['zad_city_body'] ) ? wp_kses_post( wp_unslash( $_POST['zad_city_body'] ) ) : '';
	if ( '' === trim( wp_strip_all_tags( $v ) ) ) { delete_term_meta( (int) $term_id, ZAD_CITY_BODY ); return; }
	update_term_meta( (int) $term_id, ZAD_CITY_BODY, $v );
}
add_action( 'edited_service_area', 'zad_city_body_save' );
add_action( 'created_service_area', 'zad_city_body_save' );

/** Cities named in the catalog that have no city page yet (Arabic names). */
function zad_city_missing() {
	$seen = array();
	foreach ( zad_catalog() as $f ) { foreach ( $f['items'] as $it ) { if ( '' !== $it['city'] ) { $seen[ $it['city'] ] = true; } } }
	return array_values( array_filter( array_keys( $seen ), function ( $c ) { return ! zad_city_term( $c ); } ) );
}

/** Parent city page of a new city («بريدة» goes under «القصيم»). Filter: zad_city_parents. */
function zad_city_parent_of( $name ) {
	$m = apply_filters( 'zad_city_parents', array( 'بريدة' => 'القصيم', 'عنيزة' => 'القصيم', 'المذنب' => 'القصيم' ) );
	return $m[ $name ] ?? '';
}

/** English slug of a city term from the registry (zad_city_data), '' when the term is not a registry city. */
function zad_city_slug_for( $t ) {
	$n = zad_city_norm_name( $t->name );
	foreach ( zad_city_data() as $slug => $row ) { if ( zad_city_norm_name( $row['name'] ) === $n ) { return $slug; } }
	return '';
}
/* Tools → «صفحات المدن (زاد)»: make the missing city pages, save the suggested texts; one undo. */
add_action( 'admin_menu', function () { add_management_page( 'صفحات المدن (زاد)', 'صفحات المدن (زاد)', 'manage_options', 'zad-cities', 'zad_cities_page' ); } );
function zad_cities_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$msg = '';
	if ( isset( $_POST['zad_ct_do'] ) && check_admin_referer( 'zad_cities' ) ) {
		$do  = sanitize_key( wp_unslash( $_POST['zad_ct_do'] ) );
		$log = (array) get_option( 'zad_cities_log', array( 'made' => array(), 'desc' => array() ) );
		if ( 'make' === $do ) {
			$n = 0;
			foreach ( zad_city_missing() as $c ) {
				$par = zad_city_parent_of( $c ); $pt = $par ? zad_city_term( $par ) : null;
				$r = wp_insert_term( $c, 'service_area', array( 'parent' => $pt ? (int) $pt->term_id : 0 ) );
				if ( ! is_wp_error( $r ) ) { $log['made'][] = (int) $r['term_id']; $n++; }
			}
			$msg = $n ? 'اتعملت ' . $n . ' صفحة مدينة.' : 'مفيش مدن ناقصة.';
		} elseif ( 'intro' === $do ) {
			$n = 0;
			foreach ( zad_city_terms() as $t ) {
				if ( '' !== trim( wp_strip_all_tags( (string) $t->description ) ) ) { continue; }
				$txt = implode( "\n\n", zad_city_intro_default( $t->name ) );
				if ( ! is_wp_error( wp_update_term( $t->term_id, 'service_area', array( 'description' => $txt ) ) ) ) { $log['desc'][ $t->term_id ] = md5( $txt ); $n++; }
			}
			$msg = $n ? 'اتحفظت المقدمة المقترحة في ' . $n . ' مدينة. عدّلها براحتك من المقالات ← المدن والأحياء.' : 'كل المدن ليها مقدمة بالفعل.';
		} elseif ( 'slugs' === $do ) {
			$old = (array) get_option( 'zad_city_slugs_old', array() ); $n = 0;
			foreach ( zad_city_terms() as $t ) {
				$new = zad_city_slug_for( $t );
				if ( '' === $new || $new === $t->slug ) { continue; }
				$r = wp_update_term( $t->term_id, 'service_area', array( 'slug' => $new ) );
				if ( ! is_wp_error( $r ) ) { $old[ $t->term_id ] = $t->slug; $n++; }
			}
			update_option( 'zad_city_slugs_old', $old, false ); update_option( 'zad_catalog_ver', time(), false );
			$msg = $n ? 'اتغيّرت روابط ' . $n . ' مدينة للإنجليزي.' : 'كل روابط المدن إنجليزي بالفعل.';
		} elseif ( 'unslugs' === $do ) {
			foreach ( (array) get_option( 'zad_city_slugs_old', array() ) as $tid => $slug ) { wp_update_term( (int) $tid, 'service_area', array( 'slug' => $slug ) ); }
			delete_option( 'zad_city_slugs_old' ); update_option( 'zad_catalog_ver', time(), false );
			$msg = 'رجّعت روابط المدن القديمة.';
		} elseif ( 'undo' === $do ) {
			foreach ( (array) $log['desc'] as $tid => $h ) { $t = get_term( (int) $tid, 'service_area' ); if ( $t && ! is_wp_error( $t ) && md5( (string) $t->description ) === $h ) { wp_update_term( (int) $tid, 'service_area', array( 'description' => '' ) ); } }
			foreach ( array_reverse( (array) $log['made'] ) as $tid ) { wp_delete_term( (int) $tid, 'service_area' ); }
			$log = array( 'made' => array(), 'desc' => array() );
			$msg = 'رجعت كل حاجة زي ما كانت (المقدمات اللي عدّلتها بإيدك ما اتمسحتش).';
		}
		update_option( 'zad_cities_log', $log, false );
	}
	$f = function ( $do, $label, $cls = 'button' ) { return '<form method="post" style="display:inline">' . wp_nonce_field( 'zad_cities', '_wpnonce', true, false ) . '<input type="hidden" name="zad_ct_do" value="' . esc_attr( $do ) . '"><button class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form> '; };
	echo '<div class="wrap" dir="rtl"><h1>صفحات المدن (زاد)</h1>';
	if ( $msg ) { echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>'; }
	echo '<p>كل مدينة ليها صفحة على <code dir="ltr">/' . esc_html( zad_slug( 'zad_areas_slug', 'areas' ) ) . '/{المدينة}/</code> فيها مقدمة، وكل خدماتها حسب القسم، وصفحات الأحياء. المقدمة هي «الوصف» بتاع المدينة في المقالات ← المدن والأحياء.</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>المدينة</th><th>الصفحة</th><th>المقدمة</th></tr></thead><tbody>';
	foreach ( zad_city_terms() as $t ) {
		$has = '' !== trim( wp_strip_all_tags( (string) $t->description ) );
		echo '<tr><td><b>' . esc_html( $t->name ) . '</b>' . ( $t->parent ? ' <small>(تحت ' . esc_html( get_term( $t->parent )->name ) . ')</small>' : '' ) . '</td><td><a href="' . esc_url( get_term_link( $t ) ) . '" target="_blank">فتح</a> · <a href="' . esc_url( get_edit_term_link( $t, 'service_area' ) ) . '">تعديل</a></td><td>' . ( $has ? 'مكتوبة' : 'فاضية (بيظهر النص المقترح)' ) . '</td></tr>';
	}
	foreach ( zad_city_missing() as $c ) { echo '<tr><td>' . esc_html( $c ) . '</td><td colspan="2" style="color:#b32d2e">مالهاش صفحة' . ( zad_city_parent_of( $c ) ? ' (هتتعمل تحت ' . esc_html( zad_city_parent_of( $c ) ) . ')' : '' ) . '</td></tr>'; }
	echo '</tbody></table><p style="margin-top:14px">';
	if ( zad_city_missing() ) { echo $f( 'make', 'اعمل صفحات المدن الناقصة', 'button button-primary' ); } // phpcs:ignore
	echo $f( 'intro', 'احفظ المقدمات المقترحة في المدن اللي مقدمتها فاضية' ); // phpcs:ignore
	echo $f( 'slugs', 'خلّي روابط المدن كلها إنجليزي' ); // phpcs:ignore
	if ( get_option( 'zad_city_slugs_old' ) ) { echo $f( 'unslugs', 'رجّع الروابط القديمة' ); } // phpcs:ignore
	$log = (array) get_option( 'zad_cities_log', array() );
	if ( ! empty( $log['made'] ) || ! empty( $log['desc'] ) ) { echo $f( 'undo', 'تراجع' ); } // phpcs:ignore
	echo '</p></div>';
}

/* ======================================================================================================
 * Section hubs (3.46.4): /cleaning/, /pest-control/, /cleaning/tanks/, /cleaning/sofa/, /cleaning/air-conditioning/ and the
 * /services/ sections show the family's whole catalog grouped by city (template-parts/hub-section.php).
 * ====================================================================================================== */

/** page id => family key, for the families whose own page is a published page (not a type archive). */
function zad_catalog_landing_ids() {
	static $m = null;
	if ( null !== $m ) { return $m; }
	$m = array();
	foreach ( zad_catalog_families() as $k => $f ) { $l = zad_catalog_landing( $f ); if ( $l && $l['id'] ) { $m[ $l['id'] ] = $k; } }
	return $m;
}

/** Family key of a section node (type archive or page), '' when it is not a family's own page. */
function zad_catalog_key_of_node( $node ) {
	if ( ! $node ) { return ''; }
	if ( 'page' === $node['kind'] ) { return zad_catalog_landing_ids()[ (int) $node['id'] ] ?? ''; }
	foreach ( zad_catalog_families() as $k => $f ) { foreach ( (array) ( $f['land'] ?? array() ) as $l ) { if ( 'type' === $l[0] && $l[1] === $node['pt'] ) { return $k; } } }
	return '';
}

/** The family of a hub, grouped by city: array( city => array( items, hoods, hub ) ), main cities first, '' (no city) last. */
function zad_catalog_by_city( $fk ) {
	$cat = zad_catalog();
	if ( ! isset( $cat[ $fk ] ) ) { return array(); }
	$f = $cat[ $fk ]; $g = array();
	foreach ( $f['items'] as $it ) { $g[ $it['city'] ]['items'][] = $it; }
	foreach ( (array) ( $f['hoods'] ?? array() ) as $c => $list ) { $g[ '-' === $c ? '' : $c ]['hoods'] = $list; }
	$main = array_values( zad_city_map() ); $out = array();
	foreach ( $g as $c => $v ) { $out[ $c ] = array( 'items' => $v['items'] ?? array(), 'hoods' => $v['hoods'] ?? array(), 'hub' => '' !== $c ? zad_city_hub_url( $c ) : '' ); }
	uksort( $out, function ( $a, $b ) use ( $main, $out ) {
		if ( '' === $a ) { return 1; } if ( '' === $b ) { return -1; }
		$ia = array_search( $a, $main, true ); $ib = array_search( $b, $main, true );
		$ra = false === $ia ? 50 : $ia; $rb = false === $ib ? 50 : $ib;
		if ( $ra !== $rb ) { return $ra - $rb; }
		return ( count( $out[ $b ]['items'] ) + count( $out[ $b ]['hoods'] ) ) - ( count( $out[ $a ]['items'] ) + count( $out[ $a ]['hoods'] ) );
	} );
	return $out;
}

/** The hub's list (schema ItemList, noindex test) = the family's catalog, not only the published pages directly under it. */
add_filter( 'zad_hub_data', function ( $d ) {
	if ( ! in_array( $d['kind'], array( 'type', 'page' ), true ) ) { return $d; }
	$node = zad_sec_current_node(); $fk = zad_catalog_key_of_node( $node ); $cat = zad_catalog();
	if ( '' === $fk || empty( $cat[ $fk ]['items'] ) ) { return $d; }
	$cards = array();
	foreach ( $cat[ $fk ]['items'] as $it ) { $cards[] = array( 'id' => $it['id'], 'title' => $it['label'] . ( '' !== $it['city'] ? ' في ' . $it['city'] : '' ), 'url' => $it['url'], 'tag' => '', 'city' => '' ); }
	$d['all'] = $cards; $d['cards'] = $cards; $d['pages'] = 1;
	return $d;
} );

/**
 * Blog articles of a family (3.46.4): the guide articles (the article types other than service posts, plus posts titled as a question/guide)
 * whose title matches the family's words. Used by the section hubs when no article is tied to the section by hand. Ids, newest first, cached.
 */
function zad_catalog_articles( $fk, $n = 6 ) {
	static $all = null;
	if ( null === $all ) {
		$key = 'zad_cat_art_' . md5( ZAD_VERSION . '|' . filemtime( __FILE__ ) . '|' . zad_catalog_ver() );
		$all = get_transient( $key );
		if ( ! is_array( $all ) ) {
			$all = array();
			$types = array_values( array_diff( zad_article_types(), array( 'post' ) ) );
			$q = array( 'post_status' => 'publish', 'numberposts' => 400, 'suppress_filters' => true, 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC' );
			$posts = $types ? get_posts( $q + array( 'post_type' => $types ) ) : array();
			foreach ( get_posts( $q + array( 'post_type' => 'post' ) ) as $p ) { if ( zad_catalog_is_article( $p->post_title ) ) { $posts[] = $p; } }
			foreach ( $posts as $p ) { $k = zad_article_family( $p->post_title ); if ( '' !== $k ) { $all[ $k ][] = (int) $p->ID; } }
			set_transient( $key, $all, 12 * HOUR_IN_SECONDS );
		}
	}
	return array_slice( $all[ $fk ] ?? array(), 0, $n );
}

/* ======================================================================================================
 * Link network (3.46.5): «روابط ذات صلة» on every service and neighbourhood page, «الخدمة المرتبطة» + «من نفس القسم» on articles.
 * Sizes (the lighter version agreed with the owner): city page → all its neighbourhoods + 4 other cities + 7 sections in the city
 * + 3 articles; neighbourhood page → its parent + 6 neighbourhoods + 3 sections + 2 articles. Built from zad_catalog().
 * ====================================================================================================== */

/** Where a page sits in the catalog: array( family, city, 'item'|'hood', index ) or null. */
function zad_catalog_locate( $id ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( zad_catalog() as $k => $f ) {
			foreach ( $f['items'] as $i => $it ) { $map[ (int) $it['id'] ] = array( $k, $it['city'], 'item', $i ); }
			foreach ( (array) ( $f['hoods'] ?? array() ) as $c => $list ) { foreach ( $list as $i => $h ) { if ( ! empty( $h[2] ) ) { $map[ (int) $h[2] ] = array( $k, '-' === $c ? '' : $c, 'hood', $i ); } } }
		}
	}
	return $map[ (int) $id ] ?? null;
}

/** Anchor for a service card in a city: «تنظيف خزانات بالرياض». */
function zad_catalog_anchor( $it ) {
	$l = $it['label']; $c = $it['city'];
	if ( '' !== $c && false === mb_strpos( zad_city_norm_name( $l ), zad_city_norm_name( $c ) ) ) { $l .= ' ب' . $c; }
	return $l;
}

/** n items of a list starting at a place that depends on the page (so every article gets linked from somewhere). */
function zad_rotate( $list, $seed, $n, $skip = array() ) {
	$list = array_values( array_filter( $list, function ( $x ) use ( $skip ) { return ! in_array( $x, $skip, true ); } ) );
	if ( count( $list ) <= $n ) { return $list; }
	$o = (int) $seed % count( $list );
	return array_slice( array_merge( array_slice( $list, $o ), array_slice( $list, 0, $o ) ), 0, $n );
}

/** Family of an article (the same words as the services, plus the pest names an article title uses). */
function zad_article_family( $title ) {
	if ( preg_match( '/صراصير|صرصور|(?<![\p{L}])(?:ال)?نمل|(?<![\p{L}])(?:ال)?بق(?![\p{L}])|فئران|وزغ|قراد|ذباب|بعوض|حشر|قوارض|مبيد/u', (string) $title ) ) { return 'pest'; } // an article about an insect is a pest article, wherever it lives (drains, kitchen…)
	$k = zad_catalog_family_of( $title, '' );
	if ( '' === $k && preg_match( '/رائح|روائح/u', (string) $title ) && preg_match( '/حمام|مطبخ|بالوع|مجار|صرف/u', (string) $title ) ) { $k = 'drain'; }
	return $k;
}

/** «الخدمة المرتبطة» + «من نفس القسم» at the end of an article. */
function zad_article_links_html( $post_id ) {
	$fk  = zad_article_family( get_the_title( $post_id ) );
	$cat = zad_catalog();
	if ( '' === $fk || ! isset( $cat[ $fk ] ) ) { return ''; }
	$f = $cat[ $fk ];
	$own = function_exists( 'zad_linked_service' ) ? zad_linked_service( $post_id ) : 0;
	$svc = array();
	if ( $own ) { $svc[] = array( zad_card_title( $own ), get_permalink( $own ) ); }
	// one page per main city, the one whose name is closest to the article title («صراصير» → «مكافحة حشرات», not «طارد الحمام»)
	$tw = array_filter( preg_split( '/\s+/u', zad_city_norm_name( get_the_title( $post_id ) ) ), function ( $w ) { return mb_strlen( $w ) > 3; } );
	$core = preg_split( '/\s+/u', zad_city_norm_name( $f['name'] ) )[0] ?? '';
	$score = function ( $it ) use ( $tw, $core ) { $l = zad_city_norm_name( $it['label'] ); $sc = 0; foreach ( $tw as $w ) { if ( false !== mb_strpos( $l, mb_substr( $w, 0, 5 ) ) ) { $sc += 2; } } if ( '' !== $core && false !== mb_strpos( $l, $core ) ) { $sc += 1; } return $sc; };
	$best = array();
	foreach ( $f['items'] as $it ) {
		if ( '' === $it['city'] || ( $own && (int) $it['id'] === (int) $own ) ) { continue; }
		$c = zad_city_norm_name( $it['city'] ); $v = $score( $it );
		if ( ! isset( $best[ $c ] ) || $v > $best[ $c ][0] ) { $best[ $c ] = array( $v, $it ); }
	}
	$n = 0;
	foreach ( $best as $b ) { if ( $n >= 3 ) { break; } $svc[] = array( zad_catalog_anchor( $b[1] ), $b[1]['url'] ); $n++; }
	$sib = zad_rotate( zad_catalog_articles( $fk, 30 ), $post_id, 3, array( (int) $post_id ) );
	ob_start();
	echo '<aside class="znet znet--art" aria-label="الخدمة المرتبطة"><div class="znet__box">';
	echo '<div class="znet__g"><h3>الخدمة المرتبطة</h3><ul><li><a class="is-main" href="' . esc_url( $f['url'] ) . '">' . esc_html( 'خدمات ' . $f['name'] ) . '</a></li>';
	foreach ( $svc as $s ) { echo '<li><a href="' . esc_url( $s[1] ) . '">' . esc_html( $s[0] ) . '</a></li>'; }
	echo '</ul></div>';
	if ( $sib ) { echo '<div class="znet__g"><h3>من نفس القسم</h3><ul class="znet__arts">'; foreach ( $sib as $aid ) { echo '<li><a href="' . esc_url( get_permalink( $aid ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $aid ) ) ) . '</a></li>'; } echo '</ul></div>'; }
	echo '</div></aside>';
	return ob_get_clean();
}
add_filter( 'the_content', function ( $c ) {
	if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $c; }
	$id = get_the_ID(); $pt = get_post_type( $id );
	if ( in_array( $pt, zad_service_types(), true ) || in_array( $pt, function_exists( 'zad_faq_types' ) ? zad_faq_types() : array(), true ) || ! in_array( $pt, zad_article_types(), true ) ) { return $c; }
	if ( 'post' === $pt && ! zad_catalog_is_article( get_the_title( $id ) ) ) { return $c; } // the old service articles keep their own layout until converted
	return $c . zad_article_links_html( $id );
}, 45 );

/** A section page still carrying the old designed service content (a «…-pillar» wrapper): the hub shows only its intro + its own questions. */
function zad_sec_is_legacy_body( $content ) { return (bool) preg_match( '/class=["\'][^"\']*\bzad-[a-z0-9]+-pillar\b/', (string) $content ); }

/** Intro of a page: the excerpt, else its first one or two real paragraphs (≥ 60 characters, ≤ 120 words together). */
function zad_sec_intro_html( $post ) {
	if ( '' !== trim( (string) $post->post_excerpt ) ) { return wpautop( esc_html( trim( wp_strip_all_tags( $post->post_excerpt ) ) ) ); }
	$html = apply_filters( 'the_content', $post->post_content );
	$html = preg_replace( '#<(style|script|nav)\b[^>]*>.*?</\1>#is', '', $html );
	preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $html, $m );
	$out = array(); $w = 0;
	foreach ( $m[1] as $p ) {
		$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $p, ENT_QUOTES, 'UTF-8' ) ) ) );
		if ( mb_strlen( $t ) < 60 ) { continue; }
		$out[] = $t; $w += count( preg_split( '/\s+/u', $t ) );
		if ( count( $out ) >= 2 || $w >= 90 ) { break; }
	}
	return $out ? '<p>' . implode( '</p><p>', array_map( 'esc_html', $out ) ) . '</p>' : '';
}

/** The page's own questions (the «الأسئلة الشائعة» field of the page). */
function zad_sec_own_faq( $id ) { return array_values( array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $r ) { return is_array( $r ) && ! empty( $r['q'] ) && ! empty( $r['a'] ); } ) ); }

/* ======================================================================================================
 * Admin (3.46.6): sections and services apart. «كل الخدمات» lists the services, the «الأقسام» submenu lists the section pages,
 * and the old service_cat taxonomy is renamed «التصنيفات» so the two are not confused. Views on the list: الخدمات · الأقسام · الكل.
 * ====================================================================================================== */

/** ids of the section pages of a type (any status but trash). */
function zad_admin_section_ids( $pt ) {
	static $m = array();
	if ( isset( $m[ $pt ] ) ) { return $m[ $pt ]; }
	$ids = array();
	foreach ( get_posts( array( 'post_type' => $pt, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true, 'zad_all' => true ) ) as $id ) {
		if ( 'section' === zad_page_kind( $id ) ) { $ids[] = (int) $id; }
	}
	return $m[ $pt ] = $ids;
}
function zad_admin_kind_types() { return array_values( array_intersect( zad_hub_pt_hier(), array( 'zad_service', 'cleaning', 'pest_control' ) ) ); }

add_action( 'admin_menu', function () {
	foreach ( zad_admin_kind_types() as $pt ) {
		$parent = 'edit.php?post_type=' . $pt;
		add_submenu_page( $parent, 'الأقسام', 'الأقسام', 'edit_posts', 'edit.php?post_type=' . $pt . '&zad_kind=section', '', 1 );
	}
}, 20 );

add_filter( 'register_taxonomy_args', function ( $args, $tax ) {
	if ( 'service_cat' === $tax && isset( $args['labels'] ) ) { $args['labels']['name'] = 'تصنيفات الخدمات'; $args['labels']['menu_name'] = 'التصنيفات'; $args['labels']['singular_name'] = 'تصنيف'; $args['labels']['add_new_item'] = 'إضافة تصنيف'; }
	return $args;
}, 20, 2 );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || ! function_exists( 'get_current_screen' ) ) { return; }
	global $pagenow;
	$pt = $q->get( 'post_type' );
	if ( 'edit.php' !== $pagenow || ! is_string( $pt ) || ! in_array( $pt, zad_admin_kind_types(), true ) ) { return; }
	$k = isset( $_GET['zad_kind'] ) ? sanitize_key( wp_unslash( $_GET['zad_kind'] ) ) : 'service'; // phpcs:ignore
	if ( ! empty( $_GET['s'] ) && ! isset( $_GET['zad_kind'] ) ) { $k = 'all'; } // phpcs:ignore — a search looks everywhere
	if ( 'all' === $k ) { return; }
	$sec = zad_admin_section_ids( $pt );
	if ( 'section' === $k ) { $q->set( 'post__in', $sec ? $sec : array( 0 ) ); }
	elseif ( $sec ) { $q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $sec ) ); }
}, 30 );

foreach ( array( 'zad_service', 'cleaning', 'pest_control' ) as $zad_pt ) {
	add_filter( 'views_edit-' . $zad_pt, function ( $views ) use ( $zad_pt ) {
		$k   = isset( $_GET['zad_kind'] ) ? sanitize_key( wp_unslash( $_GET['zad_kind'] ) ) : 'service'; // phpcs:ignore
		$sec = count( zad_admin_section_ids( $zad_pt ) );
		$all = (int) array_sum( (array) wp_count_posts( $zad_pt ) ) - (int) ( wp_count_posts( $zad_pt )->trash ?? 0 ) - (int) ( wp_count_posts( $zad_pt )->{'auto-draft'} ?? 0 );
		$u   = admin_url( 'edit.php?post_type=' . $zad_pt );
		$a   = function ( $key, $label, $n ) use ( $k, $u ) { return '<a href="' . esc_url( add_query_arg( 'zad_kind', $key, $u ) ) . '"' . ( $k === $key ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $n . ')</span></a>'; };
		return array_merge( array( 'zad_svc' => $a( 'service', 'الخدمات', max( 0, $all - $sec ) ), 'zad_sec' => $a( 'section', 'الأقسام', $sec ), 'zad_all' => $a( 'all', 'الكل', $all ) ), array_diff_key( $views, array( 'all' => 1 ) ) );
	} );
}
/** Keep «الأقسام» highlighted in the menu while its list is open. */
add_filter( 'submenu_file', function ( $file, $parent ) {
	if ( isset( $_GET['zad_kind'] ) && 'section' === $_GET['zad_kind'] && isset( $_GET['post_type'] ) ) { return 'edit.php?post_type=' . sanitize_key( wp_unslash( $_GET['post_type'] ) ) . '&zad_kind=section'; } // phpcs:ignore
	return $file;
}, 10, 2 );
