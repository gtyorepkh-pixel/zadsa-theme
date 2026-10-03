<?php
/**
 * 404 helpers: suggestions for the visitor, a log of broken URLs (stored in one option, no extra table),
 * and redirects that exist ONLY when the owner approves them (Tools → مراقب 404).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_404_LOG = 'zad_404_log';
const ZAD_404_RED = 'zad_redirects';

function zad_404_norm( $url ) {
	$p = wp_parse_url( (string) $url );
	$path = rawurldecode( $p['path'] ?? '/' );
	return '/' . trim( mb_strtolower( $path, 'UTF-8' ), '/' ) . '/';
}

/** Similar published pages for a requested path (search by the words of its last segment). */
function zad_404_suggest( $path, $limit = 5 ) {
	$seg    = basename( trim( $path, '/' ) );
	$tokens = array_filter( preg_split( '/[-_\s+]+/u', $seg ), function ( $t ) { return mb_strlen( $t ) >= 3 && ! is_numeric( $t ); } );
	if ( ! $tokens ) { return array(); }
	$types = array_values( array_unique( array_merge( zad_service_types(), zad_faq_types(), array( 'page', 'post' ) ) ) );
	$q = new WP_Query( array( 's' => implode( ' ', array_slice( $tokens, 0, 4 ) ), 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'zad_all' => true ) );
	$out = array();
	foreach ( $q->posts as $p ) { $out[] = array( get_the_title( $p ), get_permalink( $p ), $p->ID ); }
	return $out;
}

/** Main services + a few district/city pages (cached) for the 404 template. */
function zad_404_lists() {
	$c = get_transient( 'zad_404_lists' );
	if ( is_array( $c ) ) { return $c; }
	$svc = array(); $kids = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_parent' => 0, 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $p ) {
		$svc[] = array( get_the_title( $p ), get_permalink( $p ) );
	}
	$ids = function_exists( 'zad_area_page_ids' ) ? array_slice( (array) zad_area_page_ids(), 0, 24 ) : array();
	if ( $ids ) {
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post__in' => $ids, 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => 'title', 'order' => 'ASC', 'zad_all' => true ) ) as $p ) { $kids[] = array( get_the_title( $p ), get_permalink( $p ) ); }
	}
	$c = array( 'svc' => $svc, 'kids' => $kids );
	set_transient( 'zad_404_lists', $c, 6 * HOUR_IN_SECONDS );
	return $c;
}
add_action( 'save_post', function () { delete_transient( 'zad_404_lists' ); } );

