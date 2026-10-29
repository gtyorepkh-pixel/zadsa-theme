<?php defined( 'ABSPATH' ) || exit;
/**
 * SEO pack: term intros + smart defaults, sitemap hygiene, clean archive H1,
 * author pages (profile, Person, byline/box, 301 when no bio).
 * The theme prints title/description/canonical/robots/OG and uses wp-sitemap (this file filters it).
 */

/* ------------------------------------------------------------------ */
/* Helpers                                                              */
/* ------------------------------------------------------------------ */
function zad_seo_tax_list() { return array_values( array_unique( array_merge( array( 'service_area', 'service_cat', 'faq_cat' ), get_taxonomies( array( 'public' => true ) ) ) ) ); }

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

/** Explicit noindex (checkbox on the term). Optional automatic rule behind the theme option zad_thin_noindex (off by default). */
function zad_term_noindexed( $term ) {
	if ( ! $term || is_wp_error( $term ) ) { return false; }
	if ( ( 'category' === $term->taxonomy && zad_opt( 'zad_noindex_cat', false ) ) || ( 'post_tag' === $term->taxonomy && zad_opt( 'zad_noindex_tag', false ) ) ) { return true; }
	if ( '1' === (string) zad_seo_term_meta( $term, 'noindex' ) ) { return true; }
	if ( 'service_area' === $term->taxonomy && zad_opt( 'zad_thin_noindex', false ) ) { // no intro text and fewer than two services
		return '' === zad_term_intro( $term ) && (int) $term->count < 2;
	}
	return false;
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
add_action( 'update_option__memo_theme_options', 'zad_flush_nx' ); // the thin-area switch lives in the theme options

/** Default title/description for a term. */
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
	if ( 'faq_cat' !== $term->taxonomy ) { return 'title' === $field ? $name . ' | ' . $brand : ( $intro ?: wp_trim_words( wp_strip_all_tags( $term->description ), 30, '…' ) ); }
	if ( 'title' === $field ) { return $name . ' — أسئلة شائعة | ' . $brand; }
	return '' !== $intro ? $intro : 'إجابات واضحة عن ' . $name . ' من فريق ' . $brand . '.';
}

/** Archive defaults: /services/ and /faq/. */
function zad_archive_default( $kind, $field ) {
	$brand = zad_brand_name();
	if ( 'services' === $kind ) {
		// /services/ بيعرض خدمات كل المدن، فعنوانه ما يسمّيش مدينة واحدة (زي /cleaning/ و/pest-control/).
		return 'title' === $field ? 'كل خدمات ' . $brand . ' في السعودية | معاينة مجانية' : 'تصفّح كل خدمات ' . $brand . ' في مدن التغطية واطلب معاينة مجانية وعرض سعر واضحاً قبل البدء.';
	}
	return 'title' === $field ? 'الأسئلة الشائعة | ' . $brand : 'إجابات مختصرة وواضحة عن خدمات ' . $brand . ' من فريقنا.';
}

/** What the current request is, for defaults: array( 'term'|'archive', object|kind ) or null. */
function zad_seo_target() {
	if ( is_tax( zad_seo_tax_list() ) || is_category() || is_tag() ) { return array( 'term', get_queried_object() ); }
	if ( function_exists( 'zad_is_services_archive' ) && zad_is_services_archive() ) {
		$pt = function_exists( 'zad_query_pt' ) ? zad_query_pt() : '';
		// Native /services/ vs an adopted custom type (/cleaning/, /pest-control/ …): each type gets its own defaults.
		return ( '' === $pt || 'zad_service' === $pt ) ? array( 'archive', 'services' ) : array( 'type', $pt );
	}
	if ( function_exists( 'zad_is_faq_archive' ) && zad_is_faq_archive() ) { return array( 'archive', 'faq' ); }
	if ( defined( 'ZAD_WK' ) && is_post_type_archive( ZAD_WK ) ) { return array( 'type', ZAD_WK ); }   // أرشيف «أعمالنا» كان بره تغطية السيو فطلع بدون وصف
	return null;
}

