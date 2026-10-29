<?php
/**
 * أدوات ← «الروابط السياقية (زاد)».
 *
 * بعد حذف بلوك «روابط ذات صلة» (3.47.1) بقى الربط بين صفحات المدينة الواحدة معتمداً على القائمة
 * والفوتر بس. الأداة دي بتزرع روابط جوّه النص نفسه: بتدوّر على اسم خدمة تانية في نفس المدينة لو
 * مذكور في فقرة، وتحوّل أول ذكر له لرابط لصفحته. ده الربط اللي بيفيد فعلاً، لأن نص الرابط بيوصف
 * الصفحة الهدف، والرابط جوّه كلام مكتوب مش في قالب مكرر.
 *
 * قواعد صارمة:
 *  - جوّه وسوم <p> بس، والفقرة اللي فيها رابط أصلاً تُترك كما هي.
 *  - أول ذكر للعبارة بس، وبحد أقصى 3 روابط للصفحة (فلتر zad_ctx_max).
 *  - ما بتربطش صفحة بنفسها ولا بعبارة هي موضوع الصفحة.
 *  - نسخة من النص القديم في ميتا _zad_ctx_backup، وزر تراجع يرجّع الكل.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** أقصى عدد روابط تُزرع في الصفحة الواحدة. */
function zad_ctx_max() { return (int) apply_filters( 'zad_ctx_max', 3 ); }

/** عناصر مدينة واحدة من الكتالوج: id => array( url, label ). */
function zad_ctx_targets( $city ) {
	$out = array();
	foreach ( zad_catalog() as $f ) {
		foreach ( (array) $f['items'] as $it ) {
			if ( zad_city_norm_name( (string) $it['city'] ) !== zad_city_norm_name( $city ) ) { continue; }
			$label = trim( wp_strip_all_tags( (string) $it['label'] ) );
			if ( '' === $label || ! $it['id'] ) { continue; }
			$out[ (int) $it['id'] ] = array( 'url' => (string) $it['url'], 'label' => $label );
		}
	}
	return $out;
}

/** كلمات لا تصلح نص رابط: أفعال الخدمة العامة وأنواع العقار. */
function zad_ctx_stopwords() {
	return (array) apply_filters( 'zad_ctx_stopwords', array( 'تنظيف', 'مكافحة', 'تسليك', 'جلي', 'نقل', 'تخزين', 'غسيل', 'شقق', 'فلل', 'منازل', 'بيوت', 'مباني', 'عمائر', 'منشات', 'منشآت', 'مكان', 'خدمة', 'خدمات' ) );
}

/** كلمة واحدة كـregex، مع «ال» اختيارية وحدود حروف عربية. */
function zad_ctx_rx( $words ) {
	$core = array();
	foreach ( (array) $words as $w ) {
		$w = preg_replace( '/^(?:ال)/u', '', $w );
		if ( mb_strlen( $w ) < 3 ) { continue; }
		$core[] = '(?:ال)?' . preg_quote( $w, '/' );
	}
	if ( ! $core ) { return ''; }
	return '/(?<![ء-ي])(' . implode( '\s+', $core ) . ')(?![ء-ي])/u';
}

/**
 * أنماط العبارة مرتّبة من الأطول للأقصر: الاسم الكامل («تنظيف خزانات») ثم موضوع الخدمة وحده («الخزانات»).
 * الاسم الكامل أدق، وموضوع الخدمة هو اللي بيظهر فعلاً في الكلام المكتوب. الكلمات العامة مستبعدة.
 */
function zad_ctx_patterns( $label ) {
	$label = preg_replace( '/[ًٌٍَُِّْـ]/u', '', (string) $label );
	$label = preg_replace( '/^\s*(?:شركة|مؤسسة|افضل|أفضل|اسعار|أسعار|سعر|خدمة|خدمات)\s+/u', '', trim( $label ) );
	$words = array_values( array_filter( preg_split( '/\s+/u', trim( $label ) ) ) );
	$out   = array();
	if ( count( $words ) >= 2 ) { $rx = zad_ctx_rx( $words ); if ( '' !== $rx ) { $out[] = $rx; } }
	$stop  = zad_ctx_stopwords();
	$subj  = array();
	foreach ( $words as $w ) {
		$bare = preg_replace( '/^(?:ال)/u', '', $w );
		if ( in_array( $bare, $stop, true ) ) { continue; }
		$subj[] = $w;
	}
	if ( $subj && count( $subj ) < count( $words ) && mb_strlen( preg_replace( '/^(?:ال)/u', '', $subj[0] ) ) >= 4 ) {
		$rx = zad_ctx_rx( $subj );
		if ( '' !== $rx ) { $out[] = $rx; }
	}
	return $out;
}

/**
 * خطة الزرع لمدينة: page_id => array( before, after, array( label => url ) ).
 * بتقرأ النص الخام وتعدّل جوّه الفقرات بس.
 */
