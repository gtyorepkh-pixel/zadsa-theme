<?php
/**
 * Hero card "تذكرة الحجز" (ticket): tiles (symptom / service) + price + day + WhatsApp.
 * Tiles come from the page field hero_tiles, else the "شخّص مشكلتك" symptoms (dx), else the price rows.
 * No prices are invented: every price shown is text the owner wrote.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** Thin-stroke icon set for the tiles (24px grid). */
function zad_ticket_icons() {
	return array(
		'roach'    => '<ellipse cx="12" cy="13" rx="5" ry="7"/><path d="M12 6V4M9 3l1.5 2M15 3l-1.5 2M7 10l-3-1M7 14H4M7.5 18 5 20M17 10l3-1M17 14h3M16.5 18l2.5 2M12 9v10"/>',
		'bedbug'   => '<path d="M3 18V7M3 14h18v4M21 14v-1.5a3 3 0 0 0-3-3h-7V14"/><circle cx="7" cy="11" r="1.8"/>',
		'ant'      => '<circle cx="12" cy="6" r="2.2"/><ellipse cx="12" cy="12" rx="2.4" ry="3"/><ellipse cx="12" cy="18.5" rx="3.2" ry="3"/><path d="M9.6 11 6 9M14.4 11 18 9M9.8 13l-3.6 2M14.2 13l3.6 2M10 4.5 8.5 2.5M14 4.5l1.5-2"/>',
		'termite'  => '<circle cx="12" cy="9" r="3"/><circle cx="12" cy="16" r="4"/><path d="M9 6 7 4M15 6l2-2M8 15H4M16 15h4"/>',
		'rodent'   => '<path d="M4 16c0-4.5 3.5-8.5 8.5-8.5 3.6 0 6.5 2.6 6.5 6 0 2.6-2 4.5-4.6 4.5H7a3 3 0 0 1-3-2z"/><circle cx="9" cy="7.5" r="2.2"/><path d="M18.5 17c1.6.6 2.1 2.2 1 3.6"/>',
		'mosquito' => '<ellipse cx="12" cy="13" rx="1.8" ry="4.2"/><path d="M12 8.8 10.5 4.5M12 8.8l1.5-4.3M10.4 10.5C7 8.4 4 8.8 3.5 10.6s4.4 1.6 6.9.6M13.6 10.5c3.4-2.1 6.4-1.7 6.9.1s-4.4 1.6-6.9.6M10.6 16l-3 4M13.4 16l3 4"/>',
		'sofa'     => '<path d="M5 11V8a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v3"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v5H3v-5zM6 18v2M18 18v2"/>',
		'tank'     => '<ellipse cx="12" cy="6" rx="7" ry="2.5"/><path d="M5 6v12c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5V6M5 12c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5"/>',
		'ac'       => '<rect x="3" y="5" width="18" height="8" rx="2"/><path d="M7 9h10M7 16c0 1.5-1 2-1 3.5M12 16c0 1.5-1 2-1 3.5M17 16c0 1.5-1 2-1 3.5"/>',
		'house'    => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
		'truck'    => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'box'      => '<path d="M3 8l9-5 9 5v8l-9 5-9-5V8zM3 8l9 5 9-5M12 13v8"/>',
		'acsplit'  => '<rect x="3" y="4" width="18" height="7" rx="2"/><path d="M6 8h12M7 14c0 1.6-1 2-1 3.5M12 14c0 1.6-1 2-1 3.5M17 14c0 1.6-1 2-1 3.5"/>',
		'acwindow' => '<rect x="4" y="5" width="16" height="12" rx="2"/><path d="M8 9h8M8 12h8M10 20h4"/>',
		'accentral'=> '<rect x="3" y="9" width="18" height="8" rx="1.5"/><path d="M6 13h4M14 13h4M7 5v4M12 5v4M17 5v4M7 20v-3M17 20v-3"/>',
		'other'    => '<circle cx="12" cy="12" r="8.5"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
	);
}

