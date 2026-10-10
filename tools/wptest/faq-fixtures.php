<?php
/** Builds sample zad_faq posts on the throw-away WordPress (tools/wptest/setup.sh) for the direct-answer tool tests. Run: php tools/wptest/faq-fixtures.php */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
foreach ( get_posts( array( 'post_type' => array( 'zad_faq', 'zad_service', 'post' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $i ) { wp_delete_post( $i, true ); }
global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_zad\\_faq%'" );
$mk = function ( $type, $title, $slug, $content, $ex = '', $meta = array() ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_content' => $content, 'post_excerpt' => $ex ) );
	foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
	return $id;
};
$p = function ( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->"; };
$h = function ( $t ) { return "<!-- wp:heading -->\n<h2>$t</h2>\n<!-- /wp:heading -->"; };
$svc = $mk( 'zad_service', 'مكافحة حشرات بالرياض', 'pest-riyadh', '<p>خدمة</p>' );
$svc2 = $mk( 'zad_service', 'تنظيف خزانات بالرياض', 'tanks-riyadh', '<p>خدمة</p>' );
$art = $mk( 'post', 'دليل التحضير للرش', 'pre-spray-guide', '<p>دليل</p>' );
$ids = array();
$A1 = 'لا، رشة واحدة قد لا تكفي في كثير من الحالات، لأن البيض يفقس بعد الرش ويحتاج متابعة بعد أسبوعين.';
$ids['once'] = $mk( 'zad_faq', 'هل رش المبيدات مرة واحدة يكفي؟', 'is-spraying-pesticides-once-enough',
	$p( 'الإجابة: ' . $A1 ) . "\n\n" . $p( 'تعتمد الإجابة على نوع الحشرة ودرجة الإصابة وحجم المكان، ولذلك نوصي بزيارة متابعة.' ) . "\n\n" . $h( 'متى تكفي رشة واحدة؟' ) . "\n\n" . $p( 'تكفي في الإصابات الخفيفة جداً داخل مساحات صغيرة.' ),
	'', array( '_zad_faq_services' => array( (string) $svc ) ) );
$ids['classic'] = $mk( 'zad_faq', 'كم يستمر مفعول الرش؟', 'spray-effect-duration', '<p>الإجابة المختصرة: يستمر المفعول من ثلاثة إلى ستة أشهر حسب نوع المبيد.</p><p>وتختلف المدة حسب التهوية والرطوبة ونظافة المكان.</p><h2>عوامل التأثير</h2><p>الرطوبة والتهوية.</p>', '', array( '_zad_faq_services' => array( (string) $svc ) ) );
$ids['b_same'] = $mk( 'zad_faq', 'هل الرش آمن للأطفال؟', 'is-spray-safe-kids', $p( 'نعم، المبيدات المعتمدة آمنة للأطفال بعد انتهاء فترة التهوية الموصى بها.' ) . "\n\n" . $p( 'ينصح بإبعاد الأطفال ساعتين.' ),
	'نعم، المبيدات المعتمدة آمنة للأطفال بعد انتهاء فترة التهوية الموصى بها.', array( '_zad_faq_services' => array( (string) $svc ), '_zad_faq_article' => $art ) );
$ids['b_similar'] = $mk( 'zad_faq', 'هل تنظيف الخزان ضروري؟', 'is-tank-cleaning-needed', $p( 'الإجابة: نعم، ينصح بتنظيف الخزان مرتين في السنة على الأقل لضمان جودة المياه.' ) . "\n\n" . $p( 'الإهمال يسبب ترسبات.' ),
	'نعم ينصح بتنظيف الخزان مرتين في السنة على الأقل لضمان جودة المياه', array( '_zad_faq_services' => array( (string) $svc2 ) ) );
$ids['c'] = $mk( 'zad_faq', 'كم سعر الرش؟', 'spray-price', $p( 'يبدأ السعر حسب المساحة ونوع الحشرة، ويحدده الفني بعد المعاينة.' ) . "\n\n" . $p( 'تواصل معنا للمعاينة.' ),
	'تختلف الأسعار حسب المساحة، ويمكن طلب تسعير مجاني.', array( '_zad_faq_services' => array( (string) $svc ) ) );
$ids['manual_h'] = $mk( 'zad_faq', 'ما أنواع النمل؟', 'ant-types', $h( 'أنواع النمل' ) . "\n\n" . $p( 'النمل الأسود والنمل الأحمر.' ), '', array( '_zad_faq_services' => array( (string) $svc ) ) );
$ids['manual_list'] = $mk( 'zad_faq', 'ما خطوات التحضير؟', 'prep-steps', "<!-- wp:list -->\n<ul><li>أخلِ المكان</li><li>غطِّ الطعام</li></ul>\n<!-- /wp:list -->\n\n" . $p( 'بعدها يبدأ الفني.' ), '' );
$ids['has_more'] = $mk( 'zad_faq', 'هل الرش يؤذي الحيوانات الأليفة؟', 'pets-safe', $p( 'يجب إبعاد الحيوانات الأليفة أثناء الرش وبعده بفترة قصيرة.' ) . "\n\n" . $p( 'ثم يمكن إعادتها.' ), '', array( '_zad_faq_more' => "توضيح إضافي سطر 1\nسطر 2", '_zad_faq_services' => array( (string) $svc ) ) );
echo wp_json_encode( array( 'svc' => $svc, 'svc2' => $svc2, 'art' => $art ) + $ids ), "\n";
flush_rewrite_rules( true );
