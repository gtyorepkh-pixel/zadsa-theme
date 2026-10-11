<?php defined( 'ABSPATH' ) || exit;
/**
 * Zad Site Suite, merged into the theme.
 *
 * Re-implements the five Suite modules inside the theme so the plugin can be
 * deactivated without conflicts. The same option/meta keys are used, so every
 * saved mapping, SEO field and breadcrumb exception keeps working untouched.
 * Each part steps aside while the matching plugin module is still active.
 *
 *  1. Archive content   (draft page shown inside archive/term/author URLs)
 *  2. Breadcrumbs       (path based trail + label/parent exceptions)
 *  3. Content TOC       (theme TOC in zad-frontend.php; nothing to add)
 *  4. HTML sitemap      ([zad_site_map] shortcode)
 *  5. Performance       (dashicons, Trustindex stylesheet)
 */

const ZAD_AC_MAP         = 'zad_archive_content_mappings';
const ZAD_AC_SEO         = 'zad_archive_content_seo';
const ZAD_AC_TERM_MAP    = 'zad_archive_content_term_mappings';
const ZAD_AC_TERM_SEO    = 'zad_archive_content_term_seo';
const ZAD_AC_HIDE        = 'zad_archive_content_hide_loop';
const ZAD_AC_AUTHOR_MAP  = 'zad_archive_content_author_mappings';
const ZAD_AC_AUTHOR_IDX  = 'zad_archive_content_author_index';
const ZAD_AC_SINGULAR    = '_zad_archive_content_source_page';
const ZAD_BC_LABEL       = '_zad_breadcrumb_label';
const ZAD_BC_PARENT      = '_zad_breadcrumb_parent_path';

function zad_suite_has( $mod ) {
	$c = array( 'archive' => 'ZAD_ARCHIVE_CONTENT_VERSION', 'bc' => 'ZAD_SITE_BC_VERSION', 'map' => 'ZAD_SMART_SITEMAP_VERSION', 'toc' => 'ZAD_CONTENT_TOC_VERSION' );
	return isset( $c[ $mod ] ) && defined( $c[ $mod ] );
}

/* =====================================================================
 * 1. ARCHIVE CONTENT
 * ===================================================================== */

function zad_ac_taxes() {
	$t = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'objects' );
	$c = get_taxonomy( 'category' );
	return $c ? array_merge( array( 'category' => $c ), $t ) : $t;
}

/** Page ID mapped to the current archive (0 = none). */
function zad_ac_source_id() {
	static $cache = array();
	$key = (string) get_queried_object_id() . '|' . zad_query_pt();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$id = 0;
	if ( is_post_type_archive() ) {
		$pt  = zad_query_pt();
		$pt  = is_array( $pt ) ? reset( $pt ) : $pt;
		$map = (array) get_option( ZAD_AC_MAP, array() );
		$id  = isset( $map[ $pt ] ) ? absint( $map[ $pt ] ) : 0;
	} elseif ( is_author() ) {
		$map = (array) get_option( ZAD_AC_AUTHOR_MAP, array() );
		$id  = isset( $map[ get_queried_object_id() ] ) ? absint( $map[ get_queried_object_id() ] ) : 0;
	} elseif ( is_tax() || is_category() ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term ) {
			$map = (array) get_option( ZAD_AC_TERM_MAP, array() );
			$id  = isset( $map[ $t->taxonomy ][ $t->term_id ] ) ? absint( $map[ $t->taxonomy ][ $t->term_id ] ) : 0;
		}
	}
	if ( $id ) {
		$p = get_post( $id );
		if ( ! $p || 'page' !== $p->post_type || in_array( $p->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			$id = 0;
		}
	}
	$cache[ $key ] = $id;
	return $id;
}

/** Title of the mapped page (or the fallback). */
function zad_ac_title( $fallback ) {
	if ( is_admin() || zad_suite_has( 'archive' ) ) {
		return $fallback;
	}
	$id = zad_ac_source_id();
	if ( $id && ! is_author() ) {
		$t = trim( get_the_title( $id ) );
		return '' !== $t ? $t : $fallback;
	}
	return $fallback;
}

add_filter( 'get_the_archive_title', function ( $t ) {
	return zad_ac_title( $t );
}, 20 );