/** Guess an icon key from a tile name (Arabic keywords); 'other' when nothing matches. */
function zad_ticket_guess_icon( $name ) {
	$map = array( // first match wins: the same words always give the same icon
		'accentral' => '/كاسيت|دكت|مركزي|مخفي|باكيج/u',
		'acsplit'   => '/سبليت|سبلت|split/iu',
		'acwindow'  => '/شباك|window/iu',
		'snow'      => '/فريون|غاز\s*(?:ال)?مكيف|شحن\s*غاز/u',
		'ac'        => '/مكيف|تكييف|مكيفات/u',
		'termite'   => '/نمل\s*أبيض|نمل\s*ابيض|أرضة|ارضة|ارضه|أرضه/u',
		'ant'       => '/نمل/u',
		'roach'     => '/صراصير|صرصور|سرسور|حشرات\s*منزلية/u',
		'bedbug'    => '/بق\b|بق\s*الفراش|فراش/u',
		'rodent'    => '/فئران|فأر|فار\b|قوارض|جرذ|جرذان/u',
		'mosquito'  => '/بعوض|ذباب|ناموس|حشرات\s*طائرة/u',
		'sofa'      => '/كنب|أريكة|اريكة|مجلس|مجالس|مقاعد|كنبات|سجاد|موكيت|مفروشات|ستائر/u',
		'tank'      => '/خزان|خزانات/u',
		'truck'     => '/نقل|عفش|شاحنة|ونش|تخزين/u',
		'box'       => '/تغليف|تعبئة|صندوق|فك\s*وتركيب/u',
		'drop'      => '/مجاري|تسليك|بيارة|بيارات|مياه|تسرب/u',
		'paint'     => '/دهان|طلاء|بلاط|رخام|جلي/u',
		'shield'    => '/عزل|مكافحة|حشرات|رش|تعقيم/u',
		'house'     => '/بيت|منزل|منازل|فيلا|فلل|شقة|شقق|شقه/u',
		'tool'      => '/صيانة|اصلاح|إصلاح|تركيب|نجار/u',
	);
	foreach ( $map as $key => $re ) {
		if ( preg_match( $re, (string) $name ) ) { return $key; }
	}
	return 'sparkle'; // never the meaningless «…» icon
}

/** SVG for a tile icon key: the ticket set, then the general theme set, else "other". */
function zad_ticket_icon( $key, $size = 28 ) {
	$set = zad_ticket_icons();
	if ( isset( $set[ $key ] ) ) { $inner = $set[ $key ]; }
	elseif ( in_array( $key, zad_icon_keys(), true ) ) { $inner = zad_icons()[ $key ]; }
	else { $inner = $set['other']; }
	return '<svg class="zi" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

/** Price text to show: a bare number gets the unit; a leading "من" is dropped (the card already says "يبدأ من"). */
function zad_ticket_price_text( $raw, $unit ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) { return ''; }
	$raw = preg_replace( '/^من\s+/u', '', $raw );
	if ( preg_match( '/^[\d٠-٩.,،]+$/u', $raw ) ) { $raw .= ' ' . $unit; }
	return $raw;
}

/** Tiles for a service page: [ [name, svc, price, icon, url], … ] — one per package; pages without packages: hero_tiles, else dx, else the price rows. */
function zad_ticket_tiles( $id ) {
	static $cache = array();
	if ( isset( $cache[ $id ] ) ) { return $cache[ $id ]; }
	$unit = (string) get_post_meta( $id, '_zad_price_unit', true ) ?: 'ريال';
	$raw  = array();
	foreach ( zad_parse_packages( (string) get_post_meta( $id, '_zad_packages', true ) ) as $pk ) { // packages are the source: one tile per package
		$t = zad_price_hero_text( $pk ); // «199-300» → 199 · «من 600» → 600 · «بعد المعاينة» → ''
		$raw[] = array( $pk['name'], '', ( '' !== $t && '' !== $pk['unit'] ) ? $t . ' ' . $pk['unit'] : $t, '', '', (float) $pk['min'] );
	}
	foreach ( $raw ? array() : zad_q_lines( (string) get_post_meta( $id, '_zad_hero_tiles', true ) ) as $l ) {
		$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 4, '' );
		if ( '' === $c[0] ) { continue; }
		$url = ''; $svc = '';
		if ( preg_match( '#^https?://#', $c[3] ) ) { $url = $c[3]; }
		elseif ( '' !== $c[3] && '/' === $c[3][0] && 0 !== strpos( $c[3], '//' ) ) { $url = home_url( $c[3] ); }
		elseif ( '' !== $c[3] ) { $svc = $c[3]; }
		$raw[] = array( $c[0], $svc, $c[1], $c[2], $url );
	}
	if ( ! $raw ) {
		foreach ( zad_dx_data( $id, true ) as $d ) { $raw[] = array( $d['label'], $d['svc'], $d['price'], '', $d['url'] ); }
	}
	if ( ! $raw ) {
		foreach ( zad_price_rows( $id ) as $r ) { if ( '' !== $r['name'] ) { $raw[] = array( $r['name'], '', $r['price'], '', '' ); } }
	}
	$out = array();
	foreach ( $raw as $r ) {
		$icon  = sanitize_key( $r[3] );
		$icons = zad_ticket_icons();
		if ( ! isset( $icons[ $icon ] ) && ! in_array( $icon, zad_icon_keys(), true ) ) { $icon = zad_ticket_guess_icon( $r[0] ); }
		$out[] = array( 'name' => $r[0], 'svc' => '' !== $r[1] ? $r[1] : $r[0], 'price' => zad_ticket_price_text( $r[2], $unit ), 'icon' => $icon, 'url' => $r[4], 'num' => isset( $r[5] ) ? $r[5] : 0, 'pk' => isset( $r[5] ) );
	}
	if ( count( $out ) > 6 ) { $out = array_slice( $out, 0, 5 ); $out[] = array( 'more' => true ); } // 6th box becomes «المزيد» (opens the booking sheet)
	$cache[ $id ] = $out;
	return $out;
}

