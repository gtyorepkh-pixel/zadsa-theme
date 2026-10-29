<?php
/**
 * أدوات ← «نسخ باقات الأسعار بين المدن (زاد)».
 *
 * بتنسخ حقل «باقات الأسعار» (`_zad_packages`) من صفحة الخدمة في مدينة المصدر إلى الصفحة
 * المقابلة لها في مدينة الهدف — المقابلة بتتحدد من الرابط نفسه: نفس المسار وبس المدينة بتتغيّر
 * (`/cleaning/riyadh/villas/` ← → `/cleaning/jeddah/villas/`). الحالات اللي اسمها مختلف
 * بتتحدد يدويًا في `zad_pkgcopy_overrides`.
 *
 * قواعد:
 *  - بتلمس حقل الباقات بس. مفيش أي حقل تاني ولا محتوى ولا عنوان ولا رابط بيتغيّر.
 *  - معاينة كاملة قبل التنفيذ: كل زوج، وعدد السطور قبل وبعد، وحالته.
 *  - نسخة من النص القديم في `_zad_pkgcopy_backup` + سجل في خيار، وزر «تراجع عن الكل».
 *  - سطور الباقات مفيهاش أسماء مدن (اتأكدنا)، فالنسخ حرفي بدون أي استبدال.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_PKGCOPY_META   = '_zad_packages';
const ZAD_PKGCOPY_BACKUP = '_zad_pkgcopy_backup';
const ZAD_PKGCOPY_LOG    = 'zad_pkgcopy_log';

/** حالات الاسم المختلف: مسار الهدف => مسار المصدر. فلتر: zad_pkgcopy_overrides. */
function zad_pkgcopy_overrides() {
	return (array) apply_filters( 'zad_pkgcopy_overrides', array(
		'/cleaning/jeddah/apartment/' => '/cleaning/riyadh/apartments/',
		'/cleaning/carpet-in-jeddah/' => '/cleaning/carpet-cleaning-in-riyadh/',
	) );
}

/** كل صفحات الخدمات المنشورة: مسار الرابط => معرّف الصفحة. */
function zad_pkgcopy_index() {
	static $idx = null;
	if ( null !== $idx ) { return $idx; }
	$idx = array();
	foreach ( (array) get_posts( array(
		'post_type'   => zad_service_types(),
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'zad_all'     => true,
	) ) as $id ) {
		$p = wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH );
		if ( $p ) { $idx[ rawurldecode( (string) $p ) ] = (int) $id; }
	}
	return $idx;
}

/**
 * أزواج النسخ: array( array( dest_id, src_id, dest_path, src_path, before, after, state ) ).
 * state: 'empty' (الهدف فاضي) · 'diff' (مختلف) · 'same' (متطابق — مش محتاج تنفيذ).
 */
function zad_pkgcopy_pairs( $from, $to ) {
	$idx  = zad_pkgcopy_index();
	$ovr  = zad_pkgcopy_overrides();
	$out  = array();
	$seen = array();
	foreach ( $idx as $path => $id ) {
		$src_path = isset( $ovr[ $path ] ) ? $ovr[ $path ] : null;
		if ( null === $src_path ) {
			if ( false === strpos( $path, $to ) ) { continue; }
			$src_path = str_replace( $to, $from, $path );
		}
		if ( $src_path === $path || ! isset( $idx[ $src_path ] ) || isset( $seen[ $path ] ) ) { continue; }
		$seen[ $path ] = true;
		$src_id = $idx[ $src_path ];
		$before = (string) get_post_meta( $id, ZAD_PKGCOPY_META, true );
		$after  = (string) get_post_meta( $src_id, ZAD_PKGCOPY_META, true );
		if ( '' === trim( $after ) ) { continue; }                      // المصدر نفسه فاضي: مفيش حاجة تُنسخ
		$state = '' === trim( $before ) ? 'empty' : ( trim( $before ) === trim( $after ) ? 'same' : 'diff' );
		$out[] = array( $id, $src_id, $path, $src_path, $before, $after, $state );
	}
	return $out;
}

/** عدد سطور الباقات في نص. */
function zad_pkgcopy_lines( $raw ) {
	return count( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $raw ) ) ) );
}

add_action( 'admin_menu', function () {
	add_management_page( 'نسخ باقات الأسعار بين المدن', 'نسخ باقات الأسعار (زاد)', 'manage_options', 'zad-pkgcopy', 'zad_pkgcopy_page' );
} );

