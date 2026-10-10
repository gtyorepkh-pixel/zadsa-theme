<?php
/** Logic test of the direct-answer box + «فصل الإجابة المباشرة» tool on the real-WordPress site. Run faq-fixtures.php first. Usage: php tools/wptest/faq-test.php */
$WPX = getenv( 'WPX' ) ?: '/tmp/wpsite';
require $WPX . '/wp-load.php';
wp_set_current_user( 1 );
$fail = 0; $n = 0;
function ok( $c, $m ) { global $fail, $n; $n++; if ( ! $c ) { $fail++; echo "FAIL: $m\n"; } }
function by( $slug ) { $p = get_page_by_path( $slug, OBJECT, 'zad_faq' ); return $p ? $p->ID : 0; }
$S = array( 'once' => 'is-spraying-pesticides-once-enough', 'classic' => 'spray-effect-duration', 'b_same' => 'is-spray-safe-kids', 'b_similar' => 'is-tank-cleaning-needed', 'c' => 'spray-price', 'manual_h' => 'ant-types', 'manual_list' => 'prep-steps', 'has_more' => 'pets-safe' );
$ID = array_map( 'by', $S );
ok( ! post_type_supports( 'zad_faq', 'excerpt' ), 'native excerpt support removed for zad_faq' );

/* ---- analysis ---- */
$an = array(); foreach ( $ID as $k => $id ) { $an[ $k ] = zad_fas_analyze( get_post( $id ) ); }
$exp = array( 'once' => 'A', 'classic' => 'A', 'b_same' => 'B', 'b_similar' => 'B', 'c' => 'C', 'manual_h' => 'M', 'manual_list' => 'M', 'has_more' => 'A' );
foreach ( $exp as $k => $c ) { ok( $an[ $k ]['case'] === $c, "case $k expected $c got {$an[$k]['case']} ({$an[$k]['note']})" ); }
ok( 0 === strpos( $an['once']['answer'], 'لا، رشة واحدة' ), 'A: label «الإجابة:» stripped from the answer' );
ok( 0 === strpos( $an['classic']['answer'], 'يستمر المفعول' ), 'A classic: label «الإجابة المختصرة:» stripped' );
ok( false !== strpos( $an['once']['remain'], 'تعتمد الإجابة' ), 'A: remaining first line is the next paragraph' );
ok( false === strpos( $an['once']['new_content'], 'لا، رشة واحدة' ) && false !== strpos( $an['once']['new_content'], '<!-- wp:heading -->' ), 'A: paragraph removed, other blocks kept' );
ok( 0 === strpos( $an['classic']['new_content'], '<p>وتختلف' ), 'classic: first <p> removed' );
ok( 0 !== strpos( ltrim( $an['b_same']['new_content'] ), '<!-- wp:paragraph -->' . "\n<p>نعم" ), 'B: duplicate removed' );

