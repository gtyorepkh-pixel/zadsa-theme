<?php defined( 'ABSPATH' ) || exit;
/**
 * Built-in SEO: per-page title/description/OG image/noindex, canonical, Open Graph, Twitter, geo (no hreflang).
 * Disabled automatically when Yoast, Rank Math or All in One SEO is active.
 */

function zad_seo_active() {
	return 'theme' !== zad_schema_owner() || 'yoast' === zad_seo_mode() || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

/* ---- meta box ---- */
add_action( 'add_meta_boxes', function () {
	foreach ( array_unique( array_merge( zad_service_types(), zad_faq_types(), zad_article_types(), array( 'page', 'post' ) ) ) as $pt ) {
		add_meta_box( 'zad_seo', 'SEO — محركات البحث', 'zad_seo_metabox', $pt, 'normal', 'default' );
	}
} );

function zad_seo_metabox( $post ) {
	if ( 'yoast' === zad_seo_mode() ) {
		echo '<p>السيو (العنوان والوصف وrobots وصورة المشاركة) في هذه الصفحة يُدار من <b>Yoast SEO</b>. الثيم يطبع السكيما فقط، ويعطي Yoast قيمة افتراضية ذكية إن تُركت حقوله فارغة.</p>';
		return;
	}
	wp_nonce_field( 'zad_seo_save', 'zad_seo_nonce' );
	$g = function ( $k ) use ( $post ) { return get_post_meta( $post->ID, '_zad_seo_' . $k, true ); };
	?>
	<p><label>عنوان الصفحة (Title) <small>— المثالي 50–60 حرفاً</small>
		<input type="text" name="zad_seo[title]" value="<?php echo esc_attr( $g( 'title' ) ); ?>" style="width:100%" placeholder="<?php echo esc_attr( get_the_title( $post ) . ' | ' . get_bloginfo( 'name' ) ); ?>"></label></p>
	<p><label>الوصف (Meta description) <small>— المثالي 140–160 حرفاً</small>
		<textarea name="zad_seo[desc]" rows="3" style="width:100%"><?php echo esc_textarea( $g( 'desc' ) ); ?></textarea></label></p>
	<p><label>صورة المشاركة (OG) — رقم الصورة في المكتبة، أو اتركه لتُستخدم الصورة البارزة
		<input type="number" name="zad_seo[img]" value="<?php echo esc_attr( $g( 'img' ) ); ?>" style="width:140px"></label></p>
	<p><label><input type="checkbox" name="zad_seo[noindex]" value="1" <?php checked( $g( 'noindex' ), '1' ); ?>> عدم الفهرسة (noindex)</label></p>
	<?php if ( in_array( $post->post_type, zad_service_types(), true ) ) : ?>
	<hr>
	<p><label>اسم الخدمة بدون المدينة <small>— يُستخدم لعناوين صفحات «خدمة + مدينة»، مثال: شركة رش مبيدات</small>
		<input type="text" name="zad_seo[base]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_zad_base_name', true ) ); ?>" style="width:100%"></label></p>
	<p><label>نص تعريفي لكل مدينة <small>— سطر لكل مدينة: رابط-المدينة | النص (اختياري، وإلا يُولَّد تلقائياً)</small>
		<textarea name="zad_seo[city_text]" rows="3" style="width:100%"><?php echo esc_textarea( get_post_meta( $post->ID, '_zad_city_text', true ) ); ?></textarea></label></p>
	<?php endif;
}

add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['zad_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_seo_nonce'] ) ), 'zad_seo_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$in = isset( $_POST['zad_seo'] ) ? wp_unslash( (array) $_POST['zad_seo'] ) : array();
	update_post_meta( $id, '_zad_seo_title', isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : '' );
	update_post_meta( $id, '_zad_seo_desc', isset( $in['desc'] ) ? sanitize_textarea_field( $in['desc'] ) : '' );
	update_post_meta( $id, '_zad_seo_img', isset( $in['img'] ) ? absint( $in['img'] ) : '' );
	update_post_meta( $id, '_zad_seo_noindex', ! empty( $in['noindex'] ) ? '1' : '' );
	if ( in_array( get_post_type( $id ), zad_service_types(), true ) ) {
		update_post_meta( $id, '_zad_base_name', isset( $in['base'] ) ? sanitize_text_field( $in['base'] ) : '' );
		update_post_meta( $id, '_zad_city_text', isset( $in['city_text'] ) ? sanitize_textarea_field( $in['city_text'] ) : '' );
	}
} );

/* ---- computed values ---- */
function zad_seo_meta( $id, $k ) {
	return $id ? get_post_meta( $id, '_zad_seo_' . $k, true ) : '';
}

function zad_seo_title() {
	$site = get_bloginfo( 'name' );
	if ( is_front_page() ) {
		return zad_opt( 'zad_hero_title', $site ) . ' | ' . $site;
	}
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$city = function_exists( 'zad_current_city' ) ? zad_current_city() : null;
		if ( $city ) {
			return zad_city_title( $id, $city ) . ' | ' . $site;
		}
		$t = zad_seo_meta( $id, 'title' );
		return $t ? $t : get_the_title( $id ) . ' | ' . $site;
	}
	if ( function_exists( 'zad_seo_archive_value' ) ) {
		return zad_seo_archive_value( 'title' );
	}
	return '';
}

function zad_seo_desc() {
	if ( function_exists( 'zad_ac_seo' ) && ! is_singular() && ! is_front_page() ) {
		$a = zad_ac_seo( 'description' );
		if ( $a ) {
			return $a;
		}
	}
	if ( is_front_page() ) {
		return zad_opt( 'zad_site_desc', zad_opt( 'zad_hero_sub', get_bloginfo( 'description' ) ) );
	}
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$city = function_exists( 'zad_current_city' ) ? zad_current_city() : null;
		if ( $city ) {
			return wp_trim_words( zad_city_text( $id, $city ), 30, '' );
		}
		$d = zad_seo_meta( $id, 'desc' );
		if ( $d ) {
			return function_exists( 'zad_clean_answer' ) ? zad_clean_answer( $d ) : $d;
		}
		if ( function_exists( 'zad_is_faq' ) && zad_is_faq( $id ) && function_exists( 'zad_faq_answer_text' ) ) {
			$a = zad_faq_answer_text( $id );
			if ( '' !== $a ) { return wp_trim_words( $a, 32, '…' ); }
		}
		$ex = get_the_excerpt( $id );
		return $ex ? wp_trim_words( wp_strip_all_tags( $ex ), 30, '' ) : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 30, '' );
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
		if ( ! $aid && has_post_thumbnail( $id ) ) {
			$aid = get_post_thumbnail_id( $id );
		}
		if ( $aid ) {
			$src = wp_get_attachment_image_src( $aid, 'large' );
			if ( $src ) { return array( $src[0], $src[1], $src[2] ); }
		}
	}
	$og = zad_opt( 'zad_og_default' );
	if ( is_array( $og ) && ! empty( $og['url'] ) ) {
		return array( $og['url'], 1200, 630 );
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
	$url  = zad_current_url();
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
	$lat = zad_opt( 'zad_lat' );
	$lng = zad_opt( 'zad_lng' );
	if ( $lat && $lng ) {
		echo '<meta name="geo.region" content="SA"><meta name="geo.placename" content="' . esc_attr( zad_opt( 'zad_city_name', 'الرياض' ) ) . '">' . "\n";
		echo '<meta name="geo.position" content="' . esc_attr( $lat . ';' . $lng ) . '"><meta name="ICBM" content="' . esc_attr( $lat . ', ' . $lng ) . '">' . "\n";
	}
}, 4 );
