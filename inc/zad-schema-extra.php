<?php
/**
 * Schema: page-type nodes (About / Contact / Collection / price catalog), image rights, itemlists.
 * One generator: these functions only BUILD nodes; the single wp_head printer lives in inc/zad-schema.php.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/* ================= page roles ================= */

/** 'about' | 'contact' | 'services' | 'prices' | '' for a page: the settings win; template / slug are the fallback when a setting is empty. */
function zad_page_role( $id ) {
	$id = (int) $id;
	if ( ! $id || 'page' !== get_post_type( $id ) ) { return ''; }
	foreach ( array( 'about' => 'zad_about_page', 'contact' => 'zad_contact_page' ) as $role => $opt ) {
		if ( (int) zad_opt( $opt, 0 ) === $id ) { return $role; }
	}
	$tpl  = (string) get_post_meta( $id, '_wp_page_template', true );
	$slug = rawurldecode( (string) get_post_field( 'post_name', $id ) );
	if ( 'temp/zad-prices.php' === $tpl ) { return 'prices'; }
	if ( 'temp/memo-services.php' === $tpl ) { return 'services'; }
	if ( ! (int) zad_opt( 'zad_about_page', 0 ) && ( 'temp/memo-about.php' === $tpl || in_array( $slug, array( 'about', 'about-us', 'من-نحن' ), true ) ) ) { return 'about'; }
	if ( ! (int) zad_opt( 'zad_contact_page', 0 ) && ( 'temp/memo-contact.php' === $tpl || in_array( $slug, array( 'contact', 'contact-us', 'اتصل-بنا' ), true ) ) ) { return 'contact'; }
	return '';
}

/** Permalink of the page chosen for a role (published only), '' when none. */
function zad_role_url( $role ) {
	$id = (int) zad_opt( 'zad_' . $role . '_page', 0 );
	if ( $id && 'publish' === get_post_status( $id ) ) { return get_permalink( $id ); }
	return 'contact' === $role ? zad_page_url( 'temp/memo-contact.php', array( 'contact', 'اتصل-بنا' ) ) : '';
}

/* ================= images: rights only on «صورة من شغلنا» ================= */

/** URL of the image-rights page (setting). '' = no rights claims anywhere. The old license-URL setting is never read (it may hold the privacy page). */
function zad_img_license_url() {
	$p = (int) zad_opt( 'zad_img_rights_page', 0 );
	return ( $p && 'publish' === get_post_status( $p ) ) ? get_permalink( $p ) : '';
}

/** Is this attachment ours? Per-image choice wins (yes / no); no choice = the global switch «كل الصور من شغلنا». */
function zad_img_is_ours( $aid ) {
	if ( ! $aid ) { return false; }
	$v = (string) get_post_meta( $aid, '_zad_img_ours', true );
	if ( 'yes' === $v ) { return true; }
	if ( 'no' === $v ) { return false; }
	return (bool) zad_opt( 'zad_img_all_ours', false );
}

/** Ownership / licence properties for an ImageObject: only for our images AND when a rights page is set. */
function zad_img_claims( $aid, $year, $holder_name ) {
	$lic = zad_img_license_url();
	if ( '' === $lic || ! zad_img_is_ours( $aid ) ) { return array(); }
	$home = trailingslashit( home_url() );
	$c    = array( 'license' => $lic );
	$acq  = zad_role_url( 'contact' );
	if ( '' !== $acq ) { $c['acquireLicensePage'] = $acq; }
	if ( '' !== $holder_name ) {
		$c['creditText']      = $holder_name;
		$c['creator']         = array( '@id' => $home . '#organization' );
		$c['copyrightHolder'] = array( '@id' => $home . '#organization' );
		$c['copyrightNotice'] = '© ' . ( $year ? $year . ' ' : '' ) . $holder_name;
		if ( $year ) { $c['copyrightYear'] = (int) $year; }
	}
	return $c;
}

/* attachment editor: «صورة من شغلنا» */
add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
	$v   = (string) get_post_meta( $post->ID, '_zad_img_ours', true );
	$opt = array( '' => 'حسب الإعداد العام', 'yes' => 'صورة من شغلنا', 'no' => 'ليست من شغلنا' );
	$h   = '<select name="attachments[' . (int) $post->ID . '][zad_img_ours]">';
	foreach ( $opt as $k => $l ) { $h .= '<option value="' . esc_attr( $k ) . '"' . selected( $v, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	$fields['zad_img_ours'] = array( 'label' => 'ملكية الصورة (للسكيما)', 'input' => 'html', 'html' => $h . '</select>', 'helps' => 'تُطبع حقوق النشر والترخيص في بيانات Google لهذه الصورة فقط إذا كانت «من شغلنا» وصفحة الحقوق محددة في إعدادات القالب.' );
	return $fields;
}, 10, 2 );
add_filter( 'attachment_fields_to_save', function ( $post, $att ) {
	if ( isset( $att['zad_img_ours'] ) ) {
		$v = in_array( $att['zad_img_ours'], array( 'yes', 'no' ), true ) ? $att['zad_img_ours'] : '';
		'' === $v ? delete_post_meta( $post['ID'], '_zad_img_ours' ) : update_post_meta( $post['ID'], '_zad_img_ours', $v );
	}
	return $post;
}, 10, 2 );

/* ================= page nodes ================= */

