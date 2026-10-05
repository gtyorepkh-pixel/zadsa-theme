<?php defined( 'ABSPATH' ) || exit;
/**
 * Helpers: options, contact data, icons, schema.
 */

function zad_opt( $key, $default = '' ) {
	static $opts = null;
	if ( null === $opts ) {
		$opts = get_option( '_memo_theme_options' );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
	}
	if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] && array() !== $opts[ $key ] ) {
		return $opts[ $key ];
	}
	// Home-page content that is true for any site on this theme (computed live; see zad-home-defaults.php).
	if ( function_exists( 'zad_home_default' ) ) {
		$hd = zad_home_default( $key );
		if ( null !== $hd ) {
			return $hd;
		}
	}
	// Company data never saved in the options falls back to the validated values (footer, contact, about, schema).
	if ( '' === $default && function_exists( 'zsc_opt_defaults' ) ) {
		$d = zsc_opt_defaults();
		if ( isset( $d[ $key ] ) ) {
			return $d[ $key ];
		}
	}
	return $default;
}

/** Digits only, converts 05xxxxxxxx to 9665xxxxxxxx for wa.me / tel links. */
function zad_intl_number( $num ) {
	$n = preg_replace( '/\D+/', '', (string) $num );
	if ( '' === $n ) {
		return '';
	}
	if ( 0 === strpos( $n, '00' ) ) {
		$n = substr( $n, 2 );
	}
	if ( 0 === strpos( $n, '0' ) ) {
		$n = '966' . substr( $n, 1 );
	} elseif ( preg_match( '/^5\d{8}$/', $n ) ) {
		$n = '966' . $n;
	}
	return $n;
}

function zad_phone( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$v       = $post_id ? get_post_meta( $post_id, '_zad_phone', true ) : '';
	return $v ? trim( $v ) : trim( (string) zad_opt( 'memopt_phone' ) );
}

function zad_whatsapp( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$v       = $post_id ? get_post_meta( $post_id, '_zad_whatsapp', true ) : '';
	$v       = $v ? $v : zad_opt( 'memopt_whatsapp' );
	return zad_intl_number( $v );
}

function zad_tel_href( $phone ) {
	$p = preg_replace( '/[^\d+]/', '', (string) $phone );
	return 'tel:' . $p;
}

function zad_wa_link( $text = '', $post_id = 0 ) {
	$num = zad_whatsapp( $post_id );
	if ( ! $num ) {
		return '';
	}
	return 'https://wa.me/' . $num . ( $text ? '?text=' . rawurlencode( $text ) : '' );
}

/** Inline SVG icon set (stroke icons, 24px grid). */
function zad_icons() {
	return array(
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'whatsapp' => '<path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.5L3 21z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a3.5 3.5 0 0 1-1.8-1.8l.8-1-1-2L9 9.5z"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'      => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'check'    => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'shield'   => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5L15.5 10"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'star'     => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3z"/>',
		'bug'      => '<ellipse cx="12" cy="14" rx="5" ry="6"/><path d="M9 6l1.5 2M15 6l-1.5 2M7 12H3M17 12h4M7 16l-3 2M17 16l3 2M12 8v12"/>',
		'drop'     => '<path d="M12 3s6 6.2 6 10.5a6 6 0 0 1-12 0C6 9.2 12 3 12 3z"/>',
		'sparkle'  => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16z"/>',
		'home'     => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
		'tool'     => '<path d="M14.5 6.5a4 4 0 0 0-5 5L4 17l3 3 5.5-5.5a4 4 0 0 0 5-5L15 12l-3-3 2.5-2.5z"/>',
		'truck'    => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'snow'     => '<path d="M12 2v20M4 6.5l16 11M20 6.5l-16 11"/>',
		'paint'    => '<rect x="4" y="3" width="14" height="6" rx="1"/><path d="M18 6h2v5h-8v3"/><rect x="10" y="14" width="4" height="7" rx="1"/>',
		'bolt'     => '<path d="M13 2L5 14h6l-1 8 8-12h-6l1-8z"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
		'arrow'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'chevron'  => '<path d="M6 9l6 6 6-6"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
		'up'       => '<path d="M6 15l6-6 6 6"/>',
		'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'badge'    => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
		'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
		'x'        => '<path d="M4 4l16 16M20 4L4 20"/>',
		'instagram'=> '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6"/>',
		'linkedin' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><circle cx="8" cy="8.3" r="1"/><path d="M8 11.5V17M12 17v-3.8a2.2 2.2 0 0 1 4.4 0V17M12 11.5v1.2"/>',
		'pinterest'=> '<circle cx="12" cy="12" r="9"/><path d="M9.5 20c1-3.3 2.2-7.6 2.7-10.2A2.8 2.8 0 1 1 15 12.8c0 1.8-1 3.5-2.8 3.5"/>',
		'tiktok'   => '<path d="M15 3v10.5a3.5 3.5 0 1 1-3.5-3.5M15 3c.3 2.4 1.9 3.9 4.5 4.1"/>',
		'snapchat' => '<path d="M12 3c3 0 4.5 2.2 4.5 4.5v2.2l2.5 1.3-2.5 1.2c.4 1.4 1.5 2.3 2.5 2.8-1 .6-2 .7-3 .8-.4.8-1.1 1.7-4 1.7s-3.6-.9-4-1.7c-1-.1-2-.2-3-.8 1-.5 2.1-1.4 2.5-2.8L5 11l2.5-1.3V7.5C7.5 5.2 9 3 12 3Z"/>',
		'map'      => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/>',
		'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
		'youtube'  => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="M10 9l5 3-5 3V9z"/>',
	);
}

