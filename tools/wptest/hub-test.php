<?php
/** Section pages (hubs): rule, H1/lead, cards, robots, schema, untouched service pages. php tools/wptest/hub-fixtures.php && php tools/wptest/hub-test.php  (server :8099) */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 ); global $wpdb;
$B = 'http://127.0.0.1:8099'; $fail = 0; $n = 0; $I = get_option( 'hub_ids' );
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function get( $u ) { global $B; return (string) shell_exec( 'curl -s -m 40 ' . escapeshellarg( $B . $u ) ); }
function code( $u ) { global $B; $o = (string) shell_exec( 'curl -s -I -m 20 ' . escapeshellarg( $B . $u ) ); preg_match( '/^HTTP\/\S+ (\d+)/', $o, $m ); preg_match( '/^location:\s*(\S+)/mi', $o, $l ); return array( (int) ( $m[1] ?? 0 ), isset( $l[1] ) ? rawurldecode( trim( $l[1] ) ) : '' ); }
function h1( $h ) { return preg_match( '#<h1[^>]*>(.*?)</h1>#su', $h, $m ) ? trim( wp_strip_all_tags( $m[1] ) ) : ''; }
function ttl( $h ) { return preg_match( '#<title>(.*?)</title>#su', $h, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES ) : ''; }
function ld( $h ) { preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $h, $m ); $o = array(); foreach ( $m[1] as $j ) { $d = json_decode( $j, true ); if ( ! $d ) { continue; } foreach ( isset( $d['@graph'] ) ? $d['@graph'] : array( $d ) as $x ) { $o[] = $x; } } return $o; }
function types( $nodes ) { $t = array(); foreach ( $nodes as $x ) { foreach ( (array) ( $x['@type'] ?? array() ) as $y ) { $t[] = $y; } } return $t; }
function robots( $h ) { return preg_match( '#<meta name=[\'"]robots[\'"] content=[\'"]([^\'"]*)#i', $h, $m ) ? $m[1] : ''; }
$FORBID = array( 'ريال', 'ضمان', 'اختر حسب حالتك', 'مقارنة', 'الأسئلة الشائعة', 'آراء', 'معرض' );

/* ---- the rule ---- */
ok( 'section' === zad_page_kind( $I['tanks'] ) && 'section' === zad_page_kind( $I['ac'] ), '/cleaning/tanks/ and /cleaning/air-conditioning/ are sections (no city + children with a city)' );
ok( 'service' === zad_page_kind( $I['tanks_r'] ), '/cleaning/tanks/riyadh/ stays a service even with district pages under it' );
ok( 'service' === zad_page_kind( $I['p_riyadh'] ) && 'service' === zad_page_kind( $I['ac_r'] ) && 'service' === zad_page_kind( $I['c_riyadh'] ), 'city pages stay services' );
ok( 'service' === zad_page_kind( $I['pesticides'] ) && 'service' === zad_page_kind( $I['lone'] ), 'no city but no city children → service' );
update_post_meta( $I['lone'], ZAD_HUB_KIND, 'section' ); ok( 'section' === zad_page_kind( $I['lone'] ), 'manual «قسم» wins' ); update_post_meta( $I['tanks'], ZAD_HUB_KIND, 'service' ); ok( 'service' === zad_page_kind( $I['tanks'] ), 'manual «خدمة» wins over the rule' );
delete_post_meta( $I['lone'], ZAD_HUB_KIND ); delete_post_meta( $I['tanks'], ZAD_HUB_KIND ); zad_city_bump();
// the meta box saves
$_POST = array( 'zad_hub_nonce' => wp_create_nonce( 'zad_hub_kind' ), 'zad_hub_kind' => 'section', 'zad_hub_h1' => 'خدمات الخزانات' ); wp_update_post( array( 'ID' => $I['pesticides'], 'post_title' => get_the_title( $I['pesticides'] ) ) ); $_POST = array();
ok( 'section' === zad_page_kind( $I['pesticides'] ) && 'خدمات الخزانات' === get_post_meta( $I['pesticides'], ZAD_HUB_H1, true ), 'meta box saves kind + H1' );
$_POST = array( 'zad_hub_nonce' => 'bad', 'zad_hub_kind' => 'service' ); wp_update_post( array( 'ID' => $I['pesticides'], 'post_title' => get_the_title( $I['pesticides'] ) ) ); $_POST = array(); ok( 'section' === zad_page_kind( $I['pesticides'] ), 'bad nonce changes nothing' );
$_POST = array( 'zad_hub_nonce' => wp_create_nonce( 'zad_hub_kind' ), 'zad_hub_kind' => 'auto', 'zad_hub_h1' => '' ); wp_update_post( array( 'ID' => $I['pesticides'], 'post_title' => get_the_title( $I['pesticides'] ) ) ); $_POST = array(); ok( 'service' === zad_page_kind( $I['pesticides'] ) && ! metadata_exists( 'post', $I['pesticides'], ZAD_HUB_H1 ), 'auto = field removed' );

