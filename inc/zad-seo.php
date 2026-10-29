<?php defined( 'ABSPATH' ) || exit;
/**
 * Built-in SEO: per-page title/description/OG image/noindex, canonical, Open Graph, Twitter, geo (no hreflang).
 * Native theme ownership; saved historical metadata is readable without any SEO plugin.
 */

function zad_seo_active() {
	return 'theme' !== zad_seo_mode();
}

/* ---- meta box ---- */
add_action( 'add_meta_boxes', function () {
	foreach ( array_unique( array_merge( zad_service_types(), zad_faq_types(), zad_article_types(), array( 'page', 'post', 'zad_work' ) ) ) as $pt ) {
		if ( in_array( $pt, zad_service_types(), true ) ) { continue; } // Services use their SEO tab.
		add_meta_box( 'zad_seo', 'SEO — محركات البحث', 'zad_seo_metabox', $pt, 'normal', 'default' );
	}
} );

function zad_seo_metabox( $post ) {

	wp_nonce_field( 'zad_seo_save', 'zad_seo_nonce' );
	$g = function ( $k ) use ( $post ) { return zad_seo_meta( $post->ID, $k ); };
	?>
	<p><label>عنوان الصفحة (Title) <small>— المثالي 50–60 حرفاً</small>
		<input type="text" name="zad_seo[title]" value="<?php echo esc_attr( $g( 'title' ) ); ?>" style="width:100%" placeholder="<?php echo esc_attr( get_the_title( $post ) . ' | ' . get_bloginfo( 'name' ) ); ?>"></label></p>
	<p><label>الوصف (Meta description) <small>— المثالي 140–160 حرفاً</small>
		<textarea name="zad_seo[desc]" rows="3" style="width:100%"><?php echo esc_textarea( $g( 'desc' ) ); ?></textarea></label></p>
	<p><label>صورة المشاركة (OG) — رقم الصورة في المكتبة، أو اتركه لتُستخدم الصورة البارزة
		<input type="number" name="zad_seo[img]" value="<?php echo esc_attr( $g( 'img' ) ); ?>" style="width:140px"></label></p>
	<?php if ( zad_can_admin_fields() ) : ?>
	<p><label>الرابط الأساسي Canonical — اختياري
		<input type="url" name="zad_seo[canonical]" value="<?php echo esc_attr( zad_seo_meta( $post->ID, 'canonical' ) ); ?>" style="width:100%"></label></p>
	<p><label><input type="checkbox" name="zad_seo[noindex]" value="1" <?php checked( $g( 'noindex' ), '1' ); ?>> عدم الفهرسة (noindex)</label></p>
	<?php else : ?>
	<p class="description">الـ Canonical وعدم الفهرسة بيعدّلهم المدير بس.</p>
	<?php endif; ?>
	<?php
}

add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['zad_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_seo_nonce'] ) ), 'zad_seo_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$in = isset( $_POST['zad_seo'] ) ? wp_unslash( (array) $_POST['zad_seo'] ) : array();
	update_post_meta( $id, '_zad_seo_title', isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : '' );
	update_post_meta( $id, '_zad_seo_desc', isset( $in['desc'] ) ? sanitize_textarea_field( $in['desc'] ) : '' );
	update_post_meta( $id, '_zad_seo_img', isset( $in['img'] ) ? absint( $in['img'] ) : '' );
	if ( zad_can_admin_fields() ) { // editors never see these two fields, so their save must not clear them
		update_post_meta( $id, '_zad_seo_canonical', isset( $in['canonical'] ) ? esc_url_raw( $in['canonical'] ) : '' );
		update_post_meta( $id, '_zad_seo_noindex', ! empty( $in['noindex'] ) ? '1' : '' );
	}
} );

