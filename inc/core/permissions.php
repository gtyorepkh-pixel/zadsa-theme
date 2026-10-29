<?php defined( 'ABSPATH' ) || exit;
/**
 * Editor limits (after rokn inc/acf.php). Only an administrator (manage_options) can:
 * - see and change the fields that steer indexing and site structure: Canonical + noindex (SEO box), «نوع الصفحة (خدمة / قسم)», «نوع الصفحة (رئيسية / حي)».
 *   The theme settings page and every tool were already administrator-only.
 * - change the URL (slug or parent page) of a page that is already published. Editors and authors keep editing everything else.
 */

function zad_can_admin_fields() {
	return current_user_can( 'manage_options' );
}

add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( zad_can_admin_fields() || empty( $postarr['ID'] ) ) { return $data; }
	$old = get_post( (int) $postarr['ID'] );
	if ( ! $old || 'publish' !== $old->post_status || '' === $old->post_name || ! is_post_type_viewable( $old->post_type ) ) { return $data; }
	if ( $data['post_name'] !== $old->post_name || (int) $data['post_parent'] !== (int) $old->post_parent ) {
		$data['post_name']   = $old->post_name;
		$data['post_parent'] = $old->post_parent;
		set_transient( 'zad_slug_locked_' . get_current_user_id(), 1, 5 * MINUTE_IN_SECONDS );
	}
	return $data;
}, 20, 2 );

add_action( 'admin_notices', function () {
	if ( ! get_transient( 'zad_slug_locked_' . get_current_user_id() ) ) { return; }
	delete_transient( 'zad_slug_locked_' . get_current_user_id() );
	echo '<div class="notice notice-warning"><p>اتحفظت التعديلات، بس رابط الصفحة (الـ slug والصفحة الأب) فضل زي ما هو: تغيير رابط صفحة منشورة للمدير بس، عشان الروابط القديمة ما تتكسرش.</p></div>';
} );
