<?php defined( 'ABSPATH' ) || exit;
/** Shared lead system: CPT hs_lead + public REST endpoint (no nonce: pages are cached). */

add_action( 'init', function () {
	register_post_type( 'hs_lead', array(
		'labels'       => array( 'name' => 'طلبات الأدوات', 'singular_name' => 'طلب أداة', 'menu_name' => 'طلبات الأدوات', 'all_items' => 'كل الطلبات', 'edit_item' => 'تفاصيل الطلب', 'search_items' => 'بحث في الطلبات', 'not_found' => 'لا توجد طلبات' ),
		'public'       => false,
		'show_ui'      => true,
		'menu_icon'    => 'dashicons-clipboard',
		'menu_position'=> 60,
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
		'capabilities' => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap' => true,
	) );
} );

function hs_tool_labels() {
	return array( 'pest-method' => 'طريقة المكافحة', 'moving-bundle' => 'باقة الانتقال', 'stain-aid' => 'إسعافات البقع' );
}

/* ---------------- admin list ---------------- */
add_filter( 'manage_hs_lead_posts_columns', function () {
	return array( 'cb' => '<input type="checkbox">', 'title' => 'الاسم', 'hs_phone' => 'الجوال', 'hs_tool' => 'الأداة', 'hs_status' => 'الحالة', 'date' => 'التاريخ' );
} );
add_action( 'manage_hs_lead_posts_custom_column', function ( $col, $id ) {
	if ( 'hs_phone' === $col ) {
		$p  = (string) get_post_meta( $id, '_hs_phone', true );
		$wa = preg_replace( '/^0/', '966', $p );
		echo '<span dir="ltr">' . esc_html( $p ) . '</span> &nbsp; <a href="' . esc_url( 'tel:' . $p ) . '">اتصال</a> | <a href="' . esc_url( 'https://wa.me/' . $wa ) . '" target="_blank" rel="noopener">واتساب</a>';
	} elseif ( 'hs_tool' === $col ) {
		$l = hs_tool_labels(); $t = (string) get_post_meta( $id, '_hs_tool', true );
		echo esc_html( $l[ $t ] ?? $t );
	} elseif ( 'hs_status' === $col ) {
		echo 'contacted' === get_post_meta( $id, '_hs_status', true ) ? 'تم التواصل' : '<b>جديد</b>';
	}
}, 10, 2 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hs_lead_box', 'تفاصيل الطلب', function ( $post ) {
		wp_nonce_field( 'hs_lead_save', 'hs_lead_n' );
		$g = function ( $k ) use ( $post ) { return (string) get_post_meta( $post->ID, $k, true ); };
		$l = hs_tool_labels();
		echo '<p><b>الأداة:</b> ' . esc_html( $l[ $g( '_hs_tool' ) ] ?? $g( '_hs_tool' ) ) . '</p>';
		echo '<p><b>الجوال:</b> <span dir="ltr">' . esc_html( $g( '_hs_phone' ) ) . '</span></p>';
		echo '<p><b>المدينة/الحي:</b> ' . esc_html( $g( '_hs_area' ) ) . '</p>';
		echo '<p><b>السعر التقديري:</b> ' . esc_html( $g( '_hs_price' ) ?: '—' ) . '</p>';
		echo '<p><b>الملخص:</b></p><pre style="white-space:pre-wrap;background:#f6f7f7;padding:10px">' . esc_html( $g( '_hs_summary' ) ) . '</pre>';
		echo '<p><b>الحالة:</b> <select name="hs_status"><option value="new"' . selected( $g( '_hs_status' ) ?: 'new', 'new', false ) . '>جديد</option><option value="contacted"' . selected( $g( '_hs_status' ), 'contacted', false ) . '>تم التواصل</option></select></p>';
	}, 'hs_lead', 'normal' );
} );
add_action( 'save_post_hs_lead', function ( $id ) {
	if ( ! isset( $_POST['hs_lead_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hs_lead_n'] ) ), 'hs_lead_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) { return; }
	update_post_meta( $id, '_hs_status', ( $_POST['hs_status'] ?? '' ) === 'contacted' ? 'contacted' : 'new' );
} );

