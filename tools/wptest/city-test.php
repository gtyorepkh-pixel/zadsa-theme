<?php
/** Logic + page tests of the city field / filter / tool on the real-WordPress site. Run: php tools/wptest/city-fixtures.php && php tools/wptest/city-apply.php && php tools/wptest/city-test.php (server on :8099) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fail = 0; $n = 0; $base = 'http://127.0.0.1:8099';
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function post( $type, $slug, $parent = null ) { $a = array( 'post_type' => $type, 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any', 'zad_all' => true ); if ( null !== $parent ) { $a['post_parent'] = $parent; } $p = get_posts( $a ); return $p ? $p[0]->ID : 0; }
$pillar = post( 'pest_control', 'pest-pillar' ); $dm_city = post( 'pest_control', 'dammam', $pillar ); $rd_city = post( 'pest_control', 'riyadh', $pillar ); $jd_city = post( 'pest_control', 'jeddah', $pillar );
$rd_flat = post( 'pest_control', 'roaches-riyadh' ); $dm_flat = post( 'pest_control', 'roaches-dammam' ); $term = post( 'pest_control', 'termites' ); $nozha = post( 'pest_control', 'nozha-pest' );
$z_gen = post( 'zad_service', 'general-service' ); $z_dm = post( 'zad_service', 'dammam-service' ); $c_pillar = post( 'cleaning', 'cleaning-pillar' );

/* ---- field: only on the three types ---- */
ok( array_values( zad_city_types() ) === array( 'cleaning', 'pest_control', 'zad_service' ), 'field types = cleaning, pest_control, zad_service: ' . implode( ',', zad_city_types() ) );
global $wp_meta_boxes;
foreach ( array( 'cleaning', 'pest_control', 'zad_service', 'drain_cleaning', 'page', 'post', 'zad_faq', 'zad_tool' ) as $pt ) { unset( $wp_meta_boxes[ $pt ] ); $po = get_post( post( 'pest_control', 'termites' ) ); set_current_screen( 'edit-' . $pt ); do_action( 'add_meta_boxes', $pt, $po ); }
$has = function ( $pt ) use ( $wp_meta_boxes ) { return isset( $wp_meta_boxes[ $pt ]['side']['high']['zad_city_box'] ); };
ok( $has( 'cleaning' ) && $has( 'pest_control' ) && $has( 'zad_service' ), 'city box registered on cleaning, pest_control, zad_service' );
ok( ! $has( 'drain_cleaning' ) && ! $has( 'page' ) && ! $has( 'post' ) && ! $has( 'zad_faq' ), 'city box NOT on drain_cleaning / page / post / zad_faq' );

/* ---- save path of the box ---- */
$save = function ( $id, $val, $nonce = true ) { $_POST = array( 'zad_city_nonce' => $nonce ? wp_create_nonce( 'zad_city_save' ) : 'bad', 'zad_city' => $val ); wp_update_post( array( 'ID' => $id, 'post_title' => get_the_title( $id ) ) ); $_POST = array(); };
$save( $term, 'jeddah' ); ok( 'jeddah' === get_post_meta( $term, '_zad_city', true ), 'save: a listed city' );
$save( $term, 'all' ); ok( 'all' === get_post_meta( $term, '_zad_city', true ), 'save: all' );
$save( $term, 'atlantis' ); ok( 'all' === get_post_meta( $term, '_zad_city', true ), 'save: an unknown city is refused (value unchanged)' );
$save( $term, 'dammam', false ); ok( 'all' === get_post_meta( $term, '_zad_city', true ), 'save: bad nonce changes nothing' );
$save( $term, '' ); ok( ! metadata_exists( 'post', $term, '_zad_city' ), 'save: empty = automatic (meta removed)' );
ob_start(); zad_city_box( get_post( $term ) ); $h = ob_get_clean(); ok( false !== strpos( $h, 'name="zad_city"' ) && false !== strpos( $h, 'value="all"' ) && false !== strpos( $h, 'غير محددة' ), 'box markup: select + «كل المدن» + shows the undetermined state' );

/* ---- resolution ---- */
ok( 'all' === zad_service_city_scope( $pillar ), 'pillar field all → all' );
ok( 'dammam' === zad_service_city_scope( $dm_city ) && 'riyadh' === zad_service_city_scope( $rd_city ), 'city pages under an «all» pillar keep their own city (slug)' );
ok( 'dammam' === zad_service_city_scope( post( 'pest_control', 'ants', $dm_city ) ), 'child of a city page inherits the city' );
ok( 'dammam' === zad_service_city_scope( $dm_flat ) && 'riyadh' === zad_service_city_scope( $rd_flat ), 'flat pages: the saved field' );
ok( '' === zad_service_city_scope( $term ), 'no field, no city in URL/parents: undetermined (not Riyadh)' );
ok( 'الرياض' === zad_current_city( $term )['name'] && 'default' === zad_current_city( $term )['source'], 'zad_current_city still falls back to الرياض for display' );
$tmp = wp_insert_post( array( 'post_type' => 'pest_control', 'post_title' => 'تحت الأم', 'post_name' => 'under-pillar', 'post_status' => 'publish', 'post_parent' => $pillar ) );
ok( 'all' === zad_service_city_scope( $tmp ), 'a page under an «all» pillar with nothing more specific inherits all' );
ok( 'all' !== zad_current_city( $tmp )['name'] && 'default' === zad_current_city( $tmp )['source'], 'zad_current_city never outputs the word «all» as a city' );
wp_delete_post( $tmp, true );
update_post_meta( $term, '_zad_city', 'الدمام' ); zad_city_reset(); ok( 'dammam' === zad_service_city_scope( $term ), 'an Arabic name saved in the field resolves to the slug' ); delete_post_meta( $term, '_zad_city' ); zad_city_reset();