function zad_icon_keys() {
	return array_keys( zad_icons() );
}

/**
 * Icons are printed as <use href="#zi-name"> and the used symbols are added ONCE before </body> by an output-buffer callback
 * (so icons inside cached fragments, e.g. the mega menu, are covered too). Inline SVG everywhere else (admin, ajax, feeds, REST).
 */
function zad_sprite_on() { return ! empty( $GLOBALS['zad_sprite_buf'] ); }

add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_robots() || is_trackback() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! zad_opt( 'zad_icon_sprite', true ) ) { return; }
	$GLOBALS['zad_sprite_buf'] = true;
	ob_start( 'zad_sprite_buffer' );
}, 0 );

function zad_sprite_buffer( $html ) {
	if ( false === strpos( $html, 'href="#zi-' ) ) { return $html; }
	$icons = zad_icons();
	$pos   = strripos( $html, '</body>' );
	if ( false === $pos ) { // not a full page: put the icons back inline
		return preg_replace_callback( '#<use href="\#zi-([a-z0-9_-]+)"/>#i', function ( $m ) use ( $icons ) { return $icons[ $m[1] ] ?? ''; }, $html );
	}
	preg_match_all( '/href="#zi-([a-z0-9_-]+)"/i', $html, $m );
	$sprite = '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">';
	foreach ( array_unique( $m[1] ) as $n ) { if ( isset( $icons[ $n ] ) ) { $sprite .= '<symbol id="zi-' . $n . '" viewBox="0 0 24 24">' . $icons[ $n ] . '</symbol>'; } }
	return substr_replace( $html, $sprite . '</svg>' . "\n", $pos, 0 );
}

function zad_icon( $name, $size = 24, $class = '' ) {
	$icons = zad_icons();
	if ( ! isset( $icons[ $name ] ) ) {
		$name = 'check';
	}
	$inner = zad_sprite_on() ? '<use href="#zi-' . $name . '"/>' : $icons[ $name ]; // static, trusted markup.
	return sprintf(
		'<svg class="zi %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $class ),
		(int) $size,
		(int) $size,
		$inner
	);
}

function zad_lines( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $l ) {
		$l = trim( $l );
		if ( '' !== $l ) {
			$out[] = $l;
		}
	}
	return $out;
}

/** Print JSON-LD safely. */
function zad_print_schema( $data ) {
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

function zad_business_schema() {
	$logo = zad_opt( 'memopt_logo' );
	$logo = is_array( $logo ) && ! empty( $logo['url'] ) ? $logo['url'] : '';
	$same = array_values( array_filter( array( zad_opt( 'memopt_fb' ), zad_opt( 'memopt_tw' ), zad_opt( 'memopt_insta' ) ) ) );
	$s    = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'LocalBusiness',
		'@id'         => home_url( '/#business' ),
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url( '/' ),
		'telephone'   => zad_opt( 'memopt_phone' ),
		'email'       => zad_opt( 'memopt_mail' ),
		'address'     => array(
			'@type'          => 'PostalAddress',
			'streetAddress'  => zad_opt( 'memopt_address' ),
			'addressCountry' => 'SA',
		),
		'areaServed'  => 'SA',
		'priceRange'  => '$$',
	);
	if ( $logo ) {
		$s['logo']  = $logo;
		$s['image'] = $logo;
	}
	if ( $same ) {
		$s['sameAs'] = $same;
	}
	return $s;
}

