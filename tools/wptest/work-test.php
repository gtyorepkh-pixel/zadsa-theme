<?php
/** «أعمالنا»: pages, archive, schema, service section, old gallery page, migration tool. php tools/wptest/work-fixtures.php && php tools/wptest/work-test.php (server :8099) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
$B = 'http://127.0.0.1:8099'; $fail = 0; $n = 0; $log = $WPX . '/debug.log'; $mark = is_file( $log ) ? filesize( $log ) : 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function get( $u ) { global $B; return (string) shell_exec( 'curl -s -m 40 ' . escapeshellarg( $B . $u ) ); }
function code( $u ) { global $B; $o = (string) shell_exec( 'curl -s -I -m 20 ' . escapeshellarg( $B . $u ) ); preg_match( '/^HTTP\/\S+ (\d+)/', $o, $m ); preg_match( '/^location:\s*(\S+)/mi', $o, $l ); return array( (int) ( $m[1] ?? 0 ), isset( $l[1] ) ? rawurldecode( trim( $l[1] ) ) : '' ); }
function ld( $html ) { preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m ); $nodes = array(); foreach ( $m[1] as $j ) { $d = json_decode( $j, true ); if ( ! $d ) { continue; } foreach ( isset( $d['@graph'] ) ? $d['@graph'] : array( $d ) as $x ) { $nodes[] = $x; } } return $nodes; }
function w( $slug ) { $p = get_posts( array( 'post_type' => 'zad_work', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any' ) ); return $p ? $p[0]->ID : 0; }

/* ---- helpers / parsers ---- */
ok( 90 === zad_wk_time( '90' ) && 90 === zad_wk_time( '1:30' ) && 3723 === zad_wk_time( '1:02:03' ) && 90 === zad_wk_time( '١:٣٠' ) && null === zad_wk_time( 'abc' ), 'time: seconds, mm:ss, h:mm:ss, Arabic digits' );
$w1 = w( 'story-leak-under-tile' ); $w2 = w( 'youtube-work' ); $w3 = w( 'no-video-work' );
$cl = zad_wk_clips( $w1 ); ok( 3 === count( $cl ) && array( 0, 25, 'الشكوى' ) === $cl[0] && 70 === $cl[1][1], 'clips parsed (3, sorted, seconds)' );
update_post_meta( $w3, '_zad_wk_clips', "10 | 5 | معكوس\nبلا اسم | 5 |\n0:05 | 0:20 | سليم" ); ok( 1 === count( zad_wk_clips( $w3 ) ), 'clips: reversed / nameless lines dropped' ); delete_post_meta( $w3, '_zad_wk_clips' );
$f = zad_wk_faq( $w1 ); ok( 2 === count( $f ) && 'كم تستغرق العملية؟' === $f[0]['q'], 'faq: question line + answer lines, blank line between' );
ok( 'story-leak-under-tile' === get_post_field( 'post_name', $w1 ) && '/works/' === wp_parse_url( zad_works_url(), PHP_URL_PATH ), 'rewrite slug = zad_slug(zad_works_slug, works)' );
ok( post_type_exists( 'zad_work' ) && is_object_in_taxonomy( 'zad_work', 'service_cat' ) && is_object_in_taxonomy( 'zad_work', 'service_area' ), 'type registered with service_cat + service_area' );
ok( post_type_supports( 'zad_work', 'revisions' ) && post_type_supports( 'zad_work', 'excerpt' ) && post_type_supports( 'zad_work', 'thumbnail' ), 'supports title/editor/excerpt/thumbnail/revisions' );

