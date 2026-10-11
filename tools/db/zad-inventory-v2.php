<?php
/**
 * Zad Saudi — READ-ONLY content inventory v2 (writes nothing to the database). v2: hours / warranty hits carry surrounding text.
 *
 * Run from the WordPress root:
 *   wp eval-file zad-inventory.php
 * Output: a summary on screen + wp-content/uploads/zad-inventory/inventory-YYYYmmdd-HHMM.csv (one row per hit).
 *
 * What it scans: post_title / post_excerpt / post_content, ALL post meta (_zad_* and Yoast title/desc), term descriptions,
 * and options (the theme options, Yoast titles/taxonomy meta, widgets). Patterns: warranty wording, working hours,
 * the local domain, and the "الجل الجديد" typo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit( "Run with: wp eval-file zad-inventory.php\n" ); }
global $wpdb;

$types = array( 'page', 'pest_control', 'cleaning', 'zad_service', 'zad_hood', 'guide', 'sections', 'zad_faq', 'pests-library', 'best_guide', 'post' );

/* -------- which service a row talks about (from slug / title; "other" when unknown) -------- */
function zi_service( $s ) {
	$s = rawurldecode( strtolower( $s ) );
	if ( preg_match( '/نمل[\s\-_]*ابيض|نمل[\s\-_]*أبيض|ارضة|أرضة|termite/u', $s ) ) { return 'termite'; }
	if ( preg_match( '/بق[\s\-_]*الفراش|bed[\s\-_]*bug|bedbug|(?<![\p{L}])بق(?![\p{L}])/u', $s ) ) { return 'bedbug'; }
	if ( preg_match( '/صراصير|صرصور|cockroach|roach/u', $s ) ) { return 'roach'; }
	if ( preg_match( '/فئران|فار|rodent|rat|قوارض/u', $s ) ) { return 'rodent'; }
	if ( preg_match( '/نمل|ant/u', $s ) ) { return 'ant'; }
	return 'other';
}

/* -------- patterns: name => regex over TEXT (tags stripped); a hit = the matched snippet with some context -------- */
$P = array(
	'warranty_duration' => '/.{0,40}ضمان[^\n.؛!؟]{0,70}?(?:[0-9٠-٩]+|سنة|سنتين|سنوات|عام|أعوام|شهر|شهرين|أشهر|شهور|أسبوع|أسبوعين|%|٪|مدى الحياة|مدى|متابعة)[^\n.؛!؟]{0,40}/u',
	'warranty_pct'      => '/(?:[0-9٠-٩]+\s*[%٪])[^\n.]{0,30}ضمان|ضمان[^\n.]{0,30}[0-9٠-٩]+\s*[%٪]/u',
	'warranty_any'      => '/ضمان/u',
	'hours_10pm'        => '/.{0,45}(?:(?:10|١٠|عشرة)\s*(?:م\b|مساء|مساءً|PM|pm)|22:00|٢٢:٠٠|10:00\s*(?:PM|pm|م)).{0,45}/u',   // v2: with context
	'hours_8am'         => '/.{0,45}(?:(?:8|٨|ثمانية)\s*(?:ص\b|صباح|صباحاً|AM|am)|08:00|٠٨:٠٠).{0,45}/u',                   // v2: with context
	'hours_11pm'        => '/.{0,45}(?:(?:11|١١|أحد عشر)\s*(?:م\b|مساء|مساءً|PM|pm)|23:00|٢٣:٠٠).{0,45}/u',                  // v2: already-correct 11pm, to see what is done
	'hours_text'        => '/(?:ساعات|أوقات|اوقات|مواعيد)\s*العمل[^\n.]{0,60}/u',
	'local_domain'      => '/zadksa\.local/iu',
	'typo_jel'          => '/الجل\s+الجديد/u',
);

$rows = array();   // [pattern, scope, object_type, object_id, status, slug, service, field, snippet]
$add  = function ( $pat, $scope, $otype, $oid, $status, $slug, $field, $snippet ) use ( &$rows ) {
	$rows[] = array( $pat, $scope, $otype, $oid, $status, $slug, zi_service( $slug . ' ' . $snippet ), $field, $snippet );
};
$scan = function ( $text, $scope, $otype, $oid, $status, $slug, $field ) use ( $P, $add ) {
	if ( ! is_string( $text ) || '' === $text ) { return; }
	$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
	foreach ( $P as $name => $re ) {
		if ( 'warranty_any' === $name ) { continue; } // counted separately
		if ( preg_match_all( $re, 'local_domain' === $name ? $text : $plain, $m ) ) {
			foreach ( array_unique( $m[0] ) as $hit ) { $add( $name, $scope, $otype, $oid, $status, $slug, $field, mb_substr( $hit, 0, 160 ) ); }
		}
	}
};
$flat = function ( $v, &$out, $prefix = '' ) use ( &$flat ) { // serialized / nested values -> "path => string"
	if ( is_array( $v ) || is_object( $v ) ) { foreach ( (array) $v as $k => $x ) { $flat( $x, $out, $prefix . '/' . $k ); } return; }
	if ( is_string( $v ) && '' !== $v ) { $out[ $prefix ] = $v; }
};

