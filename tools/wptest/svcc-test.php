<?php
/** End-to-end test of inc/zad-svcconvert.php on a REAL WordPress (tools/wptest/setup.sh) with the Redirection stand-in. Run: php tools/wptest/svcc-test.php  (server: php -S 127.0.0.1:8099 -t $WPX $WPX/router.php) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
$base = home_url( '/' ); $fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function head( $url ) { $o = shell_exec( 'curl -s -I -m 15 ' . escapeshellarg( $url ) ); preg_match( '/^HTTP\/\S+ (\d+)/', (string) $o, $m ); preg_match( '/^location:\s*(\S+)/mi', (string) $o, $l ); return array( (int) ( $m[1] ?? 0 ), isset( $l[1] ) ? rawurldecode( $l[1] ) : '' ); }
function reset_site() {
	global $wpdb;
	foreach ( get_posts( array( 'post_type' => array( 'post', 'cleaning', 'pest_control', 'zad_service', 'page', 'sections', 'guide', 'zad_blog' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) as $id ) { wp_delete_post( $id, true ); }
	foreach ( array( 'zad_svcc_log', 'zad_svcc_map', 'standin_off' ) as $o ) { delete_option( $o ); }
	$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' ); $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_groups' );
	foreach ( array( 'service_cat', 'service_area', 'cleaning-sections', 'pest_sections', 'category' ) as $tax ) { foreach ( (array) get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t ) { if ( $t instanceof WP_Term && (int) $t->term_id !== (int) get_option( 'default_category' ) ) { wp_delete_term( $t->term_id, $tax ); } } }
	flush_rewrite_rules( true );
}
function mk( $type, $title, $slug, $o = array() ) { return wp_insert_post( array_merge( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_content' => 'محتوى ' . $title ), $o ) ); }
function red_rows() { global $wpdb; return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'redirection_items ORDER BY id', ARRAY_A ); }
function red_group_name( $gid ) { global $wpdb; return $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . $wpdb->prefix . 'redirection_groups WHERE id=%d', $gid ) ); }
function snapshot() { global $wpdb; return md5( json_encode( $wpdb->get_results( "SELECT ID,post_type,post_name,post_parent,post_status,post_content,post_title FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item','customize_changeset','auto-draft') ORDER BY ID", ARRAY_A ) ) ); }
function fixtures() {
	$c = array();
	$cat_r = wp_insert_term( 'خدمات الرياض', 'category' ); $cat_d = wp_insert_term( 'خدمات الدمام', 'category' ); $cat_j = wp_insert_term( 'خدمات جدة', 'category' );
	$c['p_clean'] = mk( 'cleaning', 'تنظيف مكيفات بالرياض', 'ac-cleaning-riyadh' );
	$c['p_pest']  = mk( 'pest_control', 'مكافحة حشرات بالدمام', 'pest-dammam' );
	$c['p_newant'] = mk( 'pest_control', 'مكافحة النمل بالرياض', 'ants-riyadh-new' );
	$c['a'] = mk( 'post', 'شركة تنظيف مكيفات بالرياض', 'old-ac-clean', array( 'post_category' => array( $cat_r['term_id'] ) ) );
	$c['b'] = mk( 'post', 'شركة مكافحة صراصير بالدمام', 'old-roach-dammam', array( 'post_category' => array( $cat_d['term_id'] ) ) );
	$c['c'] = mk( 'post', 'شركة تسليك مجاري بجدة', 'old-drain-jeddah', array( 'post_category' => array( $cat_j['term_id'] ) ) );
	$c['d'] = mk( 'post', 'شركة مكافحة نمل بالرياض القديمة', 'old-ants', array( 'post_category' => array( $cat_r['term_id'] ) ) );
	$c['e'] = mk( 'post', 'كيف أنظف المكيف بنفسي؟', 'how-to-clean-ac' );
	$links = '<a href="' . home_url( '/old-ac-clean/' ) . '">تنظيف</a> <a href="/old-ants/">نمل</a> <a href="' . home_url( '/old-roach-dammam/' ) . '#x">صراصير</a>';
	$c['page'] = mk( 'page', 'صفحة روابط', 'links-page', array( 'post_content' => $links ) );
	$c['cats'] = array( 'r' => $cat_r['term_id'], 'd' => $cat_d['term_id'], 'j' => $cat_j['term_id'] );
	return $c;
}
function rows_for( $c ) {
	return array(
		$c['a'] => array( 'act' => 'cleaning', 'slug' => 'old-ac-clean', 'sec' => 'تنظيف المكيفات', 'city' => '', 'parent' => $c['p_clean'], 'to' => '' ),
		$c['b'] => array( 'act' => 'pest', 'slug' => 'old-roach-dammam', 'sec' => 'الصراصير', 'city' => '', 'parent' => $c['p_pest'], 'to' => '' ),
		$c['c'] => array( 'act' => 'service', 'slug' => 'old-drain-jeddah', 'sec' => 'تسليك المجاري', 'city' => 'جدة', 'parent' => 0, 'to' => '' ),
		$c['d'] => array( 'act' => 'trash', 'slug' => 'old-ants', 'sec' => '', 'city' => '', 'parent' => 0, 'to' => (string) $c['p_newant'] ),
		$c['e'] => array( 'act' => 'none', 'slug' => 'how-to-clean-ac', 'sec' => '', 'city' => '', 'parent' => 0, 'to' => '' ),
	);
}

if ( getenv( 'FIXTURES_ONLY' ) ) { reset_site(); $c = fixtures(); echo json_encode( $c ); exit; }
/* ===== 0. the page is registered, suggestions ===== */
ok( function_exists( 'zad_svcc_run' ) && post_type_exists( 'cleaning' ) && post_type_exists( 'pest_control' ) && zad_svcc_red_active(), 'environment: theme loaded, types registered, Redirection (stand-in) active' );
$mk = function ( $t ) { return (object) array( 'post_title' => $t, 'post_type' => 'post', 'ID' => 0 ); };
foreach ( array( 'شركة مكافحة صراصير بالرياض' => 'pest', 'شركة مكافحة بق الفراش بجدة' => 'pest', 'رش مبيدات بالدمام' => 'pest', 'شركة طارد الحمام بالرياض' => 'pest', 'شركة نمل أبيض بالخبر' => 'pest',
	'شركة تنظيف خزانات بالرياض' => 'cleaning', 'شركة غسيل كنب بجدة' => 'cleaning', 'شركة تنظيف مسابح بالرياض' => 'cleaning', 'شركة تنظيف ستائر وموكيت' => 'cleaning', 'شركة تنظيف مكيفات بالرياض' => 'cleaning',
	'شركة نقل عفش بالرياض' => 'service', 'شركة تسليك مجاري بجدة' => 'service', 'شركة تركيب مكيفات بالدمام' => 'service', 'شركة جلي بلاط بالرياض' => 'service', 'شركة تعبئة فريون بجدة' => 'service', 'شركة تخزين أثاث بالرياض' => 'service',
	'كيف أنظف المكيف؟' => 'none', 'ما هي أسباب الصراصير' => 'none', 'كم سعر تنظيف الخزانات' => 'none' ) as $title => $want ) {
	$got = zad_svcc_suggest_action( $mk( $title ), $want === 'none' ? array() : array( 'خدمات الرياض' ) );
	ok( $got === $want, "suggest «{$title}»: {$got} (expected {$want})" );
}
ok( 'none' === zad_svcc_suggest_action( $mk( 'نصائح عامة للبيت' ), array( 'مقالات' ) ), 'a real article (no «خدمات» category) → none' );
ok( 'service' === zad_svcc_suggest_action( $mk( 'شركة الأمل للخدمات المنزلية' ), array( 'خدمات الرياض' ) ), 'a services page with no keyword keeps the old behaviour (service)' );

