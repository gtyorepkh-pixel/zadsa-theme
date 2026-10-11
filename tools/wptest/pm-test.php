<?php
/** مدونة الحشرات → الأقسام. Run through pm-run.sh (server on :8099). */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 ); global $wpdb;
$B = 'http://127.0.0.1:8099'; $fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function head( $u ) { $o = (string) shell_exec( 'curl -s -I -m 20 ' . escapeshellarg( $u ) ); preg_match( '/^HTTP\/\S+ (\d+)/', $o, $m ); preg_match( '/^location:\s*(\S+)/mi', $o, $l ); return array( (int) ( $m[1] ?? 0 ), isset( $l[1] ) ? rawurldecode( trim( $l[1] ) ) : '' ); }
$GLOBALS['zad_gm_src'] = 'pests';
$o = get_option( 'pm_old' ); $ID = $o['ids']; $OLD = $o['urls'];
$rows = function ( $sel, $acts = array(), $slugs = array() ) { $r = array(); foreach ( zad_gm_guides() as $g ) { $r[ (int) $g->ID ] = array( 'sel' => in_array( (int) $g->ID, $sel, true ), 'act' => $acts[ (int) $g->ID ] ?? 'move', 'slug' => $slugs[ (int) $g->ID ] ?? '' ); } return $r; };
$posted = function ( $sel, $acts = array(), $slugs = array() ) use ( $rows ) { return array( 'rows' => $rows( $sel, $acts, $slugs ), 'terms' => array(), 'default_term' => 'auto', 'fix_inbound' => true ); };
$snap = function () use ( $wpdb ) { return md5( json_encode( array( $wpdb->get_results( "SELECT ID,post_type,post_name,post_parent,post_status,post_content,post_modified FROM {$wpdb->posts} WHERE post_type NOT IN ('revision') ORDER BY ID", ARRAY_A ), $wpdb->get_results( "SELECT post_id,meta_key,meta_value FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A ), get_option( 'zad_gm_log_pests' ) ) ) ); };

/* ---- the type is gone from the mu-plugin; the theme registers it while rows exist, nested like before ---- */
ok( false === strpos( file_get_contents( WPMU_PLUGIN_DIR . '/zad-core-cpt.php' ), 'pests-library' ), 'new mu-plugin does not register pests-library' );
ok( post_type_exists( 'pests-library' ) && is_post_type_hierarchical( 'pests-library' ), 'safety net registers it (hierarchical because rows have parents)' );
foreach ( $OLD as $k => $u ) { ok( $u === get_permalink( $ID[ $k ] ), "old URL identical after losing the mu-plugin: $k → " . urldecode( $u ) ); }
ok( false !== strpos( $OLD['ants'], '/pests-library/insects/ants/' ), 'nested URL kept' );

/* ---- inventory (reads only) ---- */
$P0 = $snap(); $inv = zad_gm_inventory(); $md = zad_gm_report_md( $inv );
ok( $P0 === $snap(), 'inventory writes nothing' );
ok( 8 === count( $inv['guides'] ) && 2 === $inv['with_parent'] && ! $inv['terms'] && 0 === $inv['no_term'], 'inventory: 8 posts, 2 with parent, no taxonomy' );
ok( false !== strpos( $md, 'مدونة الحشرات' ) && false !== strpos( $md, 'pests-library' ), 'report mentions the source' );
ok( 1 === count( $inv['conflicts'] ) && 'shared' === $inv['conflicts'][0]['slug'], 'one slug conflict (shared)' );
ok( isset( $inv['links']['content']['page'] ) && $inv['links']['menu'], 'links found in content and menus' );

/* ---- plan ---- */
$pl = zad_gm_plan( $posted( array( $ID['ants'], $ID['ants2'], $ID['shared'] ) ) );
ok( 'ok' === $pl['rows'][ $ID['ants'] ]['status'], 'plan: first «ants» ok' );
ok( 'skip' === $pl['rows'][ $ID['ants2'] ]['status'] && false !== strpos( $pl['rows'][ $ID['ants2'] ]['why'], 'نفس الـ slug' ), 'plan: second «ants» (other parent) refused: same flat slug' );
ok( 'skip' === $pl['rows'][ $ID['shared'] ]['status'], 'plan: conflict with a section refused' );
$pl2 = zad_gm_plan( $posted( array( $ID['ants'], $ID['ants2'] ), array(), array( $ID['ants2'] => 'rodent-ants' ) ) );
ok( 'ok' === $pl2['rows'][ $ID['ants2'] ]['status'] && false === $pl2['archive'], 'plan: a new slug resolves it; archive redirects not yet (posts left)' );
ok( $P0 === $snap(), 'plan writes nothing' );

/* ---- trial run on 3 ---- */
$rep = zad_gm_run( $posted( array( $ID['termites'], $ID['ants'], $ID['arabic'] ) ) );
ok( '' === $rep['error'] && 3 === $rep['moved'], 'trial: 3 moved ' . $rep['error'] );
foreach ( array( 'termites', 'ants', 'arabic' ) as $k ) { ok( 'sections' === get_post_type( $ID[ $k ] ), "$k is now a sections post, same ID" ); }
ok( (int) $ID['insects'] === (int) get_post( $ID['ants'] )->post_parent, 'post_parent kept in the DB (undo needs it)' );
flush_rewrite_rules( false );
ok( rtrim( home_url( '/sections/ants/' ), '/' ) === rtrim( get_permalink( $ID['ants'] ), '/' ), 'nested child now lives at the flat /sections/ants/' );
$h = head( $OLD['ants'] ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/ants/' ), 'old nested URL answers 301 to /sections/ants/: ' . $h[0] . ' ' . $h[1] );
$h = head( $OLD['termites'] ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/termites/' ), 'old termites URL 301' );
$h = head( $OLD['arabic'] ); ok( 301 === $h[0] && false !== strpos( $h[1], 'بق-الفراش' ), 'Arabic slug old URL 301 (decoded): ' . $h[0] . ' ' . $h[1] );
$h = head( get_permalink( $ID['ants'] ) ); ok( 200 === $h[0], 'new URL answers 200: ' . $h[0] );
$pg = get_post( $ID['page'] ); ok( false !== strpos( $pg->post_content, '/sections/ants/' ) && false !== strpos( $pg->post_content, '/pests-library/' ), 'links to moved posts rewritten; the archive link waits (posts remain)' );
ok( false !== strpos( get_post( $ID['s_normal'] )->post_content, '/sections/termites/' ), 'links in a sections post rewritten' );
ok( false !== strpos( get_post( $ID['ants'] )->post_content, '/pests-library-photo.jpg' ), 'a file name starting with /pests-library is not touched' );
ok( array( $ID['termites'], $ID['ants'] ) === array_map( 'intval', get_post_meta( $ID['svc'], '_zad_guides', true ) ), 'hand-picked links (_zad_guides) keep working (IDs unchanged)' );
ok( 'sections' === get_post_meta( $ID['mi'], '_menu_item_object', true ), 'menu item re-pointed to sections' );
ok( post_type_exists( 'pests-library' ), 'type still registered (posts remain)' );

/* ---- undo ---- */
$u = zad_gm_undo();
ok( 3 === $u['posts'] || 3 === $u['posts'] + $u['trash'], 'undo: 3 posts back' );
foreach ( array( 'termites', 'ants', 'arabic' ) as $k ) { ok( 'pests-library' === get_post_type( $ID[ $k ] ), "undo: $k back to pests-library" ); }
ok( (int) $ID['insects'] === (int) get_post( $ID['ants'] )->post_parent && 'ants' === get_post( $ID['ants'] )->post_name, 'undo: parent + slug restored' );
foreach ( array( 'ants', 'termites', 'arabic' ) as $k ) { ok( $OLD[ $k ] === get_permalink( $ID[ $k ] ), "undo: URL back to the old one ($k)" ); }
ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}redirection_items" ), 'undo: the 301 rules are gone' );
ok( false !== strpos( get_post( $ID['page'] )->post_content, '/pests-library/insects/ants/' ), 'undo: page links restored' );
ok( 'pests-library' === get_post_meta( $ID['mi'], '_menu_item_object', true ), 'undo: menu item back' );

/* ---- full run, archive redirects ---- */
$all = array(); foreach ( zad_gm_guides() as $g ) { $all[] = (int) $g->ID; }
$rep = zad_gm_run( $posted( $all, array( $ID['shared'] => 'merge' ), array( $ID['ants2'] => 'rodent-ants' ) ) );
ok( '' === $rep['error'] && $rep['archive'], 'full run: archive redirects added' );
ok( 'trash' === get_post_status( $ID['shared'] ), 'merge: the duplicate went to the trash (not deleted)' );
flush_rewrite_rules( false );
$h = head( home_url( '/pests-library/' ) ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/' ), 'archive /pests-library/ → /sections/: ' . $h[0] . ' ' . $h[1] );
$rg = $wpdb->get_col( 'SELECT url FROM ' . $wpdb->prefix . 'redirection_items WHERE regex = 1' ); ok( in_array( '^/pests\-library/page/([0-9]+)/?$', $rg, true ), 'one regex rule for /pests-library/page/N/ (the plug-in stand-in does not expand $1, so only the rule is checked)' );
$h = head( $OLD['shared'] ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/shared/' ), 'merged duplicate redirects to the existing section' );
$h = head( $OLD['insects'] ); ok( 301 === $h[0], 'parent (insects) 301' );
ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'pests-library' AND post_status NOT IN ('trash','auto-draft')" ), 'no live pests-library rows left' );
$st = (string) $wpdb->get_var( "SELECT post_status FROM {$wpdb->posts} WHERE ID = {$ID['draft']}" ); ok( 'draft' === $st && 'sections' === get_post_type( $ID['draft'] ), 'the draft moved as a draft' );
ok( false === strpos( get_post( $ID['page'] )->post_content, '/pests-library/' ), 'all links incl. the archive rewritten after the last move' );
$cmd = 'cd ' . escapeshellarg( $WPX ) . ' && php -r \'require "wp-load.php"; echo json_encode( array( post_type_exists("pests-library") ) );\'';
ok( '[false]' === trim( (string) shell_exec( $cmd ) ), 'fresh request: pests-library is no longer registered by anything (only the trashed duplicate remains)' );
ok( ! get_option( 'zad_gm_log' ), 'the guide merge log was never touched' );

echo $fail ? "FAILED $fail of $n\n" : "all $n checks passed\n";
