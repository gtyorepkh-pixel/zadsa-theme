<?php
/** What the lists of service pages show: php city-capture.php /url/ [/url/…] → JSON {url: {sidebar:[…], related:[…], bridge:[…], qnet:[…], hood:[…], more:[…]}} (link texts) */
$base = getenv( 'WPX_URL' ) ?: 'http://127.0.0.1:8099'; $out = array();
foreach ( array_slice( $argv, 1 ) as $u ) {
	$h = (string) shell_exec( 'curl -s -m 40 ' . escapeshellarg( $base . $u ) );
	$links = function ( $re ) use ( $h ) { if ( ! preg_match( $re, $h, $m ) ) { return array(); } preg_match_all( '#<a [^>]*>(.*?)</a>#s', $m[0], $a ); return array_values( array_map( function ( $t ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( $t ) ) ); }, array_filter( $a[1], function ( $t ) { return '' !== trim( strip_tags( $t ) ); } ) ) ); };
	$out[ $u ] = array(
		'sidebar' => array_values( array_filter( $links( '#<nav class="sbar".*?</nav>#s' ), function ( $t ) { return false === strpos( $t, 'اطلب معاينة' ); } ) ),
		'related' => $links( '#<section class="sec sec--mint"><div class="wrap">\s*<header[^>]*>(?:(?!</section>).)*?related(?:(?!</section>).)*</section>#s' ) ?: $links( '#<div class="sgrid">.*?</div></section>#s' ),
		'bridge'  => $links( '#<section class="sec sec--tint zad-bridge">.*?</section>#s' ),
		'qnet'    => $links( '#<section class="sec qnetsec.*?</section>#s' ),
		'hood'    => $links( '#<ul class="hd-nb">.*?</ul>#s' ),
		'has_http' => strlen( $h ),
	);
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), "\n";