function zad_ticket_days() { return array( 'اليوم', 'بكرة', 'لاحقاً' ); }

function zad_ticket_message( $svc, $title, $day ) {
	return 'السلام عليكم، أبغى ' . $svc . ' — ' . $title . ' — الموعد: ' . $day;
}

/** The hero card for a service page. Always renders: with no packages / rows the page's own service name is the single tile. */
function zad_ticket_html( $id, $src = 'hero_ticket' ) {
	$tiles = zad_ticket_tiles( $id );
	$real  = array_values( array_filter( $tiles, function ( $t ) { return empty( $t['more'] ); } ) );
	if ( ! $real ) {
		$nm    = function_exists( 'zad_svc_label' ) ? zad_svc_label( $id ) : get_the_title( $id );
		$tiles = array( array( 'name' => $nm, 'svc' => $nm, 'price' => '', 'icon' => zad_ticket_guess_icon( $nm ), 'url' => '', 'num' => 0, 'pk' => true ) );
	}
	return zad_ticket_render( $tiles, array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'city' => zad_current_city( $id )['name'], 'wa' => zad_whatsapp( $id ), 'src' => $src ) ); // the page's own city (meta → parent city page → URL → site default)
}

/** The same card for the home hero: one tile per booking-sheet section (fixed icons), no prices (≈ «حسب المعاينة»). */
function zad_ticket_home_html( $src = 'hero_ticket_home' ) {
	$cats = function_exists( 'zad_wiz_manual' ) ? zad_wiz_manual() : array();
	if ( ! $cats && function_exists( 'zad_wiz_data' ) ) { $cats = zad_wiz_data()['cats']; }
	$tiles = array();
	foreach ( $cats as $c ) {
		$tiles[] = array( 'name' => $c['name'], 'svc' => $c['name'], 'price' => '', 'icon' => zad_ticket_guess_icon( $c['name'] ), 'url' => '', 'num' => 0, 'pk' => true );
	}
	if ( count( $tiles ) > 6 ) { $tiles = array_slice( $tiles, 0, 5 ); $tiles[] = array( 'more' => true ); }
	if ( ! $tiles ) { return ''; }
	return zad_ticket_render( $tiles, array( 'id' => 0, 'title' => get_bloginfo( 'name' ), 'city' => (string) zad_opt( 'zad_city_name', '' ), 'wa' => zad_whatsapp( 0 ), 'src' => $src ) );
}

