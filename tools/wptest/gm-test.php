<?php
/** Guide→sections merge: plan, trial run on 3, HTTP checks, undo, full run (conflict resolved), archives. php tools/wptest/gm-fixtures.php && php tools/wptest/gm-test.php  (server on :8099) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
$B = 'http://127.0.0.1:8099'; $fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function head( $u ) { $o = (string) shell_exec( 'curl -s -I -m 20 ' . escapeshellarg( $u ) ); preg_match( '/^HTTP\/\S+ (\d+)/', $o, $m ); preg_match( '/^location:\s*(\S+)/mi', $o, $l ); return array( (int) ( $m[1] ?? 0 ), isset( $l[1] ) ? rawurldecode( trim( $l[1] ) ) : '' ); }
function by( $slug, $type = 'guide' ) { $p = get_posts( array( 'post_type' => $type, 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any' ) ); return $p ? $p[0]->ID : 0; }
global $wpdb;
$g1 = by( 'ac-guide' ); $g2 = by( 'termites-guide' ); $g3 = by( 'shared-slug' ); $g7 = by( 'no-term' ); $g8 = by( rawurldecode( '%d9%85%d9%83%d9%8a%d9%81-%d8%b3%d8%a8%d9%84%d8%aa' ) ); $g9 = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='guide' AND post_title='دليل مسودة بلا slug'" ); $s1 = by( 'shared-slug', 'sections' );
$parts = function () use ( $wpdb ) { return array( $wpdb->get_results( "SELECT ID,post_type,post_name,post_status,post_content,post_excerpt,post_date,post_modified FROM {$wpdb->posts} WHERE post_type NOT IN ('revision') ORDER BY ID", ARRAY_A ), $wpdb->get_results( "SELECT post_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE meta_key NOT IN ('_edit_lock') ORDER BY post_id, meta_key, meta_value", ARRAY_A ), $wpdb->get_results( "SELECT object_id,term_taxonomy_id FROM {$wpdb->term_relationships} ORDER BY 1,2", ARRAY_A ), $wpdb->get_results( 'SELECT url,action_data,regex FROM ' . $wpdb->prefix . 'redirection_items ORDER BY id', ARRAY_A ), get_option( 'zad_test_opt_links' ) ); };
$snap = function () use ( $parts ) { return md5( json_encode( $parts() ) ); };
$P0 = $parts();
$orig = $snap();
$rows = function ( $sel, $acts = array(), $slugs = array() ) { $r = array(); foreach ( zad_gm_guides() as $g ) { $r[ (int) $g->ID ] = array( 'sel' => in_array( (int) $g->ID, $sel, true ), 'act' => $acts[ (int) $g->ID ] ?? 'move', 'slug' => $slugs[ (int) $g->ID ] ?? '' ); } return $r; };
$posted = function ( $sel, $acts = array(), $slugs = array() ) use ( $rows ) { return array( 'rows' => $rows( $sel, $acts, $slugs ), 'terms' => array(), 'default_term' => 'auto', 'fix_inbound' => true ); };

/* ---- plan ---- */
$plan = zad_gm_plan( $posted( array( $g1, $g2, $g3, $g7, $g8, $g9 ) ) );
ok( 'skip' === $plan['rows'][ $g3 ]['status'] && false !== strpos( $plan['rows'][ $g3 ]['why'], 'تعارض' ), 'plan: a slug conflict is refused (not moved) with the two options explained' );
ok( 'ok' === $plan['rows'][ $g1 ]['status'] && 'add' === $plan['rows'][ $g1 ]['redir'] && 'exists' === $plan['rows'][ $g2 ]['redir'], 'plan: 301 to add for ac-guide; none duplicated for termites-guide (a rule already exists)' );
ok( 'ok' === $plan['rows'][ $g9 ]['status'] && 'none' === $plan['rows'][ $g9 ]['redir'], 'plan: a draft without slug moves, no 301' );
ok( count( $plan['inbound'] ) >= 1, 'plan: existing rule pointing at a guide URL found (would become a chain)' );
$tn = array(); foreach ( $plan['terms'] as $t ) { $tn[ $t['name'] ] = $t['to']; }
ok( 'new' === $tn['السباكة'] && is_int( $tn['تنظيف المكيفات'] ), 'plan: term suggestions: similar name → existing, none → new' );
ok( $snap() === $orig, 'plan/preview wrote nothing' );
$m2 = zad_gm_plan( $posted( array( $g3 ), array( $g3 => 'merge' ) ) ); ok( 'ok' === $m2['rows'][ $g3 ]['status'] && $s1 === $m2['rows'][ $g3 ]['merge_to'], 'plan: «merge with 301» is accepted for the conflict row' );
$m3 = zad_gm_plan( $posted( array( $g3 ), array(), array( $g3 => 'shared-slug-2' ) ) ); ok( 'ok' === $m3['rows'][ $g3 ]['status'] && 'shared-slug-2' === $m3['rows'][ $g3 ]['slug'], 'plan: a new slug resolves the conflict' );
$m4 = zad_gm_plan( $posted( array( $g3 ), array(), array( $g3 => 'normal-section' ) ) ); ok( 'skip' === $m4['rows'][ $g3 ]['status'], 'plan: a new slug that is also taken is refused' );

