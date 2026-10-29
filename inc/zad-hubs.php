<?php defined( 'ABSPATH' ) || exit;
/**
 * Section pages («قسم» / hub): one simple page that says what we do and where, and links to the pages under it. It never competes with them:
 * no prices, warranty, "choose by case", long text, FAQ, reviews or works — those live on the service pages only.
 *
 * What is a section (zad_hub_ctx()):
 *  - the archive of every adopted service type (/cleaning/, /pest-control/ …) — not the native /services/ archive;
 *  - the archives of the service taxonomies (service_cat, service_area = «قسم مدينة», cleaning-sections, pest_sections …) and the /cities/ page;
 *  - a hierarchical service page whose «نوع الصفحة» is «قسم», or «تلقائي» and the page has NO city and published children that DO have a city
 *    (/cleaning/tanks/). A page that has a city (/pest-control/riyadh/) stays a service page, even with district pages under it, unless it is set to «قسم» by hand.
 *
 * Data: zad_hubs (headline + lead only; the other fields stay saved, hidden), the term/page field «عنوان القسم (H1)», the pages themselves. Nothing is written to content.
 */

const ZAD_HUB_KIND = '_zad_page_kind'; // auto | service | section
const ZAD_HUB_H1   = '_zad_hub_h1';
const ZAD_HUB_PER  = 24;

/* ------------------------------------------------------------------ */
/* Which pages are sections                                            */
/* ------------------------------------------------------------------ */

function zad_hub_pt_hier() { return array_values( array_filter( zad_service_types(), function ( $t ) { return is_post_type_hierarchical( $t ); } ) ); } // zad_service too (3.46.0: hierarchical under /services/)

/** id => true for every published page the AUTO rule turns into a section (cached with the city version: refreshed whenever a service page is saved). */
function zad_hub_auto_map() {
	static $mem = array();
	$v = zad_city_ver();
	if ( isset( $mem[ $v ] ) ) { return $mem[ $v ]; }
	$key = 'zad_hubkind_' . $v;
	$map = get_transient( $key );
	if ( ! is_array( $map ) ) {
		$map = array();
		$sc  = zad_city_scope_map();
		$kids = array();
		foreach ( get_posts( array( 'post_type' => zad_hub_pt_hier(), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'id=>parent', 'suppress_filters' => true, 'no_found_rows' => true, 'zad_all' => true ) ) as $pid => $parent ) {
			$kids[ (int) $parent ][] = (int) $pid;
		}
		foreach ( $kids as $parent => $list ) {
			if ( ! $parent || ! in_array( $sc[ $parent ] ?? '', array( '', 'all' ), true ) ) { continue; } // has a city (or no longer exists): stays a service page
			foreach ( $list as $c ) { if ( ! in_array( $sc[ $c ] ?? '', array( '', 'all' ), true ) ) { $map[ $parent ] = true; break; } }
		}
		set_transient( $key, $map, 12 * HOUR_IN_SECONDS );
	}
	return $mem[ $v ] = $map;
}

/** 'section' | 'service' for a hierarchical service page: the choice made on the page, else the rule. */
function zad_page_kind( $id ) {
	$id = (int) $id;
	if ( ! in_array( get_post_type( $id ), zad_hub_pt_hier(), true ) ) { return 'service'; }
	if ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) { return 'service'; }
	$m = (string) get_post_meta( $id, ZAD_HUB_KIND, true );
	if ( 'section' === $m || 'service' === $m ) { return $m; }
	if ( function_exists( 'zad_catalog_landing_ids' ) && isset( zad_catalog_landing_ids()[ $id ] ) ) { return 'section'; } // a family's own page (/cleaning/tanks/) is a section even while its city pages are drafts
	return isset( zad_hub_auto_map()[ $id ] ) ? 'section' : 'service';
}

/* ------------------------------------------------------------------ */
/* Names                                                               */
/* ------------------------------------------------------------------ */