/* ---- apply on 3 (once, b_same, b_similar), auto-link must not fire ---- */
$before = array(); foreach ( array( 'once', 'b_same', 'b_similar' ) as $k ) { $before[ $k ] = array( get_post_field( 'post_content', $ID[ $k ] ), get_post_field( 'post_excerpt', $ID[ $k ] ), count( wp_get_post_revisions( $ID[ $k ] ) ) ); }
$svc_before = get_post_meta( $ID['manual_list'], '_zad_faq_services', true );
$res = zad_fas_apply( array( $ID['once'], $ID['b_same'], $ID['b_similar'], $ID['c'], $ID['manual_h'] ) );
ok( $res[ $ID['once'] ]['ok'] && $res[ $ID['b_same'] ]['ok'] && $res[ $ID['b_similar'] ]['ok'], 'A + two B applied' );
ok( ! $res[ $ID['c'] ]['ok'] && ! $res[ $ID['manual_h'] ]['ok'], 'C and manual refused even if posted' );
ok( get_post_field( 'post_excerpt', $ID['once'] ) === $an['once']['answer'], 'A: excerpt = answer' );
ok( get_post_field( 'post_content', $ID['once'] ) === $an['once']['new_content'], 'A: content = rest' );
ok( get_post_field( 'post_excerpt', $ID['b_same'] ) === $before['b_same'][1], 'B: excerpt untouched' );
foreach ( array( 'once', 'b_same', 'b_similar' ) as $k ) {
	ok( wp_unslash( get_post_meta( $ID[ $k ], '_zad_faq_content_backup', true ) ) === $before[ $k ][0], "backup content exact ($k)" );
	ok( get_post_meta( $ID[ $k ], '_zad_faq_excerpt_backup', true ) === $before[ $k ][1], "backup excerpt exact ($k)" );
	ok( count( wp_get_post_revisions( $ID[ $k ] ) ) > $before[ $k ][2], "revision recorded ($k)" );
}
ok( get_post_meta( $ID['manual_list'], '_zad_faq_services', true ) === $svc_before, 'no auto-link side effect on unlinked question' );
ok( zad_fas_analyze( get_post( $ID['once'] ) )['case'] === 'C', 're-analysis after run: nothing left to do (C)' );
ok( ! zad_fas_apply( array( $ID['once'] ) )[ $ID['once'] ]['ok'], 'second run on the same row refused' );

/* ---- undo ---- */
$u = zad_fas_undo();
ok( 3 === count( array_filter( array_column( $u, 0 ) ) ), 'undo restored 3' );
foreach ( array( 'once', 'b_same', 'b_similar' ) as $k ) {
	ok( get_post_field( 'post_content', $ID[ $k ] ) === $before[ $k ][0] && get_post_field( 'post_excerpt', $ID[ $k ] ) === $before[ $k ][1], "undo exact ($k)" );
	ok( ! metadata_exists( 'post', $ID[ $k ], '_zad_faq_content_backup' ), "backup meta cleared ($k)" );
}
/* undo refuses when edited after the run */
zad_fas_apply( array( $ID['b_same'] ) );
wp_update_post( array( 'ID' => $ID['b_same'], 'post_content' => get_post_field( 'post_content', $ID['b_same'] ) . '<p>تعديل لاحق</p>' ) );
$u = zad_fas_undo();
ok( isset( $u[ $ID['b_same'] ] ) && ! $u[ $ID['b_same'] ][0], 'undo skips a post edited after the split' );
// reset that one
wp_update_post( wp_slash( array( 'ID' => $ID['b_same'], 'post_content' => get_post_meta( $ID['b_same'], '_zad_faq_content_backup', true ) ) ) );
foreach ( array( '_zad_faq_content_backup', '_zad_faq_excerpt_backup', '_zad_faq_split_hash', '_zad_faq_split_case' ) as $k ) { delete_post_meta( $ID['b_same'], $k ); }

/* backslashes / quotes in block JSON survive the backup + restore */
$tricky = "<!-- wp:paragraph {\"className\":\"a\\u0022b\"} -->\n<p>جواب مختصر قصير لاختبار الشرطة \\ المائلة.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:html -->\n<div data-x=\"a\\\\b\">x</div>\n<!-- /wp:html -->";
$tid = wp_insert_post( wp_slash( array( 'post_type' => 'zad_faq', 'post_status' => 'publish', 'post_title' => 'اختبار الشرطة', 'post_name' => 'bslash', 'post_content' => $tricky ) ) );
$a = zad_fas_analyze( get_post( $tid ) );
ok( 'A' === $a['case'], 'tricky: case A (' . $a['note'] . ')' );
zad_fas_apply( array( $tid ) );
ok( false !== strpos( get_post_field( 'post_content', $tid ), 'a\\\\b' ), 'tricky: backslashes kept in the remaining content' );
zad_fas_undo();
ok( get_post_field( 'post_content', $tid ) === $tricky, 'tricky: undo restores byte-exact content' );
wp_delete_post( $tid, true );