function zad_pkgcopy_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }

	$cities = function_exists( 'zad_city_map' ) ? zad_city_map() : array();
	$from   = isset( $_GET['from'] ) ? sanitize_key( wp_unslash( $_GET['from'] ) ) : 'riyadh';
	$to     = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : 'jeddah';

	echo '<div class="wrap" dir="rtl" style="max-width:1000px"><h1>نسخ باقات الأسعار بين المدن</h1>';

	if ( isset( $_POST['zad_pkgcopy_do'] ) && check_admin_referer( 'zad_pkgcopy' ) ) {
		$do = sanitize_key( wp_unslash( $_POST['zad_pkgcopy_do'] ) );
		if ( 'run' === $do ) {
			$log = (array) get_option( ZAD_PKGCOPY_LOG, array() );
			$n   = 0;
			foreach ( zad_pkgcopy_pairs( $from, $to ) as $p ) {
				list( $id, , , , $before, $after, $state ) = $p;
				if ( 'same' === $state ) { continue; }
				if ( ! metadata_exists( 'post', $id, ZAD_PKGCOPY_BACKUP ) ) { update_post_meta( $id, ZAD_PKGCOPY_BACKUP, $before ); }
				update_post_meta( $id, ZAD_PKGCOPY_META, $after );
				$log[ $id ] = 1;
				$n++;
			}
			update_option( ZAD_PKGCOPY_LOG, $log, false );
			if ( function_exists( 'zad_city_bump' ) ) { zad_city_bump(); }   // الكتالوج والأسعار مخزَّنين مؤقتًا
			echo '<div class="notice notice-success"><p>اتنسخت الباقات في <strong>' . (int) $n . '</strong> صفحة.</p></div>';
		} elseif ( 'undo' === $do ) {
			$log = (array) get_option( ZAD_PKGCOPY_LOG, array() );
			$n   = 0;
			foreach ( array_keys( $log ) as $id ) {
				if ( ! metadata_exists( 'post', (int) $id, ZAD_PKGCOPY_BACKUP ) ) { continue; }
				update_post_meta( (int) $id, ZAD_PKGCOPY_META, (string) get_post_meta( (int) $id, ZAD_PKGCOPY_BACKUP, true ) );
				delete_post_meta( (int) $id, ZAD_PKGCOPY_BACKUP );
				$n++;
			}
			delete_option( ZAD_PKGCOPY_LOG );
			if ( function_exists( 'zad_city_bump' ) ) { zad_city_bump(); }
			echo '<div class="notice notice-warning"><p>رجعت <strong>' . (int) $n . '</strong> صفحة لباقاتها القديمة.</p></div>';
		}
	}

	echo '<p>بتنسخ <strong>حقل «باقات الأسعار» بس</strong> من صفحة مدينة المصدر إلى الصفحة المقابلة في مدينة الهدف. المقابلة من الرابط: نفس المسار والمدينة بس هي اللي تتغيّر.</p>';

	echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="zad-pkgcopy">';
	foreach ( array( 'from' => 'من مدينة', 'to' => 'إلى مدينة' ) as $k => $lbl ) {
		$cur = 'from' === $k ? $from : $to;
		echo '<label style="margin-inline-end:14px">' . esc_html( $lbl ) . ' <select name="' . esc_attr( $k ) . '">';
		foreach ( $cities as $slug => $name ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $slug, $cur, false ) . '>' . esc_html( $name ) . ' (' . esc_html( $slug ) . ')</option>';
		}
		echo '</select></label>';
	}
	echo ' <button class="button">اعرض</button></form>';

	$pairs = zad_pkgcopy_pairs( $from, $to );
	$todo  = array_filter( $pairs, function ( $p ) { return 'same' !== $p[6]; } );

	echo '<h2>' . count( $pairs ) . ' زوج مطابق · ' . count( $todo ) . ' محتاج تنفيذ</h2>';
	if ( ! $pairs ) {
		echo '<p>مفيش صفحة في «' . esc_html( $to ) . '» لها نظير في «' . esc_html( $from ) . '» بنفس المسار. لو الاسم مختلف، أضفه في فلتر <code>zad_pkgcopy_overrides</code>.</p>';
	} else {
		$lbl = array( 'empty' => 'الهدف فاضي — هيتملي', 'diff' => 'مختلف — هيتغيّر', 'same' => 'متطابق — مش محتاج' );
		echo '<table class="widefat striped"><thead><tr><th>صفحة الهدف</th><th>المصدر</th><th>سطور الآن</th><th>سطور بعد النسخ</th><th>الحالة</th></tr></thead><tbody>';
		foreach ( $pairs as $p ) {
			list( $id, $src_id, $path, $src_path, $before, $after, $state ) = $p;
			echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( $path ) . '</a></td>'
				. '<td><a href="' . esc_url( (string) get_edit_post_link( $src_id ) ) . '">' . esc_html( $src_path ) . '</a></td>'
				. '<td>' . (int) zad_pkgcopy_lines( $before ) . '</td><td>' . (int) zad_pkgcopy_lines( $after ) . '</td>'
				. '<td>' . esc_html( $lbl[ $state ] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<form method="post" style="margin-top:14px">';
	wp_nonce_field( 'zad_pkgcopy' );
	if ( $todo ) { echo '<button class="button button-primary" name="zad_pkgcopy_do" value="run">انسخ الباقات (' . count( $todo ) . ' صفحة)</button> '; }
	if ( get_option( ZAD_PKGCOPY_LOG ) ) { echo '<button class="button" name="zad_pkgcopy_do" value="undo">تراجع عن الكل</button>'; }
	echo '</form>';

	echo '<p class="description" style="margin-top:10px">الصفحات اللي مفيش لها نظير (زي المساجد والمكاتب في جدة) ما تظهرش هنا وما تتلمسش. وعقود النظافة سيبها بدون سعر محدد — فما تنسخهاش.</p>';
	echo '</div>';
}
