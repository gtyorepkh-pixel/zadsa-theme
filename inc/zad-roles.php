<?php defined( 'ABSPATH' ) || exit;
/**
 * Role mapping ("adapter mode").
 *
 * The theme can run on a site that already has its own custom post types (e.g. /pest-control/, /cleaning/,
 * /faq/, /sections/, /guide/). Existing URLs are kept untouched; each type is assigned a design role:
 *   service  → full service page design (+ prices, FAQ, city pages…)
 *   faq      → single question design
 *   article  → blog article design
 * Types are matched by post-type key OR rewrite slug. When a role has no existing type, the theme registers
 * its own (zad_service / zad_faq).
 */

function zad_slug_list( $opt, $default ) {
	$out = array();
	foreach ( explode( ',', (string) zad_opt( $opt, $default ) ) as $s ) {
		$s = trim( str_replace( '_', '-', strtolower( $s ) ), " \t\n\r/" );
		if ( '' !== $s ) {
			$out[] = $s;
		}
	}
	return $out;
}

function zad_role_slugs() {
	return array(
		'service' => zad_slug_list( 'zad_service_slugs', 'pest-control,cleaning' ),
		'faq'     => zad_slug_list( 'zad_faq_slugs', 'faq' ),
		'article' => zad_slug_list( 'zad_article_slugs', 'sections,guide,pests-library' ),
	);
}

/** Non-native registered public post types mapped to a role (key => label). */
function zad_existing_types( $role ) {
	static $cache = array();
	$final = did_action( 'init' ) > 0 && ( doing_action( 'init' ) ? false : true );
	if ( $final && isset( $cache[ $role ] ) ) {
		return $cache[ $role ];
	}
	$want = zad_role_slugs();
	$want = isset( $want[ $role ] ) ? $want[ $role ] : array();
	$out  = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $k => $o ) {
		if ( in_array( $k, array( 'zad_service', 'zad_faq', 'zad_lead', 'post', 'page', 'attachment' ), true ) ) {
			continue;
		}
		$norm = str_replace( '_', '-', strtolower( $k ) );
		$slug = ( is_array( $o->rewrite ) && ! empty( $o->rewrite['slug'] ) ) ? str_replace( '_', '-', strtolower( trim( $o->rewrite['slug'], '/' ) ) ) : $norm;
		if ( in_array( $norm, $want, true ) || in_array( $slug, $want, true ) ) {
			$out[ $k ] = $o->labels->name;
		}
	}
	if ( $final ) {
		$cache[ $role ] = $out;
	}
	return $out;
}

function zad_service_types() {
	$t = array_keys( zad_existing_types( 'service' ) );
	if ( post_type_exists( 'zad_service' ) ) {
		$t[] = 'zad_service';
	}
	return $t ? $t : array( 'zad_service' );
}

function zad_faq_types() {
	$t = array_keys( zad_existing_types( 'faq' ) );
	if ( post_type_exists( 'zad_faq' ) ) {
		$t[] = 'zad_faq';
	}
	return $t ? $t : array( 'zad_faq' );
}

function zad_article_types() {
	$t = array_merge( array( 'post' ), array_keys( zad_existing_types( 'article' ) ) );
	return array_values( array_unique( $t ) );
}

function zad_is_service( $post = null ) {
	$pt = $post ? get_post_type( $post ) : get_post_type();
	return $post ? in_array( $pt, zad_service_types(), true ) : is_singular( zad_service_types() );
}
function zad_is_faq( $post = null ) {
	$pt = $post ? get_post_type( $post ) : get_post_type();
	return $post ? in_array( $pt, zad_faq_types(), true ) : is_singular( zad_faq_types() );
}
function zad_is_article() {
	return is_singular( zad_article_types() );
}
function zad_is_services_archive() {
	return is_post_type_archive( zad_service_types() );
}
function zad_is_faq_archive() {
	return is_post_type_archive( zad_faq_types() );
}