ok( zad_svcc_score( 'شركة مكافحة صراصير بالدمام', 'مكافحة حشرات بالدمام' ) >= 2 && zad_svcc_score( 'شركة مكافحة صراصير في الدمام', 'مكافحة حشرات بالدمام' ) >= 2, 'similarity: «بالدمام» ≈ «في الدمام» ≈ «الدمام»' );
ok( zad_svcc_score( 'شركة تنظيف مكيفات بالرياض', 'تنظيف مكيفات بالرياض' ) >= 3 && zad_svcc_score( 'شركة تنظيف مكيفات بالرياض', 'مكافحة حشرات بجدة' ) < 2, 'similarity: same service + city high, different service + city low' );
ok( zad_svcc_best( 'شركة مكافحة صراصير بالدمام', array( 1 => 'مكافحة حشرات بالرياض', 2 => 'مكافحة حشرات بالدمام', 3 => 'تنظيف خزانات بالدمام' ) ) === 2, 'best parent = same service AND city' ); ok( 0 === zad_svcc_best( 'شركة نقل عفش بالرياض', array( 1 => 'مكافحة حشرات بجدة' ) ), 'no suggestion when nothing fits' );
/* ===== 1. Redirection must be active ===== */
reset_site(); $c = fixtures(); $before = snapshot();
update_option( 'standin_off', 1 );
$out = shell_exec( 'php -r ' . escapeshellarg( 'require "' . $WPX . '/wp-load.php"; wp_set_current_user(1); echo json_encode( zad_svcc_run( array( ' . $c['a'] . ' => array( "act"=>"cleaning","slug"=>"old-ac-clean","parent"=>0 ) ), true ) );' ) );
$j = json_decode( $out, true ); ok( is_array( $j ) && false !== strpos( $j['error'] ?? '', 'Redirection' ) && 0 === ( $j['done'] ?? 1 ), 'Redirection not active → refuses, with a clear message: ' . ( $j['error'] ?? $out ) );
delete_option( 'standin_off' ); ok( snapshot() === $before, 'nothing was touched when Redirection is off' );

