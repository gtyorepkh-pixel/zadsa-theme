<?php
/**
 * One-time migration tool (Tools → نقل الأسئلة والأزرار):
 *  - wpsp/faq blocks inside page content  → the theme's own FAQ field (rendered by the theme, FAQPage schema by the theme)
 *  - wp:html call/WhatsApp button blocks  → the [zad_cta] shortcode
 * Preview first; every changed page keeps a backup of its original content and can be restored.
 * Also: [zad_cta] shortcode, and FAQ display for Pages/Posts that carry theme FAQ data.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_FAQM_BACKUP = '_zad_faqmig_backup';

/* ---------- parsing ---------- */
function zad_wpsp_blocks( $html ) {
	$out = array(); $off = 0;
	while ( preg_match( '/<div[^>]*class="[^"]*wp-block-wpsp-faq(?!-child)[^"]*"[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE, $off ) ) {
		$start = $m[0][1]; $depth = 0; $pos = $start; $end = false;
		while ( preg_match( '#<(/?)div\b#i', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
			$depth += $t[1][0] ? -1 : 1;
			$pos    = $t[0][1] + 4;
			if ( 0 === $depth ) { $end = strpos( $html, '>', $t[0][1] ) + 1; break; }
		}
		if ( false === $end ) { break; }
		$out[] = array( $start, $end - $start );
		$off   = $end;
	}
	return $out;
}

function zad_wpsp_pairs( $html ) {
	$pairs = array(); $seen = array();
	foreach ( zad_wpsp_blocks( $html ) as $b ) {
		$blk = substr( $html, $b[0], $b[1] );
		if ( preg_match_all( '#class="wpsp-question"[^>]*>(.*?)</h[1-6]>.*?class="wpsp-faq-content"[^>]*>\s*(?:<span>)?(.*?)(?:</span>)?\s*</div>#su', $blk, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$q = trim( wp_strip_all_tags( $x[1] ) ); $a = trim( $x[2] ); $k = mb_strtolower( $q );
				if ( '' !== $q && '' !== trim( wp_strip_all_tags( $a ) ) && ! isset( $seen[ $k ] ) ) { $seen[ $k ] = 1; $pairs[] = array( 'q' => $q, 'a' => wp_kses_post( $a ) ); }
			}
		}
	}
	return $pairs;
}

/** Remove FAQ blocks (and the heading right before them when it is an "الأسئلة" heading). */
function zad_faqm_strip_faq( $c ) {
	$n = 0;
	$re = '/<!--\s*wp:wpsp\/faq(?=[\s}]).*?<!--\s*\/wp:wpsp\/faq\s*-->/s';
	while ( preg_match( $re, $c, $m, PREG_OFFSET_CAPTURE ) ) {
		$c = zad_faqm_cut( $c, $m[0][1], strlen( $m[0][0] ) ); $n++;
	}
	foreach ( array_reverse( zad_wpsp_blocks( $c ) ) as $b ) { $c = zad_faqm_cut( $c, $b[0], $b[1] ); $n++; }
	return array( $c, $n );
}

function zad_faqm_cut( $c, $pos, $len ) {
	$before = substr( $c, 0, $pos ); $after = substr( $c, $pos + $len );
	$before = preg_replace( '/(?:<!--\s*wp:heading[^>]*-->\s*)?<h[23][^>]*>[^<]*(?:الأسئلة|أسئلة)[^<]*<\/h[23]>\s*(?:<!--\s*\/wp:heading\s*-->\s*)?$/u', '', $before );
	return rtrim( $before ) . "\n\n" . ltrim( $after );
}

/** Replace wp:html blocks that are call + WhatsApp button groups with [zad_cta]. */
function zad_faqm_cta( $c ) {
	$n = 0;
	$c = preg_replace_callback( '/<!--\s*wp:html\s*-->(.*?)<!--\s*\/wp:html\s*-->/s', function ( $m ) use ( &$n ) {
		$h = $m[1];
		if ( false === stripos( $h, 'href="tel:' ) || false === stripos( $h, 'wa.me' ) || false !== stripos( $h, '<table' ) ) { return $m[0]; }
		$n++;
		$price = '';
		if ( preg_match( '#href="(https?://[^"]*(?:valuation|calculator|price)[^"]*)"#i', $h, $p ) ) { $price = ' price="' . esc_url_raw( $p[1] ) . '"'; }
		return '<!-- wp:shortcode -->[zad_cta' . $price . ']<!-- /wp:shortcode -->';
	}, $c );
	return array( $c, $n );
}

/* ---------- [zad_cta] ---------- */
add_shortcode( 'zad_cta', function ( $atts ) {
	$a  = shortcode_atts( array( 'call' => '1', 'wa' => '1', 'price' => '' ), $atts, 'zad_cta' );
	$id = get_the_ID();
	$ph = zad_phone( $id ); $wa = zad_wa_link( 'مرحباً، أرغب بطلب خدمة: ' . get_the_title( $id ), $id );
	$o  = '<div class="zad-cta">';
	if ( '0' !== $a['call'] && $ph ) { $o .= '<a class="btn btn--primary" href="' . esc_url( zad_tel_href( $ph ) ) . '">' . zad_icon( 'phone', 20 ) . ' اتصل الآن</a>'; }
	if ( '0' !== $a['wa'] && $wa ) { $o .= '<a class="btn btn--wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . zad_icon( 'whatsapp', 20 ) . ' تواصل عبر واتساب</a>'; }
	if ( $a['price'] ) { $o .= '<a class="btn btn--accent" href="' . esc_url( $a['price'] ) . '">' . zad_icon( 'bolt', 20 ) . ' احصل على سعر مبدئي</a>'; }
	return $o . '</div>';
} );

/* ---------- Pages/Posts that carry theme FAQ data ---------- */
add_filter( 'the_content', function ( $c ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $c; }
	$id = get_the_ID();
	if ( in_array( get_post_type( $id ), zad_service_types(), true ) || ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) ) { return $c; }
	$f = array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $r ) { return ! empty( $r['q'] ); } );
	if ( ! $f ) { return $c; }
	ob_start(); echo '<section class="zad-faq"><h2>الأسئلة الشائعة</h2>'; zad_render_faq( $f ); echo '</section>';
	return $c . ob_get_clean();
}, 30 );