/** Breadcrumb trail as [ [label, url], ... ] for service pages. */
function zad_service_crumbs( $post_id ) {
	$pt    = get_post_type( $post_id );
	$crumb = array( array( 'الرئيسية', home_url( '/' ) ) );
	if ( 'zad_service' === $pt ) {
		$crumb[] = array( 'الخدمات', zad_services_url() );
		$terms   = get_the_terms( $post_id, 'service_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$crumb[] = array( $terms[0]->name, get_term_link( $terms[0] ) );
		}
	} else {
		// Adopted type: Home > Type (archive) > Service.
		$o = get_post_type_object( $pt );
		$l = get_post_type_archive_link( $pt );
		if ( $o ) {
			$crumb[] = array( $o->labels->name, $l ? $l : '' );
		}
	}
	$crumb[] = array( get_the_title( $post_id ), '' );
	return $crumb;
}

function zad_render_crumbs( $crumbs ) {
	echo '<nav class="crumbs" aria-label="مسار التصفح"><ol>';
	foreach ( $crumbs as $i => $c ) {
		echo '<li>';
		if ( $c[1] ) {
			echo '<a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>';
		} else {
			echo '<span aria-current="page">' . esc_html( $c[0] ) . '</span>';
		}
		echo '</li>';
	}
	echo '</ol></nav>';
}

function zad_crumbs_schema( $crumbs ) {
	$items = array();
	foreach ( $crumbs as $i => $c ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $c[0],
			'item'     => $c[1] ? $c[1] : get_permalink(),
		);
	}
	return array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

/** Arabic-Indic digits → ASCII (array form: strtr() with two multibyte strings corrupts UTF-8). */
function zad_digits_en( $s ) {
	return strtr( (string) $s, array( '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٬' => ',', '٫' => '.' ) );
}

/** YouTube video id from a watch / youtu.be / embed / shorts URL, or ''. */
function zad_youtube_id( $url ) {
	return preg_match( '#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^\s]*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})#i', (string) $url, $m ) ? $m[1] : '';
}

/** data-wa attributes for a card button (no href: one central click handler in zad.js opens wa.me). '' when there is no number. */
function zad_wa_attrs( $text, $post_id = 0 ) {
	$n = zad_whatsapp( $post_id );
	return $n ? ' data-wa="' . esc_attr( $n ) . '" data-wa-text="' . esc_attr( $text ) . '"' : '';
}

/** Remove Saudi mobile numbers (05xxxxxxxx / 9665xxxxxxxx / +966 5…) from a display string. */
function zad_strip_phones( $s ) {
	$t = zad_digits_en( (string) $s );
	$t = preg_replace( '/(?:\+?\s*00?966|\+?\s*966|\b0)\s*-?\s*5(?:[\s\-]*\d){8}(?!\d)/u', ' ', $t );
	$t = preg_replace( '/\s+/u', ' ', $t );
	return preg_replace( '/^[\s\-–—|،,:·]+|[\s\-–—|،,:·]+$/u', '', $t ); // not trim(): its byte list would corrupt Arabic
}

/** Title to show for ANOTHER page in a card / link: Yoast breadcrumb title if set, else the page title; phone numbers removed. */
function zad_card_title( $id = 0 ) {
	$id = $id ? $id : get_the_ID();
	$bc = trim( (string) get_post_meta( $id, '_yoast_wpseo_bctitle', true ) );
	$t  = zad_strip_phones( '' !== $bc ? wp_strip_all_tags( $bc ) : wp_strip_all_tags( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ) );
	if ( '' === $t ) {
		$o = get_post_type_object( get_post_type( $id ) );
		$t = $o ? $o->labels->singular_name : 'صفحة';
	}
	return $t;
}

