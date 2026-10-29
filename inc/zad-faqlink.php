<?php
/**
 * أدوات ← «ربط الأسئلة بالخدمات (زاد)».
 *
 * الحقل `_zad_faq_services` هو اللي بيخلّي السؤال يظهر في صفحة الخدمة (hub-service.php:48)
 * وفي أقسام الموسوعة (zad-sections.php:168) وبيحدّد رابط «الخدمة» في صفحة السؤال
 * (zad-links.php:124). والـREST ما بيكتبهوش، فالأسئلة المعمولة من غير الميتابوكس
 * بتفضل بلا ربط. الأداة دي بتعرض اقتراحًا لكل سؤال وتسمح بالتعديل قبل الحفظ.
 *
 * قواعد:
 *  - الاقتراح من `zad_faq_match_service()` نفسها اللي الثيم بيستخدمها (نفس المنطق، نفس النتيجة).
 *  - معاينة كاملة قبل أي كتابة، وقائمة منسدلة لكل سؤال تقدر تغيّرها أو تخليها «— بدون —».
 *  - بتكتب في `_zad_faq_services` بس، بنفس شكل الميتابوكس: مصفوفة نصوص.
 *  - نسخة من القيمة القديمة في `_zad_faq_link_backup` + سجل في خيار + «تراجع عن الكل».
 *  - ما بتلمسش سؤال مربوط إلا لو شغّلت «أعد ربط المربوط كذلك».
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_FAQLINK_META   = '_zad_faq_services';
const ZAD_FAQLINK_BACKUP = '_zad_faq_link_backup';
const ZAD_FAQLINK_LOG    = 'zad_faqlink_log';

/** كل الأسئلة (منشور + مسودة) مرتّبة بالتصنيف. */
function zad_faqlink_questions( $cat = 0, $status = array( 'draft', 'publish' ) ) {
	$args = array(
		'post_type'   => zad_faq_types(),
		'post_status' => $status,
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'zad_all'     => true,
	);
	if ( $cat ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'faq_cat', 'field' => 'term_id', 'terms' => (int) $cat ) );
	}
	return (array) get_posts( $args );
}

/** الخدمات المنشورة: id => العنوان. */
function zad_faqlink_services() {
	static $out = null;
	if ( null !== $out ) { return $out; }
	$out = array();
	foreach ( (array) get_posts( array(
		'post_type'   => zad_service_types(),
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'zad_all'     => true,
	) ) as $s ) {
		$out[ (int) $s->ID ] = $s->post_title;
	}
	return $out;
}

/** الربط الحالي لسؤال: مصفوفة أرقام. */
function zad_faqlink_current( $id ) {
	return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, ZAD_FAQLINK_META, true ) ) ) );
}

/**
 * خطة الربط: array( array( post, current[], suggested_id, state ) ).
 * state: 'linked' (مربوط) · 'suggest' (فيه اقتراح) · 'none' (مفيش اقتراح).
 */
function zad_faqlink_plan( $cat = 0 ) {
	$out = array();
	foreach ( zad_faqlink_questions( $cat ) as $q ) {
		$cur   = zad_faqlink_current( $q->ID );
		$sug   = function_exists( 'zad_faq_match_service' ) ? (int) zad_faq_match_service( $q->ID ) : 0;
		$state = $cur ? 'linked' : ( $sug ? 'suggest' : 'none' );
		$out[] = array( $q, $cur, $sug, $state );
	}
	return $out;
}

add_action( 'admin_menu', function () {
	add_management_page( 'ربط الأسئلة بالخدمات', 'ربط الأسئلة (زاد)', 'manage_options', 'zad-faqlink', 'zad_faqlink_page' );
} );

