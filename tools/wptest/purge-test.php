<?php
/** حذف نوعَي النقل والتسليك نهائياً: fixtures + dry-run + real run + «never comes back». php tools/wptest/purge-test.php (test site; the OLD mu-plugin that still registers both types stays installed on purpose) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 ); global $wpdb;
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function cnt( $sql ) { global $wpdb; return (int) $wpdb->get_var( $sql ); }

/* ---- 0) guard: old mu-plugin registers them, the theme must unregister at init 9999 ---- */
ok( file_exists( WPMU_PLUGIN_DIR . '/zad-core-cpt.php' ) && false !== strpos( file_get_contents( WPMU_PLUGIN_DIR . '/zad-core-cpt.php' ), "'moving'" ), 'test site still has the OLD mu-plugin that registers moving' );
ok( ! post_type_exists( 'moving' ) && ! post_type_exists( 'drain_cleaning' ), 'theme unregisters moving + drain_cleaning even if an old mu-plugin registers them' );

/* ---- fixtures ---- */
foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision')" ) as $i ) { wp_delete_post( (int) $i, true ); }
$wpdb->query( "DELETE FROM {$wpdb->prefix}redirection_items" );
register_taxonomy( 'moving_cat', array( 'moving' ), array( 'hierarchical' => true ) );
$mk = function ( $t, $title, $st = 'publish', $extra = array() ) { return wp_insert_post( array_merge( array( 'post_type' => $t, 'post_title' => $title, 'post_status' => $st, 'post_content' => 'محتوى ' . $title ), $extra ) ); };
$m1 = $mk( 'moving', 'نقل أثاث الرياض' ); $m2 = $mk( 'moving', 'نقل مسودة', 'draft' ); $m3 = $mk( 'moving', 'نقل محذوف', 'trash' ); $m4 = $mk( 'moving', 'نقل فرعي', 'publish', array( 'post_parent' => $m1 ) );
$d1 = $mk( 'drain_cleaning', 'تسليك مجاري' ); $d2 = $mk( 'drain-cleaning', 'تسليك قديم' );
foreach ( array( $m1, $d1 ) as $i ) { wp_insert_post( array( 'post_type' => 'revision', 'post_parent' => $i, 'post_status' => 'inherit', 'post_title' => 'rev', 'post_name' => $i . '-revision-v1' ) ); update_post_meta( $i, '_yoast_wpseo_title', 'x' ); update_post_meta( $i, 'whatever', 'y' ); }
$old = term_exists( 'تصنيف نقل', 'moving_cat' ); if ( $old ) { wp_delete_term( (int) $old['term_id'], 'moving_cat' ); }
$t = wp_insert_term( 'تصنيف نقل', 'moving_cat' ); wp_set_object_terms( $m1, array( (int) $t['term_id'] ), 'moving_cat' );
wp_insert_comment( array( 'comment_post_ID' => $m1, 'comment_content' => 'تعليق', 'comment_approved' => 1 ) );
$keep = $mk( 'page', 'صفحة باقية', 'publish', array( 'post_content' => '<a href="/moving/old-page/">رابط</a> و <a href="/cleaning/x/">سليم</a>' ) );
$c1 = $mk( 'page', 'صفحة تنظيف' ); $c2 = $mk( 'page', 'صفحة تنظيف 2' );
update_post_meta( $keep, '_zad_related', $m1 . ',' . $c1 . ',' . $d1 ); update_post_meta( $keep, '_zad_cities_svc', array( $m1, $c2 ) ); update_post_meta( $keep, '_zad_guides', array( $d1 ) ); update_post_meta( $keep, '_zad_wk_service', (string) $m1 ); update_post_meta( $c1, '_zad_related', (string) $c2 );
$mkimg = function ( $name, $parent ) { $f = wp_upload_dir()['basedir'] . "/$name.jpg"; $im = imagecreatetruecolor( 40, 40 ); imagejpeg( $im, $f ); $id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => $name, 'post_status' => 'inherit' ), $f, $parent ); wp_update_attachment_metadata( $id, array( 'width' => 40, 'height' => 40, 'file' => _wp_relative_upload_path( $f ), 'sizes' => array() ) ); return $id; };
$a_free = $mkimg( 'mv-free', $m1 ); $a_used = $mkimg( 'mv-used', $m1 ); set_post_thumbnail( $keep, $a_used );
$menu = wp_create_nav_menu( 'قائمة اختبار ' . mt_rand() ); $mi_bad = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'moving', 'menu-item-object-id' => $m1, 'menu-item-status' => 'publish', 'menu-item-title' => 'نقل' ) ); $mi_ok = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $c1, 'menu-item-status' => 'publish', 'menu-item-title' => 'تنظيف' ) );
$RI = $wpdb->prefix . 'redirection_items'; $g = Red_Group::create( 'اختبار', 1 );
$r1 = Red_Item::create( array( 'url' => '/moving/old/', 'action_data' => array( 'url' => '/cleaning/' ), 'group_id' => $g->get_id(), 'action_type' => 'url', 'action_code' => 301, 'match_type' => 'url' ) );
$r2 = Red_Item::create( array( 'url' => '/x/', 'action_data' => array( 'url' => '/drain-cleaning/y/' ), 'group_id' => $g->get_id(), 'action_type' => 'url', 'action_code' => 301, 'match_type' => 'url' ) );
$r3 = Red_Item::create( array( 'url' => '/guide/keep/', 'action_data' => array( 'url' => '/sections/keep/' ), 'group_id' => $g->get_id(), 'action_type' => 'url', 'action_code' => 301, 'match_type' => 'url' ) );
$o = (array) get_option( '_memo_theme_options' ); $o['zad_service_slugs'] = 'pest-control,cleaning,moving,drain-cleaning'; $o['zad_hood_services'] = "تنظيف مكيفات\nتسليك مجاري\nنقل أثاث\nمكافحة حشرات"; $o['zad_bridges'] = "drain-cleaning > cleaning\ncleaning > pest-control"; update_option( '_memo_theme_options', $o );
update_option( 'wpseo_titles', array( 'title-moving' => 'a', 'metadesc-moving' => 'b', 'title-drain_cleaning' => 'c', 'title-cleaning' => 'keep', 'title-page' => 'keep' ) );

