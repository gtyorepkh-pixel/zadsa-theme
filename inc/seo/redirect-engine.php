<?php defined( 'ABSPATH' ) || exit;
/**
 * Theme redirects (after rokn inc/redirects.php): one map in the option zad_redirects, applied at parse_request before WordPress runs its query,
 * so the Redirection plugin is not needed. Rule = array( code, target, src ). code 301 = redirect to target, 410 = gone (target empty).
 * Keys: decoded path, lower case, no trailing slash ("/عزل-فوم-بالرياض"); a key may carry a query ("/clientlog?x=1") that must match exactly.
 * Also: old SEO-plugin sitemap names → /wp-sitemap.xml, and junk URLs (phone links pasted into paths, broken esc_url(), ?replytocom, *.php) → 410.
 * Tools → «التحويلات (زاد)» lists, adds, removes and imports the rules from the Redirection plugin.
 */

/** Map key of a path. */
function zad_redirect_key( $path ) {
	$path = rawurldecode( (string) $path );
	$q    = '';
	if ( false !== strpos( $path, '?' ) ) { list( $path, $q ) = explode( '?', $path, 2 ); }
	$path = '/' . trim( $path, '/' );
	return mb_strtolower( $path, 'UTF-8' ) . ( '' !== $q ? '?' . $q : '' );
}

/**
 * Percent-encode every byte outside printable ASCII. PHP's parse_url() rewrites the bytes 0x80-0x9F and 0xAD to "_",
 * which silently destroys a raw Arabic path (/معرض-الأعمال/ became /?_عرض-ا?_أع?_ا?_). Encoding first keeps it intact.
 */
function zad_url_enc_bytes( $url ) {
	return (string) preg_replace_callback(
		'/[^\x20-\x7E]/',
		static function ( $m ) {
			return rawurlencode( $m[0] );
		},
		trim( (string) $url )
	);
}

/** Path and query of a URL (percent-encoded, as parse_url returns them for an encoded URL), safe for raw non-ASCII input. */
function zad_url_split( $url ) {
	$enc = zad_url_enc_bytes( $url );
	if ( '' === $enc ) { return array( '', '' ); }
	return array( (string) wp_parse_url( $enc, PHP_URL_PATH ), (string) wp_parse_url( $enc, PHP_URL_QUERY ) );
}

/** All rules (key => array( code, target, src )). */
function zad_redirect_rules() {
	$r = get_option( 'zad_redirects', array() );
	return is_array( $r ) ? $r : array();
}

/** The rule for a path (or URL), or null. */
function zad_redirect_find( $path ) {
	list( $p, $q ) = zad_url_split( $path );
	$rules = zad_redirect_rules();
	if ( '' !== $q ) { $k = zad_redirect_key( $p . '?' . $q ); if ( isset( $rules[ $k ] ) ) { return $rules[ $k ]; } }
	$k = zad_redirect_key( $p );
	return ( '/' !== $k && isset( $rules[ $k ] ) ) ? $rules[ $k ] : null;
}

/** Add or replace one rule. Returns the key ('' when refused: empty source, the home page, or a rule that points to itself). */
function zad_redirect_set( $from, $code, $to = '', $src = 'manual' ) {
	$code = 410 === (int) $code ? 410 : 301;
	$key  = zad_redirect_key( $from );
	$to   = trim( (string) $to );
	if ( '/' === $key || '' === trim( (string) $from ) || ( 301 === $code && '' === $to ) ) { return ''; }
	if ( 301 === $code ) {
		$enc  = zad_url_enc_bytes( $to );
		$host = (string) wp_parse_url( $enc, PHP_URL_HOST );
		if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) === $host ) { $to = (string) wp_parse_url( $enc, PHP_URL_PATH ) . ( wp_parse_url( $enc, PHP_URL_QUERY ) ? '?' . wp_parse_url( $enc, PHP_URL_QUERY ) : '' ) . ( wp_parse_url( $enc, PHP_URL_FRAGMENT ) ? '#' . wp_parse_url( $enc, PHP_URL_FRAGMENT ) : '' ); }
		if ( ! preg_match( '#^https?://#i', $to ) ) { $to = '/' . ltrim( rawurldecode( $to ), '/' ); }
		if ( ! preg_match( '#^https?://#i', $to ) && zad_redirect_key( $to ) === $key ) { return ''; }
	}
	$rules         = zad_redirect_rules();
	$rules[ $key ] = array( $code, 301 === $code ? $to : '', sanitize_key( $src ) );
	update_option( 'zad_redirects', $rules, true );
	return $key;
}

