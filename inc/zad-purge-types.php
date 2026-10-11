<?php defined( 'ABSPATH' ) || exit;
/**
 * الأدوات ← «حذف نوعَي النقل والتسليك نهائياً»: removes ONLY the two retired custom post types «نقل وتخزين الأثاث» (moving) and «تسليك المجاري» (drain_cleaning / drain-cleaning)
 * and the database rows that belong to those post types. Everything else about moving / drain cleaning (Redirection rules, texts, default lists, smart-link rules, tools, schema)
 * is left alone on purpose: those services are re-added later under the zad_service type. Irreversible: «تجربة» shows what would go (nothing is changed); «حذف نهائي» needs a
 * database backup + the typed confirmation. The theme's safety net (inc/zad-legacy.php) re-registers a type that still has rows, which is why the rows must be deleted.
 *
 * Deleted: the posts (every status) with revisions, post meta, term links and comments; the menu items that point at them; their ids left in other pages' fields (related /
 * other-cities / guides / question links…); the type slugs in the «types» lists of the theme options; Yoast's settings of the two types; terms of taxonomies only these types used;
 * optionally the media attached to them (only files nothing else uses). Reported, not changed: Redirection rules and internal links that mention their old URLs.
 */

function zad_pg_types() { return array( 'moving', 'drain_cleaning', 'drain-cleaning' ); }

function zad_pg_in( $arr ) { return $arr ? implode( ',', array_map( 'intval', $arr ) ) : '0'; }

/** Post types of the two families that still have rows (any status). */
function zad_pg_present() {
	global $wpdb;
	$in = "'" . implode( "','", array_map( 'esc_sql', zad_pg_types() ) ) . "'";
	return (array) $wpdb->get_col( "SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_type IN ($in)" ); // phpcs:ignore
}

function zad_pg_ids() {
	global $wpdb;
	$in = "'" . implode( "','", array_map( 'esc_sql', zad_pg_types() ) ) . "'";
	return array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in)" ) ); // phpcs:ignore
}

/** Remove the given ids from a stored value (array / CSV / single id). @return array( newValue, changed, deleteMeta ) */
function zad_pg_strip_id( $raw, $ids ) {
	$u = maybe_unserialize( $raw );
	if ( is_array( $u ) ) {
		$new = array_values( array_filter( $u, function ( $x ) use ( $ids ) { return ! is_scalar( $x ) || ! in_array( (int) $x, $ids, true ) || ! is_numeric( $x ); } ) );
		return array( $new, count( $new ) !== count( $u ), ! $new );
	}
	$s = (string) $raw;
	if ( preg_match( '/^\d+(?:\s*,\s*\d+)+$/', $s ) ) { $parts = array_map( 'trim', explode( ',', $s ) ); $new = array_values( array_filter( $parts, function ( $x ) use ( $ids ) { return ! in_array( (int) $x, $ids, true ); } ) ); return array( implode( ',', $new ), count( $new ) !== count( $parts ), ! $new ); }
	if ( preg_match( '/^\d+$/', $s ) && in_array( (int) $s, $ids, true ) ) { return array( '', true, true ); }
	return array( $raw, false, false );
}

function zad_pg_meta_keys() { return array( '_zad_related', '_zad_cities_svc', '_zad_guides', '_zad_qnet', '_zad_faq_services', '_zad_faq_service_link', '_zad_wk_service', '_zad_post_service', '_zad_default_service' ); }

function zad_pg_slug_options() { return array( 'zad_service_slugs', 'zad_article_slugs', 'zad_faq_slugs' ); }

