<?php defined( 'ABSPATH' ) || exit;
/**
 * 3.46.11 — Tools › «إزالة ذكر مدن (زاد)».
 * Finds a city name (البكيرية / الرس by default) in the text of published pages and in term descriptions, proposes a fix that keeps the
 * sentence correct, and shows every change before → after. Nothing is written until «نفّذ»; the original text is kept and «تراجع» restores it.
 * Safe rules only (inside lists of cities):  «وعنيزة والرس والبكيرية» → «وعنيزة» · «بريدة، الرس، عنيزة» → «بريدة، عنيزة» · «الرس وبريدة» → «بريدة»
 * · a list item that is only about the city is removed. Anything else is listed as «يدوي» with an edit link.
 */

const ZAD_CC_LOG = 'zad_cc_log';

function zad_cc_cities() { return apply_filters( 'zad_cc_cities', array( 'الرس', 'البكيرية' ) ); }

/** Regex part matching one of the cities as a whole word (optional ب prefix). */
function zad_cc_city_rx() {
	$alts = array();
	foreach ( zad_cc_cities() as $c ) { $alts[] = preg_quote( $c, '/' ); $alts[] = 'ب' . preg_quote( $c, '/' ); }
	return '(?:' . implode( '|', $alts ) . ')(?![\x{0621}-\x{064A}\x{0671}-\x{06D3}])';
}
function zad_cc_plain( $html ) { return wp_strip_all_tags( preg_replace( '/<[^>]+>/u', ' ', (string) $html ) ); }
function zad_cc_has( $text ) { return (bool) preg_match( '/(?<![\x{0621}-\x{064A}\x{0671}-\x{06D3}])(?:و)?' . zad_cc_city_rx() . '/u', zad_cc_plain( $text ) ); }

/** Other place names: an inline removal is accepted only when one of these stays in the sentence (so «في مدن مثل الرس والبكيرية،» is never left empty). */
function zad_cc_others_rx() {
	$o = apply_filters( 'zad_cc_other_places', array( 'بريدة', 'بريده', 'عنيزة', 'عنيزه', 'الخبراء', 'رياض الخبراء', 'الأسياح', 'الاسياح', 'المذنب', 'البدائع', 'القصيم', 'الرياض', 'جدة', 'الدمام', 'الخبر', 'القطيف', 'الخرج', 'نجران', 'مكة', 'المدينة', 'الطائف', 'الأحساء', 'الاحساء', 'الجبيل', 'حائل', 'تبوك', 'أبها', 'جازان' ) );
	return '/(?:ب)?(?:' . implode( '|', array_map( function ( $x ) { return preg_quote( $x, '/' ); }, $o ) ) . ')/u';
}

/** The changed part of a sentence with a little context: array( before, after ). */
function zad_cc_diff( $a, $b ) {
	$a = trim( preg_replace( '/\s+/u', ' ', $a ) ); $b = trim( preg_replace( '/\s+/u', ' ', $b ) );
	$la = mb_strlen( $a ); $lb = mb_strlen( $b ); $p = 0;
	while ( $p < $la && $p < $lb && mb_substr( $a, $p, 1 ) === mb_substr( $b, $p, 1 ) ) { $p++; }
	$s = 0;
	while ( $s < $la - $p && $s < $lb - $p && mb_substr( $a, $la - 1 - $s, 1 ) === mb_substr( $b, $lb - 1 - $s, 1 ) ) { $s++; }
	$from = max( 0, $p - 45 ); $ctx = 45;
	$cut = function ( $x, $len ) use ( $from, $p, $s, $ctx ) { $end = min( $len, $len - $s + $ctx ); return ( $from ? '… ' : '' ) . mb_substr( $x, $from, $end - $from ) . ( $end < $len ? ' …' : '' ); };
	return array( $cut( $a, $la ), $cut( $b, $lb ) );
}

