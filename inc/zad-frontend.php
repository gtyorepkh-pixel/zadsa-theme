<?php defined( 'ABSPATH' ) || exit;
/** Front-end output: CSS variables, schema, body classes. */

add_action( 'wp_head', function () {
	$p = sanitize_hex_color( zad_opt( 'zad_color_primary', '#0b4f5c' ) ) ?: '#0b4f5c';
	$a = sanitize_hex_color( zad_opt( 'zad_color_accent', '#f59e0b' ) ) ?: '#f59e0b';
	$c = function ( $k, $d ) { return sanitize_hex_color( zad_opt( $k, $d ) ) ?: $d; };
	$h = '--hdr-bg:' . $c( 'zad_hdr_bg', '#0c687e' ) . ';--hdr-ink:' . $c( 'zad_hdr_ink', '#ffffff' ) . ';--hdr-cta:' . $c( 'zad_hdr_cta', '#f49400' ) . ';--topbar-bg:' . $c( 'zad_topbar_bg', '#074250' )
		. ';--ftr-bg:' . $c( 'zad_ftr_bg', '#0a3947' ) . ';--ftr-head:' . $c( 'zad_ftr_head', '#3fbfae' ) . ';--ftr-ink:' . $c( 'zad_ftr_ink', '#b4c2c6' );
	$cta = ltrim( $c( 'zad_hdr_cta', '#f49400' ), '#' );
	$cta = strlen( $cta ) === 3 ? preg_replace( '/(.)/', '$1$1', $cta ) : $cta;
	$lum = ( hexdec( substr( $cta, 0, 2 ) ) * 0.299 + hexdec( substr( $cta, 2, 2 ) ) * 0.587 + hexdec( substr( $cta, 4, 2 ) ) * 0.114 ) / 255;
	$h  .= ';--hdr-cta-ink:' . ( $lum > 0.55 ? '#1a1200' : '#ffffff' );
	echo '<style id="zad-vars">:root{--primary:' . $p . ';--accent:' . $a . ';' . $h . '}</style>' . "\n"; // phpcs:ignore
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

/** "All our services" sidebar grouped by category, current page highlighted. */
function zad_services_sidebar_data() {
	$data = get_transient( 'zad_sbar_data' );
	if ( is_array( $data ) ) {
		return $data;
	}
	$data = array();
	$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true ) );
	if ( $cats && ! is_wp_error( $cats ) ) {
		foreach ( $cats as $c ) {
			$items = array();
			foreach ( get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => 30, 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => $c->term_id ) ), 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $p ) {
				$items[] = array( (int) $p->ID, get_permalink( $p ), get_the_title( $p ) );
			}
			if ( $items ) { $data[] = array( 'term' => (int) $c->term_id, 'name' => $c->name, 'items' => $items ); }
		}
	}
	// Services that were never given a category (existing content) are grouped by their type, so the list is never empty.
	foreach ( zad_service_types() as $pt ) {
		$items = array();
		foreach ( get_posts( array( 'post_type' => $pt, 'numberposts' => 60, 'no_found_rows' => true, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'operator' => 'NOT EXISTS' ) ) ) ) as $p ) {
			$items[] = array( (int) $p->ID, get_permalink( $p ), get_the_title( $p ) );
		}
		$o = get_post_type_object( $pt );
		if ( $items && $o ) { $data[] = array( 'term' => 0, 'pt' => $pt, 'name' => $o->labels->name, 'items' => $items ); }
	}
	set_transient( 'zad_sbar_data', $data, 12 * HOUR_IN_SECONDS );
	return $data;
}

function zad_services_sidebar( $current_id = 0 ) {
	$wa = zad_wa_link( 'مرحباً، أرغب بمعاينة مجانية' . ( $current_id ? ' — ' . wp_strip_all_tags( get_the_title( $current_id ) ) : '' ), $current_id );
	echo '<nav class="sbar" aria-label="كل خدماتنا">';
	if ( $wa ) {
		echo '<a class="sbar__cta" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . zad_icon( 'whatsapp', 20 ) . ' اطلب معاينة مجانية</a>'; // phpcs:ignore
	} else {
		echo '<button type="button" class="sbar__cta" data-open-wizard>' . zad_icon( 'bolt', 20 ) . ' اطلب معاينة مجانية</button>'; // phpcs:ignore
	}
	foreach ( zad_services_sidebar_data() as $g ) {
		$open = ! empty( $g['term'] ) ? has_term( $g['term'], 'service_cat', $current_id ) : ( ! empty( $g['pt'] ) && get_post_type( $current_id ) === $g['pt'] );
		echo '<details class="sbar__grp"' . ( $open ? ' open' : '' ) . '><summary><span>' . esc_html( $g['name'] ) . '</span></summary><ul>'; // phpcs:ignore
		foreach ( $g['items'] as $it ) {
			echo '<li><a href="' . esc_url( $it[1] ) . '"' . ( (int) $it[0] === (int) $current_id ? ' class="is-on" aria-current="page"' : '' ) . '>' . esc_html( $it[2] ) . '</a></li>';
		}
		echo '</ul></details>';
	}
	echo '</nav>';
	$phone = zad_phone( $current_id );
	if ( $phone ) {
		echo '<a class="callbox" href="' . esc_url( zad_tel_href( $phone ) ) . '">' . zad_icon( 'phone', 26 ) . '<span><small>اتصل مباشرة</small><b dir="ltr">' . esc_html( $phone ) . '</b></span></a>'; // phpcs:ignore
	}
}


