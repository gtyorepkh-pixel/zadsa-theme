<?php defined( 'ABSPATH' ) || exit;
require_once HS_TOOLS_DIR . 'data/stains.php';

add_shortcode( 'hs_stain_aid', function () {
	$stains = hs_stain_data(); $fabs = hs_stain_fabrics();
	$h  = '<p class="hs-warnbox" role="note">' . esc_html( hs_stain_warning() ) . '</p>';
	$h .= '<div class="hs-pick"><div class="hs-q" data-q="stain"><b class="hs-lg">1. ما نوع البقعة؟</b><div class="hs-grid hs-grid--chips">';
	foreach ( $stains as $id => $s ) { $h .= '<a class="hs-opt" href="#' . esc_attr( $id ) . '" data-v="' . esc_attr( $id ) . '"><span>' . esc_html( $s['name'] ) . '</span></a>'; }
	$h .= '</div></div><div class="hs-q" data-q="fabric" hidden><b class="hs-lg">2. ما القماش أو السطح؟ (اختياري)</b><div class="hs-grid hs-grid--chips">';
	foreach ( $fabs as $id => $f ) { $h .= '<button type="button" class="hs-opt" data-v="' . esc_attr( $id ) . '"><span>' . esc_html( $f[0] ) . '</span></button>'; }
	$h .= '</div></div></div>';

	$h .= '<div class="hs-timer" hidden><b>المؤقت:</b> <output class="hs-clock" aria-live="off">10:00</output> <button type="button" class="hs-btn" data-tstart>ابدأ مؤقت 10 دقائق</button> <button type="button" class="hs-btn" data-treset>إعادة</button></div>';

	// Full guide in HTML (works without JS; JS turns it into the interactive view).
	$h .= '<div class="hs-guide">';
	foreach ( $stains as $id => $s ) {
		$h .= '<section class="hs-stain" id="' . esc_attr( $id ) . '"><h2>' . esc_html( $s['title'] ) . '</h2><p>' . esc_html( $s['intro'] ) . '</p>';
		$h .= '<details open class="hs-do"><summary>افعل الآن</summary><ol class="hs-stepslist">';
		foreach ( $s['steps'] as $st ) { $h .= '<li' . ( $st[1] ? ' data-min="' . (int) $st[1] . '"' : '' ) . '>' . esc_html( $st[0] ) . ( $st[1] ? ' <em class="hs-min">(حوالي ' . (int) $st[1] . ' دقائق)</em>' : '' ) . '</li>'; }
		$h .= '</ol></details><details class="hs-dont"><summary>تجنّب</summary><ul>';
		foreach ( $s['avoid'] as $a ) { $h .= '<li>' . esc_html( $a ) . '</li>'; }
		$h .= '</ul></details>';
		if ( $s['fab'] ) {
			$h .= '<details class="hs-fabnote"><summary>حسب القماش لهذه البقعة</summary>';
			foreach ( $s['fab'] as $fk => $t ) { $h .= '<p data-fab="' . esc_attr( $fk ) . '"><b>' . esc_html( $fabs[ $fk ][0] ) . ':</b> ' . esc_html( $t ) . '</p>'; }
			$h .= '</details>';
		}
		$h .= '<details class="hs-expert"><summary>متى تطلب متخصصاً</summary><p>' . esc_html( $s['expert'] ) . '</p></details></section>';
	}
	$h .= '<section class="hs-fabrics" id="fabrics"><h2>نصائح حسب نوع القماش أو السطح</h2>';
	foreach ( $fabs as $id => $f ) { $h .= '<details data-fab="' . esc_attr( $id ) . '"><summary>' . esc_html( $f[0] ) . '</summary><p>' . esc_html( $f[2] ) . '</p></details>'; }
	$h .= '</section><section class="hs-rules" id="rules"><h2>قواعد عامة لكل البقع</h2><ul>';
	foreach ( hs_stain_rules() as $r ) { $h .= '<li>' . esc_html( $r ) . '</li>'; }
	$h .= '<li>الحرير والصوف والجلد: لا تستعمل المبيض، وتعامل بأقل ماء ممكن، والأفضل تركها لمتخصص.</li></ul></section></div>';

	$h .= '<div class="hs-after" hidden><p><b>البقعة لم تختفِ؟</b> لا تكرر المحاولات بمواد أقوى فقد تثبّتها. اطلب فحصاً وتنظيفاً متخصصاً.</p><button type="button" class="hs-btn hs-btn--main" data-order>اطلب تنظيفاً متخصصاً</button></div>';
	$h .= hs_lead_form( 'اطلب تنظيف البقعة', 'أرسل الطلب عبر واتساب' );
	$h .= '<p class="hs-fine">إرشادات عامة بمواد منزلية شائعة، ونتائجها تختلف حسب القماش ومدة البقعة. اختبر دائماً في مكان مخفي.</p>';
	$d = array( 'stains' => array_map( function ( $s ) { return $s['name']; }, $stains ), 'fabrics' => array_map( function ( $f ) { return $f[0]; }, $fabs ) );
	return hs_wrap( 'stain-aid', 'إسعافات البقع: أول 10 دقائق', $h, $d );
} );
