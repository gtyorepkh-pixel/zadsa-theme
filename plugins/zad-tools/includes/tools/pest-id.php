<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool 6 (part 2) — معرّف الحشرات بالأسئلة. Five questions (place, size, colour, wings, clearest sign); each answer matches the attributes the owner ticked on
 * the encyclopedia pages. Score of a pest = matched weight ÷ total weight of the answered questions (an unknown attribute counts as not matched).
 * Below the «strong» threshold (a flagged setting) the tool never claims an identification: it asks for a photo on WhatsApp.
 * Image identification is NOT part of this phase (a later, locked skeleton). Logic twin of assets/js/pest-id.js (tests/tools4-parity.test.mjs).
 */

zt_register_tool( 'pest-id', array(
	'title' => 'معرّف الحشرات', 'desc' => 'أجب عن بضعة أسئلة عن الحشرة التي رأيتها، ونعرض أقرب ثلاث حشرات من الموسوعة مع الخدمة المناسبة.',
	'settings_tab' => 'pests', 'render' => 'zt_pid_render', 'result' => 'zt_pid_result', 'how' => 'zt_pid_how', 'ready_cb' => 'zt_pid_ready',
	'js' => ZT_URL . 'assets/js/pest-id.js',
) );

function zt_pid_questions() {
	$opt = function ( $vocab ) { $o = array(); foreach ( $vocab as $k => $v ) { $o[] = array( 'k' => (string) $k, 'l' => $v[0], 'i' => $v[1] ); } return $o; };
	return array(
		array( 'k' => 'place', 'p' => 'pl', 'l' => 'أين رأيتها؟', 's' => 'المكان', 'o' => $opt( zt_pest_vocab( 'places' ) ) ),
		array( 'k' => 'size', 'p' => 'sz', 'l' => 'كم حجمها تقريباً؟', 's' => 'الحجم', 'o' => $opt( zt_pest_vocab( 'sizes' ) ) ),
		array( 'k' => 'color', 'p' => 'co', 'l' => 'ما لونها؟', 's' => 'اللون', 'o' => $opt( zt_pest_vocab( 'colors' ) ) ),
		array( 'k' => 'wings', 'p' => 'wg', 'l' => 'هل لها أجنحة؟', 's' => 'الأجنحة', 'o' => $opt( zt_pest_wings() ) ),
		array( 'k' => 'sign', 'p' => 'sg', 'l' => 'ما أوضح علامة تركتها؟', 's' => 'العلامة', 'o' => $opt( zt_pest_vocab( 'signs' ) ) ),
	);
}

