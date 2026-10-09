<?php defined( 'ABSPATH' ) || exit;
/**
 * Public side of the order system: /track/{token}/ (tracking + warranty certificate + review), /warranty/verify/ (code check).
 * Both are ordinary Pages that use a plugin template (so the editor chooses the parent page and the slug). Both are noindex, kept out of
 * every sitemap and out of the page cache. The token route is the ONE rewrite rule of the plugin: it maps /<track page>/<32 hex>/ to that page.
 */

const ZT_TEMPLATE_TRACK  = 'zt-track-page.php';
const ZT_TEMPLATE_VERIFY = 'zt-verify-page.php';

add_filter( 'theme_page_templates', function ( $t ) {
	$t[ ZT_TEMPLATE_TRACK ]  = 'تتبع الطلب (zad-tools)';
	$t[ ZT_TEMPLATE_VERIFY ] = 'التحقق من الضمان (zad-tools)';
	return $t;
} );

function zt_page_kind( $id = 0 ) {
	$id = $id ? (int) $id : (int) get_queried_object_id();
	if ( ! $id || 'page' !== get_post_type( $id ) ) { return ''; }
	$t = get_page_template_slug( $id );
	return ZT_TEMPLATE_TRACK === $t ? 'track' : ( ZT_TEMPLATE_VERIFY === $t ? 'verify' : '' );
}

add_filter( 'template_include', function ( $tpl ) {
	$k = is_page() ? zt_page_kind() : '';
	if ( '' === $k ) { return $tpl; }
	$theme = locate_template( 'zad-tools/' . $k . '-page.php' );
	return $theme ? $theme : ZT_DIR . 'templates/' . $k . '-page.php';
}, 30 );

/* ---- the route /<track page>/<token>/ ---- */
add_filter( 'query_vars', function ( $v ) { $v[] = 'zt_token'; return $v; } );
add_action( 'init', function () {
	$p = (string) get_option( 'zt_track_path', '' );
	if ( '' !== $p ) { add_rewrite_rule( '^' . preg_quote( $p, '#' ) . '/([a-f0-9]{32})/?$', 'index.php?pagename=' . $p . '&zt_token=$matches[1]', 'top' ); }
	if ( get_option( 'zt_flush_rules' ) ) { delete_option( 'zt_flush_rules' ); flush_rewrite_rules( false ); }
} );

/** Saving a track / verify page: noindex + out of the sitemaps (the theme's and Yoast's own flags), and the route follows the page's address. */
add_action( 'save_post_page', function ( $id ) {
	$k = zt_page_kind( $id );
	if ( '' === $k || wp_is_post_revision( $id ) ) { return; }
	update_post_meta( $id, '_zad_seo_noindex', '1' );
	update_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', '1' );
	if ( 'track' === $k ) {
		$uri = trim( (string) get_page_uri( $id ), '/' );
		if ( $uri !== (string) get_option( 'zt_track_path', '' ) ) { update_option( 'zt_track_path', $uri, false ); update_option( 'zt_flush_rules', 1, false ); }
	}
}, 20 );

function zt_private_page_ids() {
	$q = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 20, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_wp_page_template', 'value' => array( ZT_TEMPLATE_TRACK, ZT_TEMPLATE_VERIFY ), 'compare' => 'IN' ) ) ) );
	return array_map( 'intval', $q );
}
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) { if ( 'page' === $type ) { $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), zt_private_page_ids() ); } return $args; }, 10, 2 );
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) { return array_values( array_unique( array_merge( (array) $ids, zt_private_page_ids() ) ) ); } );

add_filter( 'wp_robots', function ( $r ) {
	if ( zt_page_kind() ) { $r['noindex'] = true; $r['nofollow'] = true; $r['noarchive'] = true; unset( $r['index'], $r['follow'], $r['max-image-preview'] ); }
	return $r;
}, 99 );

/** No page cache and noindex header for anything that shows an order. */
function zt_private_headers() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	do_action( 'litespeed_control_set_nocache', 'zad-tools private page' );
}

