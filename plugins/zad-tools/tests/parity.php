<?php
// PHP side of the JS/PHP parity test: prints JSON of every pure function over shared vectors. Run: php tests/parity.php
define( 'ABSPATH', '/x/' );
require __DIR__ . '/../includes/core.php';
$v = json_decode( file_get_contents( __DIR__ . '/vectors.json' ), true );
$out = array( 'digits' => array(), 'num' => array(), 'fmt' => array(), 'qs' => array(), 'wa' => array(), 'clean' => array() );
foreach ( $v['text'] as $t )  { $out['digits'][] = zt_digits_en( $t ); $out['num'][] = zt_num( $t ); }
foreach ( $v['numbers'] as $n ) { $out['fmt'][] = zt_fmt( $n ); }
foreach ( $v['qs'] as $o )    { $out['qs'][] = zt_qs_string( $o ); }
foreach ( $v['wa'] as $w )    { $out['wa'][] = zt_wa_message( $w[0], $w[1], $w[2] ); }
foreach ( $v['clean'] as $c ) { $out['clean'][] = zt_clean_params( $c ); }
echo json_encode( $out, JSON_UNESCAPED_UNICODE );
