<?php defined( 'ABSPATH' ) || exit;

add_shortcode( 'hs_moving_bundle', function () {
	$S = hs_settings();
	$cities = hs_cities();
	$prices = array();
	foreach ( array_keys( hs_bundle_services() ) as $k ) { foreach ( array_keys( hs_sizes() ) as $z ) { $prices[ $k ][ $z ] = hs_num( $S['bundle'][ $k ][ $z ] ?? '' ); } }
	$data = array(
		'sizes' => hs_sizes(), 'services' => hs_bundle_services(), 'prices' => $prices,
		'floor_fee' => hs_num( $S['floor_fee'] ), 'assemble_fee' => hs_num( $S['assemble_fee'] ), 'pack_fee' => hs_num( $S['pack_fee'] ),
		'disc' => array( 2 => (int) $S['disc2'], 3 => (int) $S['disc3'], 4 => (int) $S['disc4'] ),
	);
	$place = function ( $p, $title ) use ( $cities ) {
		$h  = '<fieldset class="hs-place" data-place="' . $p . '"><legend>' . esc_html( $title ) . '</legend>';
		$h .= '<div class="hs-field"><label>المدينة<input type="text" name="' . $p . '_city" maxlength="60"' . ( $cities ? ' list="hs-cities"' : '' ) . ' autocomplete="off" required></label></div>';
		$h .= '<div class="hs-field"><label>الحي<input type="text" name="' . $p . '_hood" maxlength="60"></label></div>';
		$h .= '<div class="hs-field"><label>الحجم<select name="' . $p . '_size" required><option value="">اختر الحجم</option>';
		foreach ( hs_sizes() as $k => $l ) { $h .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>'; }
		$h .= '</select></label></div><div class="hs-field"><label>الدور<select name="' . $p . '_floor">';
		for ( $i = 0; $i <= 10; $i++ ) { $h .= '<option value="' . $i . '">' . ( 0 === $i ? 'الأرضي' : ( 10 === $i ? '10 فأكثر' : $i ) ) . '</option>'; }
		$h .= '</select></label></div><label class="hs-check"><input type="checkbox" name="' . $p . '_lift"> يوجد مصعد</label></fieldset>';
		return $h;
	};
	$svc_hint = array( 'clean_old' => 'لتسليمها نظيفة واسترداد التأمين', 'move' => 'من الشقة القديمة إلى الجديدة داخل المدينة', 'clean_new' => 'جاهزة للسكن قبل دخول العفش', 'spray' => 'أسهل وقت للرش قبل دخول العفش' );
	$h  = '<ol class="hs-progress" aria-label="خطوات الباقة"><li class="is-on">الشقتان</li><li>الخدمات</li><li>الملخص</li></ol>';
	if ( $cities ) { $h .= '<datalist id="hs-cities">'; foreach ( $cities as $c ) { $h .= '<option value="' . esc_attr( $c ) . '">'; } $h .= '</datalist>'; }
	$h .= '<section class="hs-step" data-s="1"><div class="hs-two">' . $place( 'old', 'الشقة القديمة' ) . $place( 'new', 'الشقة الجديدة' ) . '</div>';
	$h .= '<div class="hs-field hs-date"><label>تاريخ الانتقال المتوقع<input type="date" name="date"></label></div><p class="hs-note" data-inter hidden>النقل بين المدن يُسعّر بعد التواصل.</p>';
	$h .= '<p class="hs-err" role="alert" hidden></p><div class="hs-nav"><button type="button" class="hs-btn hs-btn--main" data-next>التالي: اختر الخدمات</button></div></section>';
	$h .= '<section class="hs-step" data-s="2" hidden><p>اختر الخدمات التي تريدها. كلما زادت الخدمات زاد خصم الباقة.</p><div class="hs-svcs">';
	foreach ( hs_bundle_services() as $k => $l ) {
		$on = 'spray' !== $k;
		$h .= '<label class="hs-svc"><input type="checkbox" name="svc" value="' . $k . '"' . ( $on ? ' checked' : '' ) . '><span><b>' . esc_html( $l ) . '</b><small>' . esc_html( $svc_hint[ $k ] ) . '</small></span></label>';
		if ( 'move' === $k ) { $h .= '<div class="hs-extras"><label class="hs-check"><input type="checkbox" name="assemble"> فك وتركيب الأثاث</label><label class="hs-check"><input type="checkbox" name="pack"> تغليف</label></div>'; }
	}
	$h .= '</div><p class="hs-err" role="alert" hidden></p><div class="hs-nav"><button type="button" class="hs-btn" data-prev>رجوع</button><button type="button" class="hs-btn hs-btn--main" data-next>التالي: الملخص</button></div></section>';
	$h .= '<section class="hs-step" data-s="3" hidden aria-live="polite"><div class="hs-sum"></div><div class="hs-nav"><button type="button" class="hs-btn" data-prev>رجوع وتعديل</button><button type="button" class="hs-btn hs-btn--main" data-order>اطلب الباقة</button></div>' . hs_lead_form( 'أرسل طلب الباقة', 'أرسل الطلب عبر واتساب', true, false ) . '</section>';
	$h .= '<p class="hs-fine">سعر تقديري - السعر النهائي بعد المعاينة.</p>';
	return hs_wrap( 'moving-bundle', 'باقة الانتقال: تنظيف ونقل عفش وتعقيم', $h, $data );
} );
