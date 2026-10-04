<?php defined( 'ABSPATH' ) || exit;
require_once HS_TOOLS_DIR . 'data/pest-methods.php';

add_shortcode( 'hs_pest_method', function () {
	$d = hs_pest_data();
	$S = hs_settings();
	$prices = array();
	foreach ( $d['methods'] as $k => $m ) { $prices[ $k ] = hs_fmt_range( $S['pest'][ $k ]['from'] ?? '', $S['pest'][ $k ]['to'] ?? '' ); }
	$icons = array(
		'roach'    => '<path d="M12 7c-2 0-3 2-3 5s1 6 3 6 3-3 3-6-1-5-3-5zM9 9 5 6M15 9l4-3M9 13H4M15 13h5M9.5 17 6 20M14.5 17l3.5 3M10 5l-1-2M14 5l1-2"/>',
		'bedbug'   => '<ellipse cx="12" cy="13" rx="5" ry="6"/><path d="M12 7V5M7.5 11 4 9.5M16.5 11 20 9.5M7 15l-3 1.5M17 15l3 1.5M9 18.5 7.5 21M15 18.5 16.5 21"/>',
		'ant'      => '<circle cx="12" cy="6" r="2"/><ellipse cx="12" cy="12" rx="2.5" ry="2.5"/><ellipse cx="12" cy="18" rx="3" ry="3"/><path d="M10 5 8 3M14 5l2-2M9.5 12H5M14.5 12H19M10 17l-4 2M14 17l4 2"/>',
		'rodent'   => '<path d="M4 15c0-4 3-7 7-7h3a4 4 0 0 1 4 4v3H4z"/><circle cx="16" cy="11" r=".7"/><path d="M4 15 2 17M8 8 7 5"/>',
		'termite'  => '<ellipse cx="12" cy="9" rx="3" ry="3"/><ellipse cx="12" cy="16" rx="4" ry="4"/><path d="M9 6 7 4M15 6l2-2M8 15H4M16 15h4"/>',
		'mosquito' => '<path d="M12 8v8M12 16l-1 5M9 10 4 6M15 10l5-4M9 12 3 12M15 12h6"/><circle cx="12" cy="7" r="2"/>',
	);
	$h = '<div class="hs-steps" data-step="1">';
	$h .= '<fieldset class="hs-q" data-q="pest"><legend>1. ما الحشرة التي تواجهها؟</legend><div class="hs-grid hs-grid--icons">';
	foreach ( $d['pests'] as $k => $l ) {
		$h .= '<button type="button" class="hs-opt" data-v="' . esc_attr( $k ) . '"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">' . $icons[ $k ] . '</svg><span>' . esc_html( $l ) . '</span></button>';
	}
	$h .= '</div></fieldset>';
	$h .= '<div class="hs-more" hidden>';
	$opts = function ( $q, $title, $list ) {
		$o = '<fieldset class="hs-q" data-q="' . $q . '"><legend>' . esc_html( $title ) . '</legend><div class="hs-grid">';
		foreach ( $list as $v => $l ) { $o .= '<button type="button" class="hs-opt" data-v="' . esc_attr( $v ) . '"><span>' . esc_html( $l ) . '</span></button>'; }
		return $o . '</div></fieldset>';
	};
	$h .= $opts( 'kids', '2. هل يوجد أطفال صغار؟', array( 'yes' => 'نعم', 'no' => 'لا' ) );
	$h .= $opts( 'pets', 'هل يوجد حيوانات أليفة؟', array( 'none' => 'لا', 'catdog' => 'قطط أو كلاب', 'birdfish' => 'طيور أو أسماك' ) );
	$h .= $opts( 'leave', 'كم ساعة تستطيع مغادرة البيت؟', array( '0' => 'لا أستطيع', '4' => 'حتى 4 ساعات', '24' => 'يوم كامل', '72' => 'أكثر من يوم' ) );
	$h .= $opts( 'sens', 'هل يوجد شخص حساس من الروائح أو مريض ربو؟', array( 'yes' => 'نعم', 'no' => 'لا' ) );
	$h .= $opts( 'sev', 'ما حجم المشكلة؟', array( '1' => 'أرى حشرة أحياناً', '2' => 'أراها يومياً', '3' => 'أراها في النهار وبأعداد كبيرة' ) );
	$h .= '<button type="button" class="hs-btn hs-btn--main" data-go disabled>اعرض الطريقة الأنسب</button></div></div>';
	$h .= '<div class="hs-result" aria-live="polite"></div>';
	$h .= hs_lead_form( 'اطلب هذه الطريقة', 'أرسل الطلب عبر واتساب' );
	$h .= '<p class="hs-fine">الأسعار تقديرية — السعر النهائي بعد المعاينة. المعلومات إرشادية ولا تغني عن معاينة فني مرخّص.</p>';
	return hs_wrap( 'pest-method', 'أي طريقة مكافحة تناسبك؟', $h, array( 'pests' => $d['pests'], 'methods' => $d['methods'], 'matrix' => $d['matrix'], 'tips' => $d['tips'], 'prices' => $prices ) );
} );
