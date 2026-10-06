<?php
/**
 * Quick-request cards:
 *   (أ) "اطلب في 30 ثانية"  — service + district + time (+ optional price list) → WhatsApp.
 *   (ب) "شخّص مشكلتك"     — symptom → size → suggested service + the price text YOU entered → WhatsApp.
 * Used as: hero card (chosen per page), a section after the intro, the final request box, and a home section.
 * No prices are invented: everything shown comes from fields filled by the owner.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_quick_sources() { return array( 'hero-a' => 'الهيرو (أ)', 'hero-b' => 'الهيرو (ب)', 'sec-a' => 'قسم الطلب الأخير (أ)', 'sec-b' => 'قسم التشخيص (ب)', 'home-b' => 'الرئيسية (ب)', 'est' => 'مُقدّر السعر', 'hero_ticket' => 'الهيرو (تذكرة الحجز)' ); }

/* ---------------- which card in the hero ---------------- */
function zad_hero_card( $id, $has_prices ) {
	return 'ticket'; // one hero card for the whole site: «تذكرة الحجز» (the request form / estimator / 30-second / diagnosis cards are no longer shown)
}

/* ---------------- data ---------------- */
function zad_q_lines( $text ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) ); }

/** Symptoms for (ب): page field first, then the global option. Line: العرض | الخدمة المقترحة | السعر | رابط | إيموجي */
function zad_dx_data( $id = 0, $page_only = false ) {
	$src = $id ? (string) get_post_meta( $id, '_zad_dx', true ) : '';
	if ( '' === trim( $src ) && ! ( $id && $page_only ) ) { $src = (string) zad_opt( 'zad_dx_symptoms', '' ); } // $page_only: body section never falls back to the global symptoms
	$out = array();
	foreach ( zad_q_lines( $src ) as $l ) {
		$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 5, '' );
		if ( '' === $c[0] || '' === $c[1] ) { continue; }
		$u = $c[3]; if ( '' !== $u && '/' === $u[0] && 0 !== strpos( $u, '//' ) ) { $u = home_url( $u ); }
		$out[] = array( 'label' => $c[0], 'svc' => $c[1], 'price' => $c[2], 'url' => preg_match( '#^https?://#', $u ) ? $u : '', 'emoji' => $c[4] ?: '🔎' );
	}
	return array_slice( $out, 0, 8 );
}

function zad_q30_data( $id ) {
	$svcs = zad_q_lines( (string) get_post_meta( $id, '_zad_q_svcs', true ) );
	if ( ! $svcs ) { foreach ( (array) get_post_meta( $id, '_zad_subs', true ) as $s ) { if ( is_array( $s ) && ! empty( $s['t'] ) ) { $svcs[] = $s['t']; } } }
	if ( ! $svcs ) { $svcs = array( get_the_title( $id ) ); }
	$hoods = zad_q_lines( (string) get_post_meta( $id, '_zad_q_hoods', true ) );
	if ( ! $hoods ) {
		$cov = (array) get_post_meta( $id, '_zad_cov', true );
		$txt = ! empty( $cov['chips'] ) ? $cov['chips'] : zad_opt( 'zad_cov_chips', '' );
		foreach ( zad_q_lines( $txt ) as $l ) { $hoods[] = trim( explode( '|', $l )[0] ); }
	}
	$rows = array();
	foreach ( zad_price_rows( $id ) as $r ) { if ( '' !== $r['name'] ) { $rows[] = $r; } }
	return array( 'svcs' => array_slice( $svcs, 0, 4 ), 'hoods' => array_slice( $hoods, 0, 4 ), 'prices' => array_slice( $rows, 0, 8 ) );
}

