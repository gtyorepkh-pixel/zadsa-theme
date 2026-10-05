<?php
/**
 * Section headings of a service page: neutral, non-repeating defaults built from the service's short name ({svc}),
 * each overridable per page (editor panel «عناوين الأقسام» → zad[sec][key][eyebrow|title|lead]; empty = default).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** key => array( editor label, default eyebrow, default title, default lead ). {svc} / {brand} are replaced. */
/** Short company name for «ضمان …»: the theme's «الاسم المختصر» (zad_alt_name), else the provider name, else ''. */
function zad_brand_short() {
	$b = trim( (string) zad_opt( 'zad_alt_name', '' ) );
	return '' !== $b ? $b : trim( (string) zad_opt( 'zad_provider', '' ) );
}

function zad_sec_defaults() {
	$brand = zad_brand_short();
	return array(
		'why'       => array( 'لماذا نحن',          'لماذا نحن',        'ما الذي يجعل خدمة {svc} تدوم؟', '' ),
		'subs'      => array( 'أنواع الخدمة',       'خدماتنا',          'أساليب {svc} التي نستخدمها', '' ),
		'steps'     => array( 'خطوات التنفيذ',      'خطوة بخطوة',       'كيف تتم زيارة {svc} خطوة بخطوة', '' ),
		'factors'   => array( 'عوامل السعر',        'تسعير شفاف',       'ما الذي يحدد سعر {svc}؟', '' ),
		'prices'    => array( 'قائمة الأسعار',      'قائمة الأسعار',    'أسعار {svc} حسب نوع المكان', '' ),
		'spec'      => array( 'البطاقة الفنية',     'نظرة سريعة',       'المواد والمدة والضمان باختصار', '' ),
		'signs'     => array( 'العلامات',           'علامات الإصابة',   'علامات تخبرك أنك تحتاج {svc}', 'إذا لاحظت أياً منها، تواصل معنا لفحص مجاني.' ),
		'harms'     => array( 'الأضرار',            'الأضرار المحتملة', 'ماذا يحدث إذا أجّلت {svc}؟', '' ),
		'safety'    => array( 'الأمان',             'الأمان أولاً',     'كيف نحمي أسرتك أثناء {svc}؟', '' ),
		'warranty'  => array( 'الضمان',             'الضمان',           $brand ? 'ماذا يشمل ضمان {brand} على {svc}؟' : 'ماذا يشمل ضماننا على {svc}؟', '' ),
		'gallery'   => array( 'معرض الصور',         'من أعمالنا',       'صور من زيارات {svc}', '' ),
		'faq'       => array( 'الأسئلة الشائعة',    'الأسئلة الشائعة',  'أسئلة يطرحها عملاؤنا عن {svc}', '' ),
		'related'   => array( 'ذات صلة',            'قد يهمك أيضاً',    'خدمات أخرى قد تحتاجها', '' ),
		'final_cta' => array( 'الطلب الأخير',       'اطلب الآن',        'احجز معاينة مجانية {l_svc} اليوم', '' ),
	);
}

/** Short name of the service for {svc}: Yoast breadcrumb title, else the page's own short name, else the title without «شركة» and numbers. */
function zad_svc_label( $id ) {
	static $memo = array();
	if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
	$t = '';
	foreach ( array( '_yoast_wpseo_bctitle', '_zad_breadcrumb_label', '_zad_base_name' ) as $k ) {
		$v = trim( wp_strip_all_tags( (string) get_post_meta( $id, $k, true ) ) );
		if ( '' !== $v ) { $t = zad_strip_phones( $v ); break; }
	}
	if ( '' === $t ) {
		$t = function_exists( 'zad_bc_clean_label' ) ? zad_bc_clean_label( get_the_title( $id ) ) : wp_strip_all_tags( get_the_title( $id ) );
		$t = zad_strip_phones( $t );
		$t = preg_replace( '/(?<![\p{L}\p{N}])(?:شركة|شركات|مؤسسة)(?![\p{L}\p{N}])/u', ' ', $t );
		$t = preg_replace( '/[0-9٠-٩]+/u', ' ', $t );
		$t = preg_replace( '/^[\s\-–—|،,:·]+|[\s\-–—|،,:·]+$/u', '', preg_replace( '/\s+/u', ' ', $t ) ); // not trim(): its byte list would corrupt Arabic
		if ( '' === $t ) { $t = trim( wp_strip_all_tags( get_the_title( $id ) ) ); }
	}
	return $memo[ $id ] = $t;
}

