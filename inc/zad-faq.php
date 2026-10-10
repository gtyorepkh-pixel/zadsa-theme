<?php defined( 'ABSPATH' ) || exit;
/** FAQ network: one page per question (zad_faq) linked to services. */

function zad_register_faq() {
	$fs       = zad_slug( 'zad_faq_slug', 'faq' );
	$existing = zad_existing_types( 'faq' );
	$types    = array_values( array_unique( array_merge( array_keys( $existing ), array( 'zad_faq' ) ) ) ); // zad_faq always exists (a mu-plugin may register it first: see mu-plugins/zad-core-faq.php)
	if ( ! post_type_exists( 'zad_faq' ) ) {
	register_post_type( 'zad_faq', array(
		'labels'       => array( 'name' => 'الأسئلة الشائعة', 'singular_name' => 'سؤال', 'add_new' => 'إضافة سؤال', 'add_new_item' => 'إضافة سؤال جديد', 'edit_item' => 'تعديل السؤال', 'all_items' => 'كل الأسئلة', 'menu_name' => 'الأسئلة' ),
		'public'       => true,
		'has_archive'  => $fs,
		'rewrite'      => array( 'slug' => $fs, 'with_front' => false ),
		'menu_icon'    => 'dashicons-editor-help',
		'menu_position'=> 7,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
	) );
	}
	register_taxonomy( 'faq_cat', $types, array(
		'labels'            => array( 'name' => 'أقسام الأسئلة', 'singular_name' => 'قسم', 'menu_name' => 'الأقسام' ),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => $fs . '-category', 'with_front' => false ),
	) );
}
add_action( 'init', 'zad_register_faq', 50 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_faq_meta', 'ربط السؤال بالخدمات', 'zad_faq_metabox', zad_faq_types(), 'side', 'default' );
} );
function zad_faq_metabox( $post ) {
	wp_nonce_field( 'zad_faq_save', 'zad_faq_nonce' );
	$sel = array_map( 'intval', (array) get_post_meta( $post->ID, '_zad_faq_services', true ) );
	echo '<p>الإجابة المباشرة تُكتب في صندوق «الإجابة المباشرة» أسفل المحرر، والشرح الكامل في المحتوى.</p><select name="zad_faq_services[]" multiple size="8" style="width:100%">';
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $s ) {
		echo '<option value="' . (int) $s->ID . '"' . ( in_array( $s->ID, $sel, true ) ? ' selected' : '' ) . '>' . esc_html( $s->post_title ) . '</option>';
	}
	echo '</select>';
	$more = (string) get_post_meta( $post->ID, '_zad_faq_more', true );
	echo '<hr><p><b>فقرة توضيح تحت الإجابة</b> (اختياري، نص عادي)</p><textarea name="zad_faq_more" rows="5" style="width:100%">' . esc_textarea( $more ) . '</textarea>';
	if ( 'zad_faq' === $post->post_type ) { return; } // the article link lives in the «الإجابة المباشرة» box below the editor
	$art = (int) get_post_meta( $post->ID, '_zad_faq_article', true );
	$lbl = (string) get_post_meta( $post->ID, '_zad_faq_article_lbl', true );
	echo '<p><b>مقال للتعمّق</b> (اختياري)</p><select name="zad_faq_article" style="width:100%"><option value="0">— بدون —</option>';
	foreach ( get_posts( array( 'post_type' => zad_article_types(), 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC' ) ) as $a ) {
		echo '<option value="' . (int) $a->ID . '"' . selected( $art, $a->ID, false ) . '>' . esc_html( $a->post_title ) . '</option>';
	}
	echo '</select><p><input type="text" name="zad_faq_article_lbl" value="' . esc_attr( $lbl ) . '" placeholder="عنوان الزر (افتراضي: اقرأ الدليل كاملاً)" style="width:100%"></p>';
}
add_action( 'save_post', function ( $id ) {
	if ( ! in_array( get_post_type( $id ), zad_faq_types(), true ) ) {
		return;
	}
	if ( ! isset( $_POST['zad_faq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_faq_nonce'] ) ), 'zad_faq_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$v = isset( $_POST['zad_faq_services'] ) ? array_map( 'strval', array_map( 'absint', (array) $_POST['zad_faq_services'] ) ) : array();
	update_post_meta( $id, '_zad_faq_services', $v );
	update_post_meta( $id, '_zad_faq_more', isset( $_POST['zad_faq_more'] ) ? sanitize_textarea_field( wp_unslash( $_POST['zad_faq_more'] ) ) : '' );
	if ( isset( $_POST['zad_faq_article'] ) ) { // the field lives in the «الإجابة المباشرة» box; never reset it when that box is not on the screen
		$a = absint( $_POST['zad_faq_article'] );
		update_post_meta( $id, '_zad_faq_article', ( $a && in_array( get_post_type( $a ), zad_article_types(), true ) ) ? $a : 0 );
		update_post_meta( $id, '_zad_faq_article_lbl', isset( $_POST['zad_faq_article_lbl'] ) ? sanitize_text_field( wp_unslash( $_POST['zad_faq_article_lbl'] ) ) : '' );
	}
} );

/* ------------------------------------------------------------------ */
/* «الإجابة المباشرة» box: the answer lives in post_excerpt itself      */
/* ------------------------------------------------------------------ */

/** The native «المقتطف» box / panel is replaced by the direct-answer box on zad_faq pages (post_excerpt stays the stored value). */
add_action( 'init', function () {
	if ( post_type_exists( 'zad_faq' ) ) { remove_post_type_support( 'zad_faq', 'excerpt' ); }
}, 60 );

add_action( 'add_meta_boxes_zad_faq', function () {
	add_meta_box( 'zad_faq_answer', 'الإجابة المباشرة', 'zad_faq_answer_box', 'zad_faq', 'normal', 'high' );
} );

function zad_faq_words( $t ) {
	$t = trim( (string) $t );
	return '' === $t ? 0 : count( preg_split( '/\s+/u', $t ) );
}

/** Published/draft posts of the given types as [id => title] (a currently selected one is always kept). */
function zad_faq_pick_list( $types, $selected = 0 ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => $types, 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $p ) {
		$out[ $p->ID ] = $p->post_title . ( 'publish' === $p->post_status ? '' : ' (' . ( 'draft' === $p->post_status ? 'مسودة' : 'غير منشور' ) . ')' );
	}
	if ( $selected && ! isset( $out[ $selected ] ) && get_post( $selected ) ) { $out[ $selected ] = get_the_title( $selected ); }
	return $out;
}

