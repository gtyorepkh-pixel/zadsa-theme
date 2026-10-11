<?php defined( 'ABSPATH' ) || exit;
/**
 * Tools ← «نقل الأعمال القديمة»: the old work pages (ordinary pages, children of the «completed projects» page) become «أعمالنا» posts (zad_work).
 *   «تجربة»  shows exactly what would happen and changes nothing.
 *   «تنفيذ»  creates a zad_work per page (same slug, title, content, excerpt, featured image, date, author, Yoast fields), turns the old page into a DRAFT (never deleted),
 *            and records 301s: /completed-projects/<slug>/ → /works/<slug>/ and /completed-projects/ → /works/.
 *   «تراجع»  (after a run) trashes the created works, restores the old status and removes the redirects.
 * Redirects: an own map (option zad_wm_map, served by template_redirect — works without any plugin) and, when the Redirection plugin is active, the same rules in its group «نقل الأعمال».
 */

function zad_wm_parent_id() { return (int) get_option( 'zad_wm_parent_id', 24322 ); }

/** Path (no slashes at the ends, decoded, lower-case) of a URL. */
function zad_wm_key( $url ) {
	$p = (string) wp_parse_url( $url, PHP_URL_PATH ); $h = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $h && '/' !== $h && 0 === strpos( $p, $h ) ) { $p = substr( $p, strlen( $h ) - 1 ); }
	return mb_strtolower( urldecode( trim( $p, '/' ) ) );
}

/* ---- own 301 map ---- */
add_action( 'template_redirect', function () {
	global $wp;
	if ( is_admin() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || ! isset( $wp->request ) ) { return; } // phpcs:ignore
	$map = get_option( 'zad_wm_map' );
	if ( ! is_array( $map ) || ! $map ) { return; }
	$k = mb_strtolower( urldecode( trim( (string) $wp->request, '/' ) ) );
	if ( isset( $map[ $k ] ) ) { wp_safe_redirect( $map[ $k ], 301 ); exit; }
}, 1 );

/** Status change without touching the dates (wp_update_post would reset the date of a page that becomes a draft). */
function zad_wm_set_status( $id, $status ) {
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => (int) $id ) ); // phpcs:ignore
	clean_post_cache( (int) $id );
}