/** Apply the safe rules to one text (HTML kept). Returns array( new, changes[], left ). */
function zad_cc_fix( $html ) {
	$c = zad_cc_city_rx(); $L = '[\x{0621}-\x{064A}\x{0671}-\x{06D3}]'; $others = zad_cc_others_rx();
	$changes = array();
	$note = function ( $before, $after ) use ( &$changes ) { $changes[] = 0 === strpos( $after, '(حذف' ) ? array( trim( zad_cc_plain( $before ) ), $after ) : zad_cc_diff( zad_cc_plain( $before ), zad_cc_plain( $after ) ); };
	// 1) inside sentences: drop the city from a list of places, only when another place is still named in that sentence
	$parts = preg_split( '/(<[^>]+>)/u', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as $i => $part ) {
		if ( '' === $part || '<' === $part[0] || ! zad_cc_has( $part ) ) { continue; }
		$sents = preg_split( '/(?<=[.!؟?\n:؛])/u', $part );
		foreach ( $sents as $k => $sen ) {
			if ( ! zad_cc_has( $sen ) ) { continue; }
			$n = preg_replace( '/(?:\s*[،,]\s*|\s+و\s*)' . $c . '/u', '', $sen );       // «، الرس» · « والرس»
			$n = preg_replace( '/(?<!' . $L . ')' . $c . '\s*[،,]\s*/u', '', $n );      // «الرس، » at the head of a list
			$n = preg_replace( '/(?<!' . $L . ')' . $c . '\s+و(?=' . $L . ')/u', '', $n ); // «الرس وبريدة» → «بريدة»
			if ( $n !== $sen && preg_match( $others, $n ) ) { $note( $sen, $n ); $sents[ $k ] = $n; }
		}
		$parts[ $i ] = implode( '', $sents );
	}
	$html = implode( '', $parts );
	// 1b) lists separated by dashes («عنيزة - بريدة - الرس - البكيرية»): a short part that names the city is dropped; a long part ending with it loses the name
	$parts = preg_split( '/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as $i => $part ) {
		if ( '' === $part || '<' === $part[0] || ! zad_cc_has( $part ) || ! preg_match( '/\s[-ـ–]\s/u', $part ) ) { continue; }
		$seg = preg_split( '/(\s+[-ـ–]\s+)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE );
		$keep = array(); $changed = false; $glue = '';
		for ( $j = 0; $j < count( $seg ); $j += 2 ) {
			$sg = $seg[ $j ]; $sep = $seg[ $j + 1 ] ?? '';
			if ( zad_cc_has( $sg ) ) {
				if ( mb_strlen( trim( $sg ) ) <= 45 ) { $changed = true; continue; }
				$cut = preg_replace( '/\s*(?:و)?' . $c . '\s*$/u', '', $sg );
				if ( $cut !== $sg ) { $changed = true; $keep[] = array( $cut, ' ' ); continue; }
			}
			$keep[] = array( $sg, $sep );
		}
		if ( ! $changed || count( $keep ) < 1 ) { continue; }
		$out = ''; $last = count( $keep ) - 1;
		foreach ( $keep as $k => $kv ) { $out .= $kv[0]; if ( $k < $last ) { $out .= ' ' === $kv[1] ? ' ' : ( '' !== trim( $kv[1] ) ? $kv[1] : ' - ' ); } }
		if ( preg_match( '/\s*$/u', $part, $tail ) ) { $out = rtrim( $out ) . $tail[0]; }
		if ( preg_match( $others, $out ) || ! zad_cc_has( $out ) ) { $note( $part, $out ); $parts[ $i ] = $out; }
	}
	$html = implode( '', $parts );
	// 1c) a heading about the city (h2–h4) with everything under it, up to the next heading of the same or a higher level
	if ( preg_match_all( '/<h([2-6])\b[^>]*>(.*?)<\/h\1>/isu', $html, $hm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		for ( $k = count( $hm ) - 1; $k >= 0; $k-- ) {
			$lvl = (int) $hm[ $k ][1][0]; $head = trim( zad_cc_plain( $hm[ $k ][2][0] ) );
			if ( $lvl > 4 || '' === $head || ! zad_cc_has( $head ) || mb_strlen( $head ) > 90 ) { continue; }
			$start = $hm[ $k ][0][1]; $end = strlen( $html );
			for ( $q = $k + 1; $q < count( $hm ); $q++ ) { if ( (int) $hm[ $q ][1][0] <= $lvl ) { $end = $hm[ $q ][0][1]; break; } }
			$chunk = substr( $html, $start, $end - $start );
			$body = trim( zad_cc_plain( substr( $chunk, strlen( $hm[ $k ][0][0] ) ) ) );
			$note( $head . ( '' !== $body ? ' — ' . mb_substr( $body, 0, 120 ) . ( mb_strlen( $body ) > 120 ? '…' : '' ) : '' ), '(حذف القسم: العنوان وما تحته)' );
			$html = substr( $html, 0, $start ) . substr( $html, $end );
		}
	}
	// 1d) a short paragraph / line that is only about the city («شركة تنظيف كنب بالرس»)
	$html = preg_replace_callback( '/<(p|h[5-6])\b[^>]*>(?:(?!<\/\1>).)*<\/\1>/isu', function ( $m ) use ( $note ) {
		$t = trim( zad_cc_plain( $m[0] ) );
		if ( zad_cc_has( $t ) && mb_strlen( $t ) <= 60 ) { $note( $t, '(حذف السطر)' ); return ''; }
		return $m[0];
	}, $html );
	// 2) a short list item still about the city (a link to its old page, «نخدم مدينة الرس»…) is removed
	$html = preg_replace_callback( '/<li\b[^>]*>(?:(?!<\/li>).)*<\/li>/isu', function ( $m ) use ( $note ) {
		$t = trim( zad_cc_plain( $m[0] ) );
		if ( zad_cc_has( $t ) && mb_strlen( $t ) <= 90 ) { $note( $t, '(حذف البند)' ); return ''; }
		return $m[0];
	}, $html );
	$left = preg_match_all( '/(?<![\x{0621}-\x{064A}\x{0671}-\x{06D3}])(?:و)?' . $c . '/u', zad_cc_plain( $html ) );
	return array( $html, $changes, (int) $left );
}

/** Everything that mentions a city: array( key => array( kind post|term, id, title, edit, new, changes, left ) ). */
function zad_cc_scan() {
	global $wpdb;
	$out = array();
	$likes = array(); foreach ( zad_cc_cities() as $cc ) { $likes[] = $wpdb->prepare( 'post_content LIKE %s OR post_title LIKE %s', '%' . $wpdb->esc_like( $cc ) . '%', '%' . $wpdb->esc_like( $cc ) . '%' ); }
	$rows = $wpdb->get_results( "SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision','nav_menu_item','attachment','customize_changeset','oembed_cache') AND (" . implode( ' OR ', $likes ) . ') ORDER BY post_type, ID' ); // phpcs:ignore
	foreach ( (array) $rows as $r ) {
		$in_body = zad_cc_has( $r->post_content ); $in_title = zad_cc_has( $r->post_title );
		if ( ! $in_body && ! $in_title ) { continue; }
		list( $new, $ch, $left ) = $in_body ? zad_cc_fix( $r->post_content ) : array( $r->post_content, array(), 0 );
		$out[ 'p' . $r->ID ] = array( 'kind' => 'post', 'id' => (int) $r->ID, 'title' => $r->post_title, 'type' => $r->post_type, 'edit' => get_edit_post_link( $r->ID, 'raw' ), 'new' => $new, 'changes' => $ch, 'left' => $left + ( $in_title ? 1 : 0 ), 'title_hit' => $in_title );
	}
	$terms = get_terms( array( 'hide_empty' => false, 'taxonomy' => get_taxonomies( array( 'public' => true ) ) ) );
	foreach ( is_array( $terms ) ? $terms : array() as $t ) {
		if ( ! zad_cc_has( $t->description ) && ! zad_cc_has( $t->name ) ) { continue; }
		list( $new, $ch, $left ) = zad_cc_fix( $t->description );
		$out[ 't' . $t->term_id ] = array( 'kind' => 'term', 'id' => (int) $t->term_id, 'tax' => $t->taxonomy, 'title' => $t->name, 'type' => $t->taxonomy, 'edit' => get_edit_term_link( $t->term_id, $t->taxonomy ), 'new' => $new, 'changes' => $ch, 'left' => $left + ( zad_cc_has( $t->name ) ? 1 : 0 ), 'title_hit' => zad_cc_has( $t->name ) );
	}
	return $out;
}

function zad_cc_apply( $keys ) {
	global $wpdb;
	$scan = zad_cc_scan(); $log = get_option( ZAD_CC_LOG ); $log = is_array( $log ) ? $log : array( 'post' => array(), 'term' => array() ); $n = 0;
	foreach ( (array) $keys as $k ) {
		if ( empty( $scan[ $k ] ) || ! $scan[ $k ]['changes'] ) { continue; }
		$it = $scan[ $k ];
		if ( 'post' === $it['kind'] ) {
			$cur = get_post_field( 'post_content', $it['id'], 'raw' );
			if ( ! isset( $log['post'][ $it['id'] ] ) ) { $log['post'][ $it['id'] ] = $cur; }
			$wpdb->update( $wpdb->posts, array( 'post_content' => $it['new'] ), array( 'ID' => $it['id'] ) ); // phpcs:ignore — keeps dates
			clean_post_cache( $it['id'] );
		} else {
			$cur = term_description( $it['id'], $it['tax'] ); $cur = get_term_field( 'description', $it['id'], $it['tax'], 'raw' );
			if ( ! isset( $log['term'][ $it['id'] ] ) ) { $log['term'][ $it['id'] ] = array( $it['tax'], $cur ); }
			$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $it['new'] ), array( 'term_id' => $it['id'], 'taxonomy' => $it['tax'] ) ); // phpcs:ignore
			clean_term_cache( $it['id'], $it['tax'] );
		}
		$n++;
	}
	update_option( ZAD_CC_LOG, $log, false );
	return $n;
}

