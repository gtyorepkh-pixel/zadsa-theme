<?php defined( 'ABSPATH' ) || exit;
/**
 * «فصل الإجابة المباشرة» (الأدوات → فصل الإجابة المباشرة).
 *
 * The direct answer of a question page lives in post_excerpt. Older questions have no excerpt, so the page took the first paragraph of the content as the answer
 * and that same paragraph stayed in the content (shown twice). This tool shows a preview of every zad_faq and, for the rows the admin selects:
 *   A) excerpt empty, content opens with a text paragraph  -> the paragraph moves to the excerpt and is removed from the content
 *   B) excerpt present, content opens with the same / a very similar paragraph -> only the duplicated paragraph is removed from the content
 *   C) excerpt present and the content differs -> untouched
 *   M) manual: content opens with a heading / list / image / a paragraph holding links, or nothing would be left -> untouched, listed
 * Before any edit the original content and excerpt are kept in post meta (_zad_faq_content_backup / _zad_faq_excerpt_backup); «تراجع عن الكل» restores them.
 * Edits go through wp_update_post (revisions are recorded). Schema code and zad_faq_answer_text() are not touched.
 */

function zad_fas_cases() {
	return array(
		'A' => 'A — المقتطف فارغ: تنقل الفقرة الأولى',
		'B' => 'B — مكررة: تحذف الفقرة المكررة فقط',
		'C' => 'C — بدون تكرار: لا تغيير',
		'M' => 'يدوي — يحتاج مراجعتك',
	);
}

/** Comparison form of a text: tags/label/punctuation/diacritics out, letters unified. */
function zad_fas_norm( $t ) {
	$t = zad_ar_norm( zad_clean_answer( $t ) );
	$t = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $t );
	return trim( preg_replace( '/\s+/u', ' ', $t ) );
}

/** Same or very similar text (≥ 88 % alike and of comparable length). */
function zad_fas_dup( $a, $b ) {
	$a = zad_fas_norm( $a );
	$b = zad_fas_norm( $b );
	if ( '' === $a || '' === $b ) { return false; }
	if ( $a === $b ) { return true; }
	$la = mb_strlen( $a, 'UTF-8' );
	$lb = mb_strlen( $b, 'UTF-8' );
	if ( min( $la, $lb ) / max( $la, $lb ) < 0.8 ) { return false; }
	similar_text( mb_substr( $a, 0, 600, 'UTF-8' ), mb_substr( $b, 0, 600, 'UTF-8' ), $pct );
	return $pct >= 88;
}

/** Plain text of a paragraph: tags and the «الإجابة:» label out, NBSP to a space. */
function zad_fas_text( $html ) {
	return trim( str_replace( "\xc2\xa0", ' ', zad_clean_answer( $html ) ) );
}

/**
 * The first thing of a content string.
 * @return array found (bool: it is a text paragraph) · why (string, when not a paragraph) · unsafe (string, a paragraph that cannot be moved as plain text)
 *               · text · rest (content after the paragraph) · cut (the removed prefix)
 */