/** «شركة تنظيف خزانات بالرياض 150 ريال ـ زاد السعودية» → «خدمات تنظيف خزانات». '' when nothing usable is left. */
function zad_hub_clean_name( $raw ) {
	$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $raw, ENT_QUOTES, 'UTF-8' ) ) ) );
	$seg = preg_split( '~\s*(?:[:|/–—]|\s[ـ-]\s)\s*~u', $t, -1, PREG_SPLIT_NO_EMPTY );
	$t = $seg ? trim( $seg[0] ) : $t;
	$t = zad_strip_phones( $t );
	$brand = array_filter( array( trim( (string) get_bloginfo( 'name' ) ), trim( (string) zad_opt( 'zad_provider', '' ) ), 'زاد السعودية' ) );
	foreach ( $brand as $b ) { $t = preg_replace( '/(?<![\p{L}])' . preg_quote( $b, '/' ) . '(?![\p{L}])/u', ' ', $t ); }
	$t = preg_replace( '/(?<![\p{L}])(?:في|ب)\s*(?:ال)?(?:سعودية|مملكة)(?![\p{L}])/u', ' ', $t );
	$t = preg_replace( '/(?<![\p{L}])(?:شركة|شركات|أفضل|افضل|أحسن|احسن|أرخص|ارخص|صفحات|صفحة|قسم)(?![\p{L}])/u', ' ', $t );
	foreach ( array_values( zad_city_map() ) as $city ) { $t = preg_replace( '/(?<![\p{L}])(?:في\s+|ب|ل)?' . preg_quote( $city, '/' ) . '(?![\p{L}])/u', ' ', $t ); }
	$t = preg_replace( '/\d+\s*ريال/u', ' ', $t );
	$t = preg_replace( '/\s+في\s+.*$/u', '', $t ); // «تنظيف مكيفات في مكانها لتنظيف…» → «تنظيف مكيفات»: a section name stays short
	$t = preg_replace( '/^[\s\-ـ،,.]+|[\s\-ـ،,.]+$/u', '', preg_replace( '/\s+/u', ' ', $t ) ); // (not trim(): it would cut Arabic letters in the middle)
	$w = preg_split( '/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $w ) > 7 ) { $w = array_slice( $w, 0, 7 ); }
	while ( $w && in_array( end( $w ), array( 'و', 'في', 'ل', 'من', 'على', 'عن', 'مع', 'ب' ), true ) ) { array_pop( $w ); }
	$t = implode( ' ', $w );
	if ( '' === $t || in_array( $t, array( 'خدمات', 'خدمة', 'الخدمات', 'الخدمة' ), true ) ) { return ''; }
	if ( 0 === mb_strpos( $t, 'خدمة ' ) ) { return 'خدمات ' . mb_substr( $t, 5 ); }
	return 0 === mb_strpos( $t, 'خدمات' ) ? $t : 'خدمات ' . $t;
}

/** The section's name without the leading «خدمات» (for sentences: «مقالات عن …»). */
function zad_hub_topic( $h1 ) { $t = trim( preg_replace( '/^\s*خدمات\s+/u', '', (string) $h1 ) ); return '' !== $t ? $t : (string) $h1; }

/* ------------------------------------------------------------------ */
/* Context                                                             */
/* ------------------------------------------------------------------ */

function zad_hub_taxes() {
	$tx = array();
	foreach ( zad_service_types() as $pt ) { foreach ( get_object_taxonomies( $pt, 'objects' ) as $t ) { if ( $t->public && ! in_array( $t->name, array( 'post_tag', 'category', 'post_format', 'faq_cat' ), true ) ) { $tx[ $t->name ] = $t->name; } } }
	return array_values( $tx );
}

function zad_hub_cities_page_id() {
	static $id = null;
	if ( null === $id ) {
		$p = get_page_by_path( zad_slug( 'zad_areas_slug', 'areas' ) ) ?: get_page_by_path( 'cities' );   // 3.47.2: the page was renamed cities → areas
		$id = (int) apply_filters( 'zad_hub_cities_page_id', $p ? $p->ID : 0 );
	}
	return $id;
}

/** array( kind => type|term|city|page|cities, … ) when the current request is a section page, else null. */
function zad_hub_ctx() {
	static $c = false;
	if ( false !== $c ) { return $c; }
	if ( is_admin() || ! did_action( 'wp' ) ) { return null; }
	$c = null;
	if ( is_post_type_archive() ) {
		$pt = zad_query_pt();
		if ( $pt && 'zad_service' !== $pt && in_array( $pt, zad_service_types(), true ) ) { $c = array( 'kind' => 'type', 'pt' => $pt ); }
	} elseif ( is_tax() ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term && in_array( $t->taxonomy, zad_hub_taxes(), true ) ) { $c = array( 'kind' => 'service_area' === $t->taxonomy ? 'city' : 'term', 'term' => $t ); }
	} elseif ( is_singular() ) {
		$p = get_queried_object();
		if ( $p instanceof WP_Post ) {
			if ( in_array( $p->post_type, zad_hub_pt_hier(), true ) && 'section' === zad_page_kind( $p->ID ) ) { $c = array( 'kind' => 'page', 'post' => $p ); }
			elseif ( 'page' === $p->post_type && $p->ID === zad_hub_cities_page_id() ) { $c = array( 'kind' => 'cities', 'post' => $p ); }
		}
	}
	return $c;
}