/** The service page a question card points to: its own link, else the first published service of «ربط السؤال بالخدمات». */
function zad_faq_service_for( $id ) {
	$sid = (int) get_post_meta( $id, '_zad_faq_service_link', true );
	if ( $sid && 'publish' === get_post_status( $sid ) && in_array( get_post_type( $sid ), zad_service_types(), true ) ) { return $sid; }
	return function_exists( 'zad_linked_service' ) ? (int) zad_linked_service( $id ) : 0;
}

/** The blog article card target (published, article type), else 0. */
function zad_faq_article_for( $id ) {
	$aid = (int) get_post_meta( $id, '_zad_faq_article', true );
	return ( $aid && 'publish' === get_post_status( $aid ) && in_array( get_post_type( $aid ), zad_article_types(), true ) ) ? $aid : 0;
}

function zad_faq_pick_html( $name, $list, $selected, $search_ph ) {
	$o = '<input type="search" class="zad-fa-find" data-for="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $search_ph ) . '" autocomplete="off" style="width:100%;margin-bottom:4px">';
	$o .= '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" size="6" style="width:100%;height:auto"><option value="0">— بدون —</option>';
	foreach ( $list as $pid => $title ) { $o .= '<option value="' . (int) $pid . '"' . selected( $selected, $pid, false ) . '>' . esc_html( $title ) . '</option>'; }
	return $o . '</select>';
}