/* ===== 2. the plan ===== */
$rows = rows_for( $c ); $plan = zad_svcc_plan( $rows );
ok( 1 === $plan['counts']['cleaning'] && 1 === $plan['counts']['pest'] && 1 === $plan['counts']['service'] && 1 === $plan['counts']['trash'] && 1 === $plan['counts']['none'], 'plan counts per action: ' . json_encode( $plan['counts'] ) );
ok( 0 === $plan['skipped'], 'no problems in the clean fixtures: ' . json_encode( $plan['problems'], JSON_UNESCAPED_UNICODE ) );
$pa = $plan['rows'][ $c['a'] ]; ok( urldecode( $pa['new_url'] ) === $base . 'cleaning/ac-cleaning-riyadh/old-ac-clean/' && 'cleaning' === $pa['dest'], 'cleaning URL is /cleaning/{parent}/{slug}/ : ' . urldecode( $pa['new_url'] ) );
$pb = $plan['rows'][ $c['b'] ]; ok( urldecode( $pb['new_url'] ) === $base . 'pest-control/pest-dammam/old-roach-dammam/', 'pest URL is /pest-control/{parent}/{slug}/ : ' . urldecode( $pb['new_url'] ) );
$pc = $plan['rows'][ $c['c'] ]; ok( urldecode( $pc['new_url'] ) === $base . zad_type_base( 'zad_service' ) . '/old-drain-jeddah/', 'service URL as before' );
$pd = $plan['rows'][ $c['d'] ]; ok( 'trash' === $pd['act'] && $pd['target'] === $c['p_newant'] && urldecode( $pd['redir']['to'] ) === $base . 'pest-control/ants-riyadh-new/' && 'add' === $pd['redir']['state'], 'delete row: redirect to the chosen page: ' . urldecode( $pd['redir']['to'] ) );
ok( 'old-ac-clean' === $pa['redir']['from'] && 'add' === $pa['redir']['state'], 'redirect from the old path is planned' );
ok( 4 === count( $plan['redirs'] ), 'four redirects would be added' );

