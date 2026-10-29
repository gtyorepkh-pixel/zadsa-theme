<?php defined( 'ABSPATH' ) || exit;
/** Front-end output: CSS variables, schema, body classes. */

add_action( 'wp_head', function () {
	$p = sanitize_hex_color( zad_opt( 'zad_color_primary', '#0c687e' ) ) ?: '#0c687e';
	$a = sanitize_hex_color( zad_opt( 'zad_color_accent', '#f49400' ) ) ?: '#f49400';
	$c = function ( $k, $d ) { return sanitize_hex_color( zad_opt( $k, $d ) ) ?: $d; };
	$h = '--hdr-bg:' . $c( 'zad_hdr_bg', '#0c687e' ) . ';--hdr-ink:' . $c( 'zad_hdr_ink', '#ffffff' ) . ';--hdr-cta:' . $c( 'zad_hdr_cta', '#f49400' ) . ';--topbar-bg:' . $c( 'zad_topbar_bg', '#074250' )
		. ';--ftr-bg:' . $c( 'zad_ftr_bg', '#0a3947' ) . ';--ftr-head:' . $c( 'zad_ftr_head', '#3fbfae' ) . ';--ftr-ink:' . $c( 'zad_ftr_ink', '#b4c2c6' );
	$cta = ltrim( $c( 'zad_hdr_cta', '#f49400' ), '#' );
	$cta = strlen( $cta ) === 3 ? preg_replace( '/(.)/', '$1$1', $cta ) : $cta;
	$lum = ( hexdec( substr( $cta, 0, 2 ) ) * 0.299 + hexdec( substr( $cta, 2, 2 ) ) * 0.587 + hexdec( substr( $cta, 4, 2 ) ) * 0.114 ) / 255;
	$h  .= ';--hdr-cta-ink:' . ( $lum > 0.55 ? '#1a1200' : '#ffffff' );
	// small / secondary text (captions, dates, notes): deep black by default, the owner can recolour it in the options; text the editor coloured by hand keeps its own colour (inline styles win); dark mode keeps its own light value
	$sm = $c( 'zad_color_small', '#111111' );
	echo '<style id="zad-vars">:root{--primary:' . $p . ';--accent:' . $a . ';' . $h . '}:root:not([data-theme=dark]){--muted:' . $sm . '}</style>' . "\n"; // phpcs:ignore
	echo '<meta name="theme-color" content="' . esc_attr( $p ) . '">' . "\n";
}, 5 );


/** Render an FAQ accordion from [ ['q'=>, 'a'=>], ... ]. */
function zad_render_faq( $items ) {
	$items = array_values( array_filter( (array) $items, function ( $f ) { return ! empty( $f['q'] ); } ) );
	if ( ! $items ) {
		return;
	}
	echo '<div class="faq">';
	foreach ( $items as $f ) {
		echo '<details class="faq__item"><summary>' . esc_html( $f['q'] ) . zad_icon( 'chevron', 20 ) . '</summary><div class="faq__a">' . wp_kses_post( wpautop( $f['a'] ?? '' ) ) . '</div></details>'; // phpcs:ignore
	}
	echo '</div>';
}

function zad_stars( $rating ) {
	$r = max( 0, min( 5, (float) $rating ) );
	return '<span class="stars" role="img" aria-label="تقييم ' . esc_attr( $r ) . ' من 5"><span class="stars__fill" style="width:' . esc_attr( $r * 20 ) . '%"></span></span>';
}

/** Posts that are not services keep their classic look (menu, WA bar etc. come from header/footer). */
add_filter( 'body_class', function ( $c ) {
	$c[] = 'zad';
	return $c;
} );

add_filter( 'excerpt_length', function () { return 24; } );
add_filter( 'excerpt_more', function () { return '…'; } );


/** Mega menu: categories with their services. */
/** Drop cached menus/lists when content or terms change. */
function zad_flush_nav_cache() {
	delete_transient( 'zad_mega_html2' );
	delete_transient( 'zad_wiz_map' );
	delete_transient( 'zad_wiz_map2' );
	delete_transient( 'zad_wiz_map3' );
}
add_action( 'save_post', 'zad_flush_nav_cache' );
add_action( 'deleted_post', 'zad_flush_nav_cache' );
add_action( 'created_term', 'zad_flush_nav_cache' );
add_action( 'edited_term', 'zad_flush_nav_cache' );
add_action( 'delete_term', 'zad_flush_nav_cache' );

function zad_mega_html() {
	$cached = get_transient( 'zad_mega_html2' );
	if ( is_string( $cached ) ) {
		return $cached;
	}
	$html = zad_mega_html_build();
	set_transient( 'zad_mega_html2', $html, 12 * HOUR_IN_SECONDS );
	return $html;
}