function zad_redirect_remove( $from ) {
	$rules = zad_redirect_rules();
	$key   = zad_redirect_key( $from );
	if ( ! isset( $rules[ $key ] ) ) { return false; }
	unset( $rules[ $key ] );
	update_option( 'zad_redirects', $rules, true );
	return true;
}

/** Percent-encode a site path for the Location header (Arabic slugs). */
function zad_redirect_encode( $path ) {
	$frag = '';
	if ( false !== strpos( $path, '#' ) ) { list( $path, $frag ) = explode( '#', $path, 2 ); $frag = '#' . rawurlencode( $frag ); }
	$parts = explode( '?', $path, 2 );
	$enc   = implode( '/', array_map( 'rawurlencode', explode( '/', $parts[0] ) ) );
	return $enc . ( isset( $parts[1] ) ? '?' . $parts[1] : '' ) . $frag;
}

function zad_redirect_url( $target ) {
	return preg_match( '#^https?://#i', $target ) ? $target : home_url( zad_redirect_encode( $target ) );
}

function zad_redirect_gone( $wp ) {
	$GLOBALS['zad_gone'] = true;
	$wp->query_vars      = array( 'error' => '404' );
}

add_action( 'parse_request', function ( $wp ) {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) { return; }
	if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) { return; }
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$parts = wp_parse_url( $uri );
	$path  = (string) ( $parts['path'] ?? '/' );
	$query = (string) ( $parts['query'] ?? '' );
	$key   = zad_redirect_key( $path );
	if ( 0 === strpos( $key, '/wp-json' ) || 0 === strpos( $key, '/wp-admin' ) ) { return; }

	// Sitemap names of the old SEO plugin → the WordPress sitemap.
	if ( preg_match( '#^/(sitemap_index|(?!wp-)[a-z_]+-sitemap\d*)\.xml$#i', $key ) ) {
		wp_redirect( home_url( '/wp-sitemap.xml' ), 301, 'Zad' );
		exit;
	}

	$rule = zad_redirect_find( $path . ( '' !== $query ? '?' . $query : '' ) );
	if ( $rule ) {
		if ( 410 === (int) $rule[0] ) { zad_redirect_gone( $wp ); return; }
		wp_redirect( zad_redirect_url( (string) $rule[1] ), 301, 'Zad' );
		exit;
	}

	$php_junk = preg_match( '#\.php\d?$#i', $key ) && ! preg_match( '#^/(index\.php|wp-[a-z-]+\.php)$#i', $key ); // a missing .php file (scanners); WordPress's own entry files are left alone
	if ( zad_opt( 'zad_redirect_junk', true ) && ( isset( $_GET['replytocom'] ) || $php_junk || preg_match( '#(^|/)(tel|te|whatsapp|mailto):|esc_url\(|\. https?:/#i', $key ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		zad_redirect_gone( $wp );
	}
}, 1 );

add_action( 'template_redirect', function () {
	if ( ! empty( $GLOBALS['zad_gone'] ) ) {
		status_header( 410 );
		nocache_headers();
	}
}, 0 );

/** Publishing a page whose URL is a redirect source: the page would never be reachable. Warn the editor. */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || ! is_post_type_viewable( $post->post_type ) ) { return; }
	$path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
	if ( $path && zad_redirect_find( $path ) ) { set_transient( 'zad_redirect_clash_' . get_current_user_id(), zad_redirect_key( $path ), 10 * MINUTE_IN_SECONDS ); }
}, 10, 3 );
add_action( 'admin_notices', function () {
	$k = get_transient( 'zad_redirect_clash_' . get_current_user_id() );
	if ( ! $k ) { return; }
	delete_transient( 'zad_redirect_clash_' . get_current_user_id() );
	echo '<div class="notice notice-warning"><p><strong>تنبيه:</strong> رابط الصفحة <code dir="ltr">' . esc_html( $k ) . '</code> عليه تحويل في «التحويلات (زاد)»، فالزائر هيتحوّل بعيد عنها. احذف التحويل من الأدوات ← التحويلات (زاد) لو عايز الصفحة تظهر.</p></div>';
} );
