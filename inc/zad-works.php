<?php defined( 'ABSPATH' ) || exit;
/**
 * «أعمالنا» (Pure Structured Field Documentation Engine - Zad Pro v3.31.0)
 * - No Gutenberg editor, no fluff: structured metabox fields only.
 * - Automatic propagation: works linked to a parent service automatically appear on its city/district variants and category family.
 * - Pair ID (_zad_wk_pair_id) links separate "Before" and "After" works side-by-side automatically.
 * - Pure Vanilla JS & CSS (Zero external libraries).
 */

const ZAD_WK = 'zad_work';

function zad_works_slug() { return zad_slug( 'zad_works_slug', 'works' ); }

function zad_works_url() { return (string) get_post_type_archive_link( ZAD_WK ); }

/* ------------------------------------------------------------------ */
/* 1. Post Type (Title + Thumbnail Poster ONLY — No Gutenberg Editor)   */
/* ------------------------------------------------------------------ */

add_action( 'init', function () {
	register_post_type( ZAD_WK, array(
		'labels'       => array(
			'name'               => 'أعمالنا',
			'singular_name'      => 'عمل',
			'add_new'            => 'أضف عملاً',
			'add_new_item'       => 'إضافة عمل ميداني جديد',
			'edit_item'          => 'تحرير العمل',
			'new_item'           => 'عمل جديد',
			'view_item'          => 'عرض العمل',
			'search_items'       => 'بحث في الأعمال',
			'not_found'          => 'لا توجد أعمال',
			'all_items'          => 'كل الأعمال',
			'menu_name'          => 'أعمالنا',
			'featured_image'     => 'صورة البوستر / الغلاف (للفيديو أو العمل)',
			'set_featured_image' => 'تعيين صورة البوستر',
		),
		'public'       => true,
		'has_archive'  => true,
		'rewrite'      => array( 'slug' => zad_works_slug(), 'with_front' => false ),
		'menu_icon'    => 'dashicons-format-video',
		'menu_position'=> 8,
		'show_in_rest' => false, // تعطيل Gutenberg تماماً والاكتفاء بالحقول المهيكلة
		'supports'     => array( 'title', 'thumbnail' ),
	) );
}, 50 );

add_action( 'init', function () {
	foreach ( array( 'service_cat', 'service_area' ) as $tx ) {
		if ( taxonomy_exists( $tx ) ) {
			register_taxonomy_for_object_type( $tx, ZAD_WK );
		}
	}
}, 60 );

/* ------------------------------------------------------------------ */
/* 2. Data Helpers & Auto-Detectors                                     */
/* ------------------------------------------------------------------ */

function zad_wk_time( $v ) {
	$v = trim( strtr( (string) $v, array( '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => '' ) ) );
	if ( preg_match( '/^\d+$/', $v ) ) { return (int) $v; }
	if ( preg_match( '/^(?:(\d+):)?(\d{1,2}):(\d{1,2})$/', $v, $m ) ) { return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3]; }
	return null;
}

function zad_wk_clock( $s ) {
	$s = max( 0, (int) $s );
	return ( $s >= 3600 ? floor( $s / 3600 ) . ':' . sprintf( '%02d', floor( $s % 3600 / 60 ) ) : floor( $s / 60 ) ) . ':' . sprintf( '%02d', $s % 60 );
}

function zad_wk_pairs( $text ) {
	$out = array();
	foreach ( zad_lines( $text ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l, 2 ) );
		if ( '' !== $c[0] ) { $out[] = array( $c[0], $c[1] ?? '' ); }
	}
	return $out;
}

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

function zad_wk_faq( $id ) {
	$out = array();
	foreach ( preg_split( '/\R\s*\R/u', trim( (string) get_post_meta( $id, '_zad_wk_faq', true ) ) ) as $b ) {
		$ls = zad_lines( $b );
		if ( count( $ls ) >= 2 ) { $out[] = array( 'q' => array_shift( $ls ), 'a' => implode( ' ', $ls ) ); }
	}
	return $out;
}

function zad_wk_ids( $id, $key ) {
	return array_values( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, $key, true ) ) ) ) );
}

/** Smart Media & Video resolver (reads attachment ID or direct URL + auto-extracts duration & filesize) */
function zad_wk_video( $id ) {
	$file_id  = absint( get_post_meta( $id, '_zad_wk_file_id', true ) );
	$file_url = trim( (string) get_post_meta( $id, '_zad_wk_video_url', true ) );
	$dur_meta = absint( get_post_meta( $id, '_zad_wk_duration', true ) );

	// إذا لم يُحدد ملف رئيسي، نفحص أول عنصر في معرض وسائط الزيارة
	if ( ! $file_id && '' === $file_url ) {
		$media_ids = zad_wk_ids( $id, '_zad_wk_gallery' );
		foreach ( $media_ids as $mid ) {
			$mime = (string) get_post_mime_type( $mid );
			if ( 0 === strpos( $mime, 'video/' ) ) {
				$file_id = $mid;
				break;
			}
		}
	}

	if ( $file_id ) {
		$mime = (string) get_post_mime_type( $file_id );
		if ( 0 === strpos( $mime, 'video/' ) ) {
			$file_url = (string) wp_get_attachment_url( $file_id );
			if ( ! $dur_meta ) {
				$meta = wp_get_attachment_metadata( $file_id );
				if ( ! empty( $meta['length'] ) ) {
					$dur_meta = (int) $meta['length'];
				}
			}
		}
	}

	$yt   = zad_youtube_id( (string) get_post_meta( $id, '_zad_wk_video_yt', true ) );
	$kind = $file_url ? 'file' : ( $yt ? 'yt' : '' );
	$pid  = (int) get_post_meta( $id, '_zad_wk_poster', true );
	$poster = $pid ? (string) wp_get_attachment_image_url( $pid, 'large' ) : '';
	if ( '' === $poster && has_post_thumbnail( $id ) ) {
		$poster = (string) get_the_post_thumbnail_url( $id, 'large' );
	}
	if ( '' === $poster && 'yt' === $kind ) {
		$poster = 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
	}

	$up = trim( (string) get_post_meta( $id, '_zad_wk_upload', true ) );
	$iso = '';
	if ( zad_schema_review_date_valid( $up ) ) { $iso = ( new DateTime( $up . ' 00:00:00', wp_timezone() ) )->format( 'c' ); }
	if ( '' === $iso && $file_id ) { $attachment = get_post( $file_id ); if ( $attachment ) { $date = get_post_time( 'c', true, $attachment ); if ( strtotime( $date ) <= time() ) { $iso = $date; } } }

	return array(
		'kind'       => $kind,
		'src'        => $file_url,
		'file_id'    => $file_id,
		'yt'         => $yt,
		'embed'      => $yt ? 'https://www.youtube-nocookie.com/embed/' . $yt : '',
		'poster'     => $poster,
		'poster_id'  => $pid ?: ( has_post_thumbnail( $id ) ? (int) get_post_thumbnail_id( $id ) : 0 ),
		'duration'   => $dur_meta,
		'upload'     => $up,
		'upload_iso' => $iso,
	);
}

