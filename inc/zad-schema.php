<?php defined( 'ABSPATH' ) || exit;
/**
 * Structured data (JSON-LD), shaped like the reference site's graph:
 *  @graph: Organization, WebSite(+SearchAction), LocalBusiness, BreadcrumbList, SiteNavigationElement
 *  then separate scripts: Service(+OfferCatalog), FAQPage, VideoObject (service pages), QAPage (questions).
 */

function zad_company() {
	$b = zsc_settings()['business'];
	$c = zad_company_raw();
	// the validated company data fills whatever the theme options leave empty
	$map = array( 'legal' => $b['legal_name'], 'phone' => $b['telephone'], 'email' => $b['email'], 'street' => $b['street'], 'city' => $b['locality'], 'region' => $b['region'], 'postal' => $b['postal_code'], 'lat' => $b['lat'], 'lng' => $b['lng'], 'cr' => $b['cr'], 'vat' => $b['vat'], 'desc' => $b['description'] );
	foreach ( $map as $k => $v ) {
		if ( empty( $c[ $k ] ) || ( 'legal' === $k && get_bloginfo( 'name' ) === $c[ $k ] ) ) { $c[ $k ] = $v; }
	}
	if ( ! $c['same'] ) { $c['same'] = $b['same_as']; }
	return $c;
}

function zad_company_raw() {
	$logo = zad_opt( 'memopt_logo' );
	$logo = is_array( $logo ) ? $logo : array();
	$w    = 0;
	$h    = 0;
	if ( ! empty( $logo['id'] ) ) {
		$m = wp_get_attachment_metadata( $logo['id'] );
		$w = (int) ( $m['width'] ?? 0 );
		$h = (int) ( $m['height'] ?? 0 );
	}
	$sameas = array();
	foreach ( array( 'memopt_fb', 'memopt_tw', 'memopt_insta', 'zad_linkedin', 'zad_pinterest', 'zad_tiktok', 'zad_snapchat', 'memopt_yt' ) as $k ) {
		$u = zad_opt( $k );
		if ( $u && preg_match( '#^https?://#', $u ) ) {
			$sameas[] = $u;
		}
	}
	$phone = zad_intl_number( zad_opt( 'memopt_phone' ) );
	return array(
		'name'   => get_bloginfo( 'name' ),
		'legal'  => zad_opt( 'zad_legal_name', get_bloginfo( 'name' ) ),
		'logo'   => $logo['url'] ?? '',
		'logo_w' => $w,
		'logo_h' => $h,
		'phone'  => $phone ? '+' . $phone : '',
		'email'  => zad_opt( 'memopt_mail' ),
		'street' => trim( zad_opt( 'zad_street' ) . ( zad_opt( 'zad_district' ) ? '، ' . zad_opt( 'zad_district' ) : '' ) ),
		'city'   => zad_opt( 'zad_city_name', 'الرياض' ),
		'region' => zad_opt( 'zad_region', '' ),
		'postal' => zad_opt( 'zad_postal' ),
		'lat'    => zad_opt( 'zad_lat' ),
		'lng'    => zad_opt( 'zad_lng' ),
		'map'    => zad_opt( 'zad_map_url' ),
		'cr'     => zad_opt( 'zad_cr' ),
		'vat'    => zad_opt( 'zad_vat' ),
		'same'   => $sameas,
		'desc'   => zad_opt( 'zad_site_desc', get_bloginfo( 'description' ) ),
	);
}

function zad_knows_about() {
	$names = array();
	$t = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true ) );
	if ( $t && ! is_wp_error( $t ) ) {
		$names = wp_list_pluck( $t, 'name' );
	}
	return array_values( $names );
}

