<?php defined( 'ABSPATH' ) || exit;
/**
 * SEO pack: term intros + smart defaults, thin-area noindex, sitemap hygiene, clean archive H1,
 * author pages (profile, Person, byline/box, 301 when no bio) and the "yoast" mode split.
 *
 * Modes (zad_seo_mode()):
 *   theme — the theme prints title/description/canonical/robots/OG and uses wp-sitemap (this file filters it).
 *   yoast — the theme prints schema ONLY. Yoast prints all meta and the sitemap; we feed it smart defaults.
 */

/* ------------------------------------------------------------------ */
/* Helpers                                                              */
/* ------------------------------------------------------------------ */
function zad_seo_tax_list() { return array( 'service_area', 'service_cat', 'faq_cat' ); }

function zad_brand_name() {
	$b = trim( (string) zad_opt( 'zad_provider', '' ) );
	return '' !== $b ? $b : get_bloginfo( 'name' );
}

function zad_term_intro( $term ) {
	return $term ? trim( (string) get_term_meta( $term->term_id, '_zad_intro', true ) ) : '';
}

/** City name for a term: its parent for neighbourhoods, otherwise the theme's default city. */
function zad_term_city( $term ) {
	if ( $term && 'service_area' === $term->taxonomy && $term->parent ) {
		$p = get_term( $term->parent, 'service_area' );
		if ( $p && ! is_wp_error( $p ) ) { return $p->name; }
	}
	return (string) zad_opt( 'zad_city_name', 'الرياض' );
}

/** Explicitly noindexed, or a thin neighbourhood: no intro text and fewer than two services. */
function zad_term_noindexed( $term ) {
	if ( ! $term || is_wp_error( $term ) ) { return false; }
	if ( '1' === (string) get_term_meta( $term->term_id, '_zad_noindex', true ) ) { return true; }
	if ( 'service_area' !== $term->taxonomy ) { return false; }
	return '' === zad_term_intro( $term ) && (int) $term->count < 2;
}

/** IDs of noindexed terms in a taxonomy (cached; flushed when terms or posts change). */
function zad_noindex_term_ids( $taxonomy ) {
	$key = 'zad_nx_' . $taxonomy;
	$ids = get_transient( $key );
	if ( is_array( $ids ) ) { return $ids; }
	$ids = array();
	$ts  = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	if ( $ts && ! is_wp_error( $ts ) ) {
		foreach ( $ts as $t ) { if ( zad_term_noindexed( $t ) ) { $ids[] = (int) $t->term_id; } }
	}
	set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
	return $ids;
}
function zad_flush_nx() { foreach ( zad_seo_tax_list() as $t ) { delete_transient( 'zad_nx_' . $t ); } }
add_action( 'created_term', 'zad_flush_nx' );
add_action( 'edited_term', 'zad_flush_nx' );
add_action( 'delete_term', 'zad_flush_nx' );
add_action( 'set_object_terms', 'zad_flush_nx' );

/** Default title/description for a term (used by the theme and, in yoast mode, fed to Yoast when its fields are empty). */
function zad_term_default( $term, $field ) {
	$brand = zad_brand_name();
	$name  = $term->name;
	$city  = zad_term_city( $term );
	$intro = wp_trim_words( wp_strip_all_tags( zad_term_intro( $term ) ), 28, '…' );
	if ( 'service_area' === $term->taxonomy ) {
		$hood = $term->parent ? 'حي ' . $name . ' ب' . $city : $name;
		if ( 'title' === $field ) { return 'خدمات ' . $brand . ' في ' . $hood . ' | معاينة مجانية'; }
		return '' !== $intro ? $intro : 'اطلب خدمات ' . $brand . ' في ' . $hood . ': معاينة مجانية وتواصل سريع عبر واتساب أو الاتصال، مع تحديد السعر قبل البدء.';
	}
	if ( 'service_cat' === $term->taxonomy ) {
		if ( 'title' === $field ) { return $name . ' في ' . $city . ' | ' . $brand; }
		$d = trim( wp_strip_all_tags( (string) $term->description ) );
		return '' !== $intro ? $intro : ( '' !== $d ? wp_trim_words( $d, 28, '…' ) : 'تعرّف على خدمات ' . $name . ' من ' . $brand . ' في ' . $city . ' واطلب معاينة مجانية.' );
	}
	if ( 'title' === $field ) { return $name . ' — أسئلة شائعة | ' . $brand; }
	return '' !== $intro ? $intro : 'إجابات واضحة عن ' . $name . ' من فريق ' . $brand . '.';
}

