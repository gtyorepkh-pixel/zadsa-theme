<?php defined( 'ABSPATH' ) || exit;
/**
 * Tools → «التحويلات (زاد)»: the theme redirect map (inc/seo/redirect-engine.php).
 * - Import: copies every enabled plain-URL rule of the Redirection plugin (301/302/307/308 → 301, 410 stays 410). Regex rules are listed, not copied.
 *   The map before the import is kept in zad_redirects_prev; «تراجع عن النقل» puts it back. The Redirection plugin and its table are not touched.
 * - Add, edit and remove a rule by hand. A raw Arabic source is percent-encoded before parse_url() (see zad_url_split), which would otherwise rewrite bytes to "_".
 */

add_action( 'admin_menu', function () {
	add_management_page( 'التحويلات (زاد)', 'التحويلات (زاد)', 'manage_options', 'zad-redirects', 'zad_redirects_page' );
} );

/** Redirection plugin rows: array( 'rules' => [ from => [code, to] ], 'regex' => [ url, ... ], 'skipped' => [ url => reason ] ). */
function zad_redirects_from_plugin() {
	global $wpdb;
	$t   = $wpdb->prefix . 'redirection_items';
	$out = array( 'rules' => array(), 'regex' => array(), 'skipped' => array(), 'table' => false );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) !== $t ) { return $out; } // phpcs:ignore WordPress.DB
	$out['table'] = true;
	foreach ( (array) $wpdb->get_results( "SELECT url, regex, action_type, action_code, action_data, match_type FROM {$t} WHERE status = 'enabled' ORDER BY position, id", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
		if ( (int) $r['regex'] ) { $out['regex'][] = $r['url']; continue; }
		if ( 'url' !== $r['match_type'] ) { $out['skipped'][ $r['url'] ] = 'شرط مطابقة: ' . $r['match_type']; continue; }
		$code = (int) $r['action_code'];
		if ( 'error' === $r['action_type'] && 410 === $code ) { $out['rules'][ $r['url'] ] = array( 410, '' ); continue; }
		if ( 'url' !== $r['action_type'] || ! in_array( $code, array( 301, 302, 307, 308 ), true ) ) { $out['skipped'][ $r['url'] ] = 'نوع: ' . $r['action_type'] . ' ' . $code; continue; }
		$to = function_exists( 'zad_svcc_red_target' ) ? zad_svcc_red_target( $r ) : (string) $r['action_data'];
		if ( '' === $to ) { $out['skipped'][ $r['url'] ] = 'بدون وجهة'; continue; }
		$out['rules'][ $r['url'] ] = array( 301, $to );
	}
	return $out;
}