function zad_opening_hours() {
	$out = array();
	foreach ( zad_lines( zad_opt( 'zad_hours_spec', "Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday | 08:00 | 22:00" ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		if ( count( $c ) >= 3 ) {
			$days = array_values( array_filter( array_map( 'trim', explode( ',', $c[0] ) ) ) );
			$out[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => $c[1], 'closes' => $c[2] );
		}
	}
	return $out;
}

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

function zad_nav_schema() {
	$locs = get_nav_menu_locations();
	if ( empty( $locs['mainmenu'] ) ) {
		return null;
	}
	$items = wp_get_nav_menu_items( $locs['mainmenu'] );
	if ( ! $items ) {
		return null;
	}
	$names = array();
	$urls  = array();
	foreach ( $items as $it ) {
		$names[] = $it->title;
		$urls[]  = $it->url;
	}
	return array( '@type' => 'SiteNavigationElement', '@id' => home_url( '/#sitenav' ), 'name' => $names, 'url' => $urls );
}

/** Price text → schema price specification (handles ranges, "from", per month). */
function zad_price_spec( $text ) {
	$t = zad_digits_en( $text );
	preg_match_all( '/\d[\d,]*/', $t, $m );
	$nums = array_map( function ( $x ) { return (int) str_replace( ',', '', $x ); }, $m[0] );
	if ( ! $nums ) {
		return null;
	}
	$monthly = (bool) preg_match( '/شهر/u', $t );
	$base    = array( 'priceCurrency' => 'SAR' );
	if ( count( $nums ) >= 2 && preg_match( '/[-–—]/u', $t ) ) {
		$spec = array( '@type' => 'PriceSpecification', 'priceCurrency' => 'SAR', 'minPrice' => min( $nums ), 'maxPrice' => max( $nums ) );
	} elseif ( preg_match( '/(يبدأ|تبدأ|^\s*من)/u', $t ) ) {
		$spec = array( '@type' => 'UnitPriceSpecification', 'priceCurrency' => 'SAR', 'minPrice' => $nums[0] );
	} else {
		$spec = array( '@type' => 'UnitPriceSpecification', 'priceCurrency' => 'SAR', 'price' => $nums[0] );
	}
	if ( $monthly ) {
		$spec['@type']    = 'UnitPriceSpecification';
		$spec['unitText'] = 'شهر';
		$spec['unitCode'] = 'MON';
	}
	return $spec;
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

function zad_graph() {
	$c    = zad_company();
	$home = home_url( '/' );
	$org  = array(
		'@type'     => 'Organization',
		'@id'       => $home . '#organization',
		'name'      => $c['name'],
		'legalName' => $c['legal'],
		'url'       => $home,
	);
	if ( $c['logo'] ) {
		$logo = array( '@type' => 'ImageObject', 'url' => $c['logo'] );
		if ( $c['logo_w'] ) { $logo['width'] = $c['logo_w']; $logo['height'] = $c['logo_h']; }
		$org['logo'] = $logo;
	}
	if ( $c['same'] ) { $org['sameAs'] = $c['same']; }
	if ( zad_knows_about() ) { $org['knowsAbout'] = zad_knows_about(); }

	$site = array(
		'@type'     => 'WebSite',
		'@id'       => $home . '#website',
		'url'       => $home,
		'name'      => $c['name'],
		'publisher' => array( '@id' => $home . '#organization' ),
		'inLanguage'=> 'ar',
		'potentialAction' => array( array(
			'@type'       => 'SearchAction',
			'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ),
			'query-input' => array( '@type' => 'PropertyValueSpecification', 'valueRequired' => true, 'valueName' => 'search_term_string' ),
		) ),
	);
	if ( $c['desc'] ) { $site['description'] = $c['desc']; }

	$lb = array(
		'@type'     => 'LocalBusiness',
		'@id'       => $home . '#localbusiness',
		'name'      => $c['name'],
		'legalName' => $c['legal'],
		'url'       => $home,
	);
	if ( $c['logo'] ) { $lb['image'] = $org['logo']; }
	if ( zad_opt( 'zad_price_range', '100–500 ر.س' ) ) { $lb['priceRange'] = zad_opt( 'zad_price_range', '100–500 ر.س' ); }
	if ( $c['phone'] ) { $lb['telephone'] = $c['phone']; }
	if ( $c['email'] ) { $lb['email'] = $c['email']; }
	$addr = array( '@type' => 'PostalAddress', 'addressCountry' => 'SA' );
	if ( $c['street'] ) { $addr['streetAddress'] = $c['street']; }
	if ( $c['city'] ) { $addr['addressLocality'] = $c['city']; }
	if ( $c['region'] ) { $addr['addressRegion'] = $c['region']; }
	if ( $c['postal'] ) { $addr['postalCode'] = $c['postal']; }
	$lb['address'] = $addr;
	if ( $c['lat'] && $c['lng'] ) {
		$lb['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (string) $c['lat'], 'longitude' => (string) $c['lng'] );
	}
	if ( zad_opening_hours() ) { $lb['openingHoursSpecification'] = zad_opening_hours(); }
	if ( $c['same'] ) { $lb['sameAs'] = $c['same']; }
	if ( $c['map'] ) { $lb['hasMap'] = $c['map']; }
	$cities = get_terms( array( 'taxonomy' => 'service_area', 'parent' => 0, 'hide_empty' => false ) );
	$areas  = array();
	if ( $cities && ! is_wp_error( $cities ) ) {
		foreach ( $cities as $t ) { $areas[] = array( '@type' => 'City', 'name' => $t->name ); }
	}
	$lb['areaServed'] = $areas ? $areas : array( array( '@type' => 'City', 'name' => $c['city'] ) );
	if ( $c['vat'] ) { $lb['taxID'] = $c['vat']; }
	$ids = array();
	if ( $c['cr'] ) { $ids[] = array( '@type' => 'PropertyValue', 'name' => 'السجل التجاري', 'value' => $c['cr'] ); }
	if ( $c['vat'] ) { $ids[] = array( '@type' => 'PropertyValue', 'name' => 'الرقم الضريبي', 'value' => $c['vat'] ); }
	if ( $ids ) { $lb['identifier'] = $ids; }
	if ( zad_knows_about() ) { $lb['knowsAbout'] = zad_knows_about(); }
	$lb['parentOrganization'] = array( '@id' => $home . '#organization' );

	$graph = array( $org, $site, $lb );
	$crumbs = zad_current_crumbs();
	if ( $crumbs ) {
		$items = array();
		foreach ( $crumbs as $i => $cr ) {
			$it = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $cr[0] );
			if ( $cr[1] ) { $it['item'] = $cr[1]; }
			$items[] = $it;
		}
		$graph[] = array( '@type' => 'BreadcrumbList', '@id' => zad_current_url() . '#breadcrumb', 'itemListElement' => $items );
	}
	$nav = zad_nav_schema();
	if ( $nav ) { $graph[] = $nav; }
	return array( '@context' => 'https://schema.org', '@graph' => $graph );
}

function zad_service_schema( $id ) {
	$home = home_url( '/' );
	$name = get_the_title( $id );
	$url  = get_permalink( $id );
	$cats = get_the_terms( $id, 'service_cat' );
	$cat  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';

	$areas = array();
	$terms = get_the_terms( $id, 'service_area' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$areas[] = array( '@type' => 0 === (int) $t->parent ? 'City' : 'Place', 'name' => $t->name );
		}
	}
	$desc = get_post_meta( $id, '_zad_tagline', true );
	$ex   = get_the_excerpt( $id );
	$desc = trim( wp_strip_all_tags( $ex ?: $desc ) );

	$s = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Service',
		'name'        => $name,
		'serviceType' => $cat ? $cat : $name,
		'provider'    => array( '@id' => $home . '#localbusiness' ),
		'url'         => $url,
	);
	if ( $areas ) { $s['areaServed'] = $areas; }
	if ( $cat ) { $s['additionalType'] = $cat; }
	if ( has_post_thumbnail( $id ) ) {
		$tid  = get_post_thumbnail_id( $id );
		$meta = wp_get_attachment_metadata( $tid );
		$img  = array( '@type' => 'ImageObject', 'url' => wp_get_attachment_url( $tid ), 'caption' => $name, 'name' => $name, 'creator' => array( '@id' => $home . '#organization' ), 'creditText' => get_bloginfo( 'name' ), 'copyrightNotice' => get_bloginfo( 'name' ), 'copyrightYear' => (int) get_the_date( 'Y', $tid ) );
		$lic  = zad_page_url( '', array( 'terms', 'الشروط-والاحكام', 'terms-and-conditions' ) );
		$acq  = zad_page_url( 'temp/memo-contact.php', array( 'contact', 'اتصل-بنا' ) );
		if ( $lic ) { $img['license'] = $lic; }
		if ( $acq ) { $img['acquireLicensePage'] = $acq; }
		if ( ! empty( $meta['width'] ) ) { $img['width'] = (int) $meta['width']; $img['height'] = (int) $meta['height']; }
		$s['image'] = $img;
	}
	if ( $desc ) { $s['description'] = $desc; }

	$offers = array();
	foreach ( zad_price_rows( $id ) as $r ) { // rows of the block that is actually shown on the page
		$spec = zad_price_spec( $r['price'] );
		if ( ! $spec ) { continue; }
		$o = array( '@type' => 'Offer', 'name' => $r['name'], 'priceCurrency' => 'SAR', 'priceSpecification' => $spec );
		if ( $r['group'] ) { $o['category'] = $r['group']; }
		$offers[] = $o;
	}
	if ( $offers ) {
		$s['hasOfferCatalog'] = array( '@type' => 'OfferCatalog', 'name' => 'أسعار ' . $name, 'itemListElement' => $offers );
		$nums = array();
		foreach ( zad_price_rows( $id ) as $r ) { $sp = zad_price_spec( $r['price'] ); if ( $sp ) { foreach ( array( 'price', 'minPrice', 'maxPrice' ) as $k ) { if ( isset( $sp[ $k ] ) ) { $nums[] = (float) $sp[ $k ]; } } } }
		if ( $nums ) { $s['offers'] = array( '@type' => 'AggregateOffer', 'priceCurrency' => 'SAR', 'lowPrice' => min( $nums ), 'highPrice' => max( $nums ), 'offerCount' => count( $offers ), 'url' => $url ); }
	}
	return $s;
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
	$nav   = zad_nav_schema();
	$id    = is_singular() ? get_queried_object_id() : 0;
	$ptype = $id ? get_post_type( $id ) : '';

	if ( is_front_page() ) {
		$nodes = array_merge( $site, zsc_home_nodes() );
		if ( $nav ) { $nodes[] = $nav; }
		zad_print_graph( $nodes );
	} elseif ( $id && in_array( $ptype, zad_service_types(), true ) && 'none' !== zsc_meta( $id, 'mode' ) ) {
		$nodes = zsc_with_breadcrumb( array_merge( $site, zsc_service_nodes( $id ) ), get_permalink( $id ) );
		if ( $nav ) { $nodes[] = $nav; }
		zad_print_graph( $nodes );
	} elseif ( is_author() ) {
		$uid    = get_queried_object_id();
		$person = zsc_person_node( $uid );
		$aurl   = get_author_posts_url( $uid );
		$page   = array( '@type' => 'ProfilePage', '@id' => $aurl . '#webpage', 'url' => $aurl, 'name' => get_the_author_meta( 'display_name', $uid ), 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'inLanguage' => 'ar' );
		if ( $person ) { $page['mainEntity'] = array( '@id' => $person['@id'] ); }
		$nodes = array_merge( array_slice( $site, 0, 2 ), array( $page ) );
		if ( $person ) { $nodes[] = $person; }
		zad_print_graph( zsc_with_breadcrumb( $nodes, $aurl ) );
	} elseif ( $id && zsc_is_article_page( $id ) ) {
		zad_print_graph( zsc_with_breadcrumb( array_merge( array_slice( $site, 0, 2 ), zsc_article_nodes( $id ) ), get_permalink( $id ) ) );
	} elseif ( $id ) {
		$u  = get_permalink( $id );
		$wp = array( '@type' => 'WebPage', '@id' => $u . '#webpage', 'url' => $u, 'name' => wp_strip_all_tags( get_the_title( $id ) ), 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'inLanguage' => 'ar', 'datePublished' => get_post_time( 'c', true, $id ), 'dateModified' => get_post_modified_time( 'c', true, $id ) );
		zad_print_graph( zsc_with_breadcrumb( array_merge( array_slice( $site, 0, 2 ), array( $wp ) ), $u ) );
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
