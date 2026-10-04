<?php
/**
 * Tool: «تحويل الأسئلة القديمة» (Tools menu).
 * Converts the old FAQ post type (faq, categories best_faqs) into the theme's zad_faq / faq_cat:
 *  - only wp_posts.post_type (and an empty excerpt) changes: same ID, slug, dates, content and every meta (Yoast included);
 *  - categories are copied to faq_cat by name; the old taxonomy rows are left untouched (nothing is deleted);
 *  - empty excerpts are filled from the first paragraph (~50 words) and flagged for review;
 *  - every question goes through zad_faq_autolink();
 *  - preview first, one-click undo, URL check, and a switch that stops registering the old type/taxonomy;
 *  - 301 from the old best_faqs archive URLs to the matching faq_cat terms.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_faqconv_cfg( $k ) {
	$c = array( 'from' => 'faq', 'tax' => 'best_faqs', 'to' => 'zad_faq', 'newtax' => 'faq_cat' );
	return $c[ $k ] ?? '';
}
function zad_faqconv_retired() { return (bool) get_option( 'zad_faqconv_retire', false ); }

/* ---------------- retire the old type + taxonomy (a switch; no data is deleted) ---------------- */
add_action( 'init', function () {
	if ( ! zad_faqconv_retired() ) { return; }
	if ( post_type_exists( zad_faqconv_cfg( 'from' ) ) ) { unregister_post_type( zad_faqconv_cfg( 'from' ) ); }
	if ( taxonomy_exists( zad_faqconv_cfg( 'tax' ) ) ) { unregister_taxonomy( zad_faqconv_cfg( 'tax' ) ); }
}, 60 ); // before the rewrite-hash flush at 99, so the new rules are rebuilt automatically

/* ---------------- 301: old best_faqs archive URLs → faq_cat ---------------- */
add_action( 'template_redirect', function () {
	global $wp;
	if ( ! is_404() || ! isset( $wp->request ) ) { return; }
	$map = get_option( 'zad_faqconv_map' );
	if ( ! is_array( $map ) || ! $map ) { return; }
	$p = mb_strtolower( urldecode( trim( (string) $wp->request, '/' ) ) );
	$p = preg_replace( '#/(?:page/\d+|feed(?:/[a-z0-9]+)?)$#i', '', $p );
	if ( ! isset( $map[ $p ] ) ) { return; }
	$to = $map[ $p ];
	if ( isset( $to['t'] ) ) {
		$t = get_term( (int) $to['t'], zad_faqconv_cfg( 'newtax' ) );
		$u = ( $t && ! is_wp_error( $t ) ) ? get_term_link( $t ) : '';
	} else {
		$u = home_url( '/' . ltrim( (string) ( $to['u'] ?? '' ), '/' ) );
	}
	if ( $u && ! is_wp_error( $u ) ) { wp_safe_redirect( $u, 301 ); exit; }
}, 1 );

/* ---------------- pure helpers ---------------- */
/** ~50 words from the first non-empty paragraph, no HTML, no leading "الإجابة:" label. '' when there is nothing usable. */
function zad_faqconv_excerpt( $content, $words = 50 ) {
	$c = preg_replace( '/<!--.*?-->/s', '', (string) $content );
	$c = preg_replace( '/\[[^\]]+\]/u', '', $c );
	$paras = array();
	if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $c, $m ) ) { $paras = $m[1]; }
	if ( ! $paras ) { $paras = preg_split( '#(?:<br\s*/?>\s*){2,}|\R{2,}#i', $c ); }
	foreach ( $paras as $p ) {
		$t = trim( str_replace( "\xc2\xa0", ' ', zad_clean_answer( $p ) ) );
		if ( '' !== $t ) { return wp_trim_words( $t, $words, '…' ); }
	}
	return '';
}

/** New category slug from an old one: strip a trailing faq/faqs marker, then add "-faq". */
function zad_faqconv_term_slug( $old_slug ) {
	$s = strtolower( urldecode( (string) $old_slug ) );
	$s = trim( preg_replace( '/[-_]?faqs?$/', '', str_replace( '_', '-', $s ) ), '-' );
	return ( '' === $s ? 'general' : $s ) . '-faq';
}

/** Path (no domain, no slashes, decoded, lower-case) of an old term archive. */
function zad_faqconv_old_path( $term_id, $slug ) {
	$tax = zad_faqconv_cfg( 'tax' );
	if ( taxonomy_exists( $tax ) ) {
		$l = get_term_link( (int) $term_id, $tax );
		if ( ! is_wp_error( $l ) ) {
			$path = (string) wp_parse_url( $l, PHP_URL_PATH );
			$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			if ( '' !== $home && 0 === strpos( $path, $home ) ) { $path = substr( $path, strlen( $home ) ); }
			return mb_strtolower( urldecode( trim( $path, '/' ) ) );
		}
	}
	return mb_strtolower( str_replace( '_', '-', $tax ) . '/' . urldecode( $slug ) ); // guess: the rewrite the safety net uses
}