/** Parse the price list textarea: "group | service | price | warranty". */
function zad_parse_prices( $text ) {
	$rows = array();
	foreach ( zad_lines( $text ) as $line ) {
		$c = array_map( 'trim', explode( '|', $line ) );
		if ( count( $c ) < 2 ) {
			continue;
		}
		// 2 cols: name|price ; 3: group|name|price ; 4: group|name|price|details ; 5: + warranty.
		if ( count( $c ) === 2 ) {
			$c = array( '', $c[0], $c[1], '', '' );
		} elseif ( count( $c ) === 3 ) {
			$c = array( $c[0], $c[1], $c[2], '', '' );
		} elseif ( count( $c ) === 4 ) {
			$c[] = '';
		}
		$num = 0;
		if ( preg_match( '/\d[\d,٬]*/u', zad_digits_en( $c[2] ), $m ) ) {
			$num = (int) str_replace( array( ',', '٬' ), '', $m[0] );
		}
		$rows[] = array( 'group' => $c[0], 'name' => $c[1], 'price' => $c[2], 'details' => $c[3], 'warranty' => $c[4], 'num' => $num );
	}
	return $rows;
}


/** Packages textarea: "name | price | feature; feature; feature". */
function zad_parse_packages( $text ) {
	$out = array();
	foreach ( zad_lines( $text ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		if ( '' === $c[0] ) { continue; }
		$price = $c[1] ?? '';
		$num   = 0;
		if ( preg_match( '/\d[\d,٬]*/u', zad_digits_en( $price ), $m ) ) { $num = (int) str_replace( array( ',', '٬' ), '', $m[0] ); }
		$out[] = array( 'name' => $c[0], 'price' => $price, 'num' => $num, 'feat' => isset( $c[2] ) ? array_filter( array_map( 'trim', preg_split( '/[;؛]/u', $c[2] ) ) ) : array() );
	}
	return $out;
}

/**
 * Which price block a service page shows: 'table' | 'packages' | '' (none). Never both.
 * _zad_price_mode: '' auto (table if filled, else packages) · 'table' · 'packages' (each falls back to the other when empty).
 */
function zad_price_view( $id ) {
	$has_t = (bool) zad_parse_prices( get_post_meta( $id, '_zad_prices', true ) );
	$has_p = (bool) zad_parse_packages( get_post_meta( $id, '_zad_packages', true ) );
	$mode  = (string) get_post_meta( $id, '_zad_price_mode', true );
	if ( 'packages' === $mode ) { return $has_p ? 'packages' : ( $has_t ? 'table' : '' ); }
	return $has_t ? 'table' : ( $has_p ? 'packages' : '' );
}

/** Price rows of the VISIBLE block (hero estimator, schema, cards): same shape as zad_parse_prices(). */
function zad_price_rows( $id ) {
	$view = zad_price_view( $id );
	if ( 'table' === $view ) { return zad_parse_prices( get_post_meta( $id, '_zad_prices', true ) ); }
	if ( 'packages' !== $view ) { return array(); }
	$rows = array();
	foreach ( zad_parse_packages( get_post_meta( $id, '_zad_packages', true ) ) as $pk ) {
		$rows[] = array( 'group' => '', 'name' => $pk['name'], 'price' => $pk['price'], 'details' => implode( '؛ ', $pk['feat'] ), 'warranty' => '', 'num' => $pk['num'] );
	}
	return $rows;
}

/** Lowest numeric price across rows (0 if none). */
function zad_min_price( $rows ) {
	$min = 0;
	foreach ( $rows as $r ) {
		if ( $r['num'] > 0 && ( ! $min || $r['num'] < $min ) ) {
			$min = $r['num'];
		}
	}
	return $min;
}

/** Pagination for a given query (falls back to the main one). */
function zad_pagination( $q = null ) {
	$q   = $q ? $q : $GLOBALS['wp_query'];
	$max = (int) $q->max_num_pages;
	if ( $max < 2 ) {
		return '';
	}
	$cur = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	return '<div class="pagination">' . paginate_links( array( 'total' => $max, 'current' => $cur, 'mid_size' => 1, 'end_size' => 2, 'prev_text' => '›', 'next_text' => '‹' ) ) . '</div>';
}


/** Hub settings row for a post type (matched by rewrite slug or key). */
function zad_hub_opt( $pt ) {
	$base = str_replace( '_', '-', strtolower( zad_type_base( $pt ) ) );
	$key  = str_replace( '_', '-', strtolower( $pt ) );
	foreach ( (array) zad_opt( 'zad_hubs', array() ) as $h ) {
		$sl = trim( str_replace( '_', '-', strtolower( (string) ( $h['slug'] ?? '' ) ) ), '/ ' );
		if ( $sl && ( $sl === $base || $sl === $key ) ) {
			return $h;
		}
	}
	return array();
}

function zad_reading_time( $post = null ) {
	$w = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post ) ), 0, 'ءآأؤإئابةتثجحخدذرزسشصضطظعغفقكلمنهوىيًٌٍَُِّْ' );
	return max( 1, (int) ceil( $w / 180 ) );
}