function zad_hub_card( $p, $with_city = true ) {
	$tag = trim( (string) get_post_meta( $p->ID, '_zad_tagline', true ) );
	if ( '' === $tag ) { $tag = trim( wp_strip_all_tags( (string) $p->post_excerpt ) ); }
	$sc = $with_city ? zad_service_city_scope( $p->ID ) : '';
	return array( 'id' => (int) $p->ID, 'title' => zad_card_title( $p->ID ), 'url' => get_permalink( $p ), 'tag' => wp_trim_words( $tag, 22, '…' ), 'city' => ! in_array( $sc, array( '', 'all' ), true ) ? $sc : '' );
}

function zad_hub_posts( $args ) {
	return get_posts( array_merge( array( 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'suppress_filters' => true, 'no_found_rows' => true, 'zad_all' => true ), $args ) );
}

/** City slug of an area term (same normalisation the service pages use). */
function zad_hub_term_city( $term ) { return zad_city_norm_slug( $term->name ); }

/** The cards of a context: every page directly under it. */
function zad_hub_cards_all( $ctx ) {
	$out = array();
	switch ( $ctx['kind'] ) {
		case 'type':
			foreach ( zad_hub_posts( array( 'post_type' => $ctx['pt'], 'post_parent' => 0 ) ) as $p ) { $out[] = zad_hub_card( $p ); }
			break;
		case 'page':
			foreach ( zad_hub_posts( array( 'post_type' => $ctx['post']->post_type, 'post_parent' => $ctx['post']->ID ) ) as $p ) { $out[] = zad_hub_card( $p ); }
			break;
		case 'term':
			$posts = zad_hub_posts( array( 'post_type' => zad_service_types(), 'numberposts' => -1, 'tax_query' => array( array( 'taxonomy' => $ctx['term']->taxonomy, 'field' => 'term_id', 'terms' => (int) $ctx['term']->term_id, 'include_children' => true ) ) ) );
			$in = array(); foreach ( $posts as $p ) { $in[ $p->ID ] = 1; }
			$roots = array_values( array_filter( $posts, function ( $p ) use ( $in ) { return ! isset( $in[ (int) $p->post_parent ] ); } ) );
			if ( 1 === count( $roots ) && in_array( $roots[0]->post_type, zad_hub_pt_hier(), true ) && 'section' === zad_page_kind( $roots[0]->ID ) ) { // one section page = this term: list what is under it
				foreach ( zad_hub_posts( array( 'post_type' => $roots[0]->post_type, 'post_parent' => $roots[0]->ID ) ) as $p ) { $out[] = zad_hub_card( $p ); }
			} else { foreach ( $roots as $p ) { $out[] = zad_hub_card( $p ); } }
			break;
		case 'city':
			$city = zad_hub_term_city( $ctx['term'] ); $seen = array();
			foreach ( zad_hub_posts( array( 'post_type' => zad_service_types(), 'post_parent' => 0 ) ) as $r ) {
				$sc = zad_service_city_scope( $r->ID );
				if ( $sc === $city ) { $seen[ $r->ID ] = zad_hub_card( $r ); continue; }
				if ( in_array( $sc, array( '', 'all' ), true ) && in_array( $r->post_type, zad_hub_pt_hier(), true ) ) {
					foreach ( zad_hub_posts( array( 'post_type' => $r->post_type, 'post_parent' => $r->ID ) ) as $c ) { if ( zad_service_city_scope( $c->ID ) === $city ) { $seen[ $c->ID ] = zad_hub_card( $c ); } }
				}
			}
			$out = array_values( $seen );
			break;
		case 'cities':
			foreach ( (array) get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => 0 ) ) as $t ) {
				if ( ! $t instanceof WP_Term ) { continue; }
				$n = count( zad_hub_cards_all( array( 'kind' => 'city', 'term' => $t ) ) );
				if ( ! $n ) { continue; } // a city with no published page is not offered
				$out[] = array( 'id' => 0, 'title' => $t->name, 'url' => (string) get_term_link( $t ), 'tag' => 'خدماتنا في ' . $t->name . ': ' . $n . ( $n > 10 ? ' صفحة' : ( $n > 2 ? ' صفحات' : ' صفحة' ) ), 'city' => '' );
			}
			break;
	}
	return $out;
}