function zt_pid_cfg() {
	$w = array( 'place' => 1, 'size' => 1, 'color' => 1, 'wings' => 1, 'sign' => 1 );
	foreach ( zt_table( zt_opt( 'pests.weights' ) ) as $r ) { if ( count( $r ) >= 2 && isset( $w[ $r[0] ] ) ) { $n = zt_num( $r[1] ); if ( null !== $n && $n > 0 ) { $w[ $r[0] ] = zt_pow_intval( $n ); } } }
	$pests = apply_filters( 'zt_pest_dataset', null ); // lets a theme / a test supply the pests
	if ( ! is_array( $pests ) ) { $pests = get_transient( 'zt_pest_cfg' ); }
	if ( ! is_array( $pests ) ) {
		$pests = array();
		foreach ( get_posts( array( 'post_type' => 'zad_pest', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $p ) {
			$d = zt_pest_data( $p->ID ); $a = $d['a'];
			if ( ! ( $a['place'] || $a['size'] || $a['color'] || $a['sign'] || '' !== $a['wings'] ) ) { continue; } // no identification data yet
			$pests[] = array( 'id' => $d['id'], 'n' => $d['name'], 'u' => $d['url'], 'img' => $d['img'], 'alt' => $d['alt'], 'svc' => $d['service'], 'a' => $a );
		}
		set_transient( 'zt_pest_cfg', $pests, DAY_IN_SECONDS );
	}
	return array( 'q' => zt_pid_questions(), 'w' => $w, 'min' => (int) zt_opt( 'pests.min_match' ), 'max' => max( 1, (int) zt_opt( 'pests.max_results' ) ), 'pests' => $pests );
}
function zt_pid_ready() { return count( zt_pid_cfg()['pests'] ) >= max( 1, (int) zt_opt( 'pests.min_pests' ) ); }

/* ---------------------------------------------- the logic (PHP twin) ---------------------------------------------- */

function zt_pid_match( $p, $k, $ans ) {
	if ( 'wings' === $k ) { $w = $p['a']['wings'] ?? ''; return '' !== $w && ( 'some' === $w || $w === $ans ); }
	return in_array( $ans, (array) ( $p['a'][ $k ] ?? array() ), true );
}
function zt_pid_calc( $cfg, $i ) {
	$tw = 0; $answered = array(); $ans = (array) ( $i['ans'] ?? array() );
	foreach ( $cfg['q'] as $q ) {
		$a = $ans[ $q['k'] ] ?? '';
		if ( '' === $a || ! in_array( $a, array_column( $q['o'], 'k' ), true ) ) { continue; }
		$tw += $cfg['w'][ $q['k'] ]; $answered[] = $q;
	}
	if ( ! $answered ) { return array( 'error' => 'أجب عن سؤال واحد على الأقل (أو اختر «مش متأكد» لباقي الأسئلة).' ); }
	$res = array();
	foreach ( $cfg['pests'] as $p ) {
		$mw = 0; $n = 0;
		foreach ( $answered as $q ) { if ( zt_pid_match( $p, $q['k'], $ans[ $q['k'] ] ) ) { $mw += $cfg['w'][ $q['k'] ]; $n++; } }
		if ( $mw > 0 ) { $res[] = array( 'p' => $p, 'mw' => $mw, 'n' => $n, 'pct' => (int) floor( $mw / $tw * 100 + 0.5 ) ); }
	}
	usort( $res, function ( $a, $b ) { return ( $b['pct'] <=> $a['pct'] ) ?: ( $b['n'] <=> $a['n'] ) ?: ( $a['p']['id'] <=> $b['p']['id'] ); } );
	return array( 'answered' => $answered, 'total' => count( $cfg['q'] ), 'results' => array_slice( $res, 0, $cfg['max'] ), 'strong' => $res && $res[0]['pct'] >= $cfg['min'] );
}
function zt_pid_view( $cfg, $i ) {
	$r = zt_pid_calc( $cfg, $i );
	if ( isset( $r['error'] ) ) { return $r; }
	$ans = $i['ans']; $parts = array();
	foreach ( $r['answered'] as $q ) { foreach ( $q['o'] as $o ) { if ( $o['k'] === $ans[ $q['k'] ] ) { $parts[] = $q['s'] . ': ' . $o['l']; } } }
	$d = implode( '، ', $parts ); $top = $r['results'][0] ?? null;
	$big = $r['strong'] ? 'الأقرب: ' . $top['p']['n'] : 'لا يوجد تطابق قوي';
	$lines = array( 'أجبت عن ' . zt_fmt( count( $r['answered'] ) ) . ' من ' . zt_fmt( $r['total'] ) . ' أسئلة (' . $d . ')' );
	if ( ! $r['strong'] ) { $lines[] = $r['results'] ? 'أقرب ما وجدناه أدناه، لكن الأفضل أن ترسل صورة للحشرة ليحددها فنيونا.' : 'لم نجد حشرة تطابق إجاباتك في الموسوعة؛ أرسل صورة للحشرة ليحددها فنيونا.'; }
	$cards = array(); foreach ( $r['results'] as $x ) { $cards[] = array( 't' => $x['p']['n'], 'u' => $x['p']['u'], 'p' => $x['pct'], 'img' => $x['p']['img'], 'alt' => $x['p']['alt'], 'svc' => $x['p']['svc'] ); }
	$sum = $r['strong'] ? 'استخدمت معرّف الحشرات (' . $d . '): أقرب نتيجة ' . $top['p']['n'] . ' بنسبة تطابق ' . zt_fmt( $top['pct'] ) . '%. أحتاج فحصاً وعلاجاً.' : 'حاولت أعرف الحشرة بمعرّف الأسئلة (' . $d . ') ولم أصل لتطابق قوي، وسأرسل لكم صورة لها.';
	return array( 'badge' => 'تقريبي', 'big' => $big, 'lines' => $lines, 'cards' => $cards, 'notes' => array( 'التعريف تقريبي: يعتمد على إجاباتك وعلى بيانات الموسوعة، والفحص الميداني هو الحاسم. نسبة التطابق = عدد إجاباتك التي تنطبق على الحشرة (بأوزانها) ÷ كل إجاباتك.' ),
		'wa' => zt_wa_message( $sum, $i['hood'] ?? '' ), 'ga' => array( 'top' => $top ? $top['p']['id'] : 0, 'pct' => $top ? $top['pct'] : 0, 'strong' => $r['strong'] ? 1 : 0 ) );
}

/* ---------------------------------------------- request + rendering ---------------------------------------------- */

function zt_pid_request( $cfg ) {
	$ans = array();
	foreach ( $cfg['q'] as $q ) { $ans[ $q['k'] ] = zt_req( $q['p'] ); }
	return array( 'ans' => $ans, 'hood' => mb_substr( zt_req( 'hood' ), 0, 60 ) );
}

function zt_pid_render( $ctx ) {
	$cfg = zt_pid_cfg(); $q = zt_pid_request( $cfg );
	echo '<form class="zt-form" method="get" action="' . esc_url( $ctx['url'] . '#zt-result' ) . '" data-zt-form data-cfg="' . zt_esc( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '">';
	foreach ( $cfg['q'] as $x ) {
		echo '<fieldset><legend>' . zt_esc( $x['l'] ) . '</legend><div class="zt-opts"><label class="zt-opt"><input type="radio" name="' . zt_esc( $x['p'] ) . '" value=""' . ( '' === $q['ans'][ $x['k'] ] ? ' checked' : '' ) . '><span>مش متأكد</span></label>';
		foreach ( $x['o'] as $o ) { echo '<label class="zt-opt"><input type="radio" name="' . zt_esc( $x['p'] ) . '" value="' . zt_esc( $o['k'] ) . '"' . ( $o['k'] === $q['ans'][ $x['k'] ] ? ' checked' : '' ) . '><span class="zt-opt__i" aria-hidden="true">' . zt_esc( $o['i'] ) . '</span><span>' . zt_esc( $o['l'] ) . '</span></label>'; }
		echo '</div></fieldset>';
	}
	echo '<label class="fld"><span>الحي (اختياري)</span><input type="text" name="hood" maxlength="60" value="' . zt_esc( $q['hood'] ) . '" autocomplete="off"></label>';
	echo '<div class="zt-actions"><button class="btn btn--accent" type="submit">اعرف الحشرة</button></div></form>';
	if ( function_exists( 'zt_img_section_html' ) ) { echo zt_img_section_html(); } // locked: prints nothing unless unlocked // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
}

function zt_pid_result( $ctx ) {
	$cfg = zt_pid_cfg(); $any = false;
	foreach ( $cfg['q'] as $x ) { if ( '' !== zt_req( $x['p'] ) ) { $any = true; } }
	if ( ! $any ) { return ''; }
	return zt_result_html( zt_pid_view( $cfg, zt_pid_request( $cfg ) ), array( 'wa' => zt_wa_number(), 'privacy' => zt_privacy_url() ) );
}

function zt_pid_how( $ctx ) {
	$cfg = zt_pid_cfg();
	$h  = '<p>نسألك عن مكان الحشرة وحجمها ولونها وأجنحتها وأوضح علامة تركتها. كل إجابة تُقارَن بالخصائص المسجّلة لكل حشرة في موسوعة زاد، وكل سؤال له وزنه:</p><div class="zt-tblwrap"><table class="zt-table"><thead><tr><th scope="col">السؤال</th><th scope="col">الوزن</th></tr></thead><tbody>';
	foreach ( $cfg['q'] as $q ) { $h .= '<tr><th scope="row">' . zt_esc( $q['l'] ) . '</th><td>' . zt_esc( zt_fmt( $cfg['w'][ $q['k'] ] ) ) . '</td></tr>'; }
	$h .= '</tbody></table></div><p><strong>نسبة التطابق = (مجموع أوزان إجاباتك التي تنطبق على الحشرة) ÷ (مجموع أوزان الأسئلة التي أجبت عنها)</strong>. ما اخترت فيه «مش متأكد» لا يدخل في الحساب، وأي خاصية غير مسجّلة للحشرة تُعدّ غير مطابقة.</p>';
	return $h . '<p>نعتبر النتيجة قوية إذا بلغت نسبتها <strong>' . zt_esc( zt_fmt( $cfg['min'] ) ) . '%</strong> فأكثر؛ دون ذلك لا نجزم بتعريف، ونطلب منك إرسال صورة على واتساب. التعريف تقريبي والفحص الميداني هو الحاسم.</p>';
}
