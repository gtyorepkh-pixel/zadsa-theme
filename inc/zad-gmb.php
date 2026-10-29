<?php
/**
 * تقييمات خرائط جوجل (Google Business Profile) عبر Places API (New).
 *
 * - السحب من السيرفر فقط (الزائر ما بيكلّمش جوجل ولا بيشوف المفتاح)، مرة كل 24 ساعة بالكرون
 *   + زر «اسحب الآن» في أدوات ← «تقييمات جوجل».
 * - النتيجة بتتخزن في خيار `zad_gmb_cache`. لو السحب فشل بنسيب آخر نسخة ناجحة،
 *   وبعد 30 يوم من غير تحديث بتختفي (شروط جوجل بتمنع الاحتفاظ بمحتوى الخرائط أطول من كده).
 * - ما فيش رقم أو تقييم بيتعرض أو بيدخل السكيما إلا لو جاي فعلاً من جوجل.
 * - الـAPI بترجّع 5 تقييمات كحد أقصى (يختارها جوجل)، لكن المتوسط والعدد الكلي كاملين.
 *
 * الإعدادات (بيانات ونصوص إضافية): zad_gmb_key · zad_gmb_place.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_GMB_CACHE = 'zad_gmb_cache';
const ZAD_GMB_ERR   = 'zad_gmb_err';
const ZAD_GMB_HOOK  = 'zad_gmb_refresh';
const ZAD_GMB_TTL   = 30 * DAY_IN_SECONDS;

/** مفتاح الـAPI والـPlace ID بعد التنظيف؛ فاضي لو أي حاجة ناقصة أو شكلها غلط. */
function zad_gmb_conf() {
	$key   = trim( (string) zad_opt( 'zad_gmb_key' ) );
	$place = preg_replace( '#^places/#', '', trim( (string) zad_opt( 'zad_gmb_place' ) ) );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{20,80}$/', $key ) || ! preg_match( '/^[A-Za-z0-9_-]{10,200}$/', $place ) ) {
		return array();
	}
	return array( 'key' => $key, 'place' => $place );
}

/** آخر نسخة ناجحة (أو null لو مفيش أو انتهت صلاحيتها). */
function zad_gmb_data() {
	static $d = false;
	if ( false === $d ) {
		$c = get_option( ZAD_GMB_CACHE );
		$d = ( is_array( $c ) && ! empty( $c['t'] ) && ( time() - (int) $c['t'] ) < ZAD_GMB_TTL && ! empty( $c['count'] ) ) ? $c : null;
	}
	return $d;
}

/** رابط «قيّمنا على جوجل» من الـPlace ID. */
function zad_gmb_review_url() {
	$c = zad_gmb_conf();
	return $c ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $c['place'] ) : '';
}

/**
 * يسحب من جوجل ويخزّن. يرجّع true أو نص الخطأ.
 * يتنفذ من الكرون ومن زر الأدمن فقط، ما يتنفذش وقت عرض الصفحة.
 */