function zad_fas_first_para( $content ) {
	$c = (string) $content;
	if ( '' === trim( $c ) ) { return array( 'found' => false, 'why' => 'المحتوى فارغ' ); }
	$cut = '';
	$m   = null;
	if ( false !== strpos( $c, '<!-- wp:' ) ) { // Gutenberg content: the first core/paragraph block
		$re = '/\A\s*<!-- wp:paragraph(?:\s+\{[^\n]*?\})?\s*-->\s*(<p\b[^>]*>(.*?)<\/p>)\s*<!-- \/wp:paragraph -->\s*/s';
		$off = 0;
		while ( preg_match( $re, substr( $c, $off ), $m ) ) {
			$cut .= $m[0];
			$off += strlen( $m[0] );
			if ( '' !== zad_fas_text( $m[2] ) ) { break; }
			$m = null; // an empty paragraph block: skipped (removed together with the answer)
		}
		if ( ! $m ) {
			foreach ( parse_blocks( $c ) as $b ) {
				if ( empty( $b['blockName'] ) && '' === trim( $b['innerHTML'] ) ) { continue; }
				$names = array( 'core/heading' => 'عنوان', 'core/list' => 'قائمة', 'core/image' => 'صورة', 'core/table' => 'جدول', 'core/quote' => 'اقتباس', 'core/html' => 'HTML', 'core/shortcode' => 'شورتكود', 'core/freeform' => 'محتوى كلاسيكي' );
				$n = (string) $b['blockName'];
				return array( 'found' => false, 'why' => 'يبدأ بـ(' . ( $names[ $n ] ?? ( $n ? 'كتلة ' . $n : 'نص خارج الكتل' ) ) . ')' );
			}
			return array( 'found' => false, 'why' => 'المحتوى فارغ' );
		}
		$inner = $m[2];
		$rest  = substr( $c, strlen( $cut ) );
	} else { // classic content
		$t = ltrim( $c );
		if ( preg_match( '/\A<p\b[^>]*>(.*?)<\/p>\s*/s', $t, $m ) ) {
			$cut = $m[0]; $inner = $m[1];
		} elseif ( preg_match( '/\A<(h[1-6]|ul|ol|table|figure|img|div|blockquote|pre|hr|iframe|section|aside|details|form|style|script)\b/i', $t, $mm ) ) {
			return array( 'found' => false, 'why' => 'يبدأ بعنصر <' . strtolower( $mm[1] ) . '>' );
		} else { // plain text separated by blank lines (what the classic editor stores)
			preg_match( '/\A(.+?)(?:\R[ \t]*\R\s*|\s*\z)/s', $t, $m );
			$cut = $m[0]; $inner = $m[1];
			if ( preg_match( '/<(h[1-6]|ul|ol|table|figure|div|blockquote|pre|iframe|section)\b/i', $inner ) ) { return array( 'found' => false, 'why' => 'يبدأ بعنصر غير فقرة' ); }
		}
		$rest = substr( $t, strlen( $cut ) );
	}
	$text   = zad_fas_text( $inner );
	$unsafe = '';
	if ( '' === $text ) { return array( 'found' => false, 'why' => 'الفقرة الأولى بلا نص' ); }
	if ( preg_match( '/<(a|img|iframe|video|audio|svg)\b/i', $inner ) ) { $unsafe = 'الفقرة الأولى فيها رابط أو وسائط (تُفقد عند تحويلها لنص عادي)'; }
	elseif ( preg_match( '/\[[a-z][a-z0-9_\-]*(?:\s[^\]]*)?\]/i', $inner ) ) { $unsafe = 'الفقرة الأولى فيها شورتكود'; }
	elseif ( '' === trim( preg_replace( '/<!--.*?-->/s', '', $rest ) ) ) { $unsafe = 'المحتوى كله هذه الفقرة — لن يبقى محتوى'; }
	return array( 'found' => true, 'unsafe' => $unsafe, 'text' => $text, 'rest' => ltrim( $rest ), 'cut' => $cut );
}

/** First words of what stays in the content (headings included). */
function zad_fas_snip( $content ) {
	$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( preg_replace( array( '/<!--.*?-->/s', '/<[^>]+>/' ), array( '', ' ' ), (string) $content ) ) ) );
	return wp_trim_words( $t, 14, '…' );
}

/** Decision for one question. */
function zad_fas_analyze( $p ) {
	$ex  = trim( (string) $p->post_excerpt );
	$fp  = zad_fas_first_para( $p->post_content );
	$row = array( 'id' => (int) $p->ID, 'title' => $p->post_title, 'status' => $p->post_status, 'excerpt' => $ex, 'case' => 'C', 'answer' => '', 'remain' => '', 'note' => '', 'new_content' => '', 'words' => 0 );
	if ( empty( $fp['found'] ) ) {
		if ( '' === $ex ) { $row['case'] = 'M'; $row['note'] = $fp['why'] . ' — لا مقتطف ولا فقرة تُنقل'; }
		else { $row['note'] = $fp['why'] . ' — لا تكرار في بداية المحتوى'; }
		return $row;
	}
	$row['remain'] = zad_fas_snip( $fp['rest'] );
	if ( '' === $ex ) {
		$row['answer'] = $fp['text'];
		$row['words']  = zad_faq_words( $fp['text'] );
		if ( $fp['unsafe'] ) { $row['case'] = 'M'; $row['note'] = $fp['unsafe']; $row['answer'] = ''; return $row; }
		$row['case'] = 'A'; $row['new_content'] = $fp['rest'];
		if ( $row['words'] > 60 ) { $row['note'] = 'الفقرة طويلة (' . $row['words'] . ' كلمة) — قصّرها بعد النقل'; }
		return $row;
	}
	if ( zad_fas_dup( $ex, $fp['text'] ) ) {
		$row['answer'] = zad_clean_answer( $ex );
		$row['words']  = zad_faq_words( $row['answer'] );
		if ( $fp['unsafe'] ) { $row['case'] = 'M'; $row['note'] = $fp['unsafe']; return $row; }
		$row['case'] = 'B'; $row['new_content'] = $fp['rest'];
		return $row;
	}
	$row['note'] = 'المقتطف يختلف عن أول فقرة — لا تغيير';
	$row['remain'] = '';
	return $row;
}