/** Rendered body of the mapped page; echoes a section. Renders once per request, first page only. */
function zad_archive_source( $echo = true ) {
	static $done = false;
	if ( $done || zad_suite_has( 'archive' ) || is_paged() ) {
		return '';
	}
	$id = zad_ac_source_id();
	if ( ! $id ) {
		return '';
	}
	$page = get_post( $id );
	if ( is_author() ) {
		remove_filter( 'the_content', 'wpautop' );
	}
	$html = apply_filters( 'the_content', $page->post_content );
	if ( is_author() ) {
		add_filter( 'the_content', 'wpautop' );
	}
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}
	$done = true;
	$out  = '<section class="zad-archive-content wrap" aria-label="محتوى القسم"><div class="prose">' . $html . '</div></section>';
	if ( $echo ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	return $out;
}

/** Custom or inherited (Yoast fields of the mapped page) SEO field. */
function zad_ac_seo( $field ) {
	if ( is_admin() || zad_suite_has( 'archive' ) || is_singular() || is_front_page() ) {
		return '';
	}
	$custom = '';
	if ( is_post_type_archive() ) {
		$pt     = zad_query_pt();
		$pt     = is_array( $pt ) ? reset( $pt ) : $pt;
		$seo    = (array) get_option( ZAD_AC_SEO, array() );
		$custom = $seo[ $pt ][ $field ] ?? '';
	} elseif ( is_tax() || is_category() ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term ) {
			$seo    = (array) get_option( ZAD_AC_TERM_SEO, array() );
			$custom = $seo[ $t->taxonomy ][ $t->term_id ][ $field ] ?? '';
		}
	}
	$custom = trim( (string) $custom );
	if ( '' !== $custom ) {
		return $custom;
	}
	$id = zad_ac_source_id();
	if ( ! $id ) {
		return '';
	}
	$v = trim( (string) get_post_meta( $id, 'title' === $field ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc', true ) );
	if ( '' !== $v && function_exists( 'wpseo_replace_vars' ) ) {
		$v = wpseo_replace_vars( $v, get_post( $id ) );
	}
	$v = trim( wp_strip_all_tags( $v ) );
	if ( '' === $v && 'title' === $field ) {
		$v = trim( get_the_title( $id ) ) . ' | ' . get_bloginfo( 'name' );
	}
	return $v;
}

foreach ( array( 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title', 'pre_get_document_title' ) as $zad_h ) {
	add_filter( $zad_h, function ( $t ) {
		$v = zad_ac_seo( 'title' );
		return '' !== $v ? $v : $t;
	}, 25 );
}
foreach ( array( 'wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description' ) as $zad_h ) {
	add_filter( $zad_h, function ( $d ) {
		$v = zad_ac_seo( 'description' );
		return '' !== $v ? $v : $d;
	}, 25 );
}

/* Author profiles approved for indexing */
function zad_ac_author_indexable() {
	if ( ! is_author() || ! zad_ac_source_id() ) {
		return false;
	}
	$s = (array) get_option( ZAD_AC_AUTHOR_IDX, array() );
	return ! empty( $s[ get_queried_object_id() ] );
}
foreach ( array( 'wpseo_robots', 'wpseo_robots_array', 'wp_robots' ) as $zad_h ) {
	add_filter( $zad_h, function ( $r ) {
		if ( zad_suite_has( 'archive' ) || ! zad_ac_author_indexable() ) {
			return $r;
		}
		if ( is_array( $r ) ) {
			unset( $r['noindex'], $r['nofollow'] );
			$r['index']  = true;
			$r['follow'] = true;
			return $r;
		}
		$p = array_filter( array_map( 'trim', explode( ',', (string) $r ) ), function ( $x ) { return ! in_array( strtolower( $x ), array( 'noindex', 'nofollow' ), true ); } );
		return implode( ', ', array_unique( array_merge( array( 'index', 'follow' ), $p ) ) );
	}, 25 );
}

/* Hide the automatic list on a mapped archive */
function zad_ac_loop_hidden() {
	if ( zad_suite_has( 'archive' ) || ! zad_ac_source_id() ) {
		return false;
	}
	if ( is_post_type_archive() ) {
		$pt = zad_query_pt();
		$pt = is_array( $pt ) ? reset( $pt ) : $pt;
		$s  = (array) get_option( ZAD_AC_HIDE, array() );
		return ! empty( $s[ $pt ] );
	}
	if ( is_tax() || is_category() ) {
		$t = get_queried_object();
		$s = (array) get_option( 'zad_archive_content_term_hide_loop', array() );
		return $t instanceof WP_Term && ! empty( $s[ $t->taxonomy ][ $t->term_id ] );
	}
	if ( is_author() ) {
		$s = (array) get_option( 'zad_archive_content_author_hide_loop', array() );
		return ! empty( $s[ get_queried_object_id() ] );
	}
	return false;
}

/* Page as the body source of a single service ("pillar") */
add_action( 'add_meta_boxes', function () {
	if ( zad_suite_has( 'archive' ) ) {
		return;
	}
	foreach ( zad_service_types() as $pt ) {
		add_meta_box( 'zad-ac-source', 'مصدر محتوى الصفحة (مسودة)', 'zad_ac_source_box', $pt, 'side' );
	}
} );

function zad_ac_source_box( $post ) {
	$sel = absint( get_post_meta( $post->ID, ZAD_AC_SINGULAR, true ) );
	wp_nonce_field( 'zad_ac_singular', 'zad_ac_singular_nonce' );
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<p>اختر صفحة مسودة ليظهر محتواها داخل هذا الرابط. يبقى الرابط والعنوان والـSEO كما هي.</p><select name="zad_ac_singular" style="width:100%"><option value="0">— استخدام محتوى هذه الصفحة نفسها —</option>';
	foreach ( $pages as $p ) {
		echo '<option value="' . (int) $p->ID . '"' . selected( $sel, $p->ID, false ) . '>' . esc_html( $p->post_title . ' — ' . $p->post_status . ' — ID ' . $p->ID ) . '</option>';
	}
	echo '</select>';
	if ( $sel ) {
		echo '<p><a class="button" href="' . esc_url( get_edit_post_link( $sel ) ) . '">تحرير صفحة المحتوى</a></p>';
	}
}

add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['zad_ac_singular_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_ac_singular_nonce'] ) ), 'zad_ac_singular' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$src = isset( $_POST['zad_ac_singular'] ) ? absint( $_POST['zad_ac_singular'] ) : 0;
	$p   = $src ? get_post( $src ) : null;
	if ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status && $src !== (int) $id ) {
		update_post_meta( $id, ZAD_AC_SINGULAR, $src );
	} else {
		delete_post_meta( $id, ZAD_AC_SINGULAR );
	}
} );