/* ---------------- data ---------------- */
function zad_faqconv_posts() {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_name, post_status, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft','trash','inherit') ORDER BY ID", zad_faqconv_cfg( 'from' ) ) ); // phpcs:ignore
}

/** Old terms (read straight from the database, so it works even when the taxonomy is no longer registered). */
function zad_faqconv_old_terms() {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.parent, tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY tt.parent, t.name", zad_faqconv_cfg( 'tax' ) ), OBJECT_K ); // phpcs:ignore
}

/** [ post_id => [ old_term_id, … ] ] */
function zad_faqconv_post_terms( $ids ) {
	global $wpdb;
	$out = array();
	if ( ! $ids ) { return $out; }
	$in   = implode( ',', array_map( 'intval', $ids ) );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = %s AND tr.object_id IN ($in)", zad_faqconv_cfg( 'tax' ) ) ); // phpcs:ignore
	foreach ( (array) $rows as $r ) { $out[ (int) $r->object_id ][] = (int) $r->term_id; }
	return $out;
}

/** The URL base of the old type and the new one must be identical (no question link may change). */
function zad_faqconv_bases() {
	$old = get_post_type_object( zad_faqconv_cfg( 'from' ) );
	$ob  = ( $old && is_array( $old->rewrite ) && ! empty( $old->rewrite['slug'] ) ) ? trim( $old->rewrite['slug'], '/' ) : str_replace( '_', '-', zad_faqconv_cfg( 'from' ) );
	return array( $ob, zad_type_base( zad_faqconv_cfg( 'to' ) ) );
}

/* ---------------- conversion ---------------- */
function zad_faqconv_run( $slugs, $names ) {
	global $wpdb;
	$from = zad_faqconv_cfg( 'from' ); $to = zad_faqconv_cfg( 'to' ); $ntax = zad_faqconv_cfg( 'newtax' );
	$posts = zad_faqconv_posts();
	$ids   = wp_list_pluck( $posts, 'ID' );
	$pterm = zad_faqconv_post_terms( $ids );
	$old   = zad_faqconv_old_terms();
	$log   = array( 'ts' => time(), 'items' => array(), 'created' => array(), 'urls' => array() );
	$tmap  = array(); // old term id => new term id
	$left  = $old;
	for ( $pass = 0; $pass < 4 && $left; $pass++ ) { // parents first
		foreach ( $left as $id => $t ) {
			if ( $t->parent && ! isset( $tmap[ (int) $t->parent ] ) && isset( $old[ (int) $t->parent ] ) ) { continue; }
			$slug = sanitize_title( $slugs[ $id ] ?? zad_faqconv_term_slug( $t->slug ) );
			$name = sanitize_text_field( $names[ $id ] ?? $t->name );
			$ex   = term_exists( $slug, $ntax );
			if ( ! $ex ) {
				$ex = wp_insert_term( $name, $ntax, array( 'slug' => $slug, 'parent' => $t->parent ? (int) ( $tmap[ (int) $t->parent ] ?? 0 ) : 0 ) );
				if ( ! is_wp_error( $ex ) ) { $log['created'][] = (int) $ex['term_id']; }
			}
			if ( ! is_wp_error( $ex ) ) { $tmap[ $id ] = (int) ( is_array( $ex ) ? $ex['term_id'] : $ex ); }
			unset( $left[ $id ] );
		}
	}
	foreach ( $posts as $p ) {
		$id = (int) $p->ID;
		$log['urls'][ $id ] = get_permalink( $id );
		$item = array( 'type' => $from, 'auto_ex' => false, 'svc' => get_post_meta( $id, '_zad_faq_services', true ), 'svc_set' => metadata_exists( 'post', $id, '_zad_faq_services' ), 'terms' => array() );
		$set  = array( 'post_type' => $to );
		if ( '' === trim( (string) $p->post_excerpt ) ) {
			$ex = zad_faqconv_excerpt( $p->post_content );
			if ( '' !== $ex ) { $set['post_excerpt'] = $ex; $item['auto_ex'] = true; }
		}
		$wpdb->update( $wpdb->posts, $set, array( 'ID' => $id ) ); // phpcs:ignore — keeps ID, slug, dates, modified time
		clean_post_cache( $id );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET object_sub_type = %s WHERE object_type = 'post' AND object_id = %d", $to, $id ) ); // phpcs:ignore — Yoast's own copy of the type (table exists only with Yoast)
		if ( $item['auto_ex'] ) { update_post_meta( $id, '_zad_faq_auto_excerpt', $set['post_excerpt'] ); }
		$new = array();
		foreach ( (array) ( $pterm[ $id ] ?? array() ) as $ot ) { if ( isset( $tmap[ $ot ] ) ) { $new[] = $tmap[ $ot ]; } }
		if ( $new ) { wp_set_object_terms( $id, $new, $ntax, true ); $item['terms'] = $new; }
		$log['items'][ $id ] = $item;
		if ( 'publish' === $p->post_status || 'private' === $p->post_status ) { zad_faq_autolink( $id ); }
	}
	// 301 map for the old archive URLs
	$map = array();
	foreach ( $old as $id => $t ) {
		if ( isset( $tmap[ $id ] ) ) { $map[ zad_faqconv_old_path( $id, $t->slug ) ] = array( 't' => $tmap[ $id ] ); }
	}
	if ( 'yoast' === zad_seo_mode() ) { $map[ mb_strtolower( zad_faqconv_cfg( 'tax' ) ) . '-sitemap.xml' ] = array( 'u' => $ntax . '-sitemap.xml' ); }
	update_option( 'zad_faqconv_map', $map, false );
	update_option( 'zad_faqconv_log', $log, false );
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); }
	if ( ! zad_faqconv_posts() ) { update_option( 'zad_faqconv_retire', 1, false ); } // nothing left on the old type: stop registering it (next request)
	update_option( 'zad_rw_hash', '', false ); // force the rewrite flush on the next request
	return $log;
}