add_action( 'wp_enqueue_scripts', function () {
	$k = zt_page_kind();
	if ( '' === $k ) { return; }
	wp_enqueue_style( 'zt-tool', ZT_URL . 'assets/css/zt-tool.css', array(), ZT_VERSION );
	wp_enqueue_style( 'zt-orders', ZT_URL . 'assets/css/zt-orders.css', array( 'zt-tool' ), ZT_VERSION );
	wp_enqueue_script( 'zt-core', ZT_URL . 'assets/js/zt-core.js', array(), ZT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'zt-core', 'ZT_CFG', array( 'tool' => 'orders-' . $k, 'wa' => zt_wa_number(), 'rest' => esc_url_raw( rest_url( 'zad/v1/' ) ), 'ga' => (bool) zt_opt( 'general.ga_events' ), 'brand' => zt_brand(),
		'poll' => (int) zt_opt( 'orders.poll_seconds' ), 'token' => (string) get_query_var( 'zt_token', isset( $_GET['t'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['t'] ) ) : '' ) ) );
	if ( 'track' === $k ) { wp_enqueue_script( 'zt-track', ZT_URL . 'assets/js/zt-track.js', array( 'zt-core' ), ZT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) ); }
} );

/* ---------------------------------------------- the public view of an order (no phone, no address, no full name) ---------------------------------------------- */

function zt_track_public( $oid ) {
	$o = zt_order_get( $oid ); $sts = zt_order_statuses(); $flow = zt_order_flow(); $reached = array();
	foreach ( $o['log'] as $l ) { $reached[ $l[0] ] = (int) $l[1]; }
	$steps = array();
	foreach ( $flow as $s ) { $steps[] = array( 'key' => $s, 'label' => $sts[ $s ], 'at' => isset( $reached[ $s ] ) ? wp_date( 'Y-m-d H:i', $reached[ $s ] ) : '', 'done' => isset( $reached[ $s ] ) ); }
	$when = trim( $o['appt_date'] . ' ' . $o['appt_time'] . ' ' . $o['appt_period'] );
	$out = array(
		'status' => $o['status'], 'label' => $sts[ $o['status'] ] ?? '', 'service' => $o['service'], 'hood' => $o['hood'], 'city' => $o['city'], 'appointment' => $when, 'steps' => $steps,
		'technician' => in_array( $o['status'], array( 'assigned', 'on_the_way', 'arrived', 'done' ), true ) ? zt_technician_card( $o['technician_id'] ) : array(),
		'warranty' => array(),
	);
	if ( 'done' === $o['status'] && $o['warranty_code'] ) {
		$out['warranty'] = array( 'code' => $o['warranty_code'], 'months' => $o['warranty_months'], 'from' => $o['service_date'], 'to' => $o['warranty_end'], 'valid' => wp_date( 'Y-m-d' ) <= $o['warranty_end'] );
	}
	return $out;
}

function zt_verify_lookup( $code ) {
	$oid = zt_order_by_code( $code );
	if ( ! $oid ) { return array( 'state' => 'invalid' ); }
	$o = zt_order_get( $oid );
	return array( 'state' => wp_date( 'Y-m-d' ) <= $o['warranty_end'] ? 'valid' : 'expired', 'service' => $o['service'], 'from' => $o['service_date'], 'to' => $o['warranty_end'], 'name' => zt_mask_name( $o['first_name'] ), 'code' => $o['warranty_code'] );
}

/* ---------------------------------------------- rendering ---------------------------------------------- */

function zt_privacy_line() {
	$u = zt_privacy_url();
	return '<a href="' . esc_url( $u ) . '">سياسة الخصوصية</a>';
}