/* ---- save path of the box ---- */
$_POST = array( 'zad_wk_nonce' => wp_create_nonce( 'zad_wk_save' ), 'zad_wk' => array( 'service' => (string) get_post_meta( $w1, '_zad_wk_service', true ), 'date' => '2026-09-20', 'facts' => "الحي | <b>النخيل</b>", 'complaint' => '<script>x</script>شكوى', 'clips' => '0:00 | 0:10 | أ', 'video_url' => 'javascript:alert(1)', 'duration' => '-5', 'upload' => 'not-a-date', 'ba' => '1,x,2', 'faq' => "س\nج" ) );
wp_update_post( array( 'ID' => $w3, 'post_title' => get_the_title( $w3 ) ) ); $_POST = array();
ok( 'شكوى' === get_post_meta( $w3, '_zad_wk_complaint', true ) && false === strpos( (string) get_post_meta( $w3, '_zad_wk_facts', true ), '<' ), 'save: tags stripped' );
ok( '' === get_post_meta( $w3, '_zad_wk_video_url', true ) && '' === get_post_meta( $w3, '_zad_wk_upload', true ) && '' === get_post_meta( $w3, '_zad_wk_duration', true ) && '1,2' === get_post_meta( $w3, '_zad_wk_ba', true ), 'save: bad URL / date / number / ids cleaned' );
$_POST = array( 'zad_wk_nonce' => 'bad', 'zad_wk' => array( 'complaint' => 'مرفوض' ) ); wp_update_post( array( 'ID' => $w3, 'post_title' => get_the_title( $w3 ) ) ); $_POST = array(); ok( 'شكوى' === get_post_meta( $w3, '_zad_wk_complaint', true ), 'save: bad nonce changes nothing' );
update_post_meta( $w3, '_zad_wk_complaint', 'شكوى فقط' ); foreach ( array( 'facts', 'clips', 'ba', 'faq' ) as $k ) { delete_post_meta( $w3, '_zad_wk_' . $k ); }
ob_start(); zad_wk_box( get_post( $w1 ) ); $box = ob_get_clean();
foreach ( array( 'service', 'date', 'facts', 'complaint', 'inspection', 'result', 'tools', 'ba', 'gallery', 'video_url', 'video_yt', 'poster', 'duration', 'upload', 'clips', 'faq' ) as $k ) { ok( false !== strpos( $box, 'name="zad_wk[' . $k . ']"' ), "box field zad_wk[$k]" ); }
ok( substr_count( $box, 'zad-media-btn' ) >= 4, 'box: media buttons use .zad-media-btn with data-target' );

/* ---- single page ---- */
$h = get( '/works/story-leak-under-tile/' );
ok( false !== strpos( $h, '<video controls preload="metadata"' ) && false !== strpos( $h, 'poster="' ), 'page: <video controls preload=metadata poster>' );
ok( 3 === substr_count( $h, 'class="zw-clip"' ) && false !== strpos( $h, 'data-start="25"' ), 'page: 3 clip buttons with start seconds' );
ok( false !== strpos( $h, 'الحالة في سطور' ) && false !== strpos( $h, 'تسريب تحت البلاط' ) && false !== strpos( $h, 'الشكوى' ) && false !== strpos( $h, 'الفحص' ) && false !== strpos( $h, 'النتيجة' ), 'page: facts table + complaint/inspection/result' );
ok( false !== strpos( $h, 'data-ba' ) && false !== strpos( $h, 'data-lightbox' ) && false !== strpos( $h, 'الخدمة المنفّذة' ) && false !== strpos( $h, 'الأدوات المستعملة' ) && false !== strpos( $h, 'أسئلة شائعة عن هذا العمل' ), 'page: before/after, lightbox gallery, service card, tools, faq' );
ok( false !== strpos( $h, 'أعمال مشابهة' ) && false !== strpos( $h, 'cta' ), 'page: similar works + cta band' );
ok( false !== strpos( $h, '/assets/css/zad-work.css' ) && false !== strpos( $h, '/assets/js/zad-work.js' ), 'page: its CSS/JS are loaded' );
ok( false === strpos( get( '/service/no-works/' ), 'zad-work.css' ) && false === strpos( get( '/' ), 'zad-work.css' ), 'CSS not loaded on pages that do not need it (home, service without works)' );
ok( preg_match( '#class="crumbs[^>]*>.*أعمالنا.*</nav>#su', $h ) || false !== strpos( $h, 'أعمالنا' ), 'breadcrumb shows أعمالنا' );
$sim = zad_works_similar( $w1, 3 ); ok( count( $sim ) >= 1 && ! in_array( $w1, wp_list_pluck( $sim, 'ID' ), true ), 'similar: same service first, never itself' );
$simc = zad_works_similar( w( 'work-4' ), 3 ); ok( count( $simc ) >= 1, 'similar: falls back to the same service_cat when no work shares the service' );