function zad_faqconv_undo() {
	global $wpdb;
	$log = get_option( 'zad_faqconv_log' );
	if ( ! is_array( $log ) || empty( $log['items'] ) ) { return 0; }
	$ntax = zad_faqconv_cfg( 'newtax' ); $n = 0;
	foreach ( $log['items'] as $id => $it ) {
		$id = (int) $id;
		if ( zad_faqconv_cfg( 'to' ) !== get_post_type( $id ) ) { continue; }
		$set = array( 'post_type' => $it['type'] );
		if ( ! empty( $it['auto_ex'] ) && get_post_field( 'post_excerpt', $id ) === get_post_meta( $id, '_zad_faq_auto_excerpt', true ) ) { $set['post_excerpt'] = ''; }
		$wpdb->update( $wpdb->posts, $set, array( 'ID' => $id ) ); // phpcs:ignore
		clean_post_cache( $id );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET object_sub_type = %s WHERE object_type = 'post' AND object_id = %d", $it['type'], $id ) ); // phpcs:ignore
		delete_post_meta( $id, '_zad_faq_auto_excerpt' );
		if ( ! empty( $it['terms'] ) ) { wp_remove_object_terms( $id, array_map( 'intval', $it['terms'] ), $ntax ); }
		if ( ! empty( $it['svc_set'] ) ) { update_post_meta( $id, '_zad_faq_services', $it['svc'] ); } else { delete_post_meta( $id, '_zad_faq_services' ); }
		$n++;
	}
	foreach ( (array) $log['created'] as $tid ) {
		$t = get_term( (int) $tid, $ntax );
		if ( $t && ! is_wp_error( $t ) && 0 === (int) $t->count ) { wp_delete_term( (int) $tid, $ntax ); }
	}
	delete_option( 'zad_faqconv_log' ); delete_option( 'zad_faqconv_map' ); delete_option( 'zad_faqconv_retire' );
	update_option( 'zad_rw_hash', '', false );
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); }
	return $n;
}

/* ---------------- admin: review column for auto-filled excerpts ---------------- */
add_filter( 'manage_zad_faq_posts_columns', function ( $c ) { $c['zad_autoex'] = 'مقتطف تلقائي'; return $c; } );
add_action( 'manage_zad_faq_posts_custom_column', function ( $col, $id ) {
	if ( 'zad_autoex' === $col && get_post_meta( $id, '_zad_faq_auto_excerpt', true ) ) { echo '<span style="color:#b45309" title="مُلئ تلقائياً من أول فقرة — راجعه">⚠ يحتاج مراجعة</span>'; }
}, 10, 2 );
add_action( 'save_post_zad_faq', function ( $id ) { // reviewing = editing the excerpt: the flag clears itself
	$auto = get_post_meta( $id, '_zad_faq_auto_excerpt', true );
	if ( $auto && get_post_field( 'post_excerpt', $id ) !== $auto ) { delete_post_meta( $id, '_zad_faq_auto_excerpt' ); }
} );

