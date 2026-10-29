<?php
/** Real-WP test of inc/zad-content-pack.php + the warranty de-duplication of the new layout. Run: php tools/wptest/pack-test.php (server: /tmp/restart-srv.sh) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
require_once get_template_directory() . '/inc/zad-content-pack.php';
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function get( $url ) { $c = curl_init( $url ); curl_setopt_array( $c, array( CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 30 ) ); $b = curl_exec( $c ); return array( curl_getinfo( $c, CURLINFO_HTTP_CODE ), (string) $b ); }
foreach ( get_posts( array( 'post_type' => 'zad_service', 'name' => 'pack-test-tanks', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) { wp_delete_post( $p->ID, true ); }

$head = "<!-- wp:paragraph -->\n<p>INTRO_KEEP مقدمة تبقى.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:more -->\n<!--more-->\n<!-- /wp:more -->";
$old  = "\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">OLD_BLOCK عنوان قديم</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>نص قديم.</p>\n<!-- /wp:paragraph -->";
$id = wp_insert_post( array( 'post_type' => 'zad_service', 'post_title' => 'تنظيف خزانات اختبار الحزمة', 'post_name' => 'pack-test-tanks', 'post_status' => 'publish', 'post_content' => $head . $old ) );
$orig = get_post( $id )->post_content;
update_post_meta( $id, '_zad_packages', "اقتصادية|150|ريال|وصف|30 يوماً|تفريغ|0|اطلب|\nشاملة|300|ريال|وصف|30 يوماً|تفريغ;تعقيم|1|اطلب|" );
update_post_meta( $id, '_zad_ix', array( 'on' => 1, 'en' => array() ) );

$sp = zad_cp_split( $orig );
ok( $sp && false !== strpos( $sp[0], 'INTRO_KEEP' ) && false !== strpos( $sp[1], 'OLD_BLOCK' ) && false === strpos( $sp[0], 'OLD_BLOCK' ), 'split keeps the intro + the more tag in the head' );
ok( null === zad_cp_split( '<p>بلا وسم</p>' ), 'no tag = null' );

$r = zad_cp_apply( $id, 'tanks-knowledge' );
$new = get_post( $id )->post_content;
ok( true === $r, 'apply succeeds' );
ok( false !== strpos( $new, 'INTRO_KEEP' ) && false === strpos( $new, 'OLD_BLOCK' ), 'intro kept, old text gone' );
ok( false !== strpos( $new, 'ما الفرق بين تنظيف الخزان وتعقيمه' ) && false !== strpos( $new, 'كيف أختار شركة تنظيف خزانات' ), 'new pack text present' );
ok( $orig === get_post_meta( $id, ZAD_CP_META, true ), 'full original content saved in meta' );
ok( 'تنظيف خزانات اختبار الحزمة' === get_the_title( $id ) && 'pack-test-tanks' === get_post( $id )->post_name, 'title and slug untouched' );
ok( 1 === substr_count( $new, '<!--more-->' ), 'exactly one more tag' );
$w = zad_cp_words( zad_cp_html( 'tanks-knowledge' ) ); ok( $w >= 600 && $w <= 800, "pack length 600-800 words ($w)" );
ok( 0 === preg_match( '#<table|mk__todo|⟵#', zad_cp_html( 'tanks-knowledge' ) ), 'pack has no tables and no review placeholders' );
ok( 1 <= preg_match_all( '#<ul class="wp-block-list">#', zad_cp_html( 'tanks-knowledge' ) ) && 1 <= preg_match_all( '#<ol class="wp-block-list">#', zad_cp_html( 'tanks-knowledge' ) ), 'pack uses bullet and numbered lists' );

$r2 = zad_cp_apply( $id, 'tanks-knowledge' ); ok( true === $r2 && $orig === get_post_meta( $id, ZAD_CP_META, true ), 're-apply keeps the ORIGINAL backup' );
$html = get( get_permalink( $id ) )[1];
ok( false !== strpos( $html, 'INTRO_KEEP' ) && false !== strpos( $html, 'كيف أختار شركة تنظيف خزانات' ) && false === strpos( $html, 'OLD_BLOCK' ), 'front end shows the new text (classic layout)' );

ok( true === zad_cp_undo( $id ) && $orig === get_post( $id )->post_content && '' === (string) get_post_meta( $id, ZAD_CP_META, true ), 'undo restores the exact original and clears the backup' );
ok( false === zad_cp_undo( $id ), 'undo without a backup does nothing' );
wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => '<p>بلا وسم مزيد</p>' ) ) );
$r3 = zad_cp_apply( $id, 'tanks-knowledge' ); ok( is_wp_error( $r3 ) && '<p>بلا وسم مزيد</p>' === get_post( $id )->post_content, 'page without the tag is refused and untouched' );
ok( is_wp_error( zad_cp_apply( 999999, 'tanks-knowledge' ) ), 'unknown page refused' );
ok( is_wp_error( zad_cp_apply( $id, 'nope' ) ), 'unknown pack refused' );

/* warranty said once in the new layout */
wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $orig ) ) );
list( , $cl ) = get( get_permalink( $id ) );
$cl_b = substr( $cl, (int) strpos( $cl, '<main id="main"' ) );
ok( 2 === substr_count( $cl_b, 'class="pkgx__w"' ) && false === strpos( $cl_b, 'pkgx__wall' ), 'classic: warranty chip on each card, no shared line' );
update_post_meta( $id, '_zad_layout', 'new' );
list( , $nw ) = get( get_permalink( $id ) );
$nw_b = substr( $nw, (int) strpos( $nw, '<main id="main"' ) );
ok( 0 === substr_count( $nw_b, 'class="pkgx__w"' ) && 1 === substr_count( $nw_b, 'pkgx__wall' ) && false !== strpos( $nw_b, 'ضمان 30 يوماً على جميع الباقات' ), 'new: identical warranty printed once under the cards' );
update_post_meta( $id, '_zad_packages', "اقتصادية|150|ريال|وصف|30 يوماً|تفريغ|0|اطلب|\nشاملة|300|ريال|وصف|60 يوماً|تفريغ;تعقيم|1|اطلب|" );
list( , $nw2 ) = get( get_permalink( $id ) );
$nw2_b = substr( $nw2, (int) strpos( $nw2, '<main id="main"' ) );
ok( 2 === substr_count( $nw2_b, 'class="pkgx__w"' ) && false === strpos( $nw2_b, 'pkgx__wall' ), 'new: different warranties keep the per-card chips' );
$trust = function ( $h ) { return preg_match( '#<ul class="tk__trust">(.*?)</ul>#su', $h, $m ) ? $m[1] : ''; };
ok( false !== strpos( $trust( $cl_b ), 'ضمان مكتوب' ), 'classic: ticket shows the «ضمان مكتوب» badge' );
ok( '' !== $trust( $nw_b ) && false === strpos( $trust( $nw_b ), 'ضمان' ), 'new: ticket keeps its other badges but not the warranty one' );

wp_delete_post( $id, true );
echo "pack-test: $n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