function zad_redirects_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$msg = '';
	if ( isset( $_POST['zad_rd_do'] ) && check_admin_referer( 'zad_redirects' ) ) {
		$do = sanitize_key( wp_unslash( $_POST['zad_rd_do'] ) );
		if ( 'import' === $do ) {
			$src = zad_redirects_from_plugin();
			update_option( 'zad_redirects_prev', zad_redirect_rules(), false );
			$n = 0; $bad = 0;
			foreach ( $src['rules'] as $from => $r ) { if ( zad_redirect_set( $from, $r[0], $r[1], 'redirection' ) ) { $n++; } else { $bad++; } }
			update_option( 'zad_redirects_imported', time(), false );
			$msg = 'اتنقل ' . $n . ' تحويل' . ( $bad ? '، واتساب ' . $bad . ' (مصدر فاضي أو بيحوّل لنفسه)' : '' ) . '.';
		} elseif ( 'undo' === $do ) {
			update_option( 'zad_redirects', (array) get_option( 'zad_redirects_prev', array() ), true );
			delete_option( 'zad_redirects_imported' );
			$msg = 'رجعت التحويلات زي ما كانت قبل النقل.';
		} elseif ( 'add' === $do ) {
			$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
			$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
			$code = isset( $_POST['code'] ) ? (int) $_POST['code'] : 301;
			list( $fp, $fq ) = zad_url_split( $from );
			$msg  = zad_redirect_set( $fp . ( '' !== $fq ? '?' . $fq : '' ), $code, $to ) ? 'اتضاف التحويل.' : 'ما اتضافش: اكتب رابط المصدر، والوجهة لو 301، ومينفعش الرابط يحوّل لنفسه أو للرئيسية.';
		} elseif ( 'edit' === $do ) {
			$old  = isset( $_POST['key'] ) ? wp_unslash( $_POST['key'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
			$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
			$code = isset( $_POST['code'] ) ? (int) $_POST['code'] : 301;
			$all  = zad_redirect_rules();
			$src  = isset( $all[ $old ] ) ? (string) ( $all[ $old ][2] ?? 'manual' ) : 'manual';
			list( $fp, $fq ) = zad_url_split( $from );
			$new  = zad_redirect_set( $fp . ( '' !== $fq ? '?' . $fq : '' ), $code, $to, $src );
			if ( $new ) {
				if ( '' !== $old && $old !== $new ) { zad_redirect_remove( $old ); }
				$msg = 'اتعدل التحويل' . ( $old !== $new ? ' (الرابط القديم اتغيّر من ' . $old . ' لـ ' . $new . ')' : '' ) . '.';
			} else {
				$msg = 'ما اتعدلش: اكتب رابط المصدر، والوجهة لو 301، ومينفعش الرابط يحوّل لنفسه أو للرئيسية.';
			}
		} elseif ( 'del' === $do ) {
			$msg = zad_redirect_remove( isset( $_POST['key'] ) ? wp_unslash( $_POST['key'] ) : '' ) ? 'اتحذف التحويل.' : 'التحويل مش موجود.'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
	}
	$rules  = zad_redirect_rules();
	$plugin = zad_redirects_from_plugin();
	$active = class_exists( 'Red_Item' );
	$s      = isset( $_GET['s'] ) ? mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$form   = function ( $do, $label, $cls = 'button', $extra = '' ) { return '<form method="post" style="display:inline">' . wp_nonce_field( 'zad_redirects', '_wpnonce', true, false ) . '<input type="hidden" name="zad_rd_do" value="' . esc_attr( $do ) . '">' . $extra . '<button class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>'; };

	echo '<div class="wrap" dir="rtl"><h1>التحويلات (زاد)</h1>';
	if ( $msg ) { echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>'; }
	echo '<p>الثيم بينفّذ التحويلات دي بنفسه قبل ما ووردبريس يحمّل الصفحة، فمش محتاج إضافة Redirection. كمان: روابط خرائط إضافة السيو القديمة (sitemap_index.xml وأخواتها) بتتحوّل لـ /wp-sitemap.xml، والروابط الزبالة (tel: داخل الرابط، esc_url(، ‎?replytocom، ملفات ‎.php مش موجودة) بترجع 410.</p>';

	echo '<h2>النقل من إضافة Redirection</h2>';
	if ( ! $plugin['table'] ) {
		echo '<p>جدول الإضافة مش موجود.</p>';
	} else {
		echo '<p>في الإضافة: <b>' . count( $plugin['rules'] ) . '</b> تحويل ينفع يتنقل' . ( $plugin['regex'] ? '، و<b>' . count( $plugin['regex'] ) . '</b> regex مش هيتنقلوا' : '' ) . ( $plugin['skipped'] ? '، و<b>' . count( $plugin['skipped'] ) . '</b> نوع تاني مش هيتنقلوا' : '' ) . '. الإضافة ' . ( $active ? 'مفعّلة' : 'مش مفعّلة' ) . '.</p>';
		echo '<p>' . $form( 'import', get_option( 'zad_redirects_imported' ) ? 'انقل تاني (يحدّث الموجود)' : 'انقل التحويلات للثيم', 'button button-primary' ) . ' ';
		if ( get_option( 'zad_redirects_imported' ) ) { echo $form( 'undo', 'تراجع عن النقل' ); }
		echo '</p>';
		if ( get_option( 'zad_redirects_imported' ) && $active ) { echo '<p class="description">بعد ما تجرّب كام رابط قديم وتتأكد إنه بيتحوّل صح، تقدر توقف إضافة Redirection من صفحة الإضافات. لو وقفتها، التحويلات الجديدة اللي أداة «تحويل صفحات الخدمات» بتعملها هتتسجل هنا.</p>'; }
		foreach ( array( 'regex' => 'regex (مش هتتنقل — ابعتهم لو محتاجينهم)', 'skipped' => 'أنواع تانية مش هتتنقل' ) as $k => $t ) {
			if ( $plugin[ $k ] ) { echo '<details><summary>' . esc_html( $t ) . '</summary><ul>'; foreach ( $plugin[ $k ] as $a => $b ) { echo '<li dir="ltr"><code>' . esc_html( is_int( $a ) ? $b : $a . ' — ' . $b ) . '</code></li>'; } echo '</ul></details>'; }
		}
	}

	echo '<h2>إضافة تحويل</h2><form method="post">' . wp_nonce_field( 'zad_redirects', '_wpnonce', true, false ) . '<input type="hidden" name="zad_rd_do" value="add">';
	echo '<input name="from" placeholder="/الرابط-القديم/" dir="ltr" style="width:32%"> <select name="code"><option value="301">301 ← تحويل</option><option value="410">410 ← اتشال نهائياً</option></select> <input name="to" placeholder="/الوجهة/ أو https://…" dir="ltr" style="width:32%"> <button class="button">أضف</button></form>';

	echo '<h2>التحويلات الحالية (' . count( $rules ) . ')</h2><form method="get"><input type="hidden" name="page" value="zad-redirects"><input name="s" value="' . esc_attr( $s ) . '" placeholder="بحث"> <button class="button">بحث</button></form>';
	echo '<table class="widefat striped" style="margin-top:8px"><thead><tr><th>من</th><th>الكود</th><th>إلى</th><th>المصدر</th><th></th></tr></thead><tbody>';
	$i = 0;
	foreach ( $rules as $k => $r ) {
		if ( '' !== $s && false === mb_strpos( $k . ' ' . mb_strtolower( (string) $r[1] ), $s ) ) { continue; }
		if ( ++$i > 500 ) { echo '<tr><td colspan="5">… استخدم البحث لعرض الباقي.</td></tr>'; break; }
		$edit = '<details style="display:inline-block;vertical-align:top"><summary class="button button-small" style="list-style:none;cursor:pointer">تعديل</summary>'
			. '<form method="post" style="margin:8px 0;padding:8px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;min-width:320px">'
			. wp_nonce_field( 'zad_redirects', '_wpnonce', true, false )
			. '<input type="hidden" name="zad_rd_do" value="edit"><input type="hidden" name="key" value="' . esc_attr( $k ) . '">'
			. '<p style="margin:0 0 6px"><label>الرابط القديم<br><input name="from" value="' . esc_attr( $k ) . '" dir="ltr" style="width:100%"></label></p>'
			. '<p style="margin:0 0 6px"><label>الكود<br><select name="code"><option value="301"' . selected( (int) $r[0], 301, false ) . '>301 ← تحويل</option><option value="410"' . selected( (int) $r[0], 410, false ) . '>410 ← اتشال نهائياً</option></select></label></p>'
			. '<p style="margin:0 0 6px"><label>الرابط الجديد<br><input name="to" value="' . esc_attr( (string) $r[1] ) . '" dir="ltr" style="width:100%" placeholder="/الوجهة/ أو https://…"></label></p>'
			. '<button class="button button-primary button-small">حفظ</button></form></details>';
		echo '<tr><td dir="ltr"><a href="' . esc_url( home_url( zad_redirect_encode( $k ) ) ) . '" target="_blank">' . esc_html( $k ) . '</a></td><td>' . (int) $r[0] . '</td><td dir="ltr">' . esc_html( (string) $r[1] ) . '</td><td>' . esc_html( (string) ( $r[2] ?? '' ) ) . '</td><td>' . $edit . ' ' . $form( 'del', 'حذف', 'button-link-delete', '<input type="hidden" name="key" value="' . esc_attr( $k ) . '">' ) . '</td></tr>';
	}
	if ( ! $rules ) { echo '<tr><td colspan="5">مفيش تحويلات لسه.</td></tr>'; }
	echo '</tbody></table></div>';
}
