<?php
/** Captures what a FAQ page exposes (JSON-LD graph/types/@id, meta description, og:description, answer box, pages that list the answer) → JSON on stdout. Usage: php faq-capture.php slug [slug…] */
$base = getenv( 'WPX_URL' ) ?: 'http://127.0.0.1:8099';
$out  = array();
foreach ( array_slice( $argv, 1 ) as $slug ) {
	$h = shell_exec( 'curl -s -m 30 ' . escapeshellarg( $base . '/faq/' . $slug . '/' ) );
	$r = array( 'bytes' => strlen( (string) $h ) );
	preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', (string) $h, $m );
	$nodes = array(); $faqpages = 0;
	foreach ( $m[1] as $j ) {
		$d = json_decode( $j, true ); if ( ! $d ) { continue; }
		$list = isset( $d['@graph'] ) ? $d['@graph'] : array( $d );
		foreach ( $list as $n ) { $nodes[] = $n; if ( isset( $n['@type'] ) && 'FAQPage' === $n['@type'] ) { $faqpages++; } }
	}
	$r['types'] = array_map( function ( $n ) { return ( is_array( $n['@type'] ?? '' ) ? implode( '+', $n['@type'] ) : ( $n['@type'] ?? '?' ) ) . '|' . ( $n['@id'] ?? '' ); }, $nodes );
	$r['faqpage_count'] = $faqpages;
	foreach ( $nodes as $n ) { if ( 'FAQPage' === ( $n['@type'] ?? '' ) ) { $r['faq_id'] = $n['@id'] ?? ''; $r['q'] = $n['mainEntity'][0]['name'] ?? ''; $r['answer'] = $n['mainEntity'][0]['acceptedAnswer']['text'] ?? ''; } }
	preg_match( '#<meta name="description" content="([^"]*)"#', (string) $h, $d ); $r['meta_desc'] = html_entity_decode( $d[1] ?? '' );
	preg_match( '#<meta property="og:description" content="([^"]*)"#', (string) $h, $d ); $r['og_desc'] = html_entity_decode( $d[1] ?? '' );
	preg_match( '#<div class="answer">.*?</div>#s', (string) $h, $d ); $r['answer_box'] = trim( preg_replace( '/\s+/', ' ', strip_tags( $d[0] ?? '' ) ) );
	preg_match( '#<div class="prose entry-content">(.*?)</div>\s*<p class="meta-line"#s', (string) $h, $d ); $r['content_text'] = trim( preg_replace( '/\s+/', ' ', strip_tags( $d[1] ?? '' ) ) );
	$r['fork_cards'] = preg_match_all( '#class="fork__t#', (string) $h );
	$out[ $slug ] = $r;
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), "\n";