add_action( 'wp_head', function () {
	if ( ! is_singular() || 'theme' !== zad_schema_owner() ) { return; }
	$id = get_queried_object_id();
	if ( in_array( get_post_type( $id ), zad_service_types(), true ) || ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) ) { return; }
	$f = array_values( array_filter( (array) get_post_meta( $id, '_zad_faq', true ), function ( $r ) { return ! empty( $r['q'] ) && ! empty( $r['a'] ); } ) );
	if ( ! $f ) { return; }
	$ents = array();
	foreach ( $f as $r ) { $ents[] = array( '@type' => 'Question', 'name' => $r['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( wp_strip_all_tags( $r['a'] ) ) ) ); }
	zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
}, 15 );

/* ---------- Admin tool ---------- */
add_action( 'admin_menu', function () {
	add_management_page( 'نقل الأسئلة والأزرار', 'نقل الأسئلة والأزرار (زاد)', 'manage_options', 'zad-faqmig', 'zad_faqm_page' );
} );

function zad_faqm_target_key( $id ) { return ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) ? '_zad_h_faq' : '_zad_faq'; }

function zad_faqm_scan() {
	global $wpdb;
	$types = array_values( array_unique( array_merge( zad_service_types(), array( 'page', 'post' ) ) ) );
	$in = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ({$in}) AND (post_content LIKE '%wp-block-wpsp-faq%' OR post_content LIKE '%wp:wpsp/faq%' OR (post_content LIKE '%wp:html%' AND post_content LIKE '%wa.me%' AND post_content LIKE '%tel:%')) ORDER BY post_title LIMIT 600" ); // phpcs:ignore
	$rows = array();
	foreach ( $ids as $id ) {
		$p = get_post( $id ); if ( ! $p ) { continue; }
		list( , $nf ) = zad_faqm_strip_faq( $p->post_content ); list( , $nc ) = zad_faqm_cta( $p->post_content );
		$pairs = zad_wpsp_pairs( $p->post_content );
		$key = zad_faqm_target_key( $id );
		$rows[ $id ] = array( 'p' => $p, 'blocks' => $nf, 'pairs' => $pairs, 'cta' => $nc, 'have' => count( array_filter( (array) get_post_meta( $id, $key, true ), function ( $r ) { return ! empty( $r['q'] ); } ) ), 'bk' => (bool) get_post_meta( $id, ZAD_FAQM_BACKUP, true ) );
	}
	return $rows;
}

function zad_faqm_run_one( $id, $do_faq, $do_cta ) {
	$p = get_post( $id ); if ( ! $p ) { return ''; }
	$c = $p->post_content; $key = zad_faqm_target_key( $id ); $msg = array();
	$bk = array( 'content' => $c, 'key' => $key, 'prev' => get_post_meta( $id, $key, true ), 'time' => time() );
	if ( $do_faq ) {
		$pairs = zad_wpsp_pairs( $c );
		if ( $pairs ) {
			$have = array_values( array_filter( (array) get_post_meta( $id, $key, true ), function ( $r ) { return ! empty( $r['q'] ); } ) );
			$known = array_map( function ( $r ) { return mb_strtolower( $r['q'] ); }, $have );
			$added = 0;
			foreach ( $pairs as $q ) { if ( ! in_array( mb_strtolower( $q['q'] ), $known, true ) ) { $have[] = $q; $added++; } }
			update_post_meta( $id, $key, $have );
			list( $c, ) = zad_faqm_strip_faq( $c );
			$msg[] = "أُضيف {$added} سؤال";
		}
	}
	if ( $do_cta ) { list( $c, $n ) = zad_faqm_cta( $c ); if ( $n ) { $msg[] = "{$n} مجموعة أزرار → [zad_cta]"; } }
	if ( $c !== $p->post_content ) {
		update_post_meta( $id, ZAD_FAQM_BACKUP, $bk );
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $c ) ) );
	}
	return implode( '، ', $msg );
}