/* ---- trial: 3 guides ---- */
$before = array( 'date' => get_post_field( 'post_date', $g1 ), 'mod' => get_post_field( 'post_modified', $g1 ), 'content' => get_post_field( 'post_content', $g1 ), 'ex' => get_post_field( 'post_excerpt', $g1 ), 'author' => get_post_field( 'post_author', $g1 ), 'metas' => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key NOT LIKE '\\_yoast\\_wpseo\\_primary%' ORDER BY meta_id", $g1 ), ARRAY_A ) );
$rep = zad_gm_run( $posted( array( $g1, $g2, $g7 ) ) );
ok( '' === $rep['error'] && 3 === $rep['moved'], 'trial: 3 moved (' . $rep['error'] . ')' );
foreach ( array( $g1, $g2, $g7 ) as $i ) { ok( 'sections' === get_post_type( $i ), "trial: $i is now a «sections» post (same ID)" ); }
ok( 'ac-guide' === get_post_field( 'post_name', $g1 ) && $before['date'] === get_post_field( 'post_date', $g1 ) && $before['mod'] === get_post_field( 'post_modified', $g1 ) && $before['author'] === get_post_field( 'post_author', $g1 ), 'trial: slug, date, modified date, author unchanged' );
ok( str_replace( '/guide/other-guide/', '', $before['content'] ) !== '' && $before['ex'] === get_post_field( 'post_excerpt', $g1 ), 'trial: excerpt untouched' );
$metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key NOT LIKE '\\_yoast\\_wpseo\\_primary%' AND meta_key NOT LIKE '\\_zad\\_gm%' ORDER BY meta_id", $g1 ), ARRAY_A );
ok( $metas == $before['metas'], 'trial: every meta (Yoast title/description/focus keyword…) kept' );
$tn1 = wp_get_object_terms( $g1, 'best_sections', array( 'fields' => 'slugs' ) ); ok( array( 'ac' ) === $tn1, 'trial: ac-guide now in best_sections «ac» (mapped by similar name)' );
ok( ! wp_get_object_terms( $g1, 'best_guide', array( 'fields' => 'ids' ) ), 'trial: best_guide removed from it' );
$tn7 = wp_get_object_terms( $g7, 'best_sections', array( 'fields' => 'slugs' ) ); ok( array( 'general-cleaning' ) === $tn7, 'trial: a guide without category goes under «التنظيف العام» (created): ' . implode( ',', $tn7 ) );
$ac = get_term_by( 'slug', 'ac', 'best_sections' ); ok( (string) $ac->term_id === get_post_meta( $g1, '_yoast_wpseo_primary_best_sections', true ) && ! metadata_exists( 'post', $g1, '_yoast_wpseo_primary_best_guide' ), 'trial: Yoast primary term moved to best_sections with the new term id' );
$rules = $wpdb->get_results( 'SELECT url, action_data, regex FROM ' . $wpdb->prefix . 'redirection_items', ARRAY_A ); $urls = wp_list_pluck( $rules, 'action_data', 'url' );
ok( isset( $urls['/guide/ac-guide/'] ) && false !== strpos( $urls['/guide/ac-guide/'], '/sections/ac-guide/' ), 'trial: 301 /guide/ac-guide/ → /sections/ac-guide/ in Redirection' );
ok( false !== strpos( $urls['/guide/termites-guide/'], '/elsewhere/' ), 'trial: the existing rule of termites-guide untouched, no duplicate' );
ok( 1 === count( array_filter( $rules, function ( $r ) { return '/guide/termites-guide/' === $r['url']; } ) ), 'trial: no duplicate rules' );
ok( false !== strpos( $urls['/old-ac/'], '/sections/ac-guide/' ), 'trial: an old rule that pointed to /guide/ac-guide/ now points straight to the final URL (no chain)' );
$grp = $wpdb->get_var( 'SELECT name FROM ' . $wpdb->prefix . 'redirection_groups g JOIN ' . $wpdb->prefix . 'redirection_items i ON i.group_id = g.id WHERE i.url = "/guide/ac-guide/"' ); ok( 'دمج الأدلة' === $grp, 'trial: the rules live in the «دمج الأدلة» group' );
ok( ! array_filter( array_keys( $urls ), function ( $u ) { return '/guide/' === $u || false !== strpos( $u, 'best-guide' ); } ), 'trial: archive rules NOT added yet (guides remain) — /guide/ archive keeps working' );
ok( false !== strpos( get_post_field( 'post_content', $g1 ), '/guide/other-guide/' ), 'trial: a link to a guide that did not move is untouched' );
ok( false !== strpos( get_post_field( 'post_content', by( 'archive-links', 'page' ) ), '/guide/shared-slug/' ) && false !== strpos( get_post_field( 'post_content', by( 'archive-links', 'page' ) ), '/guide/"' ), 'trial: archive links not rewritten yet' );
ok( false !== strpos( (string) get_post_meta( by( 'archive-links', 'page' ), '_zad_test_link', true ), '/sections/ac-guide/' ), 'trial: links in post meta rewritten' );
ok( false !== strpos( wp_json_encode( get_option( 'zad_test_opt_links' ) ), 'sections\/termites-guide' ), 'trial: links in options rewritten' );
ok( false !== strpos( get_post_field( 'post_content', by( 'archive-links', 'page' ) ), 'uploads/guide-photo.jpg' ), 'trial: an upload named guide-photo.jpg is not treated as a link' );
$h = head( $B . '/guide/ac-guide/' ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/ac-guide/' ), 'HTTP: old URL → 301 to the new (' . $h[0] . ' ' . $h[1] . ')' );
$h = head( $B . '/sections/ac-guide/' ); ok( 200 === $h[0], 'HTTP: new URL → 200' );
$h = head( $B . '/guide/' ); ok( 200 === $h[0], 'HTTP: /guide/ archive still 200 while guides remain' );
$h = head( $B . '/guide/termites-guide/' ); ok( 301 === $h[0] && false !== strpos( $h[1], '/elsewhere/' ), 'HTTP: pre-existing rule still answers' );
$html = (string) shell_exec( 'curl -s -m 30 ' . escapeshellarg( $B . '/sections/ac-guide/' ) );
preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $mm ); $ld = implode( "\n", $mm[1] );
ok( false !== strpos( $ld, '"BreadcrumbList"' ) && false !== strpos( $ld, '/sections/ac-guide/' ) && false === strpos( $ld, '/guide/ac-guide' ), 'page: Schema has a BreadcrumbList and URLs of the new place only' );
ok( false !== strpos( $ld, '"Article"' ) || false !== strpos( $ld, 'BlogPosting' ), 'page: Article schema present' );
ok( ! preg_match( '/rel="canonical" href="[^"]*\/guide\//', $html ), 'page: canonical is not the old URL' );
$log = zad_gm_log_get(); ok( 3 === count( $log['items'] ), 'trial: undo log has 3 items' );

/* ---- undo restores everything ---- */
$u = zad_gm_undo();
if ( $snap() !== $orig ) { $P1 = $parts(); foreach ( $P0 as $i => $x ) { if ( $x != $P1[ $i ] ) { echo "DIFF in part $i\n"; if ( is_array( $x ) ) { foreach ( $x as $k => $row ) { if ( ! in_array( $row, $P1[ $i ], false ) ) { echo '  missing: ' . json_encode( $row, JSON_UNESCAPED_UNICODE ) . "\n"; } } foreach ( $P1[ $i ] as $row ) { if ( ! in_array( $row, $x, false ) ) { echo '  extra: ' . json_encode( $row, JSON_UNESCAPED_UNICODE ) . "\n"; } } } } } }
ok( 3 === $u['posts'] && $snap() === $orig, 'undo: database identical to the start (posts, meta, terms, redirects, options)' );
ok( ! get_term_by( 'slug', 'general-cleaning', 'best_sections' ), 'undo: the term it created is gone' );

/* ---- full run: conflict row merged with a 301, archives redirected ---- */
$all = array(); foreach ( zad_gm_guides() as $g ) { $all[] = (int) $g->ID; }
$rep = zad_gm_run( $posted( $all, array( $g3 => 'merge' ) ) );
ok( '' === $rep['error'] && $rep['archive'], 'full: ran, archives redirected (' . $rep['error'] . ')' );
ok( 'trash' === get_post_status( $g3 ) && 'guide' === get_post_type( $g3 ), 'full: the merged guide is in the trash (not deleted)' );
$h = head( $B . '/guide/shared-slug/' ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/shared-slug/' ), 'full: conflict guide URL → 301 to the existing sections post' );
$h = head( $B . '/guide/' ); ok( 301 === $h[0] && false !== strpos( $h[1], '/sections/' ), 'full: /guide/ archive → /sections/' );
$h = head( $B . '/best-guide/tanks/' ); ok( 301 === $h[0] && false !== strpos( $h[1], '/best_sections/tanks/' ), 'full: /best-guide/tanks/ → /best_sections/tanks/ (' . $h[1] . ')' );
$rg = $wpdb->get_col( 'SELECT url FROM ' . $wpdb->prefix . 'redirection_items WHERE regex = 1' ); ok( in_array( '^/guide/page/([0-9]+)/?$', $rg, true ), 'full: one regex rule for /guide/page/N/' );
$pc = get_post_field( 'post_content', by( 'archive-links', 'page' ) ); ok( false !== strpos( $pc, 'href="/sections/"' ) && false !== strpos( $pc, '/best_sections/tanks/' ) && false !== strpos( $pc, '/sections/shared-slug/' ), 'full: archive and term links in content rewritten' );
$chain = 0; foreach ( $wpdb->get_results( 'SELECT url, action_data FROM ' . $wpdb->prefix . 'redirection_items WHERE regex = 0', ARRAY_A ) as $r ) { $t = (string) wp_parse_url( $r['action_data'], PHP_URL_PATH ); if ( $t && $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'redirection_items WHERE regex = 0 AND url = %s', $t ) ) ) { $chain++; } }
ok( 0 === $chain, "full: no redirect chains ($chain)" );
ok( 'sections' === get_post_type( $g8 ) && '%d9%85%d9%83%d9%8a%d9%81-%d8%b3%d8%a8%d9%84%d8%aa' === get_post_field( 'post_name', $g8 ), 'full: the Arabic-slug guide keeps its slug' );
$h = head( $B . '/guide/%d9%85%d9%83%d9%8a%d9%81-%d8%b3%d8%a8%d9%84%d8%aa/' ); ok( 301 === $h[0], 'full: Arabic slug old URL answers 301 (' . $h[0] . ')' );
ok( '' === (string) get_post_field( 'post_name', $g9 ) && 'sections' === get_post_type( $g9 ) && 'draft' === get_post_status( $g9 ), 'full: the draft without slug moved as a draft' );
ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='guide' AND post_status IN ('publish','draft','private','future','pending')" ), 'full: no live guide left' );
$u = zad_gm_undo();
if ( $snap() !== $orig ) { $P1 = $parts(); foreach ( $P0 as $i => $x ) { if ( $x != $P1[ $i ] ) { echo "DIFF in part $i\n"; if ( is_array( $x ) ) { foreach ( $x as $row ) { if ( ! in_array( $row, $P1[ $i ], false ) ) { echo '  missing: ' . substr( json_encode( $row, JSON_UNESCAPED_UNICODE ), 0, 300 ) . "\n"; } } foreach ( $P1[ $i ] as $row ) { if ( ! in_array( $row, $x, false ) ) { echo '  extra: ' . substr( json_encode( $row, JSON_UNESCAPED_UNICODE ), 0, 300 ) . "\n"; } } } } } }
ok( $snap() === $orig, 'full undo: database identical to the start' );
echo "$n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