add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || zad_suite_has( 'archive' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$id = get_queried_object_id();
	if ( ! $id || $id !== get_the_ID() ) {
		return $content;
	}
	$src = absint( get_post_meta( $id, ZAD_AC_SINGULAR, true ) );
	$p   = $src && $src !== $id ? get_post( $src ) : null;
	return ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status ) ? $p->post_content : $content;
}, 5 );

/* Settings page: Settings → محتوى الأرشيف */
add_action( 'admin_menu', function () {
	if ( zad_suite_has( 'archive' ) ) {
		return;
	}
	add_options_page( 'محتوى الأرشيف', 'محتوى الأرشيف (زاد)', 'manage_options', 'zad-archive-content', 'zad_ac_settings' );
} );

function zad_ac_pages_select( $name, $sel ) {
	static $pages = null;
	if ( null === $pages ) {
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	}
	echo '<select name="' . esc_attr( $name ) . '" style="max-width:100%"><option value="0">— لا شيء —</option>';
	foreach ( $pages as $p ) {
		echo '<option value="' . (int) $p->ID . '"' . selected( (int) $sel, $p->ID, false ) . '>' . esc_html( $p->post_title . ' (' . $p->post_status . ')' ) . '</option>';
	}
	echo '</select>';
}

function zad_ac_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$map  = (array) get_option( ZAD_AC_MAP, array() );
	$seo  = (array) get_option( ZAD_AC_SEO, array() );
	$hide = (array) get_option( ZAD_AC_HIDE, array() );
	$tmap = (array) get_option( ZAD_AC_TERM_MAP, array() );
	$tseo = (array) get_option( ZAD_AC_TERM_SEO, array() );
	$amap = (array) get_option( ZAD_AC_AUTHOR_MAP, array() );

	if ( isset( $_POST['zad_ac_save'] ) && check_admin_referer( 'zad_ac_save' ) ) {
		$p = wp_unslash( $_POST );
		foreach ( (array) ( $p['map'] ?? array() ) as $pt => $id ) {
			$pt = sanitize_key( $pt );
			if ( absint( $id ) ) { $map[ $pt ] = absint( $id ); } else { unset( $map[ $pt ] ); }
			$seo[ $pt ] = array( 'title' => sanitize_text_field( $p['seo'][ $pt ]['title'] ?? '' ), 'description' => sanitize_textarea_field( $p['seo'][ $pt ]['description'] ?? '' ) );
			if ( empty( $p['hide'][ $pt ] ) ) { unset( $hide[ $pt ] ); } else { $hide[ $pt ] = 1; }
		}
		foreach ( (array) ( $p['tmap'] ?? array() ) as $tx => $terms ) {
			$tx = sanitize_key( $tx );
			foreach ( (array) $terms as $tid => $id ) {
				$tid = absint( $tid );
				if ( absint( $id ) ) { $tmap[ $tx ][ $tid ] = absint( $id ); } else { unset( $tmap[ $tx ][ $tid ] ); }
				$tseo[ $tx ][ $tid ] = array( 'title' => sanitize_text_field( $p['tseo'][ $tx ][ $tid ]['title'] ?? '' ), 'description' => sanitize_textarea_field( $p['tseo'][ $tx ][ $tid ]['description'] ?? '' ) );
			}
		}
		foreach ( (array) ( $p['amap'] ?? array() ) as $uid => $id ) {
			$uid = absint( $uid );
			if ( absint( $id ) ) { $amap[ $uid ] = absint( $id ); } else { unset( $amap[ $uid ] ); }
		}
		update_option( ZAD_AC_MAP, $map );
		update_option( ZAD_AC_SEO, $seo );
		update_option( ZAD_AC_HIDE, $hide );
		update_option( ZAD_AC_TERM_MAP, $tmap );
		update_option( ZAD_AC_TERM_SEO, $tseo );
		update_option( ZAD_AC_AUTHOR_MAP, $amap );
		echo '<div class="updated"><p>تم الحفظ.</p></div>';
	}

	$types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $types['attachment'], $types['page'] );
	echo '<div class="wrap" dir="rtl"><h1>محتوى الأرشيف</h1><p>اربط صفحة (مسودة) بأي رابط أرشيف أو قسم فيظهر محتواها داخل الرابط الثابت نفسه. العنوان والـSEO يؤخذان من الصفحة ما لم تكتب قيماً هنا.</p><form method="post">';
	wp_nonce_field( 'zad_ac_save' );
	echo '<h2>أرشيفات الأنواع</h2><table class="widefat striped"><thead><tr><th>النوع</th><th>الصفحة</th><th>عنوان SEO</th><th>وصف SEO</th><th>إخفاء القائمة</th></tr></thead><tbody>';
	foreach ( $types as $pt => $o ) {
		if ( ! $o->has_archive ) { continue; }
		echo '<tr><td><strong>' . esc_html( $o->labels->name ) . '</strong><br><code>' . esc_html( $pt ) . '</code></td><td>';
		zad_ac_pages_select( 'map[' . $pt . ']', $map[ $pt ] ?? 0 );
		echo '</td><td><input type="text" class="regular-text" name="seo[' . esc_attr( $pt ) . '][title]" value="' . esc_attr( $seo[ $pt ]['title'] ?? '' ) . '"></td><td><textarea rows="2" name="seo[' . esc_attr( $pt ) . '][description]">' . esc_textarea( $seo[ $pt ]['description'] ?? '' ) . '</textarea></td><td><input type="checkbox" name="hide[' . esc_attr( $pt ) . ']" value="1"' . checked( ! empty( $hide[ $pt ] ), true, false ) . '></td></tr>';
	}
	echo '</tbody></table>';
	foreach ( zad_ac_taxes() as $tx => $o ) {
		$terms = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false ) );
		if ( ! $terms || is_wp_error( $terms ) ) { continue; }
		echo '<h2>' . esc_html( $o->labels->name ) . '</h2><table class="widefat striped"><thead><tr><th>القسم</th><th>الصفحة</th><th>عنوان SEO</th><th>وصف SEO</th></tr></thead><tbody>';
		foreach ( $terms as $t ) {
			echo '<tr><td>' . esc_html( $t->name ) . '</td><td>';
			zad_ac_pages_select( 'tmap[' . $tx . '][' . $t->term_id . ']', $tmap[ $tx ][ $t->term_id ] ?? 0 );
			echo '</td><td><input type="text" class="regular-text" name="tseo[' . esc_attr( $tx ) . '][' . (int) $t->term_id . '][title]" value="' . esc_attr( $tseo[ $tx ][ $t->term_id ]['title'] ?? '' ) . '"></td><td><textarea rows="2" name="tseo[' . esc_attr( $tx ) . '][' . (int) $t->term_id . '][description]">' . esc_textarea( $tseo[ $tx ][ $t->term_id ]['description'] ?? '' ) . '</textarea></td></tr>';
		}
		echo '</tbody></table>';
	}
	$users = get_users( array( 'has_published_posts' => true, 'fields' => array( 'ID', 'display_name' ) ) );
	if ( $users ) {
		echo '<h2>صفحات المؤلفين</h2><table class="widefat striped"><thead><tr><th>المؤلف</th><th>الصفحة</th></tr></thead><tbody>';
		foreach ( $users as $u ) {
			echo '<tr><td>' . esc_html( $u->display_name ) . '</td><td>';
			zad_ac_pages_select( 'amap[' . $u->ID . ']', $amap[ $u->ID ] ?? 0 );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p><button class="button button-primary" name="zad_ac_save" value="1">حفظ</button></p></form></div>';
}

/* =====================================================================
 * 2. BREADCRUMBS — path based trail, label/parent exceptions
 * ===================================================================== */

add_action( 'add_meta_boxes', function () {
	if ( zad_suite_has( 'bc' ) ) {
		return;
	}
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	foreach ( $types as $pt ) {
		add_meta_box( 'zad-site-breadcrumbs', 'مسار التنقل — زاد', function ( $post ) {
			wp_nonce_field( 'zad_site_bc_save_meta', 'zad_site_bc_nonce' );
			echo '<p><label><strong>الاسم المختصر</strong></label><input class="widefat" name="zad_breadcrumb_label" type="text" value="' . esc_attr( get_post_meta( $post->ID, ZAD_BC_LABEL, true ) ) . '" placeholder="مثال: حي المربع"></p>';
			echo '<p><label><strong>رابط الصفحة الأب</strong></label><input class="widefat" name="zad_breadcrumb_parent" type="text" value="' . esc_attr( get_post_meta( $post->ID, ZAD_BC_PARENT, true ) ) . '" placeholder="https://example.com/.../"></p>';
			echo '<p class="description">اترك الحقلين فارغين لاستخدام الشجرة التلقائية. استخدمهما للصفحات الجديدة أو الاستثناءات.</p>';
		}, $pt, 'side' );
	}
} );

add_action( 'save_post', function ( $id ) {
	if ( zad_suite_has( 'bc' ) || ! isset( $_POST['zad_site_bc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_site_bc_nonce'] ) ), 'zad_site_bc_save_meta' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$l = isset( $_POST['zad_breadcrumb_label'] ) ? sanitize_text_field( wp_unslash( $_POST['zad_breadcrumb_label'] ) ) : '';
	'' === $l ? delete_post_meta( $id, ZAD_BC_LABEL ) : update_post_meta( $id, ZAD_BC_LABEL, $l );
	$p = isset( $_POST['zad_breadcrumb_parent'] ) ? zad_bc_path( wp_unslash( $_POST['zad_breadcrumb_parent'] ) ) : '';
	'' === $p ? delete_post_meta( $id, ZAD_BC_PARENT ) : update_post_meta( $id, ZAD_BC_PARENT, $p );
} );

/** URL or path → "/a/b/" (site-relative). */
function zad_bc_path( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	$path = wp_parse_url( $url, PHP_URL_PATH );
	$base = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$path = '/' . trim( (string) $path, '/' );
	if ( $base && '/' !== $base && 0 === strpos( $path . '/', rtrim( $base, '/' ) . '/' ) ) {
		$path = substr( $path, strlen( rtrim( $base, '/' ) ) );
	}
	$path = '/' . trim( rawurldecode( $path ), '/' ) . '/';
	return '//' === $path ? '/' : $path;
}

/** Known section paths → short breadcrumb label (carried over from the Suite; filter 'zad_bc_hubs' to extend). */
function zad_bc_hubs() {
	return apply_filters( 'zad_bc_hubs', array(
		'/services/' => 'خدماتنا', '/cleaning/' => 'خدمات التنظيف', '/cleaning/riyadh/' => 'خدمات تنظيف بالرياض',
		'/cleaning/air-conditioning/' => 'تنظيف المكيفات', '/cleaning/sofa/' => 'تنظيف الكنب والمفروشات', '/cleaning/tanks/' => 'تنظيف الخزانات',
		'/pest-control/' => 'مكافحة الحشرات', '/guide/' => 'دليل التنظيف والصيانة', '/sections/' => 'قسم المدونة',
		'/pests-library/' => 'مكتبة الآفات', '/cleaning-sections/tanks/' => 'دليل تنظيف الخزانات',
		'/best-faqs/pest-control/' => 'أسئلة مكافحة الحشرات', '/best-faqs/tanks-cleaning/' => 'أسئلة تنظيف الخزانات',
		'/drain-cleaning/' => 'تسليك المجاري', '/cleaning/tile-polishing/' => 'جلي البلاط والرخام', '/faq/' => 'الأسئلة الشائعة',
	) );
}

/** Short label from a long SEO title: drop phone numbers and everything after "|", "–", "—", ":". */
function zad_bc_clean_label( $title ) {
	$t = wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
	$t = preg_replace( '/(?:\+?966)?0?5\d{8}/u', '', $t );
	$p = preg_split( '/\s*[|–—]\s*|:\s*/u', $t, 2 );
	$t = preg_replace( '/\s+/u', ' ', $p[0] ?? $t );
	return trim( $t, " \t\n\r\0\x0B-|" );
}

/** Resolve a site path to [label, url] when a live published post exists. */
function zad_bc_node( $path ) {
	static $memo = array();
	if ( isset( $memo[ $path ] ) ) {
		return $memo[ $path ];
	}
	$url = home_url( $path );
	$id  = url_to_postid( $url );
	$out = null;
	if ( $id && 'publish' === get_post_status( $id ) ) {
		$l   = trim( (string) get_post_meta( $id, ZAD_BC_LABEL, true ) );
		$hub = zad_bc_hubs();
		if ( '' === $l && isset( $hub[ $path ] ) ) {
			$l = $hub[ $path ];
		}
		$out = array( '' !== $l ? $l : zad_bc_clean_label( get_the_title( $id ) ), get_permalink( $id ), $id );
	}
	return $memo[ $path ] = $out;
}

/**
 * Site path of a post as it will be once published. Drafts/pending have an ugly permalink (?p=ID), which used to leave the trail
 * with only two levels; here the post is cloned as published (slug from the title when none yet) so WordPress builds the
 * pretty URL from post_parent and the type's rewrite base, exactly like the live page.
 */
function zad_bc_own_path( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) { return ''; }
	$url = get_permalink( $post );
	if ( 'publish' === $post->post_status && false === strpos( (string) $url, '?' ) ) { return zad_bc_path( $url ); }
	$c = clone $post;
	$c->post_status = 'publish';
	if ( '' === (string) $c->post_name ) { $c->post_name = sanitize_title( $c->post_title ); }
	if ( '' === (string) $c->post_name ) { $c->post_name = (string) $c->ID; }
	$pretty = get_permalink( $c );
	return ( $pretty && false === strpos( (string) $pretty, '?' ) ) ? zad_bc_path( $pretty ) : zad_bc_path( $url );
}