function zad_faqlink_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }

	$cat   = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0;
	$svc   = zad_faqlink_services();
	$terms = get_terms( array( 'taxonomy' => 'faq_cat', 'hide_empty' => false ) );

	echo '<div class="wrap" dir="rtl" style="max-width:1080px"><h1>ربط الأسئلة بالخدمات</h1>';

	// تنفيذ.
	if ( isset( $_POST['zad_faqlink_do'] ) && check_admin_referer( 'zad_faqlink' ) ) {
		$do = sanitize_key( wp_unslash( $_POST['zad_faqlink_do'] ) );

		if ( 'save' === $do ) {
			$picks = isset( $_POST['zad_faqlink'] ) ? (array) wp_unslash( $_POST['zad_faqlink'] ) : array();
			$log   = (array) get_option( ZAD_FAQLINK_LOG, array() );
			$n     = 0;
			foreach ( $picks as $pid => $sid ) {
				$pid = absint( $pid );
				$sid = absint( $sid );
				if ( ! $pid || ! in_array( get_post_type( $pid ), zad_faq_types(), true ) ) { continue; }
				if ( $sid && ! isset( $svc[ $sid ] ) ) { continue; }          // خدمة غير منشورة أو رقم ملفّق
				$cur = zad_faqlink_current( $pid );
				$new = $sid ? array( (string) $sid ) : array();
				if ( $cur === array_map( 'intval', $new ) ) { continue; }     // مفيش تغيير
				if ( ! metadata_exists( 'post', $pid, ZAD_FAQLINK_BACKUP ) ) {
					update_post_meta( $pid, ZAD_FAQLINK_BACKUP, $cur );      // نسخة من القديم (ولو فاضي)
				}
				update_post_meta( $pid, ZAD_FAQLINK_META, $new );
				$log[ $pid ] = 1;
				$n++;
			}
			update_option( ZAD_FAQLINK_LOG, $log, false );
			echo '<div class="notice notice-success"><p>اتربط <strong>' . (int) $n . '</strong> سؤال.</p></div>';

		} elseif ( 'undo' === $do ) {
			$log = (array) get_option( ZAD_FAQLINK_LOG, array() );
			$n   = 0;
			foreach ( array_keys( $log ) as $pid ) {
				$pid = absint( $pid );
				if ( ! metadata_exists( 'post', $pid, ZAD_FAQLINK_BACKUP ) ) { continue; }
				$old = (array) get_post_meta( $pid, ZAD_FAQLINK_BACKUP, true );
				update_post_meta( $pid, ZAD_FAQLINK_META, array_map( 'strval', array_map( 'absint', $old ) ) );
				delete_post_meta( $pid, ZAD_FAQLINK_BACKUP );
				$n++;
			}
			delete_option( ZAD_FAQLINK_LOG );
			echo '<div class="notice notice-warning"><p>رجع <strong>' . (int) $n . '</strong> سؤال لربطه القديم.</p></div>';
		}
	}

	echo '<p>الحقل ده هو اللي بيخلّي السؤال يظهر في <strong>صفحة الخدمة</strong> وفي <strong>قسم الموسوعة</strong>، وبيحدّد زر «الخدمة» في صفحة السؤال. الاقتراح محسوب بنفس دالة المطابقة اللي الثيم بيستخدمها — راجعه وعدّل اللي محتاج قبل الحفظ.</p>';

	// فلتر التصنيف.
	echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="zad-faqlink"><select name="cat"><option value="0">كل التصنيفات</option>';
	foreach ( (array) $terms as $t ) {
		if ( is_wp_error( $t ) ) { continue; }
		echo '<option value="' . (int) $t->term_id . '"' . selected( $cat, $t->term_id, false ) . '>' . esc_html( $t->name ) . ' (' . (int) $t->count . ')</option>';
	}
	echo '</select> <button class="button">اعرض</button></form>';

	$plan   = zad_faqlink_plan( $cat );
	$unl    = array_filter( $plan, function ( $p ) { return 'linked' !== $p[3]; } );
	$nosug  = array_filter( $plan, function ( $p ) { return 'none' === $p[3]; } );

	echo '<h2>' . count( $plan ) . ' سؤال · <strong>' . count( $unl ) . '</strong> بلا ربط · ' . count( $nosug ) . ' بلا اقتراح</h2>';

	if ( ! $plan ) {
		echo '<p>مفيش أسئلة في النطاق المختار.</p></div>';
		return;
	}
	if ( ! $svc ) {
		echo '<div class="notice notice-error"><p>مفيش صفحات خدمات منشورة — مفيش حاجة نربط بها.</p></div></div>';
		return;
	}

	echo '<form method="post">';
	wp_nonce_field( 'zad_faqlink' );
	echo '<p><label><input type="checkbox" id="zad-fl-all"> اعرض المربوط كذلك (الافتراضي: بلا ربط بس)</label></p>';
	echo '<table class="widefat striped"><thead><tr><th style="width:38%">السؤال</th><th style="width:10%">الحالة</th><th style="width:22%">المربوط حالياً</th><th style="width:30%">الخدمة</th></tr></thead><tbody>';

	$lbl = array( 'linked' => 'مربوط', 'suggest' => 'اقتراح جاهز', 'none' => 'بلا اقتراح' );
	foreach ( $plan as $p ) {
		list( $q, $cur, $sug, $state ) = $p;
		$pick = $cur ? (int) $cur[0] : (int) $sug;                            // المربوط أولاً، وإلا الاقتراح
		echo '<tr class="zad-fl-row" data-state="' . esc_attr( $state ) . '"' . ( 'linked' === $state ? ' style="display:none"' : '' ) . '>';
		echo '<td><a href="' . esc_url( (string) get_edit_post_link( $q->ID ) ) . '" target="_blank">' . esc_html( $q->post_title ) . '</a>'
			. ( 'draft' === $q->post_status ? ' <span class="description">(مسودة)</span>' : '' ) . '</td>';
		echo '<td>' . esc_html( $lbl[ $state ] ) . '</td>';
		echo '<td>' . ( $cur ? esc_html( implode( ' · ', array_map( function ( $i ) use ( $svc ) { return $svc[ $i ] ?? ( '#' . $i ); }, $cur ) ) ) : '—' ) . '</td>';
		echo '<td><select name="zad_faqlink[' . (int) $q->ID . ']" style="width:100%"><option value="0">— بدون —</option>';
		foreach ( $svc as $sid => $title ) {
			echo '<option value="' . (int) $sid . '"' . selected( $pick, $sid, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table>';

	echo '<p style="margin-top:14px"><button class="button button-primary" name="zad_faqlink_do" value="save">احفظ الربط</button> ';
	if ( get_option( ZAD_FAQLINK_LOG ) ) {
		echo '<button class="button" name="zad_faqlink_do" value="undo">تراجع عن الكل</button>';
	}
	echo '</p></form>';

	echo '<p class="description">الحفظ بيكتب في <code>_zad_faq_services</code> بس — مفيش محتوى ولا عنوان ولا رابط بيتغيّر. والسؤال اللي تخليه «— بدون —» وهو مربوط هيتفكّ ربطه.</p>';

	// إظهار/إخفاء الصفوف المربوطة.
	echo '<script>document.getElementById("zad-fl-all").addEventListener("change",function(){document.querySelectorAll(".zad-fl-row[data-state=linked]").forEach(function(r){r.style.display=this.checked?"":"none";}.bind(this));});</script>';
	echo '</div>';
}