function zad_mega_html_build() {
	$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'parent' => 0 ) );
	if ( ! $cats || is_wp_error( $cats ) ) {
		return '';
	}
	$out = '<div class="mega"><div class="mega__grid">';
	foreach ( $cats as $c ) {
		$q = new WP_Query( array( 'post_type' => zad_service_types(), 'posts_per_page' => 8, 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => $c->term_id ) ), 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
		if ( ! $q->have_posts() ) { continue; }
		$ic   = get_term_meta( $c->term_id, 'zad_icon', true ) ?: 'sparkle';
		$out .= '<div class="mega__col"><a class="mega__cat" href="' . esc_url( get_term_link( $c ) ) . '">' . zad_icon( $ic, 20 ) . esc_html( $c->name ) . '</a><div class="mega__list">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( zad_card_title( get_the_ID() ) ) . '</a>';
		}
		$out .= '</div></div>';
		wp_reset_postdata();
	}
	$out .= '</div><a class="mega__all" href="' . esc_url( zad_services_url() ) . '">كل الخدمات ' . zad_icon( 'arrow', 16 ) . '</a></div>';
	return $out;
}

/** Legal links: menu "legalmenu" or pages by common slugs. */
/** Default legal links (privacy / terms / …) that exist and are published: [ [label, url], … ]. Empty when a legal menu is assigned. */
function zad_legal_items() {
	if ( has_nav_menu( 'legalmenu' ) ) { return array(); }
	$map = array( 'سياسة الخصوصية' => array( 'privacy-policy', 'privacy' ), 'الشروط والأحكام' => array( 'terms', 'terms-and-conditions' ), 'سياسة الضمان' => array( 'warranty-policy', 'warranty' ) );
	$out = array();
	foreach ( $map as $label => $slugs ) {
		foreach ( $slugs as $sl ) {
			$p = get_page_by_path( $sl );
			if ( $p && 'publish' === $p->post_status ) { $out[] = array( $label, get_permalink( $p ) ); break; }
		}
	}
	return $out;
}

function zad_legal_links() {
	if ( has_nav_menu( 'legalmenu' ) ) {
		wp_nav_menu( array( 'theme_location' => 'legalmenu', 'container' => false, 'menu_class' => 'ftr__legal', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1 ) );
		return;
	}
	$li = '';
	foreach ( zad_legal_items() as $i ) { $li .= '<li><a href="' . esc_url( $i[1] ) . '">' . esc_html( $i[0] ) . '</a></li>'; }
	if ( $li ) {
		echo '<ul class="ftr__legal">' . $li . '</ul>'; // phpcs:ignore
	}
}

/** Speculation rules: prefetch on hover, prerender moderately (no admin/forms/tel links). */
add_action( 'wp_head', function () {
	$rules = array(
		'prerender' => array( array( 'source' => 'document', 'where' => array( 'and' => array(
			array( 'href_matches' => '/*' ),
			array( 'not' => array( 'href_matches' => array( '/wp-*.php', '/wp-admin/*', '/wp-content/*', '/wp-json/*', '/*\\?(.+)' ) ) ),
			array( 'not' => array( 'selector_matches' => 'a[rel~="nofollow"], a[href^="tel:"], a[href*="wa.me"], .no-prefetch, .no-prefetch a' ) ),
		) ), 'eagerness' => 'moderate' ) ),
	);
	echo '<script type="speculationrules">' . wp_json_encode( $rules, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore
}, 30 );

/** Hero image: high priority, no lazy. */
add_filter( 'wp_get_attachment_image_attributes', function ( $a ) {
	if ( isset( $a['loading'] ) && 'eager' === $a['loading'] ) {
		$a['fetchpriority'] = 'high';
	}
	return $a;
} );


/* Article / guide → related service (box inside the content + button at the end) */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_post_service', 'ربط المقال بخدمة', function ( $post ) {
		wp_nonce_field( 'zad_post_service', 'zad_ps_nonce' );
		$cur = (int) get_post_meta( $post->ID, '_zad_post_service', true );
		echo '<select name="zad_post_service" style="width:100%"><option value="">—</option>';
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $s ) {
			echo '<option value="' . (int) $s->ID . '"' . selected( $cur, $s->ID, false ) . '>' . esc_html( $s->post_title ) . '</option>';
		}
		echo '</select><p class="description">يظهر صندوق ثابت داخل المحتوى وزر في آخر الصفحة.</p>';
	}, zad_article_types(), 'side' ); // articles, guides, sections, pests library…
} );
add_action( 'save_post', function ( $id ) {
	if ( ! in_array( get_post_type( $id ), zad_article_types(), true ) ) { return; }
	if ( ! isset( $_POST['zad_ps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_ps_nonce'] ) ), 'zad_post_service' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	update_post_meta( $id, '_zad_post_service', isset( $_POST['zad_post_service'] ) ? absint( $_POST['zad_post_service'] ) : '' );
} );


