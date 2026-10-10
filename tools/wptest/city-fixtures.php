<?php
/** Fixtures for the city-scoping tests: pest/cleaning/zad_service pages for Riyadh, Dammam, Jeddah (flat + hierarchical + hood pages + a page without a city) + questions. php tools/wptest/city-fixtures.php */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
global $wpdb;
foreach ( get_posts( array( 'post_type' => array( 'pest_control', 'cleaning', 'zad_service', 'zad_faq', 'zad_hood', 'post', 'page', 'sections', 'guide' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $i ) { wp_delete_post( $i, true ); }
foreach ( array( 'zad_city_extra', 'zad_city_undo', 'zad_city_ver', 'zad_qnet_ver' ) as $o ) { delete_option( $o ); }
update_option( '_memo_theme_options', array( 'zad_related_auto' => true, 'zad_bridges' => "pest-control > cleaning\ncleaning > pest-control" ) );
$mk = function ( $type, $title, $slug, $parent = 0, $meta = array(), $status = 'publish' ) { $id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status, 'post_parent' => $parent, 'post_content' => '<p>' . $title . '</p>' ) ); foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); } return $id; };
$cat = term_exists( 'مكافحة', 'service_cat' ) ?: wp_insert_term( 'مكافحة', 'service_cat' ); $cat = (int) ( is_array( $cat ) ? $cat['term_id'] : $cat );
$ids = array();
$ids['p_pillar'] = $mk( 'pest_control', 'مكافحة الحشرات', 'pest-pillar' );
foreach ( array( 'riyadh' => 'الرياض', 'dammam' => 'الدمام', 'jeddah' => 'جدة' ) as $s => $n ) {
	$ids[ "p_city_$s" ] = $mk( 'pest_control', "مكافحة الحشرات في $n", $s, $ids['p_pillar'] );
	$ids[ "p_ants_$s" ]  = $mk( 'pest_control', "مكافحة النمل في $n", 'ants', $ids[ "p_city_$s" ] );
	$ids[ "p_flat_$s" ]  = $mk( 'pest_control', "مكافحة الصراصير بـ$n", "roaches-$s" ); // flat page: no city in the URL
}
$ids['p_rodents_dammam'] = $mk( 'pest_control', 'مكافحة القوارض في الدمام', 'rodents', $ids['p_city_dammam'] );
$ids['p_termites'] = $mk( 'pest_control', 'مكافحة الأرضة', 'termites' ); // no city anywhere
$ids['c_pillar'] = $mk( 'cleaning', 'التنظيف', 'cleaning-pillar' );
foreach ( array( 'riyadh' => 'الرياض', 'dammam' => 'الدمام', 'jeddah' => 'جدة' ) as $s => $n ) { $ids[ "c_city_$s" ] = $mk( 'cleaning', "خدمات التنظيف في $n", $s, $ids['c_pillar'] ); $ids[ "c_ac_$s" ] = $mk( 'cleaning', "تنظيف المكيفات في $n", 'ac', $ids[ "c_city_$s" ] ); }
$ids['z_general'] = $mk( 'zad_service', 'خدمة عامة', 'general-service' );
$ids['z_dammam'] = $mk( 'zad_service', 'خدمة الدمام', 'dammam-service' );
foreach ( $ids as $k => $i ) { if ( 0 === strpos( $k, 'p_' ) && 'p_pillar' !== $k ) { wp_set_object_terms( $i, array( $cat ), 'service_cat' ); } }
// hood library + hood pages (neighbour of a Dammam hood is a Jeddah hood by mistake)
$gt = function ( $n, $tx ) { $x = term_exists( $n, $tx ) ?: wp_insert_term( $n, $tx ); return array( 'term_id' => (int) ( is_array( $x ) ? $x['term_id'] : $x ) ); }; $dm = $gt( 'الدمام', 'zad_hood_city' ); $jd = $gt( 'جدة', 'zad_hood_city' );
$h1 = $mk( 'zad_hood', 'حي النزهة', 'nozha' ); $h2 = $mk( 'zad_hood', 'حي الفيصلية', 'faisaliyah' ); $h3 = $mk( 'zad_hood', 'حي الروضة', 'rawdah' );
wp_set_object_terms( $h1, array( (int) $dm['term_id'] ), 'zad_hood_city' ); wp_set_object_terms( $h2, array( (int) $dm['term_id'] ), 'zad_hood_city' ); wp_set_object_terms( $h3, array( (int) $jd['term_id'] ), 'zad_hood_city' );
update_post_meta( $h1, '_zad_hd_nbrs', array( $h2, $h3 ) );
$hood = function ( $title, $slug, $parent, $hid ) use ( $mk ) { return $mk( 'pest_control', $title, $slug, $parent, array( '_zad_h_on' => '1', '_zad_h_hood' => $hid, '_zad_h_svc' => 'مكافحة حشرات' ) ); };
$ids['h_nozha'] = $hood( 'مكافحة حشرات حي النزهة', 'nozha-pest', $ids['p_city_dammam'], $h1 );
$ids['h_faisal'] = $hood( 'مكافحة حشرات حي الفيصلية', 'faisaliyah-pest', $ids['p_city_dammam'], $h2 );
$ids['h_rawdah'] = $hood( 'مكافحة حشرات حي الروضة', 'rawdah-pest', $ids['p_city_jeddah'], $h3 );
// questions
$fc = term_exists( 'pest-control-faq', 'faq_cat' ) ?: wp_insert_term( 'الأسئلة الشائعة عن مكافحة الحشرات', 'faq_cat', array( 'slug' => 'pest-control-faq' ) ); $fc = (int) ( is_array( $fc ) ? $fc['term_id'] : $fc );
foreach ( array( 'كم تكلفة مكافحة النمل بالرياض؟' => 'q-ants-riyadh', 'كم تكلفة مكافحة النمل بالدمام؟' => 'q-ants-dammam', 'هل مبيد النمل آمن للأطفال؟' => 'q-ants-safe', 'كيف أتخلص من النمل بجدة؟' => 'q-ants-jeddah' ) as $t => $s ) { $q = $mk( 'zad_faq', $t, $s ); wp_set_object_terms( $q, array( $fc ), 'faq_cat' ); }
delete_transient( 'zad_area_ids' ); delete_transient( 'zad_404_lists' ); update_option( 'zad_nav_ver', time() ); update_option( 'zad_hood_ver', time() ); update_option( 'zad_qnet_ver', time() );
echo wp_json_encode( $ids ), "\n";
flush_rewrite_rules( true );