/* ---- the box: save path ---- */
$svc = (int) get_page_by_path( 'tanks-riyadh', OBJECT, 'zad_service' )->ID; $art = (int) get_page_by_path( 'pre-spray-guide', OBJECT, 'post' )->ID;
$_POST = array( 'zad_faq_ans_nonce' => wp_create_nonce( 'zad_faq_answer_save' ), 'zad_faq_nonce' => wp_create_nonce( 'zad_faq_save' ), 'zad_faq_answer' => "إجابة <b>مباشرة</b>\nبسطرين <script>x</script>", 'zad_faq_service_link' => $svc, 'zad_faq_service_lbl' => 'اطلب الخدمة', 'zad_faq_article' => $art, 'zad_faq_article_lbl' => 'اقرأ المزيد', 'zad_faq_more' => 'توضيح' );
wp_update_post( wp_slash( array( 'ID' => $ID['c'], 'post_title' => get_the_title( $ID['c'] ) ) ) );
$x = get_post( $ID['c'] );
ok( 'إجابة مباشرة بسطرين' === $x->post_excerpt, 'box save: plain text, tags/script stripped, newline collapsed: [' . $x->post_excerpt . ']' );
ok( (int) get_post_meta( $ID['c'], '_zad_faq_service_link', true ) === $svc && 'اطلب الخدمة' === get_post_meta( $ID['c'], '_zad_faq_service_lbl', true ), 'box save: service link + label' );
ok( (int) get_post_meta( $ID['c'], '_zad_faq_article', true ) === $art && 'اقرأ المزيد' === get_post_meta( $ID['c'], '_zad_faq_article_lbl', true ), 'box save: article link + label (existing meta keys)' );
$_POST['zad_faq_service_link'] = $art; $_POST['zad_faq_article'] = $svc; // wrong post types are refused
wp_update_post( wp_slash( array( 'ID' => $ID['c'], 'post_title' => get_the_title( $ID['c'] ) ) ) );
ok( 0 === (int) get_post_meta( $ID['c'], '_zad_faq_service_link', true ) && 0 === (int) get_post_meta( $ID['c'], '_zad_faq_article', true ), 'box save: ids of the wrong type are dropped' );
$_POST['zad_faq_ans_nonce'] = 'bad'; $_POST['zad_faq_answer'] = 'لن تُحفظ';
wp_update_post( wp_slash( array( 'ID' => $ID['c'], 'post_title' => get_the_title( $ID['c'] ) ) ) );
ok( 'لن تُحفظ' !== get_post_field( 'post_excerpt', $ID['c'] ), 'box save: bad nonce changes nothing' );
$_POST = array(); wp_update_post( wp_slash( array( 'ID' => $ID['c'], 'post_title' => get_the_title( $ID['c'] ) ) ) );
ok( 'إجابة مباشرة بسطرين' === get_post_field( 'post_excerpt', $ID['c'] ), 'saves without the box (quick edit/REST) keep the excerpt' );
ob_start(); zad_faq_answer_box( get_post( $ID['c'] ) ); $h = ob_get_clean();
ok( false !== strpos( $h, 'name="zad_faq_answer"' ) && false !== strpos( $h, 'name="zad_faq_service_link"' ) && false !== strpos( $h, 'name="zad_faq_article"' ) && false === strpos( $h, 'zad_faq_more' ), 'box markup: answer + service + article, no «more» field' );
ob_start(); zad_faq_metabox( get_post( $ID['c'] ) ); $sb = ob_get_clean();
ok( false !== strpos( $sb, 'zad_faq_more' ) && false === strpos( $sb, 'name="zad_faq_article"' ) && false !== strpos( $sb, 'zad_faq_services[]' ), 'side box: services + «more» stay, article moved out' );
echo "$n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