function zad_faq_answer_box( $post ) {
	wp_nonce_field( 'zad_faq_answer_save', 'zad_faq_ans_nonce' );
	$ans = '' === trim( (string) $post->post_excerpt ) ? '' : (string) $post->post_excerpt;
	$n   = zad_faq_words( $ans );
	$sv  = (int) get_post_meta( $post->ID, '_zad_faq_service_link', true );
	$art = (int) get_post_meta( $post->ID, '_zad_faq_article', true );
	$eff = zad_faq_service_for( $post->ID );
	echo '<div class="zad-fa">';
	echo '<p><label for="zad-fa-text"><b>الإجابة المباشرة</b> — جملتان إلى ثلاث، نص عادي بلا HTML. تظهر في أول الصفحة وفي الـ Schema ووصف البحث وبطاقات الأسئلة.</label></p>';
	echo '<textarea name="zad_faq_answer" id="zad-fa-text" rows="4" style="width:100%;line-height:1.9">' . esc_textarea( $ans ) . '</textarea>';
	echo '<p class="zad-fa-meta"><span>عدد الكلمات: <b id="zad-fa-count">' . (int) $n . '</b> / 60</span> <span id="zad-fa-warn" style="color:#b32d2e;font-weight:600">' . ( 0 === $n ? 'الإجابة فارغة — ستظهر الصفحة بدون إجابة مباشرة.' : ( $n > 60 ? 'الإجابة أطول من 60 كلمة؛ الأفضل اختصارها (تحذير فقط، يمكنك الحفظ).' : '' ) ) . '</span></p>';
	echo '<hr><div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">';
	echo '<div><p><b>رابط الخدمة</b> (اختياري)</p>' . zad_faq_pick_html( 'zad_faq_service_link', zad_faq_pick_list( zad_service_types(), $sv ), $sv, 'ابحث في الخدمات…' );
	echo '<p><input type="text" name="zad_faq_service_lbl" value="' . esc_attr( (string) get_post_meta( $post->ID, '_zad_faq_service_lbl', true ) ) . '" placeholder="عنوان الزر (افتراضي: تعرّف على الخدمة)" style="width:100%"></p>';
	echo '<p class="description">إن تُرك فارغاً تُستخدم أول خدمة منشورة من «ربط السؤال بالخدمات»' . ( ! $sv && $eff ? ': <b>' . esc_html( get_the_title( $eff ) ) . '</b>' : '' ) . '.</p></div>';
	echo '<div><p><b>رابط من المدونة</b> (اختياري)</p>' . zad_faq_pick_html( 'zad_faq_article', zad_faq_pick_list( zad_article_types(), $art ), $art, 'ابحث في المقالات…' );
	echo '<p><input type="text" name="zad_faq_article_lbl" value="' . esc_attr( (string) get_post_meta( $post->ID, '_zad_faq_article_lbl', true ) ) . '" placeholder="عنوان الزر (افتراضي: اقرأ الدليل كاملاً)" style="width:100%"></p></div>';
	echo '</div></div>';
	?>
<script>
(function () {
	var t = document.getElementById('zad-fa-text'), c = document.getElementById('zad-fa-count'), w = document.getElementById('zad-fa-warn');
	function upd() {
		var s = t.value.replace(/^\s+|\s+$/g, ''), n = s ? s.split(/\s+/).length : 0;
		c.textContent = n;
		w.textContent = n === 0 ? 'الإجابة فارغة — ستظهر الصفحة بدون إجابة مباشرة.' : (n > 60 ? 'الإجابة أطول من 60 كلمة؛ الأفضل اختصارها (تحذير فقط، يمكنك الحفظ).' : '');
	}
	if (t && c && w) { t.addEventListener('input', upd); upd(); }
	[].forEach.call(document.querySelectorAll('.zad-fa-find'), function (inp) {
		var sel = document.getElementById(inp.getAttribute('data-for')); if (!sel) { return; }
		var all = [].map.call(sel.options, function (o) { return { v: o.value, t: o.text }; });
		inp.addEventListener('input', function () {
			var q = inp.value.trim().toLowerCase(), cur = sel.value;
			sel.innerHTML = '';
			all.forEach(function (o) {
				if (o.v === '0' || o.v === cur || !q || o.t.toLowerCase().indexOf(q) !== -1) { var op = new Option(o.t, o.v); if (o.v === cur) { op.selected = true; } sel.add(op); }
			});
		});
		inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
	});
})();
</script>
	<?php
}