/* ---- computed values ---- */
/** Old SEO-plugin values are still read as a fallback until «نقل بيانات السيو القديمة» (Tools) has copied them into the theme fields. */
function zad_seo_legacy_on() {
	return ! get_option( 'zad_seo_legacy_done' );
}
function zad_seo_meta( $id, $k ) {
	if ( ! $id ) { return ''; }
	$value = get_post_meta( $id, '_zad_seo_' . $k, true );
	if ( zad_seo_legacy_on() && ! metadata_exists( 'post', $id, '_zad_seo_' . $k ) ) {
		$keys = array( 'title' => 'title', 'desc' => 'metadesc', 'canonical' => 'canonical', 'img' => 'opengraph-image-id', 'noindex' => 'meta-robots-noindex' );
		if ( isset( $keys[ $k ] ) ) { $value = get_post_meta( $id, '_yoast_wpseo_' . $keys[ $k ], true ); }
		if ( 'noindex' === $k ) { return '1' === (string) $value ? '1' : ''; }
	}
	return in_array( $k, array( 'title', 'desc' ), true ) ? zad_seo_expand_template( $value, $id ) : $value;
}

/** Template variables (%%title%%, %%sep%% …) in saved titles, resolved with WordPress data only. */
function zad_seo_expand_template( $value, $id = 0 ) {
	$value = (string) $value;
	if ( false === strpos( $value, '%%' ) ) { return $value; }
	$post = $id ? get_post( $id ) : null;
	$vars = array( 'title' => $post ? get_the_title( $post ) : '', 'sitename' => get_bloginfo( 'name' ),
		'sitedesc' => get_bloginfo( 'description' ), 'sep' => '|', 'currentyear' => current_time( 'Y' ),
		'excerpt' => $post ? wp_strip_all_tags( $post->post_excerpt ) : '',
		'excerpt_only' => $post ? wp_strip_all_tags( $post->post_excerpt ) : '',
		'page' => get_query_var( 'paged' ) > 1 ? 'صفحة ' . (int) get_query_var( 'paged' ) : '',
		'primary_category' => '', 'category' => '', 'tag' => '' );
	if ( $post ) {
		foreach ( array( 'category' => 'category', 'tag' => 'post_tag' ) as $key => $tax ) {
			$terms = get_the_terms( $id, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) { $vars[ $key ] = $terms[0]->name; }
		}
		$vars['primary_category'] = $vars['category'];
	}
	$value = preg_replace_callback( '/%%([^%]+)%%/', function ( $m ) use ( $vars ) { return $vars[ $m[1] ] ?? ''; }, $value );
	$value = preg_replace( '/\s+/u', ' ', $value );
	return preg_replace( '/^[\s|–—-]+|[\s|–—-]+$/u', '', $value );
}

/** One canonical for native meta and HTTP headers, including paginated archives. */
function zad_seo_canonical_url() {
	if ( is_search() || is_404() ) { return ''; }
	if ( is_singular() ) {
		$custom = zad_seo_meta( get_queried_object_id(), 'canonical' );
		return $custom ? esc_url_raw( $custom ) : ( wp_get_canonical_url( get_queried_object_id() ) ?: get_permalink() );
	}
	if ( is_tax() || is_category() || is_tag() ) { $custom = zad_seo_term_meta( get_queried_object(), 'canonical' ); if ( $custom ) { return esc_url_raw( $custom ); } }
	return zad_current_url();
}

function zad_seo_title() {
	$site = get_bloginfo( 'name' );
	if ( is_front_page() ) {
		$id = (int) get_option( 'page_on_front' );
		$custom = $id ? zad_seo_meta( $id, 'title' ) : '';
		if ( $custom ) { return $custom; }
		return zad_opt( 'zad_hero_title', $site ) . ' | ' . $site;
	}
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$t = zad_seo_meta( $id, 'title' );
		return $t ? $t : get_the_title( $id ) . ' | ' . $site;
	}
	if ( function_exists( 'zad_seo_archive_value' ) ) {
		return zad_seo_archive_value( 'title' );
	}
	return '';
}

