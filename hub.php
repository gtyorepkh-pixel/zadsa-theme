<?php defined( 'ABSPATH' ) || exit;
/** Section page (hub): chosen by zad_hub_ctx() in inc/zad-hubs.php through the template_include filter. Type archives and section pages get the full section design; city and term pages stay a simple list. */
get_header();
$zad_hub_c = zad_hub_ctx();
get_template_part( $zad_hub_c && in_array( $zad_hub_c['kind'], array( 'type', 'page' ), true ) ? 'template-parts/hub-section' : ( $zad_hub_c && 'city' === $zad_hub_c['kind'] && function_exists( 'zad_city_catalog' ) ? 'template-parts/hub-city' : ( $zad_hub_c && 'cities' === $zad_hub_c['kind'] ? 'template-parts/hub-cities' : 'template-parts/hub-simple' ) ) );
get_footer();