function zad_faqm_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$log = array();
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['zad_faqm_act'] ) && check_admin_referer( 'zad_faqm' ) ) { // phpcs:ignore
		$act = sanitize_key( wp_unslash( $_POST['zad_faqm_act'] ) );
		if ( 'run' === $act ) {
			if ( empty( $_POST['backup'] ) ) { $log[] = 'لم يُنفَّذ شيء: أكّد أنك أخذت نسخة احتياطية.'; } else {
				$do_faq = ! empty( $_POST['do_faq'] ); $do_cta = ! empty( $_POST['do_cta'] );
				foreach ( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) ) as $id ) {
					$m = zad_faqm_run_one( $id, $do_faq, $do_cta );
					if ( $m ) { $log[] = get_the_title( $id ) . ': ' . $m; }
				}
				if ( ! $log ) { $log[] = 'لم يُحدَّد شيء.'; }
			}
		} elseif ( 'undo' === $act ) {
			$id = absint( $_POST['undo_id'] ?? 0 ); $bk = get_post_meta( $id, ZAD_FAQM_BACKUP, true );
			if ( is_array( $bk ) && isset( $bk['content'] ) ) {
				wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $bk['content'] ) ) );
				if ( '' === $bk['prev'] || false === $bk['prev'] ) { delete_post_meta( $id, $bk['key'] ); } else { update_post_meta( $id, $bk['key'], $bk['prev'] ); }
				delete_post_meta( $id, ZAD_FAQM_BACKUP );
				$log[] = 'تم التراجع عن: ' . get_the_title( $id );
			}
		}
	}
	$rows = zad_faqm_scan();
	echo '<div class="wrap" dir="rtl"><h1>نقل الأسئلة والأزرار إلى الثيم</h1>';
	echo '<div class="notice notice-warning"><p><b>خذ نسخة احتياطية أولاً.</b> تُعاين القائمة أولاً ولا يتغير شيء حتى تحدد وتؤكد. كل صفحة تتغير تُحفظ نسخة من محتواها الأصلي ويمكن التراجع عنها بزر «تراجع». الروابط لا تتغير.</p></div>';
	foreach ( $log as $l ) { echo '<div class="notice notice-info"><p>' . esc_html( $l ) . '</p></div>'; }
	echo '<p>1) <b>الأسئلة:</b> تُقرأ من بلوك أسئلة الإضافة (wpsp) وتُضاف إلى حقل الأسئلة في الثيم، ويُحذف البلوك والعنوان «الأسئلة الشائعة» الذي قبله. الأسئلة الموجودة سابقاً في الثيم لا تُحذف ولا تتكرر.<br>2) <b>الأزرار:</b> مجموعات أزرار الاتصال/واتساب المكتوبة HTML تصير <code>[zad_cta]</code> وتأخذ رقمك من إعدادات الثيم.</p>';
	if ( ! $rows ) { echo '<p><b>لا توجد صفحات تحتاج نقلاً.</b></p></div>'; return; }
	echo '<form method="post">'; wp_nonce_field( 'zad_faqm' );
	echo '<p><label><input type="checkbox" name="do_faq" value="1" checked> نقل الأسئلة</label> &nbsp; <label><input type="checkbox" name="do_cta" value="1" checked> تحويل الأزرار</label></p>';
	echo '<p><label><input type="checkbox" onclick="jQuery(\'.zfm\').prop(\'checked\',this.checked)"> تحديد الكل</label></p>';
	echo '<table class="widefat striped"><thead><tr><th></th><th>الصفحة</th><th>النوع</th><th>أسئلة الإضافة</th><th>أسئلة الثيم الآن</th><th>مجموعات أزرار</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $id => $r ) {
		echo '<tr><td><input class="zfm" type="checkbox" name="ids[]" value="' . (int) $id . '"></td><td><a href="' . esc_url( get_edit_post_link( $id, 'raw' ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( $r['p']->post_type ) . '</td><td>' . count( $r['pairs'] ) . '</td><td>' . (int) $r['have'] . '</td><td>' . (int) $r['cta'] . '</td><td>' . ( $r['bk'] ? '<button class="button" name="zad_faqm_act" value="undo" onclick="this.form.undo_id.value=' . (int) $id . '">تراجع</button>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table><input type="hidden" name="undo_id" value="">';
	echo '<p><label><input type="checkbox" name="backup" value="1"> أكّد أنني أخذت نسخة احتياطية</label></p><p><button class="button button-primary" name="zad_faqm_act" value="run" onclick="return confirm(\'تنفيذ النقل على الصفحات المحددة؟\')">نقل المحدد</button></p></form>';
	echo '<p class="description">بعد التأكد من الصفحات، أوقف إضافة الأسئلة. أقترح تجربة صفحة واحدة أولاً.</p></div>';
}
