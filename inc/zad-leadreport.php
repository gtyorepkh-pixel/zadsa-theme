<?php
/**
 * Lead source report (طلبات → تقرير المصادر): which page / district / service / section brings requests.
 * Uses the page URL already stored with every lead (_lead_source). Read-only.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=zad_lead', 'تقرير مصادر الطلبات', 'تقرير المصادر', 'manage_options', 'zad-lead-report', 'zad_leadreport_page' );
} );

function zad_leadreport_bar( $n, $max ) {
	return '<span style="display:inline-block;height:10px;border-radius:5px;background:#0c687e;width:' . ( $max ? max( 4, (int) round( 160 * $n / $max ) ) : 4 ) . 'px"></span>';
}

function zad_leadreport_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$days = isset( $_GET['d'] ) ? absint( $_GET['d'] ) : 30; // phpcs:ignore
	$days = in_array( $days, array( 7, 30, 90, 365 ), true ) ? $days : 30;
	$args = array( 'post_type' => 'zad_lead', 'post_status' => 'any', 'numberposts' => 3000, 'no_found_rows' => true, 'suppress_filters' => true, 'date_query' => array( array( 'after' => gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ) ) ) );
	$leads = get_posts( $args );
	$by = array( 'page' => array(), 'hood' => array(), 'svc' => array(), 'area' => array(), 'sec' => array(), 'day' => array() );
	$cache = array();
	foreach ( $leads as $l ) {
		$src = (string) get_post_meta( $l->ID, '_lead_source', true );
		$u   = strtok( $src, '?#' );
		if ( ! isset( $cache[ $u ] ) ) { $cache[ $u ] = $u ? (int) url_to_postid( $u ) : 0; }
		$pid = $cache[ $u ];
		$pg  = $pid ? get_the_title( $pid ) : ( $u ? ( zad_audit_path( $u ) ?: 'خارجي' ) : 'غير معروف' );
		$by['page'][ $pg ] = ( $by['page'][ $pg ] ?? 0 ) + 1;
		if ( $pid ) {
			$h = (int) get_post_meta( $pid, '_zad_h_hood', true );
			if ( $h ) { $hn = get_the_title( $h ); $by['hood'][ $hn ] = ( $by['hood'][ $hn ] ?? 0 ) + 1; }
			$seg = function_exists( 'zad_bridge_seg' ) ? zad_bridge_seg( $pid ) : '';
			if ( $seg ) { $by['sec'][ $seg ] = ( $by['sec'][ $seg ] ?? 0 ) + 1; }
		}
		$s = (string) get_post_meta( $l->ID, '_lead_service', true ); if ( $s ) { $by['svc'][ $s ] = ( $by['svc'][ $s ] ?? 0 ) + 1; }
		$a = (string) get_post_meta( $l->ID, '_lead_area', true );    if ( $a ) { $by['area'][ $a ] = ( $by['area'][ $a ] ?? 0 ) + 1; }
		$d = substr( $l->post_date, 0, 10 ); $by['day'][ $d ] = ( $by['day'][ $d ] ?? 0 ) + 1;
	}
	echo '<div class="wrap" dir="rtl"><h1>تقرير مصادر الطلبات</h1><p>';
	foreach ( array( 7 => '7 أيام', 30 => '30 يوماً', 90 => '90 يوماً', 365 => 'سنة' ) as $k => $lbl ) {
		echo '<a class="button' . ( $k === $days ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=zad_lead&page=zad-lead-report&d=' . $k ) ) . '">' . esc_html( $lbl ) . '</a> ';
	}
	echo '</p><p>إجمالي الطلبات في الفترة: <b>' . count( $leads ) . '</b></p>';
	zad_leadreport_wa( $days );
	if ( ! $leads ) { echo '<p>لا طلبات في هذه الفترة.</p></div>'; return; }
	$titles = array( 'page' => 'الصفحة التي جاء منها الطلب', 'hood' => 'الحي (لصفحات الأحياء)', 'sec' => 'القسم', 'svc' => 'الخدمة المطلوبة', 'area' => 'المنطقة المكتوبة في النموذج' );
	foreach ( $titles as $k => $t ) {
		arsort( $by[ $k ] ); $rows = array_slice( $by[ $k ], 0, 15, true ); $max = $rows ? max( $rows ) : 0;
		echo '<h2>' . esc_html( $t ) . '</h2>';
		if ( ! $rows ) { echo '<p class="description">لا بيانات.</p>'; continue; }
		echo '<table class="widefat striped" style="max-width:760px"><tbody>';
		foreach ( $rows as $n => $c ) { echo '<tr><td>' . esc_html( $n ) . '</td><td style="width:60px"><b>' . (int) $c . '</b></td><td style="width:180px">' . zad_leadreport_bar( $c, $max ) . '</td></tr>'; } // phpcs:ignore
		echo '</tbody></table>';
	}
	ksort( $by['day'] );
	echo '<h2>الطلبات يومياً</h2><table class="widefat striped" style="max-width:760px"><tbody>';
	$dm = max( $by['day'] );
	foreach ( array_slice( $by['day'], -30, null, true ) as $d => $c ) { echo '<tr><td>' . esc_html( $d ) . '</td><td style="width:60px"><b>' . (int) $c . '</b></td><td style="width:180px">' . zad_leadreport_bar( $c, $dm ) . '</td></tr>'; } // phpcs:ignore
	echo '</tbody></table><p class="description">المصدر هو رابط الصفحة المحفوظ مع كل طلب. الطلبات القديمة التي بلا مصدر تظهر «غير معروف». أين يأتي أكثر من طلب فاستثمر في محتوى تلك الصفحات والأحياء المشابهة.</p></div>';
}


/** WhatsApp button clicks by card (aggregate counters; no personal data). */
function zad_leadreport_wa( $days ) {
	$log = (array) get_option( 'zad_wa_clicks', array() );
	if ( ! $log ) { echo '<h2>نقرات واتساب من البطاقات</h2><p class="description">لا بيانات بعد (تُسجَّل عند استخدام بطاقات «اطلب في 30 ثانية» و«شخّص مشكلتك»).</p>'; return; }
	$from = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ); $by = array(); $pg = array();
	foreach ( $log as $key => $d ) {
		list( $src, $pid ) = array_pad( explode( '|', $key ), 2, 0 ); $n = 0;
		foreach ( $d as $day => $c ) { if ( $day >= $from ) { $n += (int) $c; } }
		if ( ! $n ) { continue; }
		$by[ $src ] = ( $by[ $src ] ?? 0 ) + $n;
		$t = (int) $pid ? get_the_title( (int) $pid ) : 'الصفحة الرئيسية';
		$pg[ $t ] = ( $pg[ $t ] ?? 0 ) + $n;
	}
	arsort( $by ); arsort( $pg );
	$names = zad_quick_sources();
	echo '<h2>نقرات واتساب من البطاقات</h2><table class="widefat striped" style="max-width:520px"><tbody>';
	foreach ( $by as $k => $n ) { echo '<tr><td>' . esc_html( $names[ $k ] ?? $k ) . '</td><td><b>' . (int) $n . '</b></td></tr>'; }
	echo '</tbody></table>';
	if ( $pg ) { echo '<p>أكثر الصفحات: '; $i = 0; foreach ( $pg as $t => $n ) { if ( ++$i > 6 ) { break; } echo esc_html( $t ) . ' (' . (int) $n . ') · '; } echo '</p>'; }
	echo '<p class="description">النقرات تعني فتح محادثة واتساب بالرسالة المنظمة، وليست طلبات مؤكدة.</p>';
}
