<?php
/**
 * Tool: «تصميم صفحات الخدمة» (Tools menu).
 * Sets the per-page design switch (_zad_layout = new | classic) on many service pages at once.
 *  - only the _zad_layout meta is written; content, URLs, slugs and every other field are untouched;
 *  - the table is the preview: nothing changes until a button is pressed, and each page has a ?zad_layout=new preview link;
 *  - the previous value of every touched page is logged, and «تراجع» restores exactly those values;
 *  - the LiteSpeed page cache of each touched page is purged when LiteSpeed is active.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_LT_LOG = 'zad_layout_log';

function zad_lt_pages( $q = '' ) {
	$posts = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 's' => $q ) );
	$rows  = array();
	foreach ( $posts as $p ) {
		$rows[] = array(
			'id'     => $p->ID,
			'title'  => get_the_title( $p ),
			'type'   => $p->post_type,
			'status' => $p->post_status,
			'path'   => wp_make_link_relative( get_permalink( $p ) ),
			'layout' => 'new' === get_post_meta( $p->ID, '_zad_layout', true ) ? 'new' : 'classic',
			'edit'   => get_edit_post_link( $p->ID, 'raw' ),
			'view'   => add_query_arg( 'zad_layout', 'new', get_permalink( $p ) ),
		);
	}
	return $rows;
}

/** Apply 'new' or 'classic' to the given IDs; logs the previous values (first run of a batch keeps the earliest value). Returns the number of pages changed. */
function zad_lt_apply( $ids, $layout ) {
	$layout = 'new' === $layout ? 'new' : '';
	$log    = get_option( ZAD_LT_LOG );
	$log    = is_array( $log ) ? $log : array();
	$n      = 0;
	foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
		$p = get_post( $id );
		if ( ! $p || ! in_array( $p->post_type, zad_service_types(), true ) || ! current_user_can( 'edit_post', $id ) ) { continue; }
		$prev = 'new' === get_post_meta( $id, '_zad_layout', true ) ? 'new' : '';
		if ( $prev === $layout ) { continue; }
		if ( ! array_key_exists( $id, $log ) ) { $log[ $id ] = $prev; }
		update_post_meta( $id, '_zad_layout', $layout );
		clean_post_cache( $id );
		do_action( 'litespeed_purge_post', $id );
		$n++;
	}
	update_option( ZAD_LT_LOG, $log, false );
	return $n;
}

function zad_lt_undo() {
	$log = get_option( ZAD_LT_LOG );
	$n   = 0;
	foreach ( (array) $log as $id => $prev ) {
		if ( ! get_post( (int) $id ) ) { continue; }
		update_post_meta( (int) $id, '_zad_layout', 'new' === $prev ? 'new' : '' );
		clean_post_cache( (int) $id );
		do_action( 'litespeed_purge_post', (int) $id );
		$n++;
	}
	delete_option( ZAD_LT_LOG );
	return $n;
}

add_action( 'admin_menu', function () {
	add_management_page( 'تصميم صفحات الخدمة', 'تصميم صفحات الخدمة (زاد)', 'manage_options', 'zad-layout', 'zad_lt_page' );
} );

function zad_lt_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	echo '<div class="wrap" dir="rtl"><h1>تصميم صفحات الخدمة</h1>';
	if ( isset( $_POST['zad_lt'] ) && check_admin_referer( 'zad_lt' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['zad_lt'] ) );
		$ids = isset( $_POST['zad_lt_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['zad_lt_ids'] ) ) : array();
		if ( 'undo' === $act ) {
			echo '<div class="notice notice-success"><p>رجعت القيمة السابقة لـ ' . (int) zad_lt_undo() . ' صفحة.</p></div>';
		} elseif ( in_array( $act, array( 'new', 'classic' ), true ) ) {
			echo '<div class="notice notice-success"><p>اتغيّر التصميم في ' . (int) zad_lt_apply( $ids, $act ) . ' صفحة ('  . ( 'new' === $act ? 'الجديد' : 'الكلاسيكي' ) . '). لو الموقع عليه كاش، امسحه.</p></div>';
		}
	}
	$q    = isset( $_GET['zs'] ) ? sanitize_text_field( wp_unslash( $_GET['zs'] ) ) : '';
	$rows = zad_lt_pages( $q );
	echo '<p>الجدول هو المعاينة: ما يتغيّر شيء إلا لما تضغط زر. التغيير يلمس حقل «تصميم الصفحة» بس؛ المحتوى والروابط والـslug ما تتغيرش. اضغط «معاينة» تشوف الصفحة بالتصميم الجديد قبل الاعتماد.</p>';
	echo '<form method="get"><input type="hidden" name="page" value="zad-layout"><input type="search" name="zs" value="' . esc_attr( $q ) . '" placeholder="بحث بعنوان الصفحة"> <button class="button">بحث</button></form>';
	echo '<form method="post">'; wp_nonce_field( 'zad_lt' );
	echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th style="width:28px"><input type="checkbox" onclick="document.querySelectorAll(\'.zlt\').forEach(function(c){c.checked=this.checked}.bind(this))"></th><th>الصفحة</th><th>الرابط</th><th>التصميم الحالي</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><input class="zlt" type="checkbox" name="zad_lt_ids[]" value="' . (int) $r['id'] . '"></td>';
		echo '<td><a href="' . esc_url( $r['edit'] ) . '" target="_blank">' . esc_html( $r['title'] ) . '</a><br><small>' . esc_html( $r['type'] ) . ' · ' . esc_html( $r['status'] ) . ' · #' . (int) $r['id'] . '</small></td>';
		echo '<td><code>' . esc_html( $r['path'] ) . '</code></td>';
		echo '<td>' . ( 'new' === $r['layout'] ? '<b style="color:#0c687e">جديد</b>' : 'كلاسيكي' ) . '</td>';
		echo '<td><a href="' . esc_url( $r['view'] ) . '" target="_blank">معاينة</a></td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="5">لا توجد صفحات.</td></tr>'; }
	echo '</tbody></table><p style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap"><button class="button button-primary button-hero" name="zad_lt" value="new">طبّق التصميم الجديد على المحدد</button><button class="button button-hero" name="zad_lt" value="classic">رجّع الكلاسيكي للمحدد</button>';
	if ( is_array( get_option( ZAD_LT_LOG ) ) ) { echo '<button class="button button-hero" name="zad_lt" value="undo">تراجع عن كل ما طبّقته من هنا</button>'; }
	echo '</p></form></div>';
}