/* ---- names ---- */
$cn = array( 'شركة تنظيف خزانات بالرياض 150 ريال ـ زاد السعودية' => 'خدمات تنظيف خزانات', 'أفضل شركة مكافحة حشرات بجدة' => 'خدمات مكافحة حشرات', 'خدمات التنظيف في السعودية' => 'خدمات التنظيف', 'صفحات الخدمات' => '', 'خدمة تنظيف الكنب والمجالس' => 'خدمات تنظيف الكنب والمجالس', 'شركة تنظيف زاد السعودية' => 'خدمات تنظيف' );
foreach ( $cn as $in => $out ) { ok( $out === zad_hub_clean_name( $in ), "clean_name: «$in» → «" . zad_hub_clean_name( $in ) . '»' ); }
ok( 'خدمات تنظيف مكيفات' === zad_hub_clean_name( 'تنظيف مكيفات في مكانها لتنظيف مكيفات سبلت شباك وغيرها كثير' ), 'name cut at «في»' );

/* ---- /pest-control/ and /cleaning/ ---- */
$p = get( '/pest-control/' ); $c = get( '/cleaning/' );
ok( 'خدمات مكافحة الحشرات' === h1( $p ), '/pest-control/ H1: ' . h1( $p ) ); ok( 'خدمات التنظيف' === h1( $c ), '/cleaning/ H1: ' . h1( $c ) ); ok( h1( $p ) !== h1( $c ), 'H1 of the two sections differ' );
ok( false === strpos( h1( $p ) . h1( $c ), 'شركة' ), 'no «شركة» in the H1' );
ok( 'عنوان مخصص للتنظيف | زاد' === ttl( $c ), '/cleaning/ keeps its custom SEO title: ' . ttl( $c ) );
ok( 'خدمات مكافحة الحشرات في السعودية | Zad Test' === ttl( $p ), '/pest-control/ default title «خدمات {القسم} في السعودية | {الموقع}»: ' . ttl( $p ) );
ok( false !== strpos( $c, 'zhub__cards' ) && false === strpos( $c, 'hubhero' ), 'new design (zhub), old hub design gone' );
preg_match( '#<main id="main" class="zhub">.*?</main>#su', $c, $mm ); $mainc = wp_strip_all_tags( $mm[0] ?? '' ); ok( '' !== $mainc, 'main.zhub found' );
foreach ( $FORBID as $w ) { ok( false === strpos( $mainc, $w ), "/cleaning/ section has no «$w»" ); }
ok( false === strpos( $c, 'جملة طويلة عن التنظيف' ) || substr_count( $c, 'جملة طويلة عن التنظيف' ) <= 60, 'long archive-content page is not printed in full' );
preg_match( '#<p class="zhub__lead">(.*?)</p>#su', $c, $m ); $lead = $m[1] ?? ''; ok( count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $lead ) ), -1, PREG_SPLIT_NO_EMPTY ) ) <= 60 && '' !== trim( $lead ), 'lead ≤ 60 words (first paragraph of the mapped page): ' . mb_substr( wp_strip_all_tags( $lead ), 0, 50 ) );
preg_match_all( '#class="zhub__card" href="([^"]+)"#', $c, $m ); $hrefs = array_map( 'rawurldecode', $m[1] );
ok( in_array( 'http://127.0.0.1:8099/cleaning/tanks/', $hrefs, true ) && in_array( 'http://127.0.0.1:8099/cleaning/riyadh/', $hrefs, true ) && ! in_array( 'http://127.0.0.1:8099/cleaning/tanks/riyadh/', $hrefs, true ), '/cleaning/ cards = the pages directly under it' );
ok( count( $hrefs ) === count( array_unique( $hrefs ) ), 'no duplicate cards' );
ok( false !== strpos( $c, 'zhub__chips' ) && false !== strpos( $c, 'الرياض' ), 'city chips shown (3+ cities)' );
ok( false === strpos( robots( $c ), 'noindex' ), '/cleaning/ indexable (no noindex): ' . robots( $c ) );
$ty = types( ld( $c ) ); ok( in_array( 'CollectionPage', $ty, true ) && in_array( 'ItemList', $ty, true ) && in_array( 'BreadcrumbList', $ty, true ), '/cleaning/ schema: CollectionPage + ItemList + BreadcrumbList' );
ok( ! array_intersect( $ty, array( 'Service', 'Offer', 'FAQPage', 'AggregateRating', 'Question' ) ), '/cleaning/ schema has no Service/Offer/FAQPage/AggregateRating: ' . implode( ',', array_unique( $ty ) ) );
$il = array_values( array_filter( ld( $c ), function ( $x ) { return 'ItemList' === ( $x['@type'] ?? '' ); } ) ); $names = array_column( array_column( (array) ( $il[0]['itemListElement'] ?? array() ), 'item' ), 'url' );
ok( $names && rawurldecode( $names[0] ) === $hrefs[0] && count( $names ) === count( $hrefs ), 'ItemList = the cards, same order' );
ok( in_array( code( '/cleaning/page/2/' )[0], array( 301, 404 ), true ), '/cleaning/page/2/ without a second page of cards → 301 (or 404 when WordPress has no such page): ' . code( '/cleaning/page/2/' )[0] );