/* ---------------- (أ) ---------------- */
function zad_q30_html( $id, $src = 'hero-a' ) {
	$d  = zad_q30_data( $id );
	$wa = zad_whatsapp( $id ); // may be empty: the final button then opens the booking sheet instead of WhatsApp
	$o  = '<div class="q30" data-q30 data-wa="' . esc_attr( $wa ) . '" data-title="' . esc_attr( get_the_title( $id ) ) . '" data-url="' . esc_url( get_permalink( $id ) ) . '" data-src="' . esc_attr( $src ) . '" data-post="' . (int) $id . '">';
	$o .= '<div class="q30__head"><span class="q30__bolt">' . zad_icon( 'bolt', 20 ) . '</span><div><strong>اطلب في 30 ثانية</strong><small>اختر وأرسل — وتصلنا رسالة منظمة على واتساب</small></div></div><div class="q30__body">';
	$o .= '<div><div class="q30__lab"><i>1</i> الخدمة</div><div class="q30__opts" data-g="svc">';
	foreach ( $d['svcs'] as $i => $s ) { $o .= '<button type="button" class="q30__opt' . ( 0 === $i ? ' on' : '' ) . '">' . esc_html( $s ) . '</button>'; }
	$o .= '</div></div><div><div class="q30__lab"><i>2</i> الحي</div><div class="q30__opts" data-g="hood">';
	foreach ( $d['hoods'] as $i => $h ) { $o .= '<button type="button" class="q30__opt' . ( 0 === $i ? ' on' : '' ) . '">' . esc_html( $h ) . '</button>'; }
	$o .= '<button type="button" class="q30__opt q30__opt--more" data-other>حي آخر +</button></div><input type="text" class="q30__in" data-hood-in hidden placeholder="اكتب اسم حيّك" maxlength="60"></div>';
	$o .= '<div><div class="q30__lab"><i>3</i> الوقت المفضل</div><div class="q30__seg" data-g="when"><button type="button" class="on">اليوم</button><button type="button">غداً</button><button type="button">أي وقت</button></div></div>';
	if ( $d['prices'] ) {
		$o .= '<div class="q30__pw"><button type="button" class="q30__prices" data-pbtn aria-expanded="false"><span class="q30__tag">' . zad_icon( 'list', 20 ) . '</span><span class="t"><span data-plabel>اختر الباقة والسعر</span><small data-psub>اختياري — اضغط لعرض خيارات الأسعار</small></span><span class="ch">⌄</span></button>';
		$o .= '<div class="q30__pop" data-pop hidden>'; $last = null;
		foreach ( $d['prices'] as $r ) {
			if ( $r['group'] !== $last ) { if ( '' !== $r['group'] ) { $o .= '<div class="q30__pg">' . esc_html( $r['group'] ) . '</div>'; } $last = $r['group']; }
			$o .= '<button type="button" class="q30__po" data-l="' . esc_attr( ( $r['group'] ? $r['group'] . ' — ' : '' ) . $r['name'] ) . '" data-s="' . esc_attr( $r['price'] ) . '">' . esc_html( $r['name'] ) . '<b>' . esc_html( $r['price'] ) . '</b></button>';
		}
		$o .= '</div></div>';
	}
	$o .= '<a class="btn btn--wa btn--block" data-go href="#" target="_blank" rel="noopener">' . ( $wa ? zad_icon( 'whatsapp', 20 ) . ' أرسل الطلب عبر واتساب' : zad_icon( 'bolt', 20 ) . ' أرسل الطلب' ) . '</a><p class="q30__note">نرد عليك خلال دقائق</p></div>';
	return $o . '</div>';
}

/* ---------------- (ب) ---------------- */
function zad_dx_html( $id, $src = 'hero-b', $page_only = false ) {
	$data = zad_dx_data( $id, $page_only );
	$wa   = zad_whatsapp( $id );
	if ( ! $data ) { return ''; }
	$o  = '<div class="dx" data-dx data-wa="' . esc_attr( $wa ) . '" data-title="' . esc_attr( $id ? get_the_title( $id ) : get_bloginfo( 'name' ) ) . '" data-url="' . esc_url( $id ? get_permalink( $id ) : home_url( '/' ) ) . '" data-src="' . esc_attr( $src ) . '" data-post="' . (int) $id . '">';
	$o .= '<div class="dx__head"><strong>شخّص مشكلتك</strong><small>3 خطوات سريعة — والنتيجة فورية</small><div class="dx__bar"><i class="on"></i><i></i><i></i></div></div><div class="dx__body">';
	$o .= '<div class="dx__step on" data-s="1"><p class="dx__q">ماذا تلاحظ؟</p><div class="dx__tiles">';
	foreach ( $data as $i => $s ) { $o .= '<button type="button" class="dx__tile" data-i="' . (int) $i . '"><span>' . esc_html( $s['emoji'] ) . '</span>' . esc_html( $s['label'] ) . '</button>'; }
	$o .= '</div></div><div class="dx__step" data-s="2"><p class="dx__q">ما حجم المكان؟</p><div class="dx__tiles">';
	foreach ( array( array( 'شقة', '🏢' ), array( 'دور في فيلا', '🏠' ), array( 'فيلا كاملة', '🏡' ), array( 'منشأة تجارية', '🏬' ) ) as $z ) { $o .= '<button type="button" class="dx__tile" data-size="' . esc_attr( $z[0] ) . '"><span>' . $z[1] . '</span>' . esc_html( $z[0] ) . '</button>'; }
	$o .= '</div><button type="button" class="dx__back" data-back>→ رجوع</button></div>';
	$o .= '<div class="dx__step" data-s="3"><div class="dx__res"><small>الخدمة المقترحة لك</small><h3 data-rsv></h3><div class="dx__price" data-rpr hidden></div><div class="dx__sum" data-rsm></div><a class="btn btn--wa btn--block" data-go href="#" target="_blank" rel="noopener">' . ( $wa ? zad_icon( 'whatsapp', 20 ) . ' أرسل نتيجتي عبر واتساب' : zad_icon( 'bolt', 20 ) . ' أرسل نتيجتي' ) . '</a><a class="dx__more" data-more href="#" hidden>تفاصيل الخدمة ←</a><p class="dx__why">السعر تقريبي ويُؤكَّد بعد المعاينة.</p></div><button type="button" class="dx__back" data-reset>↺ ابدأ من جديد</button></div>';
	$o .= '</div><script type="application/json" data-dx-json>' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . '</script></div>';
	return $o;
}