/** Path based trail for pages/posts of any type: Home > … ancestors … > current. */
function zad_path_crumbs( $post_id ) {
	$home  = array( 'الرئيسية', home_url( '/' ) );
	$label = trim( (string) get_post_meta( $post_id, ZAD_BC_LABEL, true ) );
	$cur   = array( '' !== $label ? $label : zad_bc_clean_label( get_the_title( $post_id ) ), '' );
	$trail = array();
	$own   = zad_bc_own_path( $post_id );
	$seen  = array( $own => 1 );
	$path  = zad_bc_path( (string) get_post_meta( $post_id, ZAD_BC_PARENT, true ) );
	$guard = 0;

	if ( '' === $path ) {
		$pp   = $own;
		$segs = array_values( array_filter( explode( '/', $pp ) ) );
		array_pop( $segs );
		$path = $segs ? '/' . implode( '/', $segs ) . '/' : '';
	}
	while ( '' !== $path && '/' !== $path && $guard++ < 8 && empty( $seen[ $path ] ) ) {
		$seen[ $path ] = 1;
		$node = zad_bc_node( $path );
		if ( $node ) {
			array_unshift( $trail, array( $node[0], $node[1] ) );
			$next = zad_bc_path( (string) get_post_meta( $node[2], ZAD_BC_PARENT, true ) );
		} else {
			// Archive of a post type or a taxonomy-less segment.
			$next = '';
			$seg  = trim( $path, '/' );
			$pto  = null;
			foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' ) as $o ) {
				$ar = get_post_type_archive_link( $o->name );
				if ( $ar && zad_bc_path( $ar ) === $path ) { $pto = $o; break; }
			}
			if ( $pto ) {
				$map = (array) get_option( ZAD_AC_MAP, array() );
				$hub = zad_bc_hubs();
				$ttl = isset( $hub[ $path ] ) ? $hub[ $path ] : ( ! empty( $map[ $pto->name ] ) ? zad_bc_clean_label( get_the_title( $map[ $pto->name ] ) ) : $pto->labels->name );
				array_unshift( $trail, array( $ttl, get_post_type_archive_link( $pto->name ) ) );
			}
		}
		if ( '' === $next ) {
			$segs = array_values( array_filter( explode( '/', $path ) ) );
			array_pop( $segs );
			$next = $segs ? '/' . implode( '/', $segs ) . '/' : '';
		}
		$path = $next;
	}
	return array_merge( array( $home ), $trail, array( $cur ) );
}

