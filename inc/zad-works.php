<?php defined( 'ABSPATH' ) || exit;
/**
 * «أعمالنا» — one page per real job (post type zad_work): a video split into clips, before/after photos, the case in short lines,
 * complaint / inspection / result, tools, questions. Schema lives in inc/zad-sc.php (zsc_work_nodes), the old-pages tool in inc/zad-works-migrate.php.
 * Meta keys (all _zad_wk_*): service · date · facts · complaint · inspection · result · tools · ba · gallery · video_url · video_yt · poster · duration · upload · clips · faq
 */

const ZAD_WK = 'zad_work';

function zad_works_slug() { return zad_slug( 'zad_works_slug', 'works' ); }

function zad_works_url() { return (string) get_post_type_archive_link( ZAD_WK ); }

/* ------------------------------------------------------------------ */
/* Post type                                                            */
/* ------------------------------------------------------------------ */

add_action( 'init', function () {
	register_post_type( ZAD_WK, array(
		'labels'       => array( 'name' => 'أعمالنا', 'singular_name' => 'عمل', 'add_new' => 'إضافة عمل', 'add_new_item' => 'إضافة عمل جديد', 'edit_item' => 'تعديل العمل', 'new_item' => 'عمل جديد', 'view_item' => 'عرض العمل', 'search_items' => 'بحث في الأعمال', 'not_found' => 'لا توجد أعمال', 'all_items' => 'كل الأعمال', 'menu_name' => 'أعمالنا' ),
		'public'       => true,
		'has_archive'  => true,
		'rewrite'      => array( 'slug' => zad_works_slug(), 'with_front' => false ),
		'menu_icon'    => 'dashicons-format-video',
		'menu_position'=> 8,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
	) );
}, 50 );

/** The service type and area taxonomies (registered by zad-cpt.php) also classify works. */
add_action( 'init', function () {
	foreach ( array( 'service_cat', 'service_area' ) as $tx ) { if ( taxonomy_exists( $tx ) ) { register_taxonomy_for_object_type( $tx, ZAD_WK ); } }
}, 60 );

/* ------------------------------------------------------------------ */
/* Parsers (the stored meta stays plain text)                           */
/* ------------------------------------------------------------------ */

/** «90», «1:30», «01:02:03» (also Arabic-Indic digits) → seconds, or null. */
function zad_wk_time( $v ) {
	$v = trim( strtr( (string) $v, array( '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => '' ) ) );
	if ( preg_match( '/^\d+$/', $v ) ) { return (int) $v; }
	if ( preg_match( '/^(?:(\d+):)?(\d{1,2}):(\d{1,2})$/', $v, $m ) ) { return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3]; }
	return null;
}

function zad_wk_clock( $s ) { $s = max( 0, (int) $s ); return ( $s >= 3600 ? floor( $s / 3600 ) . ':' . sprintf( '%02d', floor( $s % 3600 / 60 ) ) : floor( $s / 60 ) ) . ':' . sprintf( '%02d', $s % 60 ); }

/** «العنوان | القيمة» lines → array( array( label, value ), … ) */
function zad_wk_pairs( $text ) {
	$out = array();
	foreach ( zad_lines( $text ) as $l ) { $c = array_map( 'trim', explode( '|', $l, 2 ) ); if ( '' !== $c[0] ) { $out[] = array( $c[0], $c[1] ?? '' ); } }
	return $out;
}

/** «البداية | النهاية | الاسم» → sorted array( array( start, end, name ), … ); invalid lines (no name, end <= start) are dropped. */
function zad_wk_clips( $id ) {
	$out = array();
	foreach ( zad_lines( get_post_meta( $id, '_zad_wk_clips', true ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l, 3 ) );
		if ( 3 !== count( $c ) || '' === $c[2] ) { continue; }
		$s = zad_wk_time( $c[0] ); $e = zad_wk_time( $c[1] );
		if ( null === $s || null === $e || $e <= $s ) { continue; }
		$out[] = array( $s, $e, $c[2] );
	}
	usort( $out, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	return array_slice( $out, 0, 30 );
}

/** One question per block (blocks separated by an empty line): first line = question, the following lines = answer. */
function zad_wk_faq( $id ) {
	$out = array();
	foreach ( preg_split( '/\R\s*\R/u', trim( (string) get_post_meta( $id, '_zad_wk_faq', true ) ) ) as $b ) {
		$ls = zad_lines( $b );
		if ( count( $ls ) >= 2 ) { $out[] = array( 'q' => array_shift( $ls ), 'a' => implode( ' ', $ls ) ); }
	}
	return $out;
}

function zad_wk_ids( $id, $key ) { return array_values( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, $key, true ) ) ) ) ); }

