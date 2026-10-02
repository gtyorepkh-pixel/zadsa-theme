<?php defined( 'ABSPATH' ) || exit;
/**
 * Move existing pages into a service section (custom post type) WITHOUT losing their old URLs:
 * the old path is stored on the post and a 301 redirect is served automatically.
 * Tools → «نقل الصفحات إلى قسم». Two steps: preview (old → new) then confirm.
 */

const ZAD_OLD_URLS = '_zad_old_urls';

/** Normalised request path ("/a/b/") — decoded, lower-cased, no query. */
function zad_move_path( $url ) {
	$p = (string) wp_parse_url( $url, PHP_URL_PATH );
	$h = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '/' !== $h && 0 === strpos( $p, rtrim( $h, '/' ) . '/' ) ) { $p = substr( $p, strlen( rtrim( $h, '/' ) ) ); }
	return '/' . trim( mb_strtolower( rawurldecode( $p ), 'UTF-8' ), '/' ) . '/';
}

/* 301 from a stored old URL, only when the request would otherwise be a 404 */
add_action( 'template_redirect', function () {
	if ( ! is_404() || is_admin() ) { return; }
	$req = zad_move_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore
	if ( '/' === $req ) { return; }
	$seg = trim( $req, '/' );
	$cands = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 20, 'meta_query' => array( array( 'key' => ZAD_OLD_URLS, 'value' => $seg, 'compare' => 'LIKE' ) ), 'suppress_filters' => true ) );
	foreach ( $cands as $p ) {
		foreach ( preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $p->ID, ZAD_OLD_URLS, true ) ) as $line ) {
			if ( '' !== trim( $line ) && zad_move_path( $line ) === $req ) {
				wp_safe_redirect( get_permalink( $p ), 301 );
				exit;
			}
		}
	}
}, 1 );

add_action( 'admin_menu', function () {
	add_management_page( 'نقل الصفحات إلى قسم', 'نقل الصفحات إلى قسم (زاد)', 'manage_options', 'zad-move', 'zad_move_page' );
} );

function zad_move_target_types() {
	$out = array();
	foreach ( zad_service_types() as $pt ) {
		$o = get_post_type_object( $pt );
		if ( $o && $o->hierarchical ) { $out[ $pt ] = $o->labels->name . ' (' . $pt . ')'; }
	}
	return $out;
}

function zad_move_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$targets = zad_move_target_types();
	echo '<div class="wrap" dir="rtl"><h1>نقل الصفحات إلى قسم</h1>';
	echo '<div class="notice notice-warning"><p><strong>غيّر الروابط بحذر:</strong> خذ نسخة احتياطية أولاً وجرّب على نسخة تجريبية. تُحفظ الروابط القديمة وتُحوَّل تلقائياً بـ301. بعد النقل حدّث الروابط الداخلية داخل المحتوى (بحث واستبدال)، وأعد إرسال خريطة الموقع في Search Console.</p></div>';
	if ( ! $targets ) { echo '<p>لا يوجد نوع محتوى هرمي للخدمات بعد. سجّل الأقسام الجديدة أولاً (في zad-core-cpt.php).</p></div>'; return; }

	$step = isset( $_POST['zad_move_step'] ) ? sanitize_key( wp_unslash( $_POST['zad_move_step'] ) ) : ''; // phpcs:ignore
	$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore
	$tgt  = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : ''; // phpcs:ignore
	$slug = isset( $_POST['slug'] ) ? (array) wp_unslash( $_POST['slug'] ) : array(); // phpcs:ignore

	if ( $step && ( ! check_admin_referer( 'zad_move' ) || ! isset( $targets[ $tgt ] ) || ! $ids ) ) { $step = ''; echo '<div class="notice notice-error"><p>اختر الصفحات والقسم الهدف.</p></div>'; }

	if ( 'run' === $step ) {
		$done = 0;
		foreach ( $ids as $id ) {
			$p = get_post( $id );
			if ( ! $p || 'page' !== $p->post_type ) { continue; }
			$old  = zad_move_path( get_permalink( $p ) );
			$name = isset( $slug[ $id ] ) ? sanitize_title( $slug[ $id ] ) : $p->post_name;
			$r    = wp_update_post( array( 'ID' => $id, 'post_type' => $tgt, 'post_name' => $name ?: $p->post_name, 'post_parent' => 0 ), true );
			if ( is_wp_error( $r ) ) { continue; }
			$prev = (string) get_post_meta( $id, ZAD_OLD_URLS, true );
			update_post_meta( $id, ZAD_OLD_URLS, trim( $prev . "\n" . $old ) );
			delete_post_meta( $id, '_wp_page_template' );
			$done++;
		}
		delete_option( 'zad_rw_hash' );
		flush_rewrite_rules( false );
		echo '<div class="notice notice-success"><p>تم نقل ' . (int) $done . ' صفحة، وحُفظت روابطها القديمة للتحويل 301. افتح الروابط الجديدة وتحقق منها.</p></div>';
		$step = '';
	}

	if ( 'preview' === $step ) {
		echo '<h2>معاينة (لم يُنفَّذ شيء بعد)</h2><form method="post">';
		wp_nonce_field( 'zad_move' );
		echo '<input type="hidden" name="target" value="' . esc_attr( $tgt ) . '"><input type="hidden" name="zad_move_step" value="run"><table class="widefat striped"><thead><tr><th>الرابط القديم</th><th>الاسم في الرابط الجديد (عدّله لو أردت)</th><th>الرابط الجديد (تقريباً)</th></tr></thead><tbody>';
		$base = trailingslashit( home_url( '/' . ( get_post_type_object( $tgt )->rewrite['slug'] ?? $tgt ) ) );
		foreach ( $ids as $id ) {
			$p = get_post( $id );
			if ( ! $p ) { continue; }
			echo '<tr><td><input type="hidden" name="ids[]" value="' . (int) $id . '"><code dir="ltr">' . esc_html( urldecode( zad_move_path( get_permalink( $p ) ) ) ) . '</code><br>' . esc_html( $p->post_title ) . '</td><td><input type="text" name="slug[' . (int) $id . ']" value="' . esc_attr( $p->post_name ) . '" class="regular-text" dir="ltr"></td><td><code dir="ltr">' . esc_html( urldecode( $base ) ) . '…</code></td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary">تنفيذ النقل وتفعيل التحويل 301</button></p></form></div>';
		return;
	}

	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<form method="post">';
	wp_nonce_field( 'zad_move' );
	echo '<input type="hidden" name="zad_move_step" value="preview"><p><label>القسم الهدف: <select name="target">';
	foreach ( $targets as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>'; }
	echo '</select></label> &nbsp; <input type="search" id="zmq" placeholder="ابحث في الصفحات…" class="regular-text"></p><table class="widefat striped" id="zmt"><thead><tr><th style="width:40px"></th><th>الصفحة</th><th>الرابط الحالي</th></tr></thead><tbody>';
	foreach ( $pages as $p ) {
		echo '<tr><td><input type="checkbox" name="ids[]" value="' . (int) $p->ID . '"></td><td>' . esc_html( $p->post_title ) . '</td><td><code dir="ltr">' . esc_html( urldecode( zad_move_path( get_permalink( $p ) ) ) ) . '</code></td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary">معاينة النقل</button></p></form>';
	echo '<script>document.getElementById("zmq").addEventListener("input",function(){var q=this.value.toLowerCase();document.querySelectorAll("#zmt tbody tr").forEach(function(r){r.hidden=q&&r.textContent.toLowerCase().indexOf(q)<0;});});</script></div>';
}