/* ---------------- price recalculation (server is the source of truth for bundles) ---------------- */
/** Mirror of calc() in moving-bundle.js. $sel: old/new{city,size,floor,lift}, svcs[], pack, assemble. */
function hs_bundle_calc( $sel ) {
	$S     = hs_settings();
	$svcs  = hs_bundle_services();
	$sizes = hs_sizes();
	$chosen = array_values( array_intersect( array_keys( $svcs ), array_map( 'strval', (array) ( $sel['svcs'] ?? array() ) ) ) );
	$old = (array) ( $sel['old'] ?? array() ); $new = (array) ( $sel['new'] ?? array() );
	$osz = isset( $sizes[ $old['size'] ?? '' ] ) ? $old['size'] : ''; $nsz = isset( $sizes[ $new['size'] ?? '' ] ) ? $new['size'] : '';
	$norm = function ( $c ) { return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $c ) ) ); };
	$inter = $norm( $old['city'] ?? '' ) !== '' && $norm( $new['city'] ?? '' ) !== '' && $norm( $old['city'] ) !== $norm( $new['city'] );
	$P = function ( $s, $z ) use ( $S ) { return $z ? hs_num( $S['bundle'][ $s ][ $z ] ?? '' ) : null; };
	$lines = array(); $sub = 0; $unknown = false;
	foreach ( $chosen as $k ) {
		$price = null; $notes = array();
		if ( 'clean_old' === $k ) { $price = $P( 'clean_old', $osz ); }
		if ( 'clean_new' === $k ) { $price = $P( 'clean_new', $nsz ); }
		if ( 'spray' === $k )     { $price = $P( 'spray', $nsz ); }
		if ( 'move' === $k ) {
			if ( $inter ) { $notes[] = 'النقل بين المدن يُسعّر بعد التواصل'; }
			else {
				$price = $P( 'move', $osz );
				if ( null !== $price ) {
					$fl = ( empty( $old['lift'] ) ? max( 0, min( 10, (int) ( $old['floor'] ?? 0 ) ) ) : 0 ) + ( empty( $new['lift'] ) ? max( 0, min( 10, (int) ( $new['floor'] ?? 0 ) ) ) : 0 );
					if ( $fl > 0 ) { $ff = hs_num( $S['floor_fee'] ); if ( $ff ) { $price += $fl * $ff; } else { $notes[] = 'رسوم الأدوار تحدد بعد المعاينة'; } }
					if ( ! empty( $sel['pack'] ) ) { $f = hs_num( $S['pack_fee'] ); if ( $f ) { $price += $f; } else { $notes[] = 'رسم التغليف يحدد بعد المعاينة'; } }
					if ( ! empty( $sel['assemble'] ) ) { $f = hs_num( $S['assemble_fee'] ); if ( $f ) { $price += $f; } else { $notes[] = 'رسم الفك والتركيب يحدد بعد المعاينة'; } }
				}
			}
		}
		if ( null === $price ) { $unknown = true; } else { $sub += $price; }
		if ( $notes && null !== $price ) { $unknown = true; }
		$lines[ $k ] = array( 'label' => $svcs[ $k ], 'price' => $price, 'notes' => $notes );
	}
	$n    = count( $chosen );
	$rate = $n >= 2 ? (int) ( $S[ 'disc' . min( 4, $n ) ] ?? 0 ) : 0;
	$disc = (int) round( $sub * $rate / 100 );
	return array( 'lines' => $lines, 'n' => $n, 'subtotal' => $sub, 'rate' => $rate, 'discount' => $disc, 'total' => $sub - $disc, 'unknown' => $unknown );
}
function hs_bundle_price_text( $c ) {
	if ( ! $c['n'] ) { return ''; }
	$known = false; foreach ( $c['lines'] as $l ) { if ( null !== $l['price'] ) { $known = true; } }
	if ( ! $known ) { return 'يحدد بعد المعاينة'; }
	return $c['total'] . ' ر.س (تقديري' . ( $c['discount'] ? '، بعد خصم ' . $c['discount'] . ' ر.س' : '' ) . ( $c['unknown'] ? '، لا يشمل البنود التي تحدد بعد المعاينة' : '' ) . ')';
}

