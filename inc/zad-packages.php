<?php
/**
 * «باقات الأسعار»: package cards in addition to the price table, styled per service kind (pest | clean | tank | ac),
 * and tied into the page's Service schema (hasOfferCatalog).
 * Fields (post meta, `_zad_` + key — the same names the editor and the lab tool write): packages, packages_style, packages_title, packages_sub.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_pk_styles() { return array( 'auto' => 'تلقائي (حسب نوع الخدمة)', 'pest' => 'مكافحة (أخضر/تيل)', 'clean' => 'تنظيف (أبيض وذهبي)', 'tank' => 'خزانات (بيج وبني)', 'ac' => 'مكيفات (أزرق فاتح)' ); }

/** Style of a page: the page's own choice, else tank / ac by name (slug, parents, section, title), else by content type. */
function zad_pk_style( $id ) {
	$own = (string) get_post_meta( $id, '_zad_packages_style', true );
	if ( in_array( $own, array( 'pest', 'clean', 'tank', 'ac' ), true ) ) { return $own; }
	$p   = get_post( $id );
	$hay = $p ? urldecode( $p->post_name ) . ' ' . $p->post_title : '';
	foreach ( (array) get_post_ancestors( $id ) as $aid ) { $a = get_post( $aid ); if ( $a ) { $hay .= ' ' . urldecode( $a->post_name ); } }
	$terms = get_the_terms( $id, 'service_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { $hay .= ' ' . urldecode( $t->slug ) . ' ' . $t->name; } }
	if ( preg_match( '/tank|خزان/iu', $hay ) ) { return 'tank'; }
	if ( preg_match( '/(?<![a-z])ac(?![a-z])|air|مكيف|تكييف/iu', $hay ) ) { return 'ac'; }
	$pt = $p ? $p->post_type : '';
	if ( 'pest_control' === $pt || preg_match( '/pest/i', $pt ) ) { return 'pest'; }
	return 'clean';
}

/** «6 شهور» / «سنة» / «10 سنوات» / «حتى سنة» → array( value, 'MON'|'ANN' ), or null when there is no clear number. */
function zad_pk_warranty( $text ) {
	$t = zad_digits_en( trim( (string) $text ) );
	if ( '' === $t ) { return null; }
	$words = array( 'ثلاث' => 3, 'ثلاثة' => 3, 'أربع' => 4, 'أربعة' => 4, 'اربع' => 4, 'اربعة' => 4, 'خمس' => 5, 'خمسة' => 5, 'ست' => 6, 'ستة' => 6, 'سبع' => 7, 'سبعة' => 7, 'ثماني' => 8, 'ثمان' => 8, 'ثمانية' => 8, 'تسع' => 9, 'تسعة' => 9, 'عشر' => 10, 'عشرة' => 10 );
	$n = 0;
	if ( preg_match( '/(\d+)/', $t, $m ) ) { $n = (int) $m[1]; }
	if ( ! $n ) {
		if ( preg_match( '/سنتين|سنتان|عامين|عامان/u', $t ) ) { return array( 2, 'ANN' ); }
		if ( preg_match( '/شهرين|شهران/u', $t ) ) { return array( 2, 'MON' ); }
		foreach ( $words as $w => $v ) { if ( preg_match( '/(?<![\p{L}])' . $w . '(?![\p{L}])/u', $t ) ) { $n = $v; break; } }
	}
	$year  = (bool) preg_match( '/سنة|سنوات|سنين|عام|أعوام|اعوام/u', $t );
	$month = (bool) preg_match( '/شهر|أشهر|اشهر|شهور/u', $t );
	if ( ! $n && $year && ! $month ) { $n = 1; }      // «سنة» / «حتى سنة»
	elseif ( ! $n && $month && ! $year ) { $n = 1; }  // «شهر»
	if ( $n <= 0 || ( ! $year && ! $month ) ) { return null; }
	return array( $n, $year ? 'ANN' : 'MON' );
}

function zad_pk_is_monthly( $unit ) { return (bool) preg_match( '/شهر/u', (string) $unit ); }

/* ---------------- schema: hasOfferCatalog ---------------- */
function zad_pk_title( $id ) {
	$t = trim( (string) get_post_meta( $id, '_zad_packages_title', true ) );
	return '' !== $t ? $t : 'باقات الأسعار';
}

/** OfferCatalog node for the page's packages, or null. Quote-only packages carry no price at all (never 0). */
function zad_pk_catalog( $id ) {
	$pks = zad_parse_packages( get_post_meta( $id, '_zad_packages', true ) );
	if ( ! $pks ) { return null; }
	$city  = function_exists( 'zad_current_city' ) ? zad_current_city( $id ) : array( 'name' => '', 'source' => 'default' );
	$items = array();
	foreach ( $pks as $p ) {
		$o = array( '@type' => 'Offer', 'name' => $p['name'], 'priceCurrency' => 'SAR', 'itemOffered' => array( '@type' => 'Service', 'name' => $p['name'] ) );
		if ( '' !== $p['desc'] ) { $o['description'] = $p['desc']; }
		if ( '' !== $p['cat'] ) { $o['category'] = $p['cat']; } // only when the 9th column is filled
		$o += zad_price_offer( $p, zad_pk_is_monthly( $p['unit'] ) ); // number / range / «من X» / nothing for a quote
		if ( ! empty( $city['name'] ) && 'default' !== $city['source'] ) { $o['areaServed'] = array( '@type' => 'City', 'name' => $city['name'] ); }
		$w = zad_pk_warranty( $p['warranty'] );
		if ( $w ) { $o['warranty'] = array( '@type' => 'WarrantyPromise', 'durationOfWarranty' => array( '@type' => 'QuantitativeValue', 'value' => $w[0], 'unitCode' => $w[1] ) ); }
		$items[] = $o;
	}
	return array( '@type' => 'OfferCatalog', 'name' => zad_pk_title( $id ), 'itemListElement' => $items );
}

/* ---------------- HTML ---------------- */
function zad_pk_icon_for( $name ) {
	if ( preg_match( '/سبليت|split/iu', $name ) ) { return 'acsplit'; }
	if ( preg_match( '/شباك|window/iu', $name ) ) { return 'acwindow'; }
	if ( preg_match( '/مركزي|central/iu', $name ) ) { return 'accentral'; }
	return 'ac';
}

function zad_pk_price_html( $p ) {
	$unit = '' !== $p['unit'] ? '<span class="pkgx__u">' . esc_html( $p['unit'] ) . '</span>' : '';
	switch ( $p['kind'] ) {
		case 'range': return '<div class="pkgx__price"><b>' . esc_html( $p['raw_a'] ) . ' – ' . esc_html( $p['raw_b'] ) . '</b>' . $unit . '</div>';
		case 'from':  return '<div class="pkgx__price"><small>يبدأ من</small><b>' . esc_html( $p['raw_a'] ) . '</b>' . $unit . '</div>';
		case 'fixed': return '<div class="pkgx__price"><b>' . esc_html( $p['raw_a'] ) . '</b>' . $unit . '</div>';
	}
	return '<div class="pkgx__price pkgx__price--q"><b>' . esc_html( '' !== $p['price'] ? $p['price'] : 'بعد المعاينة' ) . '</b></div>'; // as written (e.g. «بعد المعاينة»)
}

/** The section, or '' when no package line exists. */
function zad_pk_html( $id ) {
	$pks = zad_parse_packages( get_post_meta( $id, '_zad_packages', true ) );
	if ( ! $pks ) { return ''; }
	$style = zad_pk_style( $id );
	$sub   = trim( (string) get_post_meta( $id, '_zad_packages_sub', true ) );
	if ( '' === $sub && 'clean' === $style ) { $sub = 'اختر الباقة المناسبة لك.'; }
	$eyebrows = array( 'pest' => 'باقات الحماية', 'clean' => 'باقات واضحة', 'tank' => 'باقات الأسعار', 'ac' => 'باقات الأسعار' );
	$flag  = trim( (string) zad_opt( 'zad_packages_flag', '' ) ) ?: 'الأكثر طلباً';
	$note  = trim( (string) zad_opt( 'zad_packages_note', '' ) ) ?: 'الأسعار تقديرية وتتحدد بعد المعاينة';
	$city  = function_exists( 'zad_current_city' ) ? zad_current_city( $id )['name'] : '';
	$svc   = function_exists( 'zad_svc_label' ) ? zad_svc_label( $id ) : get_the_title( $id );
	$n     = count( $pks );
	$o  = '<section class="sec pkgsec pkgsec--' . esc_attr( $style ) . '" id="prices"><div class="wrap"><header class="sec__head"><span class="eyebrow">' . ( 'pest' === $style ? zad_icon( 'shield', 14 ) . ' ' : '' ) . esc_html( $eyebrows[ $style ] ) . '</span><h2>' . esc_html( zad_pk_title( $id ) ) . '</h2>' . ( '' !== $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</header>';
	$o .= '<div class="pkgx pkgx--n' . min( $n, 4 ) . '">';
	foreach ( $pks as $p ) {
		$hot = $p['featured'];
		$o  .= '<article class="pkgx__c' . ( $hot ? ' pkgx__c--hot' : '' ) . '">';
		if ( $hot ) { $o .= '<span class="pkgx__flag">' . esc_html( $flag ) . '</span>'; }
		if ( 'ac' === $style ) { $o .= '<span class="pkgx__ic">' . zad_ticket_icon( zad_pk_icon_for( $p['name'] ), 30 ) . '</span>'; }
		if ( '' !== $p['cat'] ) { $o .= '<small class="pkgx__cat">' . esc_html( $p['cat'] ) . '</small>'; }
		$o  .= '<h3>' . esc_html( $p['name'] ) . '</h3>';
		if ( '' !== $p['desc'] ) { $o .= '<p class="pkgx__d">' . esc_html( $p['desc'] ) . '</p>'; }
		$o  .= zad_pk_price_html( $p );
		if ( '' !== $p['warranty'] ) { $o .= '<div class="pkgx__w">' . ( 'pest' === $style ? zad_icon( 'shield', 16 ) . ' ' : '' ) . 'ضمان: ' . esc_html( $p['warranty'] ) . '</div>'; }
		if ( $p['feat'] ) { $o .= '<ul class="pkgx__f">'; foreach ( $p['feat'] as $f ) { $o .= '<li>' . zad_icon( 'check', 16 ) . '<span>' . esc_html( $f ) . '</span></li>'; } $o .= '</ul>'; }
		$msg = 'السلام عليكم، أبغى باقة ' . $p['name'] . ' — ' . $svc . ( ( '' !== $city && false === mb_strpos( $svc, $city ) ) ? ' — ' . $city : '' ); // the city only when the service name does not already say it
		$at  = zad_wa_attrs( $msg, $id );
		$o  .= '<button type="button" class="pkgx__btn"' . ( $at ?: ' data-open-wizard' ) . '>' . zad_icon( 'whatsapp', 18 ) . ' ' . esc_html( '' !== $p['cta'] ? $p['cta'] : 'اطلب هذه الباقة' ) . '</button></article>';
	}
	$o .= '</div><p class="pkgx__note">' . esc_html( $note ) . '</p>';
	$o .= zad_pk_factors_html( $id );
	return $o . '</div></section>';
}

/** «العوامل التي تحدد السعر» (_zad_factors) as a short numbered list inside the packages section — one price H2 per page (the title here is an H3). */
function zad_pk_factors_html( $id ) {
	$f = array_values( array_filter( (array) get_post_meta( $id, '_zad_factors', true ), function ( $r ) { return is_array( $r ) && ! empty( $r['t'] ); } ) );
	if ( ! $f ) { return ''; }
	$s = function_exists( 'zad_sec' ) ? zad_sec( $id, 'factors' ) : array( 'title' => 'العوامل التي تحدد السعر' );
	$o = '<div class="pkgx__fac"><h3>' . esc_html( '' !== $s['title'] ? $s['title'] : 'العوامل التي تحدد السعر' ) . '</h3><ol>';
	foreach ( array_slice( $f, 0, 6 ) as $r ) {
		$d = trim( (string) ( $r['d'] ?? '' ) );
		if ( mb_strlen( $d, 'UTF-8' ) > 110 ) { $d = rtrim( mb_substr( $d, 0, 108, 'UTF-8' ), " \t.,،" ) . '…'; }
		$o .= '<li><b>' . esc_html( $r['t'] ) . '</b>' . ( '' !== $d ? '<span> — ' . esc_html( $d ) . '</span>' : '' ) . '</li>';
	}
	return $o . '</ol></div>';
}

/* ---------------- editor ---------------- */
function zad_pk_box( $post_id ) {
	$g = function ( $k ) use ( $post_id ) { return (string) get_post_meta( $post_id, '_zad_' . $k, true ); };
	echo '<h4>باقات الأسعار <small>(مصدر الأسعار الوحيد: القسم والهيرو والسكيما)</small></h4>';
	echo '<p class="description">سطر لكل باقة: <code>الاسم | السعر | الوحدة | الوصف القصير | الضمان | ميزة؛ميزة؛ميزة | مميزة (1/0) | نص الزر | الفئة</code> — الفئة اختيارية (منازل / فلل / منشآت…)<br>السعر: <code>349</code> ثابت · <code>250-450</code> من/إلى · <code>من 600</code> يبدأ من · <code>بعد المعاينة</code> بلا رقم. الوحدة اختيارية: ريال، ريال/شهرياً، ريال/زيارة.</p>';
	echo '<p><textarea name="zad[packages]" rows="6" style="width:100%" placeholder="الباقة الأساسية | بعد المعاينة | | رش مركّز لغرفة واحدة | | فحص المراتب؛رش متبقٍّ | 0 | اطلب معاينة&#10;الباقة المتكاملة | 450-650 | ريال | رش + بخار للشقة | حتى سنة | بخار حار؛جلسة متابعة | 1 | اختر هذه الباقة">' . esc_textarea( $g( 'packages' ) ) . '</textarea></p>';
	echo '<div class="zad-grid"><p><label>شكل القسم<select name="zad[packages_style]">';
	foreach ( zad_pk_styles() as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $g( 'packages_style' ) ?: 'auto', $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></label></p><p><label>العنوان (اختياري)<input type="text" name="zad[packages_title]" value="' . esc_attr( $g( 'packages_title' ) ) . '" placeholder="باقات الأسعار"></label></p><p><label>السطر تحت العنوان (اختياري)<input type="text" name="zad[packages_sub]" value="' . esc_attr( $g( 'packages_sub' ) ) . '"></label></p></div>';
}
function zad_pk_save( $post_id, $in ) {
	if ( ! array_key_exists( 'packages', $in ) ) { return; }
	update_post_meta( $post_id, '_zad_packages', sanitize_textarea_field( $in['packages'] ) );
	$st = (string) ( $in['packages_style'] ?? 'auto' );
	update_post_meta( $post_id, '_zad_packages_style', in_array( $st, array( 'pest', 'clean', 'tank', 'ac' ), true ) ? $st : 'auto' );
	update_post_meta( $post_id, '_zad_packages_title', sanitize_text_field( $in['packages_title'] ?? '' ) );
	update_post_meta( $post_id, '_zad_packages_sub', sanitize_text_field( $in['packages_sub'] ?? '' ) );
}
