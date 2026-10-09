<?php defined( 'ABSPATH' ) || exit;
/** Template «تتبع الطلب»: the token comes from /<page>/<token>/ (rewrite) or ?t= ; the certificate is a standalone printable page (?view=certificate). */
$token = (string) get_query_var( 'zt_token' );
if ( '' === $token && isset( $_GET['t'] ) ) { $token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['t'] ) ); }
if ( isset( $_GET['view'] ) && 'certificate' === $_GET['view'] && zt_orders_ready() ) {
	$oid = zt_order_by_token( $token );
	if ( $oid && zt_rate_limit( 'track', (int) zt_opt( 'orders.track_per_hour' ) ) ) {
		$o = zt_order_get( $oid );
		if ( 'done' === $o['status'] && $o['warranty_code'] && '' !== trim( (string) zt_opt( 'orders.warranty_terms' ) ) ) { zt_render_certificate( $oid ); exit; }
	}
}
get_header();
?>
<main id="main" class="zt-page zt-track">
	<section class="sec zt-hero"><div class="wrap wrap--narrow"><h1>تتبع طلبك</h1></div></section>
	<?php zt_render_track( $token ); ?>
</main>
<?php get_footer();