/** Working hours text shown in header/footer/contact/about. */
function zad_hours_text() {
	$def = 'من 8 صباحاً إلى 10 مساءً طوال أيام الأسبوع';
	$v   = trim( (string) zad_opt( 'zad_hours', $def ) );
	// An older saved default ("نخدمكم 24 ساعة…") must not keep overriding the real hours.
	if ( '' === $v || false !== mb_strpos( $v, 'نخدمكم 24', 0, 'UTF-8' ) ) {
		return $def;
	}
	return $v;
}


/**
 * SEO mode: 'theme' (theme prints schema + meta) | 'yoast' (theme prints schema only; Yoast prints meta + sitemap)
 *           | 'other' (a mu-plugin owns schema) | 'off'.
 * 'auto': mu-plugin zad-schema.php present → other; Yoast active → yoast; otherwise theme.
 */
function zad_seo_mode() {
	$mode = zad_opt( 'zad_schema_mode', 'auto' );
	if ( in_array( $mode, array( 'off', 'theme', 'yoast' ), true ) ) {
		return $mode;
	}
	$mu = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
	if ( file_exists( $mu . '/zad-schema.php' ) ) {
		return 'other';
	}
	return defined( 'WPSEO_VERSION' ) ? 'yoast' : 'theme';
}

/** Who outputs schema: 'theme' (also in yoast mode: the theme owns the schema) or 'other' or 'off'. */
function zad_schema_owner() {
	$m = zad_seo_mode();
	return ( 'theme' === $m || 'yoast' === $m ) ? 'theme' : $m;
}

/** Current post-type query var as a single string (WordPress returns an array for multi-type archives). */
function zad_query_pt() {
	$pt = get_query_var( 'post_type' );
	if ( is_array( $pt ) ) {
		$pt = reset( $pt );
	}
	return is_string( $pt ) ? $pt : '';
}

/** Floating info cards around the service hero card: "title | sub | icon" lines (option zad_float_cards). */
function zad_float_cards( $post_id = 0 ) {
	$city  = ( $post_id && function_exists( 'zad_current_city' ) ) ? zad_current_city( $post_id )['name'] : zad_opt( 'zad_city_name', 'الرياض' );
	$since = (int) zad_opt( 'zad_since', 0 );
	$years = ( $since > 1980 && $since <= (int) gmdate( 'Y' ) ) ? (int) gmdate( 'Y' ) - $since : 0;
	$def   = "معاينة مجانية | قبل أي عمل | bolt\nتغطية | أحياء {city} | pin\n{years}+ سنة | خبرة موثوقة | star";
	$out   = array();
	foreach ( zad_lines( zad_opt( 'zad_float_cards', $def ) ) as $l ) {
		if ( false !== strpos( $l, '{years}' ) && ! $years ) { continue; }
		$l = str_replace( array( '{city}', '{years}' ), array( $city, (string) $years ), $l );
		$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 3, '' );
		if ( '' === $c[0] ) { continue; }
		$out[] = array( $c[0], $c[1], in_array( $c[2], zad_icon_keys(), true ) ? $c[2] : 'check' );
	}
	return array_slice( $out, 0, 3 );
}

/** Owner-filled link lines "الاسم | الرابط | وصف قصير" (link optional). */
function zad_link_lines( $opt ) {
	$out = array();
	foreach ( zad_lines( zad_opt( $opt, '' ) ) as $l ) {
		$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 3, '' );
		if ( '' === $c[0] ) { continue; }
		$u = $c[1];
		if ( '' !== $u && '/' === $u[0] && 0 !== strpos( $u, '//' ) ) { $u = home_url( $u ); }
		if ( '' !== $u && ! preg_match( '#^https?://#', $u ) ) { $u = ''; }
		$out[] = array( 'name' => $c[0], 'url' => $u, 'note' => $c[2] );
	}
	return $out;
}