/** URL of "all services": native archive, a hub page, or the first service type archive. */
function zad_services_url() {
	if ( post_type_exists( 'zad_service' ) && ! zad_existing_types( 'service' ) ) {
		return get_post_type_archive_link( 'zad_service' );
	}
	$q = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'meta_key' => '_wp_page_template', 'meta_value' => 'temp/memo-services.php', 'fields' => 'ids' ) );
	if ( $q ) {
		return get_permalink( $q[0] );
	}
	foreach ( zad_service_types() as $t ) {
		$l = get_post_type_archive_link( $t );
		if ( $l ) {
			return $l;
		}
	}
	return home_url( '/' );
}
function zad_faq_url() {
	foreach ( zad_faq_types() as $t ) {
		$l = get_post_type_archive_link( $t );
		if ( $l ) {
			return $l;
		}
	}
	return home_url( '/' );
}

/** Rewrite slug (URL base) of a post type. */
function zad_type_base( $pt ) {
	$o = get_post_type_object( $pt );
	if ( $o && is_array( $o->rewrite ) && ! empty( $o->rewrite['slug'] ) ) {
		return trim( $o->rewrite['slug'], '/' );
	}
	return $pt;
}

/** Use our single/archive templates for adopted types. */
add_filter( 'template_include', function ( $tpl ) {
	if ( is_singular() ) {
		if ( zad_is_service() ) {
			$f = locate_template( 'single-zad_service.php' );
			return $f ? $f : $tpl;
		}
		if ( zad_is_faq() ) {
			$f = locate_template( 'single-zad_faq.php' );
			return $f ? $f : $tpl;
		}
	}
	if ( is_post_type_archive() ) {
		$ex = array_diff( zad_article_types(), array( 'post' ) );
		if ( $ex && is_post_type_archive( $ex ) ) {
			$f = locate_template( 'archive-hub-articles.php' );
			return $f ? $f : $tpl;
		}
		if ( zad_is_services_archive() ) {
			$f = locate_template( 'archive-zad_service.php' );
			return $f ? $f : $tpl;
		}
		if ( zad_is_faq_archive() ) {
			$f = locate_template( 'archive-zad_faq.php' );
			return $f ? $f : $tpl;
		}
	}
	return $tpl;
}, 20 );

/**
 * Old posts keep their data in the framework metabox (_memo_metabox_options). Map it onto the new fields
 * when the new field is empty, so adopted content looks right without re-entering anything.
 */
add_filter( 'get_post_metadata', function ( $v, $id, $key, $single ) {
	static $busy = false;
	if ( null !== $v || $busy || ! is_string( $key ) ) {
		return $v;
	}
	$map = array( '_zad_tagline', '_zad_phone', '_zad_whatsapp', '_zad_video', '_thumbnail_id' );
	if ( ! in_array( $key, $map, true ) ) {
		return $v;
	}
	$pt = get_post_type( $id );
	if ( ! $pt || 'zad_service' === $pt || ! in_array( $pt, array_merge( zad_service_types(), zad_article_types() ), true ) ) {
		return $v;
	}
	$busy = true;
	$real = get_post_meta( $id, $key, true );
	$o    = get_post_meta( $id, '_memo_metabox_options', true );
	$busy = false;
	if ( ( '' !== $real && array() !== $real && false !== $real ) || ! is_array( $o ) ) {
		return $v;
	}
	$val = '';
	switch ( $key ) {
		case '_zad_tagline':
			$val = wp_trim_words( wp_strip_all_tags( (string) ( $o['memo_single_desc'] ?? '' ) ), 26, '…' );
			break;
		case '_zad_phone':
			$val = trim( (string) ( $o['memo_single_phone'] ?? '' ) );
			break;
		case '_zad_whatsapp':
			$val = trim( (string) ( $o['memo_single_whatss'] ?? '' ) );
			break;
		case '_zad_video':
			$val = (string) ( $o['memo_single_video']['url'] ?? '' );
			break;
		case '_thumbnail_id':
			$val = (int) ( $o['memo_single_img']['id'] ?? 0 );
			break;
	}
	if ( '' === $val || array() === $val || 0 === $val ) {
		return $v;
	}
	return array( $val );
}, 10, 4 );

