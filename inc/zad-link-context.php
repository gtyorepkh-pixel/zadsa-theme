<?php defined( 'ABSPATH' ) || exit;
/** Shared, conservative context for district links and the same-service city list. */
function zad_link_name( $value ) {
 $value = zad_ar_norm( $value );
 return trim( preg_replace( '/\s+/u', ' ', $value ) );
}

/** Specific services, not the broad WordPress post type or a generic services category. */
function zad_link_service_key( $id ) {
 static $cache = array();
 $id = (int) $id; if ( isset( $cache[ $id ] ) ) { return $cache[ $id ]; }
 $p = get_post( $id ); if ( ! $p || ! zad_is_service( $id ) ) { return ''; }
 $declared = trim( (string) get_post_meta( $id, '_zad_h_svc', true ) );
 $text = zad_link_name( $declared ?: zsc_clean_name( $p->post_title ) );
 $keys = array();
 if ( preg_match( '/مكيف|تكييف|سبليت|سبلت|كاسيت|دكت/u', $text ) ) {
  $freon = preg_match( '/(?:تعبئه|شحن|تزويد).{0,20}(?:فريون|غاز)/u', $text );
  $clean = preg_match( '/تنظيف|غسيل|غسل/u', $text );
  $repair = preg_match( '/صيانه|اصلاح|تركيب/u', $text );
  if ( $clean ) { $keys[] = 'ac-cleaning'; }
  if ( $freon ) { $keys[] = 'ac-freon'; }
  elseif ( $repair ) { $keys[] = 'ac-maintenance'; }
  if ( ! $keys ) { $keys[] = 'ac'; }
 } else {
  $patterns = array(
   'tank-cleaning' => '/خزان/u', 'sofa-cleaning' => '/كنب|مجالس|ارائك|اريكه/u',
   'carpet-cleaning' => '/موكيت|سجاد/u', 'curtain-cleaning' => '/ستائر/u',
   'mattress-cleaning' => '/مراتب/u', 'mosque-cleaning' => '/مساجد|مسجد/u',
   'floor-polishing' => '/جلي|تلميع.{0,16}(?:بلاط|رخام|ارضيات)/u',
   'pool-cleaning' => '/مسابح|مسبح/u', 'facade-cleaning' => '/واجهات/u',
   'drain-clearing' => '/مجاري|بيارات|تسليك/u', 'leak-detection' => '/كشف.{0,12}تسرب/u',
   'insulation' => '/عزل/u', 'furniture-moving' => '/نقل.{0,16}(?:عفش|اثاث)/u',
   'furniture-storage' => '/تخزين.{0,16}(?:عفش|اثاث)/u',
   'roach-control' => '/صراصير|صرصور/u', 'bedbug-control' => '/بق(?:\s|$)|بق.{0,6}الفراش/u',
   'termite-control' => '/نمل.{0,4}ابيض|ارضه/u', 'rodent-control' => '/فئران|فار(?:\s|$)|قوارض|جرذ/u',
   'mosquito-control' => '/بعوض|ناموس/u', 'fly-control' => '/ذباب/u',
   'gecko-control' => '/وزغ|ابراص/u', 'scorpion-control' => '/عقارب/u',
  );
  foreach ( $patterns as $key => $pattern ) { if ( preg_match( $pattern, $text ) ) { $keys[] = $key; } }
  if ( ! in_array( 'termite-control', $keys, true ) && preg_match( '/نمل/u', $text ) ) { $keys[] = 'ant-control'; }
  if ( ! $keys && preg_match( '/حشرات|مكافحه|رش.{0,12}مبيدات/u', $text ) ) { $keys[] = 'pest-control'; }
  if ( ! $keys && preg_match( '/تنظيف|نظافه/u', $text ) ) { $keys[] = 'home-cleaning'; }
 }
 // A title containing no identifiable service can inherit its explicit service branch.
 if ( ! $keys && ! $declared ) {
  foreach ( get_post_ancestors( $id ) as $parent ) {
   if ( in_array( zad_service_city_scope( $parent ), array( '', 'all' ), true ) ) {
    $key = zad_link_service_key( $parent ); if ( $key ) { return $cache[ $id ] = $key; }
   }
  }
 }
 sort( $keys );
 return $cache[ $id ] = (string) apply_filters( 'zad_link_service_key', implode( '+', $keys ), $id );
}

function zad_link_same_service( $source, $target ) {
 $key = zad_link_service_key( $source );
 return '' !== $key && $key === zad_link_service_key( $target );
}

function zad_link_concrete_city( $id ) {
 $city = zad_service_city_scope( $id );
 return '' !== $city && 'all' !== $city ? $city : '';
}

function zad_link_public_target( $id ) {
 $p = get_post( $id );
 return $p && zad_is_service( $id ) && 'publish' === $p->post_status && '' === $p->post_password;
}