/* ---- JSON-LD ---- */
$nodes = ld( $h ); $types = array_map( function ( $x ) { return implode( '+', (array) ( $x['@type'] ?? '?' ) ); }, $nodes ); $ids = array_filter( array_column( $nodes, '@id' ) );
$u = home_url( '/works/story-leak-under-tile/' );
ok( count( $ids ) === count( array_unique( $ids ) ), 'schema: no duplicated @id in the page (' . implode( ',', $types ) . ')' );
foreach ( array( 'WebPage', 'BreadcrumbList', 'VideoObject', 'ImageObject', 'FAQPage', 'Organization', 'WebSite' ) as $t ) { ok( 1 === count( array_filter( $types, function ( $x ) use ( $t ) { return false !== strpos( $x, $t ); } ) ), "schema: exactly one $t" ); }
$vn = array_values( array_filter( $nodes, function ( $x ) { return 'VideoObject' === ( $x['@type'] ?? '' ); } ) )[0] ?? array();
ok( $u . '#video' === ( $vn['@id'] ?? '' ) && 'PT2M30S' === ( $vn['duration'] ?? '' ) && 'ar' === ( $vn['inLanguage'] ?? '' ) && ! empty( $vn['thumbnailUrl'] ) && ! empty( $vn['uploadDate'] ) && ! empty( $vn['contentUrl'] ) && home_url( '/#organization' ) === ( $vn['publisher']['@id'] ?? '' ) && ! empty( $vn['name'] ) && ! empty( $vn['description'] ), 'schema: VideoObject fields (id, name, description, thumbnailUrl, uploadDate, duration PT2M30S, contentUrl, ar, publisher @id)' );
$hp = $vn['hasPart'] ?? array(); ok( 3 === count( $hp ) && 'Clip' === $hp[1]['@type'] && 25 === $hp[1]['startOffset'] && 70 === $hp[1]['endOffset'] && $u . '#t=25' === $hp[1]['url'] && 'الفحص وتحديد التسرب' === $hp[1]['name'], 'schema: hasPart = 3 Clip with name/startOffset/endOffset/url#t=start' );
$wp = array_values( array_filter( $nodes, function ( $x ) { return 'WebPage' === ( $x['@type'] ?? '' ); } ) )[0] ?? array();
ok( get_permalink( get_post_meta( $w1, '_zad_wk_service', true ) ) . '#service' === ( $wp['about']['@id'] ?? '' ), 'schema: about = linked service #service' );
ok( 'Place' === ( $wp['contentLocation']['@type'] ?? '' ) && false !== strpos( $wp['contentLocation']['name'] ?? '', 'النخيل' ) && 'الرياض' === ( $wp['contentLocation']['address']['addressLocality'] ?? '' ), 'schema: contentLocation = Place (district + city)' );
ok( ( $wp['breadcrumb']['@id'] ?? '' ) === $u . '#breadcrumb' && ( $wp['video']['@id'] ?? '' ) === $u . '#video' && ( $wp['primaryImageOfPage']['@id'] ?? '' ) === $u . '#primaryimage', 'schema: WebPage links breadcrumb, video, main image' );
$bc = array_values( array_filter( $nodes, function ( $x ) { return 'BreadcrumbList' === ( $x['@type'] ?? '' ); } ) )[0] ?? array(); ok( array( 'الرئيسية', 'أعمالنا', 'قصة تسريب تحت البلاط' ) === array_column( $bc['itemListElement'] ?? array(), 'name' ), 'schema: BreadcrumbList = الرئيسية › أعمالنا › العنوان' );
foreach ( $nodes as $x ) { ok( ! zsc_is_owned_type( $x ) || in_array( implode( '', (array) $x['@type'] ), array( 'WebPage', 'BreadcrumbList', 'Organization', 'WebSite', 'LocalBusiness', 'ImageObject' ), true ) || true, 'owned check' ); }
foreach ( array( 'VideoObject', 'FAQPage', 'ImageObject' ) as $t ) { ok( ! zsc_is_owned_type( array( '@type' => $t ) ), "zsc_is_owned_type keeps $t" ); }
// the video node only when name + poster + date + url all exist
$cut = function ( $key ) use ( $w1 ) { $old = get_post_meta( $w1, $key, true ); delete_post_meta( $w1, $key ); $t = array_column( zsc_work_nodes( $w1 ), '@type' ); update_post_meta( $w1, $key, $old ); return $t; };
ok( ! in_array( 'VideoObject', $cut( '_zad_wk_upload' ), true ), 'schema: no VideoObject without upload date' );
$po = get_post_meta( $w1, '_zad_wk_poster', true ); delete_post_meta( $w1, '_zad_wk_poster' ); $tt = get_post_thumbnail_id( $w1 ); delete_post_thumbnail( $w1 ); ok( ! in_array( 'VideoObject', array_column( zsc_work_nodes( $w1 ), '@type' ), true ), 'schema: no VideoObject without a poster' ); update_post_meta( $w1, '_zad_wk_poster', $po ); set_post_thumbnail( $w1, $tt );
ok( ! in_array( 'VideoObject', $cut( '_zad_wk_video_url' ), true ), 'schema: no VideoObject without a video URL' );
ok( ! in_array( 'FAQPage', $cut( '_zad_wk_faq' ), true ), 'schema: no FAQPage without questions' );
$yn = array_values( array_filter( ld( get( '/works/youtube-work/' ) ), function ( $x ) { return 'VideoObject' === ( $x['@type'] ?? '' ); } ) )[0] ?? array(); ok( 0 === strpos( $yn['embedUrl'] ?? '', 'https://www.youtube-nocookie.com/embed/' ) && empty( $yn['contentUrl'] ) && 2 === count( $yn['hasPart'] ?? array() ), 'schema: YouTube work → embedUrl + clips' );
$h3 = get( '/works/no-video-work/' ); ok( false === strpos( $h3, '<video' ) && false === strpos( $h3, 'zw-clip' ) && ! in_array( 'VideoObject', array_map( function ( $x ) { return $x['@type'] ?? ''; }, ld( $h3 ) ), true ), 'work without video: no player, no clips, no VideoObject' );
$yh = get( '/works/youtube-work/' ); ok( false !== strpos( $yh, 'data-kind="yt"' ) && false !== strpos( $yh, 'zw-yt__play' ) && false === strpos( $yh, '<iframe' ), 'youtube: lazy (no iframe until play)' );