/* ---- the filter ---- */
$ids = zad_city_ids_for( 'dammam' );
ok( in_array( $dm_flat, $ids, true ) && in_array( $pillar, $ids, true ) && ! in_array( $rd_flat, $ids, true ) && ! in_array( $term, $ids, true ), 'ids for dammam: its pages + all; not riyadh, not undetermined' );
$only_all = zad_city_ids_for( 'all' ); ok( in_array( $pillar, $only_all, true ) && ! in_array( $dm_flat, $only_all, true ), 'a page of «all» lists «all» pages only' );
$only_unset = zad_city_ids_for( '' ); ok( $only_all === $only_unset, 'an undetermined page lists «all» pages only' );
ok( array( 0 ) === zad_city_filter_args( array( 'post__in' => array( $rd_flat ) ), 'dammam' )['post__in'], 'filter: nothing left = post__in [0]' );
ok( ! in_array( $dm_flat, zad_city_filter_args( array( 'post__not_in' => array( $dm_flat ) ), 'dammam' )['post__in'], true ), 'filter honours post__not_in (WP ignores it next to post__in)' );
ok( ! in_array( $nozha, zad_city_filter_args( array(), 'dammam' )['post__in'], true ) && in_array( $nozha, zad_city_filter_args( array( 'zad_all' => true ), 'dammam' )['post__in'], true ), 'neighbourhood pages stay out of listings unless zad_all' );
$sb = function ( $id ) { delete_transient( 'x' ); $d = zad_services_sidebar_data( $id ); $o = array(); foreach ( $d as $g ) { foreach ( $g['items'] as $it ) { $o[] = $it[2]; } } return $o; };
$mentions_other = function ( $titles, $city ) { foreach ( $titles as $t ) { if ( zad_city_mentions_other( $t, $city ) ) { return $t; } } return ''; };
foreach ( array( array( $dm_flat, 'dammam' ), array( $rd_flat, 'riyadh' ), array( $dm_city, 'dammam' ), array( post( 'pest_control', 'ants', $jd_city ), 'jeddah' ) ) as $pc ) {
	list( $pid, $city ) = $pc;
	$rel = wp_list_pluck( zad_related_services( $pid, 6 )->posts, 'post_title' );
	ok( '' === $mentions_other( $sb( $pid ), $city ), "sidebar of $pid ($city) has no other city: " . $mentions_other( $sb( $pid ), $city ) );
	ok( '' === $mentions_other( $rel, $city ), "related of $pid ($city) has no other city: " . $mentions_other( $rel, $city ) );
}

/* ---- pages over HTTP: every list, every city ---- */
$cap = function ( $urls ) { return json_decode( shell_exec( 'php ' . escapeshellarg( __DIR__ . '/city-capture.php' ) . ' ' . implode( ' ', array_map( 'escapeshellarg', $urls ) ) ), true ); };
$urls = array( '/pest-control/roaches-dammam/' => 'dammam', '/pest-control/pest-pillar/dammam/ants/' => 'dammam', '/pest-control/pest-pillar/jeddah/ants/' => 'jeddah', '/pest-control/roaches-riyadh/' => 'riyadh', '/pest-control/pest-pillar/dammam/nozha-pest/' => 'dammam', '/cleaning/cleaning-pillar/dammam/ac/' => 'dammam' );
$pages = $cap( array_keys( $urls ) );
foreach ( $urls as $u => $city ) { foreach ( array( 'sidebar', 'related', 'bridge', 'qnet', 'hood' ) as $k ) { $bad = $mentions_other( $pages[ $u ][ $k ] ?? array(), $city ); ok( '' === $bad, "page $u list «$k» has no other city: $bad" ); } ok( ! empty( $pages[ $u ]['sidebar'] ) || false !== strpos( $u, 'nozha' ), "page $u still has a sidebar" ); }
$pt = $cap( array( '/pest-control/termites/' ) )['/pest-control/termites/'];
ok( ! $pt['sidebar'] && ! $pt['related'] && ! $pt['qnet'], 'undetermined page: sidebar / related / questions show nothing from any city' );
ok( ! array_filter( $pages['/pest-control/pest-pillar/dammam/nozha-pest/']['hood'], function ( $t ) { return false !== strpos( $t, 'الروضة' ); } ), 'hood page of Dammam: the Jeddah neighbour is gone' );
ok( in_array( 'التنظيف', $pages['/pest-control/roaches-dammam/']['bridge'], true ) && in_array( 'خدمات التنظيف في الدمام', $pages['/pest-control/roaches-dammam/']['bridge'], true ), 'bridges: main service of the same city (+ «all» pillar)' );