/** Archive defaults: /services/ and /faq/. */
function zad_archive_default( $kind, $field ) {
	$brand = zad_brand_name();
	$city  = (string) zad_opt( 'zad_city_name', 'الرياض' );
	if ( 'services' === $kind ) {
		return 'title' === $field ? 'كل خدمات ' . $brand . ' في ' . $city . ' | معاينة مجانية' : 'تصفّح كل خدمات ' . $brand . ' في ' . $city . ' واطلب معاينة مجانية وعرض سعر واضحاً قبل البدء.';
	}
	return 'title' === $field ? 'الأسئلة الشائعة | ' . $brand : 'إجابات مختصرة وواضحة عن خدمات ' . $brand . ' من فريقنا.';
}

/** What the current request is, for defaults: array( 'term'|'archive', object|kind ) or null. */
function zad_seo_target() {
	if ( is_tax( zad_seo_tax_list() ) ) { return array( 'term', get_queried_object() ); }
	if ( function_exists( 'zad_is_services_archive' ) && zad_is_services_archive() ) { return array( 'archive', 'services' ); }
	if ( function_exists( 'zad_is_faq_archive' ) && zad_is_faq_archive() ) { return array( 'archive', 'faq' ); }
	return null;
}

/** Title/description (theme mode): the term's own SEO fields, then the smart default. '' = not ours. */
function zad_seo_archive_value( $field ) {
	$t = zad_seo_target();
	if ( ! $t ) { return ''; }
	if ( 'term' === $t[0] ) {
		$own = trim( (string) get_term_meta( $t[1]->term_id, 'title' === $field ? '_zad_seo_title' : '_zad_seo_desc', true ) );
		return '' !== $own ? $own : zad_term_default( $t[1], $field );
	}
	return zad_archive_default( $t[1], $field );
}

/* ------------------------------------------------------------------ */
/* Term fields: intro + SEO (service_area / service_cat / faq_cat)       */
/* ------------------------------------------------------------------ */
foreach ( array( 'service_area', 'service_cat', 'faq_cat' ) as $zad_tx ) {
	add_action( $zad_tx . '_add_form_fields', function () {
		wp_nonce_field( 'zad_term_seo', 'zad_term_nonce' );
		echo '<div class="form-field"><label for="zad_intro">مقدمة نصية للصفحة</label><textarea name="zad_term[intro]" id="zad_intro" rows="4"></textarea><p>تظهر أول الصفحة. بدونها وبأقل من خدمتين يُمنع فهرسة الحي تلقائياً.</p></div>';
	} );
	add_action( $zad_tx . '_edit_form_fields', function ( $term ) {
		wp_nonce_field( 'zad_term_seo', 'zad_term_nonce' );
		$g   = function ( $k ) use ( $term ) { return (string) get_term_meta( $term->term_id, $k, true ); };
		$mode = zad_seo_mode();
		echo '<tr class="form-field"><th><label for="zad_intro">مقدمة نصية للصفحة</label></th><td><textarea name="zad_term[intro]" id="zad_intro" rows="5" class="large-text">' . esc_textarea( $g( '_zad_intro' ) ) . '</textarea><p class="description">تظهر أول الصفحة. إن تُركت فارغة والحي فيه أقل من خدمتين فالصفحة noindex تلقائياً وتغيب عن خريطة الموقع.</p></td></tr>';
		if ( 'yoast' === $mode ) {
			echo '<tr class="form-field"><th>السيو</th><td><p class="description">العنوان والوصف وrobots من Yoast. إن تُركت حقوله فارغة يعطيه الثيم قيمة افتراضية: «' . esc_html( zad_term_default( $term, 'title' ) ) . '».</p></td></tr>';
			return;
		}
		echo '<tr class="form-field"><th><label for="zad_seo_t">عنوان الصفحة (Title)</label></th><td><input type="text" name="zad_term[seo_title]" id="zad_seo_t" class="large-text" value="' . esc_attr( $g( '_zad_seo_title' ) ) . '" placeholder="' . esc_attr( zad_term_default( $term, 'title' ) ) . '"></td></tr>';
		echo '<tr class="form-field"><th><label for="zad_seo_d">الوصف (Meta description)</label></th><td><textarea name="zad_term[seo_desc]" id="zad_seo_d" rows="3" class="large-text" placeholder="' . esc_attr( zad_term_default( $term, 'desc' ) ) . '">' . esc_textarea( $g( '_zad_seo_desc' ) ) . '</textarea></td></tr>';
		echo '<tr class="form-field"><th>الفهرسة</th><td><label><input type="checkbox" name="zad_term[noindex]" value="1" ' . checked( $g( '_zad_noindex' ), '1', false ) . '> عدم الفهرسة (noindex) وإخراجها من خريطة الموقع</label></td></tr>';
	} );
	$zad_save = function ( $term_id ) {
		if ( ! isset( $_POST['zad_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_term_nonce'] ) ), 'zad_term_seo' ) || ! current_user_can( 'manage_categories' ) ) { return; }
		$in = isset( $_POST['zad_term'] ) ? wp_unslash( (array) $_POST['zad_term'] ) : array();
		update_term_meta( $term_id, '_zad_intro', isset( $in['intro'] ) ? wp_kses_post( $in['intro'] ) : '' );
		if ( array_key_exists( 'seo_title', $in ) || array_key_exists( 'seo_desc', $in ) || array_key_exists( 'noindex', $in ) || 'yoast' !== zad_seo_mode() ) {
			update_term_meta( $term_id, '_zad_seo_title', isset( $in['seo_title'] ) ? sanitize_text_field( $in['seo_title'] ) : '' );
			update_term_meta( $term_id, '_zad_seo_desc', isset( $in['seo_desc'] ) ? sanitize_textarea_field( $in['seo_desc'] ) : '' );
			update_term_meta( $term_id, '_zad_noindex', ! empty( $in['noindex'] ) ? '1' : '' );
		}
	};
	add_action( 'created_' . $zad_tx, $zad_save );
	add_action( 'edited_' . $zad_tx, $zad_save );
}

