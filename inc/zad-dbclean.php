<?php
/**
 * Database cleanup (Tools → تنظيف قاعدة البيانات).
 * Scan first, delete only what the admin ticks. Never touches core tables, theme data
 * (zad*, zsc*, memo options), or anything owned by a plugin that is still active.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'تنظيف قاعدة البيانات', 'تنظيف قاعدة البيانات (زاد)', 'manage_options', 'zad-dbclean', 'zad_dbclean_page' );
} );

/** Known plugins: folder(s), table regex (after prefix), option prefixes, deletable postmeta prefixes, label. */
function zad_dbc_owners() {
	return array(
		'elementor'  => array( 'label' => 'Elementor', 'dirs' => array( 'elementor', 'elementor-pro' ), 'tables' => '/^(e_|elementor)/', 'options' => array( 'elementor', '_elementor', 'e_' ), 'meta' => array( '_elementor_' ) ),
		'yoast'      => array( 'label' => 'Yoast SEO', 'dirs' => array( 'wordpress-seo', 'wordpress-seo-premium' ), 'tables' => '/^yoast_/', 'options' => array( 'wpseo', 'yoast_' ), 'meta' => array() ),
		'rankmath'   => array( 'label' => 'Rank Math', 'dirs' => array( 'seo-by-rank-math', 'seo-by-rank-math-pro' ), 'tables' => '/^rank_math_/', 'options' => array( 'rank_math', 'rank-math' ), 'meta' => array() ),
		'schemapro'  => array( 'label' => 'Schema Pro', 'dirs' => array( 'wp-schema-pro' ), 'tables' => '/^$^/', 'options' => array( 'wp-schema-pro', 'aiosrs' ), 'meta' => array( 'bsf-aiosrs-' ) ),
		'woo'        => array( 'label' => 'WooCommerce', 'dirs' => array( 'woocommerce' ), 'tables' => '/^(woocommerce_|wc_)/', 'options' => array( 'woocommerce_' ), 'meta' => array() ),
		'actionsch'  => array( 'label' => 'Action Scheduler', 'dirs' => array( 'woocommerce', 'action-scheduler', 'wpforms', 'wpforms-lite', 'mailpoet', 'elementor-pro', 'wp-mail-smtp' ), 'tables' => '/^actionscheduler_/', 'options' => array( 'action_scheduler' ), 'meta' => array() ),
		'wpforms'    => array( 'label' => 'WPForms', 'dirs' => array( 'wpforms', 'wpforms-lite' ), 'tables' => '/^wpforms_/', 'options' => array( 'wpforms' ), 'meta' => array() ),
		'cf7'        => array( 'label' => 'Contact Form 7', 'dirs' => array( 'contact-form-7' ), 'tables' => '/^(cf7|wpcf7)/', 'options' => array( 'wpcf7' ), 'meta' => array() ),
		'litespeed'  => array( 'label' => 'LiteSpeed Cache', 'dirs' => array( 'litespeed-cache' ), 'tables' => '/^litespeed_/', 'options' => array( 'litespeed' ), 'meta' => array() ),
		'jetpack'    => array( 'label' => 'Jetpack', 'dirs' => array( 'jetpack' ), 'tables' => '/^jetpack_/', 'options' => array( 'jetpack' ), 'meta' => array() ),
		'wordfence'  => array( 'label' => 'Wordfence', 'dirs' => array( 'wordfence' ), 'tables' => '/^wf[A-Z]/', 'options' => array( 'wordfence', 'wf_' ), 'meta' => array() ),
		'updraft'    => array( 'label' => 'UpdraftPlus', 'dirs' => array( 'updraftplus' ), 'tables' => '/^$^/', 'options' => array( 'updraft' ), 'meta' => array() ),
		'smush'      => array( 'label' => 'Smush', 'dirs' => array( 'wp-smushit', 'wp-smush-pro' ), 'tables' => '/^smush_/', 'options' => array( 'wp-smush', 'wp_smush', 'smush' ), 'meta' => array() ),
		'redirection' => array( 'label' => 'Redirection', 'dirs' => array( 'redirection' ), 'tables' => '/^redirection_/', 'options' => array( 'redirection' ), 'meta' => array() ),
	);
}

/** Core tables (without prefix). */
function zad_dbc_core_tables() {
	return array( 'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts', 'term_relationships', 'term_taxonomy', 'termmeta', 'terms', 'usermeta', 'users' );
}

