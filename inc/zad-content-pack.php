<?php
/**
 * Tool: «محتوى جاهز لصفحات الخدمة» (Tools menu).
 * Replaces the text that follows the "Read more" tag of ONE service page with a ready-made content pack (assets/content-packs/*.html):
 *  - the intro before the tag, the title, slug, URL and every other field are untouched;
 *  - no tag in the page = refused (the tool never guesses where the old text ends);
 *  - the preview shows the old text (words) next to the new text and checks the internal links before anything is written;
 *  - the full original content is saved once in post meta; «تراجع» restores it exactly (WordPress also keeps a revision).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_CP_META = '_zad_cp_backup';

function zad_cp_packs() {
	return apply_filters( 'zad_content_packs', array(
		'tanks-knowledge' => array(
			'label' => 'قسم المعرفة — تنظيف الخزانات (9 أسئلة بقوائم، نحو 650 كلمة)',
			'file'  => 'assets/content-packs/tanks-knowledge.html',
		),
	) );
}

function zad_cp_html( $key ) {
	$p = zad_cp_packs();
	if ( ! isset( $p[ $key ] ) ) { return ''; }
	$f = ZAD_THEME_DIR . $p[ $key ]['file'];
	return is_readable( $f ) ? (string) file_get_contents( $f ) : '';
}

/** array( head incl. the more tag, tail ) or null when the content has no tag. */
function zad_cp_split( $content ) {
	if ( preg_match( '#^(.*?<!--more(?:\s[^>]*?)?-->(?:\s*<!-- /wp:more -->)?)(.*)$#s', (string) $content, $m ) ) { return array( $m[1], $m[2] ); }
	return null;
}

function zad_cp_words( $html ) {
	$t = preg_replace( '#<!--.*?-->#s', ' ', (string) $html );
	preg_match_all( '/[\x{0621}-\x{064A}\x{0660}-\x{0669}A-Za-z0-9]+/u', wp_strip_all_tags( $t ), $m );
	return count( $m[0] );
}

/** Internal links of a pack: [ [href, ok(bool|null)] ]. null = could not be confirmed (archives, hubs). */
function zad_cp_links( $html ) {
	$out = array();
	if ( ! preg_match_all( '#href="(/[^"]*)"#', (string) $html, $m ) ) { return $out; }
	foreach ( array_unique( $m[1] ) as $h ) {
		$id    = url_to_postid( home_url( $h ) );
		$out[] = array( $h, $id ? true : null );
	}
	return $out;
}

function zad_cp_apply( $post_id, $key ) {
	$post = get_post( (int) $post_id );
	$html = zad_cp_html( $key );
	if ( ! $post || ! in_array( $post->post_type, zad_service_types(), true ) || ! current_user_can( 'edit_post', $post->ID ) ) { return new WP_Error( 'zad_cp', 'صفحة غير صالحة.' ); }
	if ( '' === $html ) { return new WP_Error( 'zad_cp', 'الحزمة غير موجودة.' ); }
	$parts = zad_cp_split( $post->post_content );
	if ( ! $parts ) { return new WP_Error( 'zad_cp', 'الصفحة ما فيها وسم «قراءة المزيد»؛ أضِفه في المحرر بعد المقدمة ثم أعد المحاولة.' ); }
	if ( '' === (string) get_post_meta( $post->ID, ZAD_CP_META, true ) ) { update_post_meta( $post->ID, ZAD_CP_META, wp_slash( $post->post_content ) ); }
	$r = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => rtrim( $parts[0] ) . "\n\n" . trim( $html ) . "\n" ) ), true );
	if ( is_wp_error( $r ) ) { return $r; }
	clean_post_cache( $post->ID );
	do_action( 'litespeed_purge_post', $post->ID );
	return true;
}

function zad_cp_undo( $post_id ) {
	$post = get_post( (int) $post_id );
	$bak  = $post ? (string) get_post_meta( $post->ID, ZAD_CP_META, true ) : '';
	if ( ! $post || '' === $bak || ! current_user_can( 'edit_post', $post->ID ) ) { return false; }
	$r = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $bak ) ), true );
	if ( is_wp_error( $r ) ) { return false; }
	delete_post_meta( $post->ID, ZAD_CP_META );
	clean_post_cache( $post->ID );
	do_action( 'litespeed_purge_post', $post->ID );
	return true;
}

add_action( 'admin_menu', function () {
	add_management_page( 'محتوى جاهز لصفحات الخدمة', 'محتوى جاهز للخدمات (زاد)', 'manage_options', 'zad-content-pack', 'zad_cp_page' );
} );