/** Hero photo of a service page: a real <img> (srcset, sizes, high priority, eager) behind the overlay. A 600w size (quality 70: it sits under a dark overlay) serves small screens. */
add_action( 'after_setup_theme', function () { add_image_size( 'zad-hero-600', 600, 0, false ); } );
/* Lighter generated images (as in rokn inc/media.php): WebP sizes at quality 72 instead of WordPress's 82. Applies to images uploaded or regenerated from now on. */
add_filter( 'wp_editor_set_quality', function ( $q, $mime = '' ) { return 'image/webp' === $mime ? 72 : $q; }, 10, 2 );
add_filter( 'image_make_intermediate_size', function ( $file ) {
	if ( is_string( $file ) && preg_match( '/-600x\d+\.(jpe?g|webp)$/i', $file ) && function_exists( 'wp_get_image_editor' ) ) {
		$ed = wp_get_image_editor( $file );
		if ( ! is_wp_error( $ed ) ) { $ed->set_quality( 70 ); $ed->save( $file ); }
	}
	return $file;
} );
function zad_hero_sizes() { return '100vw'; }

/** alt for an image shown on a page: the image's own alt, else its caption, else the page title. */
function zad_img_alt( $att_id, $page_id = 0 ) {
	$a = trim( (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ) );
	if ( '' === $a ) { $a = trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $att_id ) ) ); }
	if ( '' === $a ) { $a = trim( wp_strip_all_tags( html_entity_decode( get_the_title( $page_id ? $page_id : get_queried_object_id() ), ENT_QUOTES, 'UTF-8' ) ) ); }
	return $a;
}

/** Hero image: eager, high priority (never lazy), alt falls back to the page title. */
function zad_hero_img( $tid ) {
	return wp_get_attachment_image( $tid, 'full', false, array( 'class' => 'shero__bg', 'alt' => zad_img_alt( $tid ), 'sizes' => zad_hero_sizes(), 'loading' => 'eager', 'decoding' => 'async', 'fetchpriority' => 'high' ) );
}

/** URL → file path inside uploads ('' when the URL is not an upload). */
function zad_upload_path( $url ) {
	static $u = null;
	if ( null === $u ) { $u = wp_get_upload_dir(); }
	$base = set_url_scheme( $u['baseurl'] );
	$url  = set_url_scheme( strtok( (string) $url, '?' ) );
	return 0 === strpos( $url, $base ) ? $u['basedir'] . substr( $url, strlen( $base ) ) : '';
}

/**
 * Modern formats next to an upload: "photo.jpg.avif" or "photo.avif" (what WebP/AVIF converters write).
 * Returns array( 'avif' => srcset, 'webp' => srcset ) for the formats whose file exists for EVERY candidate of the img.
 */
function zad_modern_srcsets( $html ) {
	if ( ! preg_match( '/\ssrc="([^"]+)"/', $html, $s ) ) { return array(); }
	$cands = array();
	if ( preg_match( '/\ssrcset="([^"]+)"/', $html, $ss ) ) {
		foreach ( explode( ',', $ss[1] ) as $part ) { $p = preg_split( '/\s+/', trim( $part ) ); if ( ! empty( $p[0] ) ) { $cands[] = array( $p[0], $p[1] ?? '' ); } }
	}
	if ( ! $cands ) { $cands[] = array( $s[1], '' ); }
	$key = 'zad_pic_' . md5( wp_json_encode( $cands ) );
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) { return $hit; }
	$out = array();
	foreach ( array( 'avif', 'webp' ) as $fmt ) {
		$list = array();
		foreach ( $cands as $c ) {
			$path = zad_upload_path( $c[0] );
			if ( '' === $path ) { $list = array(); break; }
			$alt = array( array( $path . '.' . $fmt, $c[0] . '.' . $fmt ), array( preg_replace( '/\.[A-Za-z0-9]+$/', '.' . $fmt, $path ), preg_replace( '/\.[A-Za-z0-9]+(\?.*)?$/', '.' . $fmt, $c[0] ) ) );
			$found = '';
			foreach ( $alt as $a ) { if ( is_readable( $a[0] ) ) { $found = $a[1]; break; } }
			if ( '' === $found ) { $list = array(); break; }
			$list[] = trim( $found . ' ' . $c[1] );
		}
		if ( $list ) { $out[ $fmt ] = implode( ', ', $list ); }
	}
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