/* ---- hand-picked lists are never filtered; the editor warns ---- */
update_post_meta( $dm_flat, '_zad_related', array( $rd_flat ) ); zad_city_bump();
$rel = zad_related_services( $dm_flat, 3 ); ok( array( $rd_flat ) === wp_list_pluck( $rel->posts, 'ID' ), 'hand-picked «ذات صلة» (another city) is kept as picked' );
ob_start(); zad_related_picker( $dm_flat, array( $rd_flat, $dm_city ) ); $h = ob_get_clean();
ok( false !== strpos( $h, '⚠' ) && false !== strpos( $h, 'الرياض' ), 'editor: a warning next to a picked page of another city' );
delete_post_meta( $dm_flat, '_zad_related' ); zad_city_bump();

/* ---- caches follow a change immediately ---- */
$save( $rd_flat, 'dammam' ); ok( 'dammam' === zad_service_city_scope( $rd_flat ) && in_array( $rd_flat, zad_city_ids_for( 'dammam' ), true ), 'changing a page city updates the lists at once (version bump)' ); $save( $rd_flat, 'riyadh' );

/* ---- tool ---- */
$rows = zad_city_rows(); $by = array(); foreach ( $rows as $r ) { $by[ $r['id'] ] = $r; }
ok( $by[ $term ]['review'] && 0 === array_search( true, array_map( function ( $r ) { return $r['review']; }, $rows ), true ), 'tool: «review» rows first and flagged (undetermined)' );
ok( 'all' === $by[ post( 'pest_control', 'pest-pillar' ) ]['scope'], 'tool: pillar shows the saved «all»' );
$new = array( 'cleaning' => 'x' ); $c_unknown = wp_insert_term( 'القصيم', 'service_area' ); $kid = wp_insert_post( array( 'post_type' => 'zad_service', 'post_title' => 'خدمة القصيم', 'post_name' => 'qassim-service', 'post_status' => 'publish' ) ); wp_set_object_terms( $kid, array( (int) $c_unknown['term_id'] ), 'service_area' ); zad_city_bump();
$r = zad_city_row( get_post( $kid ) ); ok( '' === $r['sug'] && 'القصيم' === $r['unknown'], 'tool: a city term not in the list is offered for adding, not guessed' );
update_option( 'zad_city_extra', array( 'القصيم' => 'القصيم' ) ); zad_city_bump(); $r = zad_city_row( get_post( $kid ) ); ok( 'القصيم' === $r['sug'] && isset( zad_city_map()['القصيم'] ), 'tool: after adding the city it is suggested from the service-area term' );
$before = get_post_meta( $kid, '_zad_city', true ); zad_city_apply( array( $kid => 'القصيم' ) ); ok( 'القصيم' === get_post_meta( $kid, '_zad_city', true ), 'tool: apply sets the field (added city)' );
ok( zad_city_undo() >= 1 && ! metadata_exists( 'post', $kid, '_zad_city' ), 'tool: undo restores the previous state (field removed)' );
$ch = zad_city_apply( array( $z_gen => 'all', $z_dm => 'jeddah' ) ); ok( 2 === $ch, 'tool: bulk apply counts changes' ); ok( 'all' === get_post_meta( $z_gen, '_zad_city', true ), 'tool: «all» stored as all' ); zad_city_undo(); ok( '' === get_post_meta( $z_gen, '_zad_city', true ) && 'dammam' === get_post_meta( $z_dm, '_zad_city', true ), 'tool: undo returns the previous values' );
ok( 0 === zad_city_apply( array( $z_gen => 'atlantis', $kid => '' ) ), 'tool: unknown city / no-op rows are ignored' );
ob_start(); zad_city_tool_page(); $h = ob_get_clean();
ok( false !== strpos( $h, 'الخدمات × المدن' ) && false !== strpos( $h, 'راجع' ) && false !== strpos( $h, 'areaServed' ), 'tool page: matrix + review flags + areaServed column' );
wp_delete_post( $kid, true ); delete_option( 'zad_city_extra' ); wp_delete_term( (int) $c_unknown['term_id'], 'service_area' ); zad_city_bump();

/* ---- areaServed / schema: nothing changed ---- */
$sch = function ( $u ) { $h = shell_exec( 'curl -s -m 30 ' . escapeshellarg( 'http://127.0.0.1:8099' . $u ) ); preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', (string) $h, $m ); return implode( "\n", $m[1] ); };
$j = $sch( '/pest-control/pest-pillar/dammam/ants/' ); ok( false === strpos( $j, '"name":"all"' ) && false === strpos( $j, '"all"' ), 'schema: the word «all» never appears as a city' );
echo "$n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