/** النص البديل الذكي: المكتوب يدوياً، وإلا alt المرفق، وإلا عنوان العمل + اسم الخدمة */
function zad_wk_alt( $id ) {
	$alt = trim( (string) get_post_meta( $id, '_zad_wk_alt', true ) );
	if ( '' !== $alt ) { return $alt; }
	$fid = absint( get_post_meta( $id, '_zad_wk_file_id', true ) ) ?: ( has_post_thumbnail( $id ) ? (int) get_post_thumbnail_id( $id ) : 0 );
	if ( $fid ) {
		$m_alt = trim( (string) get_post_meta( $fid, '_wp_attachment_image_alt', true ) );
		if ( '' !== $m_alt ) { return $m_alt; }
	}
	$svc = zad_wk_service( $id );
	return trim( get_the_title( $id ) . ( $svc ? ' — ' . zad_svc_label( $svc ) : '' ) );
}

function zad_wk_service( $id ) {
	$s = (int) get_post_meta( $id, '_zad_wk_service', true );
	return ( $s && 'publish' === get_post_status( $s ) && in_array( get_post_type( $s ), zad_service_types(), true ) ) ? $s : 0;
}

function zad_wk_terms( $id ) {
	$svc  = zad_wk_service( $id );
	$cat  = null;
	$city = trim( (string) get_post_meta( $id, '_zad_wk_city', true ) );
	$hood = trim( (string) get_post_meta( $id, '_zad_wk_hood', true ) );

	foreach ( array( $id, $svc ) as $src ) {
		if ( ! $src ) { continue; }
		if ( ! $cat ) {
			$t = get_the_terms( $src, 'service_cat' );
			if ( $t && ! is_wp_error( $t ) ) { $cat = $t[0]; }
		}
		$ar = get_the_terms( $src, 'service_area' );
		if ( $ar && ! is_wp_error( $ar ) && '' === $city ) {
			foreach ( $ar as $t ) {
				if ( 0 === (int) $t->parent ) {
					$city = $t->name;
				} elseif ( '' === $hood ) {
					$hood = $t->name;
					$p = get_term( (int) $t->parent, 'service_area' );
					if ( $p && ! is_wp_error( $p ) && '' === $city ) { $city = $p->name; }
				}
			}
		}
	}
	if ( '' === $hood ) {
		foreach ( zad_wk_pairs( get_post_meta( $id, '_zad_wk_facts', true ) ) as $f ) {
			if ( 0 === mb_strpos( $f[0], 'الحي' ) && '' !== $f[1] ) { $hood = $f[1]; break; }
		}
	}
	return array( $cat, $city, $hood );
}

function zad_wk_place( $id ) {
	list( , $city, $hood ) = zad_wk_terms( $id );
	return trim( ( $hood ? $hood : '' ) . ( $hood && $city ? '، ' : '' ) . $city );
}

function zad_wk_image_id( $id ) {
	if ( has_post_thumbnail( $id ) ) { return (int) get_post_thumbnail_id( $id ); }
	$fid = absint( get_post_meta( $id, '_zad_wk_file_id', true ) );
	if ( $fid && 0 === strpos( (string) get_post_mime_type( $fid ), 'image/' ) ) { return $fid; }
	$v = zad_wk_video( $id );
	if ( $v['poster_id'] ) { return (int) $v['poster_id']; }
	$ba = zad_wk_ids( $id, '_zad_wk_ba' );
	if ( count( $ba ) >= 2 ) { return $ba[1]; }
	$g = zad_wk_ids( $id, '_zad_wk_gallery' );
	foreach ( $g as $gid ) {
		if ( 0 === strpos( (string) get_post_mime_type( $gid ), 'image/' ) ) { return $gid; }
	}
	return 0;
}

/** إيجاد العمل التوأم (الزوج قبل/بعد) الذي يحمل نفس معرّف الزوج _zad_wk_pair_id */
function zad_wk_paired_post( $id ) {
	$pair_key = trim( (string) get_post_meta( $id, '_zad_wk_pair_id', true ) );
	if ( '' === $pair_key ) { return null; }
	$found = get_posts( array(
		'post_type'        => ZAD_WK,
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'post__not_in'     => array( (int) $id ),
		'meta_key'         => '_zad_wk_pair_id',
		'meta_value'       => $pair_key,
		'suppress_filters' => true,
	) );
	return $found ? $found[0] : null;
}

/** التسمية العربية للمرحلة */
function zad_wk_stage_label( $id ) {
	$st = (string) get_post_meta( $id, '_zad_wk_stage', true );
	$map = array(
		'before' => 'قبل العمل',
		'during' => 'أثناء العمل',
		'after'  => 'بعد العمل',
		'full'   => 'قبل وبعد',
	);
	return $map[ $st ] ?? '';
}

