<?php defined( 'ABSPATH' ) || exit;
/* ==========================================================================
   4. نظام التتبع المؤجل للبيكسلات والتحويلات (Ported from rokn inc/tracking.php)
   ========================================================================== */
/** Accepted format of each tracking ID (as in rokn inc/tracking.php). A value that does not match is ignored, never printed. */
function zad_tracking_formats() {
	return array(
		'zad_ga4_id'       => '/^G-[A-Z0-9]{6,12}$/',
		'zad_gads_id'      => '/^AW-\d{8,12}$/',
		'zad_gads_label'   => '/^[A-Za-z0-9_-]{6,40}$/',
		'zad_meta_pixel'   => '/^\d{15,16}$/',
		'zad_tiktok_pixel' => '/^[A-Z0-9]{18,22}$/',
		'zad_snap_pixel'   => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
	);
}
/** A tracking ID from the settings, '' when empty or in the wrong format. */
function zad_tracking_id( $key ) {
	$v   = trim( (string) zad_opt( $key, '' ) );
	$fmt = zad_tracking_formats()[ $key ] ?? '';
	return ( '' !== $v && $fmt && preg_match( $fmt, $v ) ) ? $v : '';
}
/**
 * Pixels run on the live site only: not on Local / staging hosts (.local, .test, localhost, staging., IP) and not while
 * «Discourage search engines» is on. Filter zad_is_production to force it either way.
 */
function zad_is_production() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$dev  = '' === $host || 'localhost' === $host || filter_var( $host, FILTER_VALIDATE_IP ) || preg_match( '/\.(?:local|localhost|test|invalid|example)$/', $host ) || preg_match( '/^(?:staging|stage|dev|test)\./', $host );
	$live = ! $dev && (bool) get_option( 'blog_public' ) && ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() );
	return (bool) apply_filters( 'zad_is_production', $live );
}

add_action( 'wp_head', function () {
	if ( ! zad_is_production() ) {
		return; // لا بيكسلات على اللوكال أو نسخ التجربة
	}
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return; // لا نحمّل البيكسلات للمدير والمحررين لمنع تشويه الإحصائيات
	}
	$ga   = zad_tracking_id( 'zad_ga4_id' );
	$aw   = zad_tracking_id( 'zad_gads_id' );
	$awl  = $aw ? zad_tracking_id( 'zad_gads_label' ) : '';
	$meta = zad_tracking_id( 'zad_meta_pixel' );
	$tt   = zad_tracking_id( 'zad_tiktok_pixel' );
	$snap = zad_tracking_id( 'zad_snap_pixel' );

	if ( ! $ga && ! $aw && ! $meta && ! $tt && ! $snap ) {
		return;
	}
	$js = 'var R=[];';
	if ( $ga || $aw ) {
		$js .= 'window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments)};gtag("js",new Date());';
		if ( $ga ) { $js .= 'gtag("config",' . wp_json_encode( $ga ) . ');'; }
		if ( $aw ) { $js .= 'gtag("config",' . wp_json_encode( $aw ) . ');'; }
		$js .= 'R.push("https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga ? $ga : $aw ) . '");';
	}
	if ( $meta ) {
		$js .= 'var f=window.fbq=function(){f.callMethod?f.callMethod.apply(f,arguments):f.queue.push(arguments)};f.push=f;f.loaded=!0;f.version="2.0";f.queue=[];window._fbq=f;fbq("init",' . wp_json_encode( $meta ) . ');fbq("track","PageView");R.push("https://connect.facebook.net/en_US/fbevents.js");';
	}
	if ( $tt ) {
		$js .= 'var t=window.ttq=window.ttq||[];["page","track","identify"].forEach(function(m){t[m]=function(){t.push([m].concat([].slice.call(arguments)))}});t.page();R.push("https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' . rawurlencode( $tt ) . '&lib=ttq");';
	}
	if ( $snap ) {
		$js .= 'var s=window.snaptr=function(){s.handleRequest?s.handleRequest.apply(s,arguments):s.queue.push(arguments)};s.queue=[];snaptr("init",' . wp_json_encode( $snap ) . ');snaptr("track","PAGE_VIEW");R.push("https://sc-static.net/scevent.min.js");';
	}
	// إطلاق حدث التحويل تلقائياً عند النقر على أي زر اتصال أو واتساب يحمل data-ev
	$js .= 'document.addEventListener("click",function(e){var a=e.target.closest("[data-ev]");if(!a)return;'
		. ( $ga ? 'if(window.gtag)gtag("event",a.getAttribute("data-ev"),{event_category:"lead",event_label:a.getAttribute("data-src")||""});' : '' )
		. ( $aw && $awl ? 'if(window.gtag)gtag("event","conversion",{send_to:' . wp_json_encode( $aw . '/' . $awl ) . '});' : '' )
		. ( $meta ? 'if(window.fbq)fbq("track","Lead");' : '' )
		. ( $tt ? 'if(window.ttq)ttq.track("Contact");' : '' )
		. ( $snap ? 'if(window.snaptr)snaptr("track","SIGN_UP");' : '' )
		. '});';
	$js .= 'function L(){R.forEach(function(u){var e=document.createElement("script");e.async=!0;e.src=u;document.head.appendChild(e)})}'
		. 'function I(){("requestIdleCallback" in window)?requestIdleCallback(L,{timeout:3000}):setTimeout(L,1500)}'
		. '(document.readyState==="complete")?I():addEventListener("load",I,{once:!0});';
	echo '<script id="zad-idle-tracking">(function(){' . $js . '})();</script>' . "\n";
}, 3 );


/* Settings screen: say which saved tracking ID is ignored because of its format. */
add_action( 'admin_notices', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || 'toplevel_page_memo-theme-options' !== $s->id || ! current_user_can( 'manage_options' ) ) { return; }
	$names = array( 'zad_ga4_id' => 'GA4 (مثل G-6EQMXQ4YR1)', 'zad_gads_id' => 'Google Ads (مثل AW-123456789)', 'zad_gads_label' => 'تسمية تحويل Google Ads', 'zad_meta_pixel' => 'Meta Pixel (15–16 رقماً)', 'zad_tiktok_pixel' => 'TikTok Pixel (18–22 حرفاً كبيراً ورقماً)', 'zad_snap_pixel' => 'Snap Pixel (بصيغة UUID)' );
	$bad = array();
	foreach ( $names as $k => $label ) { $v = trim( (string) zad_opt( $k, '' ) ); if ( '' !== $v && '' === zad_tracking_id( $k ) ) { $bad[] = $label; } }
	if ( $bad ) { echo '<div class="notice notice-warning"><p>معرّفات تتبع محفوظة بصيغة غير صحيحة ولن تُحمَّل: ' . esc_html( implode( '، ', $bad ) ) . '.</p></div>'; }
	if ( ! zad_is_production() ) { echo '<div class="notice notice-info"><p>البيكسلات لا تُحمَّل على هذا الموقع لأنه ليس الموقع الحي (لوكال أو تجربة، أو «منع محركات البحث» مفعّل).</p></div>'; }
} );
