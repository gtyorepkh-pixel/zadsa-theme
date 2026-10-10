<?php
/** Sets the city of the fixture pages the way the tool would (flat pages by name, pillars = all, one zad_service = dammam). php tools/wptest/city-apply.php */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
$by = function ( $type, $slug, $parent = null ) { $a = array( 'post_type' => $type, 'name' => $slug, 'numberposts' => 1, 'post_status' => 'publish', 'zad_all' => true ); if ( null !== $parent ) { $a['post_parent'] = $parent; } $p = get_posts( $a ); return $p ? $p[0]->ID : 0; };
$set = array( $by( 'pest_control', 'pest-pillar' ) => 'all', $by( 'cleaning', 'cleaning-pillar' ) => 'all', $by( 'pest_control', 'roaches-riyadh' ) => 'riyadh', $by( 'pest_control', 'roaches-dammam' ) => 'dammam', $by( 'pest_control', 'roaches-jeddah' ) => 'jeddah', $by( 'zad_service', 'dammam-service' ) => 'dammam' );
echo 'changed: ', zad_city_apply( $set ), "\n";