function zad_gmb_fetch() {
	$c = zad_gmb_conf();
	if ( ! $c ) {
		update_option( ZAD_GMB_ERR, 'مفتاح الـAPI أو الـPlace ID ناقص أو شكله غير صحيح.', false );
		return 'مفتاح الـAPI أو الـPlace ID ناقص أو شكله غير صحيح.';
	}
	$res = wp_remote_get(
		'https://places.googleapis.com/v1/places/' . rawurlencode( $c['place'] ) . '?languageCode=ar',
		array(
			'timeout' => 12,
			'headers' => array(
				'X-Goog-Api-Key'   => $c['key'],
				'X-Goog-FieldMask' => 'rating,userRatingCount,googleMapsUri,reviews.rating,reviews.text,reviews.relativePublishTimeDescription,reviews.authorAttribution',
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		$e = 'تعذّر الاتصال بجوجل: ' . $res->get_error_message();
		update_option( ZAD_GMB_ERR, $e, false );
		return $e;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code || ! is_array( $body ) ) {
		$e = 'رد جوجل (' . $code . '): ' . ( is_array( $body ) && ! empty( $body['error']['message'] ) ? wp_strip_all_tags( $body['error']['message'] ) : 'غير متوقع' );
		update_option( ZAD_GMB_ERR, $e, false );
		return $e;
	}
	$rating = isset( $body['rating'] ) ? round( (float) $body['rating'], 1 ) : 0;
	$count  = isset( $body['userRatingCount'] ) ? (int) $body['userRatingCount'] : 0;
	if ( $rating <= 0 || $count <= 0 ) {
		$e = 'جوجل ما رجّعش تقييمات لهذا المكان.';
		update_option( ZAD_GMB_ERR, $e, false );
		return $e;
	}
	$reviews = array();
	foreach ( (array) ( $body['reviews'] ?? array() ) as $r ) {
		$text = trim( (string) ( $r['text']['text'] ?? '' ) );
		if ( '' === $text ) { continue; }
		$reviews[] = array(
			'name'   => sanitize_text_field( (string) ( $r['authorAttribution']['displayName'] ?? '' ) ),
			'url'    => esc_url_raw( (string) ( $r['authorAttribution']['uri'] ?? '' ) ),
			'rating' => max( 1, min( 5, (int) ( $r['rating'] ?? 5 ) ) ),
			'text'   => sanitize_textarea_field( $text ),
			'when'   => sanitize_text_field( (string) ( $r['relativePublishTimeDescription'] ?? '' ) ),
		);
	}
	update_option( ZAD_GMB_CACHE, array(
		't'       => time(),
		'rating'  => $rating,
		'count'   => $count,
		'uri'     => esc_url_raw( (string) ( $body['googleMapsUri'] ?? '' ) ),
		'reviews' => $reviews,
	), false );
	delete_option( ZAD_GMB_ERR );
	return true;
}

/* ------------------------------------------------------------------ الكرون */

add_action( ZAD_GMB_HOOK, 'zad_gmb_fetch' );
add_action( 'init', function () {
	$on = (bool) zad_gmb_conf();
	$ts = wp_next_scheduled( ZAD_GMB_HOOK );
	if ( $on && ! $ts ) { wp_schedule_event( time() + 60, 'daily', ZAD_GMB_HOOK ); }
	if ( ! $on && $ts ) { wp_unschedule_event( $ts, ZAD_GMB_HOOK ); }
} );
add_action( 'switch_theme', function () { wp_clear_scheduled_hook( ZAD_GMB_HOOK ); } );

/* ------------------------------------------------------------------ العرض */

/** الملخص: متوسط وعدد من جوجل، وإلا القيم اللي كتبها صاحب الموقع يدوياً. */
function zad_gmb_summary() {
	$g = zad_gmb_data();
	if ( $g ) { return array( 'score' => $g['rating'], 'count' => $g['count'], 'google' => true ); }
	$s = trim( (string) zad_opt( 'zad_rating_score' ) );
	return $s ? array( 'score' => (float) $s, 'count' => trim( (string) zad_opt( 'zad_rating_count' ) ), 'google' => false ) : array();
}

/** تقييمات جوجل بنفس شكل آراء الصفحة الرئيسية (text, name, city, rating). */
function zad_gmb_testimonials() {
	$g = zad_gmb_data();
	$o = array();
	foreach ( $g ? $g['reviews'] : array() as $r ) {
		$o[] = array( 'text' => $r['text'], 'name' => $r['name'], 'city' => $r['when'], 'rating' => $r['rating'] );
	}
	return $o;
}

/** سطر «★ 4.9 من 120 تقييم» + نسب جوجل + رابط التقييم. */
function zad_gmb_summary_html() {
	$s = zad_gmb_summary();
	if ( ! $s ) { return ''; }
	$h = '<p class="ratesum">' . zad_stars( $s['score'] ) . ' <b>' . esc_html( $s['score'] ) . '</b>'
		. ( $s['count'] ? ' <span>من ' . esc_html( $s['count'] ) . ' تقييم</span>' : '' );
	if ( $s['google'] ) {
		$g   = zad_gmb_data();
		$h  .= ' <span class="gmb-src">على <a href="' . esc_url( $g['uri'] ?: zad_opt( 'zad_map_url' ) ) . '" target="_blank" rel="noopener">خرائط جوجل</a></span>';
	}
	return $h . '</p>';
}

/** [zad_gmb_reviews count="3" write="1"] — تقييمات جوجل في أي صفحة. بدون بيانات حقيقية ما بيطبع حاجة. */
add_shortcode( 'zad_gmb_reviews', function ( $atts ) {
	$a = shortcode_atts( array( 'count' => 3, 'write' => 1 ), $atts, 'zad_gmb_reviews' );
	$g = zad_gmb_data();
	if ( ! $g ) { return ''; }
	$out = '<section class="gmb">' . zad_gmb_summary_html();
	$rev = array_slice( $g['reviews'], 0, max( 1, min( 5, (int) $a['count'] ) ) );
	if ( $rev ) {
		$out .= '<div class="sgrid">';
		foreach ( $rev as $r ) {
			$name = $r['url'] ? '<a href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $r['name'] ) . '</a>' : esc_html( $r['name'] );
			$out .= '<figure class="tcard">' . zad_stars( $r['rating'] ) . '<blockquote>' . esc_html( $r['text'] ) . '</blockquote>'
				. '<figcaption><b>' . $name . '</b><span>' . esc_html( $r['when'] ) . '</span></figcaption></figure>';
		}
		$out .= '</div>';
	}
	if ( (int) $a['write'] && zad_gmb_review_url() ) {
		$out .= '<p class="gmb__write"><a class="btn btn--primary" href="' . esc_url( zad_gmb_review_url() ) . '" target="_blank" rel="noopener">قيّمنا على جوجل</a></p>';
	}
	return $out . '</section>';
} );

/* ------------------------------------------------------------------ صفحة الأدمن */

if ( is_admin() ) {
	add_action( 'admin_menu', function () {
		add_management_page( 'تقييمات جوجل', 'تقييمات جوجل (زاد)', 'manage_options', 'zad-gmb', 'zad_gmb_page' );
	} );
}

function zad_gmb_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	echo '<div class="wrap" dir="rtl" style="max-width:820px"><h1>تقييمات جوجل</h1>';

	if ( isset( $_POST['zad_gmb_do'] ) && check_admin_referer( 'zad_gmb' ) ) {
		$r = zad_gmb_fetch();
		echo true === $r
			? '<div class="notice notice-success"><p>تم السحب بنجاح.</p></div>'
			: '<div class="notice notice-error"><p>' . esc_html( $r ) . '</p></div>';
		$GLOBALS['zad_gmb_fresh'] = true;
	}

	$c = zad_gmb_conf();
	$g = get_option( ZAD_GMB_CACHE );
	$e = get_option( ZAD_GMB_ERR );
	echo '<table class="widefat striped"><tbody>';
	echo '<tr><td style="width:30%">مفتاح الـAPI</td><td>' . ( $c ? 'مُدخل (' . esc_html( substr( $c['key'], 0, 4 ) ) . '…)' : '<b style="color:#b32d2e">غير مُدخل أو شكله غلط</b>' ) . '</td></tr>';
	echo '<tr><td>Place ID</td><td>' . ( $c ? '<code>' . esc_html( $c['place'] ) . '</code>' : '<b style="color:#b32d2e">غير مُدخل أو شكله غلط</b>' ) . '</td></tr>';
	if ( is_array( $g ) && ! empty( $g['t'] ) ) {
		echo '<tr><td>آخر سحب ناجح</td><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $g['t'] ) ) . '</td></tr>';
		echo '<tr><td>التقييم / العدد</td><td>' . esc_html( $g['rating'] ) . ' / ' . (int) $g['count'] . '</td></tr>';
		echo '<tr><td>تقييمات نصية مسحوبة</td><td>' . count( (array) $g['reviews'] ) . ' (جوجل بيرجّع 5 كحد أقصى)</td></tr>';
		echo '<tr><td>معروض في الموقع؟</td><td>' . ( zad_gmb_data() ? 'نعم' : 'لا — النسخة أقدم من 30 يوم' ) . '</td></tr>';
	} else {
		echo '<tr><td>آخر سحب ناجح</td><td>لسه ما حصلش</td></tr>';
	}
	if ( $e ) { echo '<tr><td>آخر خطأ</td><td style="color:#b32d2e">' . esc_html( $e ) . '</td></tr>'; }
	echo '<tr><td>التحديث التلقائي</td><td>' . ( wp_next_scheduled( ZAD_GMB_HOOK ) ? 'يومي — الجاي: ' . esc_html( wp_date( 'Y-m-d H:i', (int) wp_next_scheduled( ZAD_GMB_HOOK ) ) ) : 'متوقف (أدخل المفتاح والـPlace ID)' ) . '</td></tr>';
	if ( zad_gmb_review_url() ) { echo '<tr><td>رابط «قيّمنا على جوجل»</td><td><a href="' . esc_url( zad_gmb_review_url() ) . '" target="_blank" rel="noopener">فتح</a></td></tr>'; }
	echo '</tbody></table>';

	echo '<form method="post" style="margin:16px 0">';
	wp_nonce_field( 'zad_gmb' );
	echo '<button class="button button-primary" name="zad_gmb_do" value="1"' . ( $c ? '' : ' disabled' ) . '>اسحب الآن</button></form>';

	echo '<h2>الإعداد</h2><ol>'
		. '<li>من Google Cloud فعّل <b>Places API (New)</b> وأنشئ مفتاح API، وقيّده على Places API (New) وعلى عنوان سيرفر الموقع.</li>'
		. '<li>الـPlace ID من <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">أداة Place ID من جوجل</a> (ابحث باسم النشاط).</li>'
		. '<li>الصق الاتنين في «بيانات ونصوص إضافية ← تقييمات جوجل»، واحفظ، وبعدين اضغط «اسحب الآن».</li>'
		. '<li>بعد ما السحب ينجح: الرئيسية بتعرض التقييمات الحقيقية بدل اليدوية، والسكيما بتاخد المتوسط والعدد، والشورتكود <code>[zad_gmb_reviews count="3"]</code> يعرضها في أي صفحة.</li>'
		. '</ol></div>';
}