/* ===== 3. validation: no delete without a target, drafts, duplicates, chains ===== */
$r2 = $rows; $r2[ $c['d'] ]['to'] = ''; $p2 = zad_svcc_plan( $r2 ); ok( 'skip' === $p2['rows'][ $c['d'] ]['status'] && false !== strpos( $p2['rows'][ $c['d'] ]['why'], 'ممنوع الحذف بلا تحويل' ), 'delete with no target → refused' );
$draft = mk( 'pest_control', 'مكافحة النمل - مسودة', 'ants-draft', array( 'post_status' => 'draft' ) ); $r2[ $c['d'] ]['to'] = (string) $draft; $p2 = zad_svcc_plan( $r2 ); ok( 'skip' === $p2['rows'][ $c['d'] ]['status'] && false !== strpos( $p2['rows'][ $c['d'] ]['why'], 'غير منشورة' ), 'target still a draft → refused: ' . $p2['rows'][ $c['d'] ]['why'] );
$r2[ $c['d'] ]['to'] = (string) $c['d']; ok( 'skip' === zad_svcc_plan( $r2 )['rows'][ $c['d'] ]['status'], 'target = itself → refused' );
$r2 = $rows; $r2[ $c['d'] ]['to'] = (string) $c['a']; $p2 = zad_svcc_plan( $r2 ); ok( 'ok' === $p2['rows'][ $c['d'] ]['status'] && urldecode( $p2['rows'][ $c['d'] ]['redir']['to'] ) === $base . 'cleaning/ac-cleaning-riyadh/old-ac-clean/', 'target that is moved in the same run → uses its NEW url (no chain)' );
$r2 = $rows; $r2[ $c['a'] ]['act'] = 'trash'; $r2[ $c['a'] ]['to'] = (string) $c['p_clean']; $r2[ $c['d'] ]['to'] = (string) $c['a']; ok( 'skip' === zad_svcc_plan( $r2 )['rows'][ $c['d'] ]['status'], 'target that is deleted in the same run → refused' );
$r2 = $rows; $dup = mk( 'cleaning', 'نسخة', 'old-ac-clean', array( 'post_parent' => $c['p_clean'] ) ); $p2 = zad_svcc_plan( $r2 ); ok( 'skip' === $p2['rows'][ $c['a'] ]['status'] && false !== strpos( $p2['rows'][ $c['a'] ]['why'], 'مكرر' ), 'duplicate FULL url (same parent + slug in cleaning) → refused' ); wp_delete_post( $dup, true );
$dup2 = mk( 'cleaning', 'نسخة بأم مختلفة', 'old-ac-clean' ); $p2 = zad_svcc_plan( $r2 ); ok( 'ok' === $p2['rows'][ $c['a'] ]['status'], 'same slug under ANOTHER parent is a different URL → allowed' ); wp_delete_post( $dup2, true );
$r2 = $rows; $r2[ $c['b'] ]['slug'] = 'old-ac-clean'; $r2[ $c['b'] ]['parent'] = $c['p_clean']; $r2[ $c['b'] ]['act'] = 'cleaning'; $p2 = zad_svcc_plan( $r2 ); ok( 'skip' === $p2['rows'][ $c['b'] ]['status'] || 'skip' === $p2['rows'][ $c['a'] ]['status'], 'two rows planning the same final URL → the second is refused' );
$r2 = $rows; $r2[ $c['a'] ]['parent'] = $c['a']; ok( 'skip' === zad_svcc_plan( $r2 )['rows'][ $c['a'] ]['status'], 'a page cannot be its own parent' );
$r2 = $rows; $r2[ $c['a'] ]['parent'] = $c['p_pest']; ok( 'skip' === zad_svcc_plan( $r2 )['rows'][ $c['a'] ]['status'], 'parent must be of the destination type' );
// existing redirect on the old URL → not duplicated
$g = zad_svcc_red_group(); zad_svcc_red_add( $g, 'old-roach-dammam', home_url( '/somewhere-else/' ) );
$p2 = zad_svcc_plan( $rows ); ok( 'exists' === $p2['rows'][ $c['b'] ]['redir']['state'] && $p2['rows'][ $c['b'] ]['warn'], 'a redirect already on the old URL (any group) is detected, not duplicated: ' . ( $p2['rows'][ $c['b'] ]['warn'][0] ?? '' ) );
global $wpdb; $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' );
// the NEW url already redirected elsewhere → straight to the final destination
$final_page = mk( 'page', 'الوجهة النهائية', 'final-dest' ); zad_svcc_red_add( $g, 'cleaning/ac-cleaning-riyadh/old-ac-clean', home_url( '/mid/' ) ); zad_svcc_red_add( $g, 'mid', home_url( '/final-dest/' ) );
$p2 = zad_svcc_plan( $rows ); ok( 'ok' === $p2['rows'][ $c['a'] ]['status'] && urldecode( $p2['rows'][ $c['a'] ]['redir']['to'] ) === $base . 'final-dest/', 'new URL already redirected (2 hops) → old URL goes straight to the final destination: ' . urldecode( $p2['rows'][ $c['a'] ]['redir']['to'] ) );
ok( (bool) array_filter( $p2['rows'][ $c['a'] ]['warn'], function ( $w ) { return false !== strpos( $w, 'عليه تحويل قائم' ); } ), 'and the page is told that its new URL is redirected away' );
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' );
zad_svcc_red_add( $g, 'loopa', home_url( '/loopb/' ) ); zad_svcc_red_add( $g, 'loopb', home_url( '/loopa/' ) ); list( , , $loop ) = zad_svcc_red_final( home_url( '/loopa/' ) ); ok( true === $loop, 'a redirect loop is detected' );
$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' ); wp_delete_post( $final_page, true ); wp_delete_post( $draft, true );
// regex rule counts as an existing redirect
$wpdb->insert( $wpdb->prefix . 'redirection_items', array( 'url' => '^/old-ac-.*', 'match_url' => 'regex', 'regex' => 1, 'group_id' => $g, 'status' => 'enabled', 'action_type' => 'url', 'action_code' => 301, 'action_data' => '/x/', 'match_type' => 'url' ) );
ok( zad_svcc_red_lookup( 'old-ac-clean' ) && zad_svcc_red_lookup( 'old-ac-clean' )['regex'], 'an enabled regex rule that matches the old URL counts as an existing redirect' ); $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'redirection_items' );
ok( null === zad_svcc_red_lookup( 'cleaning/zzz-none' ), 'no redirect → null' );
// trash disabled
// (EMPTY_TRASH_DAYS is a constant: checked by reading the guard in the plan code path)
ok( defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS > 0, 'trash is enabled in this site (needed for «delete with redirect»)' );