/* ---- a section page, a service page that stays, a city, a term, /cities/ ---- */
$t = get( '/cleaning/tanks/' );
ok( 'خدمات تنظيف خزانات المياه' === h1( $t ), '/cleaning/tanks/ H1: ' . h1( $t ) ); ok( false !== strpos( $t, 'zhub__cards' ), '/cleaning/tanks/ is rendered as a section' );
preg_match_all( '#class="zhub__card" href="([^"]+)"#', $t, $m ); $th = array_map( 'rawurldecode', $m[1] ); sort( $th );
ok( array( 'http://127.0.0.1:8099/cleaning/tanks/jeddah/', 'http://127.0.0.1:8099/cleaning/tanks/riyadh/' ) === $th, 'section cards = its children' );
$ty = types( ld( $t ) ); ok( in_array( 'CollectionPage', $ty, true ) && ! array_intersect( $ty, array( 'Service', 'Offer', 'FAQPage', 'AggregateRating' ) ), 'section page schema without Service/FAQPage: ' . implode( ',', array_unique( $ty ) ) );
ok( false !== strpos( implode( ' ', array_column( array_filter( ld( $t ), function ( $x ) { return ( $x['@type'] ?? '' ) === 'BreadcrumbList'; } ), '@type' ) ), 'BreadcrumbList' ), 'breadcrumb node present' );
ok( 'خدمات تنظيف مكيفات' === h1( get( '/cleaning/air-conditioning/' ) ), 'AC section H1 cut at «في»: ' . h1( get( '/cleaning/air-conditioning/' ) ) );
ok( 3 === substr_count( get( '/cleaning/air-conditioning/' ), 'class="zhub__card"' ), 'AC section lists all 3 children (a child without a city too)' );
ok( false !== strpos( $t, 'مقالات عن' ) && false !== strpos( $t, 'أسئلة شائعة عن' ), 'two link lines (blog + FAQ category match «tanks»)' );
ok( false === strpos( get( '/cleaning/air-conditioning/' ), 'مقالات عن' ), 'no matching blog term → no link' );
$sv = get( '/cleaning/tanks/riyadh/' ); ok( false === strpos( $sv, 'zhub__cards' ), '/cleaning/tanks/riyadh/ is still the service design' );
$ct = get( '/areas/' . rawurlencode( 'الرياض' ) . '/' );
ok( 'خدماتنا في الرياض' === h1( $ct ) && 'خدماتنا في الرياض | Zad Test' === ttl( $ct ), 'city H1 + title «خدماتنا في {المدينة} | {الموقع}»: ' . h1( $ct ) . ' / ' . ttl( $ct ) );
preg_match_all( '#class="zhub__card" href="([^"]+)"#', $ct, $m ); $cc = array_map( 'rawurldecode', $m[1] );
ok( in_array( 'http://127.0.0.1:8099/cleaning/tanks/riyadh/', $cc, true ) && in_array( 'http://127.0.0.1:8099/cleaning/air-conditioning/riyadh/', $cc, true ) && in_array( 'http://127.0.0.1:8099/pest-control/riyadh/', $cc, true ) && in_array( 'http://127.0.0.1:8099/cleaning/riyadh/', $cc, true ), 'city hub: a card per service page IN that city (not per assigned term)' );
ok( ! in_array( 'http://127.0.0.1:8099/pest-control/jeddah/', $cc, true ) && ! in_array( 'http://127.0.0.1:8099/cleaning/tanks/jeddah/', $cc, true ), 'no other city pages in it' );
$qa = get( '/areas/' . rawurlencode( 'القصيم' ) . '/' ); ok( false !== strpos( robots( $qa ), 'noindex' ), 'a city with no published page is noindex,follow: ' . robots( $qa ) ); ok( false !== strpos( $qa, 'لا توجد صفحات منشورة' ), 'empty state' );
$cp = get( '/cities/' ); preg_match_all( '#class="zhub__card" href="([^"]+)"#', $cp, $m ); $cl = array_map( 'rawurldecode', $m[1] );
ok( false !== strpos( $cp, 'zhub__cards' ) && 3 === count( $cl ) && in_array( 'http://127.0.0.1:8099/areas/الرياض/', $cl, true ), '/cities/: one card per city that has pages (القصيم left out): ' . count( $cl ) );
ok( 'فروعنا في مختلف مدن المملكة' === h1( $cp ), '/cities/ H1: ' . h1( $cp ) );
$sc = get( '/services-category/cleaning/' ); ok( false !== strpos( $sc, 'zhub__cards' ) && 'خدمات التنظيف' === h1( $sc ), 'service_cat that mirrors a whole type: section design, H1 of the type (not «صفحات الخدمات»): ' . h1( $sc ) ); ok( false !== strpos( robots( $sc ), 'noindex' ), 'the mirror category page is noindex,follow (it only repeats /cleaning/): ' . robots( $sc ) );
$cs = get( '/cleaning-sections/tanks/' ); preg_match_all( '#class="zhub__card" href="([^"]+)"#', $cs, $m ); ok( 2 === count( $m[1] ), 'cleaning-sections term with ONE root section → lists that section\'s children' );
delete_term_meta( get_term_by( 'slug', 'cleaning-company', 'cleaning-sections' )->term_id, ZAD_HUB_H1 ); $cs2 = get( '/cleaning-sections/cleaning-company/' ); ok( 'خدمات تنظيف' === h1( $cs2 ), 'term «شركة تنظيف زاد السعودية» → H1 «خدمات تنظيف»: ' . h1( $cs2 ) );
update_term_meta( get_term_by( 'slug', 'cleaning-company', 'cleaning-sections' )->term_id, ZAD_HUB_H1, 'خدمات التنظيف العامة' ); ok( 'خدمات التنظيف العامة' === h1( get( '/cleaning-sections/cleaning-company/' ) ), 'term H1 field overrides' ); delete_term_meta( get_term_by( 'slug', 'cleaning-company', 'cleaning-sections' )->term_id, ZAD_HUB_H1 );

