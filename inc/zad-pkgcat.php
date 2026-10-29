<?php
/**
 * أدوات ← «توحيد فئات الباقات (زاد)».
 *
 * العمود التاسع في «باقات الأسعار» (الفئة) اتكتب بأيدٍ مختلفة عبر صفحات المدن، فظهرت فيه مسميات
 * مترادفة («بيوت» و«منازل»، «مبانٍ» و«منشآت»). الأداة دي بتوحّدها حسب خريطة واضحة، بتجربة قبل
 * التنفيذ ونسخة للتراجع. ما بتلمسش أي عمود تاني في سطر الباقة.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

/** خريطة التوحيد: المسمى القديم => الجديد. عدّلها بفلتر zad_pkgcat_map. */
function zad_pkgcat_map() {
	return apply_filters( 'zad_pkgcat_map', array(
		'بيوت'          => 'منازل',
		'شقق وبيوت'     => 'منازل وشقق',
		'بيوت وفلل'     => 'منازل وفلل',
		'مبانٍ'          => 'منشآت',
		'مباني'          => 'منشآت',
		'عمائر ومنشآت'  => 'منشآت',
		'الأوفر للفلل'  => 'الأوفر',
	) );
}

/** كل صفحات الخدمات اللي فيها سطور باقات. */
function zad_pkgcat_pages() {
	return (array) get_posts( array(
		'post_type'   => zad_service_types(),
		'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
		'numberposts' => -1,
		'fields'      => 'ids',
		'zad_all'     => true,
		'meta_query'  => array( array( 'key' => '_zad_packages', 'compare' => 'EXISTS' ) ),
	) );
}

/**
 * التغييرات المطلوبة: صفحة => array( raw_before, raw_after, array( old => new ) ).
 * بتشتغل على العمود التاسع بس، وبتسيب السطر كما هو لو الفئة مش في الخريطة.
 */
function zad_pkgcat_plan() {
	$map  = zad_pkgcat_map();
	$plan = array();
	foreach ( zad_pkgcat_pages() as $id ) {
		$raw = (string) get_post_meta( $id, '_zad_packages', true );
		if ( '' === trim( $raw ) ) { continue; }
		$hits  = array();
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		foreach ( $lines as $i => $line ) {
			if ( '' === trim( $line ) ) { continue; }
			$c = explode( '|', $line );
			if ( count( $c ) < 9 ) { continue; }               // مفيش عمود فئة في السطر ده
			$cat = trim( $c[8] );
			if ( '' === $cat || ! isset( $map[ $cat ] ) ) { continue; }
			$lead   = preg_match( '/^\s*/u', $c[8], $m ) ? $m[0] : '';
			$c[8]   = $lead . $map[ $cat ];
			$lines[ $i ] = implode( '|', $c );
			$hits[ $cat ] = $map[ $cat ];
		}
		if ( $hits ) { $plan[ $id ] = array( $raw, implode( "\n", $lines ), $hits ); }
	}
	return $plan;
}

add_action( 'admin_menu', function () {
	add_management_page( 'توحيد فئات الباقات', 'توحيد فئات الباقات (زاد)', 'manage_options', 'zad-pkgcat', 'zad_pkgcat_page' );
} );

function zad_pkgcat_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	echo '<div class="wrap" dir="rtl" style="max-width:900px"><h1>توحيد فئات الباقات</h1>';

	if ( isset( $_POST['zad_pkgcat_do'] ) && check_admin_referer( 'zad_pkgcat' ) ) {
		$do = sanitize_key( wp_unslash( $_POST['zad_pkgcat_do'] ) );
		if ( 'run' === $do ) {
			$back = array(); $n = 0;
			foreach ( zad_pkgcat_plan() as $id => $p ) {
				$back[ $id ] = $p[0];                          // النص القديم كاملاً للتراجع
				update_post_meta( $id, '_zad_packages', $p[1] );
				$n++;
			}
			if ( $back ) { update_option( 'zad_pkgcat_backup', $back, false ); }
			echo '<div class="notice notice-success"><p>اتعدّلت ' . (int) $n . ' صفحة. تقدر ترجع بزر «تراجع».</p></div>';
		} elseif ( 'undo' === $do ) {
			$back = (array) get_option( 'zad_pkgcat_backup', array() ); $n = 0;
			foreach ( $back as $id => $raw ) { update_post_meta( (int) $id, '_zad_packages', $raw ); $n++; }
			delete_option( 'zad_pkgcat_backup' );
			echo '<div class="notice notice-warning"><p>رجعت ' . (int) $n . ' صفحة لنصها القديم.</p></div>';
		}
	}

	$plan = zad_pkgcat_plan();
	echo '<p>بتغيّر <strong>عمود الفئة بس</strong> (التاسع) في سطور «باقات الأسعار»، وما بتلمسش الاسم ولا السعر ولا أي عمود تاني.</p>';
	echo '<h2>الخريطة</h2><table class="widefat striped" style="max-width:420px"><thead><tr><th>المسمى الحالي</th><th>هيبقى</th></tr></thead><tbody>';
	foreach ( zad_pkgcat_map() as $from => $to ) {
		echo '<tr><td>' . esc_html( $from ) . '</td><td><strong>' . esc_html( $to ) . '</strong></td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>الصفحات اللي هتتغير (' . count( $plan ) . ')</h2>';
	if ( ! $plan ) {
		echo '<p>مفيش صفحة فيها مسمّى من الخريطة — يا إما اتعمل خلاص، يا إما المسميات مختلفة عن اللي في الخريطة.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>الصفحة</th><th>الحالة</th><th>التغيير</th></tr></thead><tbody>';
		foreach ( $plan as $id => $p ) {
			$ch = array();
			foreach ( $p[2] as $from => $to ) { $ch[] = esc_html( $from ) . ' ← ' . esc_html( $to ); }
			echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( get_post_status( $id ) ) . '</td><td>' . implode( ' · ', $ch ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	}

	echo '<form method="post" style="margin-top:14px">';
	wp_nonce_field( 'zad_pkgcat' );
	if ( $plan ) { echo '<button class="button button-primary" name="zad_pkgcat_do" value="run">وحّد الفئات</button> '; }
	if ( get_option( 'zad_pkgcat_backup' ) ) { echo '<button class="button" name="zad_pkgcat_do" value="undo">تراجع</button>'; }
	echo '</form></div>';
}