/** Saves the box: the direct answer goes into post_excerpt (plain text); the service link and its label are post meta. */
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( 'zad_faq' !== ( $data['post_type'] ?? '' ) || ! isset( $_POST['zad_faq_answer'], $_POST['zad_faq_ans_nonce'] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $data;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_faq_ans_nonce'] ) ), 'zad_faq_answer_save' ) ) { return $data; }
	$pid = (int) ( $postarr['ID'] ?? 0 );
	if ( $pid ? ! current_user_can( 'edit_post', $pid ) : ! current_user_can( get_post_type_object( 'zad_faq' )->cap->edit_posts ) ) { return $data; }
	$data['post_excerpt'] = wp_slash( trim( preg_replace( '/[ \t]*\R[ \t]*/u', ' ', sanitize_textarea_field( wp_unslash( $_POST['zad_faq_answer'] ) ) ) ) );
	return $data;
}, 20, 2 );

add_action( 'save_post_zad_faq', function ( $id ) {
	if ( ! isset( $_POST['zad_faq_ans_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_faq_ans_nonce'] ) ), 'zad_faq_answer_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$sv = isset( $_POST['zad_faq_service_link'] ) ? absint( $_POST['zad_faq_service_link'] ) : 0;
	update_post_meta( $id, '_zad_faq_service_link', ( $sv && in_array( get_post_type( $sv ), zad_service_types(), true ) ) ? $sv : 0 );
	update_post_meta( $id, '_zad_faq_service_lbl', isset( $_POST['zad_faq_service_lbl'] ) ? sanitize_text_field( wp_unslash( $_POST['zad_faq_service_lbl'] ) ) : '' );
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->zad_is_faq_archive() || $q->is_tax( 'faq_cat' ) ) {
		$q->set( 'posts_per_page', 24 );
		$q->set( 'orderby', 'title' );
		$q->set( 'order', 'ASC' );
		if ( ! empty( $_GET['q'] ) ) { // phpcs:ignore
			$q->set( 's', sanitize_text_field( wp_unslash( $_GET['q'] ) ) ); // phpcs:ignore
		}
	}
} );

/** FAQs linked to a service. */
function zad_service_faqs( $service_id, $limit = 6 ) {
	return new WP_Query( array(
		'post_type'      => zad_faq_types(),
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_zad_faq_services', 'value' => '"' . (int) $service_id . '"', 'compare' => 'LIKE' ) ),
	) );
}

/** Strip a leading "الإجابة" label ("الإجابة:", "الإجابة المختصرة -", …) so it never starts a description, snippet or schema answer. */
function zad_clean_answer( $t ) {
	$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ) ) ) );
	return trim( preg_replace( '/^\s*(?:ال)?[إا]جابة(?:\s+المختصرة)?\s*[:：\-–—]*\s*/u', '', $t ) );
}

/** Plain-text answer of a single question page: the short answer (excerpt) when present, otherwise the start of the content. */
function zad_faq_answer_text( $id ) {
	$ex = has_excerpt( $id ) ? get_the_excerpt( $id ) : '';
	$t  = zad_clean_answer( $ex );
	if ( '' === $t ) { $t = zad_clean_answer( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ); }
	return $t;
}

/** Schema for a single question page: FAQPage with one Question (answer text only; no label, no extra fields). */
add_action( 'wp_head', function () {
	if ( ! zad_is_faq() || 'theme' !== zad_schema_owner() ) {
		return;
	}
	$id  = get_queried_object_id();
	$ans = zad_faq_answer_text( $id );
	if ( '' === $ans ) { return; }
	$full = zad_clean_answer( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) );
	if ( mb_strlen( $full ) > mb_strlen( $ans ) && 0 !== mb_strpos( $full, mb_substr( $ans, 0, 30 ) ) ) { $ans .= ' ' . $full; }
	zad_print_schema( array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'@id'        => get_permalink( $id ) . '#faq',
		'mainEntity' => array(
			array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( get_the_title( $id ) ),
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_trim_words( $ans, 160, '' ) ),
			),
		),
	) );
}, 21 );

/* ------------------------------------------------------------------ */
/* Linking FAQs to services: automatic (keyword match), default, bulk   */
/* ------------------------------------------------------------------ */

