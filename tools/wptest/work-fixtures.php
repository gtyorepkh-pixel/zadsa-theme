<?php
/** Fixtures for the «أعمالنا» tests: a service with terms, 15 works (the first one complete: video + 3 clips + before/after + gallery + faq), old pages to migrate, a gallery page. php tools/wptest/work-fixtures.php */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );

global $wpdb;
foreach ( get_posts( array( 'post_type' => array( 'zad_work', 'zad_service', 'page', 'attachment' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $i ) { wp_delete_post( $i, true ); }
foreach ( array( 'zad_wm_map', 'zad_wm_log', 'zad_wm_parent_id', 'zad_work_items' ) as $o ) { delete_option( $o ); } delete_transient( 'zad_work_items' );
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' ); $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_groups' );
$up = wp_upload_dir(); $dir = $up['path'];
$img = function ( $name, $r, $g, $b ) use ( $dir ) { $f = $dir . '/' . $name . '.jpg'; $im = imagecreatetruecolor( 1200, 800 ); imagefill( $im, 0, 0, imagecolorallocate( $im, $r, $g, $b ) ); imagestring( $im, 5, 40, 40, $name, imagecolorallocate( $im, 255, 255, 255 ) ); imagejpeg( $im, $f, 70 ); $id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => $name, 'post_status' => 'inherit' ), $f ); wp_update_attachment_metadata( $id, array( 'width' => 1200, 'height' => 800, 'file' => _wp_relative_upload_path( $f ), 'sizes' => array() ) ); update_post_meta( $id, '_wp_attachment_image_alt', $name ); return $id; };
$before = $img( 'before-leak', 120, 80, 60 ); $after = $img( 'after-leak', 60, 140, 90 ); $g1 = $img( 'gallery-1', 40, 90, 140 ); $g2 = $img( 'gallery-2', 140, 90, 40 ); $poster = $img( 'poster', 12, 104, 130 ); $thumb = $img( 'thumb', 90, 60, 120 );
$cat = term_exists( 'كشف تسربات', 'service_cat' ) ?: wp_insert_term( 'كشف تسربات', 'service_cat' ); $cat = (int) ( is_array( $cat ) ? $cat['term_id'] : $cat );
$riy = term_exists( 'الرياض', 'service_area' ) ?: wp_insert_term( 'الرياض', 'service_area' ); $riy = (int) ( is_array( $riy ) ? $riy['term_id'] : $riy );
$nkh = term_exists( 'النخيل', 'service_area' ) ?: wp_insert_term( 'النخيل', 'service_area', array( 'parent' => $riy ) ); $nkh = (int) ( is_array( $nkh ) ? $nkh['term_id'] : $nkh );
$svc = wp_insert_post( array( 'post_type' => 'zad_service', 'post_title' => 'كشف تسربات المياه بالرياض', 'post_name' => 'leak-detection', 'post_status' => 'publish', 'post_content' => '<p>خدمة كشف تسربات.</p>' ) );
wp_set_object_terms( $svc, array( $cat ), 'service_cat' ); wp_set_object_terms( $svc, array( $riy ), 'service_area' );
update_post_meta( $svc, '_zad_faq', array( array( 'q' => 'هل الكشف بدون كسر؟', 'a' => 'نعم بالأجهزة.' ) ) );
$svc2 = wp_insert_post( array( 'post_type' => 'zad_service', 'post_title' => 'خدمة بلا أعمال', 'post_name' => 'no-works', 'post_status' => 'publish', 'post_content' => '<p>x</p>' ) );
$ids = array( 'svc' => $svc, 'svc2' => $svc2 );
$mk = function ( $title, $slug, $meta = array(), $thumb_id = 0 ) use ( $svc, $cat, $nkh, $riy ) { $id = wp_insert_post( array( 'post_type' => 'zad_work', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_content' => '<p>تفاصيل ' . $title . '</p>', 'post_excerpt' => 'ملخص ' . $title ) ); foreach ( $meta as $k => $v ) { update_post_meta( $id, '_zad_wk_' . $k, $v ); } if ( $thumb_id ) { set_post_thumbnail( $id, $thumb_id ); } return $id; };
$vid = $up['url'] . '/work-test.webm';
$ids['w1'] = $mk( 'قصة تسريب تحت البلاط', 'story-leak-under-tile', array( 'service' => $svc, 'date' => '2026-09-20', 'facts' => "الحي | النخيل\nنوع المبنى | فيلا\nالمشكلة | تسريب تحت البلاط\nالمدة | يوم واحد\nالنتيجة | إصلاح كامل", 'complaint' => 'ارتفاع فاتورة المياه ورطوبة في الأرضية.', 'inspection' => 'كشفنا بالجهاز الصوتي وحددنا موضع التسريب.', 'result' => 'أُصلح التسريب بلا كسر كبير.', 'tools' => "جهاز كشف صوتي | لتحديد المكان بدقة\nكاميرا حرارية | لمتابعة الرطوبة", 'ba' => $before . ',' . $after, 'gallery' => $g1 . ',' . $g2, 'video_url' => $vid, 'poster' => $poster, 'duration' => 150, 'upload' => '2026-09-21', 'clips' => "0:00 | 0:25 | الشكوى\n0:25 | 1:10 | الفحص وتحديد التسرب\n1:10 | 2:30 | الإصلاح", 'faq' => "كم تستغرق العملية؟\nعادةً يوم واحد بحسب المساحة.\n\nهل يلزم كسر البلاط؟\nلا، نحدد المكان بجهاز الكشف أولاً." ), $thumb );
wp_set_object_terms( $ids['w1'], array( $cat ), 'service_cat' ); wp_set_object_terms( $ids['w1'], array( $nkh ), 'service_area' );
$ids['w2'] = $mk( 'عمل بفيديو يوتيوب', 'youtube-work', array( 'service' => $svc, 'video_yt' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'poster' => $poster, 'duration' => 90, 'upload' => '2026-09-01', 'clips' => "0:10 | 0:40 | البداية\n0:40 | 1:20 | النهاية" ), $thumb );
$ids['w3'] = $mk( 'عمل بلا فيديو', 'no-video-work', array( 'service' => $svc, 'complaint' => 'شكوى فقط' ), 0 );
for ( $i = 4; $i <= 15; $i++ ) { $x = $mk( 'عمل رقم ' . $i, 'work-' . $i, array( 'complaint' => 'شكوى ' . $i ), $thumb ); wp_set_object_terms( $x, array( $cat ), 'service_cat' ); }
// the old gallery page (template must keep working) and the pages to migrate
$gal = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'أعمالنا القديمة', 'post_name' => 'old-gallery', 'post_status' => 'publish', 'post_content' => '' ) ); update_post_meta( $gal, '_wp_page_template', 'temp/memo-gallery.php' );
$par = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'المشاريع المنجزة', 'post_name' => 'completed-projects', 'post_status' => 'publish', 'post_content' => '<p>قائمة المشاريع</p>' ) );
$old = array(); foreach ( array( 'sofa-and-carpet-cleaning-in-al-nakhil-neighborhood' => 'تنظيف كنب وسجاد في حي النخيل', 'water-tank-cleaning-al-olaya-riyadh' => 'تنظيف خزان مياه في العليا' ) as $s => $t ) {
	$p = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $t, 'post_name' => $s, 'post_status' => 'publish', 'post_parent' => $par, 'post_content' => '<!-- wp:paragraph --><p>محتوى ' . $t . '</p><!-- /wp:paragraph -->', 'post_excerpt' => 'ملخص ' . $t, 'post_date' => '2025-05-05 10:00:00' ) );
	set_post_thumbnail( $p, $thumb ); update_post_meta( $p, '_yoast_wpseo_title', 'عنوان سيو ' . $t ); update_post_meta( $p, '_yoast_wpseo_metadesc', 'وصف ' . $t ); update_post_meta( $p, '_yoast_wpseo_focuskw', 'كلمة' ); $old[ $s ] = $p; }
update_option( 'zad_wm_parent_id', $par, false );
update_option( 'zad_nav_ver', time() ); flush_rewrite_rules( true );
echo wp_json_encode( $ids + array( 'gal' => $gal, 'par' => $par, 'old' => $old ) ), "\n";
