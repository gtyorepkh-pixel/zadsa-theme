<?php defined( 'ABSPATH' ) || exit;
/** Settings page «أدوات الموقع» — one option: hs_tools_settings. */

add_action( 'admin_menu', function () {
	add_menu_page( 'أدوات الموقع', 'أدوات الموقع', 'manage_options', 'hs-tools', 'hs_settings_page', 'dashicons-hammer', 59 );
} );
add_action( 'admin_init', function () {
	register_setting( 'hs_tools', 'hs_tools_settings', array( 'type' => 'array', 'sanitize_callback' => 'hs_sanitize_settings', 'default' => hs_defaults() ) );
} );

function hs_sanitize_settings( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = hs_defaults();
	$out['whatsapp'] = sanitize_text_field( $in['whatsapp'] ?? '' );
	$out['email']    = is_email( $in['email'] ?? '' ) ? sanitize_email( $in['email'] ) : '';
	$out['cities']   = sanitize_textarea_field( $in['cities'] ?? '' );
	foreach ( array_keys( hs_pest_method_names() ) as $m ) {
		$out['pest'][ $m ] = array( 'from' => hs_num( $in['pest'][ $m ]['from'] ?? '' ), 'to' => hs_num( $in['pest'][ $m ]['to'] ?? '' ) );
	}
	foreach ( array_keys( hs_bundle_services() ) as $s ) {
		foreach ( array_keys( hs_sizes() ) as $z ) {
			$out['bundle'][ $s ][ $z ] = hs_num( $in['bundle'][ $s ][ $z ] ?? '' );
		}
	}
	foreach ( array( 'floor_fee', 'assemble_fee', 'pack_fee' ) as $k ) {
		$out[ $k ] = hs_num( $in[ $k ] ?? '' );
	}
	foreach ( array( 'disc2', 'disc3', 'disc4' ) as $k ) {
		$v = isset( $in[ $k ] ) && '' !== $in[ $k ] ? (int) $in[ $k ] : 0;
		$out[ $k ] = max( 0, min( 90, $v ) );
	}
	return $out;
}

/** Create the three tool pages as drafts (button on the settings page). */
add_action( 'admin_post_hs_make_pages', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مصرّح' ); }
	check_admin_referer( 'hs_make_pages' );
	$pages = array(
		'pest-control-method' => array( 'أي طريقة مكافحة تناسبك؟', '[hs_pest_method]', 'تختلف طرق مكافحة الحشرات في الرائحة ومدة مغادرة البيت وملاءمتها للأطفال والحيوانات الأليفة. أجب عن أسئلة قصيرة لنقترح عليك الأنسب مع مقارنة واضحة.' ),
		'moving-bundle'       => array( 'باقة الانتقال: تنظيف ونقل عفش وتعقيم', '[hs_moving_bundle]', 'اجمع تنظيف الشقة القديمة ونقل العفش وتنظيف وتعقيم الشقة الجديدة والرش الوقائي في باقة واحدة، واعرف السعر التقديري والخصم قبل أن تطلب.' ),
		'stain-removal-guide' => array( 'إسعافات البقع: أول 10 دقائق', '[hs_stain_aid]', 'ما تفعله في الدقائق العشر الأولى يحدد إن كانت البقعة ستزول أم تثبت. اختر نوع البقعة والقماش لتعرف ماذا تفعل وماذا تتجنب.' ),
	);
	foreach ( $pages as $slug => $p ) {
		if ( get_page_by_path( $slug ) ) { continue; }
		wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $p[0], 'post_content' => '<p>' . $p[2] . '</p>' . "\n\n" . $p[1] ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=hs-tools&hs_pages=1' ) );
	exit;
} );