function zad_ar_norm( $t ) {
	$t = wp_strip_all_tags( (string) $t );
	$t = preg_replace( '/[\x{064B}-\x{065F}\x{0640}]/u', '', $t );
	$t = strtr( $t, array( 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي' ) );
	return mb_strtolower( $t );
}

function zad_tokens( $text ) {
	static $stop = null;
	if ( null === $stop ) {
		$stop = array_flip( array( 'هل', 'كيف', 'ما', 'ماذا', 'متي', 'في', 'من', 'علي', 'عن', 'الي', 'هو', 'هي', 'لماذا', 'كم', 'ان', 'او', 'مع', 'بعد', 'قبل', 'هذا', 'هذه', 'ذلك', 'لا', 'نعم', 'يمكن', 'ايضا', 'كل', 'اي', 'لدي', 'عند', 'الفرق', 'بين', 'شركه', 'بالرياض', 'الرياض', 'السعوديه', 'خدمه', 'افضل', 'رياض', 'شرك', 'خدم', 'سعودي', 'اهم', 'اكثر', 'وقت', 'مدينه', 'منزل' ) );
	}
	$out = array();
	foreach ( preg_split( '/[^\p{L}\p{N}]+/u', zad_ar_norm( $text ) ) as $w ) {
		if ( mb_strlen( $w ) < 2 ) {
			continue;
		}
		$w = preg_replace( '/^(وال|بال|كال|فال|لل|ال)/u', '', $w );
		$w = preg_replace( '/(ات|ين|ون|ها|هم|ه|ي)$/u', '', $w );
		if ( mb_strlen( $w ) < 2 || isset( $stop[ $w ] ) ) {
			continue;
		}
		$out[ $w ] = true;
	}
	return array_keys( $out );
}

/** Best matching service for a FAQ (id, or 0). Score: title tokens x3, tagline/features/base x1. */
function zad_faq_match_service( $faq_id ) {
	static $cache = null;
	if ( null === $cache ) {
		$cache = array();
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1 ) ) as $sv ) {
			$cache[ $sv->ID ] = array(
				'title' => array_flip( zad_tokens( $sv->post_title . ' ' . get_post_meta( $sv->ID, '_zad_base_name', true ) ) ),
				'body'  => array_flip( zad_tokens( get_post_meta( $sv->ID, '_zad_tagline', true ) . ' ' . get_post_meta( $sv->ID, '_zad_features', true ) . ' ' . $sv->post_excerpt ) ),
			);
		}
	}
	$f      = get_post( $faq_id );
	$tokens = zad_tokens( $f->post_title . ' ' . $f->post_title . ' ' . $f->post_excerpt . ' ' . wp_trim_words( $f->post_content, 60, '' ) );
	$best   = 0;
	$score  = 0;
	foreach ( $cache as $id => $sv ) {
		$sc = 0;
		foreach ( $tokens as $t ) {
			if ( isset( $sv['title'][ $t ] ) ) { $sc += 3; }
			elseif ( isset( $sv['body'][ $t ] ) ) { $sc += 1; }
		}
		if ( $sc > $score ) { $score = $sc; $best = $id; }
	}
	return $score >= 3 ? $best : 0;
}

function zad_faq_linked( $faq_id ) {
	return array_filter( (array) get_post_meta( $faq_id, '_zad_faq_services', true ) );
}

/** Link one FAQ if it has no link: auto-match, then default service. Returns the service id used or 0. */
function zad_faq_autolink( $faq_id, $force = false ) {
	if ( ! $force && zad_faq_linked( $faq_id ) ) {
		return 0;
	}
	$sid = zad_opt( 'zad_faq_autolink', true ) ? zad_faq_match_service( $faq_id ) : 0;
	if ( ! $sid ) {
		$sid = (int) zad_opt( 'zad_faq_default_service' );
	}
	if ( $sid ) {
		update_post_meta( $faq_id, '_zad_faq_services', array( (string) $sid ) );
	}
	return $sid;
}

add_action( 'save_post', function ( $id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! empty( $GLOBALS['zad_fas_running'] ) || ! in_array( get_post_type( $id ), zad_faq_types(), true ) || 'publish' !== get_post_status( $id ) ) { // zad_fas_running: the «فصل الإجابة المباشرة» tool must not link questions as a side effect
		return;
	}
	zad_faq_autolink( $id );
}, 40 );

add_action( 'admin_menu', function () {
	add_management_page( 'ربط الأسئلة بالخدمات', 'ربط الأسئلة بالخدمات', 'manage_options', 'zad-faqlink', 'zad_faqlink_page' );
} );