/** First paragraph of a text as a short lead (≤ 60 words, cut at a sentence end when possible). */
function zad_hub_first_para( $content, $max = 60 ) {
	$c = excerpt_remove_blocks( strip_shortcodes( (string) $content ) );
	$c = preg_replace( '~<h[1-6][^>]*>.*?</h[1-6]>~isu', "\n\n", $c );
	$c = preg_replace( '~</(?:p|div|li|ul|ol|blockquote|table)>|<br\s*/?>~iu', "\n\n", $c );
	foreach ( preg_split( '/\n\s*\n/u', wp_strip_all_tags( $c ) ) as $chunk ) {
		$chunk = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $chunk, ENT_QUOTES, 'UTF-8' ) ) );
		if ( count( preg_split( '/\s+/u', $chunk, -1, PREG_SPLIT_NO_EMPTY ) ) >= 8 ) { return zad_hub_limit_words( $chunk, $max ); }
	}
	return '';
}

function zad_hub_limit_words( $text, $max = 60 ) {
	$w = preg_split( '/\s+/u', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $w ) <= $max ) { return implode( ' ', $w ); }
	$cut = implode( ' ', array_slice( $w, 0, $max ) );
	if ( preg_match( '/^(.{40,}[.؟!…])(?:\s|$)/su', $cut, $m ) ) { return $m[1]; }
	return rtrim( $cut, " ،,.:؛" ) . '…';
}

function zad_hub_cities_text( $names ) {
	$names = array_values( array_unique( array_filter( $names ) ) );
	if ( count( $names ) > 1 ) { $last = array_pop( $names ); return implode( '، ', $names ) . ' و' . $last; }
	return $names ? $names[0] : '';
}

/** A term of $taxonomy that matches the section (same slug, same/similar name); null when none. */
function zad_hub_match_term( $taxonomy, $name, $slug ) {
	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) { return null; }
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
	if ( ! $terms || is_wp_error( $terms ) ) { return null; }
	$probe = (object) array( 'name' => zad_hub_topic( $name ), 'slug' => rawurldecode( (string) $slug ) );
	foreach ( $terms as $t ) { if ( rawurldecode( $t->slug ) === $probe->slug && '' !== $probe->slug ) { return $t; } }
	return null;
}

function zad_hub_taxonomy_of( $types ) {
	foreach ( (array) $types as $t ) { foreach ( get_object_taxonomies( $t, 'objects' ) as $o ) { if ( $o->public && $o->hierarchical && 'category' !== $o->name ) { return $o->name; } } }
	return '';
}


/** The «محتوى الأرشيف» page linked to a type (draft allowed): array( id, WP_Post|null ). */
function zad_hub_type_page( $pt ) {
	list( $id, $p ) = zad_hr_mapped( ZAD_AC_MAP, array( $pt ) );
	return array( $id, $p );
}

/** H1 of a type's archive: zad_hubs headline → name of the linked page (cleaned) → name of the type (cleaned) → «خدمات {slug}». */
function zad_hub_type_h1( $pt ) {
	$hub = zad_hub_opt( $pt );
	if ( ! empty( $hub['headline'] ) ) { return trim( $hub['headline'] ); }
	list( , $mp ) = zad_hub_type_page( $pt );
	$h1 = $mp ? zad_hub_clean_name( get_the_title( $mp ) ) : '';
	if ( '' === $h1 ) { $o = get_post_type_object( $pt ); $h1 = zad_hub_clean_name( $o ? $o->labels->name : '' ); }
	if ( '' === $h1 ) { // «صفحات الخدمات» cleans to nothing: the short name the admin menu uses (cleaning → التنظيف), never the URL slug
		$map = apply_filters( 'zad_type_label_map', array( 'cleaning' => 'التنظيف', 'pest_control' => 'مكافحة الحشرات' ) );
		if ( ! empty( $map[ $pt ] ) ) { $h1 = zad_hub_clean_name( $map[ $pt ] ); }
	}
	return '' !== $h1 ? $h1 : 'خدمات ' . str_replace( '-', ' ', zad_type_base( $pt ) );
}

/** A service_cat term that is just the category of one whole type (slug = the type's URL base): it mirrors that type's archive. Returns the type key or ''. */
function zad_hub_mirror_type( $term ) {
	if ( ! $term instanceof WP_Term || 'service_cat' !== $term->taxonomy ) { return ''; }
	$sl = str_replace( '_', '-', strtolower( rawurldecode( $term->slug ) ) );
	foreach ( zad_service_types() as $pt ) {
		if ( 'zad_service' === $pt ) { continue; }
		if ( $sl === str_replace( '_', '-', strtolower( zad_type_base( $pt ) ) ) || $sl === str_replace( '_', '-', strtolower( $pt ) ) ) { return $pt; }
	}
	return '';
}

