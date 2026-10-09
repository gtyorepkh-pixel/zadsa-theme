<?php
// PHP side of the tools' JS/PHP parity test: prints JSON for every vector. Run: php tests/tools-parity.php
require __DIR__ . '/stub.php';
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/ac-size.php'; require ZT_DIR . 'includes/tools/after-spray.php'; require ZT_DIR . 'includes/tools/tank.php';
$v   = json_decode( file_get_contents( __DIR__ . '/vectors-tools.json' ), true );
$env = array( 'wa' => '966555000111', 'privacy' => 'https://zadksa.com/privacy/' );
$out = array( 'ac' => array(), 'spray' => array(), 'tank' => array(), 'tank2' => array(), 'html' => array(), 'dates' => array(), 'counts' => array(), 'fmtpct' => array() );
foreach ( $v['ac']['inputs'] as $i )    { $x = zt_ac_view( $v['ac']['cfg'], $i );    $out['ac'][] = $x;    $out['html'][] = zt_result_html( $x, $env ); }
foreach ( $v['spray']['inputs'] as $i ) { $x = zt_spray_view( $v['spray']['cfg'], $i ); $out['spray'][] = $x; $out['html'][] = zt_result_html( $x, $env ); }
foreach ( $v['tank']['inputs'] as $i )  { $x = zt_tank_view( $v['tank']['cfg'], $i, $v['tank']['today'] ); $out['tank'][] = $x; $out['html'][] = zt_result_html( $x, $env ); $out['tank2'][] = zt_tank_view( $v['tank']['cfg2'], $i, $v['tank']['today'] ); }
foreach ( $v['views'] as $x )           { $out['html'][] = zt_result_html( $x, $env ); }
foreach ( $v['dates'] as $d )           { $out['dates'][] = array( zt_add_months_str( $d[0], $d[1] ), zt_ar_date( zt_add_months_str( $d[0], $d[1] ) ), zt_add_days_str( $d[0], 40 ) ); }
foreach ( $v['counts'] as $c )          { $out['counts'][] = array( zt_ar_count( $c[0], 'ساعة', 'ساعتين', 'ساعات', 'ساعة' ), zt_pct_label( $c[0] ), zt_pct_label( -$c[0] ) ); }
echo json_encode( $out, JSON_UNESCAPED_UNICODE );