/** Whole district name, from explicit data first, otherwise the district phrase in the title. */
function zad_link_district_names( $id ) {
 static $cache = array(); if ( isset( $cache[ $id ] ) ) { return $cache[ $id ]; }
 $names = array(); $hood = absint( get_post_meta( $id, '_zad_h_hood', true ) );
 if ( $hood && 'zad_hood' === get_post_type( $hood ) ) { $names[] = get_the_title( $hood ); }
 $terms = get_the_terms( $id, 'service_area' );
 if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { if ( $term->parent ) { $names[] = $term->name; } } }
 $title = zad_link_name( zsc_clean_name( get_the_title( $id ) ) );
 if ( preg_match( '/(?:^|\s)ب?حي\s+(.+)$/u', $title, $match ) ) {
  $name = $match[1];
  $cities = array_map( function ( $city ) { return preg_quote( zad_link_name( $city ), '/' ); }, array_values( zad_city_map() ) );
  if ( $cities ) { $name = preg_replace( '/\s+(?:في\s+|ب)?(?:' . implode( '|', $cities ) . ')(?!\p{L}).*$/u', '', $name ); }
  $names[] = $name;
 }
 return $cache[ $id ] = array_values( array_unique( array_filter( array_map( function ( $name ) { return trim( preg_replace( '/^حي\s+/u', '', zad_link_name( $name ) ) ); }, $names ) ) ) );
}

/** Existing district in the same city AND the same specific service. Never infer the site's default city. */
function zad_link_find_district( $name, $source ) {
 $city = zad_link_concrete_city( $source ); $name = trim( preg_replace( '/^حي\s+/u', '', zad_link_name( $name ) ) );
 if ( ! $city || ! $name || ! zad_link_service_key( $source ) ) { return ''; }
 static $pool = array(); $type = get_post_type( $source );
 if ( ! isset( $pool[ $type ] ) ) { $pool[ $type ] = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'has_password' => false,
  'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ), 'suppress_filters' => true, 'zad_all' => true ) ); }
 $candidates = $pool[ $type ];
 $matches = array(); $source_parent = wp_get_post_parent_id( $source );
 foreach ( $candidates as $p ) {
  if ( $p->ID === (int) $source || zad_link_concrete_city( $p->ID ) !== $city || ! zad_link_same_service( $source, $p->ID ) || ! in_array( $name, zad_link_district_names( $p->ID ), true ) ) { continue; }
  $priority = $p->post_parent === (int) $source ? 0 : ( $source_parent && $p->post_parent === $source_parent ? 1 : 2 );
  $matches[] = array( 'priority' => $priority, 'id' => $p->ID );
 }
 usort( $matches, function ( $a, $b ) { return $a['priority'] <=> $b['priority'] ?: $a['id'] <=> $b['id']; } );
 return $matches ? get_permalink( $matches[0]['id'] ) : '';
}

/** Automatic cross-city targets are city service pages, not guides, offers or district pages. */
function zad_link_city_target( $source, $target ) {
 if ( $source === $target || ! zad_link_public_target( $target ) || ! zad_link_same_service( $source, $target ) ) { return false; }
 $mine = zad_link_concrete_city( $source ); $city = zad_link_concrete_city( $target );
 if ( ! $mine || ! $city || $city === $mine || zad_link_district_names( $target ) ) { return false; }
 $title = zad_link_name( get_the_title( $target ) );
 if ( preg_match( '/^(?:كيف|ما |هل |كم |خطوات|دليل|نصائح|اسعار)|(?:شمال|جنوب|شرق|غرب|وسط)\s|عروض|تعبئه|شحن/u', $title ) ) { return false; }
 return true;
}

/** The same ordered city selection drives visible links and structured data. */
function zad_link_city_ids( $source ) {
 $source = (int) $source;
 $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) get_post_meta( $source, '_zad_cities_svc', true ) ) ) ) );
 $manual = (bool) $ids;
 if ( ! $manual && zad_link_concrete_city( $source ) && zad_link_service_key( $source ) ) {
  $ids = get_posts( array( 'post_type' => get_post_type( $source ), 'post_status' => 'publish', 'has_password' => false,
   'numberposts' => -1, 'fields' => 'ids', 'post__not_in' => array( $source ), 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
   'suppress_filters' => true, 'zad_all' => true ) );
 }
 $selected = array(); $cities = array();
 foreach ( $ids as $target ) {
  $target = (int) $target;
  if ( ! zad_link_city_target( $source, $target ) ) { continue; }
  $city = zad_link_concrete_city( $target );
  if ( ! $manual && isset( $cities[ $city ] ) ) { continue; }
  $cities[ $city ] = true; $selected[] = $target;
  if ( count( $selected ) >= 8 ) { break; }
 }
 return $selected;
}