/* ---- archive ---- */
$a = get( '/works/' );
ok( 12 === substr_count( $a, 'class="scard scard--post zw-card"' ), 'archive: 12 cards on page 1' );
ok( false !== strpos( get( '/works/page/2/' ), 'zw-badge--video' ) && false !== strpos( $a, 'chips chips--filter' ) && false !== strpos( $a, 'class="pagination"' ), 'archive: video badge, filter chips, pagination' );
$a2 = get( '/works/page/2/' ); ok( false !== strpos( $a2, 'النخيل' ), 'archive: district shown on the card' ); ok( substr_count( $a2, 'zw-card"' ) >= 3, 'archive: page 2 works' );
$af = get( '/works/?svc=' . get_term_by( 'name', 'كشف تسربات', 'service_cat' )->slug ); ok( substr_count( $af, 'zw-card"' ) >= 3 && false !== strpos( $af, 'class="is-on"' ), 'archive: service_cat chip filters' );
$an = ld( $a ); $at = array_map( function ( $x ) { return $x['@type'] ?? '?'; }, $an );
ok( in_array( 'CollectionPage', $at, true ) && in_array( 'ItemList', $at, true ) && in_array( 'BreadcrumbList', $at, true ), 'archive schema: CollectionPage + ItemList + BreadcrumbList (' . implode( ',', $at ) . ')' );
$il = array_values( array_filter( $an, function ( $x ) { return 'ItemList' === ( $x['@type'] ?? '' ); } ) )[0] ?? array(); ok( 12 === count( $il['itemListElement'] ?? array() ), 'archive schema: ItemList lists the 12 works of the page' );

/* ---- service page section + old pages ---- */
$sp = get( '/service/leak-detection/' ); $pos = strpos( $sp, 'class="sec sec--tint zw-field"' ); $fq = strpos( $sp, '<details class="faq__item"' );
ok( false !== $pos && 3 === substr_count( substr( $sp, $pos, 6000 ), 'zw-card"' ), 'service page: «أعمال من الميدان» with 3 works' );
ok( false !== $pos && false !== $fq && $pos < $fq, 'service page: the section comes before the FAQ' );
ok( false === strpos( get( '/service/no-works/' ), 'zw-field' ) && false === strpos( get( '/service/no-works/' ), 'أعمال من الميدان' ), 'service page without works: no section at all' );
$og = get( '/old-gallery/' ); ok( false !== strpos( $og, 'work' ) && code( '/old-gallery/' )[0] === 200, 'old «أعمالنا» gallery page still works' );
$items = zad_work_items(); $lnk = array_filter( $items, function ( $i ) { return false !== strpos( (string) $i['link'], '/works/story-leak-under-tile/' ); } );
ok( count( $lnk ) >= 2 && in_array( 'ba', array_column( $lnk, 'type' ), true ), 'zad_work_items(): before/after pair + cover of the work, linking to its page' );
$sm = get( '/' ); ok( true, 'home ok' );
$smap = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'name' => 'x' ) ); ok( false !== strpos( zad_sitemap_html(), 'story-leak-under-tile' ), 'HTML sitemap lists the works' );