/** The tracking page body for a token ('' token = the form to type a link is not offered: the link comes by message). */
function zt_render_track( $token ) {
	zt_private_headers();
	if ( ! zt_orders_ready() ) { echo '<section class="sec"><div class="wrap wrap--narrow"><p class="zt-note"><strong>خدمة تتبع الطلب قيد التفعيل.</strong></p></div></section>'; return; }
	$oid = zt_order_by_token( $token );
	if ( ! $oid || ! zt_rate_limit( 'track', (int) zt_opt( 'orders.track_per_hour' ) ) ) {
		echo '<section class="sec"><div class="wrap wrap--narrow"><h2>رابط التتبع غير صالح</h2><p>تأكد من الرابط الذي وصلك في رسالة واتساب، أو تواصل معنا.</p></div></section>'; return;
	}
	$v = zt_track_public( $oid ); $o = zt_order_get( $oid );
	echo '<section class="sec zt-sec"><div class="wrap wrap--narrow" data-zt-track="' . esc_attr( $v['status'] ) . '">';
	echo '<p class="zt-est" data-zt-status>' . esc_html( $v['label'] ) . '</p><h2>' . esc_html( $v['service'] ?: 'طلبك' ) . '</h2>';
	echo '<p>' . esc_html( trim( $v['city'] . ( $v['hood'] ? ' — حي ' . preg_replace( '/^\s*حي\s+/u', '', $v['hood'] ) : '' ), ' —' ) ) . ( '' !== $v['appointment'] ? ' · الموعد: ' . esc_html( $v['appointment'] ) : '' ) . '</p>';
	if ( in_array( $v['status'], array( 'cancelled', 'postponed' ), true ) ) { echo '<p class="zt-note">' . esc_html( $v['label'] ) . ' — تواصل معنا لأي استفسار.</p>'; }
	echo '<ol class="zt-steps" aria-label="مراحل الطلب">';
	foreach ( $v['steps'] as $s ) { echo '<li class="' . ( $s['done'] ? 'is-done' : '' ) . ( $s['key'] === $v['status'] ? ' is-cur' : '' ) . '"><span>' . esc_html( $s['label'] ) . '</span>' . ( $s['at'] ? '<small>' . esc_html( $s['at'] ) . '</small>' : '' ) . '</li>'; }
	echo '</ol>';
	if ( $v['technician'] ) {
		$t = $v['technician'];
		echo '<div class="zt-card zt-tech">' . ( $t['photo'] ? '<img src="' . esc_url( $t['photo'] ) . '" alt="' . esc_attr( $t['name'] ) . '" width="72" height="72" loading="lazy">' : '' ) . '<div><strong>' . esc_html( $t['name'] ) . '</strong>' . ( $t['years'] ? '<br>خبرة ' . esc_html( zt_fmt( $t['years'] ) ) . ' سنة' : '' ) . ( $t['services'] ? '<br><small>' . esc_html( implode( '، ', array_slice( $t['services'], 0, 4 ) ) ) . '</small>' : '' ) . '</div></div>';
	}
	echo '<div class="zt-actions">';
	$ph = function_exists( 'zad_phone' ) ? zad_phone( 0 ) : '';
	if ( $ph && function_exists( 'zad_tel_href' ) ) { echo '<a class="btn" href="' . esc_url( zad_tel_href( $ph ) ) . '" data-zt-event="tool_call_click">اتصل بنا</a>'; }
	$wa = zt_wa_link( 'السلام عليكم، بخصوص طلبي: ' . $v['service'] . ( '' !== $v['appointment'] ? ' — موعد ' . $v['appointment'] : '' ) );
	if ( $wa ) { echo '<a class="btn btn--wa" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '" data-zt-event="tool_whatsapp_click">واتساب</a>'; }
	echo '</div>';
	if ( 'done' === $v['status'] && $v['warranty'] ) { zt_render_after_service( $o, $v ); }
	echo '</div></section>';
}

function zt_render_after_service( $o, $v ) {
	$w = $v['warranty'];
	echo '<div class="zt-card"><h3>ضمان الخدمة</h3><p>' . esc_html( zt_months_label( $w['months'] ) ) . ' — من ' . esc_html( $w['from'] ) . ' إلى ' . esc_html( $w['to'] ) . ' · ' . ( $w['valid'] ? '<strong>ساري</strong>' : '<strong>منتهي</strong>' ) . '</p><p>كود الضمان: <code dir="ltr">' . esc_html( $w['code'] ) . '</code></p>';
	if ( '' !== trim( (string) zt_opt( 'orders.warranty_terms' ) ) ) { echo '<p><a class="btn" target="_blank" rel="noopener" href="' . esc_url( add_query_arg( 'view', 'certificate' ) ) . '" data-zt-event="tool_certificate_open">شهادة الضمان (طباعة / PDF)</a></p>'; }
	echo '</div>';
	$rev = zt_review_url(); // for EVERY customer: no satisfaction filter, no incentive (Google's policy)
	if ( '' !== $rev ) { echo '<div class="zt-card"><h3>رأيك يهمنا</h3><p>شاركنا تجربتك على خرائط Google.</p><p><a class="btn btn--accent" target="_blank" rel="noopener" href="' . esc_url( $rev ) . '" data-zt-event="tool_review_click">قيّمنا على Google</a></p></div>'; }
	if ( zt_opt( 'orders.feedback_enabled' ) ) {
		echo '<form class="zt-card zt-form" data-zt-feedback><h3>ملاحظاتك لنا (داخلية)</h3><label class="fld"><span>اكتب ملاحظتك</span><textarea name="text" rows="3" maxlength="600"></textarea></label><div class="zt-hp"><label>اترك هذا فارغاً<input name="website" tabindex="-1" autocomplete="off"></label></div><button class="btn" type="submit">إرسال</button><p class="zt-msg" role="status" aria-live="polite"></p></form>';
	}
	if ( ! $o['reminder_optin'] ) {
		echo '<form class="zt-card zt-form" data-zt-optin><h3>تذكير قبل انتهاء الضمان</h3><label class="zt-consent"><input type="checkbox" name="consent" value="1"><span>أوافق على أن تُرسل لي زاد رسالة تذكير واحدة قبل انتهاء الضمان على رقمي المسجّل في الطلب. ' . zt_privacy_line() . '</span></label><div class="zt-hp"><label>اترك هذا فارغاً<input name="website" tabindex="-1" autocomplete="off"></label></div><button class="btn" type="submit">فعّل التذكير</button><p class="zt-msg" role="status" aria-live="polite"></p></form>';
	} else { echo '<p class="zt-note">تم تفعيل تذكير الضمان لهذا الطلب.</p>'; }
}

/** The printable certificate: a standalone page (no theme chrome). */
function zt_render_certificate( $oid ) {
	zt_private_headers();
	$o = zt_order_get( $oid ); $terms = array_values( array_filter( array_map( 'trim', explode( "\n", (string) zt_opt( 'orders.warranty_terms' ) ) ) ) );
	$verify = zt_verify_url( $o['warranty_code'] );
	$opt = function ( $k ) { return function_exists( 'zad_opt' ) ? (string) zad_opt( $k ) : ''; };
	status_header( 200 );
	echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>شهادة ضمان ' . esc_html( $o['warranty_code'] ) . '</title><link rel="stylesheet" href="' . esc_url( ZT_URL . 'assets/css/zt-cert.css?ver=' . ZT_VERSION ) . '"></head><body><main class="cert">';
	echo '<header><h1>شهادة ضمان</h1><p class="brand">' . esc_html( $opt( 'zad_legal_name' ) ?: zt_brand() ) . '</p>';
	echo '<p class="ids">' . ( $opt( 'zad_cr' ) ? 'السجل التجاري: <b dir="ltr">' . esc_html( $opt( 'zad_cr' ) ) . '</b>' : '' ) . ( $opt( 'zad_vat' ) ? ' · الرقم الضريبي: <b dir="ltr">' . esc_html( $opt( 'zad_vat' ) ) . '</b>' : '' ) . '</p></header>';
	echo '<table><tr><th>الخدمة</th><td>' . esc_html( $o['service'] ) . '</td></tr><tr><th>تاريخ الخدمة</th><td dir="ltr">' . esc_html( $o['service_date'] ) . '</td></tr><tr><th>مدة الضمان</th><td>' . esc_html( zt_months_label( $o['warranty_months'] ) ) . '</td></tr><tr><th>ينتهي في</th><td dir="ltr">' . esc_html( $o['warranty_end'] ) . '</td></tr><tr><th>كود الضمان</th><td dir="ltr"><b>' . esc_html( $o['warranty_code'] ) . '</b></td></tr></table>';
	if ( $terms ) { echo '<h2>شروط الضمان</h2><ul>'; foreach ( $terms as $t ) { echo '<li>' . esc_html( $t ) . '</li>'; } echo '</ul>'; }
	echo '<div class="verify"><div id="zt-qr" data-url="' . esc_attr( $verify ) . '" aria-label="رمز QR للتحقق"></div><p>للتحقق من الضمان: <span dir="ltr">' . esc_html( $verify ) . '</span></p></div>';
	echo '<p class="noprint"><button onclick="window.print()">طباعة / حفظ PDF</button></p></main>';
	echo '<script src="' . esc_url( ZT_URL . 'assets/js/vendor/qrcode.js?ver=1.4.4' ) . '"></script><script src="' . esc_url( ZT_URL . 'assets/js/zt-cert.js?ver=' . ZT_VERSION ) . '"></script></body></html>';
}

function zt_verify_url( $code ) {
	foreach ( zt_private_page_ids() as $id ) { if ( ZT_TEMPLATE_VERIFY === get_page_template_slug( $id ) && 'publish' === get_post_status( $id ) ) { return add_query_arg( 'c', $code, get_permalink( $id ) ); } }
	return add_query_arg( 'c', $code, home_url( '/' ) );
}

/** The verification page body. */
function zt_render_verify() {
	zt_private_headers();
	if ( ! zt_orders_ready() ) { echo '<section class="sec"><div class="wrap wrap--narrow"><p class="zt-note"><strong>خدمة التحقق من الضمان قيد التفعيل.</strong></p></div></section>'; return; }
	$code = isset( $_GET['c'] ) ? zt_code_normalize( wp_unslash( $_GET['c'] ) ) : '';
	echo '<section class="sec zt-sec"><div class="wrap wrap--narrow"><form method="get" class="zt-form"><label class="fld"><span>كود الضمان</span><input name="c" dir="ltr" value="' . esc_attr( $code ) . '" placeholder="ZAD-2610-XXXXXX" autocomplete="off" data-zt-num></label><button class="btn btn--accent" type="submit">تحقق</button></form>';
	echo '<div class="zt-result" role="status" aria-live="polite">';
	if ( '' !== $code ) {
		if ( ! zt_rate_limit( 'verify', (int) zt_opt( 'orders.verify_per_hour' ) ) ) { echo '<p class="zt-note">محاولات كثيرة، حاول لاحقاً.</p>'; }
		else {
			$r = zt_verify_lookup( $code );
			if ( 'invalid' === $r['state'] ) { echo '<p class="zt-note"><strong>لم نجد ضماناً بهذا الكود.</strong> تأكد من كتابته كما هو في الشهادة.</p>'; }
			else { echo '<div class="zt-card"><p class="zt-est">' . ( 'valid' === $r['state'] ? 'ضمان ساري' : 'ضمان منتهي' ) . '</p><table class="zt-table"><tr><th>الخدمة</th><td>' . esc_html( $r['service'] ) . '</td></tr><tr><th>تاريخ الخدمة</th><td dir="ltr">' . esc_html( $r['from'] ) . '</td></tr><tr><th>ينتهي في</th><td dir="ltr">' . esc_html( $r['to'] ) . '</td></tr><tr><th>العميل</th><td>' . esc_html( $r['name'] ) . '</td></tr></table></div>'; }
		}
	}
	echo '</div></div></section>';
}

/* ---------------------------------------------- REST ---------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'zad/v1', '/track/(?P<token>[a-f0-9]{32})', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $req ) {
		if ( ! zt_orders_ready() ) { return new WP_Error( 'zt_off', 'غير متاح.', array( 'status' => 404 ) ); }
		if ( ! zt_rate_limit( 'track', (int) zt_opt( 'orders.track_per_hour' ) ) ) { return new WP_Error( 'zt_rate', 'محاولات كثيرة.', array( 'status' => 429 ) ); }
		$oid = zt_order_by_token( (string) $req['token'] );
		if ( ! $oid ) { return new WP_Error( 'zt_nf', 'غير موجود.', array( 'status' => 404 ) ); }
		$r = new WP_REST_Response( zt_track_public( $oid ) ); $r->header( 'Cache-Control', 'no-store, max-age=0' ); $r->header( 'X-Robots-Tag', 'noindex' ); return $r;
	} ) );
	register_rest_route( 'zad/v1', '/feedback', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $req ) {
		$g = zt_rest_guard( $req, 'feedback', (int) zt_opt( 'general.rate_per_ip_hour' ) ); if ( true !== $g ) { return $g; }
		$oid = zt_order_by_token( (string) $req->get_param( 'token' ) ); $text = mb_substr( sanitize_textarea_field( (string) $req->get_param( 'text' ) ), 0, 600 );
		if ( ! $oid || mb_strlen( $text ) < 2 || 'done' !== zt_order_get( $oid )['status'] ) { return new WP_Error( 'zt_bad', 'تعذر الإرسال.', array( 'status' => 400 ) ); }
		$fb = (array) get_post_meta( $oid, '_zt_feedback', true ); $fb = array_filter( $fb );
		if ( count( $fb ) >= max( 1, (int) zt_opt( 'orders.feedback_max' ) ) ) { return new WP_Error( 'zt_cap', 'وصلت للحد الأقصى من الملاحظات.', array( 'status' => 400 ) ); }
		$fb[] = array( 'text' => $text, 'at' => time() ); update_post_meta( $oid, '_zt_feedback', array_values( $fb ) );
		return new WP_REST_Response( array( 'ok' => true ) );
	} ) );
	register_rest_route( 'zad/v1', '/track/optin', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $req ) {
		$g = zt_rest_guard( $req, 'optin', (int) zt_opt( 'general.rate_per_ip_hour' ) ); if ( true !== $g ) { return $g; }
		$oid = zt_order_by_token( (string) $req->get_param( 'token' ) );
		if ( ! $oid || ! $req->get_param( 'consent' ) || 'done' !== zt_order_get( $oid )['status'] ) { return new WP_Error( 'zt_bad', 'تعذر التفعيل.', array( 'status' => 400 ) ); }
		update_post_meta( $oid, '_zt_reminder_optin', 1 ); update_post_meta( $oid, '_zt_optin_at', time() );
		zt_order_schedule_warranty_reminder( $oid );
		return new WP_REST_Response( array( 'ok' => true ) );
	} ) );
} );