/* ===== 4. the run ===== */
reset_site(); $c = fixtures(); $rows = rows_for( $c ); $before = snapshot();
$a_id = $c['a']; $orig_content = get_post_field( 'post_content', $c['page'] ); $orig_date = get_post_field( 'post_date', $a_id );
// the old URLs work before
list( $s0 ) = head( $base . 'old-ac-clean/' ); ok( 200 === $s0, 'before: the old URL is a normal page (200)' );
$rep = zad_svcc_run( $rows, false );
ok( '' === $rep['error'] && 3 === $rep['moved'] && 1 === $rep['trashed'] && 7 === $rep['red_added'], // 4 pages + the 3 emptied category archives (Riyadh, Dammam, Jeddah)
 'run report' . ' ' . 'run report: ' . json_encode( array_diff_key( $rep, array( 'lines' => 1 ) ), JSON_UNESCAPED_UNICODE ) );
ok( 'cleaning' === get_post_type( $c['a'] ) && (int) get_post_field( 'post_parent', $c['a'] ) === $c['p_clean'] && urldecode( get_permalink( $c['a'] ) ) === $base . 'cleaning/ac-cleaning-riyadh/old-ac-clean/', 'A is now a cleaning page under its parent: ' . urldecode( get_permalink( $c['a'] ) ) );
ok( 'pest_control' === get_post_type( $c['b'] ) && urldecode( get_permalink( $c['b'] ) ) === $base . 'pest-control/pest-dammam/old-roach-dammam/', 'B is now a pest_control page: ' . urldecode( get_permalink( $c['b'] ) ) );
ok( 'zad_service' === get_post_type( $c['c'] ), 'C is now a zad_service' );
ok( 'trash' === get_post_status( $c['d'] ), 'D is in the TRASH (not deleted)' ); ok( 'post' === get_post_type( $c['e'] ) && 'publish' === get_post_status( $c['e'] ), 'E (action none) is untouched' );
ok( get_post_field( 'post_date', $a_id ) === $orig_date && get_post_field( 'post_content', $a_id ) === 'محتوى شركة تنظيف مكيفات بالرياض', 'same ID, date and content' );
ok( has_term( 'تنظيف المكيفات', 'cleaning-sections', $c['a'] ), 'section term in cleaning-sections' ); ok( has_term( 'الصراصير', 'pest_sections', $c['b'] ), 'section term in pest_sections' ); ok( has_term( 'تسليك المجاري', 'service_cat', $c['c'] ) && has_term( 'جدة', 'service_area', $c['c'] ), 'service_cat + service_area for the service' );
$rr = red_rows(); ok( 7 === count( $rr ) && 1 === count( array_unique( array_column( $rr, 'group_id' ) ) ), 'seven redirects in Redirection (4 pages + 3 category archives), one group' ); ok( 'تحويل الخدمات' === red_group_name( $rr[0]['group_id'] ), 'in the group «تحويل الخدمات»' );
$byfrom = array(); foreach ( $rr as $x ) { $byfrom[ trim( rawurldecode( $x['url'] ), '/' ) ] = rawurldecode( $x['action_data'] ); }
ok( ( $byfrom['old-ac-clean'] ?? '' ) === $base . 'cleaning/ac-cleaning-riyadh/old-ac-clean/', '301 old-ac-clean → new cleaning URL' ); ok( ( $byfrom['old-ants'] ?? '' ) === $base . 'pest-control/ants-riyadh-new/', '301 of the deleted page → the chosen page' );
ok( 301 === (int) $rr[0]['action_code'], 'status code 301' );
// links in content
$pc = get_post_field( 'post_content', $c['page'] ); ok( false !== strpos( $pc, home_url( '/cleaning/ac-cleaning-riyadh/old-ac-clean/' ) ) && false !== strpos( $pc, '"/pest-control/ants-riyadh-new/"' ) && false !== strpos( $pc, '/pest-control/pest-dammam/old-roach-dammam/#x' ), 'internal links rewritten (absolute, relative → the DELETED page links go to the final page, anchors kept): ' . $pc );
ok( $rep['links'] >= 3, 'link count reported: ' . $rep['links'] );
// HTTP
foreach ( array( 'old-ac-clean' => 'cleaning/ac-cleaning-riyadh/old-ac-clean/', 'old-roach-dammam' => 'pest-control/pest-dammam/old-roach-dammam/', 'old-drain-jeddah' => zad_type_base( 'zad_service' ) . '/old-drain-jeddah/', 'old-ants' => 'pest-control/ants-riyadh-new/' ) as $old => $new ) {
	list( $s, $loc ) = head( $base . $old . '/' ); ok( 301 === $s && $loc === $base . $new, "curl -I /$old/ → 301 $loc (expected $new)" );
	list( $s2 ) = head( $base . $new ); ok( 200 === $s2, "curl -I /$new → $s2 (expected 200)" );
}
list( $s3 ) = head( $base . 'how-to-clean-ac/' ); ok( 200 === $s3, 'the untouched article still works' );
$log = get_option( 'zad_svcc_log' ); ok( 4 === count( $log['items'] ) && 1 === count( $log['trash'] ) && 7 === count( $log['red'] ), 'log has items / trash / redirects' );