/* ---------------- posts ---------------- */
$counts = array(); // type => [posts, with_warranty_word]
$ph = implode( ',', array_fill( 0, count( $types ), '%s' ) );
$ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_name, post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE post_type IN ($ph) AND post_status NOT IN ('trash','auto-draft','inherit')", $types ) ); // phpcs:ignore
foreach ( $ids as $p ) {
	$key = $p->post_type;
	$counts[ $key ]['posts'] = ( $counts[ $key ]['posts'] ?? 0 ) + 1;
	$slug = $p->post_name . ' ' . $p->post_title;
	foreach ( array( 'post_title' => $p->post_title, 'post_excerpt' => $p->post_excerpt, 'post_content' => $p->post_content ) as $f => $t ) {
		$scan( $t, 'post', $p->post_type, $p->ID, $p->post_status, $slug, $f );
		if ( preg_match( '/ضمان/u', wp_strip_all_tags( (string) $t ) ) ) { $counts[ $key ]['warranty_word'] = ( $counts[ $key ]['warranty_word'] ?? 0 ) + 1; }
	}
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND ( meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s )", $p->ID, '\_zad\_%', '\_yoast\_wpseo\_title', '\_yoast\_wpseo\_metadesc' ) ) as $m ) { // phpcs:ignore
		$v = maybe_unserialize( $m->meta_value ); $out = array(); $flat( $v, $out, $m->meta_key );
		foreach ( $out as $path => $str ) { $scan( $str, 'meta', $p->post_type, $p->ID, $p->post_status, $slug, $path ); }
	}
}

/* ---------------- terms ---------------- */
foreach ( $wpdb->get_results( "SELECT t.term_id, t.slug, t.name, tt.taxonomy, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id" ) as $t ) { // phpcs:ignore
	$scan( $t->description, 'term', $t->taxonomy, $t->term_id, '-', $t->slug . ' ' . $t->name, 'description' );
}

/* ---------------- options (theme options, Yoast, widgets; transients skipped) ---------------- */
foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name NOT LIKE '\_transient%' AND option_name NOT LIKE '\_site\_transient%' AND ( option_name LIKE '%memo%' OR option_name LIKE 'wpseo%' OR option_name LIKE 'widget%' OR option_name LIKE 'zad%' OR option_name IN ('blogname','blogdescription','admin_email','siteurl','home') OR option_value LIKE '%ضمان%' OR option_value LIKE '%zadksa.local%' )" ) as $o ) { // phpcs:ignore
	$v = maybe_unserialize( $o->option_value ); $out = array(); $flat( $v, $out, '' );
	foreach ( $out as $path => $str ) { $scan( $str, 'option', 'option', 0, '-', $o->option_name, $o->option_name . $path ); }
}

/* ---------------- write CSV + summary ---------------- */
$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'zad-inventory';
wp_mkdir_p( $dir );
$file = $dir . '/inventory-' . gmdate( 'Ymd-Hi' ) . '.csv';
$fh = fopen( $file, 'w' );
fwrite( $fh, "\xEF\xBB\xBF" );
fputcsv( $fh, array( 'pattern', 'scope', 'object_type', 'object_id', 'status', 'slug_title', 'service_guess', 'field', 'snippet' ) );
foreach ( $rows as $r ) { fputcsv( $fh, $r ); }
fclose( $fh );

$sum = array();
foreach ( $rows as $r ) { $sum[ $r[0] ][ $r[1] . ':' . $r[2] ][ $r[6] ] = ( $sum[ $r[0] ][ $r[1] . ':' . $r[2] ][ $r[6] ] ?? 0 ) + 1; }
WP_CLI::log( "\n===== Posts scanned per type =====" );
foreach ( $counts as $t => $c ) { WP_CLI::log( sprintf( '%-14s posts=%-4d with the word «ضمان»=%d', $t, $c['posts'], $c['warranty_word'] ?? 0 ) ); }
WP_CLI::log( "\n===== Hits per pattern / location / service =====" );
ksort( $sum );
foreach ( $sum as $pat => $locs ) {
	WP_CLI::log( "\n[$pat]" );
	ksort( $locs );
	foreach ( $locs as $loc => $svcs ) { WP_CLI::log( sprintf( '  %-28s %s', $loc, json_encode( $svcs, JSON_UNESCAPED_UNICODE ) ) ); }
}
WP_CLI::success( count( $rows ) . " hits. CSV: $file" );