/** What would be removed. Pure reads. */
function zad_pg_inventory() {
	global $wpdb;
	$ids = zad_pg_ids(); $in = zad_pg_in( $ids ); $inv = array( 'ids' => $ids, 'types' => array(), 'registered' => array() );
	foreach ( zad_pg_types() as $t ) { if ( post_type_exists( $t ) ) { $inv['registered'][] = $t; } }
	foreach ( (array) $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) n FROM {$wpdb->posts} WHERE ID IN ($in) GROUP BY post_type, post_status" ) as $r ) { $inv['types'][ $r->post_type ][ $r->post_status ] = (int) $r->n; } // phpcs:ignore
	$inv['samples'] = (array) $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_name FROM {$wpdb->posts} WHERE ID IN ($in) ORDER BY post_type, ID LIMIT 40", ARRAY_A ); // phpcs:ignore
	$inv['revisions'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent IN ($in)" ); // phpcs:ignore
	$inv['meta']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN ($in)" ); // phpcs:ignore
	$inv['rels']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id IN ($in)" ); // phpcs:ignore
	$inv['taxes']     = (array) $wpdb->get_col( "SELECT DISTINCT tt.taxonomy FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id IN ($in)" ); // phpcs:ignore
	$inv['comments']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID IN ($in)" ); // phpcs:ignore
	$types_in = "'" . implode( "','", array_map( 'esc_sql', zad_pg_types() ) ) . "'";
	$inv['menu'] = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = 'nav_menu_item' AND ( ( m.meta_key = '_menu_item_object' AND m.meta_value IN ($types_in) ) OR ( m.meta_key = '_menu_item_object_id' AND m.meta_value IN ($in) AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} x WHERE x.post_id = p.ID AND x.meta_key = '_menu_item_type' AND x.meta_value = 'post_type' ) ) )" ) ); // phpcs:ignore
	$att = array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN ($in)" ) ); // phpcs:ignore
	$inv['attach'] = array( 'all' => count( $att ), 'free' => array_values( array_filter( $att, 'zad_pg_attachment_unused' ) ) );
	$inv['refs'] = array();
	$keys = "'" . implode( "','", array_map( 'esc_sql', zad_pg_meta_keys() ) ) . "'";
	foreach ( (array) $wpdb->get_results( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($keys) AND post_id NOT IN ($in)" ) as $m ) { // phpcs:ignore
		list( , $ch ) = zad_pg_strip_id( $m->meta_value, $ids ); if ( $ch ) { $inv['refs'][ (int) $m->meta_id ] = array( (int) $m->post_id, $m->meta_key ); }
	}
	$inv['redirects'] = array();
	$rt = $wpdb->prefix . 'redirection_items';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rt ) ) === $rt ) { // phpcs:ignore
		$inv['redirects'] = (array) $wpdb->get_results( "SELECT id, url, action_data FROM $rt WHERE url LIKE '/moving%' OR url LIKE '/drain-cleaning%' OR action_data LIKE '%/moving/%' OR action_data LIKE '%/moving' OR action_data LIKE '%/drain-cleaning%' OR url LIKE '%/moving/%' OR url LIKE '%/drain-cleaning/%'", ARRAY_A ); // phpcs:ignore
	}
	$inv['links'] = array();
	foreach ( (array) $wpdb->get_results( "SELECT ID, post_type, post_title FROM {$wpdb->posts} WHERE ID NOT IN ($in) AND post_status NOT IN ('trash','auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item') AND ( post_content LIKE '%/moving/%' OR post_content LIKE '%/drain-cleaning/%' OR post_content LIKE '%/moving\"%' OR post_content LIKE '%/drain-cleaning\"%' ) LIMIT 100" ) as $p ) { $inv['links'][] = array( (int) $p->ID, $p->post_type, $p->post_title ); } // phpcs:ignore
	$inv['options'] = array(); $o = get_option( '_memo_theme_options' );
	if ( is_array( $o ) ) {
		foreach ( zad_pg_slug_options() as $k ) { if ( isset( $o[ $k ] ) && is_string( $o[ $k ] ) && zad_pg_clean_slugs( $o[ $k ] ) !== $o[ $k ] ) { $inv['options'][ $k ] = array( $o[ $k ], zad_pg_clean_slugs( $o[ $k ] ) ); } }
	}
	$inv['yoast'] = array(); $yt = get_option( 'wpseo_titles' );
	if ( is_array( $yt ) ) { foreach ( $yt as $k => $v ) { if ( preg_match( '/(?:^|-)(?:moving|drain[_-]cleaning)(?:-|$)/', $k ) ) { $inv['yoast'][] = $k; } } }
	return $inv;
}

