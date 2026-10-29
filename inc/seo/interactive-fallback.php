<?php defined( 'ABSPATH' ) || exit;
/** Read-only result calculation. No leads, notifications or post updates are created here. */
function zad_ix_questions( $d ) {
 $qs = array();
 foreach ( zad_lines( $d['questions'] ?? '' ) as $line ) {
  $p = array_map( 'trim', explode( '||', $line ) ); if ( count( $p ) < 3 ) { continue; }
  $options = array(); foreach ( preg_split( '/[;؛]/u', $p[2] ) as $item ) { $v = array_map( 'trim', explode( '::', $item ) ); if ( '' !== $v[0] ) { $options[] = array( $v[0], (int) ( $v[1] ?? 0 ) ); } }
  if ( $options ) { $qs[] = array( $p[0], 'multi' === $p[1] ? 'multi' : 'single', $options ); }
 }
 return $qs;
}
function zad_ix_result_data( $id, $answers ) {
 $ix = zad_ix_get( $id ); $d = $ix['wiz'] ?? array(); $score = 0; $rows = array();
 foreach ( zad_ix_questions( $d ) as $i => $q ) {
  $selected = array_values( array_unique( array_map( 'absint', array_filter( (array) ( $answers[ 'q' . $i ] ?? array() ), 'is_scalar' ) ) ) );
  if ( 'multi' !== $q[1] ) { $selected = array_slice( $selected, 0, 1 ); }
  $labels = array(); foreach ( $selected as $key ) { if ( isset( $q[2][ $key ] ) ) { $score += $q[2][ $key ][1]; $labels[] = $q[2][ $key ][0]; } }
  $rows[] = array( $q[0], implode( '، ', $labels ) );
 }
 $tiers = array(); foreach ( zad_lines( $d['tiers'] ?? '' ) as $line ) { $v = array_pad( array_map( 'trim', explode( '|', $line ) ), 4, '' ); $tiers[] = array( 'min' => (int) $v[0], 'title' => $v[1], 'text' => $v[2], 'plan' => array_filter( array_map( 'trim', preg_split( '/[;؛]/u', $v[3] ) ) ) ); }
 usort( $tiers, function ( $a, $b ) { return $a['min'] <=> $b['min']; } ); $tier = $tiers[0] ?? array( 'title' => '', 'text' => '', 'plan' => array() );
 foreach ( $tiers as $t ) { if ( $score >= $t['min'] ) { $tier = $t; } }
 return array( 'score' => $score, 'tier' => $tier, 'answers' => $rows );
}
function zad_result_document( $title, $body, $back ) {
	if ( wp_parse_url( $back, PHP_URL_HOST ) !== wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) { $back = home_url( '/' ); }
	nocache_headers(); header( 'X-Robots-Tag: noindex, follow' ); header( 'Content-Type: text/html; charset=UTF-8' );
	echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,follow"><title>' . esc_html( $title ) . '</title><link rel="stylesheet" href="' . esc_url( ZAD_THEME_URI . 'assets/css/zad.css' ) . '"></head><body><main class="wrap sec" style="max-width:800px"><h1>' . esc_html( $title ) . '</h1>' . $body . '<p><a class="btn btn--primary" href="' . esc_url( $back ) . '">العودة للصفحة</a></p></main></body></html>'; exit;
}
function zad_ix_result_request() {
	$id = is_scalar( $_POST['post_id'] ?? 0 ) ? absint( $_POST['post_id'] ?? 0 ) : 0; $post = get_post( $id );
	if ( ! $post || ( ( 'publish' !== $post->post_status || $post->post_password ) && ! current_user_can( 'edit_post', $id ) ) ) { wp_die( 'غير متاح', '', array( 'response' => 403 ) ); }
	$result = zad_ix_result_data( $id, wp_unslash( $_POST ) );
	foreach ( $result['answers'] as $answer ) { if ( '' === $answer[1] ) { zad_result_document( 'أكمل إجابات الفحص', '<p>أجب عن كل سؤال قبل عرض النتيجة.</p>', get_permalink( $id ) ); } }
	$body = '<h2>' . esc_html( $result['tier']['title'] ) . '</h2><p>' . esc_html( $result['tier']['text'] ) . '</p><ol>';
	foreach ( $result['tier']['plan'] as $item ) { $body .= '<li>' . esc_html( $item ) . '</li>'; }
	$body .= '</ol><h2>إجاباتك</h2><ul>'; foreach ( $result['answers'] as $answer ) { $body .= '<li>' . esc_html( $answer[0] . ': ' . $answer[1] ) . '</li>'; }
	$body .= '</ul><p>خطة مبدئية وليست عرض سعر؛ يحدد الفني الخطة النهائية والسعر بعد المعاينة.</p>';
	zad_result_document( 'نتيجة الفحص الإرشادي', $body, get_permalink( $id ) );
}
add_action( 'admin_post_zad_ix_result', 'zad_ix_result_request' );
add_action( 'admin_post_nopriv_zad_ix_result', 'zad_ix_result_request' );