/* ------------------------------------------------------------------ */
/* Everything the template and the schema need (built once per request) */
/* ------------------------------------------------------------------ */

function zad_hub_data() {
	static $d = null;
	if ( null !== $d ) { return $d ?: null; }
	$ctx = zad_hub_ctx();
	if ( ! $ctx ) { $d = false; return null; }
	$k = $ctx['kind']; $slug = ''; $url = ''; $h1 = ''; $lead = ''; $src_page = 0; $mp = null; $mirror = '';
	$hub = ( 'type' === $k ) ? zad_hub_opt( $ctx['pt'] ) : array();
	$over = '';
	if ( 'type' === $k ) {
		$url = (string) get_post_type_archive_link( $ctx['pt'] ); $slug = zad_type_base( $ctx['pt'] );
		list( $src_page, $mp ) = zad_hub_type_page( $ctx['pt'] );
		$h1 = zad_hub_type_h1( $ctx['pt'] );
		if ( ! empty( $hub['lead'] ) ) { $lead = zad_hub_limit_words( $hub['lead'], 60 ); }
		elseif ( $mp ) { $lead = zad_hub_first_para( $mp->post_content ); }
	} elseif ( 'term' === $k || 'city' === $k ) {
		$t = $ctx['term']; $url = (string) get_term_link( $t ); $slug = $t->slug;
		$over = trim( (string) get_term_meta( $t->term_id, ZAD_HUB_H1, true ) );
		if ( 'city' === $k ) { $h1 = '' !== $over ? $over : 'خدماتنا في ' . $t->name; }
		else {
			$mirror = zad_hub_mirror_type( $t );
			$h1 = '' !== $over ? $over : ( $mirror ? zad_hub_type_h1( $mirror ) : zad_hub_clean_name( $t->name ) );
			if ( '' === $h1 ) { $h1 = $t->name; }
		}
		$desc = trim( wp_strip_all_tags( (string) $t->description ) ); if ( '' === $desc && function_exists( 'zad_term_intro' ) ) { $desc = zad_hub_first_para( zad_term_intro( $t ) ); }
		if ( '' !== $desc ) { $lead = zad_hub_limit_words( $desc, 60 ); }
	} else { // page | cities
		$p = $ctx['post']; $url = get_permalink( $p ); $slug = $p->post_name;
		$over = trim( (string) get_post_meta( $p->ID, ZAD_HUB_H1, true ) );
		if ( 'cities' === $k ) { $h1 = '' !== $over ? $over : trim( (string) preg_split( '~\s*[:|]\s*~u', wp_strip_all_tags( get_the_title( $p ) ) )[0] ); }
		else { $h1 = '' !== $over ? $over : zad_hub_clean_name( zad_card_title( $p->ID ) ); if ( '' === $h1 ) { $h1 = zad_hub_clean_name( get_the_title( $p ) ); } if ( '' === $h1 ) { $h1 = wp_strip_all_tags( get_the_title( $p ) ); } }
		if ( '' !== trim( (string) $p->post_excerpt ) ) { $lead = zad_hub_limit_words( $p->post_excerpt, 60 ); }
	}
	$all   = zad_hub_cards_all( $ctx );
	$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	$pages = in_array( $k, array( 'type', 'term' ), true ) ? max( 1, (int) ceil( count( $all ) / ZAD_HUB_PER ) ) : 1;
	$cards = in_array( $k, array( 'type', 'term' ), true ) ? array_slice( $all, ( $paged - 1 ) * ZAD_HUB_PER, ZAD_HUB_PER ) : array_slice( $all, 0, 60 );
	// cities: only the ones that have a published page under this section
	$cities = array();
	foreach ( $all as $c ) { if ( $c['city'] && ! isset( $cities[ $c['city'] ] ) ) { $cities[ $c['city'] ] = array( 'name' => zad_city_label( $c['city'] ), 'url' => $c['url'] ); } }
	$chips = ( 'cities' !== $k && 'city' !== $k && count( $cities ) > 1 ) ? array_values( $cities ) : array();
	if ( '' === $lead ) {
		$cn = 'cities' === $k ? array() : array_column( $cities, 'name' );
		if ( 'city' === $k ) { $lead = 'نقدّم في ' . $ctx['term']->name . ' الخدمات الموضحة أدناه، ولكل خدمة صفحتها بتفاصيلها في ' . $ctx['term']->name . '.'; }
		elseif ( 'cities' === $k ) { $lead = 'اختر مدينتك وخدمتك.'; }
		else { $lead = 'نقدّم ' . $h1 . ( $cn ? ' في ' . zad_hub_cities_text( $cn ) : '' ) . '. اختر الخدمة' . ( $cn ? ' والمدينة' : '' ) . ' المناسبة من القائمة أدناه.'; }
	}
	// two link lines: articles + FAQ categories that match this section (none = no link)
	$name = 'page' === $k || 'type' === $k ? $h1 : zad_hub_topic( $h1 ); $topic = zad_hub_topic( $h1 ); $links = array();
	if ( ! in_array( $k, array( 'city', 'cities' ), true ) ) {
		$at = zad_hub_match_term( zad_hub_taxonomy_of( zad_article_types() ), $h1, $slug );
		if ( $at ) { $u = get_term_link( $at ); if ( ! is_wp_error( $u ) ) { $links[] = array( 'مقالات عن ' . $topic, $u ); } }
		$ft = zad_hub_match_term( zad_hub_taxonomy_of( zad_faq_types() ), $h1, $slug );
		if ( $ft ) { $u = get_term_link( $ft ); if ( ! is_wp_error( $u ) ) { $links[] = array( 'أسئلة شائعة عن ' . $topic, $u ); } }
	}
	$d = array( 'ctx' => $ctx, 'kind' => $k, 'h1' => $h1, 'lead' => $lead, 'url' => $url, 'cards' => $cards, 'all' => $all, 'chips' => $chips, 'paged' => $paged, 'pages' => $pages, 'links' => $links, 'cities' => array_column( $cities, 'name' ), 'topic' => $topic, 'mirror' => $mirror );
	$d = apply_filters( 'zad_hub_data', $d );
	return $d;
}