function zad_faqlink_page() {
	$faqs     = get_posts( array( 'post_type' => zad_faq_types(), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$services = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<div class="wrap"><h1>ربط الأسئلة بالخدمات</h1>';

	if ( ! empty( $_POST['zad_fl_action'] ) && check_admin_referer( 'zad_fl' ) && current_user_can( 'manage_options' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['zad_fl_action'] ) );
		$n   = 0;
		if ( 'auto' === $act ) {
			foreach ( $faqs as $f ) { if ( zad_faq_autolink( $f->ID ) ) { $n++; } }
		} elseif ( 'all' === $act && ! empty( $_POST['zad_fl_service'] ) ) {
			foreach ( $faqs as $f ) { if ( ! zad_faq_linked( $f->ID ) ) { update_post_meta( $f->ID, '_zad_faq_services', array( (string) absint( $_POST['zad_fl_service'] ) ) ); $n++; } }
		} elseif ( 'selected' === $act && ! empty( $_POST['zad_fl_service'] ) && ! empty( $_POST['zad_fl_faqs'] ) ) {
			foreach ( array_map( 'absint', (array) $_POST['zad_fl_faqs'] ) as $fid ) { update_post_meta( $fid, '_zad_faq_services', array( (string) absint( $_POST['zad_fl_service'] ) ) ); $n++; }
		} elseif ( 'clear' === $act ) {
			foreach ( $faqs as $f ) { delete_post_meta( $f->ID, '_zad_faq_services' ); $n++; }
		}
		echo '<div class="notice notice-success"><p>تم تحديث ' . (int) $n . ' سؤالاً.</p></div>';
	}

	$unlinked = 0;
	foreach ( $faqs as $f ) { if ( ! zad_faq_linked( $f->ID ) ) { $unlinked++; } }
	echo '<p>عدد الأسئلة: <strong>' . count( $faqs ) . '</strong> — غير مربوطة بخدمة: <strong>' . (int) $unlinked . '</strong>. الربط يجعل السؤال يعرض «الخدمة المرتبطة»، ويظهر في «أسئلة تفصيلية ذات صلة» داخل صفحة الخدمة.</p>';

	echo '<form method="post">';
	wp_nonce_field( 'zad_fl' );
	echo '<h2>1) ربط تلقائي بالكلمات المتشابهة</h2><p>يقارن عنوان السؤال ونصه بعناوين الخدمات ومميزاتها، ويربط كل سؤال غير مربوط بأقرب خدمة. الأسئلة غير المطابقة تذهب للخدمة الافتراضية إن اخترتها من الإعدادات.</p>';
	echo '<p><button class="button button-primary" name="zad_fl_action" value="auto">تطبيق الربط التلقائي على غير المربوطة</button></p>';

	echo '<h2>2) ربط بخدمة محددة</h2><p><select name="zad_fl_service"><option value="">— اختر الخدمة —</option>';
	foreach ( $services as $s ) { echo '<option value="' . (int) $s->ID . '">' . esc_html( $s->post_title ) . '</option>'; }
	echo '</select> <button class="button" name="zad_fl_action" value="all">اربط كل الأسئلة غير المربوطة بهذه الخدمة</button> <button class="button" name="zad_fl_action" value="selected">اربط الأسئلة المحددة أدناه بها</button></p>';

	echo '<h2>الأسئلة</h2><table class="widefat striped" style="max-width:980px"><thead><tr><th style="width:30px"></th><th>السؤال</th><th>مربوط حالياً بـ</th><th>الاقتراح التلقائي</th></tr></thead><tbody>';
	foreach ( $faqs as $f ) {
		$cur = array_map( 'intval', zad_faq_linked( $f->ID ) );
		$cl  = $cur ? implode( '، ', array_map( 'get_the_title', $cur ) ) : '—';
		$sg  = ( ! $cur ) ? zad_faq_match_service( $f->ID ) : 0;
		echo '<tr><td><input type="checkbox" name="zad_fl_faqs[]" value="' . (int) $f->ID . '"></td><td><a href="' . esc_url( get_edit_post_link( $f->ID ) ) . '">' . esc_html( $f->post_title ) . '</a></td><td>' . esc_html( $cl ) . '</td><td>' . ( $sg ? esc_html( get_the_title( $sg ) ) : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p style="margin-top:16px"><button class="button" name="zad_fl_action" value="clear" onclick="return confirm(\'إزالة كل الروابط بين الأسئلة والخدمات؟\');">إزالة كل الروابط</button></p></form></div>';
}