function zad_pg_clean_slugs( $s ) {
	$parts = array_filter( array_map( 'trim', explode( ',', (string) $s ) ), function ( $x ) { return '' !== $x && ! in_array( strtolower( $x ), array( 'moving', 'drain-cleaning', 'drain_cleaning' ), true ); } );
	return implode( ',', $parts );
}

/** Is this attachment used by anything that stays (featured image, a page content/meta)? */
function zad_pg_attachment_unused( $aid ) {
	global $wpdb;
	$aid = (int) $aid;
	$ids = zad_pg_ids(); $in = zad_pg_in( $ids );
	if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d AND post_id NOT IN ($in)", $aid ) ) ) { return false; } // phpcs:ignore
	if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID NOT IN ($in) AND post_type NOT IN ('revision','attachment') AND post_status <> 'auto-draft' AND post_content LIKE %s", '%wp-image-' . $aid . '%' ) ) ) { return false; } // phpcs:ignore
	$url = wp_get_attachment_url( $aid );
	if ( $url ) {
		$file = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID NOT IN ($in) AND post_type NOT IN ('revision','attachment') AND post_status <> 'auto-draft' AND post_content LIKE %s", '%' . $wpdb->esc_like( $file ) . '%' ) ) ) { return false; } // phpcs:ignore
	}
	if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id NOT IN ($in) AND meta_key NOT IN ('_thumbnail_id','_wp_attached_file','_wp_attachment_metadata') AND ( meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s )", (string) $aid, '%,' . $aid . ',%', '%"' . $aid . '"%' ) ) ) { return false; } // phpcs:ignore
	return true;
}

/** Deletes it all. @return array counts */
function zad_pg_run( $media ) {
	global $wpdb;
	$inv = zad_pg_inventory(); $ids = $inv['ids']; $n = array( 'posts' => 0, 'attachments' => 0, 'refs' => 0, 'menu' => 0, 'terms' => 0, 'options' => 0, 'yoast' => 0 );
	$taxes = $inv['taxes'];
	// 1) menu items that point at them
	foreach ( $inv['menu'] as $mid ) { wp_delete_post( (int) $mid, true ); $n['menu']++; }
	// 2) ids left in other pages' fields
	foreach ( array_keys( $inv['refs'] ) as $mid ) {
		$m = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $mid ) ); // phpcs:ignore
		if ( ! $m ) { continue; }
		list( $new, $ch, $del ) = zad_pg_strip_id( $m->meta_value, $ids );
		if ( ! $ch ) { continue; }
		if ( $del ) { delete_post_meta( (int) $m->post_id, $m->meta_key ); } else { update_post_meta( (int) $m->post_id, $m->meta_key, $new ); }
		$n['refs']++;
	}
	// 3) media attached to them (only when asked, only files nothing else uses)
	if ( $media ) { foreach ( $inv['attach']['free'] as $aid ) { if ( wp_delete_attachment( (int) $aid, true ) ) { $n['attachments']++; } } }
	// 4) the posts (revisions, meta, term links and comments go with them)
	foreach ( $ids as $id ) { if ( wp_delete_post( $id, true ) ) { $n['posts']++; } }
	// 5) loose rows of those ids (a post deleted by a plugin leaves none, but be sure)
	$in = zad_pg_in( $ids );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)" ); $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($in)" ); // phpcs:ignore
	// 6) terms of taxonomies that only these types used (no remaining post type, nothing linked)
	foreach ( $taxes as $tx ) {
		$obj = get_taxonomy( $tx );
		if ( $obj && array_diff( (array) $obj->object_type, zad_pg_types() ) ) { continue; } // shared with other types: terms stay
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s", $tx ) ) as $t ) { // phpcs:ignore
			if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $t->term_taxonomy_id ) ) ) { // phpcs:ignore
				$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => (int) $t->term_taxonomy_id ) ); $wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $t->term_id ) ); // phpcs:ignore
				if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $t->term_id ) ) ) { $wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $t->term_id ) ); } // phpcs:ignore
				$n['terms']++;
			}
		}
	}
	// 7) the type slugs in the theme options + Yoast settings of the two types
	$o = get_option( '_memo_theme_options' );
	if ( is_array( $o ) && $inv['options'] ) { foreach ( $inv['options'] as $k => $pair ) { $o[ $k ] = $pair[1]; $n['options']++; } update_option( '_memo_theme_options', $o ); }
	$yt = get_option( 'wpseo_titles' );
	if ( is_array( $yt ) && $inv['yoast'] ) { foreach ( $inv['yoast'] as $k ) { unset( $yt[ $k ] ); $n['yoast']++; } update_option( 'wpseo_titles', $yt ); }
	// 8) caches + permalinks
	foreach ( array( 'zad_area_ids', 'zad_404_lists', 'zad_sitemap_html', 'zad_wiz_map3', 'zad_work_items' ) as $tr ) { delete_transient( $tr ); }
	update_option( 'zad_nav_ver', time() ); update_option( 'zad_hood_ver', time() ); update_option( 'zad_qnet_ver', time() ); if ( function_exists( 'zad_city_bump' ) ) { zad_city_bump(); }
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); }
	flush_rewrite_rules( false );
	wp_cache_flush();
	return $n;
}

