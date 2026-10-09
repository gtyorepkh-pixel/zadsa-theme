<?php defined( 'ABSPATH' ) || exit;
/**
 * REST: /wp-json/zad/v1/…   Every write needs (1) the cache-safe token from GET /token, (2) an empty honeypot, (3) a rate limit.
 * Nothing here returns a person's data; answers are generic so a number cannot be probed.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'zad/v1', '/token', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function () {
			nocache_headers();
			$r = new WP_REST_Response( array( 't' => zt_token_make() ) );
			$r->header( 'Cache-Control', 'no-store, max-age=0' );
			return $r;
		},
	) );
	register_rest_route( 'zad/v1', '/reminders', array(
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => 'zt_rest_reminder',
	) );
} );

function zt_rest_guard( WP_REST_Request $req, $bucket, $limit ) {
	if ( '' !== (string) $req->get_param( 'website' ) ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); } // honeypot: looks like success
	if ( ! zt_token_check( (string) $req->get_param( 't' ) ) ) { return new WP_Error( 'zt_token', 'انتهت صلاحية الصفحة، حدّثها وحاول مرة أخرى.', array( 'status' => 403 ) ); }
	if ( ! zt_rate_limit( $bucket, $limit ) ) { return new WP_Error( 'zt_rate', 'محاولات كثيرة، حاول لاحقاً.', array( 'status' => 429 ) ); }
	return true;
}

function zt_rest_reminder( WP_REST_Request $req ) {
	$g = zt_rest_guard( $req, 'reminder', (int) zt_opt( 'general.rate_per_ip_hour' ) );
	if ( true !== $g ) { return $g; }
	$id = zt_reminder_add( array(
		'first_name' => $req->get_param( 'first_name' ), 'phone' => $req->get_param( 'phone' ), 'email' => $req->get_param( 'email' ),
		'service' => $req->get_param( 'service' ), 'due_date' => $req->get_param( 'due_date' ), 'tool' => $req->get_param( 'tool' ),
		'hood' => $req->get_param( 'hood' ), 'consent' => (bool) $req->get_param( 'consent' ),
	) );
	if ( is_wp_error( $id ) ) { return new WP_Error( 'zt_' . $id->get_error_code(), $id->get_error_message(), array( 'status' => 400 ) ); }
	return new WP_REST_Response( array( 'ok' => true ), 200 ); // no id, no echo of the data
}
