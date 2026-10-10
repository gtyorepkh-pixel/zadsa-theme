<?php
/** Fixtures for the guide→sections merge tests (read-only inventory now; the merge itself later). Run: php tools/wptest/gm-fixtures.php */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
global $wpdb;
foreach ( get_posts( array( 'post_type' => array( 'guide', 'sections', 'page', 'post', 'nav_menu_item', 'zad_service', 'zad_faq' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $i ) { wp_delete_post( $i, true ); }
foreach ( array( 'best_guide', 'best_sections' ) as $tx ) { foreach ( (array) get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false ) ) as $t ) { wp_delete_term( $t->term_id, $tx ); } }
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' ); $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_groups' );
$mk = function ( $type, $title, $slug, $status = 'publish', $content = '<p>نص</p>', $ex = '' ) { return wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status, 'post_content' => $content, 'post_excerpt' => $ex, 'post_date' => '2024-03-05 10:00:00', 'post_date_gmt' => '2024-03-05 10:00:00' ) ); };
$t = function ( $name, $slug, $tax ) { $r = wp_insert_term( $name, $tax, array( 'slug' => $slug ) ); return (int) $r['term_id']; };
$g_clean = $t( 'تنظيف المكيفات', 'ac-cleaning', 'best_guide' ); $g_pest = $t( 'مكافحة النمل الأبيض', 'termites', 'best_guide' ); $g_tank = $t( 'الخزانات', 'tanks', 'best_guide' ); $g_new = $t( 'السباكة', 'plumbing', 'best_guide' );
$s_clean = $t( 'تنظيف مكيفات', 'ac', 'best_sections' ); $s_pest = $t( 'مكافحة النمل الابيض', 'white-ants', 'best_sections' ); $s_tank = $t( 'الخزانات', 'tanks', 'best_sections' );
$ids = array();
$ids['g1'] = $mk( 'guide', 'دليل تنظيف المكيف', 'ac-guide', 'publish', '<p>راجع <a href="/guide/other-guide/">دليل</a> و<a href="/best-guide/tanks/">الخزانات</a></p>', 'ملخص' );
$ids['g2'] = $mk( 'guide', 'دليل النمل الأبيض', 'termites-guide', 'publish' );
$ids['g3'] = $mk( 'guide', 'دليل مشترك', 'shared-slug', 'publish' );
$ids['g4'] = $mk( 'guide', 'دليل مسودة', 'draft-guide', 'draft' );
$ids['g5'] = $mk( 'guide', 'دليل خاص', 'private-guide', 'private' );
$ids['g6'] = $mk( 'guide', 'دليل مجدول', 'future-guide', 'future' ); wp_update_post( array( 'ID' => $ids['g6'], 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + 864000 ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + 864000 ) ) );
$ids['g7'] = $mk( 'guide', 'دليل بلا تصنيف', 'no-term', 'publish' );
$ids['s1'] = $mk( 'sections', 'مقال مشترك', 'shared-slug', 'publish' );
$ids['s2'] = $mk( 'sections', 'مقال عادي', 'normal-section', 'publish', '<p><a href="/guide/ac-guide/">دليل</a></p>' );
wp_set_object_terms( $ids['g1'], array( $g_clean ), 'best_guide' ); wp_set_object_terms( $ids['g2'], array( $g_pest ), 'best_guide' ); wp_set_object_terms( $ids['g3'], array( $g_tank ), 'best_guide' );
wp_set_object_terms( $ids['g4'], array( $g_new ), 'best_guide' ); wp_set_object_terms( $ids['g5'], array( $g_clean ), 'best_guide' ); wp_set_object_terms( $ids['g6'], array( $g_new ), 'best_guide' );
wp_set_object_terms( $ids['g1'], array( 'tag-a' ), 'post_tag' );
foreach ( array( 'g1', 'g2' ) as $k ) { update_post_meta( $ids[ $k ], '_yoast_wpseo_title', 'عنوان سيو ' . $k ); update_post_meta( $ids[ $k ], '_yoast_wpseo_metadesc', 'وصف ' . $k ); update_post_meta( $ids[ $k ], '_yoast_wpseo_focuskw', 'كلمة' ); update_post_meta( $ids[ $k ], '_yoast_wpseo_primary_best_guide', $g_clean ); }
$page = $mk( 'page', 'صفحة روابط', 'links-page', 'publish', '<a href="' . home_url( '/guide/ac-guide/' ) . '">x</a> <a href="/guide/termites-guide">y</a> <a href="/guides-overview/">not a match</a>' );
$svc = $mk( 'zad_service', 'خدمة', 'svc-x' ); update_post_meta( $svc, '_zad_guides', array( $ids['g1'], $ids['g2'] ) );
$m = wp_create_nav_menu( 'Main' ); wp_update_nav_menu_item( $m, 0, array( 'menu-item-title' => 'الأدلة', 'menu-item-url' => home_url( '/guide/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
wp_update_nav_menu_item( $m, 0, array( 'menu-item-title' => 'دليل المكيف', 'menu-item-object' => 'guide', 'menu-item-object-id' => $ids['g1'], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
update_option( 'zad_test_opt_links', array( 'a' => '<a href="/guide/">دليل</a>' ) );
global $wpdb; $wpdb->insert( $wpdb->prefix . 'redirection_groups', array( 'name' => 'old', 'tracking' => 1, 'module_id' => 1, 'status' => 'enabled', 'position' => 0 ) );
echo wp_json_encode( $ids + array( 'terms' => compact( 'g_clean', 'g_pest', 'g_tank', 'g_new', 's_clean', 's_pest', 's_tank' ) ) ), "\n";
flush_rewrite_rules( true );