/* ===== 5. run again: duplicates are not re-added; moved posts are not source any more ===== */
$rep2 = zad_svcc_run( $rows, false ); ok( 0 === $rep2['moved'] && 0 === $rep2['red_added'], 'a second run does nothing (rows are no longer sources): ' . json_encode( array_diff_key( $rep2, array( 'lines' => 1, 'skip' => 1 ) ) ) );

/* ===== 6. undo everything ===== */
$u = zad_svcc_undo(); ok( 3 === $u['posts'] && 1 === $u['trash'] && 7 === $u['red'], 'undo report: ' . json_encode( $u ) );
ok( 'post' === get_post_type( $c['a'] ) && 'old-ac-clean' === get_post_field( 'post_name', $c['a'] ) && 0 === (int) get_post_field( 'post_parent', $c['a'] ), 'A is back: type, slug, parent' );
ok( 'post' === get_post_type( $c['b'] ) && 'post' === get_post_type( $c['c'] ), 'B and C are back' ); ok( 'publish' === get_post_status( $c['d'] ) && 'old-ants' === get_post_field( 'post_name', $c['d'] ), 'D is out of the trash, published, with its slug' );
ok( 0 === count( red_rows() ), 'all redirects removed from Redirection' ); ok( get_post_field( 'post_content', $c['page'] ) === $orig_content, 'the content links are restored' );
ok( ! term_exists( 'تنظيف المكيفات', 'cleaning-sections' ) && ! term_exists( 'الصراصير', 'pest_sections' ) && ! term_exists( 'تسليك المجاري', 'service_cat' ), 'terms created by the run are removed' );
ok( false === get_option( 'zad_svcc_log' ) && false === get_option( 'zad_svcc_map' ), 'log and map cleared' );
ok( snapshot() === $before, 'database rows of posts are IDENTICAL to before the run (undo is complete)' );
list( $s4 ) = head( $base . 'old-ac-clean/' ); ok( 200 === $s4, 'curl: the old URL is a normal page again (200)' ); list( $s5 ) = head( $base . 'old-ants/' ); ok( 200 === $s5, 'curl: the formerly trashed page is back (200)' ); list( $s6 ) = head( $base . 'cleaning/ac-cleaning-riyadh/old-ac-clean/' ); ok( 404 === $s6, 'curl: the new URL is gone (404)' );

/* ===== 7. a trashed page whose status was draft/private/pending comes back as it was ===== */
reset_site(); $c = fixtures(); wp_update_post( array( 'ID' => $c['d'], 'post_status' => 'private' ) ); $rows = rows_for( $c ); zad_svcc_run( array( $c['d'] => $rows[ $c['d'] ] ), false ); ok( 'trash' === get_post_status( $c['d'] ), 'private page trashed' ); zad_svcc_undo(); ok( 'private' === get_post_status( $c['d'] ), 'undo restores the ORIGINAL status (private), not draft' );

/* ===== 8. existing redirect on the old URL is kept and not duplicated; undo does not remove it ===== */
reset_site(); $c = fixtures(); $rows = rows_for( $c ); $g = zad_svcc_red_group( 'مجموعة أخرى' ); $own = zad_svcc_red_add( $g, 'old-roach-dammam', home_url( '/elsewhere/' ) );
$rep = zad_svcc_run( $rows, false ); ok( 6 === $rep['red_added'] && 1 === count( $rep['red_exists'] ), 'existing redirect not duplicated (3 pages + 3 archives added, 1 existing): ' . json_encode( array( $rep['red_added'], $rep['red_exists'] ) ) );
$u = zad_svcc_undo(); ok( 1 === count( red_rows() ) && (int) red_rows()[0]['id'] === $own, 'undo removes only OUR redirects; the pre-existing one stays' );