/** The card markup. $x: id (post, 0 on the home), title, city, wa, src. */
function zad_ticket_render( $tiles, $x ) {
	$real  = array_values( array_filter( $tiles, function ( $t ) { return empty( $t['more'] ); } ) );
	if ( ! $real ) { return ''; }
	static $n = 0; $n++;
	$id    = (int) $x['id'];
	$src   = $x['src'];
	$wa    = $x['wa'];
	$title = $x['title'];
	$days  = zad_ticket_days();
	$city  = $x['city'];
	$first = $real[0];
	foreach ( $real as $t ) { if ( ! empty( $t['pk'] ) && $t['num'] > 0 && ( empty( $first['pk'] ) || $first['num'] <= 0 || $t['num'] < $first['num'] ) ) { $first = $t; } } // packages: the cheapest priced one is pre-selected, so the card opens on «يبدأ من <أقل سعر>»
	$q_txt = ! empty( $first['pk'] ) ? 'حسب المعاينة' : 'بعد المعاينة'; // packages all «بعد المعاينة» → «حسب المعاينة»; old pages keep their wording
	$q     = trim( (string) zad_opt( 'zad_ticket_title', '' ) ) ?: 'وش المشكلة عندك؟';
	$msg   = zad_ticket_message( $first['svc'], $title, $days[0] );
	$href  = $wa ? 'https://wa.me/' . $wa . '?text=' . rawurlencode( $msg ) : '#';
	$price = $first['price'];
	$o  = '<div class="tk" data-tk data-q="' . esc_attr( $q_txt ) . '" data-wa="' . esc_attr( $wa ) . '" data-title="' . esc_attr( $title ) . '" data-src="' . esc_attr( $src ) . '" data-post="' . (int) $id . '">';
	$o .= '<div class="tk__top"><div class="tk__meta"><span class="tk__badge">تسعير فوري · معاينة مجانية</span>' . ( $city ? '<span class="tk__city">' . esc_html( $city ) . '</span>' : '' ) . '</div>';
	$o .= '<p class="tk__q" id="tkq' . $n . '">' . esc_html( $q ) . '</p><div class="tk__grid" role="group" aria-labelledby="tkq' . $n . '">';
	foreach ( $tiles as $t ) {
		if ( ! empty( $t['more'] ) ) {
			$o .= '<button type="button" class="tk__tile tk__tile--more" data-tk-more><span class="tk__ic">' . zad_ticket_icon( 'other' ) . '</span><span class="tk__nm">المزيد</span></button>';
			continue;
		}
		$on = ( $t === $first );
		$o .= '<button type="button" class="tk__tile" aria-pressed="' . ( $on ? 'true' : 'false' ) . '" data-name="' . esc_attr( $t['name'] ) . '" data-svc="' . esc_attr( $t['svc'] ) . '" data-price="' . esc_attr( $t['price'] ) . '" data-url="' . esc_url( $t['url'] ) . '"><span class="tk__ic">' . zad_ticket_icon( $t['icon'] ) . '</span><span class="tk__nm">' . esc_html( $t['name'] ) . '</span></button>';
	}
	$o .= '</div></div><div class="tk__perf" aria-hidden="true"></div>';
	$o .= '<div class="tk__bot"><div class="tk__row"><div class="tk__price" aria-live="polite"><small data-tk-lab>' . ( '' === $price ? 'السعر' : 'يبدأ من' ) . '</small><b data-tk-price>' . esc_html( '' !== $price ? $price : $q_txt ) . '</b></div>';
	$o .= '<div class="tk__when"><span class="tk__wl" id="tkw' . $n . '">الموعد</span><div class="tk__seg" role="group" aria-labelledby="tkw' . $n . '">';
	foreach ( $days as $i => $d ) { $o .= '<button type="button" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '" data-day="' . esc_attr( $d ) . '">' . esc_html( $d ) . '</button>'; }
	$o .= '</div></div></div>';
	$o .= '<a class="btn btn--wa btn--block tk__go" data-tk-go href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . zad_icon( 'whatsapp', 20 ) . '<span data-tk-lbl>اطلب ' . esc_html( $first['svc'] ) . ' على واتساب</span></a>';
	$badges = array_slice( zad_lines( zad_opt( 'zad_card_badges', "فحص مجاني\nضمان مكتوب" ) ), 0, 3 );
	if ( $badges ) {
		$o .= '<ul class="tk__trust">';
		foreach ( $badges as $b ) { $o .= '<li>' . zad_icon( 'check', 15 ) . '<span>' . esc_html( $b ) . '</span></li>'; }
		$o .= '</ul>';
	}
	$o .= '<p class="tk__note">السعر النهائي يتأكد بعد المعاينة</p><a class="tk__more" data-tk-link href="' . esc_url( $first['url'] ) . '"' . ( $first['url'] ? '' : ' hidden' ) . '>تفاصيل هذا الخيار</a></div></div>';
	return $o;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! zad_is_service() ) { return; }
	wp_enqueue_script( 'zad-ticket', get_template_directory_uri() . '/assets/js/zad-ticket.js', array(), zad_asset_ver( 'assets/js/zad-ticket.js' ), true );
}, 110 );
