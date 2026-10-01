<?php defined( 'ABSPATH' ) || exit;
/** Front-end output: CSS variables, schema, body classes. */

add_action( 'wp_head', function () {
	$p = sanitize_hex_color( zad_opt( 'zad_color_primary', '#0b4f5c' ) ) ?: '#0b4f5c';
	$a = sanitize_hex_color( zad_opt( 'zad_color_accent', '#f59e0b' ) ) ?: '#f59e0b';
	echo '<style id="zad-vars">:root{--primary:' . $p . ';--accent:' . $a . ';}</style>' . "\n"; // phpcs:ignore
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
function zad_services_sidebar( $current_id = 0 ) {
	$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true ) );
	echo '<nav class="sbar" aria-label="كل خدماتنا"><h3>كل خدماتنا</h3>';
	if ( $cats && ! is_wp_error( $cats ) ) {
		foreach ( $cats as $c ) {
			$q = new WP_Query( array( 'post_type' => 'zad_service', 'posts_per_page' => 30, 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => $c->term_id ) ), 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
			if ( ! $q->have_posts() ) {
				continue;
			}
			echo '<details class="sbar__grp"' . ( has_term( $c->term_id, 'service_cat', $current_id ) ? ' open' : '' ) . '><summary>' . esc_html( $c->name ) . zad_icon( 'chevron', 16 ) . '</summary><ul>'; // phpcs:ignore
			while ( $q->have_posts() ) {
				$q->the_post();
				echo '<li><a href="' . esc_url( get_permalink() ) . '"' . ( get_the_ID() === (int) $current_id ? ' class="is-on" aria-current="page"' : '' ) . '>' . esc_html( get_the_title() ) . '</a></li>';
			}
			echo '</ul></details>';
			wp_reset_postdata();
		}
	}
	echo '</nav>';
	$phone = zad_phone( $current_id );
	if ( $phone ) {
		echo '<a class="callbox" href="' . esc_url( zad_tel_href( $phone ) ) . '">' . zad_icon( 'phone', 26 ) . '<span><small>اتصل مباشرة</small><b dir="ltr">' . esc_html( $phone ) . '</b></span></a>'; // phpcs:ignore
	}
}


/** Mega menu: categories with their services. */
function zad_mega_html() {
	$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'parent' => 0 ) );
	if ( ! $cats || is_wp_error( $cats ) ) {
		return '';
	}
	$out = '<div class="mega"><div class="mega__grid">';
	foreach ( $cats as $c ) {
		$q = new WP_Query( array( 'post_type' => 'zad_service', 'posts_per_page' => 8, 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => $c->term_id ) ), 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
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
	$out .= '</div><a class="mega__all" href="' . esc_url( get_post_type_archive_link( 'zad_service' ) ) . '">كل الخدمات ' . zad_icon( 'arrow', 16 ) . '</a></div>';
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
