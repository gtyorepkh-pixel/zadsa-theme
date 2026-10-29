<?php defined( 'ABSPATH' ) || exit;
/** Register the existing physical robots.txt policy before other modules. */
add_filter( 'robots_txt', function( $output, $public ) {
	$file = ABSPATH . 'robots.txt';
	return file_exists( $file ) ? file_get_contents( $file ) : $output;
}, 10, 2 );