/** Video of a work: array( kind file|yt|'', src, yt, embed, poster, duration, upload (ISO) ) */
function zad_wk_video( $id ) {
	$file = trim( (string) get_post_meta( $id, '_zad_wk_video_url', true ) );
	$yt   = zad_youtube_id( (string) get_post_meta( $id, '_zad_wk_video_yt', true ) );
	$kind = $file ? 'file' : ( $yt ? 'yt' : '' );
	$pid  = (int) get_post_meta( $id, '_zad_wk_poster', true );
	$poster = $pid ? (string) wp_get_attachment_image_url( $pid, 'large' ) : '';
	if ( '' === $poster && has_post_thumbnail( $id ) ) { $poster = (string) get_the_post_thumbnail_url( $id, 'large' ); }
	if ( '' === $poster && 'yt' === $kind ) { $poster = 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg'; }
	$up = trim( (string) get_post_meta( $id, '_zad_wk_upload', true ) ); $iso = '';
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $up ) ) { try { $iso = ( new DateTime( $up . ' 00:00:00', wp_timezone() ) )->format( 'c' ); } catch ( \Throwable $e ) {} }
	return array( 'kind' => $kind, 'src' => $file, 'yt' => $yt, 'embed' => $yt ? 'https://www.youtube-nocookie.com/embed/' . $yt : '', 'poster' => $poster, 'poster_id' => $pid, 'duration' => absint( get_post_meta( $id, '_zad_wk_duration', true ) ), 'upload' => $up, 'upload_iso' => $iso );
}

/** Linked service (published, a service type) → id or 0. */
function zad_wk_service( $id ) {
	$s = (int) get_post_meta( $id, '_zad_wk_service', true );
	return ( $s && 'publish' === get_post_status( $s ) && in_array( get_post_type( $s ), zad_service_types(), true ) ) ? $s : 0;
}

/** array( cat WP_Term|null, city name, hood name ): the work's own service_cat / service_area terms, else the linked service's; the hood falls back to the «الحي» line. */
function zad_wk_terms( $id ) {
	$svc = zad_wk_service( $id ); $cat = null; $city = ''; $hood = '';
	foreach ( array( $id, $svc ) as $src ) {
		if ( ! $src ) { continue; }
		if ( ! $cat ) { $t = get_the_terms( $src, 'service_cat' ); if ( $t && ! is_wp_error( $t ) ) { $cat = $t[0]; } }
		$ar = get_the_terms( $src, 'service_area' );
		if ( $ar && ! is_wp_error( $ar ) && '' === $city ) {
			foreach ( $ar as $t ) { if ( 0 === (int) $t->parent ) { $city = $t->name; } elseif ( '' === $hood ) { $hood = $t->name; $p = get_term( (int) $t->parent, 'service_area' ); if ( $p && ! is_wp_error( $p ) && '' === $city ) { $city = $p->name; } } }
		}
	}
	if ( '' === $hood ) { foreach ( zad_wk_pairs( get_post_meta( $id, '_zad_wk_facts', true ) ) as $f ) { if ( 0 === mb_strpos( $f[0], 'الحي' ) && '' !== $f[1] ) { $hood = $f[1]; break; } } }
	return array( $cat, $city, $hood );
}

function zad_wk_place( $id ) { list( , $city, $hood ) = zad_wk_terms( $id ); return trim( ( $hood ? $hood : '' ) . ( $hood && $city ? '، ' : '' ) . $city ); }

/** The image of a work: featured image, else the poster, else the «after» photo, else the first gallery photo (attachment id, 0 = none). */
function zad_wk_image_id( $id ) {
	if ( has_post_thumbnail( $id ) ) { return (int) get_post_thumbnail_id( $id ); }
	$v = zad_wk_video( $id ); if ( $v['poster_id'] ) { return (int) $v['poster_id']; }
	$ba = zad_wk_ids( $id, '_zad_wk_ba' ); if ( count( $ba ) >= 2 ) { return $ba[1]; }
	$g = zad_wk_ids( $id, '_zad_wk_gallery' ); return $g ? $g[0] : 0;
}