/** Old pages: published and unpublished children of the parent page. */
function zad_wm_pages() {
	$pid = zad_wm_parent_id();
	if ( ! $pid ) { return array(); }
	return get_posts( array( 'post_type' => 'page', 'post_parent' => $pid, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
}

function zad_wm_done_map() { // old page id => new work id (already migrated)
	global $wpdb; $o = array();
	foreach ( (array) $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_zad_wm_from' AND p.post_type = 'zad_work' AND p.post_status <> 'trash'" ) as $r ) { $o[ (int) $r->meta_value ] = (int) $r->post_id; } // a work the undo sent to the trash does not count
	return $o;
}

function zad_wm_yoast_keys( $id ) {
	global $wpdb;
	return array_values( array_filter( (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $id, $wpdb->esc_like( '_yoast_wpseo_' ) . '%' ) ), function ( $k ) { return 0 !== strpos( $k, '_yoast_wpseo_primary_' ); } ) );
}

/** What a run would do. @return array( rows => [...], parent => [...], error ) */
function zad_wm_plan() {
	$plan = array( 'rows' => array(), 'parent' => null, 'error' => '' );
	$pid  = zad_wm_parent_id();
	$par  = $pid ? get_post( $pid ) : null;
	if ( ! $par || 'page' !== $par->post_type ) { $plan['error'] = 'الصفحة الأم رقم ' . $pid . ' غير موجودة.'; return $plan; }
	if ( ! post_type_exists( 'zad_work' ) ) { $plan['error'] = 'نوع «أعمالنا» غير مسجّل.'; return $plan; }
	$base = trailingslashit( zad_works_url() );
	$done = zad_wm_done_map();
	foreach ( zad_wm_pages() as $p ) {
		$old_url = 'publish' === $p->post_status ? get_permalink( $p ) : trailingslashit( get_permalink( $par ) ) . $p->post_name . '/';
		$row = array( 'id' => (int) $p->ID, 'title' => $p->post_title, 'slug' => $p->post_name, 'status' => $p->post_status, 'old_url' => $old_url, 'new_url' => $base . rawurlencode( urldecode( $p->post_name ) ) . '/', 'action' => 'move', 'why' => '',
			'excerpt' => '' !== trim( $p->post_excerpt ), 'thumb' => (int) get_post_thumbnail_id( $p->ID ), 'yoast' => zad_wm_yoast_keys( $p->ID ), 'chars' => mb_strlen( wp_strip_all_tags( $p->post_content ), 'UTF-8' ), 'date' => $p->post_date, 'redirect' => ( 'publish' === $p->post_status ) );
		if ( isset( $done[ $p->ID ] ) ) { $row['action'] = 'skip'; $row['why'] = 'نُقلت سابقاً (العمل #' . $done[ $p->ID ] . ')'; }
		else {
			$ex = get_posts( array( 'post_type' => 'zad_work', 'name' => $p->post_name, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
			if ( $ex ) { $row['action'] = 'skip'; $row['why'] = 'يوجد عمل بنفس الـ slug (#' . $ex[0] . ')'; }
			elseif ( '' === $p->post_name ) { $row['action'] = 'skip'; $row['why'] = 'الصفحة بلا slug'; }
		}
		$plan['rows'][] = $row;
	}
	$plan['parent'] = array( 'id' => $pid, 'title' => $par->post_title, 'old_url' => get_permalink( $par ), 'new_url' => zad_works_url() );
	return $plan;
}

function zad_wm_run() {
	global $wpdb;
	$rep = array( 'error' => '', 'moved' => 0, 'skipped' => 0, 'red' => 0, 'lines' => array() );
	$plan = zad_wm_plan();
	if ( $plan['error'] ) { $rep['error'] = $plan['error']; return $rep; }
	$log = get_option( 'zad_wm_log' ); $log = is_array( $log ) ? $log : array();
	$log = array_merge( array( 'items' => array(), 'map' => array(), 'red' => array() ), $log );
	$map = (array) get_option( 'zad_wm_map', array() );
	$grp = ( function_exists( 'zad_svcc_red_active' ) && zad_svcc_red_active() ) ? zad_svcc_red_group( 'نقل الأعمال' ) : 0;
	$add = function ( $old_url, $new_url ) use ( &$map, &$log, &$rep, $grp ) {
		$k = zad_wm_key( $old_url );
		if ( '' === $k || isset( $map[ $k ] ) ) { return; }
		$map[ $k ] = $new_url; $log['map'][] = $k; $rep['red']++;
		if ( $grp && ! zad_svcc_red_lookup( $k ) ) { $rid = zad_svcc_red_add( $grp, rawurldecode( $k ), $new_url ); if ( $rid ) { $log['red'][] = $rid; } }
	};
	foreach ( $plan['rows'] as $r ) {
		if ( 'move' !== $r['action'] ) { $rep['skipped']++; $rep['lines'][ $r['id'] ] = array( $r['title'], 'تخطّى: ' . $r['why'] ); continue; }
		$p = get_post( $r['id'] );
		if ( ! $p ) { continue; }
		$new = wp_insert_post( wp_slash( array( 'post_type' => 'zad_work', 'post_title' => $p->post_title, 'post_name' => $p->post_name, 'post_content' => $p->post_content, 'post_excerpt' => $p->post_excerpt, 'post_status' => $p->post_status, 'post_author' => $p->post_author, 'post_date' => $p->post_date, 'post_date_gmt' => $p->post_date_gmt ) ), true );
		if ( is_wp_error( $new ) || ! $new ) { $rep['lines'][ $r['id'] ] = array( $r['title'], 'فشل: ' . ( is_wp_error( $new ) ? $new->get_error_message() : '' ) ); continue; }
		if ( $r['thumb'] ) { update_post_meta( $new, '_thumbnail_id', $r['thumb'] ); }
		foreach ( $r['yoast'] as $k ) { $v = get_post_meta( $p->ID, $k, true ); if ( '' !== $v && null !== $v ) { update_post_meta( $new, $k, wp_slash( $v ) ); } }
		update_post_meta( $new, '_zad_wm_from', $p->ID );
		$wpdb->update( $wpdb->posts, array( 'post_name' => $p->post_name ), array( 'ID' => $new ) ); // phpcs:ignore — keeps the exact slug
		clean_post_cache( $new );
		$log['items'][ $p->ID ] = array( 'work' => (int) $new, 'status' => $p->post_status );
		zad_wm_set_status( $p->ID, 'draft' ); // the old page is never deleted, and keeps its dates
		if ( $r['redirect'] ) { $add( $r['old_url'], get_permalink( $new ) ); }
		$rep['moved']++;
		$rep['lines'][ $r['id'] ] = array( $r['title'], 'أُنشئ العمل #' . $new . ' → ' . urldecode( get_permalink( $new ) ) . ' · الصفحة القديمة صارت مسودة' );
	}
	if ( $rep['moved'] && $plan['parent'] ) { $add( $plan['parent']['old_url'], $plan['parent']['new_url'] ); }
	update_option( 'zad_wm_map', $map, false );
	update_option( 'zad_wm_log', $log, false );
	delete_transient( 'zad_work_items' );
	return $rep;
}

function zad_wm_undo() {
	$log = get_option( 'zad_wm_log' ); $n = 0;
	if ( ! is_array( $log ) || empty( $log['items'] ) ) { return 0; }
	foreach ( $log['items'] as $old => $it ) {
		if ( $it['work'] && 'zad_work' === get_post_type( $it['work'] ) ) { wp_trash_post( $it['work'] ); }
		if ( get_post( $old ) ) { zad_wm_set_status( (int) $old, $it['status'] ); }
		$n++;
	}
	$map = (array) get_option( 'zad_wm_map', array() ); foreach ( (array) ( $log['map'] ?? array() ) as $k ) { unset( $map[ $k ] ); } update_option( 'zad_wm_map', $map, false );
	foreach ( (array) ( $log['red'] ?? array() ) as $rid ) { if ( function_exists( 'zad_svcc_red_del' ) ) { zad_svcc_red_del( $rid ); } }
	delete_option( 'zad_wm_log' ); delete_transient( 'zad_work_items' );
	return $n;
}

add_action( 'admin_menu', function () { add_management_page( 'نقل الأعمال القديمة', 'نقل الأعمال القديمة', 'manage_options', 'zad-works-migrate', 'zad_wm_page' ); } );

function zad_wm_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>نقل الأعمال القديمة إلى «أعمالنا»</h1>';
	echo '<p>الأعمال القديمة صفحات عادية تحت صفحة «المشاريع المنجزة». «تجربة» تعرض ما سيحدث دون أي تغيير. «تنفيذ» ينشئ عملاً (zad_work) لكل صفحة بنفس الـ slug والعنوان والمحتوى والملخص والصورة البارزة وحقول Yoast، ويحوّل الصفحة القديمة إلى <b>مسودة</b> (لا تُحذف)، ويسجّل تحويلات 301 من <code>/…/slug/</code> إلى <code>/works/slug/</code> ومن صفحة المشاريع إلى <code>/works/</code>. خُذ نسخة احتياطية قبل التنفيذ.</p>';
	$do = isset( $_POST['zad_wm_do'] ) ? sanitize_key( wp_unslash( $_POST['zad_wm_do'] ) ) : ''; // phpcs:ignore
	if ( $do ) {
		check_admin_referer( 'zad_wm' );
		if ( isset( $_POST['zad_wm_parent'] ) ) { update_option( 'zad_wm_parent_id', absint( $_POST['zad_wm_parent'] ), false ); }
		if ( 'run' === $do ) {
			$rep = zad_wm_run();
			if ( $rep['error'] ) { echo '<div class="notice notice-error"><p>' . esc_html( $rep['error'] ) . '</p></div>'; }
			else {
				echo '<div class="notice notice-success"><p>نُقل <b>' . (int) $rep['moved'] . '</b> · تخطّى <b>' . (int) $rep['skipped'] . '</b> · تحويلات 301 مسجّلة <b>' . (int) $rep['red'] . '</b>. احفظ الروابط الدائمة وامسح كاش LiteSpeed.</p></div><ul style="list-style:disc;margin-inline-start:22px">';
				foreach ( $rep['lines'] as $id => $l ) { echo '<li>' . esc_html( $l[0] ) . ' — ' . esc_html( $l[1] ) . '</li>'; }
				echo '</ul>';
			}
		} elseif ( 'undo' === $do ) { echo '<div class="notice notice-success"><p>تم التراجع عن <b>' . (int) zad_wm_undo() . '</b> صفحة (الأعمال المنشأة في السلة، والصفحات القديمة بحالتها السابقة، وحُذفت التحويلات).</p></div>'; }
	}
	$plan = zad_wm_plan();
	echo '<form method="post">'; wp_nonce_field( 'zad_wm' );
	echo '<p><label>رقم صفحة «المشاريع المنجزة» (الأم): <input type="number" name="zad_wm_parent" value="' . (int) zad_wm_parent_id() . '" style="width:110px"></label></p>';
	if ( $plan['error'] ) { echo '<div class="notice notice-warning inline"><p>' . esc_html( $plan['error'] ) . '</p></div>'; }
	else {
		echo '<h2>' . ( 'preview' === $do ? 'نتيجة التجربة (لم يتغير شيء)' : 'ما سيحدث' ) . '</h2><table class="widefat striped"><thead><tr><th>الصفحة القديمة</th><th>الحالة</th><th>الرابط القديم ← الجديد</th><th>سيُنسخ</th><th>الإجراء</th></tr></thead><tbody>';
		foreach ( $plan['rows'] as $r ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html( $r['title'] ) . '</a> <small>#' . (int) $r['id'] . '</small></td><td>' . esc_html( $r['status'] ) . '</td><td dir="ltr" style="font-size:11px">' . esc_html( urldecode( (string) wp_parse_url( $r['old_url'], PHP_URL_PATH ) ) ) . ' → ' . esc_html( urldecode( (string) wp_parse_url( $r['new_url'], PHP_URL_PATH ) ) ) . ( $r['redirect'] ? '' : '<br>(بلا 301: غير منشورة)' ) . '</td>';
			echo '<td>المحتوى (' . (int) $r['chars'] . ' حرف) · الملخص: ' . ( $r['excerpt'] ? 'نعم' : 'لا' ) . ' · الصورة البارزة: ' . ( $r['thumb'] ? '#' . (int) $r['thumb'] : 'لا' ) . ' · Yoast: ' . count( $r['yoast'] ) . ' حقل</td>';
			echo '<td>' . ( 'move' === $r['action'] ? 'ينشأ عمل، وتصير الصفحة مسودة' : '<span style="color:#b32d2e">' . esc_html( $r['why'] ) . '</span>' ) . '</td></tr>';
		}
		if ( ! $plan['rows'] ) { echo '<tr><td colspan="5">لا توجد صفحات تحت هذه الصفحة الأم.</td></tr>'; }
		echo '</tbody></table><p>تحويل الأرشيف: <code dir="ltr">' . esc_html( urldecode( (string) wp_parse_url( $plan['parent']['old_url'], PHP_URL_PATH ) ) ) . ' → ' . esc_html( urldecode( (string) wp_parse_url( $plan['parent']['new_url'], PHP_URL_PATH ) ) ) . '</code> (يُسجَّل عند أول نقل ناجح).</p>';
	}
	$lg = get_option( 'zad_wm_log' );
	echo '<p style="margin-top:14px"><button class="button" name="zad_wm_do" value="preview">تجربة</button> <button class="button button-primary" name="zad_wm_do" value="run" onclick="return confirm(\'تنفيذ النقل؟ لا يُحذف شيء، وتقدر تتراجع.\');">تنفيذ</button> <button class="button" name="zad_wm_do" value="undo"' . ( is_array( $lg ) && ! empty( $lg['items'] ) ? '' : ' disabled' ) . ' onclick="return confirm(\'التراجع عن آخر نقل؟\');">تراجع</button></p></form></div>';
}
