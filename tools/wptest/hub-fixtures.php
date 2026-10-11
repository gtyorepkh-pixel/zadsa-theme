<?php
/** Fixtures for the section pages (hubs): shaped like the live site. php tools/wptest/hub-fixtures.php (test site, old mu-plugin with cleaning/pest_control). */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 ); global $wpdb;
foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision')" ) as $i ) { wp_delete_post( (int) $i, true ); }
foreach ( array( 'service_area', 'service_cat', 'cleaning-sections', 'best_sections', 'best_faqs' ) as $tx ) { foreach ( (array) get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false ) ) as $t ) { if ( $t instanceof WP_Term ) { wp_delete_term( $t->term_id, $tx ); } } }
foreach ( array( '_memo_theme_options', 'zad_archive_content_mappings', 'zad_archive_content_seo', 'zad_city_extra', 'zad_archive_content_term_mappings' ) as $o ) { delete_option( $o ); }
update_option( '_memo_theme_options', array( 'zad_whatsapp' => '966500000000' ) );
$mk = function ( $type, $title, $slug, $parent = 0, $content = '<p>نص الصفحة الطويل عن الخدمة والأسعار والضمان والتفاصيل الكثيرة جداً التي لا تظهر في القسم أبداً.</p>', $extra = array() ) { return wp_insert_post( array_merge( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_parent' => $parent, 'post_content' => $content ), $extra ) ); };
$I = array();
// cleaning
$I['tanks'] = $mk( 'cleaning', 'تنظيف خزانات المياه في السعودية ـ زاد السعودية لخدمة صحية آمنة', 'tanks' );
$I['tanks_r'] = $mk( 'cleaning', 'أفضل شركة تنظيف خزانات بالرياض 150 ريال غسيل الخزان / زاد السعودية', 'riyadh', $I['tanks'] );
$I['tanks_j'] = $mk( 'cleaning', 'شركة تنظيف خزانات بجدة', 'jeddah', $I['tanks'] );
$I['hood1'] = $mk( 'cleaning', 'تنظيف خزانات حي النخيل', 'nakheel', $I['tanks_r'] );
$I['hood2'] = $mk( 'cleaning', 'تنظيف خزانات حي الملقا', 'malqa', $I['tanks_r'] );
$I['ac'] = $mk( 'cleaning', 'خدمات تنظيف مكيفات في مكانها لتنظيف مكيفات سبلت شباك', 'air-conditioning' );
$I['ac_r'] = $mk( 'cleaning', 'أفضل شركة تنظيف مكيفات بالرياض', 'riyadh', $I['ac'] );
$I['ac_j'] = $mk( 'cleaning', 'افضل شركة تنظيف مكيفات بجدة', 'jeddah', $I['ac'] );
$I['ac_x'] = $mk( 'cleaning', 'صيانة المكيفات', 'maintenance', $I['ac'] ); // child without a city
$I['c_riyadh'] = $mk( 'cleaning', 'شركة تنظيف منازل بالرياض زاد السعودية', 'riyadh' );
$I['c_dammam'] = $mk( 'cleaning', 'شركة تنظيف بالدمام', 'dammam' );
$I['c_k1'] = $mk( 'cleaning', 'تنظيف منازل حي العليا', 'olaya', $I['c_riyadh'] );
$I['lone'] = $mk( 'cleaning', 'خدمة بلا مدينة وبلا أبناء', 'lone' );
// pest control
$I['p_riyadh'] = $mk( 'pest_control', 'شركة مكافحة حشرات بالرياض / رش مبيدات وأسعار تبدأ من 150 ريال', 'riyadh' );
$I['p_k1'] = $mk( 'pest_control', 'مكافحة حشرات حي الياسمين', 'yasmin', $I['p_riyadh'] );
$I['p_jeddah'] = $mk( 'pest_control', 'أفضل شركة مكافحة حشرات بجدة', 'jeddah' );
$I['pesticides'] = $mk( 'pest_control', 'مبيدات الحشرات المنزلية', 'pesticides' );
$I['pest_kid'] = $mk( 'pest_control', 'أفضل مبيد', 'best', $I['pesticides'] );
// terms
$cities = array(); foreach ( array( 'الرياض', 'جدة', 'الدمام', 'القصيم' ) as $n ) { $r = wp_insert_term( $n, 'service_area' ); $cities[ $n ] = (int) $r['term_id']; }
$t_all = array_merge( array_values( $I ) );
foreach ( $t_all as $id ) { wp_set_object_terms( $id, array_values( $cities ), 'service_area' ); } // like the live site: every page carries every city
$tm = function ( $name, $tax, $slug ) { $r = wp_insert_term( $name, $tax, array( 'slug' => $slug ) ); if ( is_wp_error( $r ) ) { $t = get_term_by( 'slug', $slug, $tax ); return array( 'term_id' => $t->term_id ); } return $r; };
$sc = $tm( 'صفحات الخدمات', 'service_cat', 'cleaning' ); $pc = $tm( 'صفحات مكافحة الحشرات', 'service_cat', 'pest-control' );
foreach ( array( 'tanks', 'tanks_r', 'tanks_j', 'hood1', 'hood2', 'ac', 'ac_r', 'ac_j', 'ac_x', 'c_riyadh', 'c_dammam', 'c_k1', 'lone' ) as $k ) { wp_set_object_terms( $I[ $k ], array( (int) $sc['term_id'] ), 'service_cat' ); }
foreach ( array( 'p_riyadh', 'p_k1', 'p_jeddah', 'pesticides', 'pest_kid' ) as $k ) { wp_set_object_terms( $I[ $k ], array( (int) $pc['term_id'] ), 'service_cat' ); }
$cs = $tm( 'قسم تنظيف الخزانات', 'cleaning-sections', 'tanks' ); $cs2 = $tm( 'شركة تنظيف زاد السعودية', 'cleaning-sections', 'cleaning-company' );
foreach ( array( 'tanks', 'tanks_r', 'tanks_j', 'hood1', 'hood2' ) as $k ) { wp_set_object_terms( $I[ $k ], array( (int) $cs['term_id'] ), 'cleaning-sections' ); }
foreach ( array( 'c_riyadh', 'c_dammam', 'lone' ) as $k ) { wp_set_object_terms( $I[ $k ], array( (int) $cs2['term_id'] ), 'cleaning-sections' ); }
// blog + faq terms that match «tanks»
register_taxonomy( 'best_sections', array( 'sections' ), array( 'hierarchical' => true, 'public' => true ) );
$b = $tm( 'تنظيف الخزانات', 'best_sections', 'tanks' ); $art = $mk( 'sections', 'مقال عن الخزانات', 'tank-article' ); wp_set_object_terms( $art, array( (int) $b['term_id'] ), 'best_sections' );
$fq = $tm( 'أسئلة الخزانات', 'best_faqs', 'tanks' ); $faq = $mk( 'faq', 'سؤال عن الخزان؟', 'tank-q' ); wp_set_object_terms( $faq, array( (int) $fq['term_id'] ), 'best_faqs' );
// archive-content pages (drafts), the long one like the live /cleaning/ page
$long = '<p>' . str_repeat( 'جملة طويلة عن التنظيف في السعودية. ', 30 ) . '</p><h2>عنوان</h2><p>فقرة ثانية.</p>';
$pg_c = $mk( 'page', 'خدمات التنظيف في السعودية', 'ac-cleaning', 0, $long, array( 'post_status' => 'draft' ) ); $pg_p = $mk( 'page', 'خدمات مكافحة الحشرات في السعودية', 'ac-pest', 0, '<p>x</p>', array( 'post_status' => 'draft' ) );
update_option( ZAD_AC_MAP, array( 'cleaning' => $pg_c, 'pest_control' => $pg_p ) );
update_option( ZAD_AC_SEO, array( 'cleaning' => array( 'title' => 'عنوان مخصص للتنظيف | زاد' ) ) );
$I['cities_page'] = $mk( 'page', 'فروعنا في مختلف مدن المملكة: خدمات متكاملة أينما كنت', 'cities', 0, '<p>محتوى</p>' );
$I['art'] = $art; $I['faq'] = $faq; $I['cities'] = $cities;
update_option( 'hub_ids', $I, false );
if ( function_exists( 'zad_city_bump' ) ) { zad_city_bump(); }
delete_transient( 'zad_area_ids' ); update_option( 'zad_nav_ver', time() );
flush_rewrite_rules( false );
echo json_encode( $I ), "\n";