/** Option-name prefixes that are ours / core and are never touched. */
function zad_dbc_protected_opt( $name ) {
	foreach ( array( 'zad', 'zsc', '_memo', 'memo', 'theme_mods_', 'widget_', 'sidebars_', 'wp_', 'active_plugins', 'siteurl', 'home', 'blog', 'template', 'stylesheet' ) as $p ) {
		if ( 0 === strpos( $name, $p ) ) { return true; }
	}
	return false;
}

function zad_dbc_active_owner( $o ) {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) { $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ); }
	foreach ( $active as $f ) {
		foreach ( $o['dirs'] as $d ) {
			if ( 0 === strpos( $f, $d . '/' ) || $f === $d . '.php' ) { return true; }
		}
	}
	return false;
}

/** Classify one extra table. Returns array( status, owner label ). status: protected|active|orphan|unknown. */
function zad_dbc_classify_table( $short ) {
	if ( 0 === strpos( $short, 'zad' ) ) { return array( 'protected', 'الثيم (زاد)' ); }
	foreach ( zad_dbc_owners() as $o ) {
		if ( preg_match( $o['tables'], $short ) ) {
			return array( zad_dbc_active_owner( $o ) ? 'active' : 'orphan', $o['label'] );
		}
	}
	return array( 'unknown', 'غير معروف' );
}

function zad_dbc_scan() {
	global $wpdb;
	$pre = $wpdb->prefix;
	$res = array( 'tables' => array(), 'options' => array(), 'meta' => array(), 'misc' => array() );

	// Tables.
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME AS n, DATA_LENGTH + INDEX_LENGTH AS sz FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s', DB_NAME, $wpdb->esc_like( $pre ) . '%' ), ARRAY_A );
	foreach ( (array) $rows as $r ) {
		$short = substr( $r['n'], strlen( $pre ) );
		if ( in_array( $short, zad_dbc_core_tables(), true ) ) { continue; }
		// Multisite per-blog core tables like 2_posts: leave alone.
		if ( preg_match( '/^\d+_/', $short ) ) { continue; }
		list( $st, $owner ) = zad_dbc_classify_table( $short );
		$cnt = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $r['n'] ) . '`' ); // phpcs:ignore
		$res['tables'][ $r['n'] ] = array( 'size' => (int) $r['sz'], 'rows' => $cnt, 'status' => $st, 'owner' => $owner );
	}

	// Options owned by inactive known plugins.
	foreach ( zad_dbc_owners() as $k => $o ) {
		if ( zad_dbc_active_owner( $o ) ) { continue; }
		foreach ( $o['options'] as $p ) {
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $p ) . '%' ) );
			foreach ( $names as $n ) {
				if ( zad_dbc_protected_opt( $n ) || 0 === strpos( $n, '_transient' ) ) { continue; }
				$res['options'][ $n ] = $o['label'];
			}
		}
	}

	// Postmeta of inactive owners that is safe to drop (Elementor / Schema Pro).
	foreach ( zad_dbc_owners() as $o ) {
		if ( zad_dbc_active_owner( $o ) ) { continue; }
		foreach ( $o['meta'] as $p ) {
			$c = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( $p ) . '%' ) );
			if ( $c ) { $res['meta'][ $p ] = array( 'count' => $c, 'owner' => $o['label'] ); }
		}
	}

	// Housekeeping counts.
	$res['misc']['transients']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
	$res['misc']['revisions']   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_modified < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
	$res['misc']['autodrafts']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='auto-draft' AND post_modified < %s", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
	$res['misc']['orph_meta']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
	$res['misc']['orph_termmeta'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL" );
	$res['misc']['orph_rel']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL AND tr.term_taxonomy_id IN (SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('category','post_tag'))" );
	$res['misc']['spam']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );
	return $res;
}

function zad_dbc_fmt( $b ) { return $b >= 1048576 ? round( $b / 1048576, 1 ) . ' MB' : round( $b / 1024 ) . ' KB'; }

