<?php
// Renders one tool page the way the WordPress template does (stubbed WP). Usage: php render-page.php <tool> <query-string> <port>
require __DIR__ . '/../stub.php';
function get_privacy_policy_url() { return '/privacy/'; }
function zad_whatsapp( $x = 0 ) { return '966555000111'; }
function zad_min_price( $rows ) { return $rows ? min( $rows ) : null; } function zad_price_rows() { return array(); }
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/reminders.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/ac-size.php'; require ZT_DIR . 'includes/tools/after-spray.php'; require ZT_DIR . 'includes/tools/tank.php'; require ZT_DIR . 'includes/tools/ac-power.php'; require ZT_DIR . 'includes/tools/moving.php'; require ZT_DIR . 'includes/tools/plan.php'; require ZT_DIR . 'includes/tools/coverage.php';
date_default_timezone_set( 'Asia/Riyadh' ); // the site timezone (the JS side uses Asia/Riyadh too)
$slug = $argv[1]; $GLOBALS['SLUG'] = $slug; parse_str( $argv[2] ?? '', $_GET );
$a = array(); foreach ( zt_settings_registry() as $tab => $t ) { foreach ( $t['fields'] as $k => $f ) { if ( ! empty( $f['approval'] ) ) { $a[ $tab . '.' . $k ] = array( 'by' => 1 ); } } } update_option( 'zad_tools_approved', $a );
if ( isset( $argv[4] ) && $argv[4] ) { update_option( 'zad_tools_opts', json_decode( $argv[4], true ) ); } // setting overrides for a test
if ( 'coverage' === $slug ) {
	$H = function ( $id, $n, $c, $s, $lat, $lng, $v, $eta ) { return array( 'id' => $id, 'n' => $n, 'c' => $c, 's' => $s, 'lat' => $lat, 'lng' => $lng, 'v' => $v, 'eta' => $eta ); };
	$data = array( 'services' => array( 'تنظيف مكيفات', 'تنظيف خزانات', 'تنظيف كنب' ), 'hoods' => array(
		$H( 1, 'النرجس', 'الرياض', 'north', 24.8, 46.65, array( array( 'تنظيف مكيفات', '/a/' ), array( 'تنظيف خزانات', '/t/' ) ), array( 'm' => 35, 'n' => 14, 'src' => 'orders' ) ),
		$H( 2, 'الملقا', 'الرياض', 'north', 24.82, 46.62, array( array( 'تنظيف كنب', '/s/' ) ), array( 'm' => 25, 'n' => 0, 'src' => 'manual' ) ),
		$H( 3, 'العليا', 'الرياض', 'center', null, null, array( array( 'تنظيف مكيفات', '/a2/' ) ), null ),
		$H( 4, 'الشاطئ', 'جدة', 'west', 21.6, 39.1, array( array( 'تنظيف مكيفات', '/a3/' ), array( 'تنظيف خزانات', '/t3/' ), array( 'تنظيف كنب', '/s3/' ), array( 'مكافحة حشرات', '/p3/' ) ), null ),
	) );
	add_filter( 'zt_coverage_dataset', function () use ( $data ) { return $data; } );
}
$GLOBALS['META'][1] = array( '_zt_tool' => $slug, '_zt_intro' => 'سطر تعريفي للأداة.', '_zt_faq' => "ما هذا؟ | أداة تجريبية.\nهل هي دقيقة؟ | تقديرية.", '_zt_services' => '5,6', '_zt_cta' => 'اطلب الخدمة' );
$ctx = zt_ctx( 1 ); $ctx['url'] = '/t/' . $slug . '/'; $def = $ctx['def'];
$title = $def['title'];
$cfg = array( 'tool' => $slug, 'wa' => '966555000111', 'rest' => '/wp-json/zad/v1/', 'ga' => true, 'brand' => 'زاد', 'url' => $ctx['url'], 'privacy' => '/privacy/', 'ics' => '/' );
echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . zt_esc( $title ) . '</title>';
echo '<link rel="stylesheet" href="/assets/css/zt-tool.css">' . ( ! empty( $def['css'] ) ? '<link rel="stylesheet" href="' . zt_esc( str_replace( ZT_URL, '/', $def['css'] ) ) . '">' : '' ) . '<style>body{font-family:system-ui,sans-serif;margin:0}.wrap{max-width:760px;margin:0 auto;padding:0 16px}.sec{padding:24px 0}.btn{display:inline-block;padding:12px 18px;border-radius:10px;border:1px solid #ccc;background:#fff;font:inherit;cursor:pointer;text-decoration:none;color:#111}.btn--accent{background:#0b2e3a;color:#fff}.btn--wa{background:#25d366;color:#fff}</style>';
echo '<script>document.documentElement.className+=" zt-js"</script><script>window.dataLayer=[];window.__cls=0;try{new PerformanceObserver(function(l){l.getEntries().forEach(function(e){if(!e.hadRecentInput){window.__cls+=e.value}})}).observe({type:"layout-shift",buffered:true})}catch(e){}</script></head><body><main class="zt-page">';
echo '<section class="sec zt-hero"><div class="wrap wrap--narrow"><h1>' . zt_esc( $title ) . '</h1><p class="zt-lead">' . zt_esc( $ctx['intro'] ) . '</p></div></section>';
zt_render_body( $ctx );
echo '</main><script>var ZT_CFG=' . json_encode( $cfg, JSON_UNESCAPED_UNICODE ) . ';</script><script src="/assets/js/zt-core.js" defer></script><script src="/assets/js/' . $slug . '.js" defer></script></body></html>';