/** Title/description (theme mode): the term's own SEO fields, then the smart default. '' = not ours. */
function zad_seo_archive_value( $field ) {
	$t = zad_seo_target();
	if ( ! $t ) { return ''; }
	if ( 'term' === $t[0] ) {
		$own = trim( (string) zad_seo_term_meta( $t[1], $field ) );
		return '' !== $own ? $own : zad_term_default( $t[1], $field );
	}
	if ( 'type' === $t[0] ) { return zad_type_default( $t[1], $field ); }
	return zad_archive_default( $t[1], $field );
}

/** Defaults for the archive of an adopted service type (e.g. /cleaning/). The archive-content module's own title/description override these. */
function zad_type_default( $pt, $field ) {
	$o = get_post_type_object( $pt );
	if ( ! $o ) { return ''; }
	// «أعمالنا» معرض تنفيذ، مش قسم خدمات، فصيغة «خدمات …» ما تنفعش له وكان بيخرج بدون وصف.
	if ( defined( 'ZAD_WK' ) && ZAD_WK === $pt ) {
		$brand = zad_brand_name();
		return 'title' === $field
			? 'أعمالنا — نماذج من تنفيذ ' . $brand
			: 'صور من أعمال ' . $brand . ' قبل التنفيذ وبعده في التنظيف ومكافحة الحشرات وتسليك المجاري وجلي البلاط، مرتّبة حسب الخدمة والمدينة.';
	}
	$label = function_exists( 'zad_hub_type_h1' ) ? zad_hub_type_h1( $pt ) : trim( preg_replace( '/^\s*(?:صفحات|صفحة)\s+/u', '', (string) $o->labels->name ) ); // same name as the H1 («خدمات التنظيف»)
	if ( '' === $label ) { return ''; }
	if ( 0 !== mb_strpos( $label, 'خدمات' ) && 0 !== mb_strpos( $label, 'خدمة' ) ) { $label = 'خدمات ' . $label; }
	$brand = zad_brand_name();
	// أرشيف نوع المحتوى بيعرض كل المدن، فما ينفعش عنوانه يقول مدينة واحدة.
	// نفس صيغة zad_hub_default_title() علشان /services/ و/cleaning/ و/pest-control/ يبقوا متسقين.
	return 'title' === $field ? $label . ' في السعودية | ' . $brand : 'تعرّف على ' . $label . ' من ' . $brand . ' في كل مدن التغطية: معاينة مجانية وتواصل سريع عبر واتساب أو الاتصال.';
}