function zad_dbc_run( $scan ) {
	global $wpdb;
	$log = array();
	$sel_t = isset( $_POST['t'] ) ? (array) wp_unslash( $_POST['t'] ) : array(); // phpcs:ignore
	foreach ( $sel_t as $t ) {
		$t = (string) $t;
		// Only tables from the fresh scan, and only orphan/unknown ones.
		if ( ! isset( $scan['tables'][ $t ] ) || ! in_array( $scan['tables'][ $t ]['status'], array( 'orphan', 'unknown' ), true ) ) { continue; }
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $t ) . '`' ); // phpcs:ignore
		$log[] = 'حُذف الجدول ' . $t;
	}
	$sel_o = isset( $_POST['o'] ) ? (array) wp_unslash( $_POST['o'] ) : array(); // phpcs:ignore
	$n = 0;
	foreach ( $sel_o as $o ) {
		if ( isset( $scan['options'][ $o ] ) && delete_option( (string) $o ) ) { $n++; }
	}
	if ( $n ) { $log[] = "حُذف $n خياراً من بقايا إضافات محذوفة"; }
	$sel_m = isset( $_POST['m'] ) ? (array) wp_unslash( $_POST['m'] ) : array(); // phpcs:ignore
	foreach ( $sel_m as $p ) {
		if ( isset( $scan['meta'][ $p ] ) ) {
			$c = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( $p ) . '%' ) );
			$log[] = "حُذف $c سطر postmeta ($p*)";
		}
	}
	$x = isset( $_POST['x'] ) ? (array) wp_unslash( $_POST['x'] ) : array(); // phpcs:ignore
	if ( in_array( 'transients', $x, true ) ) {
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
		foreach ( $names as $nm ) { delete_option( $nm ); delete_option( str_replace( '_timeout', '', $nm ) ); }
		$log[] = 'حُذفت ' . count( $names ) . ' من الـ transients المنتهية';
	}
	if ( in_array( 'revisions', $x, true ) ) {
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='revision' AND post_modified < %s LIMIT 2000", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		foreach ( $ids as $id ) { wp_delete_post_revision( (int) $id ); }
		$log[] = 'حُذفت ' . count( $ids ) . ' مراجعة أقدم من 30 يوماً (دفعة حتى 2000)';
	}
	if ( in_array( 'autodrafts', $x, true ) ) {
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status='auto-draft' AND post_modified < %s LIMIT 2000", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		foreach ( $ids as $id ) { wp_delete_post( (int) $id, true ); }
		$log[] = 'حُذفت ' . count( $ids ) . ' مسودة تلقائية';
	}
	if ( in_array( 'orph_meta', $x, true ) ) {
		$c = $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
		$log[] = "حُذف $c سطر postmeta يتيم";
	}
	if ( in_array( 'orph_termmeta', $x, true ) ) {
		$c = $wpdb->query( "DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL" );
		$log[] = "حُذف $c سطر termmeta يتيم";
	}
	if ( in_array( 'spam', $x, true ) ) {
		$c = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );
		$log[] = "حُذف $c تعليق مزعج/محذوف";
	}
	wp_cache_flush();
	return $log;
}

