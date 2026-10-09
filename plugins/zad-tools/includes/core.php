<?php defined( 'ABSPATH' ) || exit;
/**
 * Shared helpers. Nothing here invents a number: every business value comes from settings (includes/settings.php).
 * The theme's own helpers are used when the theme is active (zad_whatsapp, zad_wa_link, zad_normalize_phone, zad_opt …);
 * the fallbacks below keep the plugin working on any theme.
 */

/** Arabic-Indic / Persian digits → ASCII (also ٬ ٫). Same table as the theme's zad_digits_en() and as ZT.digits() in assets/js/zt-core.js. */
function zt_digits_en( $s ) {
	return strtr( (string) $s, array(
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٬' => ',', '٫' => '.', '،' => ',',
	) );
}

/** A number typed by a person («٣٫٥» / «1,200» / «12 م²») → float, or null when there is no number. Mirrors ZT.num(). */
function zt_num( $v ) {
	$t = zt_digits_en( (string) $v );
	$t = str_replace( ',', '', $t );
	if ( preg_match( '/-?\d+(?:\.\d+)?/', $t, $m ) ) {
		return (float) $m[0];
	}
	return null;
}

/** Saudi mobile → 05xxxxxxxx ; anything plausible but not Saudi mobile → digits only; '' when invalid. */
function zt_normalize_phone( $raw ) {
	if ( function_exists( 'zad_normalize_phone' ) ) {
		return zad_normalize_phone( $raw );
	}
	$all = preg_replace( '/\D+/', '', zt_digits_en( (string) $raw ) );
	$d   = $all;
	if ( 0 === strpos( $d, '00966' ) ) { $d = substr( $d, 5 ); }
	elseif ( 0 === strpos( $d, '966' ) ) { $d = substr( $d, 3 ); }
	$d = ltrim( $d, '0' );
	if ( preg_match( '/^5\d{8}$/', $d ) ) { return '0' . $d; }
	return ( strlen( $all ) >= 8 && strlen( $all ) <= 15 ) ? $all : ''; // landline / foreign: the digits as typed
}

/** 9665xxxxxxxx for wa.me. */
function zt_intl_phone( $p ) {
	if ( function_exists( 'zad_intl_number' ) ) { return zad_intl_number( $p ); }
	$d = preg_replace( '/\D+/', '', (string) $p );
	return preg_match( '/^05\d{8}$/', $d ) ? '966' . substr( $d, 1 ) : $d;
}

/** The business WhatsApp number (digits, international) — the theme's setting. */
function zt_wa_number() {
	return function_exists( 'zad_whatsapp' ) ? zad_whatsapp( 0 ) : '';
}

/** wa.me link with a ready message (theme helper when present). */
function zt_wa_link( $text ) {
	if ( function_exists( 'zad_wa_link' ) ) { return zad_wa_link( $text, 0 ); }
	$n = zt_wa_number();
	return $n ? 'https://wa.me/' . $n . '?text=' . rawurlencode( $text ) : '';
}

function zt_brand() {
	return function_exists( 'zad_opt' ) ? (string) zad_opt( 'zad_provider', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
}

/** Per-IP key that never stores the IP itself. */
function zt_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 20 );
}

/** Fixed-window rate limit on transients. true = allowed (and counted), false = over the limit. */
function zt_rate_limit( $bucket, $limit, $window = HOUR_IN_SECONDS ) {
	$key = 'zt_rl_' . md5( $bucket . '|' . zt_ip_hash() );
	$n   = (int) get_transient( $key );
	if ( $n >= (int) $limit ) { return false; }
	set_transient( $key, $n + 1, $window );
	return true;
}

/**
 * Cache-safe request token (pages are cached by LiteSpeed, so a WP nonce printed in the page would expire inside the cache).
 * The page fetches a fresh token from GET /zad/v1/token (never cached); it is an HMAC of a time bucket + the browser's UA.
 */