/* ------------------------------------------------------------------ */
/* Admin: "Adopt existing content"                                     */
/* ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_management_page( 'تبنّي المحتوى الحالي', 'تبنّي المحتوى الحالي', 'manage_options', 'zad-adopt', 'zad_adopt_page' );
} );

function zad_adopt_page() {
	echo '<div class="wrap"><h1>تبنّي المحتوى الحالي</h1>';
	if ( isset( $_POST['zad_adopt_go'] ) && check_admin_referer( 'zad_adopt' ) && current_user_can( 'manage_options' ) ) {
		$res = zad_adopt_run( isset( $_POST['zad_cities'] ) ? sanitize_textarea_field( wp_unslash( $_POST['zad_cities'] ) ) : '' );
		flush_rewrite_rules( false );
		echo '<div class="notice notice-success"><p>تم: تصنيف ' . (int) $res['cats'] . ' خدمة بحسب نوعها، وربط ' . (int) $res['areas'] . ' خدمة بالمدن. الروابط لم تتغير.</p></div>';
	}
	$rows = array( 'service' => 'خدمات (تصميم الخدمة)', 'faq' => 'أسئلة (تصميم السؤال)', 'article' => 'مقالات (تصميم المقال)' );
	$mu = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
	$muf = array_filter( array( 'zad-schema.php', 'zad-cleanup.php', 'zad-core-cpt.php' ), function ( $f ) use ( $mu ) { return file_exists( $mu . '/' . $f ); } );
	if ( $muf ) {
		echo '<div class="notice notice-info inline"><p><strong>ملفات mu-plugins مكتشفة:</strong> <code>' . esc_html( implode( '، ', $muf ) ) . '</code>. سكيما الثيم: <strong>' . ( 'theme' === zad_schema_owner() ? 'مفعّلة' : 'متنحّية للإضافة القديمة (لا تكرار)' ) . '</strong>. للتحكم: إعدادات القالب ← الهوية والألوان ← «سكيما وSEO الثيم».</p></div>';
	}
	global $zad_legacy_registered;
	if ( ! empty( $zad_legacy_registered ) ) {
		echo '<div class="notice notice-warning inline"><p><strong>شبكة الأمان فعّالة:</strong> سجّل القالب تلقائياً هذه الأنواع لأنها غير مسجّلة من أي مصدر آخر: <code>' . esc_html( implode( '، ', $zad_legacy_registered ) ) . '</code>. هذا يعني أن الثيم القديم أو كوداً قديماً كان يسجّلها. حافظ على نسخة احتياطية قبل أي تعديل آخر.</p></div>';
	}
	$orph = function_exists( 'zad_db_orphan_types' ) ? zad_db_orphan_types() : array();
	if ( $orph ) {
		echo '<div class="notice notice-info inline"><p>أنواع موجودة في قاعدة البيانات وغير مسجّلة (لم تُطابق أي رابط مضبوط): <code>' . esc_html( implode( '، ', $orph ) ) . '</code>. إن كانت من أنواعك، أضف روابطها في إعدادات القالب ← الهوية والألوان.</p></div>';
	}
	echo '<p>القالب يطبّق التصميم الجديد على الأنواع الموجودة في موقعك <strong>دون تغيير أي رابط</strong>. يتم الربط بحسب رابط كل نوع، وتعدّله من إعدادات القالب ← الهوية والألوان.</p>';
	echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>الدور</th><th>الأنواع المكتشفة</th><th>الروابط</th><th>عدد المنشورات</th></tr></thead><tbody>';
	foreach ( $rows as $role => $label ) {
		$types = 'article' === $role ? zad_existing_types( 'article' ) : zad_existing_types( $role );
		if ( ! $types ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td colspan="3">— لا يوجد نوع قائم (سيُستخدم نوع القالب الخاص به إن لزم)</td></tr>';
			continue;
		}
		foreach ( $types as $k => $name ) {
			$cnt = wp_count_posts( $k );
			$link = get_post_type_archive_link( $k );
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( $name ) . ' <code>' . esc_html( $k ) . '</code></td><td>' . ( $link ? '<a href="' . esc_url( $link ) . '" target="_blank">' . esc_html( $link ) . '</a>' : '—' ) . '</td><td>' . (int) ( $cnt->publish ?? 0 ) . '</td></tr>';
		}
	}
	echo '</tbody></table>';
	$ar = zad_area_page_ids();
	echo '<h2>صفحات الأحياء المكتشفة (تُستثنى من القوائم)</h2>';
	if ( $ar ) {
		echo '<p>' . count( $ar ) . ' صفحة. أول عشر صفحات للتأكد:</p><ul style="list-style:disc;padding-inline-start:20px">';
		foreach ( array_slice( $ar, 0, 10 ) as $i ) { echo '<li><a href="' . esc_url( get_edit_post_link( $i ) ) . '">' . esc_html( get_the_title( $i ) ) . '</a> <code>' . esc_html( urldecode( get_post_field( 'post_name', $i ) ) ) . '</code></li>'; }
		echo '</ul>';
	} else {
		echo '<p>لم تُكتشف صفحات أحياء. إن كانت لديك، عدّل «كلمات تدل على صفحات الأحياء» في إعدادات القالب، أو حدّد الصفحة يدوياً من شاشة تحريرها (صندوق «نوع الصفحة»).</p>';
	}
	echo '<form method="post" style="margin-top:20px">';
	wp_nonce_field( 'zad_adopt' );
	echo '<h2>الخطوة التالية</h2><p>يضيف تصنيف «القسم» لكل خدمة (باسم نوعها: مكافحة، تنظيف، نقل…)، ويربط الخدمات التي بلا مدن بالمدن التالية لتظهر صفحات «خدمة + مدينة» والمناطق. لا يحذف شيئاً ولا يغيّر الروابط.</p>';
	echo '<p><label><strong>المدن (مدينة في كل سطر)</strong><br><textarea name="zad_cities" rows="4" cols="40" placeholder="الرياض&#10;جدة"></textarea></label></p>';
	echo '<p><button class="button button-primary" name="zad_adopt_go" value="1">تنفيذ</button></p></form></div>';
}

function zad_adopt_run( $cities_text ) {
	$res = array( 'cats' => 0, 'areas' => 0 );
	$city_ids = array();
	foreach ( zad_lines( $cities_text ) as $c ) {
		$t = term_exists( $c, 'service_area' );
		if ( ! $t ) { $t = wp_insert_term( $c, 'service_area' ); }
		if ( ! is_wp_error( $t ) ) { $city_ids[] = (int) ( is_array( $t ) ? $t['term_id'] : $t ); }
	}
	$ids = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		if ( ! has_term( '', 'service_cat', $id ) ) {
			zad_type_category( $id );
			$res['cats']++;
		}
		if ( $city_ids && ! has_term( '', 'service_area', $id ) ) {
			wp_set_object_terms( $id, $city_ids, 'service_area' );
			$res['areas']++;
		}
	}
	return $res;
}

/* ------------------------------------------------------------------ */
/* Main services vs neighbourhood (district) pages                      */
/* ------------------------------------------------------------------ */