function zad_dbclean_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$log = array();
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['zad_dbc_go'] ) ) { // phpcs:ignore
		check_admin_referer( 'zad_dbc' );
		if ( empty( $_POST['backup'] ) || ( isset( $_POST['word'] ) ? trim( wp_unslash( $_POST['word'] ) ) : '' ) !== 'حذف' ) { // phpcs:ignore
			$log[] = 'لم يُنفَّذ شيء: يجب تأكيد النسخة الاحتياطية وكتابة كلمة «حذف».';
		} else {
			$log = zad_dbc_run( zad_dbc_scan() );
			if ( ! $log ) { $log[] = 'لم يُحدَّد شيء للحذف.'; }
		}
	}
	$s = zad_dbc_scan();
	$badge = array( 'protected' => array( 'محمي', '#0c687e' ), 'active' => array( 'إضافة مفعّلة', '#0c687e' ), 'orphan' => array( 'يتيم (إضافة محذوفة)', '#b45f00' ), 'unknown' => array( 'غير معروف — راجعه', '#b91c1c' ) );
	echo '<div class="wrap" dir="rtl"><h1>تنظيف قاعدة البيانات</h1>';
	echo '<div class="notice notice-warning"><p><b>خذ نسخة احتياطية كاملة من قاعدة البيانات قبل أي حذف.</b> الأداة لا تحذف جداول ووردبريس الأساسية ولا بيانات الثيم (zad / zsc / memo) ولا ما تملكه إضافة مفعّلة. الحذف نهائي.</p></div>';
	foreach ( $log as $l ) { echo '<div class="notice notice-info"><p>' . esc_html( $l ) . '</p></div>'; }
	echo '<form method="post">'; wp_nonce_field( 'zad_dbc' );

	echo '<h2>1) جداول إضافية</h2>';
	if ( ! $s['tables'] ) { echo '<p>لا توجد جداول إضافية.</p>'; } else {
		echo '<table class="widefat striped"><thead><tr><th></th><th>الجدول</th><th>الصاحب</th><th>الصفوف</th><th>الحجم</th><th>الحالة</th></tr></thead><tbody>';
		foreach ( $s['tables'] as $n => $t ) {
			$can = in_array( $t['status'], array( 'orphan', 'unknown' ), true );
			$b = $badge[ $t['status'] ];
			echo '<tr><td>' . ( $can ? '<input type="checkbox" name="t[]" value="' . esc_attr( $n ) . '"' . ( 'orphan' === $t['status'] ? ' checked' : '' ) . '>' : '—' ) . '</td><td dir="ltr"><code>' . esc_html( $n ) . '</code></td><td>' . esc_html( $t['owner'] ) . '</td><td>' . (int) $t['rows'] . ( 0 === $t['rows'] ? ' (فارغ)' : '' ) . '</td><td>' . esc_html( zad_dbc_fmt( $t['size'] ) ) . '</td><td style="color:' . esc_attr( $b[1] ) . '"><b>' . esc_html( $b[0] ) . '</b></td></tr>';
		}
		echo '</tbody></table><p class="description">الجداول «اليتيمة» محددة مسبقاً. «غير معروف» غير محدد — احذفه فقط إذا تأكدت أنه بقايا إضافة لا تحتاجها.</p>';
	}

	echo '<h2>2) خيارات (options) بقايا إضافات غير مفعّلة</h2>';
	if ( ! $s['options'] ) { echo '<p>لا شيء.</p>'; } else {
		echo '<p><label><input type="checkbox" onclick="jQuery(\'.zo\').prop(\'checked\',this.checked)"> تحديد الكل</label></p><div style="max-height:260px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:8px" dir="ltr">';
		foreach ( $s['options'] as $n => $own ) { echo '<label style="display:block"><input class="zo" type="checkbox" name="o[]" value="' . esc_attr( $n ) . '"> <code>' . esc_html( $n ) . '</code> — ' . esc_html( $own ) . '</label>'; }
		echo '</div>';
	}

	echo '<h2>3) بيانات صفحات (postmeta) لإضافات غير مفعّلة</h2>';
	if ( ! $s['meta'] ) { echo '<p>لا شيء.</p>'; } else {
		foreach ( $s['meta'] as $p => $m ) { echo '<label style="display:block"><input type="checkbox" name="m[]" value="' . esc_attr( $p ) . '"> <code dir="ltr">' . esc_html( $p ) . '*</code> — ' . esc_html( $m['owner'] ) . ' — ' . (int) $m['count'] . ' سطر</label>'; }
		echo '<p class="description">تنبيه: بيانات Elementor قد تكون هي المحتوى الفعلي لصفحة قديمة؛ تأكد أن كل الصفحات المهمة لها محتوى عادي قبل الحذف. بيانات Yoast/Rank Math لا تُحذف أبداً لأن الثيم يقرأ عناوين وأوصاف السيو منها.</p>';
	}

	echo '<h2>4) تنظيف عام</h2>';
	$m = $s['misc'];
	$items = array( 'transients' => 'transients منتهية', 'revisions' => 'مراجعات أقدم من 30 يوماً', 'autodrafts' => 'مسودات تلقائية أقدم من 7 أيام', 'orph_meta' => 'postmeta يتيم (بدون مقال)', 'orph_termmeta' => 'termmeta يتيم', 'spam' => 'تعليقات مزعجة / في المحذوفات' );
	foreach ( $items as $k => $lbl ) { echo '<label style="display:block"><input type="checkbox" name="x[]" value="' . esc_attr( $k ) . '"' . ( empty( $m[ $k ] ) ? ' disabled' : '' ) . '> ' . esc_html( $lbl ) . ' — <b>' . (int) ( $m[ $k ] ?? 0 ) . '</b></label>'; }

	echo '<hr><p><label><input type="checkbox" name="backup" value="1"> أكّد أنني أخذت نسخة احتياطية كاملة من قاعدة البيانات</label></p>';
	echo '<p>اكتب كلمة <b>حذف</b> للتأكيد: <input type="text" name="word" size="8" autocomplete="off"></p>';
	echo '<p><button class="button button-primary" name="zad_dbc_go" value="1" onclick="return confirm(\'الحذف نهائي. متابعة؟\')">حذف المحدد</button></p></form></div>';
}