/** Mega menu: categories with their services. */
/** Drop cached menus/lists when content or terms change. */
function zad_flush_nav_cache() {
	delete_transient( 'zad_mega_html' );
	delete_transient( 'zad_sbar_data' );
	delete_transient( 'zad_wiz_map' );
}
add_action( 'save_post', 'zad_flush_nav_cache' );
add_action( 'deleted_post', 'zad_flush_nav_cache' );
add_action( 'created_term', 'zad_flush_nav_cache' );
add_action( 'edited_term', 'zad_flush_nav_cache' );
add_action( 'delete_term', 'zad_flush_nav_cache' );

function zad_mega_html() {
	$cached = get_transient( 'zad_mega_html' );
	if ( is_string( $cached ) ) {
		return $cached;
	}
	$html = zad_mega_html_build();
	set_transient( 'zad_mega_html', $html, 12 * HOUR_IN_SECONDS );
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
		$out .= '<div class="mega__col"><a class="mega__cat" href="' . esc_url( get_term_link( $c ) ) . '">' . zad_icon( $ic, 20 ) . esc_html( $c->name ) . '</a><ul>';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		$out .= '</ul></div>';
		wp_reset_postdata();
	}
	$out .= '</div><a class="mega__all" href="' . esc_url( zad_services_url() ) . '">كل الخدمات ' . zad_icon( 'arrow', 16 ) . '</a></div>';
	return $out;
}

/** Legal links: menu "legalmenu" or pages by common slugs. */
function zad_legal_links() {
	if ( has_nav_menu( 'legalmenu' ) ) {
		wp_nav_menu( array( 'theme_location' => 'legalmenu', 'container' => false, 'menu_class' => 'ftr__legal', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1 ) );
		return;
	}
	$map = array( 'سياسة الخصوصية' => array( 'privacy-policy', 'privacy' ), 'الشروط والأحكام' => array( 'terms', 'terms-and-conditions' ), 'سياسة الضمان' => array( 'warranty-policy', 'warranty' ) );
	$li  = '';
	foreach ( $map as $label => $slugs ) {
		foreach ( $slugs as $sl ) {
			$p = get_page_by_path( $sl );
			if ( $p && 'publish' === $p->post_status ) {
				$li .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $label ) . '</a></li>';
				break;
			}
		}
	}
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


/* Post → related service (CTA card on the article) */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_post_service', 'ربط المقال بخدمة', function ( $post ) {
		wp_nonce_field( 'zad_post_service', 'zad_ps_nonce' );
		$cur = (int) get_post_meta( $post->ID, '_zad_post_service', true );
		echo '<select name="zad_post_service" style="width:100%"><option value="">—</option>';
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $s ) {
			echo '<option value="' . (int) $s->ID . '"' . selected( $cur, $s->ID, false ) . '>' . esc_html( $s->post_title ) . '</option>';
		}
		echo '</select><p class="description">تظهر بطاقة الخدمة تحت المقال.</p>';
	}, 'post', 'side' );
} );
add_action( 'save_post_post', function ( $id ) {
	if ( ! isset( $_POST['zad_ps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_ps_nonce'] ) ), 'zad_post_service' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	update_post_meta( $id, '_zad_post_service', isset( $_POST['zad_post_service'] ) ? absint( $_POST['zad_post_service'] ) : '' );
} );

/* Table of contents for articles with 3+ headings */
add_filter( 'the_content', function ( $content ) {
	if ( zad_suite_has( 'toc' ) || ! ( is_singular( 'post' ) || zad_is_faq() || zad_is_article() ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$n   = 0;
	$toc = array();
	$content = preg_replace_callback( '#<h([23])([^>]*)>(.*?)</h\1>#is', function ( $m ) use ( &$n, &$toc ) {
		$n++;
		$id = 'h' . $n . '-' . substr( md5( wp_strip_all_tags( $m[3] ) ), 0, 5 );
		$toc[] = array( (int) $m[1], $id, wp_strip_all_tags( $m[3] ) );
		return '<h' . $m[1] . $m[2] . ' id="' . $id . '">' . $m[3] . '</h' . $m[1] . '>';
	}, $content );
	if ( count( $toc ) < 3 ) {
		return $content;
	}
	$html = '<nav class="toc" aria-label="عناوين المقال"><details><summary><span>عناوين المقال</span><span class="toc__btn" aria-hidden="true">عرض العناوين</span></summary><ol>';
	foreach ( $toc as $t ) {
		$html .= '<li class="toc--' . (int) $t[0] . '"><a href="#' . esc_attr( $t[1] ) . '">' . esc_html( $t[2] ) . '</a></li>';
	}
	$html .= '</ol></details></nav>';
	// Place the collapsed TOC right after the first H2 (not before it).
	$done = false;
	$out  = preg_replace_callback( '#</h2>#i', function ( $m ) use ( &$done, $html ) {
		if ( $done ) { return $m[0]; }
		$done = true;
		return $m[0] . $html;
	}, $content, 1 );
	return $done ? $out : $html . $content;
}, 9 );


/** Hero photo of a service page is a CSS background (invisible to the preload scanner): preload it. */
add_action( 'wp_head', function () {
	if ( ! is_singular() || ! function_exists( 'zad_is_service' ) || ! zad_is_service() ) {
		return;
	}
	$tid = get_post_thumbnail_id( get_queried_object_id() );
	$url = $tid ? wp_get_attachment_image_url( $tid, 'full' ) : '';
	if ( $url ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high">' . "\n"; // phpcs:ignore
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
