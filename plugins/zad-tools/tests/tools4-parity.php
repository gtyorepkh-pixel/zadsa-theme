<?php
// PHP side of the Phase-4 JS/PHP parity test (pest identifier). Run: php tests/tools4-parity.php
require __DIR__ . '/stub.php';
require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php'; require ZT_DIR . 'includes/settings.php'; require ZT_DIR . 'includes/tool-page.php';
require ZT_DIR . 'includes/tools/ac-power.php'; require ZT_DIR . 'includes/pests.php'; require ZT_DIR . 'includes/tools/pest-id.php';
$v = json_decode( file_get_contents( __DIR__ . '/vectors-tools4.json' ), true );
$env = array( 'wa' => '966555000111', 'privacy' => 'https://zadksa.com/privacy/' );
$out = array( 'pid' => array(), 'pid2' => array(), 'html' => array() );
foreach ( $v['pid']['inputs'] as $i )  { $a = zt_pid_view( $v['pid']['cfg'], $i ); $out['pid'][] = $a; $out['html'][] = zt_result_html( $a, $env ); }
foreach ( $v['pid']['inputs2'] as $i ) { $out['pid2'][] = zt_pid_view( $v['pid']['cfg2'], $i ); }
echo json_encode( $out, JSON_UNESCAPED_UNICODE );