function zsc_page_node( $type, $url, $name, $post_id = 0 ) {
	$n = array( '@type' => $type, '@id' => $url . '#webpage', 'url' => $url, 'name' => $name, 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'inLanguage' => 'ar' );
	if ( $post_id ) { $n['datePublished'] = get_post_time( 'c', true, $post_id ); $n['dateModified'] = get_post_modified_time( 'c', true, $post_id ); }
	return $n;
}

/** ItemList node: kind = service (Service {name,url}) | faq (Question {name,url}, no answer) | article (Article {headline,url}). Needs 2+ items. */
function zsc_itemlist_node( $url, $name, $posts, $kind ) {
	$els = array(); $pos = 1;
	foreach ( $posts as $p ) {
		if ( ! $p instanceof WP_Post || 'publish' !== $p->post_status ) { continue; }
		$u = get_permalink( $p );
		if ( 'faq' === $kind ) { $it = array( '@type' => 'Question', 'name' => zad_card_title( $p->ID ), 'url' => $u ); }
		elseif ( 'article' === $kind ) { $h = zad_card_title( $p->ID ); $it = array( '@type' => 'Article', 'headline' => mb_strlen( $h, 'UTF-8' ) > 110 ? mb_substr( $h, 0, 109, 'UTF-8' ) . '…' : $h, 'url' => $u ); }
		else { $it = array( '@type' => 'Service', 'name' => zad_card_title( $p->ID ), 'url' => $u ); }
		$els[] = array( '@type' => 'ListItem', 'position' => $pos++, 'item' => $it );
	}
	if ( count( $els ) < 2 ) { return null; }
	return array( '@type' => 'ItemList', '@id' => $url . '#itemlist', 'name' => $name, 'numberOfItems' => count( $els ), 'itemListElement' => $els );
}

/** The listing the current request shows: array( posts, kind ) or null. Only the posts of the page being viewed (pagination-safe). */
function zsc_current_listing() {
	global $wp_query;
	if ( is_post_type_archive( zad_service_types() ) || is_tax( array( 'service_cat', 'service_area' ) ) ) { return array( array_slice( (array) $wp_query->posts, 0, 40 ), 'service' ); }
	if ( is_post_type_archive( zad_faq_types() ) || is_tax( 'faq_cat' ) ) { return array( array_slice( (array) $wp_query->posts, 0, 40 ), 'faq' ); }
	if ( is_post_type_archive( zad_article_types() ) ) { return array( array_slice( (array) $wp_query->posts, 0, 40 ), 'article' ); }
	return null;
}

/** Children of a service page (pillar → cities, city → services…): the items of a hub page. */
function zsc_hub_children( $id ) {
	if ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) { return array(); }
	return get_posts( array( 'post_type' => get_post_type( $id ), 'post_parent' => $id, 'post_status' => 'publish', 'numberposts' => 60, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'suppress_filters' => true, 'zad_all' => true ) );
}

/** Nodes for a listing / archive request (CollectionPage + ItemList), or null when the request is not one. */
function zsc_archive_nodes() {
	$l = zsc_current_listing();
	if ( ! $l ) { return null; }
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$url   = zad_current_url();
	if ( $paged > 1 && get_option( 'permalink_structure' ) ) { $url = trailingslashit( $url ) . 'page/' . $paged . '/'; } // each page of the listing is its own page node
	$name = wp_strip_all_tags( get_the_archive_title() );
	$page = zsc_page_node( 'CollectionPage', $url, $name );
	$list = zsc_itemlist_node( $url, $name, $l[0], $l[1] );
	if ( $list ) { $page['mainEntity'] = array( '@id' => $list['@id'] ); }
	return $list ? array( $page, $list ) : array( $page );
}

/** The «الأسعار» page: OfferCatalog of the services that have a numeric price (no price = not listed). */
function zsc_prices_catalog( $url ) {
	$items = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 60, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'suppress_filters' => true, 'zad_all' => true ) ) as $s ) {
		$min = 0; $max = 0; $has_range = false;
		foreach ( zad_price_rows( $s->ID ) as $r ) {
			$pp = zad_price_parse( $r['price'] );
			if ( 'quote' === $pp['kind'] ) { continue; }
			$min = $min ? min( $min, $pp['min'] ) : $pp['min'];
			$hi  = 'range' === $pp['kind'] ? $pp['max'] : ( 'fixed' === $pp['kind'] ? $pp['min'] : 0 );
			if ( $hi ) { $max = max( $max, $hi ); }
		}
		if ( ! $min ) { continue; }
		$n    = function ( $v ) { return ( floor( $v ) == $v ) ? (int) $v : (float) $v; };
		$spec = array( '@type' => 'PriceSpecification', 'minPrice' => $n( $min ) );
		if ( $max ) { $spec['maxPrice'] = $n( $max ); }
		$spec['priceCurrency'] = 'SAR';
		$items[] = array( '@type' => 'Offer', 'itemOffered' => array( '@type' => 'Service', 'name' => zad_card_title( $s->ID ), 'url' => get_permalink( $s ) ), 'priceSpecification' => $spec );
	}
	return $items ? array( '@type' => 'OfferCatalog', '@id' => $url . '#offercatalog', 'name' => wp_strip_all_tags( get_the_title() ), 'itemListElement' => $items ) : null;
}