function hs_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = hs_settings();
	$n = 'hs_tools_settings';
	$num = function ( $name, $val ) { return '<input type="number" min="0" step="1" class="small-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">'; };
	echo '<div class="wrap" dir="rtl"><h1>أدوات الموقع</h1>';
	if ( isset( $_GET['hs_pages'] ) ) { echo '<div class="notice notice-success"><p>تم إنشاء الصفحات كمسودات (إن لم تكن موجودة). راجعها وانشرها بنفسك.</p></div>'; }
	echo '<p>كل الأسعار تقديرية. أي حقل تتركه فارغاً يظهر للزائر «يحدد بعد المعاينة».</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'hs_tools' );

	echo '<h2>عام</h2><table class="form-table">';
	echo '<tr><th>رقم واتساب الشركة</th><td><input type="text" class="regular-text" name="' . $n . '[whatsapp]" value="' . esc_attr( $s['whatsapp'] ) . '" placeholder="05XXXXXXXX"><p class="description">إن تُرك فارغاً يُستخدم رقم الواتساب في خيارات الثيم.</p></td></tr>';
	echo '<tr><th>بريد إشعارات الطلبات</th><td><input type="email" class="regular-text" name="' . $n . '[email]" value="' . esc_attr( $s['email'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"></td></tr>';
	echo '<tr><th>المدن التي تخدمها</th><td><textarea rows="4" class="large-text" name="' . $n . '[cities]" placeholder="الرياض&#10;جدة">' . esc_textarea( $s['cities'] ) . '</textarea><p class="description">مدينة في كل سطر (اقتراحات في أداة الانتقال). إن تُركت فارغة يكتب الزائر المدينة بنفسه.</p></td></tr></table>';

	echo '<h2>أسعار طرق المكافحة (ر.س)</h2><table class="widefat striped" style="max-width:520px"><thead><tr><th>الطريقة</th><th>السعر من</th><th>السعر إلى</th></tr></thead><tbody>';
	foreach ( hs_pest_method_names() as $k => $label ) {
		echo '<tr><td>' . esc_html( $label ) . '</td><td>' . $num( "{$n}[pest][{$k}][from]", $s['pest'][ $k ]['from'] ?? '' ) . '</td><td>' . $num( "{$n}[pest][{$k}][to]", $s['pest'][ $k ]['to'] ?? '' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>أسعار باقة الانتقال (ر.س) حسب حجم الشقة</h2><table class="widefat striped" style="max-width:980px"><thead><tr><th>الخدمة</th>';
	foreach ( hs_sizes() as $z => $zl ) { echo '<th>' . esc_html( $zl ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( hs_bundle_services() as $k => $label ) {
		echo '<tr><td>' . esc_html( $label ) . '</td>';
		foreach ( array_keys( hs_sizes() ) as $z ) { echo '<td>' . $num( "{$n}[bundle][{$k}][{$z}]", $s['bundle'][ $k ][ $z ] ?? '' ) . '</td>'; }
		echo '</tr>';
	}
	echo '</tbody></table><p class="description">سعر النقل يُحسب حسب حجم الشقة القديمة، وتنظيف/تعقيم/رش الشقة الجديدة حسب حجمها.</p>';

	echo '<table class="form-table">';
	echo '<tr><th>رسم كل دور بدون مصعد (نقل العفش)</th><td>' . $num( "{$n}[floor_fee]", $s['floor_fee'] ) . ' ر.س لكل دور</td></tr>';
	echo '<tr><th>رسم الفك والتركيب (اختياري)</th><td>' . $num( "{$n}[assemble_fee]", $s['assemble_fee'] ) . ' ر.س</td></tr>';
	echo '<tr><th>رسم التغليف (اختياري)</th><td>' . $num( "{$n}[pack_fee]", $s['pack_fee'] ) . ' ر.س</td></tr>';
	echo '<tr><th>خصم الباقة</th><td>خدمتان ' . $num( "{$n}[disc2]", $s['disc2'] ) . '% &nbsp; 3 خدمات ' . $num( "{$n}[disc3]", $s['disc3'] ) . '% &nbsp; 4 خدمات ' . $num( "{$n}[disc4]", $s['disc4'] ) . '%</td></tr></table>';
	submit_button( 'حفظ الإعدادات' );
	echo '</form><hr><h2>صفحات الأدوات</h2><p>ينشئ ثلاث صفحات كمسودات تحتوي على الـ shortcodes: <code>[hs_pest_method]</code> <code>[hs_moving_bundle]</code> <code>[hs_stain_aid]</code></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hs_make_pages">';
	wp_nonce_field( 'hs_make_pages' );
	submit_button( 'إنشاء الصفحات كمسودات', 'secondary' );
	echo '</form></div>';
}
