<?php defined( 'ABSPATH' ) || exit;
/** Content consistency diagnostics. Suggestions do not fabricate facts or change indexing. */
function zad_seo_review_fingerprint( $id ) {
	$p = get_post( $id ); if ( ! $p ) { return ''; }
	$data = array( $p->post_title, $p->post_content, $p->post_excerpt );
	foreach ( array( '_zad_packages', '_zad_prices', '_zad_specs', '_zad_methods', '_zad_faq', '_zad_wk_result', '_zad_wk_inspection', '_zad_wk_video_url' ) as $key ) { $data[ $key ] = get_post_meta( $id, $key, true ); }
	return hash( 'sha256', wp_json_encode( $data ) );
}
function zad_seo_quality_issues( $id ) {
	$p = get_post( $id ); if ( ! $p ) { return array(); } $out = array();
	$pk = zad_parse_packages( get_post_meta( $id, '_zad_packages', true ) );
	foreach ( $pk as $row ) {
		$price = zad_price_parse( $row['price'] );
		if ( 'range' === $price['kind'] && $price['max'] < $price['min'] ) { $out[] = 'راجع ترتيب حدّي السعر في باقة: ' . $row['name']; }
		if ( ! empty( $row['warranty'] ) && ! zad_pk_warranty( $row['warranty'] ) && preg_match( '/\d|[٠-٩]/u', $row['warranty'] ) ) { $out[] = 'ضمان «' . $row['name'] . '» متغير؛ السكيما تحتفظ بالنص دون مدة ثابتة. فصل الباقات يوضح المقارنة.'; }
	}
	$faq = array_filter( (array) get_post_meta( $id, '_zad_faq', true ), 'is_array' ); $seen = array();
	foreach ( $faq as $f ) { $q = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $f['q'] ?? $f['t'] ?? $f[0] ?? '' ) ) ); if ( '' === $q ) { continue; } if ( isset( $seen[ $q ] ) ) { $out[] = 'سؤال مكرر: ' . $q; } $seen[ $q ] = true; }
	$review = zad_schema_review( $id ); $saved = (string) get_post_meta( $id, '_zad_review_fingerprint', true );
	if ( $review && $saved && ! hash_equals( $saved, zad_seo_review_fingerprint( $id ) ) ) { $out[] = 'تغير المحتوى أو بيانات الخدمة منذ المراجعة المسجلة؛ راجعها قبل اعتماد مراجعة جديدة.'; }
	if ( 'zad_work' === $p->post_type ) {
		if ( zad_opt( 'zad_work_smart_noindex', true ) && count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $p->post_content ) ), -1, PREG_SPLIT_NO_EMPTY ) ) < 60 ) { $out[] = 'وصف العمل قصير؛ أضف ما يفيد الزائر عن المشكلة والتنفيذ والنتيجة إذا كانت هذه التفاصيل متاحة. لا تتغير الفهرسة تلقائياً.'; }
		$v = zad_wk_video( $id );
		if ( $v['kind'] && ( ! $v['poster'] || ! $v['upload_iso'] ) ) { $out[] = 'الفيديو يحتاج صورة معاينة وتاريخ رفع فعلي حتى تخرج بياناته المنظمة.'; }
	}
	if ( zad_opt( 'zad_img_all_ours', false ) && ! zad_img_license_url() ) { $out[] = 'ملكية الصور مفعلة؛ اختر صفحة منشورة توضّح حقوق الاستخدام من إعدادات السيو.'; }
	$canonical = zad_seo_meta( $id, 'canonical' ); if ( $canonical && untrailingslashit( $canonical ) !== untrailingslashit( get_permalink( $id ) ) ) { $out[] = 'الرابط الأساسي يشير إلى صفحة أخرى؛ هذه الصفحة تُستبعد من خرائط الثيم.'; }
	return array_values( array_unique( $out ) );
}
function zad_seo_quality_metabox( $post ) {
	$issues = zad_seo_quality_issues( $post->ID );
	echo '<p>فحص البيانات المحفوظة؛ التعديلات في الحقول تحتاج حفظ المسودة لإعادة فحصها. هذه ملاحظات واضحة وليست درجة ترتيب.</p><div data-zad-quality-live></div>';
	if ( ! $issues ) { echo '<p>لم يظهر تعارض في الفحوص المتاحة لهذه الصفحة.</p>'; }
	else { echo '<ul>'; foreach ( $issues as $issue ) { echo '<li>' . esc_html( $issue ) . '</li>'; } echo '</ul>'; }
}
add_action( 'add_meta_boxes', function () {
 foreach ( array_unique( array_merge( array( 'post', 'page', 'zad_work' ), zad_faq_types(), zad_article_types() ) ) as $type ) {
  if ( in_array( $type, zad_service_types(), true ) ) { continue; }
  add_meta_box( 'zad-seo-quality', 'فحص اتساق المحتوى والسيو', 'zad_seo_quality_metabox', $type, 'side' );
 }
} );
/** Capture the old review before the ordinary metabox saver writes submitted values. */
function zad_seo_review_previous( $id, $capture = false ) {
 static $values = array();
 if ( $capture || ! isset( $values[ $id ] ) ) { $values[ $id ] = array( 'date' => (string) get_post_meta( $id, '_zad_reviewed_date', true ), 'user' => absint( get_post_meta( $id, '_zad_reviewed_by', true ) ) ); }
 return $values[ $id ];
}
function zad_seo_review_should_record( $previous, $date, $by, $confirm ) {
 return $confirm || ( $previous['date'] ?? '' ) !== $date || (int) ( $previous['user'] ?? 0 ) !== $by;
}
add_action( 'save_post', function ( $id ) { zad_seo_review_previous( $id, true ); }, 1 );
add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['zad_schema_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_schema_review_nonce'] ) ), 'zad_schema_review' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
	$date = sanitize_text_field( wp_unslash( $_POST['zad_reviewed_date'] ?? '' ) ); $by = absint( $_POST['zad_reviewed_by'] ?? 0 );
	if ( zad_schema_review_date_valid( $date ) && $by && get_userdata( $by ) ) {
		$previous = zad_seo_review_previous( $id );
		if ( zad_seo_review_should_record( $previous, $date, $by, ! empty( $_POST['zad_review_confirm'] ) ) ) {
			update_post_meta( $id, '_zad_review_fingerprint', zad_seo_review_fingerprint( $id ) );
			update_post_meta( $id, '_zad_review_record', array( 'date' => $date, 'user' => $by ) );
		}
	} elseif ( '' === $date && ! $by ) { delete_post_meta( $id, '_zad_review_fingerprint' ); delete_post_meta( $id, '_zad_review_record' ); }
}, 110 );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { wp_enqueue_script( 'zad-seo-quality', ZAD_THEME_URI . 'assets/js/zad-seo-quality.js', array(), ZAD_VERSION, true ); }
} );
