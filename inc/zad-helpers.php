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
	return ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] && array() !== $opts[ $key ] ) ? $opts[ $key ] : $default;
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
		'youtube'  => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="M10 9l5 3-5 3V9z"/>',
	);
}

function zad_icon_keys() {
	return array_keys( zad_icons() );
}

function zad_icon( $name, $size = 24, $class = '' ) {
	$icons = zad_icons();
	if ( ! isset( $icons[ $name ] ) ) {
		$name = 'check';
	}
	return sprintf(
		'<svg class="zi %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $class ),
		(int) $size,
		(int) $size,
		$icons[ $name ] // static, trusted markup.
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
	$crumbs = array( array( 'الرئيسية', home_url( '/' ) ), array( 'الخدمات', get_post_type_archive_link( 'zad_service' ) ) );
	$terms  = get_the_terms( $post_id, 'service_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$crumbs[] = array( $terms[0]->name, get_term_link( $terms[0] ) );
	}
	$crumbs[] = array( get_the_title( $post_id ), '' );
	return $crumbs;
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

/** Parse the price list textarea: "group | service | price | warranty". */
function zad_parse_prices( $text ) {
	$rows = array();
	foreach ( zad_lines( $text ) as $line ) {
		$c = array_map( 'trim', explode( '|', $line ) );
		if ( count( $c ) < 2 ) {
			continue;
		}
		if ( count( $c ) === 2 ) {
			$row = array( '', $c[0], $c[1], '' );
		} elseif ( count( $c ) === 3 ) {
			$row = array( $c[0], $c[1], $c[2], '' );
		} else {
			$row = array( $c[0], $c[1], $c[2], $c[3] );
		}
		$num = 0;
		if ( preg_match( '/\d[\d,٬]*/u', strtr( $row[2], '٠١٢٣٤٥٦٧٨٩', '0123456789' ), $m ) ) {
			$num = (int) str_replace( array( ',', '٬' ), '', $m[0] );
		}
		$rows[] = array( 'group' => $row[0], 'name' => $row[1], 'price' => $row[2], 'warranty' => $row[3], 'num' => $num );
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