/* ---- 1) dry run: reads only ---- */
$snap = function () use ( $wpdb, $RI ) { return array( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM $RI" ), md5( serialize( get_option( '_memo_theme_options' ) ) . serialize( get_option( 'wpseo_titles' ) ) ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ); };
$before = $snap();
$inv = zad_pg_inventory();
ok( $before === $snap(), 'inventory (dry run) changes nothing' );
ok( 6 === count( $inv['ids'] ), 'finds all 6 posts of both types (publish/draft/trash/child, both slugs): ' . count( $inv['ids'] ) );
ok( $inv['revisions'] >= 2, 'counts revisions' ); ok( $inv['meta'] >= 4 && $inv['comments'] === 1 && $inv['rels'] >= 1, 'counts meta / comments / term links' );
ok( array( $mi_bad ) === $inv['menu'], 'only the menu item that points at moving is listed' );
ok( 4 === count( $inv['refs'] ) || 5 === count( $inv['refs'] ), 'refs in other pages: ' . count( $inv['refs'] ) );
ok( 2 === count( $inv['redirects'] ), 'redirect rules mentioning the old URLs are only REPORTED: ' . count( $inv['redirects'] ) );
ok( 1 === count( $inv['options'] ) && isset( $inv['options']['zad_service_slugs'] ), 'options: only the type-slug list is touched' ); ok( 3 === count( $inv['yoast'] ), 'yoast keys: 3' );
ok( array( $a_free ) === $inv['attach']['free'] && 2 === $inv['attach']['all'], 'attachments: 2, only the unused one is deletable' );
ok( 1 === count( $inv['links'] ) && $inv['links'][0][0] === $keep, 'reports the page that links to /moving/' );

/* ---- 2) real run through the admin page (wrong confirmation first) ---- */
$_POST = array( '_wpnonce' => wp_create_nonce( 'zad_pg' ), 'zad_pg_do' => 'run', 'zad_pg_word' => 'غلط', 'zad_pg_backup' => '1' ); $_REQUEST = $_POST;
ob_start(); zad_pg_page(); ob_end_clean(); ok( $before === $snap(), 'wrong confirmation word → nothing deleted' );
$_POST['zad_pg_word'] = 'احذف نهائياً'; unset( $_POST['zad_pg_backup'] ); $_REQUEST = $_POST;
ob_start(); zad_pg_page(); ob_end_clean(); ok( $before === $snap(), 'no backup tick → nothing deleted' );
$_POST['zad_pg_backup'] = '1'; $_POST['_wpnonce'] = 'bad'; $_REQUEST = $_POST;
add_filter( 'wp_die_handler', function () { return function () { throw new Exception( 'died' ); }; } ); $died = false;
try { ob_start(); zad_pg_page(); ob_end_clean(); } catch ( \Throwable $e ) { ob_end_clean(); $died = true; }
ok( $died && $before === $snap(), 'bad nonce → refused, nothing deleted' );
$_POST = array(); $_REQUEST = array();
$r = zad_pg_run( true );
ok( 6 === $r['posts'], 'deleted 6 posts: ' . $r['posts'] ); ok( 1 === $r['attachments'], 'deleted only the free attachment' ); ok( ! isset( $r['redirects'] ), 'run no longer touches redirects' );
ok( ! get_post( $a_free ) && get_post( $a_used ), 'used attachment stays, free one gone' );
foreach ( array( 'moving', 'drain_cleaning', 'drain-cleaning' ) as $t ) { ok( 0 === cnt( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = '$t'" ), "no rows left of $t" ); }
ok( 0 === cnt( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent IN (" . implode( ',', array( $m1, $d1 ) ) . ')' ), 'revisions gone' );
ok( 0 === cnt( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN (" . implode( ',', array( $m1, $m2, $m3, $m4, $d1, $d2 ) ) . ')' ) && 0 === cnt( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = $m1" ), 'meta + comments gone' );
ok( 0 === cnt( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = $m1" ) && ! term_exists( 'تصنيف نقل', 'moving_cat' ), 'term links + the type-only term gone' );
ok( ! get_post( $mi_bad ) && get_post( $mi_ok ), 'menu item to moving removed, the other kept' );
ok( (string) $c2 === (string) get_post_meta( $c1, '_zad_related', true ), 'unrelated field untouched' );
ok( (string) $c1 === (string) get_post_meta( $keep, '_zad_related', true ), 'related: only the removed ids stripped → "' . get_post_meta( $keep, '_zad_related', true ) . '"' );
ok( array( $c2 ) === array_map( 'intval', (array) get_post_meta( $keep, '_zad_cities_svc', true ) ), 'array field cleaned' );
ok( '' === (string) get_post_meta( $keep, '_zad_guides', true ) && ! metadata_exists( 'post', $keep, '_zad_guides' ) && ! metadata_exists( 'post', $keep, '_zad_wk_service' ), 'fields that became empty are deleted' );
ok( 3 === cnt( "SELECT COUNT(*) FROM $RI" ), 'ALL redirect rules kept (3)' );
$o = get_option( '_memo_theme_options' ); ok( 'pest-control,cleaning' === $o['zad_service_slugs'] && "تنظيف مكيفات\nتسليك مجاري\nنقل أثاث\nمكافحة حشرات" === $o['zad_hood_services'] && "drain-cleaning > cleaning\ncleaning > pest-control" === $o['zad_bridges'], 'type-slug list cleaned; district-services text and smart-link rules untouched' );
$y = get_option( 'wpseo_titles' ); ok( array( 'title-cleaning', 'title-page' ) === array_keys( $y ), 'only the two types\' yoast keys removed' );
ok( get_post( $keep ) && get_post( $c1 ) && get_post( $c2 ), 'unrelated pages untouched' );
$after = zad_pg_inventory(); ok( ! $after['ids'] && ! $after['options'] && ! $after['yoast'] && ! $after['refs'] && ! $after['menu'], 'second inventory is empty' );
$r2 = zad_pg_run( true ); ok( 0 === $r2['posts'] && 0 === $r2['refs'], 'running again is a harmless no-op' );

/* ---- 3) never comes back ---- */
$ghost = wp_insert_post( array( 'post_type' => 'moving', 'post_title' => 'شبح', 'post_status' => 'publish' ) );
$out = shell_exec( 'curl -s -m 30 -o /dev/null -w "%{http_code}" http://127.0.0.1:8099/wp-admin/edit.php?post_type=moving' );
$legacy = shell_exec( 'cd ' . escapeshellarg( $WPX ) . ' && php -r \'require "wp-load.php"; echo json_encode( array( post_type_exists("moving"), post_type_exists("drain_cleaning"), post_type_exists("drain-cleaning"), isset( $GLOBALS["zad_legacy_registered"] ) ? $GLOBALS["zad_legacy_registered"] : array() ) );\'' );
ok( '[false,false,false,[]]' === trim( (string) $legacy ), 'fresh request: not registered, and the legacy safety net does not re-register them even with a stray row: ' . $legacy );
wp_delete_post( $ghost, true );
ok( '' === zad_pg_clean_slugs( 'moving,drain-cleaning' ) && 'a,b' === zad_pg_clean_slugs( 'a, moving ,b,drain_cleaning' ), 'slug cleaner' );
update_option( '_memo_theme_options', array() ); $sl = shell_exec( 'cd ' . escapeshellarg( $WPX ) . ' && php -r \'require "wp-load.php"; echo json_encode( zad_role_slugs()["service"] );\'' ); ok( '["pest-control","cleaning"]' === trim( (string) $sl ), 'default service slugs (no option set): ' . $sl );

echo $fail ? "FAILED $fail of $n\n" : "all $n checks passed\n";
$log = $WPX . '/debug.log'; if ( is_file( $log ) ) { $t = (string) shell_exec( 'tail -n 30 ' . escapeshellarg( $log ) . ' | grep -i "zad-purge\|zad-legacy" ' ); if ( $t ) { echo "LOG: $t\n"; } }