/* Use the path trail for pages and adopted/own content that has no richer trail */
add_filter( 'zad_current_crumbs', function ( $c ) {
	if ( zad_suite_has( 'bc' ) || is_front_page() || ! is_singular() ) {
		return $c;
	}
	$id = get_queried_object_id();
	if ( ! $id ) {
		return $c;
	}
	// Exception: manual parent set → always honour it.
	$manual = (string) get_post_meta( $id, ZAD_BC_PARENT, true );
	$pto    = get_post_type_object( get_post_type( $id ) );
	$simple = is_page() || ( is_singular() && ! zad_is_faq() && ! is_singular( 'post' ) && ! zad_is_service() && ! zad_is_article() )
		|| ( zad_is_service() && $pto && $pto->hierarchical ); // nested pillars: /cleaning/tanks/riyadh/
	if ( '' === $manual && ! $simple ) {
		return $c;
	}
	return zad_path_crumbs( $id );
} );

/* =====================================================================
 * 4. HTML SITEMAP shortcode [zad_site_map]
 * ===================================================================== */

add_action( 'init', function () {
	if ( zad_suite_has( 'map' ) || shortcode_exists( 'zad_site_map' ) ) {
		return;
	}
	add_shortcode( 'zad_site_map', function () {
		return zad_sitemap_html();
	} );
} );

