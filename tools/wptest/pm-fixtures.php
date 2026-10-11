<?php
/** Fixtures for the pests-library→sections merge. NEEDS the OLD mu-plugin (the one that registers pests-library, hierarchical). See pm-run.sh. */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 ); global $wpdb;
if ( ! post_type_exists( 'pests-library' ) ) { echo "pests-library is not registered: install the old mu-plugin first\n"; exit( 1 ); }
foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision')" ) as $i ) { wp_delete_post( (int) $i, true ); }
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' ); $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_groups' );
foreach ( array( 'zad_gm_log', 'zad_gm_log_pests', 'pm_old' ) as $o ) { delete_option( $o ); }
$mk = function ( $type, $title, $slug, $status = 'publish', $parent = 0, $content = '<p>نص</p>' ) { return wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status, 'post_parent' => $parent, 'post_content' => $content ) ); };
$ids = array();
$ids['insects'] = $mk( 'pests-library', 'الحشرات', 'insects' );
$ids['ants']    = $mk( 'pests-library', 'النمل', 'ants', 'publish', $ids['insects'], '<p>راجع <a href="/pests-library/termites/">النمل الأبيض</a> و<a href="/pests-library/insects/ants/">نفسه</a> و<img src="/pests-library-photo.jpg">' );
$ids['rodents'] = $mk( 'pests-library', 'القوارض', 'rodents' );
$ids['ants2']   = $mk( 'pests-library', 'نمل القوارض', 'ants', 'publish', $ids['rodents'] ); // same slug under another parent
$ids['termites'] = $mk( 'pests-library', 'النمل الأبيض', 'termites' );
$ids['draft']   = $mk( 'pests-library', 'مسودة', 'draft-pest', 'draft' );
$ids['shared']  = $mk( 'pests-library', 'مشترك', 'shared' );
$ids['arabic']  = $mk( 'pests-library', 'بق الفراش', 'بق-الفراش' );
$ids['s_shared'] = $mk( 'sections', 'مقال مشترك', 'shared' ); $ids['s_normal'] = $mk( 'sections', 'مقال عادي', 'normal-section', 'publish', 0, '<a href="/pests-library/termites/">x</a>' );
$ids['page'] = $mk( 'page', 'صفحة', 'links-page', 'publish', 0, '<a href="' . home_url( '/pests-library/' ) . '">المدونة</a> <a href="/pests-library/insects/ants/">نمل</a>' );
$ids['svc'] = $mk( 'zad_service', 'خدمة', 'svc-x' ); update_post_meta( $ids['svc'], '_zad_guides', array( $ids['termites'], $ids['ants'] ) );
$m = wp_create_nav_menu( 'M' . mt_rand() ); wp_update_nav_menu_item( $m, 0, array( 'menu-item-title' => 'المدونة', 'menu-item-url' => home_url( '/pests-library/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
$ids['mi'] = wp_update_nav_menu_item( $m, 0, array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'pests-library', 'menu-item-object-id' => $ids['termites'], 'menu-item-status' => 'publish', 'menu-item-title' => 'نمل أبيض' ) );
$old = array(); foreach ( array( 'insects', 'ants', 'ants2', 'termites', 'arabic', 'shared' ) as $k ) { $old[ $k ] = get_permalink( $ids[ $k ] ); }
update_option( 'pm_old', array( 'ids' => $ids, 'urls' => $old ), false );
flush_rewrite_rules( false );
echo json_encode( $old, JSON_UNESCAPED_UNICODE ), "\n";