function zt_token_make( $now = null ) {
	$now    = null === $now ? time() : (int) $now;
	$ttl    = max( 5, (int) zt_opt( 'general.token_ttl_minutes' ) ) * MINUTE_IN_SECONDS;
	$bucket = (int) floor( $now / $ttl );
	return $bucket . '.' . zt_token_sig( $bucket );
}
function zt_token_sig( $bucket ) {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	return substr( hash_hmac( 'sha256', $bucket . '|' . md5( $ua ), wp_salt( 'nonce' ) ), 0, 24 );
}
function zt_token_check( $tok, $now = null ) {
	$now = null === $now ? time() : (int) $now;
	if ( ! is_string( $tok ) || ! preg_match( '/^(\d+)\.([a-f0-9]{24})$/', $tok, $m ) ) { return false; }
	$ttl = max( 5, (int) zt_opt( 'general.token_ttl_minutes' ) ) * MINUTE_IN_SECONDS;
	$cur = (int) floor( $now / $ttl );
	$b   = (int) $m[1];
	return ( $b === $cur || $b === $cur - 1 ) && hash_equals( zt_token_sig( $b ), $m[2] );
}

/** Quote a pipe-separated table setting («a | b | c» per line) → array of cell arrays. */
function zt_table( $text ) {
	$rows = array();
	foreach ( preg_split( '/\r\n|\n|\r/', (string) $text ) as $l ) {
		$l = preg_replace( '/^\s+|\s+$/u', '', $l );
		if ( '' === $l || 0 === strpos( $l, '#' ) ) { continue; }
		$rows[] = array_map( function ( $c ) { return preg_replace( '/^\s+|\s+$/u', '', $c ); }, explode( '|', $l ) );
	}
	return $rows;
}

/* ------------------------------------------------------------------------------------------------------------------
 * PHP twins of the pure functions in assets/js/zt-core.js (tests/parity.test.mjs checks that both give the same result).
 * ---------------------------------------------------------------------------------------------------------------- */

/** Latin digits, "," thousands, at most 2 decimals, no trailing zeros (n >= 0). */
function zt_fmt( $n ) {
	if ( null === $n || ! is_numeric( $n ) ) { return ''; }
	$cents = (int) floor( $n * 100 + 0.5 + 1e-9 );
	$whole = intdiv( $cents, 100 ); $frac = $cents % 100;
	$w     = number_format( $whole, 0, '.', ',' );
	if ( 0 === $frac ) { return $w; }
	return $w . '.' . preg_replace( '/0$/', '', str_pad( (string) $frac, 2, '0', STR_PAD_LEFT ) );
}

function zt_qs_string( $o ) {
	ksort( $o, SORT_STRING );
	$p = array();
	foreach ( $o as $k => $v ) {
		if ( '' === $v || null === $v || false === $v ) { continue; }
		$p[] = rawurlencode( $k ) . '=' . rawurlencode( true === $v ? '1' : (string) $v );
	}
	return implode( '&', $p );
}

function zt_wa_message( $result, $hood = '', $service = '' ) {
	$out = array( 'السلام عليكم،', trim( preg_replace( '/\s+/u', ' ', (string) $result ) ) );
	if ( '' !== trim( (string) $service ) ) { $out[] = trim( (string) $service ); }
	if ( '' !== trim( (string) $hood ) ) { $out[] = 'في حي ' . trim( preg_replace( '/^\s*حي\s+/u', '', (string) $hood ) ) . '.'; }
	return implode( ' ', array_filter( $out, function ( $x ) { return '' !== $x; } ) );
}

function zt_clean_params( $p ) {
	$out = array();
	foreach ( (array) $p as $k => $v ) {
		if ( preg_match( '/phone|mobile|tel|name|email|mail|address|note|msg|message|token/i', (string) $k ) ) { continue; }
		if ( is_int( $v ) || is_float( $v ) || is_bool( $v ) || ( is_string( $v ) && mb_strlen( $v ) <= 60 ) ) { $out[ $k ] = $v; }
	}
	return $out;
}