function zad_fas_posts() {
	return get_posts( array( 'post_type' => 'zad_faq', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
}

/** Applies the split to the given ids. @return array id => array( ok, case, msg ) */
function zad_fas_apply( $ids ) {
	$out = array();
	$GLOBALS['zad_fas_running'] = true; // keeps the auto-link-on-save hook (inc/zad-faq.php) from touching service links
	foreach ( array_unique( array_map( 'absint', (array) $ids ) ) as $id ) {
		$p = get_post( $id );
		if ( ! $p || 'zad_faq' !== $p->post_type ) { $out[ $id ] = array( 'ok' => false, 'case' => '', 'msg' => 'ليس سؤالاً' ); continue; }
		if ( metadata_exists( 'post', $id, '_zad_faq_content_backup' ) ) { $out[ $id ] = array( 'ok' => false, 'case' => '', 'msg' => 'سبق فصله — تراجع أولاً' ); continue; }
		$r = zad_fas_analyze( $p ); // recomputed on the server: the form only supplies ids
		if ( ! in_array( $r['case'], array( 'A', 'B' ), true ) ) { $out[ $id ] = array( 'ok' => false, 'case' => $r['case'], 'msg' => 'الحالة ' . $r['case'] . ' — لا يُعدَّل' ); continue; }
		update_post_meta( $id, '_zad_faq_content_backup', wp_slash( $p->post_content ) );
		update_post_meta( $id, '_zad_faq_excerpt_backup', wp_slash( $p->post_excerpt ) );
		$args = array( 'ID' => $id, 'post_content' => $r['new_content'] );
		if ( 'A' === $r['case'] ) { $args['post_excerpt'] = $r['answer']; }
		$res = wp_update_post( wp_slash( $args ), true );
		if ( is_wp_error( $res ) || ! $res ) {
			delete_post_meta( $id, '_zad_faq_content_backup' ); delete_post_meta( $id, '_zad_faq_excerpt_backup' );
			$out[ $id ] = array( 'ok' => false, 'case' => $r['case'], 'msg' => is_wp_error( $res ) ? $res->get_error_message() : 'فشل الحفظ' );
			continue;
		}
		update_post_meta( $id, '_zad_faq_split_hash', md5( (string) get_post_field( 'post_content', $id ) ) );
		update_post_meta( $id, '_zad_faq_split_case', $r['case'] );
		$out[ $id ] = array( 'ok' => true, 'case' => $r['case'], 'msg' => 'A' === $r['case'] ? 'نُقلت الفقرة إلى المقتطف' : 'حُذفت الفقرة المكررة' );
	}
	unset( $GLOBALS['zad_fas_running'] );
	if ( $out ) {
		$log   = (array) get_option( 'zad_fas_log', array() );
		$log[] = array( 't' => time(), 'u' => get_current_user_id(), 'done' => array_keys( array_filter( wp_list_pluck( $out, 'ok' ) ) ) );
		update_option( 'zad_fas_log', array_slice( $log, -20 ), false );
	}
	return $out;
}

function zad_fas_backed_up() {
	return get_posts( array( 'post_type' => 'zad_faq', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true, 'meta_key' => '_zad_faq_content_backup', 'meta_compare' => 'EXISTS' ) );
}

/** Restores content + excerpt of every post this tool changed (skips a post edited since — its revisions keep both versions). @return array id => msg */
function zad_fas_undo() {
	$out = array();
	$GLOBALS['zad_fas_running'] = true;
	foreach ( zad_fas_backed_up() as $id ) {
		$cur  = (string) get_post_field( 'post_content', $id );
		$hash = (string) get_post_meta( $id, '_zad_faq_split_hash', true );
		if ( '' !== $hash && md5( $cur ) !== $hash ) { $out[ $id ] = array( false, 'عُدّل المحتوى بعد الفصل — لم يُسترجع (راجع النسخ السابقة من شاشة التعديل)' ); continue; }
		$res = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => (string) get_post_meta( $id, '_zad_faq_content_backup', true ), 'post_excerpt' => (string) get_post_meta( $id, '_zad_faq_excerpt_backup', true ) ) ), true );
		if ( is_wp_error( $res ) || ! $res ) { $out[ $id ] = array( false, 'فشل الاسترجاع' ); continue; }
		foreach ( array( '_zad_faq_content_backup', '_zad_faq_excerpt_backup', '_zad_faq_split_hash', '_zad_faq_split_case' ) as $k ) { delete_post_meta( $id, $k ); }
		$out[ $id ] = array( true, 'استُرجع' );
	}
	unset( $GLOBALS['zad_fas_running'] );
	return $out;
}