/* ===== 9. chain avoidance for category archives + undo of archive redirects ===== */
reset_site(); $c = fixtures(); $rows = rows_for( $c ); $rows = array( $c['c'] => $rows[ $c['c'] ] ); // the only post of «خدمات جدة»
$rep = zad_svcc_run( $rows, false ); $cat_old = zad_svcc_path_key( get_term_link( $c['cats']['j'], 'category' ) ); $found = false; foreach ( red_rows() as $x ) { if ( trim( rawurldecode( $x['url'] ), '/' ) === $cat_old ) { $found = rawurldecode( $x['action_data'] ); } }
ok( $found && 0 === strpos( $found, $base ), 'the emptied category archive is redirected to the new section: ' . $found ); zad_svcc_undo(); ok( 0 === count( red_rows() ), 'and removed by undo' );


/* ===== 10. «تجربة حقول زاد» drafts ===== */
reset_site(); $c = fixtures(); $labdir = $WPX . '/wp-content/mu-plugins/zad-lab'; @mkdir( $labdir );
$newdraft = mk( 'pest_control', 'مكافحة النمل بالرياض - الجديدة', 'ants-new-lab', array( 'post_status' => 'draft' ) );
file_put_contents( $labdir . '/ants.json', json_encode( array( 'meta' => 1, 'items' => array( array( 'source_id' => $c['d'], 'title' => 'مكافحة النمل بالرياض - الجديدة', 'post_id' => $newdraft ), array( 'source_id' => 999999, 'title' => 'ليس موجوداً' ) ) ), JSON_UNESCAPED_UNICODE ) );
$lab = zad_svcc_lab_map( true ); ok( isset( $lab[ $c['d'] ] ) && $lab[ $c['d'] ]['new'] === $newdraft, 'lab map: source_id → the new draft id' ); ok( isset( $lab[999999] ) && 0 === $lab[999999]['new'], 'a source with no page found has new=0' );
$p = zad_svcc_plan( array( $c['d'] => array( 'act' => 'trash', 'to' => (string) $newdraft ) ) ); $x = $p['rows'][ $c['d'] ];
ok( 'skip' === $x['status'] && false !== strpos( $x['why'], 'تجربة حقول زاد' ) && false !== strpos( $x['why'], 'انشرها' ), 'draft in the lab → NOT executed, with the «publish it first» warning: ' . $x['why'] ); ok( 'publish' === get_post_status( $c['d'] ), 'and the old page is untouched' );
wp_update_post( array( 'ID' => $newdraft, 'post_status' => 'publish' ) ); $p = zad_svcc_plan( array( $c['d'] => array( 'act' => 'trash', 'to' => (string) $newdraft ) ) ); ok( 'ok' === $p['rows'][ $c['d'] ]['status'], 'once the draft is published → the row is allowed' );
file_put_contents( $labdir . '/ants.json', '{bad json' ); ok( is_array( zad_svcc_lab_map( true ) ), 'a broken JSON file never breaks the tool' );
file_put_contents( $labdir . '/ants.json', json_encode( array( array( 'source_id' => $c['d'], 'title' => 'مكافحة النمل بالرياض - الجديدة' ) ) , JSON_UNESCAPED_UNICODE ) ); $lab = zad_svcc_lab_map( true ); ok( $lab[ $c['d'] ]['new'] === $newdraft, 'new page found by its TITLE when the file has no id' );
file_put_contents( $labdir . '/ants.json', json_encode( array( array( 'source_id' => $c['d'], 'title' => 'x' ) ) ) ); update_post_meta( $newdraft, '_zad_lab_source_id', (string) $c['d'] ); ok( zad_svcc_lab_map( true )[ $c['d'] ]['new'] === $newdraft, 'new page found by the source-id meta' );
wp_update_post( array( 'ID' => $newdraft, 'post_status' => 'draft' ) );