function zad_cc_undo() {
	global $wpdb;
	$log = get_option( ZAD_CC_LOG ); $n = 0;
	if ( ! is_array( $log ) ) { return 0; }
	foreach ( (array) $log['post'] as $id => $orig ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $orig ), array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); $n++; } // phpcs:ignore
	foreach ( (array) $log['term'] as $id => $tv ) { $wpdb->update( $wpdb->term_taxonomy, array( 'description' => $tv[1] ), array( 'term_id' => (int) $id, 'taxonomy' => $tv[0] ) ); clean_term_cache( (int) $id, $tv[0] ); $n++; } // phpcs:ignore
	delete_option( ZAD_CC_LOG );
	return $n;
}

add_action( 'admin_menu', function () {
	add_management_page( 'إزالة ذكر مدن', 'إزالة ذكر مدن (زاد)', 'manage_options', 'zad-cityclean', 'zad_cc_page' );
} );

function zad_cc_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	echo '<div class="wrap" dir="rtl"><h1>إزالة ذكر مدن من النصوص</h1>';
	if ( isset( $_POST['zad_cc'] ) && check_admin_referer( 'zad_cc' ) ) {
		if ( 'run' === $_POST['zad_cc'] ) {
			$keys = isset( $_POST['zad_cc_k'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['zad_cc_k'] ) ) : array();
			echo '<div class="notice notice-success"><p>اتعدّل نص ' . (int) zad_cc_apply( $keys ) . ' صفحة/تصنيف. النص الأصلي محفوظ وزر «تراجع» بيرجّعه.</p></div>';
		} elseif ( 'undo' === $_POST['zad_cc'] ) {
			echo '<div class="notice notice-success"><p>رجع النص الأصلي لـ ' . (int) zad_cc_undo() . ' صفحة/تصنيف.</p></div>';
		}
	}
	$scan = zad_cc_scan();
	$auto = array_filter( $scan, function ( $x ) { return $x['changes']; } );
	$manual = array_filter( $scan, function ( $x ) { return $x['left'] > 0; } );
	echo '<p>المدن: <b>' . esc_html( implode( '، ', zad_cc_cities() ) ) . '</b> · ' . count( $scan ) . ' صفحة/تصنيف فيها ذكر · <b>' . count( $auto ) . '</b> فيها تعديل تلقائي آمن · <b>' . count( $manual ) . '</b> فيها ذكر محتاج تعديل يدوي.</p>';
	echo '<p>التعديل التلقائي بيشيل المدينة من قوائم المدن بس (زي «بريدة وعنيزة والرس والبكيرية» ← «بريدة وعنيزة») أو يحذف بند قائمة عن المدينة. أي ذكر تاني (عنوان، جملة كاملة عن المدينة) بيظهر «يدوي» برابط التعديل.</p>';
	echo '<form method="post">'; wp_nonce_field( 'zad_cc' );
	echo '<table class="widefat striped"><thead><tr><th style="width:28px"></th><th>الصفحة</th><th>قبل ← بعد</th><th>يدوي</th></tr></thead><tbody>';
	foreach ( $scan as $k => $x ) {
		echo '<tr><td>' . ( $x['changes'] ? '<input type="checkbox" name="zad_cc_k[]" value="' . esc_attr( $k ) . '" checked>' : '' ) . '</td>';
		echo '<td><a href="' . esc_url( $x['edit'] ) . '" target="_blank">' . esc_html( $x['title'] ) . '</a><br><small>' . esc_html( $x['type'] ) . ' #' . (int) $x['id'] . '</small></td><td>';
		foreach ( $x['changes'] as $c ) { echo '<div style="margin:0 0 8px"><span style="background:#fde8e8">' . esc_html( $c[0] ) . '</span><br>← <span style="background:#e6f6ea">' . esc_html( $c[1] ) . '</span></div>'; }
		if ( ! $x['changes'] ) { echo '—'; }
		echo '</td><td>' . ( $x['left'] ? '<b style="color:#b32d2e">' . (int) $x['left'] . '</b>' . ( $x['title_hit'] ? ' <small>(في العنوان)</small>' : '' ) . ' · <a href="' . esc_url( $x['edit'] ) . '" target="_blank">تعديل</a>' : '—' ) . '</td></tr>';
	}
	echo '</tbody></table><p style="display:flex;gap:10px;margin-top:14px">';
	if ( $auto ) { echo '<button class="button button-primary button-hero" name="zad_cc" value="run">نفّذ التعديلات المحددة</button>'; }
	if ( is_array( get_option( ZAD_CC_LOG ) ) ) { echo '<button class="button button-hero" name="zad_cc" value="undo">تراجع</button>'; }
	echo '</p></form></div>';
}