/* =====================================================================
 * 5. PERFORMANCE
 * ===================================================================== */

add_action( 'wp_enqueue_scripts', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	wp_dequeue_style( 'dashicons' );
}, PHP_INT_MAX );

add_filter( 'style_loader_tag', function ( $html, $handle ) {
	return 0 === strpos( (string) $handle, 'trustindex-widget-css-' ) ? '' : $html;
}, PHP_INT_MAX, 2 );

add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( 'trustindex' !== $tag || false === strpos( $out, 'cdn.trustindex.io/assets/widget-presetted-css/' ) ) {
		return $out;
	}
	return (string) preg_replace_callback( '#<link\b(?=[^>]*\bhref=(["\'])https://cdn\.trustindex\.io/assets/widget-presetted-css/[^"\']+\1)[^>]*>#i', function ( $m ) {
		$d = preg_replace( '/\brel=(["\'])stylesheet\1/i', 'rel=$1preload$1 as=$1style$1', $m[0], 1 );
		if ( ! is_string( $d ) || $d === $m[0] ) { return $m[0]; }
		$d = preg_replace( '/\s*\/?\>$/', ' onload="this.onload=null;this.rel=\'stylesheet\'">', $d, 1 );
		return $d . '<noscript>' . $m[0] . '</noscript>';
	}, $out, 1 );
}, PHP_INT_MAX, 2 );