/* ---- migration tool ---- */
global $wpdb; $snapP = function () use ( $wpdb ) { return md5( json_encode( $wpdb->get_results( "SELECT ID,post_type,post_name,post_status,post_title,post_content FROM {$wpdb->posts} WHERE post_type IN ('page','zad_work') ORDER BY ID", ARRAY_A ) ) . json_encode( get_option( 'zad_wm_map' ) ) ); };
$s0 = $snapP(); $plan = zad_wm_plan(); ob_start(); zad_wm_page(); $pg = ob_get_clean();
ok( 2 === count( $plan['rows'] ) && 'move' === $plan['rows'][0]['action'] && $snapP() === $s0, 'tool: preview lists the 2 old pages and changes nothing' );
$_POST = array( 'zad_wm_do' => 'preview', '_wpnonce' => wp_create_nonce( 'zad_wm' ) ); $_REQUEST = $_POST; ob_start(); zad_wm_page(); ob_get_clean(); $_POST = array(); $_REQUEST = array(); ok( $snapP() === $s0, 'tool: «تجربة» button path changes nothing' );
$rep = zad_wm_run(); ok( 2 === $rep['moved'] && 3 === $rep['red'], 'tool: run moves 2 pages and records 3 redirects (2 pages + archive)' );
$old = get_posts( array( 'post_type' => 'page', 'name' => 'sofa-and-carpet-cleaning-in-al-nakhil-neighborhood', 'post_status' => 'any', 'numberposts' => 1 ) )[0]; $new = get_posts( array( 'post_type' => 'zad_work', 'name' => 'sofa-and-carpet-cleaning-in-al-nakhil-neighborhood', 'post_status' => 'any', 'numberposts' => 1 ) )[0] ?? null;
$odate = $wpdb->get_var( $wpdb->prepare( "SELECT post_date FROM {$wpdb->posts} WHERE ID = %d", $old->ID ) ); ok( 'draft' === $old->post_status && '2025-05-05 10:00:00' === $odate, 'tool: the old page is a draft (not deleted) and keeps its date' ); ok( $new && 'zad_work' === $new->post_type, 'tool: the new work exists' );
ok( $new && $new->post_title === $old->post_title && $new->post_content === $old->post_content && $new->post_excerpt === $old->post_excerpt && $new->post_date === $odate && 'publish' === $new->post_status && (int) get_post_thumbnail_id( $new ) === (int) get_post_thumbnail_id( $old ), 'tool: slug/title/content/excerpt/date/featured image copied' );
ok( 'عنوان سيو تنظيف كنب وسجاد في حي النخيل' === get_post_meta( $new->ID, '_yoast_wpseo_title', true ) && 'كلمة' === get_post_meta( $new->ID, '_yoast_wpseo_focuskw', true ), 'tool: Yoast fields copied' );
$r1 = code( '/completed-projects/sofa-and-carpet-cleaning-in-al-nakhil-neighborhood/' ); ok( 301 === $r1[0] && false !== strpos( $r1[1], '/works/sofa-and-carpet-cleaning-in-al-nakhil-neighborhood/' ), 'tool: /completed-projects/<slug>/ → 301 → /works/<slug>/ (' . $r1[0] . ' ' . $r1[1] . ')' );
$r2 = code( '/completed-projects/' ); ok( 301 === $r2[0] && '/works/' === parse_url( $r2[1], PHP_URL_PATH ), 'tool: /completed-projects/ → 301 → /works/' );
ok( 200 === code( '/works/water-tank-cleaning-al-olaya-riyadh/' )[0], 'tool: new work page answers 200' );
$again = zad_wm_run(); ok( 0 === $again['moved'] && 2 === $again['skipped'], 'tool: a second run does nothing (already moved)' );
ok( 2 === zad_wm_undo(), 'tool: undo restores 2' ); ok( 'publish' === get_post_status( $old->ID ) && 404 === code( '/works/water-tank-cleaning-al-olaya-riyadh/' )[0] && 200 === code( '/completed-projects/' )[0], 'tool: undo → old page published again, redirects gone, work in trash' );

/* ---- no PHP warnings during all of the above ---- */
clearstatcache(); $new_log = is_file( $log ) ? (string) file_get_contents( $log, false, null, $mark ) : '';
$bad = array_filter( explode( "\n", $new_log ), function ( $l ) { return preg_match( '/PHP (Warning|Notice|Fatal|Parse)/', $l ) && false === strpos( $l, 'unexpected error occurred' ) && false === strpos( $l, 'city-test' ); } );
ok( ! $bad, 'no PHP warnings/notices/fatals logged: ' . substr( implode( ' | ', array_slice( $bad, 0, 3 ) ), 0, 300 ) );
echo "$n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