/* ---- paging + robots ---- */
update_option( '_memo_theme_options', array_merge( (array) get_option( '_memo_theme_options' ), array() ) );
$big = array(); for ( $i = 1; $i <= 30; $i++ ) { $big[] = wp_insert_post( array( 'post_type' => 'pest_control', 'post_title' => "صفحة اختبار $i", 'post_name' => "bulk-$i", 'post_status' => 'publish', 'post_content' => 'x' ) ); } zad_city_bump();
$pg1 = get( '/pest-control/' ); ok( false !== strpos( $pg1, 'class="pagination"' ), 'more than 24 cards → pagination' );
$pg2 = get( '/pest-control/page/2/' ); ok( false !== strpos( robots( $pg2 ), 'noindex' ) && false !== strpos( robots( $pg2 ), 'follow' ), 'page 2 is noindex,follow: ' . robots( $pg2 ) ); ok( 200 === code( '/pest-control/page/2/' )[0], 'page 2 exists' );
foreach ( $big as $i ) { wp_delete_post( $i, true ); } zad_city_bump();

/* ---- service pages unchanged ---- */
foreach ( array( '/pest-control/riyadh/' => '_pest-control_riyadh_', '/cleaning/tanks/riyadh/' => '_cleaning_tanks_riyadh_' ) as $u => $f ) {
	$new = get( $u ); $old = @file_get_contents( '/tmp/old' . $f . '.html' );
	if ( false === $old ) { continue; }
	$norm = function ( $h ) { $h = preg_replace( '/Zad Pro build \S+/', 'build', preg_replace( '/\b(?:ver|v)=[\w.\-]+/', 'ver=X', $h ) ); $h = preg_replace( '/"nonce":"[^"]+"|nonce=[\w]+|_wpnonce=\w+/', 'N', $h ); $h = preg_replace( '#<script type="application/ld\+json".*?</script>#s', '', $h ); return preg_replace( '/\s+/', ' ', $h ); };
	ok( $norm( $new ) === $norm( $old ), "$u renders exactly as before (3.28.1), schema aside" );
	$a = ld( $new ); $b = ld( $old ); ok( json_encode( $a ) === json_encode( $b ), "$u schema unchanged" );
}
echo $fail ? "FAILED $fail of $n\n" : "all $n checks passed\n";