/* ---- Log (only real, human-looking GET misses) ---- */
add_action( 'template_redirect', function () {
	if ( ! is_404() || is_admin() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { return; } // phpcs:ignore
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
	$path = zad_404_norm( $uri );
	if ( '/' === $path || strlen( $path ) > 190 || preg_match( '#\.(js|css|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|txt|xml|php|asp|env|git|zip)/?$#i', $path ) || preg_match( '#/(wp-|xmlrpc|\.well-known|cgi-bin)#', $path ) ) { return; }
	$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore
	$ref  = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore
	$log  = (array) get_option( ZAD_404_LOG, array() );
	$k    = md5( $path );
	$rh   = $ref ? (string) wp_parse_url( $ref, PHP_URL_HOST ) : '';
	$cur  = $log[ $k ] ?? array( 'path' => $path, 'hits' => 0, 'first' => time(), 'google' => 0, 'refs' => array() );
	$cur['hits']++; $cur['last'] = time();
	if ( stripos( $ua, 'googlebot' ) !== false ) { $cur['google']++; }
	if ( $rh && count( $cur['refs'] ) < 3 && ! in_array( $rh, $cur['refs'], true ) ) { $cur['refs'][] = $rh; }
	$log[ $k ] = $cur;
	if ( count( $log ) > 300 ) { uasort( $log, function ( $a, $b ) { return $b['hits'] <=> $a['hits']; } ); $log = array_slice( $log, 0, 250, true ); }
	update_option( ZAD_404_LOG, $log, false );
}, 3 );

/* ---- Approved redirects (never touches a URL that exists) ---- */
add_action( 'template_redirect', function () {
	if ( ! is_404() || is_admin() ) { return; }
	$rules = (array) get_option( ZAD_404_RED, array() );
	if ( ! $rules ) { return; }
	$path = zad_404_norm( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore
	if ( ! empty( $rules[ $path ]['to'] ) ) {
		wp_safe_redirect( $rules[ $path ]['to'], 301 );
		exit;
	}
}, 2 );

/* ---- Admin page ---- */
add_action( 'admin_menu', function () {
	add_management_page( 'مراقب 404', 'مراقب 404 (زاد)', 'manage_options', 'zad-404', 'zad_404_page' );
} );

function zad_404_clean_target( $to ) {
	$to = trim( (string) $to );
	if ( '' === $to ) { return ''; }
	if ( '/' === $to[0] && 0 !== strpos( $to, '//' ) ) { $to = home_url( $to ); }
	$h = wp_parse_url( home_url(), PHP_URL_HOST ); $t = wp_parse_url( $to, PHP_URL_HOST );
	return ( $t && strtolower( $t ) === strtolower( $h ) ) ? esc_url_raw( $to ) : '';
}

function zad_404_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$log = (array) get_option( ZAD_404_LOG, array() ); $rules = (array) get_option( ZAD_404_RED, array() ); $msg = '';
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['zad_404_act'] ) && check_admin_referer( 'zad_404' ) ) { // phpcs:ignore
		$act = sanitize_key( wp_unslash( $_POST['zad_404_act'] ) ); $key = isset( $_POST['k'] ) ? sanitize_text_field( wp_unslash( $_POST['k'] ) ) : '';
		if ( 'redirect' === $act && isset( $log[ $key ] ) ) {
			$to = zad_404_clean_target( isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '' );
			if ( $to ) { $rules[ $log[ $key ]['path'] ] = array( 'to' => $to, 'time' => time() ); update_option( ZAD_404_RED, $rules, false ); unset( $log[ $key ] ); update_option( ZAD_404_LOG, $log, false ); $msg = 'حُفظ التحويل (301).'; }
			else { $msg = 'الرابط الهدف يجب أن يكون من موقعك.'; }
		} elseif ( 'ignore' === $act && isset( $log[ $key ] ) ) { unset( $log[ $key ] ); update_option( ZAD_404_LOG, $log, false ); $msg = 'أُزيل من السجل.';
		} elseif ( 'unredirect' === $act ) { $p = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : ''; unset( $rules[ $p ] ); update_option( ZAD_404_RED, $rules, false ); $msg = 'أُلغي التحويل.';
		} elseif ( 'clear' === $act ) { update_option( ZAD_404_LOG, array(), false ); $log = array(); $msg = 'مُسح السجل.'; }
	}
	uasort( $log, function ( $a, $b ) { return array( $b['google'] > 0, $b['hits'] ) <=> array( $a['google'] > 0, $a['hits'] ); } );
	echo '<div class="wrap" dir="rtl"><h1>مراقب 404</h1>';
	if ( $msg ) { echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>'; }
	echo '<p>يسجّل الروابط المكسورة التي زارها الناس أو جوجل. <b>لا يُنشأ أي تحويل إلا بموافقتك</b> هنا، ولا يعمل التحويل على رابط له صفحة فعلية. الروابط التي زارها جوجل تظهر أولاً.</p>';
	echo '<h2>روابط مكسورة مسجّلة (' . count( $log ) . ')</h2>';
	if ( ! $log ) { echo '<p>لا شيء حتى الآن.</p>'; } else {
		echo '<table class="widefat striped"><thead><tr><th>الرابط</th><th>الزيارات</th><th>جوجل</th><th>مصادر</th><th>آخر زيارة</th><th>تحويل إلى</th></tr></thead><tbody>';
		foreach ( array_slice( $log, 0, 100, true ) as $k => $r ) {
			$sug = zad_404_suggest( $r['path'], 3 );
			echo '<tr><td dir="ltr"><code>' . esc_html( rawurldecode( $r['path'] ) ) . '</code></td><td>' . (int) $r['hits'] . '</td><td>' . ( $r['google'] ? '<b style="color:#b91c1c">' . (int) $r['google'] . '</b>' : '—' ) . '</td><td>' . esc_html( implode( '، ', $r['refs'] ) ?: '—' ) . '</td><td>' . esc_html( human_time_diff( $r['last'] ) ) . ' مضت</td><td>';
			echo '<form method="post" style="display:flex;gap:4px;flex-wrap:wrap">'; wp_nonce_field( 'zad_404' );
			echo '<input type="hidden" name="k" value="' . esc_attr( $k ) . '"><input type="url" name="to" dir="ltr" size="34" placeholder="' . esc_attr( home_url( '/…' ) ) . '" value="' . esc_attr( $sug ? $sug[0][1] : '' ) . '" list="zs' . esc_attr( $k ) . '">';
			echo '<datalist id="zs' . esc_attr( $k ) . '">'; foreach ( $sug as $s ) { echo '<option value="' . esc_attr( $s[1] ) . '">' . esc_html( $s[0] ) . '</option>'; } echo '</datalist>';
			echo '<button class="button button-primary" name="zad_404_act" value="redirect">تحويل 301</button> <button class="button" name="zad_404_act" value="ignore">تجاهل</button></form>';
			if ( $sug ) { echo '<small>مقترح: ' . esc_html( $sug[0][0] ) . '</small>'; }
			echo '</td></tr>';
		}
		echo '</tbody></table><form method="post" style="margin-top:8px">'; wp_nonce_field( 'zad_404' ); echo '<button class="button" name="zad_404_act" value="clear" onclick="return confirm(\'مسح كل السجل؟\')">مسح السجل</button></form>';
	}
	echo '<h2>التحويلات المعتمدة (' . count( $rules ) . ')</h2>';
	if ( ! $rules ) { echo '<p>لا توجد.</p>'; } else {
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rules as $from => $r ) { echo '<tr><td dir="ltr"><code>' . esc_html( rawurldecode( $from ) ) . '</code></td><td dir="ltr">→ <a href="' . esc_url( $r['to'] ) . '" target="_blank">' . esc_html( rawurldecode( $r['to'] ) ) . '</a></td><td><form method="post">'; wp_nonce_field( 'zad_404' ); echo '<input type="hidden" name="path" value="' . esc_attr( $from ) . '"><button class="button" name="zad_404_act" value="unredirect">إلغاء</button></form></td></tr>'; }
		echo '</tbody></table>';
	}
	echo '</div>';
}