function zad_ctx_plan( $city ) {
	$targets = zad_ctx_targets( $city );
	$plan    = array();
	foreach ( $targets as $id => $me ) {
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status ) { continue; }
		$raw  = (string) $post->post_content;
		if ( '' === trim( $raw ) ) { continue; }
		$done = array();
		$max  = zad_ctx_max();
		$new  = preg_replace_callback(
			'/(<p\b[^>]*>)(.*?)(<\/p>)/su',
			function ( $m ) use ( $targets, $id, &$done, $max ) {
				if ( count( $done ) >= $max || false !== stripos( $m[0], '<a' ) ) { return $m[0]; }
				$inner = $m[2];
				foreach ( $targets as $tid => $t ) {
					if ( count( $done ) >= $max ) { break; }
					if ( $tid === $id || isset( $done[ $t['label'] ] ) ) { continue; }
					foreach ( zad_ctx_patterns( $t['label'] ) as $pat ) {
						if ( ! preg_match( $pat, $inner ) ) { continue; }
						$inner = preg_replace( $pat, '<a href="' . esc_url( $t['url'] ) . '">$1</a>', $inner, 1 );
						$done[ $t['label'] ] = $t['url'];
						break;
					}
				}
				return $m[1] . $inner . $m[3];
			},
			$raw
		);
		if ( $done && $new !== $raw ) { $plan[ $id ] = array( $raw, $new, $done ); }
	}
	return $plan;
}

/** المدن اللي عندها عناصر في الكتالوج. */
function zad_ctx_cities() {
	$c = array();
	foreach ( zad_catalog() as $f ) {
		foreach ( (array) $f['items'] as $it ) { if ( '' !== (string) $it['city'] ) { $c[ (string) $it['city'] ] = true; } }
	}
	$out = array_keys( $c );
	sort( $out );
	return $out;
}

add_action( 'admin_menu', function () {
	add_management_page( 'الروابط السياقية', 'الروابط السياقية (زاد)', 'manage_options', 'zad-ctxlinks', 'zad_ctx_page' );
} );

function zad_ctx_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$cities = zad_ctx_cities();
	$city   = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : (string) ( $cities[0] ?? '' );
	echo '<div class="wrap" dir="rtl" style="max-width:940px"><h1>الروابط السياقية</h1>';

	if ( isset( $_POST['zad_ctx_do'] ) && check_admin_referer( 'zad_ctx' ) ) {
		$do = sanitize_key( wp_unslash( $_POST['zad_ctx_do'] ) );
		if ( 'run' === $do ) {
			$log = (array) get_option( 'zad_ctx_log', array() );
			$n   = 0;
			foreach ( zad_ctx_plan( $city ) as $id => $p ) {
				if ( ! metadata_exists( 'post', $id, '_zad_ctx_backup' ) ) { update_post_meta( $id, '_zad_ctx_backup', $p[0] ); }
				wp_update_post( array( 'ID' => $id, 'post_content' => $p[1] ) );
				$log[ $id ] = 1;
				$n++;
			}
			update_option( 'zad_ctx_log', $log, false );
			echo '<div class="notice notice-success"><p>اتزرعت روابط في ' . (int) $n . ' صفحة في «' . esc_html( $city ) . '».</p></div>';
		} elseif ( 'undo' === $do ) {
			$log = (array) get_option( 'zad_ctx_log', array() );
			$n   = 0;
			foreach ( array_keys( $log ) as $id ) {
				$raw = get_post_meta( (int) $id, '_zad_ctx_backup', true );
				if ( '' === (string) $raw ) { continue; }
				wp_update_post( array( 'ID' => (int) $id, 'post_content' => (string) $raw ) );
				delete_post_meta( (int) $id, '_zad_ctx_backup' );
				$n++;
			}
			delete_option( 'zad_ctx_log' );
			echo '<div class="notice notice-warning"><p>رجعت ' . (int) $n . ' صفحة لنصها قبل الزرع.</p></div>';
		}
	}

	echo '<p>بتحوّل <strong>أول ذكر</strong> لاسم خدمة تانية في نفس المدينة — جوّه فقرة مكتوبة — لرابط لصفحتها. الفقرة اللي فيها رابط أصلاً ما تتلمسش، وأقصى ' . (int) zad_ctx_max() . ' روابط للصفحة.</p>';

	echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="zad-ctxlinks"><select name="city">';
	foreach ( $cities as $c ) {
		echo '<option value="' . esc_attr( $c ) . '"' . selected( $c, $city, false ) . '>' . esc_html( $c ) . '</option>';
	}
	echo '</select> <button class="button">اعرض</button></form>';

	$plan = zad_ctx_plan( $city );
	echo '<h2>' . esc_html( $city ) . ': ' . count( $plan ) . ' صفحة هتتغيّر</h2>';
	if ( ! $plan ) {
		echo '<p>مفيش صفحة فيها ذكر لخدمة تانية بدون رابط — يا إما اتعمل خلاص، يا إما النص ما بيذكرش الخدمات التانية بأسمائها.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>الصفحة</th><th>الروابط اللي هتتزرع</th></tr></thead><tbody>';
		foreach ( $plan as $id => $p ) {
			$ls = array();
			foreach ( $p[2] as $label => $url ) { $ls[] = '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $label ) . '</a>'; }
			echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . implode( ' · ', $ls ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	}

	echo '<form method="post" style="margin-top:14px">';
	wp_nonce_field( 'zad_ctx' );
	if ( $plan ) { echo '<button class="button button-primary" name="zad_ctx_do" value="run">ازرع الروابط في ' . esc_html( $city ) . '</button> '; }
	if ( get_option( 'zad_ctx_log' ) ) { echo '<button class="button" name="zad_ctx_do" value="undo">تراجع عن الكل</button>'; }
	echo '</form></div>';
}