/* ------------------------------------------------------------------ */
/* 3. Smart Queries (Propagates from Parent Service to City Variants)   */
/* ------------------------------------------------------------------ */

function zad_works_for_service( $service_id, $limit = 4 ) {
	static $memo = array();
	$k = $service_id . '_' . $limit;
	if ( isset( $memo[ $k ] ) ) {
		return $memo[ $k ];
	}
	$service_id = (int) $service_id;
	$svc_ids    = array( $service_id );

	// 1. نضيف الخدمة الأب والأجداد (لكي تظهر أعمال الخدمة الأم في كل صفحات الأحياء والمدن الفرعية تلقائياً)
	foreach ( (array) get_post_ancestors( $service_id ) as $anc_id ) {
		$svc_ids[] = (int) $anc_id;
	}
	// 2. نضيف الأبناء المباشرين (لو كان العمل مربوطاً بفرع ويُعرض في الأب)
	$children = get_posts( array(
		'post_type'        => get_post_type( $service_id ),
		'post_parent'      => $service_id,
		'post_status'      => 'publish',
		'numberposts'      => 30,
		'fields'           => 'ids',
		'suppress_filters' => true,
		'zad_all'          => true,
	) );
	if ( $children ) {
		$svc_ids = array_values( array_unique( array_merge( $svc_ids, array_map( 'intval', $children ) ) ) );
	}

	$found = get_posts( array(
		'post_type'        => ZAD_WK,
		'post_status'      => 'publish',
		'numberposts'      => $limit,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => true,
		'meta_query'       => array(
			array(
				'key'     => '_zad_wk_service',
				'value'   => $svc_ids,
				'compare' => 'IN',
			),
		),
	) );

	// 3. إن لم توجد، نبحث في نفس عائلة التصنيف (service_cat) أو نفس نوع المنشور
	if ( count( $found ) < $limit ) {
		$exclude = wp_list_pluck( $found, 'ID' );
		$cats    = wp_get_post_terms( $service_id, 'service_cat', array( 'fields' => 'ids' ) );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$more = get_posts( array(
				'post_type'        => ZAD_WK,
				'post_status'      => 'publish',
				'numberposts'      => $limit - count( $found ),
				'post__not_in'     => $exclude,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'tax_query'        => array( array( 'taxonomy' => 'service_cat', 'terms' => $cats ) ),
			) );
			$found = array_merge( $found, $more );
		}
	}

	return $memo[ $k ] = array_slice( $found, 0, $limit );
}

function zad_works_similar( $id, $limit = 3 ) {
	$svc = zad_wk_service( $id );
	$out = array();
	if ( $svc ) {
		$out = array_values( array_filter( zad_works_for_service( $svc, $limit + 1 ), function ( $p ) use ( $id ) {
			return (int) $p->ID !== (int) $id;
		} ) );
	}
	if ( ! $out ) {
		$out = get_posts( array(
			'post_type'        => ZAD_WK,
			'post_status'      => 'publish',
			'numberposts'      => $limit,
			'post__not_in'     => array( (int) $id ),
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => true,
		) );
	}
	return array_slice( $out, 0, $limit );
}

function zad_works_section_html( $service_id ) {
	$ws  = zad_works_for_service( $service_id, 4 );
	$svc = zad_svc_label( $service_id );
	if ( ! $ws ) { return ''; }
	$arch_url = add_query_arg( 'svc_id', (int) $service_id, zad_works_url() );
	ob_start();
	echo '<section class="sec sec--tint zw-field" id="works" data-n="' . count( $ws ) . '"><div class="wrap">';
	echo '<header class="sec__head"><span class="eyebrow">توثيق ميداني حقيقي</span><h2 id="من-أعمالنا">من أعمالنا في ' . esc_html( $svc ) . '</h2><p>لقطات وفيديوهات حقيقية نفّذها فريقنا في الميدان مع شرح الحالة ونتيجة المعالجة.</p></header>';
	echo '<div class="zw-grid4">';
	foreach ( $ws as $w ) {
		$GLOBALS['post'] = $w;
		setup_postdata( $w );
		get_template_part( 'template-parts/work-card' );
	}
	wp_reset_postdata();
	echo '</div>';
	if ( zad_works_url() ) {
		echo '<p class="sec__more"><a class="btn btn--ghost-dark" href="' . esc_url( $arch_url ) . '">كل أعمال ' . esc_html( $svc ) . ' ' . zad_icon( 'arrow', 16 ) . '</a></p>';
	}
	echo '</div></section>';
	return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* 4. Front-End Assets & Archive Filter                                 */
/* ------------------------------------------------------------------ */

// Frontend assets are registered by inc/core/assets.php.


add_filter( 'zad_current_crumbs', function ( $c ) {
	$h = array( 'الرئيسية', home_url( '/' ) );
	if ( is_singular( ZAD_WK ) ) {
		$svc = zad_wk_service( get_queried_object_id() );
		$trail = array( $h, array( 'أعمالنا', zad_works_url() ) );
		if ( $svc ) {
			$trail[] = array( zad_svc_label( $svc ), get_permalink( $svc ) );
		}
		$trail[] = array( get_the_title(), '' );
		return $trail;
	}
	if ( is_post_type_archive( ZAD_WK ) ) {
		return array( $h, array( 'أعمالنا', '' ) );
	}
	return $c;
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( ZAD_WK ) ) { return; }
	$q->set( 'posts_per_page', 12 );
	$svc_slug = isset( $_GET['svc'] ) ? sanitize_title( wp_unslash( $_GET['svc'] ) ) : ''; // phpcs:ignore
	$svc_id   = isset( $_GET['svc_id'] ) ? absint( $_GET['svc_id'] ) : 0; // phpcs:ignore
	if ( $svc_id ) {
		$q->set( 'meta_query', array( array( 'key' => '_zad_wk_service', 'value' => $svc_id ) ) );
	} elseif ( '' !== $svc_slug ) {
		$q->set( 'tax_query', array( array( 'taxonomy' => 'service_cat', 'field' => 'slug', 'terms' => array( $svc_slug ) ) ) );
	}
} );

