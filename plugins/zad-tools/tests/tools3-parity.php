<?php
// PHP side of the Phase-3 JS/PHP parity test. Run: php tests/tools3-parity.php
require __DIR__ . '/stub.php';
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/tank.php'; require ZT_DIR . 'includes/tools/ac-power.php'; require ZT_DIR . 'includes/tools/moving.php'; require ZT_DIR . 'includes/tools/plan.php';
$v = json_decode( file_get_contents( __DIR__ . '/vectors-tools3.json' ), true );
$env = array( 'wa' => '966555000111', 'privacy' => 'https://zadksa.com/privacy/' );
$out = array( 'power' => array(), 'power2' => array(), 'moving' => array(), 'moving2' => array(), 'plan' => array(), 'plan2' => array(), 'html' => array(), 'ics' => array() );
foreach ( $v['power']['inputs'] as $i )  { $a = zt_pow_view( $v['power']['cfg'], $i ); $out['power'][] = $a; $out['html'][] = zt_result_html( $a, $env ); $out['power2'][] = zt_pow_view( $v['power']['cfg2'], $i ); }
foreach ( $v['moving']['inputs'] as $i ) { $a = zt_mv_view( $v['moving']['cfg'], $i ); $out['moving'][] = $a; $out['html'][] = zt_result_html( $a, $env ); $out['moving2'][] = zt_mv_view( $v['moving']['cfg2'], $i ); }
foreach ( $v['plan']['inputs'] as $i )   { $a = zt_plan_view( $v['plan']['cfg'], $i, $v['plan']['today'] ); $out['plan'][] = $a; $out['html'][] = zt_result_html( $a, $env ); $out['plan2'][] = zt_plan_view( $v['plan']['cfg2'], $i, $v['plan']['today'] ); }
$ev = array( array( '2026-11-01', 'تنظيف المكيفات' ), array( '2027-04-01', 'عنوان ~ فيه | فواصل' ) );
$out['ics'] = array( zt_ics_url_multi( 'https://zadksa.com/', $ev ), zt_wa_self_url( "السلام عليكم (اختبار) !*'" ) );
echo json_encode( $out, JSON_UNESCAPED_UNICODE );