function hs_pest_rank( $answers ) {
	$answers = array_filter( (array) $answers, 'is_scalar' );
	$d = hs_pest_data(); $pest = $answers['pest'] ?? ''; $out = array();
	foreach ( $d['matrix'][ $pest ] ?? array() as $i => $row ) {
		$m = $d['methods'][ $row[0] ]; $severity = (int) ( $answers['sev'] ?? 0 );
		if ( 'severe_only' === ( $row[1] ?? '' ) && $severity < 3 ) { continue; }
		$score = 100 - $i * 10;
		if ( 'yes' === ( $answers['sens'] ?? '' ) && $m['smell'] >= 2 ) { $score -= 30; }
		$children_pets = 'yes' === ( $answers['kids'] ?? '' ) || 'none' !== ( $answers['pets'] ?? 'none' );
		if ( $children_pets && 'low' === $m['kids_pets'] ) { $score -= 30; } elseif ( $children_pets && 'medium' === $m['kids_pets'] ) { $score -= 10; }
		if ( 'birdfish' === ( $answers['pets'] ?? '' ) && $m['smell'] >= 1 ) { $score -= 10; }
		if ( 3 === $severity ) { $score += ( 4 - $m['speed_rank'] ) * 8; } elseif ( 1 === $severity ) { $score += $m['speed_rank'] * 3; }
		$out[] = array( 'key' => $row[0], 'method' => $m, 'score' => $score, 'unavailable' => $m['leave_hours'][0] > (int) ( $answers['leave'] ?? 0 ) );
	}
	usort( $out, function ( $a, $b ) { return (int) $a['unavailable'] <=> (int) $b['unavailable'] ?: $b['score'] <=> $a['score']; } );
	return $out;
}
function hs_pest_html_guide( $keys = null ) {
	$d = hs_pest_data(); $settings = hs_settings(); $html = '<div class="hs-tablewrap"><table class="hs-cmp"><thead><tr><th>الطريقة</th><th>شرح الطريقة</th><th>التنبيهات</th><th>السعر التقديري</th></tr></thead><tbody>';
	foreach ( $d['methods'] as $key => $method ) {
		if ( null !== $keys && ! in_array( $key, $keys, true ) ) { continue; }
		$html .= '<tr><th scope="row">' . esc_html( $method['name'] ) . '</th><td>' . esc_html( $method['summary'] ) . '</td><td>' . esc_html( $method['kids_note'] ) . '</td><td>' . esc_html( hs_fmt_range( $settings['pest'][ $key ]['from'] ?? '', $settings['pest'][ $key ]['to'] ?? '' ) ) . '</td></tr>';
	}
	return $html . '</tbody></table></div>';
}
function hs_pest_fallback_form() {
	$d = hs_pest_data();
	$lists = array( 'pest' => array( 'الحشرة', $d['pests'] ), 'kids' => array( 'أطفال صغار', array( 'yes' => 'نعم', 'no' => 'لا' ) ), 'pets' => array( 'حيوانات أليفة', array( 'none' => 'لا', 'catdog' => 'قطط أو كلاب', 'birdfish' => 'طيور أو أسماك' ) ), 'leave' => array( 'مدة مغادرة المكان', array( 0 => 'لا أستطيع', 4 => 'حتى 4 ساعات', 24 => 'يوم كامل', 72 => 'أكثر من يوم' ) ), 'sens' => array( 'حساسية من الروائح', array( 'yes' => 'نعم', 'no' => 'لا' ) ), 'sev' => array( 'حجم المشكلة', array( 1 => 'أحياناً', 2 => 'يومياً', 3 => 'نهاراً وبأعداد كبيرة' ) ) );
	$out = '<noscript><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="zad_pest_result">';
	foreach ( $lists as $key => $item ) { $out .= '<label class="hs-field">' . esc_html( $item[0] ) . '<select name="' . esc_attr( $key ) . '" required>'; foreach ( $item[1] as $value => $label ) { $out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; } $out .= '</select></label>'; }
	return $out . '<button class="hs-btn" type="submit">اعرض الطريقة والنتيجة</button></form></noscript>';
}
function hs_pest_result_request() {
	$rows = hs_pest_rank( wp_unslash( $_POST ) ); $best = null;
	foreach ( $rows as $row ) { if ( ! $row['unavailable'] && empty( $row['method']['licensed_only'] ) ) { $best = $row; break; } }
	$body = $best ? '<h2>' . esc_html( $best['method']['name'] ) . '</h2><p>' . esc_html( $best['method']['summary'] ) . '</p>' : '<p>تحتاج معاينة لتحديد طريقة مناسبة.</p>';
	$body .= hs_pest_html_guide( array_column( $rows, 'key' ) ) . '<p>معلومات إرشادية؛ يحدد الفني الطريقة النهائية وتعليمات المواد بعد معاينة المكان.</p>';
	zad_result_document( 'نتيجة اختيار طريقة المكافحة', $body, wp_get_referer() ?: home_url( '/' ) );
}
function hs_bundle_result_request() {
	$input = wp_unslash( $_POST ); $services = array_filter( (array) ( $input['svc'] ?? array() ), 'is_scalar' ); $input = array_filter( $input, 'is_scalar' ); $selected = array( 'svcs' => $services, 'pack' => ! empty( $input['pack'] ), 'assemble' => ! empty( $input['assemble'] ) );
	foreach ( array( 'old', 'new' ) as $place ) { $selected[ $place ] = array( 'city' => sanitize_text_field( $input[ $place . '_city' ] ?? '' ), 'size' => sanitize_key( $input[ $place . '_size' ] ?? '' ), 'floor' => min( 10, absint( $input[ $place . '_floor' ] ?? 0 ) ), 'lift' => ! empty( $input[ $place . '_lift' ] ) ); }
	$result = hs_bundle_calc( $selected ); $body = '<ul>';
	foreach ( $result['lines'] as $row ) { $body .= '<li>' . esc_html( $row['label'] . ': ' . ( null === $row['price'] ? 'بعد المعاينة' : $row['price'] . ' ريال' ) . ' ' . implode( '؛ ', $row['notes'] ) ) . '</li>'; }
	$body .= '</ul><p>' . esc_html( hs_bundle_price_text( $result ) ) . '</p>';
	zad_result_document( 'حساب باقة الانتقال', $body, wp_get_referer() ?: home_url( '/' ) );
}
foreach ( array( 'zad_pest_result' => 'hs_pest_result_request', 'zad_bundle_result' => 'hs_bundle_result_request' ) as $action => $callback ) {
	add_action( 'admin_post_' . $action, $callback ); add_action( 'admin_post_nopriv_' . $action, $callback );
}