/** (ب) as a section: after the intro on a service page (auto when data exists, unless switched off per page) / on the home page. */
function zad_dx_section( $id = 0, $src = 'sec-b', $title = '', $sub = '' ) {
	if ( $id && 'off' === get_post_meta( $id, '_zad_dx_sec', true ) ) { return ''; }
	// Service page: only the page's own symptoms, and never next to the interactive "wiz" self-check.
	if ( $id && function_exists( 'zad_ix_wiz_active' ) && zad_ix_wiz_active( $id ) ) { return ''; }
	$card = zad_dx_html( $id, $src, (bool) $id );
	if ( '' === $card ) { return ''; }
	return '<section class="sec sec--mint dxsec"><div class="wrap wrap--narrow"><header class="sec__head"><span class="eyebrow">ابدأ من هنا</span><h2>' . esc_html( $title ?: 'لا تعرف اسم المشكلة؟' ) . '</h2>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '<p>أجب عن سؤالين ونقترح عليك الحل.</p>' ) . '</header>' . $card . '</div></section>';
}

/* ---------------- assets ---------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! ( is_front_page() || zad_is_service() ) ) { return; }
	wp_enqueue_script( 'zad-quick', get_template_directory_uri() . '/assets/js/zad-quick.js', array(), zad_asset_ver( 'assets/js/zad-quick.js' ), true );
}, 110 );

/* ---------------- WhatsApp click counter (aggregate only, no personal data) ---------------- */
function zad_wa_click_handler() {
	$src = isset( $_POST['src'] ) ? sanitize_key( wp_unslash( $_POST['src'] ) ) : ''; // phpcs:ignore
	$pid = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0; // phpcs:ignore
	if ( ! isset( zad_quick_sources()[ $src ] ) ) { wp_send_json_success(); }
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$k   = 'zad_wc_' . md5( $ip );
	if ( (int) get_transient( $k ) >= 20 ) { wp_send_json_success(); }
	set_transient( $k, (int) get_transient( $k ) + 1, MINUTE_IN_SECONDS );
	if ( $pid && ! get_post_status( $pid ) ) { $pid = 0; }
	$log = (array) get_option( 'zad_wa_clicks', array() );
	$day = gmdate( 'Y-m-d' );
	$key = $src . '|' . $pid;
	$log[ $key ][ $day ] = (int) ( $log[ $key ][ $day ] ?? 0 ) + 1;
	if ( count( $log ) > 400 ) { $log = array_slice( $log, -300, null, true ); }
	foreach ( $log as $kk => $days ) { if ( count( $days ) > 60 ) { ksort( $days ); $log[ $kk ] = array_slice( $days, -60, null, true ); } }
	update_option( 'zad_wa_clicks', $log, false );
	wp_send_json_success();
}
add_action( 'wp_ajax_zad_wa_click', 'zad_wa_click_handler' );
add_action( 'wp_ajax_nopriv_zad_wa_click', 'zad_wa_click_handler' );