/* ------------------------------------------------------------------ */
/* 5. Admin Metabox: Structured Field Documentation (Like Rokn + Better)*/
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes_' . ZAD_WK, function () {
	add_meta_box( 'zad_wk_box', 'العمل (بيانات التوثيق الميداني)', 'zad_wk_box', ZAD_WK, 'normal', 'high' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
	}
} );

function zad_wk_box( $post ) {
	if ( function_exists( 'wp_enqueue_media' ) ) {
		wp_enqueue_media( array( 'post' => $post->ID ) );
	}
	wp_nonce_field( 'zad_wk_save', 'zad_wk_nonce' );
	$g   = function ( $k, $def = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, '_zad_wk_' . $k, true );
		return '' !== (string) $v ? (string) $v : $def;
	};
	$svc      = (int) $g( 'service' );
	$kind     = $g( 'media_type', 'video' );
	$file_id  = absint( $g( 'file_id' ) );
	$file_url = $g( 'video_url' );
	$gal_ids  = array_filter( array_map( 'absint', explode( ',', $g( 'gallery' ) ) ) );
	$stage    = $g( 'stage', '' );
	$city_val = $g( 'city', '' );

	// حساب معلومات الملف الرئيسي لعرضها كما في الصورة
	$file_info_html = '';
	if ( $file_id ) {
		$f_path = get_attached_file( $file_id );
		$f_name = $f_path ? basename( $f_path ) : basename( (string) wp_get_attachment_url( $file_id ) );
		$f_size = ( $f_path && file_exists( $f_path ) ) ? size_format( filesize( $f_path ) ) : '';
		$f_title= get_the_title( $file_id );
		$is_vid = 0 === strpos( (string) get_post_mime_type( $file_id ), 'video/' );
		$thumb_h= $is_vid ? '<span class="dashicons dashicons-video-alt3" style="font-size:38px;width:38px;height:38px;color:#64748b"></span>' : wp_get_attachment_image( $file_id, array( 64, 64 ) );
		$file_info_html = '<div class="zwk-filecard"><div class="zwk-filecard__ico">' . $thumb_h . '</div><div class="zwk-filecard__meta"><b>' . esc_html( $f_title ) . '</b><div dir="ltr">File name: <a href="' . esc_url( wp_get_attachment_url( $file_id ) ) . '" target="_blank">' . esc_html( $f_name ) . '</a></div>' . ( $f_size ? '<div>حجم الملف: ' . esc_html( $f_size ) . '</div>' : '' ) . '</div><button type="button" class="button-link-delete zwk-clear-file">إزالة</button></div>';
	}

	$cities_list = array( 'الرياض', 'الخرج', 'بريدة', 'عنيزة', 'القصيم', 'الدمام', 'القطيف', 'الخبر', 'جدة', 'نجران' );
	if ( function_exists( 'zad_get_confirmed_cities' ) ) {
		$conf_c = wp_list_pluck( zad_get_confirmed_cities(), 'name' );
		if ( $conf_c ) { $cities_list = array_values( array_unique( array_merge( $conf_c, $cities_list ) ) ); }
	}
	?>
	<style>
		.zwk-admin{direction:rtl;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
		.zwk-field{padding:16px 12px;border-bottom:1px solid #e2e8f0}
		.zwk-field:last-child{border-bottom:0}
		.zwk-label{display:block;font-weight:700;font-size:14px;margin-bottom:4px;color:#0f172a}
		.zwk-label .req{color:#dc2626}
		.zwk-desc{display:block;font-size:12.5px;color:#64748b;margin-bottom:8px}
		.zwk-input,.zwk-select,.zwk-textarea{width:100%;max-width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;background:#fff}
		.zwk-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
		.zwk-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
		@media(max-width:782px){.zwk-grid2,.zwk-grid3{grid-template-columns:1fr}}
		.zwk-filecard{display:flex;align-items:center;gap:14px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:12px;margin-top:8px}
		.zwk-filecard__ico{width:64px;height:64px;background:#e2e8f0;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0}
		.zwk-filecard__ico img{width:100%;height:100%;object-fit:cover}
		.zwk-filecard__meta{flex:1;font-size:13px;line-height:1.5}
		.zwk-gallery-box{border:1px solid #cbd5e1;border-radius:8px;background:#fff;overflow:hidden}
		.zwk-gallery-grid{display:flex;flex-wrap:wrap;gap:12px;padding:16px;min-height:140px;align-items:flex-start}
		.zwk-gitem{width:120px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;overflow:hidden;cursor:grab;position:relative;text-align:center;font-size:11px}
		.zwk-gitem__thumb{height:84px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;overflow:hidden}
		.zwk-gitem__thumb img{width:100%;height:100%;object-fit:cover}
		.zwk-gitem__name{padding:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;direction:ltr}
		.zwk-gitem__del{position:absolute;top:4px;left:4px;background:rgba(220,38,38,.9);color:#fff;border:0;width:20px;height:20px;border-radius:50%;cursor:pointer;line-height:1;font-size:12px}
		.zwk-gallery-bar{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#f8fafc;border-top:1px solid #e2e8f0}
		.zwk-btn-primary{background:#2563eb !important;color:#fff !important;border-color:#1d4ed8 !important;padding:4px 14px !important;border-radius:6px !important}
	</style>

	<div class="zwk-admin">
		<!-- 1. الخدمة الأم -->
		<div class="zwk-field">
			<label class="zwk-label">الخدمة <span class="req">*</span></label>
			<span class="zwk-desc">خدمة الرياض (الأم) أو أي خدمة رئيسية. العمل يظهر تلقائياً في هذه الخدمة وكل نسخها في المدن والأحياء وفي صفحة عائلتها.</span>
			<select name="zad_wk[service]" class="zwk-select" required>
				<option value="0">— اختر الخدمة —</option>
				<?php
				foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'zad_all' => true, 'suppress_filters' => true ) ) as $s ) {
					echo '<option value="' . (int) $s->ID . '"' . selected( $svc, $s->ID, false ) . '>' . esc_html( $s->post_title ) . '</option>';
				}
				?>
			</select>
		</div>

		<!-- 2. النوع -->
		<div class="zwk-field">
			<label class="zwk-label">النوع <span class="req">*</span></label>
			<select name="zad_wk[media_type]" class="zwk-select">
				<option value="video" <?php selected( $kind, 'video' ); ?>>فيديو</option>
				<option value="image" <?php selected( $kind, 'image' ); ?>>صورة</option>
				<option value="ba" <?php selected( $kind, 'ba' ); ?>>مقارنة قبل وبعد</option>
			</select>
		</div>

		<!-- 3. الملف (الصورة أو الفيديو MP4) -->
		<div class="zwk-field">
			<label class="zwk-label">الملف (الصورة أو الفيديو MP4) <span class="req">*</span></label>
			<span class="zwk-desc">الصورة بإطار الحقوق، أو الفيديو. للفيديو اختر «صورة البوستر» من العمود الجانبي الأيسر. يُقرأ حجم الملف ومدته تلقائياً.</span>
			<input type="hidden" name="zad_wk[file_id]" id="zwk_file_id" value="<?php echo (int) $file_id; ?>">
			<div style="display:flex;gap:8px;align-items:center">
				<button type="button" class="button zwk-pick-file">اختيار ملف من الوسائط</button>
				<input type="url" name="zad_wk[video_url]" id="zwk_video_url" value="<?php echo esc_attr( $file_url ); ?>" class="zwk-input" dir="ltr" placeholder="أو الصق رابط ملف MP4 مباشر هنا..." style="flex:1">
			</div>
			<div id="zwk_file_preview"><?php echo $file_info_html; // phpcs:ignore ?></div>
			<div class="zwk-grid3" style="margin-top:10px">
				<div>
					<label class="zwk-desc">المدة بالثواني (تُقرأ تلقائياً لـ MP4)</label>
					<input type="number" min="0" name="zad_wk[duration]" value="<?php echo esc_attr( $g( 'duration' ) ); ?>" class="zwk-input" placeholder="مثال: 22">
				</div>
				<div>
					<label class="zwk-desc">أو رابط يوتيوب (اختياري)</label>
					<input type="url" name="zad_wk[video_yt]" value="<?php echo esc_attr( $g( 'video_yt' ) ); ?>" class="zwk-input" dir="ltr" placeholder="https://youtube.com/watch?v=...">
				</div>
				<div>
					<label class="zwk-desc">تاريخ الرفع للسكيما (اختياري)</label>
					<input type="date" name="zad_wk[upload]" value="<?php echo esc_attr( $g( 'upload' ) ); ?>" class="zwk-input">
				</div>
			</div>
		</div>

		<!-- 4. النص البديل (alt) -->
		<div class="zwk-field">
			<label class="zwk-label">النص البديل (alt)</label>
			<span class="zwk-desc">وصف المشهد كما يُرى + الخدمة. فارغ = alt الملف أو عنوان العمل.</span>
			<input type="text" name="zad_wk[alt]" value="<?php echo esc_attr( $g( 'alt' ) ); ?>" class="zwk-input" placeholder="مثال: أداة شفط موصولة بخرطوم أزرق على أعلى جدار غرفة تحت السقف">
		</div>

		<!-- 5. صور العمل وفيديوهاته (المعرض المرتب بالسحب) -->
		<div class="zwk-field">
			<label class="zwk-label">صور العمل وفيديوهاته (رتّبها بالسحب: قبل ← أثناء ← بعد)</label>
			<span class="zwk-desc">كل صور الزيارة نفسها وفيديوهاتها. أول فيديو (أو أول صورة) يظهر في أعلى الصفحة إن لم يُحدَّد ملف أعلاه.</span>
			<input type="hidden" name="zad_wk[gallery]" id="zwk_gallery_input" value="<?php echo esc_attr( implode( ',', $gal_ids ) ); ?>">
			<div class="zwk-gallery-box">
				<div class="zwk-gallery-grid" id="zwk_gallery_grid">
					<?php foreach ( $gal_ids as $gid ) :
						$gmime = (string) get_post_mime_type( $gid );
						$gpath = get_attached_file( $gid );
						$gname = $gpath ? basename( $gpath ) : basename( (string) wp_get_attachment_url( $gid ) );
						$gthumb= ( 0 === strpos( $gmime, 'video/' ) ) ? '<span class="dashicons dashicons-video-alt3" style="font-size:32px;width:32px;height:32px;color:#475569"></span>' : wp_get_attachment_image( $gid, array( 120, 84 ) );
						?>
						<div class="zwk-gitem" draggable="true" data-id="<?php echo (int) $gid; ?>">
							<button type="button" class="zwk-gitem__del" title="حذف">×</button>
							<div class="zwk-gitem__thumb"><?php echo $gthumb; // phpcs:ignore ?></div>
							<div class="zwk-gitem__name"><?php echo esc_html( $gname ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="zwk-gallery-bar">
					<button type="button" class="button zwk-btn-primary" id="zwk_add_gallery">إضافة إلى المعرض</button>
					<button type="button" class="button" id="zwk_clear_gallery">تفريغ المعرض</button>
				</div>
			</div>
		</div>

		<!-- 6. ماذا حدث في العمل (اختياري) -->
		<div class="zwk-field">
			<label class="zwk-label">ماذا حدث في العمل (اختياري)</label>
			<span class="zwk-desc">نص حر بكلامك. إن مُلئ يحل محل الأقسام الثلاثة أدناه (الحالة قبل العمل / ما فعلناه / النتيجة).</span>
			<textarea name="zad_wk[free_story]" rows="4" class="zwk-textarea"><?php echo esc_textarea( $g( 'free_story' ) ); ?></textarea>
		</div>

		<!-- 7. الحالة قبل العمل -->
		<div class="zwk-field">
			<label class="zwk-label">الحالة قبل العمل</label>
			<span class="zwk-desc">كيف كان المكان أو القطعة قبل أن نبدأ.</span>
			<textarea name="zad_wk[complaint]" rows="3" class="zwk-textarea" placeholder="مثال: جدار غرفة فاتح اللون عند السقف، وتحته نافذة بحافة بيضاء وستائر منقوشة."><?php echo esc_textarea( $g( 'complaint' ) ); ?></textarea>
		</div>

		<!-- 8. ما فعلناه -->
		<div class="zwk-field">
			<label class="zwk-label">ما فعلناه</label>
			<span class="zwk-desc">ما قام به الفريق في هذه الزيارة بصيغة «نظّفنا... غسلنا...». خطوات الخدمة المرتبطة تُضاف تلقائياً.</span>
			<textarea name="zad_wk[inspection]" rows="3" class="zwk-textarea" placeholder="مثال: مرّر فنينا أداة الشفط الموصولة بخرطوم أزرق على أعلى الجدار تحت السقف مباشرة، وانتقل بها على امتداده حتى زاوية النافذة."><?php echo esc_textarea( $g( 'inspection' ) ); ?></textarea>
		</div>

		<!-- 9. النتيجة -->
		<div class="zwk-field">
			<label class="zwk-label">النتيجة</label>
			<span class="zwk-desc">كيف صار المكان بعد العمل. فارغ = القسم مخفي.</span>
			<textarea name="zad_wk[result]" rows="3" class="zwk-textarea"><?php echo esc_textarea( $g( 'result' ) ); ?></textarea>
		</div>

		<!-- 10. لماذا تحدث هذه الحالة؟ -->
		<div class="zwk-field">
			<label class="zwk-label">لماذا تحدث هذه الحالة؟</label>
			<span class="zwk-desc">شرح عام صحيح للحالة الظاهرة (60–100 كلمة) وما يمنع تكرارها. بلا أرقام ولا وعود. فارغ = القسم مخفي.</span>
			<textarea name="zad_wk[why_happens]" rows="4" class="zwk-textarea" placeholder="مثال: أعلى الجدران وزوايا السقف من الأماكن التي لا يصل إليها التنظيف اليومي، فيتجمع فيها الغبار الخفيف الذي يحمله الهواء الدافئ إلى الأعلى..."><?php echo esc_textarea( $g( 'why_happens' ) ); ?></textarea>
		</div>

		<!-- 11. ملاحظة من الفني (اختياري) -->
		<div class="zwk-field">
			<label class="zwk-label">ملاحظة من الفني (اختياري)</label>
			<span class="zwk-desc">ملاحظة قصيرة من الفني الذي نفّذ العمل. فارغ = مخفية.</span>
			<textarea name="zad_wk[tech_note]" rows="2" class="zwk-textarea"><?php echo esc_textarea( $g( 'tech_note' ) ); ?></textarea>
		</div>

		<!-- 12. المرحلة + معرّف الزوج (قبل/بعد) + تاريخ التنفيذ + المدينة -->
		<div class="zwk-field">
			<div class="zwk-grid2">
				<div>
					<label class="zwk-label">المرحلة</label>
					<span class="zwk-desc">مرحلة هذا المقطع أو الصورة من الزيارة.</span>
					<select name="zad_wk[stage]" class="zwk-select">
						<option value="">- Select -</option>
						<option value="before" <?php selected( $stage, 'before' ); ?>>قبل العمل</option>
						<option value="during" <?php selected( $stage, 'during' ); ?>>أثناء العمل</option>
						<option value="after" <?php selected( $stage, 'after' ); ?>>بعد العمل</option>
						<option value="full" <?php selected( $stage, 'full' ); ?>>الزيارة كاملة (قبل وبعد)</option>
					</select>
				</div>
				<div>
					<label class="zwk-label">معرّف الزوج (قبل/بعد)</label>
					<span class="zwk-desc">الرمز نفسه في عملَي «قبل» و«بعد» للموقع ذاته يعرضهما متجاورين تلقائياً.</span>
					<input type="text" name="zad_wk[pair_id]" value="<?php echo esc_attr( $g( 'pair_id' ) ); ?>" class="zwk-input" dir="ltr" placeholder="مثال: riyadh-villa-01">
				</div>
			</div>
		</div>

		<div class="zwk-field">
			<div class="zwk-grid3">
				<div>
					<label class="zwk-label">تاريخ التنفيذ</label>
					<span class="zwk-desc">تاريخ الزيارة الفعلي.</span>
					<input type="date" name="zad_wk[date]" value="<?php echo esc_attr( $g( 'date' ) ); ?>" class="zwk-input">
				</div>
				<div>
					<label class="zwk-label">المدينة (اختياري)</label>
					<span class="zwk-desc">لا تُطبع إلا إذا اختيرت. اتركها فارغة إن لم تتأكد.</span>
					<select name="zad_wk[city]" class="zwk-select">
						<option value="">- Select -</option>
						<?php foreach ( $cities_list as $cn ) : ?>
							<option value="<?php echo esc_attr( $cn ); ?>" <?php selected( $city_val, $cn ); ?>><?php echo esc_html( $cn ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label class="zwk-label">الحي (اختياري)</label>
					<span class="zwk-desc">مثال: حي النرجس، حي الروضة.</span>
					<input type="text" name="zad_wk[hood]" value="<?php echo esc_attr( $g( 'hood' ) ); ?>" class="zwk-input" placeholder="مثال: حي اليرموك">
				</div>
			</div>
		</div>

		<!-- 13. إضافات متقدمة اختيارية (في قائمة قابلة للطي لعدم زحام الصفحة) -->
		<div class="zwk-field">
			<details>
				<summary style="cursor:pointer;font-weight:700;color:#0c687e">خيارات متقدمة اختيارية (المعدات، ملخص الحالة، تقسيم فصول الفيديو، أسئلة العمل)</summary>
				<div style="margin-top:14px;display:grid;gap:12px">
					<div>
						<label class="zwk-label">الحالة في سطور (العنوان | القيمة)</label>
						<textarea name="zad_wk[facts]" rows="3" class="zwk-textarea" placeholder="نوع المبنى | فيلا سكنية&#10;المعدات | ماكينة شفط صناعية"><?php echo esc_textarea( $g( 'facts' ) ); ?></textarea>
					</div>
					<div>
						<label class="zwk-label">الأدوات والمعدات المستعملة (الأداة | الوظيفة)</label>
						<textarea name="zad_wk[tools]" rows="3" class="zwk-textarea"><?php echo esc_textarea( $g( 'tools' ) ); ?></textarea>
					</div>
					<div>
						<label class="zwk-label">مقاطع الفيديو (البداية | النهاية | الاسم)</label>
						<textarea name="zad_wk[clips]" rows="3" class="zwk-textarea" placeholder="0:00 | 0:15 | الحالة قبل البدء&#10;0:15 | 0:45 | طريقة المعالجة"><?php echo esc_textarea( $g( 'clips' ) ); ?></textarea>
					</div>
					<div>
						<label class="zwk-label">أسئلة شائعة عن هذا العمل (السؤال في سطر والإجابة تحته)</label>
						<textarea name="zad_wk[faq]" rows="4" class="zwk-textarea"><?php echo esc_textarea( $g( 'faq' ) ); ?></textarea>
					</div>
				</div>
			</details>
		</div>
	</div>

	<script>
	(function(){
		var mainFrame = null;
		var galFrame  = null;

		function syncGallery(){
			var galGrid  = document.getElementById('zwk_gallery_grid');
			var galInput = document.getElementById('zwk_gallery_input');
			if(!galGrid || !galInput) return;
			var ids = [].map.call(galGrid.querySelectorAll('.zwk-gitem'), function(el){ return el.getAttribute('data-id'); });
			galInput.value = ids.join(',');
		}

		// ربط الأحداث لحظة الضغط (يضمن أن wp.media قد تم تحميلها بالكامل في الفوتر)
		document.addEventListener('click', function(e){
			// 1. زر اختيار الملف الرئيسي (صورة أو فيديو MP4)
			var pickBtn = e.target.closest('.zwk-pick-file');
			if(pickBtn){
				e.preventDefault();
				if(!window.wp || !wp.media){
					alert('جاري تحميل مكتبة الوسائط، يرجى المحاولة بعد ثانية.');
					return;
				}
				if(!mainFrame){
					mainFrame = wp.media({
						title: 'اختر ملف العمل (فيديو MP4 أو صورة)',
						button: { text: 'اعتماد هذا الملف' },
						multiple: false
					});
					mainFrame.on('select', function(){
						var att = mainFrame.state().get('selection').first().toJSON();
						var fileIdInput = document.getElementById('zwk_file_id');
						var urlInput    = document.getElementById('zwk_video_url');
						var prevBox     = document.getElementById('zwk_file_preview');
						var durInput    = document.querySelector('input[name="zad_wk[duration]"]');
						var altInput    = document.querySelector('input[name="zad_wk[alt]"]');

						if(fileIdInput) fileIdInput.value = att.id || 0;
						if(urlInput) urlInput.value = att.url || '';

						if(att.type === 'video' && att.fileLength && durInput && !durInput.value){
							var parts = String(att.fileLength).split(':').map(Number);
							if(parts.length === 2) durInput.value = parts[0]*60 + parts[1];
							else if(parts.length === 3) durInput.value = parts[0]*3600 + parts[1]*60 + parts[2];
						}
						if(att.alt && altInput && !altInput.value){
							altInput.value = att.alt;
						}
						var ico = att.type === 'video'
							? '<span class="dashicons dashicons-video-alt3" style="font-size:38px;width:38px;height:38px;color:#64748b"></span>'
							: '<img src="' + (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) + '">';
						if(prevBox){
							prevBox.innerHTML = '<div class="zwk-filecard"><div class="zwk-filecard__ico">' + ico + '</div><div class="zwk-filecard__meta"><b>' + (att.title || att.filename || '') + '</b><div dir="ltr">File name: <a href="' + att.url + '" target="_blank">' + (att.filename || '') + '</a></div>' + (att.filesizeHumanReadable ? '<div>حجم الملف: ' + att.filesizeHumanReadable + '</div>' : '') + '</div><button type="button" class="button-link-delete zwk-clear-file">إزالة</button></div>';
						}
					});
				}
				mainFrame.open();
				return;
			}

			// 2. زر إزالة الملف الرئيسي
			if(e.target.closest('.zwk-clear-file')){
				e.preventDefault();
				var fid = document.getElementById('zwk_file_id');
				var pbx = document.getElementById('zwk_file_preview');
				if(fid) fid.value = '0';
				if(pbx) pbx.innerHTML = '';
				return;
			}

			// 3. زر إضافة إلى معرض صور وفيديوهات العمل
			var addGalBtn = e.target.closest('#zwk_add_gallery');
			if(addGalBtn){
				e.preventDefault();
				if(!window.wp || !wp.media) return;
				if(!galFrame){
					galFrame = wp.media({
						title: 'إضافة صور وفيديوهات للزيارة (قبل ← أثناء ← بعد)',
						button: { text: 'إضافة إلى المعرض' },
						multiple: 'add'
					});
					galFrame.on('select', function(){
						var galGrid = document.getElementById('zwk_gallery_grid');
						if(!galGrid) return;
						galFrame.state().get('selection').each(function(m){
							var att = m.toJSON();
							if(galGrid.querySelector('[data-id="' + att.id + '"]')) return;
							var div = document.createElement('div');
							div.className = 'zwk-gitem';
							div.draggable = true;
							div.setAttribute('data-id', att.id);
							var thumb = att.type === 'video'
								? '<span class="dashicons dashicons-video-alt3" style="font-size:32px;width:32px;height:32px;color:#475569"></span>'
								: '<img src="' + (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) + '">';
							div.innerHTML = '<button type="button" class="zwk-gitem__del" title="حذف">×</button><div class="zwk-gitem__thumb">' + thumb + '</div><div class="zwk-gitem__name">' + (att.filename || att.title || att.id) + '</div>';
							galGrid.appendChild(div);
						});
						syncGallery();
					});
				}
				galFrame.open();
				return;
			}

			// 4. زر تفريغ المعرض
			if(e.target.closest('#zwk_clear_gallery')){
				e.preventDefault();
				var gGrid = document.getElementById('zwk_gallery_grid');
				if(gGrid) gGrid.innerHTML = '';
				syncGallery();
				return;
			}

			// 5. زر حذف عنصر مفرد من المعرض
			var delBtn = e.target.closest('.zwk-gitem__del');
			if(delBtn){
				e.preventDefault();
				var item = delBtn.closest('.zwk-gitem');
				if(item) item.remove();
				syncGallery();
				return;
			}
		});

		// السحب والإفلات لترتيب عناصر المعرض
		var dragItem = null;
		document.addEventListener('dragstart', function(e){
			var item = e.target.closest('#zwk_gallery_grid .zwk-gitem');
			if(item){
				dragItem = item;
				e.dataTransfer.effectAllowed = 'move';
			}
		});
		document.addEventListener('dragover', function(e){
			var target = e.target.closest('#zwk_gallery_grid .zwk-gitem');
			if(!dragItem || !target || dragItem === target) return;
			e.preventDefault();
			var galGrid = document.getElementById('zwk_gallery_grid');
			var rect = target.getBoundingClientRect();
			var next = (e.clientX - rect.left) < (rect.width / 2);
			galGrid.insertBefore(dragItem, next ? target.nextSibling : target);
		});
		document.addEventListener('dragend', function(){
			if(dragItem){
				dragItem = null;
				syncGallery();
			}
		});
	})();
	</script>
	<?php
}

/* ------------------------------------------------------------------ */
/* 6. Save Metabox Data                                                 */
/* ------------------------------------------------------------------ */

add_action( 'save_post_' . ZAD_WK, function ( $id ) {
	if ( ! isset( $_POST['zad_wk_nonce'], $_POST['zad_wk'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_wk_nonce'] ) ), 'zad_wk_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$in   = wp_unslash( (array) $_POST['zad_wk'] ); // phpcs:ignore
	$txt  = function ( $k ) use ( $in ) { return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? sanitize_textarea_field( (string) $in[ $k ] ) : ''; };
	$line = function ( $k ) use ( $in ) { return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? sanitize_text_field( (string) $in[ $k ] ) : ''; };
	$csv  = function ( $k ) use ( $in ) { return implode( ',', array_filter( array_map( 'absint', explode( ',', isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '' ) ) ) ); };
	$date = function ( $k ) use ( $in ) { $v = isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : ''; return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : ''; };

	$svc     = isset( $in['service'] ) ? absint( $in['service'] ) : 0;
	$file_id = isset( $in['file_id'] ) ? absint( $in['file_id'] ) : 0;
	$dur     = max( 0, (int) ( $in['duration'] ?? 0 ) );

	// استخراج مدة الفيديو تلقائياً من المرفق إن لم تُكتب
	if ( ! $dur && $file_id ) {
		$meta = wp_get_attachment_metadata( $file_id );
		if ( ! empty( $meta['length'] ) ) { $dur = (int) $meta['length']; }
	}

	$vals = array(
		'service'    => ( $svc && in_array( get_post_type( $svc ), zad_service_types(), true ) ) ? $svc : 0,
		'media_type' => in_array( $line( 'media_type' ), array( 'video', 'image', 'ba' ), true ) ? $line( 'media_type' ) : 'video',
		'file_id'    => $file_id,
		'alt'        => $line( 'alt' ),
		'free_story' => $txt( 'free_story' ),
		'complaint'  => $txt( 'complaint' ),
		'inspection' => $txt( 'inspection' ),
		'result'     => $txt( 'result' ),
		'why_happens'=> $txt( 'why_happens' ),
		'tech_note'  => $txt( 'tech_note' ),
		'stage'      => in_array( $line( 'stage' ), array( 'before', 'during', 'after', 'full' ), true ) ? $line( 'stage' ) : '',
		'pair_id'    => sanitize_key( $line( 'pair_id' ) ),
		'date'       => $date( 'date' ),
		'upload'     => $date( 'upload' ),
		'city'       => $line( 'city' ),
		'hood'       => $line( 'hood' ),
		'facts'      => $txt( 'facts' ),
		'tools'      => $txt( 'tools' ),
		'ba'         => $csv( 'ba' ),
		'gallery'    => $csv( 'gallery' ),
		'poster'     => (int) absint( $in['poster'] ?? 0 ),
		'video_url'  => isset( $in['video_url'] ) ? esc_url_raw( trim( (string) $in['video_url'] ) ) : '',
		'video_yt'   => isset( $in['video_yt'] ) ? esc_url_raw( trim( (string) $in['video_yt'] ) ) : '',
		'duration'   => $dur,
		'clips'      => $txt( 'clips' ),
		'faq'        => $txt( 'faq' ),
	);

	foreach ( $vals as $k => $v ) {
		if ( '' === $v || 0 === $v ) {
			delete_post_meta( $id, '_zad_wk_' . $k );
		} else {
			update_post_meta( $id, '_zad_wk_' . $k, $v );
		}
	}

	// نسخ تصنيف الخدمة تلقائياً للعمل ليعمل الفلتر في الأرشيف
	if ( $svc ) {
		$scats = wp_get_post_terms( $svc, 'service_cat', array( 'fields' => 'ids' ) );
		if ( $scats && ! is_wp_error( $scats ) ) {
			wp_set_object_terms( $id, array_map( 'intval', $scats ), 'service_cat', false );
		}
	}

	delete_transient( 'zad_work_items' );
} );