/** True when a service-type post is a neighbourhood page (excluded from lists). Override: meta _zad_page_kind = main|area. */
function zad_is_area_page( $id ) {
	$kind = get_post_meta( $id, '_zad_page_kind', true );
	if ( 'area' === $kind ) {
		return true;
	}
	if ( 'main' === $kind ) {
		return false;
	}
	$p = get_post( $id );
	if ( ! $p ) {
		return false;
	}
	if ( zad_opt( 'zad_area_children', true ) && $p->post_parent && is_post_type_hierarchical( $p->post_type ) ) {
		return true;
	}
	$title = zad_ar_norm( $p->post_title );
	$slug  = strtolower( urldecode( $p->post_name ) );
	foreach ( explode( ',', (string) zad_opt( 'zad_area_keywords', 'حي,hay-,district,neighborhood' ) ) as $kw ) {
		$kw = trim( $kw );
		if ( '' === $kw ) {
			continue;
		}
		if ( preg_match( '/[a-z]/i', $kw ) ) {
			if ( false !== strpos( $slug, strtolower( $kw ) ) ) {
				return true;
			}
		} elseif ( preg_match( '/(^|\s)' . preg_quote( zad_ar_norm( $kw ), '/' ) . '(\s|$)/u', $title ) ) {
			return true;
		}
	}
	return false;
}