function zad_cp_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$packs = zad_cp_packs();
	echo '<div class="wrap" dir="rtl"><h1>محتوى جاهز لصفحات الخدمة</h1>';
	$act = '';
	if ( isset( $_POST['zad_cp'] ) && check_admin_referer( 'zad_cp' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['zad_cp'] ) );
		$pid = isset( $_POST['zad_cp_page'] ) ? (int) $_POST['zad_cp_page'] : 0;
		$key = isset( $_POST['zad_cp_pack'] ) ? sanitize_key( wp_unslash( $_POST['zad_cp_pack'] ) ) : '';
		if ( 'apply' === $act ) {
			$r = zad_cp_apply( $pid, $key );
			echo is_wp_error( $r ) ? '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>' : '<div class="notice notice-success"><p>اتبدّل النص بعد وسم «قراءة المزيد». النص الأصلي محفوظ وزر «تراجع» بيرجّعه. لو الموقع عليه كاش، امسحه.</p></div>';
			$act = '';
		} elseif ( 'undo' === $act ) {
			echo zad_cp_undo( $pid ) ? '<div class="notice notice-success"><p>رجع النص الأصلي.</p></div>' : '<div class="notice notice-error"><p>ما فيش نسخة محفوظة للصفحة دي.</p></div>';
			$act = '';
		}
	}
	$q    = isset( $_REQUEST['zs'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['zs'] ) ) : '';
	$sel  = isset( $_REQUEST['zad_cp_page'] ) ? (int) $_REQUEST['zad_cp_page'] : 0;
	$pk   = isset( $_REQUEST['zad_cp_pack'] ) ? sanitize_key( wp_unslash( $_REQUEST['zad_cp_pack'] ) ) : (string) key( $packs );
	echo '<p>بيبدّل بس النص اللي <b>بعد وسم «قراءة المزيد»</b> في صفحة واحدة؛ المقدمة والعنوان والرابط وباقي الحقول كما هي. المعاينة تحت، والتنفيذ بزر منفصل، وفيه تراجع.</p>';
	echo '<form method="post">'; wp_nonce_field( 'zad_cp' );
	echo '<p><label>الحزمة: <select name="zad_cp_pack">';
	foreach ( $packs as $k => $p ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $pk, $k, false ) . '>' . esc_html( $p['label'] ) . '</option>'; }
	echo '</select></label> <label>بحث بعنوان الصفحة: <input type="search" name="zs" value="' . esc_attr( $q ) . '" placeholder="خزانات"></label> <button class="button" name="zad_cp" value="list">بحث</button></p>';
	$posts = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => 60, 'orderby' => 'title', 'order' => 'ASC', 's' => $q ) );
	echo '<table class="widefat striped"><thead><tr><th style="width:28px"></th><th>الصفحة</th><th>وسم «قراءة المزيد»</th><th>كلمات النص بعده</th><th>نسخة محفوظة</th></tr></thead><tbody>';
	foreach ( $posts as $p ) {
		$sp = zad_cp_split( $p->post_content );
		echo '<tr><td><input type="radio" name="zad_cp_page" value="' . (int) $p->ID . '"' . checked( $sel, $p->ID, false ) . '></td><td><a href="' . esc_url( get_edit_post_link( $p->ID, 'raw' ) ) . '" target="_blank">' . esc_html( get_the_title( $p ) ) . '</a><br><small><code>' . esc_html( wp_make_link_relative( get_permalink( $p ) ) ) . '</code></small></td>';
		echo '<td>' . ( $sp ? 'موجود' : '<span style="color:#b32d2e">غير موجود</span>' ) . '</td><td>' . ( $sp ? (int) zad_cp_words( $sp[1] ) : '—' ) . '</td><td>' . ( '' !== (string) get_post_meta( $p->ID, ZAD_CP_META, true ) ? '<b>نعم</b>' : '—' ) . '</td></tr>';
	}
	if ( ! $posts ) { echo '<tr><td colspan="5">لا توجد صفحات.</td></tr>'; }
	echo '</tbody></table><p style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap"><button class="button button-hero" name="zad_cp" value="preview">معاينة على الصفحة المحددة</button><button class="button button-primary button-hero" name="zad_cp" value="apply" onclick="return confirm(\'تبديل النص بعد وسم قراءة المزيد في الصفحة المحددة؟ النص الأصلي محفوظ ويمكن التراجع.\')">نفّذ على الصفحة المحددة</button><button class="button button-hero" name="zad_cp" value="undo">تراجع للنص الأصلي للصفحة المحددة</button></p>';
	if ( 'preview' === $act && $sel ) {
		$post = get_post( $sel ); $html = zad_cp_html( $pk );
		$sp   = $post ? zad_cp_split( $post->post_content ) : null;
		echo '<hr><h2>المعاينة: ' . esc_html( $post ? get_the_title( $post ) : '' ) . '</h2>';
		if ( ! $sp ) {
			echo '<div class="notice notice-error inline"><p>الصفحة ما فيها وسم «قراءة المزيد» — أضِفه في المحرر بعد المقدمة. لن ينفّذ الزر.</p></div>';
		} else {
			echo '<p>النص الحالي بعد الوسم: <b>' . (int) zad_cp_words( $sp[1] ) . '</b> كلمة ← النص الجديد: <b>' . (int) zad_cp_words( $html ) . '</b> كلمة.</p>';
			echo '<p><b>الروابط الداخلية في النص الجديد:</b> ';
			foreach ( zad_cp_links( $html ) as $l ) { echo '<code>' . esc_html( $l[0] ) . '</code> ' . ( $l[1] ? '✓' : '<span style="color:#b36b00">؟ تأكد منه</span>' ) . ' · '; }
			echo '</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><div><h3>الحالي بعد الوسم</h3><div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:420px;overflow:auto">' . wp_kses_post( $sp[1] ) . '</div></div><div><h3>الجديد</h3><div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:420px;overflow:auto">' . wp_kses_post( do_blocks( $html ) ) . '</div></div></div>';
		}
	}
	echo '</form></div>';
}