/* ---------------- admin page ---------------- */
add_action( 'admin_menu', function () {
	add_management_page( 'تحويل الأسئلة القديمة', 'تحويل الأسئلة القديمة', 'manage_options', 'zad-faqconv', 'zad_faqconv_page' );
} );

function zad_faqconv_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$from = zad_faqconv_cfg( 'from' );
	echo '<div class="wrap" dir="rtl"><h1>تحويل الأسئلة القديمة</h1>';
	if ( ! empty( $_POST['zad_fc'] ) && check_admin_referer( 'zad_fc' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['zad_fc'] ) );
		if ( 'run' === $act ) {
			$slugs = isset( $_POST['slug'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['slug'] ) ) : array();
			$names = isset( $_POST['name'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['name'] ) ) : array();
			list( $ob, $nb ) = zad_faqconv_bases();
			if ( $ob !== $nb ) { echo '<div class="notice notice-error"><p>لم يُنفَّذ: رابط النوع القديم (<code>' . esc_html( $ob ) . '</code>) يختلف عن رابط الأسئلة الجديد (<code>' . esc_html( $nb ) . '</code>) وهذا يغيّر روابط الأسئلة.</p></div>'; }
			else {
				$log = zad_faqconv_run( $slugs, $names ); $bad = array();
				foreach ( $log['urls'] as $id => $u ) { if ( get_permalink( $id ) !== $u ) { $bad[] = $id; } }
				echo '<div class="notice notice-success"><p>تم تحويل <b>' . count( $log['items'] ) . '</b> سؤالاً. ' . ( $bad ? '' : 'كل روابط الأسئلة بقيت كما هي.' ) . '</p></div>';
				if ( $bad ) { echo '<div class="notice notice-error"><p>تغيّر رابط الأسئلة التالية (ID): ' . esc_html( implode( '، ', $bad ) ) . ' — اضغط «تراجع» الآن.</p></div>'; }
				$review = array_filter( wp_list_pluck( $log['items'], 'auto_ex' ) );
				if ( $review ) {
					echo '<h2>مقتطفات مُلئت تلقائياً (للمراجعة)</h2><ul style="list-style:disc;padding-inline-start:22px">';
					foreach ( array_keys( $review ) as $id ) { echo '<li><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></li>'; }
					echo '</ul>';
				}
			}
		} elseif ( 'undo' === $act ) {
			echo '<div class="notice notice-warning"><p>تم التراجع عن ' . (int) zad_faqconv_undo() . ' سؤالاً (أُعيد النوع القديم وفُتح تسجيله).</p></div>';
		} elseif ( 'retire' === $act ) {
			if ( ! empty( $_POST['retire_on'] ) && zad_faqconv_posts() ) { echo '<div class="notice notice-error"><p>لا يمكن قفل النوع القديم: ما زالت فيه أسئلة غير محوّلة.</p></div>'; }
			else { update_option( 'zad_faqconv_retire', ! empty( $_POST['retire_on'] ) ? 1 : 0, false ); echo '<div class="notice notice-success"><p>تم الحفظ.</p></div>'; }
		}
	}
	$posts = zad_faqconv_posts();
	$log   = get_option( 'zad_faqconv_log' );
	list( $ob, $nb ) = zad_faqconv_bases();
	echo '<p>يحوّل أسئلة النوع القديم <code>' . esc_html( $from ) . '</code> إلى نوع الثيم <code>zad_faq</code>: نفس الرقم والرابط والتاريخ والمحتوى وكل الحقول (ومنها Yoast). لا يُحذف شيء، ويمكن التراجع.</p>';
	if ( ! $posts ) {
		echo '<p><b>لا توجد أسئلة من النوع القديم لتحويلها.</b></p>';
	} else {
		$ptm = zad_faqconv_post_terms( wp_list_pluck( $posts, 'ID' ) );
		$old = zad_faqconv_old_terms();
		echo '<h2>معاينة (لم يتغيّر شيء بعد)</h2>';
		echo '<p>رابط الأسئلة: القديم <code>/' . esc_html( $ob ) . '/</code> ← الجديد <code>/' . esc_html( $nb ) . '/</code> ' . ( $ob === $nb ? '<span style="color:#0a7">✓ نفس الرابط</span>' : '<span style="color:#b00">✗ مختلف — لن يُنفَّذ التحويل</span>' ) . '</p>';
		echo '<form method="post">'; wp_nonce_field( 'zad_fc' );
		if ( $old ) {
			echo '<h3>الأقسام (' . count( $old ) . ')</h3><table class="widefat striped" style="max-width:820px"><thead><tr><th>القسم القديم</th><th>الاسم الجديد</th><th>رابط القسم الجديد</th><th>الأسئلة</th></tr></thead><tbody>';
			foreach ( $old as $id => $t ) {
				$n = 0; foreach ( $ptm as $ts ) { if ( in_array( (int) $id, $ts, true ) ) { $n++; } }
				$slug = zad_faqconv_term_slug( $t->slug );
				echo '<tr><td>' . esc_html( $t->name ) . ' <code>' . esc_html( urldecode( $t->slug ) ) . '</code></td><td><input type="text" name="name[' . (int) $id . ']" value="' . esc_attr( $t->name ) . '"></td><td><input type="text" dir="ltr" name="slug[' . (int) $id . ']" value="' . esc_attr( $slug ) . '"> <small>/' . esc_html( zad_slug( 'zad_faq_slug', 'faq' ) ) . '-category/…</small></td><td>' . (int) $n . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">الروابط القديمة لأرشيف هذه الأقسام ستُحوَّل 301 إلى الأقسام الجديدة: ';
			$lst = array(); foreach ( $old as $id => $t ) { $lst[] = '<code>/' . esc_html( zad_faqconv_old_path( $id, $t->slug ) ) . '/</code>'; }
			echo implode( ' ', $lst ) . '</p>';
		}
		echo '<h3>الأسئلة (' . count( $posts ) . ')</h3><table class="widefat striped"><thead><tr><th>#</th><th>السؤال</th><th>الحالة</th><th>القسم الجديد</th><th>المقتطف</th><th>الربط بخدمة</th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$id = (int) $p->ID;
			$cats = array(); foreach ( (array) ( $ptm[ $id ] ?? array() ) as $ot ) { if ( isset( $old[ $ot ] ) ) { $cats[] = $old[ $ot ]->name; } }
			$ex = '' === trim( (string) $p->post_excerpt ) ? zad_faqconv_excerpt( $p->post_content ) : '';
			$cur = array_filter( (array) get_post_meta( $id, '_zad_faq_services', true ) );
			$sg  = $cur ? 0 : zad_faq_match_service( $id );
			$sv  = $cur ? implode( '، ', array_map( 'get_the_title', array_map( 'intval', $cur ) ) ) . ' (قائم)' : ( $sg ? get_the_title( $sg ) . ' (اقتراح)' : '—' );
			echo '<tr><td>' . $id . '</td><td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $p->post_title ) . '</a><br><code>/' . esc_html( $nb ) . '/' . esc_html( urldecode( $p->post_name ) ) . '/</code></td><td>' . esc_html( $p->post_status ) . '</td><td>' . ( $cats ? esc_html( implode( '، ', $cats ) ) : '—' ) . '</td><td>' . ( '' !== trim( (string) $p->post_excerpt ) ? 'موجود' : ( $ex ? '<em>يُملأ: ' . esc_html( wp_trim_words( $ex, 14, '…' ) ) . '</em>' : 'فارغ (لا فقرة)' ) ) . '</td><td>' . esc_html( $sv ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><button class="button button-primary" name="zad_fc" value="run"' . ( $ob === $nb ? '' : ' disabled' ) . ' onclick="return confirm(\'تنفيذ التحويل؟ يمكن التراجع بعده.\');">تنفيذ التحويل</button></p></form>';
	}
	if ( is_array( $log ) && ! empty( $log['items'] ) ) {
		echo '<hr><form method="post">'; wp_nonce_field( 'zad_fc' );
		echo '<p>آخر تحويل: ' . esc_html( wp_date( 'Y-m-d H:i', (int) $log['ts'] ) ) . ' — ' . count( $log['items'] ) . ' سؤالاً. <button class="button" name="zad_fc" value="undo" onclick="return confirm(\'التراجع عن التحويل؟\');">تراجع</button></p></form>';
	}
	echo '<hr><form method="post">'; wp_nonce_field( 'zad_fc' );
	echo '<h2>قفل النوع القديم</h2><p><label><input type="checkbox" name="retire_on" value="1"' . checked( zad_faqconv_retired(), true, false ) . '> أوقف تسجيل النوع القديم <code>' . esc_html( $from ) . '</code> وقسمه <code>' . esc_html( zad_faqconv_cfg( 'tax' ) ) . '</code> (لا يحذف بيانات)</label> <button class="button" name="zad_fc" value="retire">حفظ</button></p><p class="description">يُفعَّل تلقائياً بعد تحويل كل الأسئلة؛ ويلزم لتعمل روابط <code>/' . esc_html( $nb ) . '/…</code> من النوع الجديد.</p></form></div>';
}