/* ---------------- editor fields (service pages) ---------------- */
function zad_quick_box( $post_id ) {
	$g = function ( $k ) use ( $post_id ) { return get_post_meta( $post_id, '_zad_' . $k, true ); };
	$hc = $g( 'hero_card' ); $fin = $g( 'final_req' ); $sec = $g( 'dx_sec' );
	echo '<h4>بطاقة الهيرو وطلب الخدمة</h4><div class="zad-grid">';
	echo '<p class="description" style="grid-column:1/-1">بطاقة الهيرو موحّدة في كل الموقع: «تذكرة الحجز» (أيقوناتها ثابتة بحسب اسم الباقة، وتختار الخدمة فقط).</p><p><label>قسم الطلب الأخير في آخر الصفحة<select name="zad[final_req]">';
	foreach ( array( '' => 'نموذج الطلب (الحالي)', 'q30' => 'اطلب في 30 ثانية (أ)' ) as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $fin, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></label></p><p><label>قسم «شخّص مشكلتك» بعد التعريف<select name="zad[dx_sec]">';
	foreach ( array( '' => 'يظهر إن وُجدت أعراض', 'off' => 'مخفي في هذه الصفحة' ) as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $sec, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></label></p></div>';
	echo '<p><label>خيارات «الخدمة» في (أ) <small>(سطر لكل خيار، حتى 4؛ فارغ = أنواع الخدمة الفرعية)</small><textarea name="zad[q_svcs]" rows="3" style="width:100%">' . esc_textarea( (string) $g( 'q_svcs' ) ) . '</textarea></label></p>';
	echo '<p><label>خيارات «الحي» في (أ) <small>(سطر لكل حي، حتى 4؛ فارغ = أحياء التغطية)</small><textarea name="zad[q_hoods]" rows="3" style="width:100%">' . esc_textarea( (string) $g( 'q_hoods' ) ) . '</textarea></label></p>';
	echo '<p><label>مربعات تذكرة الحجز <small>(سطر لكل مربع، حتى 6: الاسم | السعر من | الأيقونة (اختياري) | الخدمة أو الرابط (اختياري). فارغ = من أعراض «شخّص مشكلتك» ثم من جدول الأسعار. الأيقونات: roach ant termite bedbug rodent mosquito sofa tank ac house truck box other)</small><textarea name="zad[hero_tiles]" rows="5" style="width:100%" placeholder="صراصير | 150 | roach | مكافحة الصراصير' . "\n" . 'نمل أبيض | 400 | termite | /pest-control/termites/">' . esc_textarea( (string) $g( 'hero_tiles' ) ) . '</textarea></label></p>';
	$dxnote = ( function_exists( 'zad_ix_wiz_active' ) && zad_ix_wiz_active( $post_id ) && '' !== trim( (string) $g( 'dx' ) ) ) ? '<p class="description" style="color:#b45309">⚠ مخفي لأن أداة التشخيص التفاعلية مفعّلة في هذه الصفحة.</p>' : '';
	echo '<h4>الأعراض لـ«شخّص مشكلتك»</h4>' . $dxnote . '<p><label><small>سطر لكل عرض: العرض | الخدمة المقترحة | السعر (نص تكتبه أنت، مثل: من 150 ريال) | رابط الخدمة (اختياري) | إيموجي (اختياري). فارغ = لا يظهر القسم.</small><textarea name="zad[dx]" rows="5" style="width:100%" placeholder="صراصير في المطبخ | مكافحة الصراصير | من 150 ريال | /pest-control/cockroaches/ | 🪳">' . esc_textarea( (string) $g( 'dx' ) ) . '</textarea></label></p>';
}

function zad_quick_save( $post_id, $in ) {
	if ( ! array_key_exists( 'final_req', $in ) ) { return; } // (the hero card is one for the whole site now; _zad_hero_card is left as it was)
	update_post_meta( $post_id, '_zad_final_req', 'q30' === ( $in['final_req'] ?? '' ) ? 'q30' : '' );
	update_post_meta( $post_id, '_zad_dx_sec', 'off' === ( $in['dx_sec'] ?? '' ) ? 'off' : '' );
	foreach ( array( 'q_svcs', 'q_hoods', 'dx', 'hero_tiles' ) as $k ) { update_post_meta( $post_id, '_zad_' . $k, sanitize_textarea_field( $in[ $k ] ?? '' ) ); }
}