/** IDs of neighbourhood pages (cached). */
function zad_area_page_ids() {
	$ids = get_transient( 'zad_area_ids' );
	if ( false !== $ids ) {
		return $ids;
	}
	$ids = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'zad_all' => true ) ) as $id ) {
		if ( zad_is_area_page( $id ) ) {
			$ids[] = (int) $id;
		}
	}
	set_transient( 'zad_area_ids', $ids, 12 * HOUR_IN_SECONDS );
	return $ids;
}
add_action( 'save_post', function () { delete_transient( 'zad_area_ids' ); } );
add_action( 'deleted_post', function () { delete_transient( 'zad_area_ids' ); } );
add_action( 'update_option__memo_theme_options', function () { delete_transient( 'zad_area_ids' ); } );

/** Hide neighbourhood pages from every front-end listing of services (unless the query sets zad_all). */
add_action( 'pre_get_posts', function ( $q ) {
	static $busy = false;
	if ( $busy || is_admin() || $q->get( 'zad_all' ) || $q->is_singular() || $q->get( 'p' ) || $q->get( 'name' ) || $q->get( 'pagename' ) ) {
		return;
	}
	if ( $q->is_main_query() && ! ( $q->is_post_type_archive() || $q->is_tax( array( 'service_cat', 'service_area' ) ) || $q->is_search() ) ) {
		return;
	}
	$pt  = $q->get( 'post_type' );
	$svc = zad_service_types();
	$pts = is_array( $pt ) ? $pt : ( $pt ? array( $pt ) : array() );
	if ( ! $pts || array_diff( $pts, $svc ) ) {
		return;
	}
	$busy = true;
	$ex   = zad_area_page_ids();
	$busy = false;
	if ( $ex ) {
		$q->set( 'post__not_in', array_unique( array_merge( (array) $q->get( 'post__not_in' ), $ex ) ) );
	}
}, 30 );

/* Page kind meta box (service types) */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_page_kind', 'نوع الصفحة (رئيسية / حي)', function ( $post ) {
		wp_nonce_field( 'zad_page_kind', 'zad_pk_nonce' );
		$v = get_post_meta( $post->ID, '_zad_page_kind', true );
		echo '<select name="zad_page_kind" style="width:100%"><option value="">تلقائي (' . ( zad_is_area_page( $post->ID ) ? 'حي' : 'رئيسية' ) . ')</option><option value="main"' . selected( $v, 'main', false ) . '>خدمة رئيسية — تظهر في القوائم</option><option value="area"' . selected( $v, 'area', false ) . '>صفحة حي — لا تظهر في القوائم</option></select>';
	}, zad_service_types(), 'side', 'default' );
} );
add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['zad_pk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_pk_nonce'] ) ), 'zad_page_kind' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$v = isset( $_POST['zad_page_kind'] ) ? sanitize_key( wp_unslash( $_POST['zad_page_kind'] ) ) : '';
	update_post_meta( $id, '_zad_page_kind', in_array( $v, array( 'main', 'area' ), true ) ? $v : '' );
} );