/* ---------------- REST ---------------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'hs-tools/v1', '/lead', array( 'methods' => 'POST', 'callback' => 'hs_rest_lead', 'permission_callback' => '__return_true' ) );
} );

function hs_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) { return sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ); }
	}
	return '';
}
function hs_origin_ok() {
	$h = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : ( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
	if ( '' === $h ) { return false; }
	$a = wp_parse_url( $h, PHP_URL_HOST ); $b = wp_parse_url( home_url(), PHP_URL_HOST );
	return $a && $b && strtolower( $a ) === strtolower( $b );
}
function hs_clean_phone( $raw ) {
	$d = strtr( (string) $raw, array( '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
	$d = preg_replace( '/[\s\-]+/', '', $d );
	if ( ! preg_match( '/^(05\d{8}|9665\d{8}|\+9665\d{8})$/', $d ) ) { return ''; }
	$d = ltrim( $d, '+' );
	return 0 === strpos( $d, '966' ) ? '0' . substr( $d, 3 ) : $d;
}

function hs_rest_lead( WP_REST_Request $r ) {
	$p = $r->get_json_params();
	if ( ! is_array( $p ) ) { $p = $r->get_params(); }
	$fake = rest_ensure_response( array( 'ok' => true ) );
	// Silent layers: bots always get a fake "success".
	if ( ! empty( $p['hs_website'] ) ) { return $fake; }
	if ( (float) ( $p['elapsed'] ?? 0 ) < 3 ) { return $fake; }
	if ( ! hs_origin_ok() ) { return $fake; }

	$tool = sanitize_key( $p['tool'] ?? '' );
	if ( ! isset( hs_tool_labels()[ $tool ] ) ) { return new WP_Error( 'hs_tool', 'طلب غير صالح.', array( 'status' => 400 ) ); }
	$name  = mb_substr( sanitize_text_field( $p['name'] ?? '' ), 0, 100 );
	$phone = hs_clean_phone( $p['phone'] ?? '' );
	if ( mb_strlen( $name ) < 2 ) { return new WP_Error( 'hs_name', 'يرجى كتابة الاسم.', array( 'status' => 400 ) ); }
	if ( ! $phone ) { return new WP_Error( 'hs_phone', 'رقم الجوال غير صحيح. اكتبه بصيغة 05XXXXXXXX.', array( 'status' => 400 ) ); }

	$ip  = hs_client_ip();
	$key = 'hs_rl_' . md5( $ip );
	$all = 'hs_rl_all_' . gmdate( 'YmdH' );
	if ( (int) get_transient( $key ) >= 5 || (int) get_transient( $all ) >= 200 ) { return $fake; }

	$area    = mb_substr( sanitize_text_field( $p['area'] ?? '' ), 0, 120 );
	$summary = mb_substr( sanitize_textarea_field( $p['summary'] ?? '' ), 0, 1500 );
	$notes   = mb_substr( sanitize_textarea_field( $p['notes'] ?? '' ), 0, 400 );
	$price   = '';
	$data    = isset( $p['data'] ) && is_array( $p['data'] ) ? $p['data'] : array();
	if ( 'moving-bundle' === $tool ) {
		$calc  = hs_bundle_calc( $data );
		$price = hs_bundle_price_text( $calc );
		$data  = array( 'sel' => map_deep( $data, 'sanitize_text_field' ), 'calc' => $calc );
	} elseif ( 'pest-method' === $tool ) {
		$m = sanitize_key( $data['method'] ?? '' );
		$S = hs_settings();
		if ( isset( hs_pest_method_names()[ $m ] ) ) { $price = hs_fmt_range( $S['pest'][ $m ]['from'] ?? '', $S['pest'][ $m ]['to'] ?? '' ); }
		$data = map_deep( $data, 'sanitize_text_field' );
	} else {
		$data = map_deep( $data, 'sanitize_text_field' );
	}
	if ( $notes ) { $summary .= "\nملاحظات: " . $notes; }

	$id = wp_insert_post( array( 'post_type' => 'hs_lead', 'post_status' => 'publish', 'post_title' => $name . ' — ' . hs_tool_labels()[ $tool ] ), true );
	if ( is_wp_error( $id ) || ! $id ) { return new WP_Error( 'hs_save', 'تعذّر حفظ الطلب، حاول مرة أخرى أو تواصل معنا مباشرة.', array( 'status' => 500 ) ); }
	foreach ( array( '_hs_tool' => $tool, '_hs_phone' => $phone, '_hs_area' => $area, '_hs_summary' => $summary, '_hs_price' => $price, '_hs_status' => 'new' ) as $k => $v ) { update_post_meta( $id, $k, $v ); }
	update_post_meta( $id, '_hs_data', wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	set_transient( $all, (int) get_transient( $all ) + 1, HOUR_IN_SECONDS );

	$S  = hs_settings();
	$to = $S['email'] ? $S['email'] : get_option( 'admin_email' );
	$body = "أداة: " . hs_tool_labels()[ $tool ] . "\nالاسم: $name\nالجوال: $phone\nالمدينة/الحي: $area\nالسعر التقديري: " . ( $price ?: '—' ) . "\n\n$summary\n\n" . admin_url( 'post.php?post=' . $id . '&action=edit' );
	wp_mail( $to, 'طلب جديد من أداة: ' . hs_tool_labels()[ $tool ], $body );

	return rest_ensure_response( array( 'ok' => true, 'id' => $id, 'price' => $price ) );
}