/* ------------------------------------------------------------------ */
/* Queries                                                              */
/* ------------------------------------------------------------------ */

function zad_works_for_service( $service_id, $limit = 3 ) {
	static $memo = array();
	$k = $service_id . '_' . $limit;
	if ( ! isset( $memo[ $k ] ) ) {
		$memo[ $k ] = get_posts( array( 'post_type' => ZAD_WK, 'post_status' => 'publish', 'numberposts' => $limit, 'meta_key' => '_zad_wk_service', 'meta_value' => (int) $service_id, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => true ) );
	}
	return $memo[ $k ];
}

/** Similar works: 3 of the same service; if none, of the same service type (service_cat). */
function zad_works_similar( $id, $limit = 3 ) {
	$svc = zad_wk_service( $id ); $out = array();
	if ( $svc ) { $out = array_values( array_filter( zad_works_for_service( $svc, $limit + 1 ), function ( $p ) use ( $id ) { return (int) $p->ID !== (int) $id; } ) ); }
	if ( ! $out ) {
		list( $cat ) = zad_wk_terms( $id );
		if ( $cat ) { $out = get_posts( array( 'post_type' => ZAD_WK, 'post_status' => 'publish', 'numberposts' => $limit, 'post__not_in' => array( $id ), 'suppress_filters' => true, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => array( $cat->term_id ) ) ) ) ); }
		if ( ! $out && $svc ) { // the type of the linked service
			$t = get_the_terms( $svc, 'service_cat' );
			if ( $t && ! is_wp_error( $t ) ) { $ids = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'zad_all' => true, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => array( $t[0]->term_id ) ) ) ) ); if ( $ids ) { $out = get_posts( array( 'post_type' => ZAD_WK, 'post_status' => 'publish', 'numberposts' => $limit, 'post__not_in' => array( $id ), 'suppress_filters' => true, 'meta_query' => array( array( 'key' => '_zad_wk_service', 'value' => $ids, 'compare' => 'IN' ) ) ) ); } }
		}
	}
	return array_slice( $out, 0, $limit );
}

/** The «أعمال من الميدان» section of a service page: up to 3 linked works; '' when there are none. */
function zad_works_section_html( $service_id ) {
	$ws = zad_works_for_service( $service_id, 3 );
	if ( ! $ws ) { return ''; }
	ob_start();
	echo '<section class="sec sec--tint zw-field"><div class="wrap"><header class="sec__head"><span class="eyebrow">أعمالنا</span><h2>أعمال من الميدان</h2></header><div class="sgrid">';
	foreach ( $ws as $w ) { $GLOBALS['post'] = $w; setup_postdata( $w ); get_template_part( 'template-parts/work-card' ); }
	wp_reset_postdata();
	echo '</div>';
	if ( zad_works_url() ) { echo '<p class="sec__more"><a class="btn btn--ghost-dark" href="' . esc_url( zad_works_url() ) . '">كل أعمالنا</a></p>'; }
	echo '</div></section>';
	return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Front-end assets (this type's pages, and a service page that shows works)  */
/* ------------------------------------------------------------------ */

add_action( 'wp_enqueue_scripts', function () {
	$on = is_singular( ZAD_WK ) || is_post_type_archive( ZAD_WK ) || ( is_singular() && zad_is_service() && zad_works_for_service( get_queried_object_id(), 3 ) );
	if ( ! $on ) { return; }
	wp_enqueue_style( 'zad-work', get_template_directory_uri() . '/assets/css/zad-work.css', array( 'zad-main' ), zad_asset_ver( 'assets/css/zad-work.css' ) );
	if ( is_singular( ZAD_WK ) ) { wp_enqueue_script( 'zad-work', get_template_directory_uri() . '/assets/js/zad-work.js', array(), zad_asset_ver( 'assets/js/zad-work.js' ), true ); }
}, 30 );

/** Breadcrumb: الرئيسية › أعمالنا › العنوان. */
add_filter( 'zad_current_crumbs', function ( $c ) {
	$h = array( 'الرئيسية', home_url( '/' ) );
	if ( is_singular( ZAD_WK ) ) { return array( $h, array( 'أعمالنا', zad_works_url() ), array( get_the_title(), '' ) ); }
	if ( is_post_type_archive( ZAD_WK ) ) { return array( $h, array( 'أعمالنا', '' ) ); }
	return $c;
} );

/** Archive: 12 per page, optional filter ?svc=<service_cat slug>. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( ZAD_WK ) ) { return; }
	$q->set( 'posts_per_page', 12 );
	$svc = isset( $_GET['svc'] ) ? sanitize_title( wp_unslash( $_GET['svc'] ) ) : ''; // phpcs:ignore
	if ( '' !== $svc ) { $q->set( 'tax_query', array( array( 'taxonomy' => 'service_cat', 'field' => 'slug', 'terms' => array( $svc ) ) ) ); }
} );

/* ------------------------------------------------------------------ */
/* Admin: meta box                                                      */
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes_' . ZAD_WK, function () {
	add_meta_box( 'zad_wk_box', 'تفاصيل العمل', 'zad_wk_box', ZAD_WK, 'normal', 'high' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && ZAD_WK === get_post_type() ) {
		wp_enqueue_media();
		wp_enqueue_script( 'zad-admin', get_template_directory_uri() . '/assets/js/admin.js', array(), zad_asset_ver( 'assets/js/admin.js' ), true );
	}
} );