/* ------------------------------------------------------------------ */
/* Term fields: intro + SEO (service_area / service_cat / faq_cat)       */
/* ------------------------------------------------------------------ */
foreach ( array( 'service_area', 'service_cat', 'faq_cat' ) as $zad_tx ) {
	add_action( $zad_tx . '_add_form_fields', function () {
		wp_nonce_field( 'zad_term_seo', 'zad_term_nonce' );
		echo '<div class="form-field"><label for="zad_intro">مقدمة نصية للصفحة</label><textarea name="zad_term[intro]" id="zad_intro" rows="4"></textarea><p>تظهر أول الصفحة.</p></div>';
	} );
	add_action( $zad_tx . '_edit_form_fields', function ( $term ) {
		wp_nonce_field( 'zad_term_seo', 'zad_term_nonce' );
		$g   = function ( $k ) use ( $term ) { return (string) get_term_meta( $term->term_id, $k, true ); };
		echo '<tr class="form-field"><th><label for="zad_intro">مقدمة نصية للصفحة</label></th><td><textarea name="zad_term[intro]" id="zad_intro" rows="5" class="large-text">' . esc_textarea( $g( '_zad_intro' ) ) . '</textarea><p class="description">تظهر أول الصفحة.</p></td></tr>';
		echo '<tr class="form-field"><th><label for="zad_seo_t">عنوان الصفحة (Title)</label></th><td><input type="text" name="zad_term[seo_title]" id="zad_seo_t" class="large-text" value="' . esc_attr( zad_seo_term_meta( $term, 'title' ) ) . '" placeholder="' . esc_attr( zad_term_default( $term, 'title' ) ) . '"></td></tr>';
		echo '<tr class="form-field"><th><label for="zad_seo_d">الوصف (Meta description)</label></th><td><textarea name="zad_term[seo_desc]" id="zad_seo_d" rows="3" class="large-text" placeholder="' . esc_attr( zad_term_default( $term, 'desc' ) ) . '">' . esc_textarea( zad_seo_term_meta( $term, 'desc' ) ) . '</textarea></td></tr>';
		echo '<tr class="form-field"><th>الرابط الأساسي</th><td><input type="url" name="zad_term[canonical]" class="large-text" value="' . esc_attr( zad_seo_term_meta( $term, 'canonical' ) ) . '"></td></tr>';
		echo '<tr class="form-field"><th>الفهرسة</th><td><label><input type="checkbox" name="zad_term[noindex]" value="1" ' . checked( zad_seo_term_meta( $term, 'noindex' ), '1', false ) . '> عدم الفهرسة (noindex) وإخراجها من خريطة الموقع</label></td></tr>';
	} );
	$zad_save = function ( $term_id ) {
		if ( ! isset( $_POST['zad_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_term_nonce'] ) ), 'zad_term_seo' ) || ! current_user_can( 'manage_categories' ) ) { return; }
		$in = isset( $_POST['zad_term'] ) ? wp_unslash( (array) $_POST['zad_term'] ) : array();
		update_term_meta( $term_id, '_zad_intro', isset( $in['intro'] ) ? wp_kses_post( $in['intro'] ) : '' );
		update_term_meta( $term_id, '_zad_seo_title', isset( $in['seo_title'] ) ? sanitize_text_field( $in['seo_title'] ) : '' );
		update_term_meta( $term_id, '_zad_seo_desc', isset( $in['seo_desc'] ) ? sanitize_textarea_field( $in['seo_desc'] ) : '' );
		update_term_meta( $term_id, '_zad_seo_canonical', esc_url_raw( $in['canonical'] ?? '' ) );
		update_term_meta( $term_id, '_zad_noindex', ! empty( $in['noindex'] ) ? '1' : '' );
	};
	add_action( 'created_' . $zad_tx, $zad_save );
	add_action( 'edited_' . $zad_tx, $zad_save );
}

/** The intro block printed first on term pages and on /services/, /faq/. */
function zad_archive_intro_html() {
	$t = zad_seo_target();
	if ( ! $t || is_paged() || 'type' === $t[0] ) { return ''; } // adopted types use the archive-content page
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
	$mq[] = array( 'relation' => 'OR',
		array( 'key' => '_zad_seo_noindex', 'value' => '1', 'compare' => '!=' ),
		array( 'key' => '_zad_seo_noindex', 'compare' => 'NOT EXISTS' ), // pages noindexed only by the old SEO plugin are dropped by zad_seo_eligible_ids() (inc/seo/policy.php)
	);
	$args['meta_query'] = $mq;
	return $args;
} );
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	if ( 'theme' !== zad_seo_mode() || ! in_array( $taxonomy, zad_seo_tax_list(), true ) ) { return $args; }
	$ex = array();
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { if ( ! zad_seo_term_eligible( $term ) ) { $ex[] = (int) $term->term_id; } } }
	if ( $ex ) { $args['exclude'] = array_values( array_unique( array_merge( (array) ( $args['exclude'] ?? array() ), $ex ) ) ); }
	return $args;
}, 10, 2 );
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return ( 'theme' === zad_seo_mode() && 'users' === $name && zad_opt( 'zad_noindex_author', false ) ) ? false : $provider; // respect the saved author indexing option
}, 10, 2 );

/** Robots for terms and authors. */
add_filter( 'wp_robots', function ( $r ) {
	if ( 'theme' !== zad_seo_mode() ) { return $r; }
	$nx = false;
	if ( is_author() ) { $nx = zad_opt( 'zad_noindex_author', false ); }
	elseif ( ( is_tax( zad_seo_tax_list() ) || is_category() || is_tag() ) && zad_term_noindexed( get_queried_object() ) ) { $nx = true; }
	if ( $nx ) { $r['noindex'] = true; $r['follow'] = true; unset( $r['max-image-preview'], $r['index'], $r['nofollow'] ); }
	return $r;
}, 12 );

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