add_action( 'admin_menu', function () { add_management_page( 'حذف النقل والتسليك نهائياً', 'حذف النقل والتسليك نهائياً', 'manage_options', 'zad-purge-types', 'zad_pg_page' ); } );

function zad_pg_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>حذف «نقل وتخزين الأثاث» و«تسليك المجاري» نهائياً</h1>';
	echo '<div class="notice notice-error inline"><p><b>الحذف نهائي ولا يمكن التراجع عنه.</b> خُد نسخة احتياطية كاملة من قاعدة البيانات أولاً. «تجربة» تعرض ما سيُحذف ولا تغيّر شيئاً.</p></div>';
	$do = isset( $_POST['zad_pg_do'] ) ? sanitize_key( wp_unslash( $_POST['zad_pg_do'] ) ) : ''; // phpcs:ignore
	if ( 'run' === $do ) {
		check_admin_referer( 'zad_pg' );
		$word = isset( $_POST['zad_pg_word'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['zad_pg_word'] ) ) ) : '';
		if ( 'احذف نهائياً' !== $word || empty( $_POST['zad_pg_backup'] ) ) { echo '<div class="notice notice-warning"><p>لم يُنفَّذ شيء: اكتب «احذف نهائياً» بالحرف وأكّد أنك أخذت نسخة احتياطية.</p></div>'; }
		else {
			$n = zad_pg_run( ! empty( $_POST['zad_pg_media'] ) );
			echo '<div class="notice notice-success"><p><b>تم الحذف.</b> صفحات: ' . (int) $n['posts'] . ' · عناصر قوائم: ' . (int) $n['menu'] . ' · حقول نُظّفت في صفحات أخرى: ' . (int) $n['refs'] . ' · صور: ' . (int) $n['attachments'] . ' · تصنيفات: ' . (int) $n['terms'] . ' · إعدادات القالب: ' . (int) $n['options'] . ' · إعدادات Yoast: ' . (int) $n['yoast'] . '. احفظ الروابط الدائمة وامسح كاش LiteSpeed.</p></div>';
		}
	}
	$inv = zad_pg_inventory();
	echo '<h2>' . ( 'preview' === $do ? 'نتيجة التجربة (لم يتغير شيء)' : 'ما الموجود الآن' ) . '</h2>';
	if ( ! $inv['ids'] && ! $inv['options'] && ! $inv['yoast'] && ! $inv['refs'] && ! $inv['menu'] ) { echo '<div class="notice notice-success inline"><p>لا توجد أي بيانات لهذين النوعين في قاعدة البيانات.</p></div>'; }
	echo '<ul style="list-style:disc;margin-inline-start:22px">';
	echo '<li>مسجَّل حالياً كنوع محتوى: <b>' . ( $inv['registered'] ? esc_html( implode( '، ', $inv['registered'] ) ) . '</b> — احذف تسجيله من ملف الـ mu-plugin على الموقع (انظر التقرير)' : 'لا' ) . '</li>';
	foreach ( $inv['types'] as $t => $st ) { $a = array(); foreach ( $st as $k => $c ) { $a[] = $k . ': ' . $c; } echo '<li>النوع <code>' . esc_html( $t ) . '</code>: ' . esc_html( implode( ' · ', $a ) ) . '</li>'; }
	echo '<li>نسخ المراجعات: <b>' . (int) $inv['revisions'] . '</b> · حقول الصفحات (meta): <b>' . (int) $inv['meta'] . '</b> · روابط تصنيفات: <b>' . (int) $inv['rels'] . '</b>' . ( $inv['taxes'] ? ' (' . esc_html( implode( '، ', $inv['taxes'] ) ) . ')' : '' ) . ' · تعليقات: <b>' . (int) $inv['comments'] . '</b></li>';
	echo '<li>عناصر قوائم تشير إليها: <b>' . count( $inv['menu'] ) . '</b> · قيم في صفحات أخرى فيها أرقام صفحاتها: <b>' . count( $inv['refs'] ) . '</b> · قواعد Redirection (لا تُحذف): <b>' . count( $inv['redirects'] ) . '</b> · إعدادات Yoast: <b>' . count( $inv['yoast'] ) . '</b> · قوائم أنواع في إعدادات القالب: <b>' . count( $inv['options'] ) . '</b></li>';
	echo '<li>صور مرفوعة عليها: <b>' . (int) $inv['attach']['all'] . '</b> (منها غير مستعمل في أي مكان آخر: <b>' . count( $inv['attach']['free'] ) . '</b>)</li></ul>';
	if ( $inv['samples'] ) { echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>#</th><th>النوع</th><th>الحالة</th><th>العنوان</th><th>slug</th></tr></thead><tbody>'; foreach ( $inv['samples'] as $s ) { echo '<tr><td>' . (int) $s['ID'] . '</td><td>' . esc_html( $s['post_type'] ) . '</td><td>' . esc_html( $s['post_status'] ) . '</td><td>' . esc_html( $s['post_title'] ) . '</td><td dir="ltr">' . esc_html( urldecode( $s['post_name'] ) ) . '</td></tr>'; } echo '</tbody></table>' . ( count( $inv['ids'] ) > 40 ? '<p>… وغيرها (أول 40 من ' . count( $inv['ids'] ) . ')</p>' : '' ); }
	foreach ( $inv['options'] as $k => $pair ) { echo '<p><code>' . esc_html( $k ) . '</code>: «' . esc_html( mb_substr( $pair[0], 0, 80 ) ) . '» ← «' . esc_html( mb_substr( $pair[1], 0, 80 ) ) . '»</p>'; }
	if ( $inv['links'] ) { echo '<div class="notice notice-warning inline"><p><b>صفحات فيها روابط إلى /moving/ أو /drain-cleaning/</b> (لن تُعدَّل تلقائياً، عدّلها أو احذف الرابط): '; foreach ( $inv['links'] as $l ) { echo '<a href="' . esc_url( get_edit_post_link( $l[0] ) ) . '">' . esc_html( $l[2] ?: '#' . $l[0] ) . '</a> · '; } echo '</p></div>'; }
	echo '<form method="post">'; wp_nonce_field( 'zad_pg' );
	echo '<p><label><input type="checkbox" name="zad_pg_media" value="1"> احذف أيضاً الصور المرفوعة على هذه الصفحات (فقط التي لا يستعملها شيء آخر)</label></p>';
	echo '<p><button class="button" name="zad_pg_do" value="preview">تجربة</button></p><hr>';
	echo '<p><label><input type="checkbox" name="zad_pg_backup" value="1"> أخذت نسخة احتياطية كاملة من قاعدة البيانات</label></p><p><label>للتأكيد اكتب: <b>احذف نهائياً</b> <input type="text" name="zad_pg_word" autocomplete="off"></label></p>';
	echo '<p><button class="button button-primary" name="zad_pg_do" value="run" onclick="return confirm(\'حذف نهائي لا رجعة فيه. متابعة؟\');">حذف نهائي</button></p></form></div>';
}