function zad_wk_media_field( $key, $val, $label, $opts = '' ) {
	echo '<p><label><b>' . esc_html( $label ) . '</b></label><br><input type="hidden" class="zad-media-val" id="' . esc_attr( $key ) . '" name="zad_wk[' . esc_attr( str_replace( 'zad_wk_', '', $key ) ) . ']" value="' . esc_attr( $val ) . '"><button type="button" class="button zad-media-btn" data-target="' . esc_attr( $key ) . '"' . $opts . '>اختيار</button> <span id="' . esc_attr( $key ) . '_prev">';
	foreach ( array_filter( array_map( 'intval', explode( ',', (string) $val ) ) ) as $aid ) { echo wp_get_attachment_image( $aid, array( 60, 60 ) ); }
	echo '</span></p>';
}

function zad_wk_box( $post ) {
	wp_nonce_field( 'zad_wk_save', 'zad_wk_nonce' );
	$g   = function ( $k ) use ( $post ) { return (string) get_post_meta( $post->ID, '_zad_wk_' . $k, true ); };
	$svc = (int) $g( 'service' );
	$ta  = function ( $k, $label, $rows, $ph = '' ) use ( $g ) { echo '<p><label><b>' . esc_html( $label ) . '</b><br><textarea name="zad_wk[' . esc_attr( $k ) . ']" rows="' . (int) $rows . '" style="width:100%" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( $g( $k ) ) . '</textarea></label></p>'; };
	echo '<div class="zad-wk">';
	echo '<p><label><b>الخدمة المرتبطة</b><br><select name="zad_wk[service]" style="width:100%"><option value="0">— بدون —</option>';
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'zad_all' => true, 'suppress_filters' => true ) ) as $s ) { echo '<option value="' . (int) $s->ID . '"' . selected( $svc, $s->ID, false ) . '>' . esc_html( $s->post_title ) . '</option>'; }
	echo '</select></label></p>';
	echo '<p><label><b>تاريخ التنفيذ</b><br><input type="date" name="zad_wk[date]" value="' . esc_attr( $g( 'date' ) ) . '"></label></p>';
	$ta( 'facts', 'الحالة في سطور (سطر لكل بند: العنوان | القيمة)', 6, "الحي | النخيل\nنوع المبنى | فيلا\nالمشكلة | تسريب تحت البلاط\nالمدة | يوم واحد\nالنتيجة | إصلاح كامل" );
	$ta( 'complaint', 'الشكوى', 4 ); $ta( 'inspection', 'الفحص', 4 ); $ta( 'result', 'النتيجة', 4 );
	$ta( 'tools', 'الأدوات المستعملة (سطر لكل أداة: الأداة | لماذا)', 4, 'جهاز كشف التسرب الصوتي | لتحديد مكان التسرب بدون كسر' );
	zad_wk_media_field( 'zad_wk_ba', $g( 'ba' ), 'صور قبل/بعد (اختر الصور بالترتيب: قبل، بعد، قبل، بعد…)' );
	zad_wk_media_field( 'zad_wk_gallery', $g( 'gallery' ), 'معرض الصور' );
	echo '<fieldset style="border:1px solid #dcdcde;padding:8px 12px;margin:8px 0"><legend><b>الفيديو</b></legend>';
	echo '<p><label>ملف فيديو (mp4) من الوسائط أو رابط مباشر<br><input type="url" id="zad_wk_video_url" name="zad_wk[video_url]" value="' . esc_attr( $g( 'video_url' ) ) . '" dir="ltr" style="width:80%" placeholder="https://…/video.mp4"></label> <button type="button" class="button zad-media-btn" data-target="zad_wk_video_url" data-mtype="video" data-fill="url" data-single="1">من الوسائط</button></p>';
	echo '<p><label>أو رابط تضمين يوتيوب (يُستعمل إن لم يوجد ملف)<br><input type="url" name="zad_wk[video_yt]" value="' . esc_attr( $g( 'video_yt' ) ) . '" dir="ltr" style="width:100%" placeholder="https://www.youtube.com/watch?v=…"></label></p>';
	zad_wk_media_field( 'zad_wk_poster', $g( 'poster' ), 'صورة الغلاف (poster)', ' data-single="1"' );
	echo '<p><label>المدة بالثواني <input type="number" min="0" name="zad_wk[duration]" value="' . esc_attr( $g( 'duration' ) ) . '" style="width:110px"></label> &nbsp; <label>تاريخ الرفع <input type="date" name="zad_wk[upload]" value="' . esc_attr( $g( 'upload' ) ) . '"></label></p>';
	$ta( 'clips', 'المقاطع (سطر لكل مقطع: البداية | النهاية | الاسم — الوقت بالثواني أو mm:ss)', 5, "0:00 | 0:25 | الشكوى\n0:25 | 1:10 | الفحص وتحديد التسرب\n1:10 | 2:30 | الإصلاح" );
	echo '</fieldset>';
	$ta( 'faq', 'أسئلة شائعة (السؤال في سطر والإجابة في السطر التالي، وسطر فارغ بين كل سؤال والتالي)', 7, "كم تستغرق العملية؟\nعادةً يوم واحد بحسب المساحة.\n\nهل يلزم كسر البلاط؟\nلا، نحدد المكان بجهاز الكشف أولاً." );
	echo '</div>';
}