add_filter( 'wp_get_attachment_image', function ( $html ) {
	if ( is_admin() || is_feed() || false !== strpos( $html, '<picture' ) || 0 !== strpos( ltrim( $html ), '<img' ) ) { return $html; }
	$src = zad_modern_srcsets( $html );
	if ( ! $src ) { return $html; }
	$sizes = preg_match( '/\ssizes="([^"]+)"/', $html, $m ) ? ' sizes="' . esc_attr( $m[1] ) . '"' : '';
	$o = '<picture>';
	foreach ( array( 'avif' => 'image/avif', 'webp' => 'image/webp' ) as $fmt => $type ) {
		if ( isset( $src[ $fmt ] ) ) { $o .= '<source type="' . $type . '" srcset="' . esc_attr( $src[ $fmt ] ) . '"' . $sizes . '>'; }
	}
	return $o . $html . '</picture>';
}, 20 );
add_action( 'wp_head', function () {
	if ( ! is_singular() || ! function_exists( 'zad_is_service' ) || ! zad_is_service() ) {
		return;
	}
	$tid = get_post_thumbnail_id( get_queried_object_id() );
	$url = $tid ? wp_get_attachment_image_url( $tid, 'full' ) : '';
	if ( $url && false !== strpos( (string) wp_get_attachment_image( $tid, 'full', false, array( 'sizes' => zad_hero_sizes() ) ), '<picture' ) ) { $url = ''; } // AVIF/WebP in use: a JPG preload would download the wrong file
	if ( $url ) {
		$set = wp_get_attachment_image_srcset( $tid, 'full' );
		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '"' . ( $set ? ' imagesrcset="' . esc_attr( $set ) . '" imagesizes="' . esc_attr( zad_hero_sizes() ) . '"' : '' ) . ' fetchpriority="high">' . "\n"; // phpcs:ignore
	}
}, 2 );


/** Self-hosted Tajawal. Declared inline with absolute URLs so cache/minify/CDN plugins that move the stylesheet cannot break the font path. */
add_action( 'wp_head', function () {
	$u   = trailingslashit( get_template_directory_uri() ) . 'assets/fonts/';
	$css = '';
	foreach ( array( 400, 500, 700, 800 ) as $w ) {
		$css .= '@font-face{font-family:"Tajawal";font-style:normal;font-weight:' . $w . ';font-display:swap;src:url(' . esc_url( $u . 'tajawal-' . $w . '.woff2' ) . ') format("woff2")}';
	}
	echo '<style id="zad-fonts">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 1 );


/* ---- Comments on articles: closed by default (theme option zad_comments_on turns them on) ---- */
function zad_comments_enabled() { return (bool) zad_opt( 'zad_comments_on', false ); }
function zad_comment_types() { return array_unique( array_merge( array( 'post' ), function_exists( 'zad_article_types' ) ? zad_article_types() : array() ) ); }
foreach ( array( 'comments_open', 'pings_open' ) as $zad_h ) {
	add_filter( $zad_h, function ( $open, $post_id ) {
		return ( ! zad_comments_enabled() && in_array( get_post_type( $post_id ), zad_comment_types(), true ) ) ? false : $open;
	}, 20, 2 );
}


/** The "services" item of the main menu (the one that carries the mega menu). */
function zad_is_mega_item( $item ) {
	return 0 === (int) $item->menu_item_parent && ! empty( $item->url ) && function_exists( 'zad_mega_html' ) && ( untrailingslashit( $item->url ) === untrailingslashit( (string) zad_services_url() ) || in_array( 'zad-mega', (array) $item->classes, true ) );
}

/** The mega item's own sub-menu would be a second copy of the same links (hidden on desktop): drop it; JS builds the mobile list from the mega menu on first tap. */
add_filter( 'wp_nav_menu_objects', function ( $items, $args ) {
	if ( empty( $args->theme_location ) || 'mainmenu' !== $args->theme_location ) { return $items; }
	$drop = array();
	foreach ( $items as $it ) { if ( zad_is_mega_item( $it ) ) { $drop[ (int) $it->ID ] = true; } }
	if ( ! $drop ) { return $items; }
	do {
		$n = count( $drop );
		foreach ( $items as $it ) { if ( isset( $drop[ (int) $it->menu_item_parent ] ) ) { $drop[ (int) $it->ID ] = true; } }
	} while ( count( $drop ) > $n );
	$keep_roots = array();
	foreach ( $items as $it ) { if ( zad_is_mega_item( $it ) ) { $keep_roots[ (int) $it->ID ] = true; } }
	return array_values( array_filter( $items, function ( $it ) use ( $drop, $keep_roots ) { return ! isset( $drop[ (int) $it->ID ] ) || isset( $keep_roots[ (int) $it->ID ] ); } ) );
}, 10, 2 );