/* =====================================================================
 * 6. BREADCRUMB SCHEMA GUARD
 * The visible trail is ours, so BreadcrumbList must come from us too.
 * Schema Pro's copy is removed (it would disagree with the visible trail);
 * when the theme does not own the whole schema (mu-plugin zad-schema.php),
 * we print just the BreadcrumbList. Everything else is left untouched.
 * ===================================================================== */

function zad_strip_breadcrumb_jsonld( $html ) {
	if ( false === stripos( $html, 'BreadcrumbList' ) ) {
		return $html;
	}
	return preg_replace_callback( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>\s*#is', function ( $m ) {
		$d = json_decode( trim( $m[1] ), true );
		if ( ! is_array( $d ) ) {
			return $m[0];
		}
		$is_bc = function ( $n ) { return is_array( $n ) && isset( $n['@type'] ) && in_array( 'BreadcrumbList', (array) $n['@type'], true ); };
		if ( isset( $d['@graph'] ) && is_array( $d['@graph'] ) ) {
			$left = array_values( array_filter( $d['@graph'], function ( $n ) use ( $is_bc ) { return ! $is_bc( $n ); } ) );
			if ( count( $left ) === count( $d['@graph'] ) ) { return $m[0]; }
			if ( ! $left ) { return ''; }
			$d['@graph'] = $left;
			return '<script type="application/ld+json">' . wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n";
		}
		return $is_bc( $d ) ? '' : $m[0];
	}, $html );
}