/** Saves every field (each value cleaned by its kind). */
add_action( 'save_post_' . ZAD_WK, function ( $id ) {
	if ( ! isset( $_POST['zad_wk_nonce'], $_POST['zad_wk'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_wk_nonce'] ) ), 'zad_wk_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$in  = wp_unslash( (array) $_POST['zad_wk'] ); // phpcs:ignore
	$txt = function ( $k ) use ( $in ) { return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? sanitize_textarea_field( (string) $in[ $k ] ) : ''; };
	$csv = function ( $k ) use ( $in ) { return implode( ',', array_filter( array_map( 'absint', explode( ',', isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '' ) ) ) ); };
	$date = function ( $k ) use ( $in ) { $v = isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : ''; return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : ''; };
	$svc = isset( $in['service'] ) ? absint( $in['service'] ) : 0;
	$vals = array(
		'service'    => ( $svc && in_array( get_post_type( $svc ), zad_service_types(), true ) ) ? $svc : 0,
		'date'       => $date( 'date' ), 'upload' => $date( 'upload' ),
		'facts'      => $txt( 'facts' ), 'complaint' => $txt( 'complaint' ), 'inspection' => $txt( 'inspection' ), 'result' => $txt( 'result' ), 'tools' => $txt( 'tools' ),
		'ba'         => $csv( 'ba' ), 'gallery' => $csv( 'gallery' ), 'poster' => (int) absint( $in['poster'] ?? 0 ),
		'video_url'  => isset( $in['video_url'] ) ? esc_url_raw( trim( (string) $in['video_url'] ) ) : '',
		'video_yt'   => isset( $in['video_yt'] ) ? esc_url_raw( trim( (string) $in['video_yt'] ) ) : '',
		'duration'   => max( 0, (int) ( $in['duration'] ?? 0 ) ),
		'clips'      => $txt( 'clips' ), 'faq' => $txt( 'faq' ),
	);
	foreach ( $vals as $k => $v ) { if ( '' === $v || 0 === $v ) { delete_post_meta( $id, '_zad_wk_' . $k ); } else { update_post_meta( $id, '_zad_wk_' . $k, $v ); } }
	delete_transient( 'zad_work_items' );
} );
