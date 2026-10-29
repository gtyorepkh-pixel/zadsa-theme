<?php defined( 'ABSPATH' ) || exit;
/* ==========================================================================
   2. محرك السيو الذكي: حقن الأسعار في الميتا، معالجة الروابط الخارجية، والـ Alt العربي
   ========================================================================== */

/** حقن أقل سعر تلقائياً داخل وصف الميتا لصفحات الخدمات إذا لم يكن مكتوباً (Ported from rokn_desc_with_price) */
function zad_enrich_meta_desc_with_price( $desc, $post_id ) {
	if ( ! $post_id || ! in_array( get_post_type( $post_id ), zad_service_types(), true ) ) {
		return $desc;
	}
	$rows = zad_price_rows( $post_id );
	$min  = zad_min_price( $rows );
	if ( ! $min ) {
		$min = (int) get_post_meta( $post_id, '_zad_price', true );
	}
	if ( ! $min || false !== mb_strpos( $desc, (string) $min ) || false !== mb_strpos( $desc, 'ريال' ) ) {
		return $desc;
	}
	$price_phrase = 'يبدأ من ' . number_format_i18n( $min ) . ' ريال.';
	$cta_phrase   = 'اطلب عرض سعر.';
	$try_full     = rtrim( $desc, " .،" ) . '. ' . $price_phrase . ' ' . $cta_phrase;
	if ( mb_strlen( $try_full, 'UTF-8' ) <= 160 ) {
		return $try_full;
	}
	$try_short = rtrim( $desc, " .،" ) . '. ' . $price_phrase;
	if ( mb_strlen( $try_short, 'UTF-8' ) <= 160 ) {
		return $try_short;
	}
	return $desc;
}

/** توليد وصف ميتا ذكي لأعمالنا (Ported from rokn_work_meta_desc) */
function zad_work_smart_meta_desc( $id ) {
	$svc  = zad_wk_service( $id );
	$tail = 'من أعمال ' . zad_opt( 'zad_alt_name', 'زاد السعودية' ) . ( $svc ? ' في ' . zad_svc_label( $svc ) : '' ) . '.';
	$src  = '';
	foreach ( array( 'result', 'complaint', 'free_story', 'inspection' ) as $k ) {
		$src = trim( (string) get_post_meta( $id, '_zad_wk_' . $k, true ) );
		if ( $src ) { break; }
	}
	if ( ! $src ) {
		$src = zad_wk_alt( $id );
	}
	$s = trim( preg_split( '/(?<=[.؟!])\s/u', $src )[0], " .\t\n" );
	$out = $s . ' — ' . $tail;
	return mb_strlen( $out, 'UTF-8' ) > 160 ? rtrim( mb_substr( $out, 0, 157, 'UTF-8' ) ) . '…' : $out;
}

/** فرض النص البديل العربي (Alt) و loading="lazy" لكل صور المحتوى (Ported from rokn inc/seo.php) */
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular() || false === strpos( $html, '<img' ) ) {
		return $html;
	}
	$title = esc_attr( wp_strip_all_tags( get_the_title() ) );
	return preg_replace_callback( '/<img\b[^>]*>/', function ( $m ) use ( $title ) {
		$tag = $m[0];
		if ( ! preg_match( '/\salt="([^"]*)"/u', $tag, $a ) || ! preg_match( '/[\x{0600}-\x{06FF}]{2,}/u', html_entity_decode( $a[1] ) ) ) {
			$tag = preg_replace( '/\salt="[^"]*"/', '', $tag );
			$tag = preg_replace( '/^<img/', '<img alt="' . $title . '"', $tag );
		}
		if ( false === strpos( $tag, 'loading=' ) ) {
			$tag = preg_replace( '/^<img/', '<img loading="lazy" decoding="async"', $tag );
		}
		return $tag;
	}, $html );
}, 30 );

/** حماية الروابط الخارجية في المحتوى: rel="me noopener" لحساباتنا، و rel="nofollow noopener" لغيرها */
add_filter( 'the_content', function ( $html ) {
	if ( false === stripos( $html, '<a ' ) ) {
		return $html;
	}
	$own  = wp_parse_url( home_url(), PHP_URL_HOST );
	$mine = array_map( 'untrailingslashit', array_filter( array(
		zad_opt( 'memopt_fb' ), zad_opt( 'memopt_tw' ), zad_opt( 'memopt_insta' ),
		zad_opt( 'memopt_yt' ), zad_opt( 'zad_linkedin' ), zad_opt( 'zad_map_url' ),
	) ) );
	return preg_replace_callback( '#<a\s[^>]*href=(["\'])(https?://[^"\']+)\1[^>]*>#i', function ( $m ) use ( $own, $mine ) {
		$host = (string) wp_parse_url( $m[2], PHP_URL_HOST );
		if ( '' === $host || $host === $own || preg_match( '#(^|\.)zadksa\.(com|local)$#i', $host ) ) {
			return $m[0];
		}
		if ( false !== strpos( $m[2], 'wa.me/' ) ) {
			return $m[0];
		}
		$rel = in_array( untrailingslashit( $m[2] ), $mine, true ) ? 'me noopener' : 'nofollow noopener';
		$tag = preg_replace( '#\s(rel|target)=(["\']).*?\2#i', '', $m[0] );
		return substr( $tag, 0, -1 ) . ' target="_blank" rel="' . $rel . '">';
	}, $html );
}, 25 );

/** أكواد التحقق لمشرفي المواقع (Google & Bing Verification) في الهيدر */
add_action( 'wp_head', function () {
	$gv = trim( (string) zad_opt( 'zad_google_verify', '' ) );
	$bv = trim( (string) zad_opt( 'zad_bing_verify', '' ) );
	if ( $gv && preg_match( '/^[A-Za-z0-9_-]{15,80}$/', $gv ) ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $gv ) . '">' . "\n";
	}
	if ( $bv && preg_match( '/^[A-Fa-f0-9]{20,40}$/', $bv ) ) {
		echo '<meta name="msvalidate.01" content="' . esc_attr( $bv ) . '">' . "\n";
	}
}, 2 );

/* ==========================================================================
   3. فلتر جودة الأرشفة لصفحات "أعمالنا" + خريطة موقع الفيديو والصور المستقلة
      (Ported from rokn inc/works.php & inc/media.php)
   ========================================================================== */

// Index eligibility and media sitemaps are owned by inc/seo/policy.php and projections.php.