add_action( 'admin_menu', function () {
	add_management_page( 'فصل الإجابة المباشرة', 'فصل الإجابة المباشرة', 'manage_options', 'zad-faq-split', 'zad_fas_page' );
} );

function zad_fas_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$cases = zad_fas_cases();
	echo '<div class="wrap" dir="rtl"><h1>فصل الإجابة المباشرة</h1>';
	echo '<p>كل سؤال تُخزَّن إجابته المباشرة في حقل «الإجابة المباشرة» (المقتطف). هذه الأداة تنقل الفقرة الأولى من المحتوى إلى هذا الحقل (أو تحذفها من المحتوى إن كانت مكررة) حتى لا تظهر الإجابة مرتين. <b>لا يُنفَّذ شيء قبل أن تحدد الأسئلة وتضغط «نفّذ على المحدد».</b></p>';
	echo '<div class="notice notice-warning inline"><p><b>قبل التنفيذ:</b> خذ نسخة احتياطية من قاعدة البيانات. وبعد التنفيذ امسح كاش LiteSpeed (LiteSpeed Cache ← Toolbox ← Purge All).</p></div>';

	if ( isset( $_POST['zad_fas_do'] ) ) {
		check_admin_referer( 'zad_fas' );
		$what = sanitize_key( wp_unslash( $_POST['zad_fas_do'] ) );
		if ( 'run' === $what ) {
			$res = zad_fas_apply( isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array() );
			$ok  = count( array_filter( wp_list_pluck( $res, 'ok' ) ) );
			echo '<div class="notice notice-' . ( $ok ? 'success' : 'warning' ) . '"><p>تم تعديل <b>' . (int) $ok . '</b> من ' . count( $res ) . ' سؤالاً. امسح كاش LiteSpeed الآن.</p></div>';
			if ( $res ) {
				echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>السؤال</th><th>الحالة</th><th>النتيجة</th><th></th></tr></thead><tbody>';
				foreach ( $res as $id => $r ) {
					echo '<tr><td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( $r['case'] ) . '</td><td>' . ( $r['ok'] ? '✅ ' : '⚠️ ' ) . esc_html( $r['msg'] ) . '</td><td>' . ( $r['ok'] ? '<a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">عرض الصفحة</a>' : '' ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		} elseif ( 'undo' === $what ) {
			$res = zad_fas_undo();
			$ok  = count( array_filter( array_column( $res, 0 ) ) );
			echo '<div class="notice notice-' . ( $ok === count( $res ) ? 'success' : 'warning' ) . '"><p>تم استرجاع <b>' . (int) $ok . '</b> من ' . count( $res ) . ' سؤالاً. امسح كاش LiteSpeed.</p>';
			foreach ( $res as $id => $r ) { if ( ! $r[0] ) { echo '<p>⚠️ <a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>: ' . esc_html( $r[1] ) . '</p>'; } }
			echo '</div>';
		}
	}

	$rows = array();
	$cnt  = array( 'A' => 0, 'B' => 0, 'C' => 0, 'M' => 0 );
	foreach ( zad_fas_posts() as $p ) { $r = zad_fas_analyze( $p ); $rows[] = $r; $cnt[ $r['case'] ]++; }
	$done = zad_fas_backed_up();

	echo '<h2>الملخص — ' . count( $rows ) . ' سؤالاً</h2><ul style="list-style:disc;margin-inline-start:22px">';
	foreach ( $cases as $k => $lbl ) { echo '<li><b>' . (int) $cnt[ $k ] . '</b> — ' . esc_html( $lbl ) . '</li>'; }
	echo '</ul>';

	echo '<form method="post">';
	wp_nonce_field( 'zad_fas' );
	echo '<p class="zad-fas-bar"><label>عرض: <select id="zad-fas-filter"><option value="">الكل</option>';
	foreach ( $cases as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>'; }
	echo '</select></label> <button type="button" class="button" data-sel="A">حدّد كل A</button> <button type="button" class="button" data-sel="B">حدّد كل B</button> <button type="button" class="button" data-sel="trial">حدّد 3 للتجربة</button> <button type="button" class="button" data-sel="none">ألغِ التحديد</button> <span id="zad-fas-n"></span></p>';
	echo '<table class="widefat striped" id="zad-fas-t"><thead><tr><th style="width:28px"></th><th>السؤال</th><th style="width:70px">الحالة</th><th>الإجابة التي ستُضبط</th><th>أول سطر سيبقى في المحتوى</th><th>ملاحظة</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$can = in_array( $r['case'], array( 'A', 'B' ), true ) && ! in_array( $r['id'], $done, true );
		echo '<tr data-case="' . esc_attr( $r['case'] ) . '" data-slug="' . esc_attr( get_post_field( 'post_name', $r['id'] ) ) . '"><td>' . ( $can ? '<input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '">' : '' ) . '</td>'
			. '<td><a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html( $r['title'] ) . '</a> <small>(' . esc_html( $r['status'] ) . ')</small><br><code>' . esc_html( get_post_field( 'post_name', $r['id'] ) ) . '</code></td>'
			. '<td><b>' . esc_html( in_array( $r['id'], $done, true ) ? '✔ ' . get_post_meta( $r['id'], '_zad_faq_split_case', true ) : $r['case'] ) . '</b></td>'
			. '<td>' . ( $r['answer'] ? esc_html( wp_trim_words( $r['answer'], 30, '…' ) ) . ' <small>(' . (int) $r['words'] . ' كلمة)</small>' : '—' ) . '</td>'
			. '<td>' . ( in_array( $r['case'], array( 'A', 'B' ), true ) ? esc_html( $r['remain'] ) : '—' ) . '</td><td>' . esc_html( $r['note'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p style="margin-top:14px"><button class="button button-primary" name="zad_fas_do" value="run" onclick="return confirm(\'تنفيذ الفصل على الأسئلة المحددة؟ يُحفظ أصل المحتوى للتراجع.\');">نفّذ على المحدد</button></p></form>';

	echo '<hr><h2>التراجع</h2><p>أسئلة غُيِّرت بهذه الأداة ويمكن استرجاعها: <b>' . count( $done ) . '</b>.</p><form method="post">';
	wp_nonce_field( 'zad_fas' );
	echo '<button class="button" name="zad_fas_do" value="undo"' . ( $done ? '' : ' disabled' ) . ' onclick="return confirm(\'استرجاع المحتوى والمقتطف الأصليين لكل الأسئلة المعدَّلة؟\');">تراجع عن الكل</button></form>';
	?>
<script>
(function () {
	var tb = document.getElementById('zad-fas-t'); if (!tb) { return; }
	var rows = [].slice.call(tb.tBodies[0].rows), f = document.getElementById('zad-fas-filter'), n = document.getElementById('zad-fas-n');
	function cnt() { n.textContent = tb.querySelectorAll('input[type=checkbox]:checked').length + ' محدد'; }
	f.addEventListener('change', function () { rows.forEach(function (r) { r.style.display = (!f.value || r.getAttribute('data-case') === f.value) ? '' : 'none'; }); });
	tb.addEventListener('change', cnt);
	function pick(fn) { rows.forEach(function (r) { var c = r.querySelector('input[type=checkbox]'); if (c) { c.checked = fn(r); } }); cnt(); }
	[].forEach.call(document.querySelectorAll('.zad-fas-bar [data-sel]'), function (b) {
		b.addEventListener('click', function () {
			var k = b.getAttribute('data-sel'), seen = {};
			if (k === 'none') { pick(function () { return false; }); }
			else if (k === 'trial') { var gotA = 0, gotB = 0; pick(function (r) { var c = r.getAttribute('data-case'), s = r.getAttribute('data-slug'); if (s === 'is-spraying-pesticides-once-enough') { return true; } if (c === 'A' && !gotA) { gotA = 1; return true; } if (c === 'B' && !gotB) { gotB = 1; return true; } return false; }); }
			else { pick(function (r) { return r.getAttribute('data-case') === k; }); }
		});
	});
	cnt();
})();
</script></div>
	<?php
}
