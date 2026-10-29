<?php
/** Real-WP test of the service page layout switch (_zad_layout). Run: php tools/wptest/layout-test.php  (server: /tmp/restart-srv.sh → 127.0.0.1:8099). Optional: BASELINE=/path/classic.html writes the classic HTML for before/after comparison. */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function get( $url, $cookie = '' ) { $c = curl_init( $url ); curl_setopt_array( $c, array( CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $cookie ? array( 'Cookie: ' . $cookie ) : array() ) ); $b = curl_exec( $c ); return array( curl_getinfo( $c, CURLINFO_HTTP_CODE ), (string) $b ); }
function auth_cookie() { $u = 1; $exp = time() + 3600; return LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $u, $exp, 'logged_in' ) ); }

foreach ( get_posts( array( 'post_type' => 'zad_service', 'name' => 'layout-test-tanks', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) { wp_delete_post( $p->ID, true ); }
$id = wp_insert_post( array( 'post_type' => 'zad_service', 'post_title' => 'تنظيف خزانات بالرياض (اختبار)', 'post_name' => 'layout-test-tanks', 'post_status' => 'publish',
	'post_content' => '<p>INTRO_MARK نص المقدمة قبل وسم المزيد.</p><!--more--><h2>AFTER_MARK عنوان بعد وسم المزيد</h2><p>نص المعرفة.</p>' ) );
$m = function ( $k, $v ) use ( $id ) { update_post_meta( $id, '_zad_' . $k, $v ); };
$m( 'signs',  array( array( 't' => 'SIGNS_MARK رائحة', 'd' => 'وصف' ), array( 't' => 'لون', 'd' => 'وصف' ) ) );
$m( 'harms',  array( array( 't' => 'HARMS_MARK أمراض', 'd' => 'وصف' ), array( 't' => 'ضرر', 'd' => 'وصف' ) ) );
$m( 'steps',  array( array( 't' => 'STEPS_MARK تفريغ', 'd' => 'وصف' ), array( 't' => 'غسيل', 'd' => 'وصف' ) ) );
$m( 'tools',  array( array( 't' => 'EQUIP_MARK مضخة', 'd' => 'وصف' ) ) );
$m( 'faq',    array( array( 'q' => 'FAQ_MARK سؤال؟', 'a' => 'جواب' ) ) );
$m( 'packages', "اقتصادية|150|ريال|وصف|ضمان 30 يوماً|تفريغ;غسيل|0|اطلب|\nشاملة|300|ريال|وصف|ضمان 60 يوماً|تفريغ;غسيل;تعقيم|1|اطلب|" );
$m( 'ix', array( 'on' => 1, 'order' => 'wiz', 'en' => array( 'wiz' => 1 ), 'wiz' => array( 'questions' => 'WIZ_MARK متى آخر غسيل؟||single||أقل من 6 أشهر::0;أكثر من سنة::3', 'tiers' => '0|خطة|نص|تفريغ' ) ) );

$url = get_permalink( $id );
$cookie = auth_cookie();
$body = function ( $html ) { $i = strpos( $html, '<main id="main"' ); return false === $i ? $html : substr( $html, $i ); }; // the head (meta, JSON-LD) repeats some texts
$pos = function ( $html, $s ) use ( $body ) { $p = strpos( $body( $html ), $s ); return false === $p ? -1 : $p; };

/* classic (default) */
list( $code, $c ) = get( $url );
ok( 200 === $code, "classic page 200 (got $code)" );
$order_c = array( 'main' => 0, 'intro' => $pos( $c, 'INTRO_MARK' ), 'wiz' => $pos( $c, 'WIZ_MARK' ), 'after' => $pos( $c, 'AFTER_MARK' ), 'equip' => $pos( $c, 'EQUIP_MARK' ), 'steps' => $pos( $c, 'STEPS_MARK' ), 'prices' => $pos( $c, 'id="prices"' ), 'signs' => $pos( $c, 'SIGNS_MARK' ), 'harms' => $pos( $c, 'HARMS_MARK' ), 'faq' => $pos( $c, 'FAQ_MARK' ) );
foreach ( $order_c as $k => $v ) { ok( $v >= 0, "classic has $k" ); }
ok( $order_c['intro'] < $order_c['wiz'] && $order_c['wiz'] < $order_c['after'], 'classic: intro → wizard → after-more text' );
ok( $order_c['after'] < $order_c['equip'] && $order_c['equip'] < $order_c['steps'] && $order_c['steps'] < $order_c['prices'] && $order_c['prices'] < $order_c['signs'] && $order_c['signs'] < $order_c['harms'] && $order_c['harms'] < $order_c['faq'], 'classic: after → equipment → steps → prices → signs → harms → faq' );
ok( false === strpos( $c, 'svc--new' ), 'classic: no svc--new class' );
if ( getenv( 'BASELINE' ) ) { file_put_contents( getenv( 'BASELINE' ), preg_replace( '#(wp-nonce|nonce)[^"\']*#', '', $c ) ); }

/* new layout via meta */
update_post_meta( $id, '_zad_layout', 'new' );
list( $code, $nw ) = get( $url );
ok( 200 === $code, 'new page 200' );
$o = array( 'main' => 0, 'wiz' => $pos( $nw, 'WIZ_MARK' ), 'signs' => $pos( $nw, 'SIGNS_MARK' ), 'harms' => $pos( $nw, 'HARMS_MARK' ), 'prices' => $pos( $nw, 'id="prices"' ), 'steps' => $pos( $nw, 'STEPS_MARK' ), 'equip' => $pos( $nw, 'EQUIP_MARK' ), 'intro' => $pos( $nw, 'INTRO_MARK' ), 'after' => $pos( $nw, 'AFTER_MARK' ), 'faq' => $pos( $nw, 'FAQ_MARK' ) );
foreach ( $o as $k => $v ) { ok( $v >= 0, "new has $k" ); }
ok( $o['main'] < $o['wiz'] && $o['wiz'] < $o['signs'] && $o['signs'] < $o['harms'] && $o['harms'] < $o['prices'] && $o['prices'] < $o['steps'] && $o['steps'] < $o['equip'], 'new: wizard → signs → harms → prices → steps → equipment' );
ok( $o['equip'] < $o['intro'] && $o['intro'] < $o['after'] && $o['after'] < $o['faq'], 'new: equipment → intro → after-more text → faq' );
ok( false !== strpos( $nw, 'class="svc--new"' ), 'new: main has svc--new' );
ok( false !== strpos( $nw, 'sec--tint' ) && 1 === preg_match( '#<section class="sec sec--tint"><div class="wrap">\s*<header[^>]*>.{0,400}HARMS_MARK#su', $nw ) || false !== strpos( $nw, 'HARMS_MARK' ), 'new: harms rendered' );
ok( 1 === substr_count( $body( $nw ), 'WIZ_MARK' ), 'new: wizard printed once' );
ok( 1 === substr_count( $body( $nw ), 'INTRO_MARK' ) && 1 === substr_count( $body( $nw ), 'AFTER_MARK' ), 'new: content printed once' );
ok( strlen( $nw ) > 0.85 * strlen( $c ), 'new: no section lost (size within 15% of classic)' );
foreach ( array( 'SIGNS_MARK', 'HARMS_MARK', 'STEPS_MARK', 'EQUIP_MARK', 'FAQ_MARK', 'WIZ_MARK', 'INTRO_MARK', 'AFTER_MARK' ) as $mk ) { ok( false !== strpos( $nw, $mk ) && false !== strpos( $c, $mk ), "both layouts contain $mk" ); }

/* preview param: needs edit capability */
update_post_meta( $id, '_zad_layout', '' );
list( , $anon ) = get( $url . '?zad_layout=new' );
ok( false === strpos( $anon, 'svc--new' ), 'preview param ignored for visitors' );
list( , $ed ) = get( $url . '?zad_layout=new', $cookie );
ok( false !== strpos( $ed, 'svc--new' ), 'preview param works for an editor' );
list( , $ed2 ) = get( $url, $cookie );
ok( false === strpos( $ed2, 'svc--new' ), 'no param + meta empty = classic for editor' );
update_post_meta( $id, '_zad_layout', 'new' );
list( , $ed3 ) = get( $url . '?zad_layout=classic', $cookie );
ok( false === strpos( $ed3, 'svc--new' ), 'editor can preview classic on a new page' );

/* saving through the metabox normalizes junk */
update_post_meta( $id, '_zad_layout', 'weird' );
list( , $junk ) = get( $url );
ok( false === strpos( $junk, 'svc--new' ), 'unknown layout value = classic' );

if ( ! getenv( "KEEP" ) ) { wp_delete_post( $id, true ); } else { update_post_meta( $id, "_zad_layout", "new" ); echo "kept: $url\n"; }
echo "layout-test: $n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
