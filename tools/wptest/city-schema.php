<?php
/** JSON-LD of service pages → {url: sha1 + areaServed/places summary}. php city-schema.php url… */
$base = 'http://127.0.0.1:8099'; $out = array();
foreach ( array_slice( $argv, 1 ) as $u ) {
	$h = (string) shell_exec( 'curl -s -m 40 ' . escapeshellarg( $base . $u ) );
	preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $h, $m ); $all = implode( "\n", $m[1] );
	preg_match_all( '/"areaServed":(\{[^}]*\}|"[^"]*")/u', $all, $a );
	$norm = preg_replace( '/"relatedLink":\[[^\]]*\],?/', '', $all );
	$out[ $u ] = array( 'sha' => sha1( $all ), 'sha_norelated' => sha1( $norm ), 'areaServed' => array_values( array_unique( $a[1] ) ) );
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), "\n";