/** Readable plain text for auto summaries, including retired shortcode tags. */
/** Blocks the theme injects into the rendered content (table of contents, answer box, CTA) are navigation, not prose. */
function zad_seo_strip_injected( $text ) {
	$text = (string) $text;
	foreach ( array( 'nav', 'aside', 'figure', 'div' ) as $tag ) {
		$text = preg_replace( '#<' . $tag . '\b[^>]*class="[^"]*\bzad-(?:toc|answer|answer-box|cta|share|crumbs)\b[^"]*"[^>]*>.*?</' . $tag . '>#isu', ' ', $text );
	}
	return $text;
}

function zad_seo_plain_text( $text ) {
 $text = zad_seo_strip_injected( $text );
	$text = strip_shortcodes( (string) $text );
 $text = preg_replace( '/\[(?:\/?)[A-Za-z_][A-Za-z0-9_-]*(?:\s[^\]]*)?\/?\]/u', ' ', $text );
 return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
}

function zad_seo_desc() {
	if ( function_exists( 'zad_ac_seo' ) && ! is_singular() && ! is_front_page() ) {
		$a = zad_ac_seo( 'description' );
		if ( $a ) {
			return $a;
		}
	}
	if ( is_front_page() ) {
		$id = (int) get_option( 'page_on_front' );
		$custom = $id ? zad_seo_meta( $id, 'desc' ) : '';
		if ( $custom ) { return $custom; }
		return zad_opt( 'zad_site_desc', zad_opt( 'zad_hero_sub', get_bloginfo( 'description' ) ) );
	}
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$d = zad_seo_meta( $id, 'desc' );
		if ( $d ) {
			return function_exists( 'zad_clean_answer' ) ? zad_clean_answer( $d ) : $d;
		}
		if ( function_exists( 'zad_is_faq' ) && zad_is_faq( $id ) && function_exists( 'zad_faq_answer_text' ) ) {
			$a = zad_faq_answer_text( $id );
			if ( '' !== $a ) { return wp_trim_words( $a, 32, '…' ); }
		}
		$ex = zad_seo_plain_text( get_the_excerpt( $id ) );
		if ( ! $ex ) { $ex = zad_seo_plain_text( get_post_field( 'post_content', $id ) ); }
		if ( ! $ex ) { $ex = zad_seo_plain_text( zad_opt( 'zad_site_desc', get_bloginfo( 'description' ) ) ); }
		return wp_trim_words( $ex, 30, '' );
	}
	if ( function_exists( 'zad_seo_archive_value' ) ) {
		$v = zad_seo_archive_value( 'desc' );
		if ( '' !== $v ) { return $v; }
	}
	if ( is_tax() || is_category() ) {
		$d = term_description();
		return $d ? wp_trim_words( wp_strip_all_tags( $d ), 30, '' ) : '';
	}
	return '';
}

function zad_seo_image() {
	if ( is_singular() ) {
		$id  = get_queried_object_id();
		$aid = (int) zad_seo_meta( $id, 'img' );
		if ( ! $aid ) {
			if ( zad_seo_legacy_on() && ! metadata_exists( 'post', $id, '_zad_seo_img' ) ) {
				$legacy = esc_url_raw( (string) get_post_meta( $id, '_yoast_wpseo_opengraph-image', true ) );
				if ( $legacy ) { return array( $legacy, 0, 0 ); }
			}
			if ( has_post_thumbnail( $id ) ) { $aid = get_post_thumbnail_id( $id ); }
		}
		if ( $aid ) {
			$src = wp_get_attachment_image_src( $aid, 'large' );
			if ( $src ) { return array( $src[0], $src[1], $src[2] ); }
		}
	}
	$og = zad_opt( 'zad_og_default' );
	if ( is_array( $og ) && ! empty( $og['url'] ) ) {
		$src = ! empty( $og['id'] ) ? wp_get_attachment_image_src( absint( $og['id'] ), 'full' ) : false;
		return $src ? array( $src[0], $src[1], $src[2] ) : array( $og['url'], (int) ( $og['width'] ?? 0 ), (int) ( $og['height'] ?? 0 ) );
	}
	if ( is_front_page() ) { // no default OG image set: the home hero photo
		$h = zad_opt( 'zad_hero_img' );
		if ( is_array( $h ) && ! empty( $h['url'] ) ) { return array( $h['url'], 0, 0 ); }
	}
	$logo = zad_opt( 'memopt_logo' );
	return ( is_array( $logo ) && ! empty( $logo['url'] ) ) ? array( $logo['url'], 0, 0 ) : null;
}