/* ------------------------------------------------------------------ */
/* Template, CSS, 301 for empty pages                                   */
/* ------------------------------------------------------------------ */

add_filter( 'template_include', function ( $tpl ) {
	if ( zad_hub_ctx() ) { $f = locate_template( 'hub.php' ); if ( $f ) { return $f; } }
	return $tpl;
}, 21 );

// Frontend assets are registered by inc/core/assets.php.


/** /page/2/ of a section that has no second page of cards: back to the first page (301). */
add_action( 'template_redirect', function () {
	if ( ! zad_hub_ctx() || ! in_array( zad_hub_ctx()['kind'], array( 'type', 'term' ), true ) ) { return; }
	$d = zad_hub_data();
	if ( $d && $d['paged'] > 1 && $d['paged'] > $d['pages'] ) { wp_safe_redirect( $d['url'], 301 ); exit; }
}, 5 );

/* ------------------------------------------------------------------ */
/* SEO: default title, robots                                          */
/* ------------------------------------------------------------------ */

/** The title that wins when nothing custom exists: «خدمات {القسم} في السعودية | الموقع» (city: «خدماتنا في {المدينة} | الموقع»). */
function zad_hub_default_title( $d ) {
	$site = trim( (string) get_bloginfo( 'name' ) );
	if ( 'city' === $d['kind'] ) { return $d['h1'] . ' | ' . $site; }
	if ( 'cities' === $d['kind'] ) { return ''; }
	return $d['h1'] . ' في السعودية | ' . $site;
}

/** Has the owner written a title for this section (theme SEO field / archive-content)? */
function zad_hub_custom_title( $d ) {
	$c = $d['ctx'];
	if ( 'type' === $d['kind'] ) { return '' !== trim( (string) zad_ac_seo( 'title' ) ); }
	if ( 'term' === $d['kind'] || 'city' === $d['kind'] ) {
		return '' !== trim( (string) zad_seo_term_meta( $c['term'], 'title' ) );
	}
	return '' !== trim( (string) zad_seo_meta( $c['post']->ID, 'title' ) );
}

foreach ( array( 'pre_get_document_title' ) as $zad_h ) {
	add_filter( $zad_h, function ( $t ) {
		$d = zad_hub_data();
		if ( ! $d || zad_hub_custom_title( $d ) ) { return $t; }
		$v = zad_hub_default_title( $d );
		return '' !== $v ? $v : $t;
	}, 30 );
}