/* ===== 11. the admin screen itself (HTML) ===== */
$_POST = array(); $_REQUEST = array(); ob_start(); zad_svcc_page(); $h = ob_get_clean();
ok( false !== strpos( $h, 'ليها صفحة جديدة' ) && false !== strpos( $h, 'zadsv-act' ) && false !== strpos( $h, 'نقل إلى صفحات التنظيف (cleaning)' ) && false !== strpos( $h, 'حذف مع تحويل' ), 'screen: action column, the 5 actions and the «ليها صفحة جديدة» badge' );
ok( false !== strpos( $h, 'zadsv-f-act' ) && false !== strpos( $h, 'zadsv-f-city' ) && false !== strpos( $h, 'zadsv-f-lab' ) && false !== strpos( $h, 'zadsv-apply' ), 'screen: filters (action, city, has-new-page) and the bulk-apply button' );
ok( preg_match( '/name="act\[' . $c['d'] . '\]".*?<option value="trash" selected/s', $h ), 'the row with a lab draft is suggested as «حذف مع تحويل»' ); ok( false !== strpos( $h, 'name="to[' . $c['d'] . ']" value="' . $newdraft . '"' ), '…with the new draft prefilled as the target' );
ok( preg_match( '/name="act\[' . $c['e'] . '\]".*?<option value="none" selected/s', $h ), 'the real article is «لا شيء»' );
// preview
$_POST = array( 'zad_sv' => 'preview', '_wpnonce' => wp_create_nonce( 'zad_sv' ), 'act' => array( $c['a'] => 'cleaning', $c['b'] => 'pest', $c['d'] => 'trash' ), 'slug' => array( $c['a'] => 'old-ac-clean', $c['b'] => 'old-roach-dammam', $c['d'] => 'old-ants' ), 'sec' => array( $c['a'] => 'تنظيف المكيفات', $c['b'] => 'الصراصير' ), 'city' => array(), 'parent' => array( $c['a'] => $c['p_clean'], $c['b'] => $c['p_pest'] ), 'to' => array( $c['d'] => (string) $newdraft ) );
$_REQUEST = $_POST; ob_start(); zad_svcc_page(); $h = ob_get_clean();
ok( false !== strpos( $h, 'معاينة فقط' ) && false !== strpos( $h, 'ستُضاف في Redirection' ) && false !== strpos( $h, '/cleaning/ac-cleaning-riyadh/old-ac-clean/' ), 'preview: summary + the from ← to list' ); ok( false !== strpos( $h, 'مشاكل' ) && false !== strpos( $h, 'ما زالت draft' ) || false !== strpos( $h, 'انشرها' ), 'preview: the unpublished lab draft is listed as a problem' );
ok( false !== strpos( $h, 'name="zad_sv" value="run"' ), 'preview: the real-run button' ); ok( 'old-ac-clean' === get_post_field( 'post_name', $c['a'] ) && 'post' === get_post_type( $c['a'] ), 'preview changed NOTHING' );
update_option( 'standin_off', 1 );
$sub = shell_exec( 'php -r ' . escapeshellarg( 'require "' . $WPX . '/wp-load.php"; wp_set_current_user(1); $_POST=array("zad_sv"=>"run","_wpnonce"=>wp_create_nonce("zad_sv"),"act"=>array(' . $c['a'] . '=>"cleaning")); $_REQUEST=$_POST; ob_start(); zad_svcc_page(); echo ob_get_clean();' ) );
delete_option( 'standin_off' ); ok( false !== strpos( (string) $sub, 'Redirection غير مفعّلة' ) && 'post' === get_post_type( $c['a'] ), 'screen: a clear notice when Redirection is off, and nothing runs' );
// ajax search (own process: wp_send_json ends the request)
$sub = shell_exec( 'php -r ' . escapeshellarg( 'require "' . $WPX . '/wp-load.php"; wp_set_current_user(1); $_GET=array("q"=>"النمل","n"=>wp_create_nonce("zad_svcc_search"),"action"=>"zad_svcc_search"); $_REQUEST=$_GET; do_action("wp_ajax_zad_svcc_search");' ) );
$j = json_decode( (string) $sub, true ); ok( is_array( $j ) && $j && isset( $j[0]['id'], $j[0]['title'], $j[0]['url'] ) && 'publish' !== '' , 'search endpoint answers JSON with id / title / url: ' . substr( (string) $sub, 0, 80 ) );
$sub = shell_exec( 'php -r ' . escapeshellarg( 'require "' . $WPX . '/wp-load.php"; wp_set_current_user(1); $_GET=array("q"=>"النمل","n"=>"bad","action"=>"zad_svcc_search"); $_REQUEST=$_GET; do_action("wp_ajax_zad_svcc_search");' ) ); ok( '-1' === trim( (string) $sub ), 'search endpoint refuses a bad nonce (-1)' );
exec( 'rm -rf ' . escapeshellarg( $labdir ) );

echo $fail ? "\n$fail of $n FAILED\n" : "svcconvert (real WP): all $n passed\n";
exit( $fail ? 1 : 0 );