add_filter( 'pre_get_document_title', function ( $t ) {
	if ( zad_seo_active() ) {
		return $t;
	}
	$z = zad_seo_title();
	return $z ? $z : $t;
}, 20 );

add_filter( 'wp_robots', function ( $r ) {
	if ( zad_seo_active() ) {
		return $r;
	}
	if ( is_singular() && zad_seo_meta( get_queried_object_id(), 'noindex' ) ) {
		$r['noindex'] = true;
		unset( $r['max-image-preview'] );
	} elseif ( is_search() ) {
		$r['noindex'] = true;
	} else {
		$r['max-image-preview'] = 'large';
		$r['max-snippet']       = '-1';
		$r['max-video-preview'] = '-1';
	}
	return $r;
} );

add_action( 'wp_head', function () {
	if ( zad_seo_active() || is_404() ) {
		return;
	}
	$url  = zad_seo_canonical_url();
	$desc = zad_seo_desc();
	$ttl  = zad_seo_title() ?: wp_get_document_title();
	$type = ( is_singular( 'post' ) || zad_is_faq() || zad_is_article() ) ? 'article' : 'website';
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	remove_action( 'wp_head', 'rel_canonical' );
	if ( $url && ! is_search() ) { // search results: noindex and no canonical; the theme prints no hreflang
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
	$og = array( 'og:locale' => 'ar_SA', 'og:type' => $type, 'og:title' => $ttl, 'og:description' => $desc, 'og:url' => $url, 'og:site_name' => get_bloginfo( 'name' ) );
	foreach ( $og as $k => $v ) {
		if ( $v ) { echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; }
	}
	$img = zad_seo_image();
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img[0] ) . '">' . "\n";
		if ( $img[1] ) {
			echo '<meta property="og:image:width" content="' . (int) $img[1] . '"><meta property="og:image:height" content="' . (int) $img[2] . '">' . "\n";
		}
		echo '<meta name="twitter:image" content="' . esc_url( $img[0] ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $ttl ) . '">' . "\n";
	if ( $desc ) { echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
	if ( is_singular( 'post' ) || zad_is_faq() || zad_is_article() ) {
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
	}
	// Place tags describe the page, not the company: a service page in Unaizah must not carry the head office's coordinates.
	$place  = ( is_singular() && function_exists( 'zad_is_service' ) && zad_is_service() ) ? zad_current_city( get_queried_object_id() )['name'] : zad_opt( 'zad_city_name', 'الرياض' );
	$row    = function_exists( 'zad_city_row' ) ? zad_city_row( $place ) : array();
	$lat    = $row ? $row['lat'] : zad_opt( 'zad_lat' );
	$lng    = $row ? $row['lng'] : zad_opt( 'zad_lng' );
	$region = $row && ! empty( $row['region'] ) ? $row['region'] : 'SA';
	if ( $lat && $lng ) {
		echo '<meta name="geo.region" content="' . esc_attr( $region ) . '"><meta name="geo.placename" content="' . esc_attr( $place ) . '">' . "\n";
		echo '<meta name="geo.position" content="' . esc_attr( $lat . ';' . $lng ) . '"><meta name="ICBM" content="' . esc_attr( $lat . ', ' . $lng ) . '">' . "\n";
	}
}, 4 );

add_action( 'wp', function () { if ( ! zad_seo_active() && false === has_action( 'wp_head', 'wp_robots' ) ) { add_action( 'wp_head', 'wp_robots', 1 ); } } );