/** Pages 2+ of a section, a section with nothing to list, and a category page that only repeats the archive of a whole type (/services-category/pest-control/ next to /pest-control/) are noindex,follow. */
function zad_hub_noindex() {
	$d = zad_hub_data();
	if ( $d && 'city' === $d['kind'] && function_exists( 'zad_city_catalog' ) ) { return $d['paged'] > 1 || ! zad_city_catalog( $d['ctx']['term'] ); } // a city page lists the whole catalog of the city, not only the pages tagged with it
	return $d && ( $d['paged'] > 1 || ! $d['all'] || ( $d['mirror'] && apply_filters( 'zad_hub_mirror_noindex', true, $d ) ) );
}
foreach ( array( 'wp_robots' ) as $zad_h ) {
	add_filter( $zad_h, function ( $r ) {
		if ( ! zad_hub_noindex() ) { return $r; }
		if ( is_array( $r ) ) { unset( $r['index'], $r['nofollow'] ); $r['noindex'] = true; $r['follow'] = true; return $r; }
		return 'noindex, follow';
	}, 30 );
}

/* ------------------------------------------------------------------ */
/* Schema: CollectionPage + ItemList (the cards, in order) + BreadcrumbList — no Service / Offer / FAQPage / AggregateRating */
/* ------------------------------------------------------------------ */

function zad_hub_schema_nodes() {
	$d = zad_hub_data();
	if ( ! $d ) { return array(); }
	$url = $d['url']; if ( $d['paged'] > 1 && get_option( 'permalink_structure' ) ) { $url = trailingslashit( $url ) . 'page/' . $d['paged'] . '/'; }
	$page = zsc_page_node( 'CollectionPage', $url, wp_strip_all_tags( $d['h1'] ), isset( $d['ctx']['post'] ) ? $d['ctx']['post']->ID : 0 );
	$els = array(); $pos = 1;
	foreach ( $d['cards'] as $c ) { $els[] = array( '@type' => 'ListItem', 'position' => $pos++, 'item' => array( '@type' => 'WebPage', 'name' => $c['title'], 'url' => $c['url'] ) ); }
	$nodes = array( $page );
	if ( $els ) {
		$list = array( '@type' => 'ItemList', '@id' => $url . '#itemlist', 'name' => wp_strip_all_tags( $d['h1'] ), 'numberOfItems' => count( $els ), 'itemListElement' => $els );
		$nodes[0]['mainEntity'] = array( '@id' => $list['@id'] );
		$nodes[] = $list;
	}
	if ( 'page' === $d['kind'] && function_exists( 'zad_sec_own_faq' ) ) { // 3.46.5: the page's own questions are shown on the hub too
		$ents = array();
		foreach ( zad_sec_own_faq( $d['ctx']['post']->ID ) as $r ) { $ents[] = array( '@type' => 'Question', 'name' => wp_strip_all_tags( $r['q'] ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( wp_strip_all_tags( $r['a'] ) ) ) ); }
		if ( $ents ) { $nodes[] = array( '@type' => 'FAQPage', '@id' => $url . '#faq', 'isPartOf' => array( '@id' => $url . '#webpage' ), 'mainEntity' => $ents ); }
	}
	return array( $url, $nodes );
}

/* ------------------------------------------------------------------ */
/* Admin: «نوع الصفحة» + «عنوان القسم (H1)» on pages; the H1 field on terms */
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes', function () {
	if ( ! zad_can_admin_fields() ) { return; }
	foreach ( zad_hub_pt_hier() as $pt ) { add_meta_box( 'zad-hub-kind', 'نوع الصفحة (خدمة / قسم)', 'zad_hub_box', $pt, 'side', 'high' ); }
} );

function zad_hub_box( $post ) {
	wp_nonce_field( 'zad_hub_kind', 'zad_hub_nonce' );
	$m = (string) get_post_meta( $post->ID, ZAD_HUB_KIND, true ); if ( ! in_array( $m, array( 'service', 'section' ), true ) ) { $m = 'auto'; }
	$auto = isset( zad_hub_auto_map()[ $post->ID ] ) ? 'قسم' : 'خدمة';
	echo '<p><label><input type="radio" name="zad_hub_kind" value="auto"' . checked( $m, 'auto', false ) . '> تلقائي <small>(الآن: <b>' . esc_html( $auto ) . '</b>)</small></label><br><label><input type="radio" name="zad_hub_kind" value="service"' . checked( $m, 'service', false ) . '> خدمة</label><br><label><input type="radio" name="zad_hub_kind" value="section"' . checked( $m, 'section', false ) . '> قسم</label></p>';
	echo '<p class="description">التلقائي: «قسم» لو الصفحة بلا مدينة وتحتها صفحات منشورة بمدن. أي صفحة لها مدينة تبقى «خدمة» (إلا لو اخترت «قسم» بنفسك). صفحة القسم: عنوان ومقدمة قصيرة وكروت الصفحات التي تحتها فقط، بلا أسعار ولا ضمان ولا أسئلة.</p>';
	echo '<p><label for="zad_hub_h1"><b>عنوان القسم (H1)</b> — اختياري</label><input type="text" id="zad_hub_h1" name="zad_hub_h1" class="widefat" value="' . esc_attr( (string) get_post_meta( $post->ID, ZAD_HUB_H1, true ) ) . '" placeholder="' . esc_attr( zad_hub_clean_name( zad_card_title( $post->ID ) ) ) . '"></p>';
}