add_action( 'wp', function () {
	if ( is_admin() || ! class_exists( 'BSF_AIOSRS_Pro_Markup' ) || ! method_exists( 'BSF_AIOSRS_Pro_Markup', 'get_instance' ) ) {
		return;
	}
	$inst = BSF_AIOSRS_Pro_Markup::get_instance();
	foreach ( array( 'wp_head', 'wp_footer' ) as $hook ) {
		foreach ( array( 'schema_markup', 'global_schemas_markup' ) as $method ) {
			if ( ! method_exists( $inst, $method ) ) { continue; }
			$prio = has_action( $hook, array( $inst, $method ) );
			if ( false === $prio ) { continue; }
			remove_action( $hook, array( $inst, $method ), $prio );
			add_action( $hook, function () use ( $inst, $method ) {
				ob_start();
				$inst->$method();
				$h = ob_get_clean();
				if ( zsc_is_active_request() ) {
					$h = zsc_filter_foreign_jsonld( $h ); // theme owns the graph: drop Schema Pro's duplicates
				} elseif ( function_exists( 'zad_sc_is_active_request' ) && zad_sc_is_active_request() && function_exists( 'zad_sc_filter_foreign_jsonld' ) ) {
					$h = zad_sc_filter_foreign_jsonld( $h ); // mu-plugin owns it: keep its own de-duplication
				}
				echo zad_strip_breadcrumb_jsonld( $h ); // phpcs:ignore WordPress.Security.EscapeOutput
			}, $prio );
		}
	}
}, 98 );

add_action( 'wp_head', function () {
	if ( is_admin() || is_feed() || is_front_page() || 'theme' === zad_schema_owner() || zad_suite_has( 'bc' ) ) {
		return; // owner=theme prints it inside its graph; the plugin rewrites its own
	}
	$crumbs = zad_current_crumbs();
	if ( count( $crumbs ) < 2 ) {
		return;
	}
	$items = array();
	foreach ( $crumbs as $i => $c ) {
		$it = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0] );
		$it['item'] = $c[1] ? $c[1] : ( function_exists( 'zad_current_url' ) ? zad_current_url() : '' );
		$items[] = $it;
	}
	echo '<script type="application/ld+json" class="zad-breadcrumb-schema">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 31 );