/** The intro block printed first on term pages and on /services/, /faq/. */
function zad_archive_intro_html() {
	$t = zad_seo_target();
	if ( ! $t || is_paged() ) { return ''; }
	$txt = '';
	if ( 'term' === $t[0] ) { $txt = zad_term_intro( $t[1] ); }
	else { $txt = trim( (string) zad_opt( 'services' === $t[1] ? 'zad_services_intro' : 'zad_faq_intro', '' ) ); }
	return '' === $txt ? '' : '<section class="termintro wrap"><div class="prose">' . wpautop( wp_kses_post( $txt ) ) . '</div></section>';
}

/* ------------------------------------------------------------------ */
/* Archive H1: plain name, no prefix, no <span>                          */
/* ------------------------------------------------------------------ */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
add_filter( 'get_the_archive_title', function ( $t ) {
	if ( is_author() ) { return get_the_author_meta( 'display_name', get_queried_object_id() ); }
	if ( is_category() || is_tag() || is_tax() ) { return single_term_title( '', false ); }
	return $t;
}, 5 );

/* ------------------------------------------------------------------ */
/* Theme mode: sitemap hygiene (wp-sitemap)                              */
/* ------------------------------------------------------------------ */
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	if ( 'theme' !== zad_seo_mode() ) { return $args; }
	$mq   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
	$mq[] = array( 'relation' => 'AND',
		array( 'relation' => 'OR', array( 'key' => '_zad_seo_noindex', 'compare' => 'NOT EXISTS' ), array( 'key' => '_zad_seo_noindex', 'value' => '1', 'compare' => '!=' ) ),
		array( 'relation' => 'OR', array( 'key' => '_yoast_wpseo_meta-robots-noindex', 'compare' => 'NOT EXISTS' ), array( 'key' => '_yoast_wpseo_meta-robots-noindex', 'value' => '1', 'compare' => '!=' ) ),
	);
	$args['meta_query'] = $mq;
	return $args;
} );
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	if ( 'theme' !== zad_seo_mode() || ! in_array( $taxonomy, zad_seo_tax_list(), true ) ) { return $args; }
	$ex = zad_noindex_term_ids( $taxonomy );
	if ( $ex ) { $args['exclude'] = array_values( array_unique( array_merge( (array) ( $args['exclude'] ?? array() ), $ex ) ) ); }
	return $args;
}, 10, 2 );
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return ( 'theme' === zad_seo_mode() && 'users' === $name ) ? false : $provider; // author pages are noindex
}, 10, 2 );

/** Robots for terms and authors (theme mode only; Yoast mode: see below). */
add_filter( 'wp_robots', function ( $r ) {
	if ( 'theme' !== zad_seo_mode() || defined( 'WPSEO_VERSION' ) ) { return $r; }
	$nx = false;
	if ( is_author() ) { $nx = true; }
	elseif ( is_tax( zad_seo_tax_list() ) && zad_term_noindexed( get_queried_object() ) ) { $nx = true; }
	if ( $nx ) { $r['noindex'] = true; $r['follow'] = true; unset( $r['max-image-preview'], $r['index'], $r['nofollow'] ); }
	return $r;
}, 12 );