add_action( 'save_post', function ( $id, $post ) {
	if ( ! zad_can_admin_fields() || ! isset( $_POST['zad_hub_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_hub_nonce'] ) ), 'zad_hub_kind' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) || ! in_array( $post->post_type, zad_hub_pt_hier(), true ) ) { return; }
	$k = isset( $_POST['zad_hub_kind'] ) ? sanitize_key( wp_unslash( $_POST['zad_hub_kind'] ) ) : 'auto';
	if ( in_array( $k, array( 'service', 'section' ), true ) ) { update_post_meta( $id, ZAD_HUB_KIND, $k ); } else { delete_post_meta( $id, ZAD_HUB_KIND ); }
	$h = isset( $_POST['zad_hub_h1'] ) ? sanitize_text_field( wp_unslash( $_POST['zad_hub_h1'] ) ) : '';
	if ( '' !== $h ) { update_post_meta( $id, ZAD_HUB_H1, $h ); } else { delete_post_meta( $id, ZAD_HUB_H1 ); }
	if ( function_exists( 'zad_city_bump' ) ) { zad_city_bump(); } // the section list is cached with the city version
}, 20, 2 );

add_action( 'init', function () {
	foreach ( zad_hub_pt_hier() as $pt ) {
		add_filter( "manage_{$pt}_posts_columns", function ( $c ) { $c['zad_kind'] = 'النوع'; return $c; } );
		add_action( "manage_{$pt}_posts_custom_column", function ( $col, $id ) { if ( 'zad_kind' === $col ) { echo 'section' === zad_page_kind( $id ) ? '<b>قسم</b>' : 'خدمة'; } }, 10, 2 );
	}
	foreach ( zad_hub_taxes() as $tx ) {
		add_action( $tx . '_edit_form_fields', function ( $term ) {
			wp_nonce_field( 'zad_hub_term', 'zad_hub_term_nonce' );
			echo '<tr class="form-field"><th><label for="zad_hub_h1">عنوان القسم (H1)</label></th><td><input type="text" name="zad_hub_h1" id="zad_hub_h1" class="large-text" value="' . esc_attr( (string) get_term_meta( $term->term_id, ZAD_HUB_H1, true ) ) . '" placeholder="' . esc_attr( 'service_area' === $term->taxonomy ? 'خدماتنا في ' . $term->name : zad_hub_clean_name( $term->name ) ) . '"><p class="description">اختياري. الفارغ = يُشتق من الاسم (بلا «شركة» ولا مدينة).</p></td></tr>';
		} );
		add_action( 'edited_' . $tx, function ( $term_id ) {
			if ( ! isset( $_POST['zad_hub_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_hub_term_nonce'] ) ), 'zad_hub_term' ) || ! current_user_can( 'manage_categories' ) ) { return; }
			$h = isset( $_POST['zad_hub_h1'] ) ? sanitize_text_field( wp_unslash( $_POST['zad_hub_h1'] ) ) : '';
			if ( '' !== $h ) { update_term_meta( $term_id, ZAD_HUB_H1, $h ); } else { delete_term_meta( $term_id, ZAD_HUB_H1 ); }
		} );
	}
}, 90 );

/* The trail of a section ends with its own short name (not the long SEO title of the page), for the page and for the BreadcrumbList. */
add_filter( 'zad_current_crumbs', function ( $c ) {
	if ( ! zad_hub_ctx() ) { return $c; }
	$d = zad_hub_data();
	if ( ! $d ) { return $c; }
	if ( ! $c || 'type' === $d['kind'] ) { return array( array( 'الرئيسية', home_url( '/' ) ), array( $d['h1'], '' ) ); }
	$last = count( $c ) - 1;
	if ( empty( $c[ $last ][1] ) ) { $c[ $last ][0] = $d['h1']; }
	return $c;
}, 40 );