function zad_sec_fill( $text, $id ) {
	$svc  = zad_svc_label( $id );
	$l    = 0 === mb_strpos( $svc, 'ال' ) ? 'لل' . mb_substr( $svc, 2 ) : 'ل' . $svc; // لـ + الصراصير = للصراصير ، لـ + مكافحة = لمكافحة
	$text = preg_replace( '/(?<![\p{L}])لـ?\s*\{svc\}/u', '{l_svc}', (string) $text ); // «لـ {svc}» written by hand gets the same grammar
	$t    = str_replace( array( '{l_svc}', '{svc}', '{brand}' ), array( $l, $svc, zad_brand_short() ), $text );
	return preg_replace( '/(?<![\p{L}])خدمة\s+خدمة(?![\p{L}])/u', 'خدمة', $t ); // «خدمة خدمة تسليك» when the name already starts with «خدمة»
}

/** Heading parts to print: the page's own value when filled, else the default. */
function zad_sec( $id, $key ) {
	$d   = zad_sec_defaults();
	$def = $d[ $key ] ?? array( '', '', '', '' );
	$own = (array) get_post_meta( $id, '_zad_sec', true );
	$own = isset( $own[ $key ] ) && is_array( $own[ $key ] ) ? $own[ $key ] : array();
	$get = function ( $f, $i ) use ( $own, $def, $id ) { $v = trim( (string) ( $own[ $f ] ?? '' ) ); return zad_sec_fill( '' !== $v ? $v : $def[ $i ], $id ); };
	return array( 'eyebrow' => $get( 'eyebrow', 1 ), 'title' => $get( 'title', 2 ), 'lead' => $get( 'lead', 3 ), 'custom_title' => '' !== trim( (string) ( $own['title'] ?? '' ) ), 'custom_lead' => '' !== trim( (string) ( $own['lead'] ?? '' ) ) );
}

/** The usual <header class="sec__head">; $icon = icon name placed before the eyebrow text (spec / safety / warranty). */
function zad_sec_head( $id, $key, $icon = '' ) {
	$s = zad_sec( $id, $key );
	$o = '<header class="sec__head">';
	if ( '' !== $s['eyebrow'] ) { $o .= '<span class="eyebrow">' . ( $icon ? zad_icon( $icon, 14 ) . ' ' : '' ) . esc_html( $s['eyebrow'] ) . '</span>'; }
	if ( '' !== $s['title'] ) { $o .= '<h2>' . esc_html( $s['title'] ) . '</h2>'; }
	if ( '' !== $s['lead'] ) { $o .= '<p>' . esc_html( $s['lead'] ) . '</p>'; }
	return $o . '</header>';
}

/* ---------- editor ---------- */
function zad_sec_box( $post_id ) {
	$own = (array) get_post_meta( $post_id, '_zad_sec', true );
	echo '<details class="zad-secbox" style="margin:16px 0"><summary style="cursor:pointer;font-weight:700">عناوين الأقسام <small>(اختياري — فارغ = العنوان الافتراضي، وفيه {svc} = اسم الخدمة المختصر)</small></summary>';
	echo '<p class="description">الاسم المختصر: عنوان مسار التنقل في Yoast إن وُجد، وإلا اسم الخدمة بلا «شركة» وبلا أرقام: <b>' . esc_html( zad_svc_label( $post_id ) ) . '</b></p>';
	echo '<table class="widefat striped"><thead><tr><th>القسم</th><th>الوسم الصغير</th><th>العنوان</th><th>السطر تحت العنوان</th></tr></thead><tbody>';
	foreach ( zad_sec_defaults() as $k => $d ) {
		$v = isset( $own[ $k ] ) && is_array( $own[ $k ] ) ? $own[ $k ] : array();
		echo '<tr><th>' . esc_html( $d[0] ) . '</th>';
		foreach ( array( 'eyebrow' => 1, 'title' => 2, 'lead' => 3 ) as $f => $i ) {
			echo '<td><input type="text" style="width:100%" name="zad[sec][' . esc_attr( $k ) . '][' . esc_attr( $f ) . ']" value="' . esc_attr( (string) ( $v[ $f ] ?? '' ) ) . '" placeholder="' . esc_attr( zad_sec_fill( $d[ $i ], $post_id ) ) . '"></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></details>';
}
function zad_sec_save( $post_id, $in ) {
	if ( ! isset( $in['sec'] ) || ! is_array( $in['sec'] ) ) { return; }
	$out = array();
	foreach ( array_keys( zad_sec_defaults() ) as $k ) {
		foreach ( array( 'eyebrow', 'title', 'lead' ) as $f ) {
			$v = isset( $in['sec'][ $k ][ $f ] ) ? sanitize_text_field( $in['sec'][ $k ][ $f ] ) : '';
			if ( '' !== $v ) { $out[ $k ][ $f ] = $v; }
		}
	}
	update_post_meta( $post_id, '_zad_sec', $out );
}