/* ------------------------------------------------------------------ */
/* Yoast mode: Yoast owns meta + sitemap, the theme owns schema          */
/* ------------------------------------------------------------------ */
add_filter( 'wp_sitemaps_enabled', function ( $on ) { return 'yoast' === zad_seo_mode() ? false : $on; } );
add_action( 'template_redirect', function () {
	if ( 'yoast' !== zad_seo_mode() ) { return; }
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
	if ( 'wp-sitemap.xml' === $path || 0 === strpos( $path, 'wp-sitemap-' ) ) {
		wp_safe_redirect( home_url( '/sitemap_index.xml' ), 301 );
		exit;
	}
}, 1 );
add_action( 'init', function () {
	if ( 'yoast' === zad_seo_mode() ) { add_filter( 'wpseo_json_ld_output', '__return_false', 99 ); }
} );

function zad_yoast_term_meta( $term, $key ) {
	if ( class_exists( 'WPSEO_Taxonomy_Meta' ) && method_exists( 'WPSEO_Taxonomy_Meta', 'get_term_meta' ) ) {
		$v = WPSEO_Taxonomy_Meta::get_term_meta( $term, $term->taxonomy, $key );
		return is_string( $v ) ? trim( $v ) : '';
	}
	return null; // unknown: never override
}

/** Smart default for Yoast when ITS field is empty. Returns '' when Yoast has a value (or we cannot tell). */
function zad_yoast_default( $field ) {
	if ( 'yoast' !== zad_seo_mode() ) { return ''; }
	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( ! $id || ! function_exists( 'zad_is_service' ) || ! zad_is_service() ) { return ''; }
		$mine = trim( (string) get_post_meta( $id, 'title' === $field ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc', true ) );
		if ( '' !== $mine ) { return ''; }
		return 'title' === $field ? ( function_exists( 'zad_seo_title' ) ? zad_seo_title() : '' ) : ( function_exists( 'zad_seo_desc' ) ? zad_seo_desc() : '' );
	}
	$t = zad_seo_target();
	if ( $t && 'term' === $t[0] ) {
		$y = zad_yoast_term_meta( $t[1], 'title' === $field ? 'wpseo_title' : 'wpseo_desc' );
		return ( '' === $y ) ? zad_term_default( $t[1], $field ) : '';
	}
	return '';
}
foreach ( array( 'wpseo_title' => 'title', 'wpseo_opengraph_title' => 'title', 'wpseo_twitter_title' => 'title', 'wpseo_metadesc' => 'desc', 'wpseo_opengraph_desc' => 'desc', 'wpseo_twitter_description' => 'desc' ) as $zad_h => $zad_f ) {
	add_filter( $zad_h, function ( $v ) use ( $zad_f ) {
		$d = zad_yoast_default( $zad_f );
		return '' !== $d ? $d : $v;
	}, 20 );
}
foreach ( array( 'wpseo_opengraph_image', 'wpseo_twitter_image' ) as $zad_h ) {
	add_filter( $zad_h, function ( $img ) {
		if ( 'yoast' !== zad_seo_mode() || ( is_string( $img ) && '' !== $img ) ) { return $img; }
		$d = function_exists( 'zad_seo_image' ) ? zad_seo_image() : null;
		return $d ? $d[0] : $img;
	}, 20 );
}
/** Thin / noindexed neighbourhoods and terms: noindex,follow + out of Yoast's sitemap. */
add_filter( 'wpseo_robots', function ( $r ) {
	if ( 'yoast' !== zad_seo_mode() || ! is_string( $r ) ) { return $r; }
	if ( is_tax( zad_seo_tax_list() ) && zad_term_noindexed( get_queried_object() ) ) { return 'noindex, follow'; }
	return $r;
}, 15 );
add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', function ( $ids ) {
	if ( 'yoast' !== zad_seo_mode() ) { return $ids; }
	foreach ( zad_seo_tax_list() as $tx ) { $ids = array_merge( (array) $ids, zad_noindex_term_ids( $tx ) ); }
	return array_values( array_unique( $ids ) );
} );
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) {
	if ( 'yoast' !== zad_seo_mode() ) { return $ids; }
	$more = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_key' => '_zad_seo_noindex', 'meta_value' => '1', 'suppress_filters' => true ) ); // phpcs:ignore
	return array_values( array_unique( array_merge( (array) $ids, $more ) ) );
} );

/** Two schema sources on the same site. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'dashboard', 'plugins' ), true ) ) { return; }
	$act = (array) get_option( 'active_plugins', array() );
	if ( in_array( 'wp-schema-pro/wp-schema-pro.php', $act, true ) ) {
		echo '<div class="notice notice-warning"><p><b>تنبيه سكيما:</b> إضافة «Schema Pro» مفعّلة بجانب سكيما الثيم، فيظهر في الصفحات سكيما من مصدرين. عطّل إحداهما.</p></div>';
	}
} );
