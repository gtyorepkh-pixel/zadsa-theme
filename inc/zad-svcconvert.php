<?php
/**
 * Tool: «تحويل صفحات الخدمات» (Tools menu).
 * Moves pages that are really services (regular posts) into the theme's zad_service:
 *  - only post_type (and, where you edit it, post_name) changes: same ID, dates, content, thumbnail and every meta (Yoast included);
 *  - new URL = /{service base}/{same slug}/ ; the old URL gets a 301 (Redirection plugin when active, plus the theme's own map);
 *  - sections (service_cat) and cities (service_area) are suggested from the old categories and are editable before running;
 *  - emptied old category archives are redirected to the new section;
 *  - old links inside the content of every page are replaced (original content is backed up);
 *  - one undo button reverts everything.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_SVCC_BACKUP = '_zad_svcc_content_backup';

function zad_svcc_source_types() { return apply_filters( 'zad_svcc_source_types', array( 'post' ) ); }

/* ---------------- own 301 map (works without the Redirection plugin, and after it is removed) ---------------- */
add_action( 'template_redirect', function () {
	global $wp;
	if ( ! is_404() || ! isset( $wp->request ) ) { return; }
	$map = get_option( 'zad_svcc_map' );
	if ( ! is_array( $map ) || ! $map ) { return; }
	$k = mb_strtolower( urldecode( trim( (string) $wp->request, '/' ) ) );
	if ( isset( $map[ $k ] ) ) { wp_safe_redirect( $map[ $k ], 301 ); exit; }
}, 1 );

/* ---------------- pure helpers ---------------- */
function zad_svcc_path_key( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) { $path = substr( $path, strlen( $home ) - 1 ); }
	return mb_strtolower( urldecode( trim( $path, '/' ) ) );
}

/** A real article (a question / guide title) is never suggested. */
function zad_svcc_is_article_title( $t ) {
	return (bool) preg_match( '/[؟?]\s*$|^\s*(?:كيف|كيفية|ما|ماذا|هل|لماذا|متى|أسباب|اسباب|نصائح|طرق|دليل)\s/u', (string) $t );
}

function zad_svcc_suggest_selected( $post, $cat_names ) {
	if ( 'post' !== $post->post_type ) { return true; }
	if ( zad_svcc_is_article_title( $post->post_title ) ) { return false; }
	foreach ( $cat_names as $n ) { if ( preg_match( '/^\s*خدمات/u', $n ) ) { return true; } }
	return (bool) preg_match( '/شركة|مؤسسة/u', $post->post_title );
}

/** Suggested section name from the old categories (then the title). */
function zad_svcc_section_guess( $cat_names, $title ) {
	$rules = apply_filters( 'zad_svcc_section_rules', array(
		'/نقل|عفش|أثاث|اثاث/u'                       => 'نقل الأثاث',
		'/تسليك|مجاري|المجارير|بيارات|شفط|صرف/u'    => 'تسليك المجاري',
		'/جلي|بلاط|أرضيات|ارضيات|رخام|تجديد/u'        => 'جلي البلاط',
		'/مكافحة|حشرات|رش\s|صراصير|نمل/u'            => 'مكافحة الحشرات',
		'/عزل/u'                                       => 'العزل',
		'/تسرب|تسريب/u'                                => 'كشف التسربات',
		'/تنظيف|غسيل|تعقيم/u'                          => 'التنظيف',
	) );
	foreach ( array_merge( $cat_names, array( $title ) ) as $src ) {
		foreach ( $rules as $re => $name ) { if ( preg_match( $re, $src ) ) { return $name; } }
	}
	foreach ( $cat_names as $n ) { $s = trim( preg_replace( '/^\s*خدمات\s*/u', '', $n ) ); if ( '' !== $s ) { return $s; } }
	return '';
}

function zad_svcc_known_cities() {
	$c = array( 'الرياض', 'جدة', 'الدمام', 'الخبر', 'الظهران', 'القطيف', 'الجبيل', 'الأحساء', 'الهفوف', 'مكة', 'مكة المكرمة', 'المدينة المنورة', 'الطائف', 'أبها', 'خميس مشيط', 'بريدة', 'تبوك', 'حائل', 'نجران', 'جازان', 'ينبع' );
	if ( taxonomy_exists( 'service_area' ) ) {
		$t = get_terms( array( 'taxonomy' => 'service_area', 'parent' => 0, 'hide_empty' => false ) );
		if ( $t && ! is_wp_error( $t ) ) { $c = array_merge( $c, wp_list_pluck( $t, 'name' ) ); }
	}
	return array_unique( $c );
}

/** City from the old category names: «فرع جدة» → جدة, or a plain known city name. */
function zad_svcc_city_guess( $cat_names ) {
	$known = zad_svcc_known_cities();
	foreach ( $cat_names as $n ) {
		$n = trim( $n );
		if ( preg_match( '/^فرع\s+(.+)$/u', $n, $m ) ) { return trim( $m[1] ); }
		if ( in_array( $n, $known, true ) ) { return $n; }
	}
	return '';
}

/** Rewrite internal hrefs (old path → new URL) inside a content string. Returns array( new_content, count ). */
function zad_svcc_replace_links( $content, $map ) {
	$n    = 0;
	$home = home_url( '/' );
	$out  = preg_replace_callback( '/(href\s*=\s*)(["\'])(.*?)\2/is', function ( $m ) use ( $map, $home, &$n ) {
		$u = $m[3];
		if ( 0 === strpos( $u, $home ) ) { $abs = true; $rest = substr( $u, strlen( $home ) - 1 ); }
		elseif ( '/' === ( $u[0] ?? '' ) && 0 !== strpos( $u, '//' ) ) { $abs = false; $rest = $u; }
		else { return $m[0]; }
		$suffix = '';
		if ( preg_match( '/^([^?#]*)([?#].*)?$/s', $rest, $p ) ) { $rest = $p[1]; $suffix = $p[2] ?? ''; }
		$key = mb_strtolower( urldecode( trim( $rest, '/' ) ) );
		if ( '' === $key || ! isset( $map[ $key ] ) ) { return $m[0]; }
		$new = $abs ? $map[ $key ] : (string) wp_parse_url( $map[ $key ], PHP_URL_PATH );
		$n++;
		return $m[1] . $m[2] . $new . $suffix . $m[2];
	}, (string) $content );
	return array( $out, $n );
}

/* ---------------- data ---------------- */
function zad_svcc_sources() {
	return get_posts( array( 'post_type' => zad_svcc_source_types(), 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
}

function zad_svcc_old_terms( $post ) {
	$taxes = 'post' === $post->post_type ? array( 'category' ) : array_diff( get_object_taxonomies( $post->post_type ), array( 'post_format', 'service_cat', 'service_area' ) );
	if ( ! $taxes ) { return array(); }
	$t = wp_get_object_terms( $post->ID, $taxes );
	return is_wp_error( $t ) ? array() : $t;
}

function zad_svcc_new_url( $slug ) { return home_url( '/' . zad_type_base( 'zad_service' ) . '/' . rawurlencode( urldecode( $slug ) ) . '/' ); }

function zad_svcc_slug_default( $post ) {
	$map = apply_filters( 'zad_svcc_slug_map', array() );
	$cur = urldecode( $post->post_name );
	return $map[ $post->post_type ][ $cur ] ?? $cur;
}

/* ---------------- Redirection plugin (optional) ---------------- */
function zad_svcc_red_group() {
	if ( ! class_exists( 'Red_Group' ) ) { return 0; }
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}redirection_groups WHERE name = %s LIMIT 1", 'تحويل الخدمات' ) ); // phpcs:ignore
	if ( $id ) { return $id; }
	try { $g = Red_Group::create( 'تحويل الخدمات', 1 ); } catch ( \Throwable $e ) { return 0; }
	return ( is_object( $g ) && method_exists( $g, 'get_id' ) ) ? (int) $g->get_id() : 0;
}
function zad_svcc_red_add( $group, $from_path, $to ) {
	if ( ! $group || ! class_exists( 'Red_Item' ) ) { return 0; }
	try {
		$it = Red_Item::create( array( 'url' => '/' . trim( $from_path, '/' ) . '/', 'match_type' => 'url', 'action_type' => 'url', 'action_code' => 301, 'action_data' => array( 'url' => $to ), 'group_id' => $group, 'regex' => false, 'title' => 'تحويل الخدمات' ) );
	} catch ( \Throwable $e ) { return 0; }
	return ( is_object( $it ) && method_exists( $it, 'get_id' ) ) ? (int) $it->get_id() : 0;
}
function zad_svcc_red_del( $id ) {
	if ( ! $id || ! class_exists( 'Red_Item' ) ) { return; }
	try { $it = Red_Item::get_by_id( (int) $id ); if ( $it && method_exists( $it, 'delete' ) ) { $it->delete(); } } catch ( \Throwable $e ) {} // phpcs:ignore
}

/* ---------------- Yoast helpers ---------------- */
function zad_svcc_yoast_forget( $ids ) {
	global $wpdb;
	if ( ! $ids ) { return; }
	$t = $wpdb->prefix . 'yoast_indexable';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return; } // phpcs:ignore
	$in = implode( ',', array_map( 'intval', $ids ) );
	$iids = $wpdb->get_col( "SELECT id FROM {$t} WHERE object_type = 'post' AND object_id IN ($in)" ); // phpcs:ignore
	if ( $iids ) {
		$h = $wpdb->prefix . 'yoast_indexable_hierarchy';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $h ) ) === $h ) { $wpdb->query( "DELETE FROM {$h} WHERE indexable_id IN (" . implode( ',', array_map( 'intval', $iids ) ) . ')' ); } // phpcs:ignore
		$wpdb->query( "DELETE FROM {$t} WHERE id IN (" . implode( ',', array_map( 'intval', $iids ) ) . ')' ); // phpcs:ignore — rebuilt by Yoast on demand with the new type and URL
	}
}
/** Freeze the title/description Yoast shows now, when the page has none of its own (the new type would otherwise switch templates). */
function zad_svcc_yoast_freeze( $id ) {
	$added = array();
	if ( ! function_exists( 'YoastSEO' ) ) { return $added; }
	try {
		$m = YoastSEO()->meta->for_post( $id );
		if ( ! $m ) { return $added; }
		foreach ( array( '_yoast_wpseo_title' => $m->title, '_yoast_wpseo_metadesc' => $m->description ) as $k => $v ) {
			$v = trim( (string) $v );
			if ( '' === trim( (string) get_post_meta( $id, $k, true ) ) && '' !== $v ) { update_post_meta( $id, $k, $v ); $added[] = $k; }
		}
	} catch ( \Throwable $e ) {} // phpcs:ignore
	return $added;
}

/* ---------------- run ---------------- */
function zad_svcc_term_id( $name, $tax, $parent = 0, &$created = array() ) {
	$name = trim( $name );
	if ( '' === $name ) { return 0; }
	$ex = term_exists( $name, $tax, $parent ?: null );
	if ( ! $ex ) { $ex = term_exists( $name, $tax ); }
	if ( ! $ex ) {
		$ex = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
		if ( is_wp_error( $ex ) ) { return 0; }
		$created[] = (int) $ex['term_id'];
	}
	return (int) ( is_array( $ex ) ? $ex['term_id'] : $ex );
}

function zad_svcc_run( $rows, $freeze ) {
	global $wpdb;
	$log = get_option( 'zad_svcc_log' );
	if ( ! is_array( $log ) ) { $log = array( 'items' => array(), 'created' => array(), 'red' => array(), 'map' => array(), 'links' => array(), 'menu' => array(), 'skipped' => array() ); }
	$group   = zad_svcc_red_group();
	$urlmap  = array(); $sec_of = array(); $report = array( 'done' => 0, 'skip' => array(), 'urlbad' => array(), 'links' => 0, 'red' => $group ? 'Redirection' : 'خريطة الثيم فقط' );
	$types   = zad_svcc_source_types();
	$cat_hits = array(); // old category term id => array( new section term id => count )

	foreach ( $rows as $id => $r ) {
		$p = get_post( (int) $id );
		if ( ! $p || ! in_array( $p->post_type, $types, true ) ) { $report['skip'][ $id ] = 'ليس من أنواع المصدر'; continue; }
		$slug = $r['slug'] === urldecode( $p->post_name ) ? $p->post_name : sanitize_title( $r['slug'] );
		if ( '' === $slug ) { $report['skip'][ $id ] = 'رابط فارغ'; continue; }
		$clash = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'zad_service' AND post_name = %s AND post_status NOT IN ('trash','auto-draft') AND ID <> %d LIMIT 1", $slug, $id ) ); // phpcs:ignore
		if ( $clash ) { $report['skip'][ $id ] = 'رابط مكرر مع الخدمة ' . (int) $clash; continue; }
		$old_url = get_permalink( $p );
		$old_key = zad_svcc_path_key( $old_url );
		$item = array( 'type' => $p->post_type, 'name' => $p->post_name, 'old_url' => $old_url, 'terms' => array( 'service_cat' => array(), 'service_area' => array() ), 'yoast' => array() );
		if ( $freeze ) { $item['yoast'] = zad_svcc_yoast_freeze( $id ); }
		$cats = zad_svcc_old_terms( $p );
		$set  = array( 'post_type' => 'zad_service' );
		if ( $slug !== $p->post_name ) { $set['post_name'] = $slug; }
		$wpdb->update( $wpdb->posts, $set, array( 'ID' => $id ) ); // phpcs:ignore — keeps ID, dates, modified time
		clean_post_cache( $id );
		$created = array();
		$sec = zad_svcc_term_id( $r['sec'], 'service_cat', 0, $created );
		if ( $sec ) { wp_set_object_terms( $id, array( $sec ), 'service_cat', true ); $item['terms']['service_cat'][] = $sec; }
		$city = zad_svcc_term_id( $r['city'], 'service_area', 0, $created );
		if ( $city ) { wp_set_object_terms( $id, array( $city ), 'service_area', true ); $item['terms']['service_area'][] = $city; }
		$log['created'] = array_merge( $log['created'], $created );
		foreach ( $cats as $c ) { if ( 'category' === $c->taxonomy && $sec ) { $cat_hits[ $c->term_id ][ $sec ] = ( $cat_hits[ $c->term_id ][ $sec ] ?? 0 ) + 1; } }
		// menu items pointing at this post by ID
		$mi = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $id ) ); // phpcs:ignore
		foreach ( (array) $mi as $mid ) {
			if ( 'post_type' === get_post_meta( $mid, '_menu_item_type', true ) && $p->post_type === get_post_meta( $mid, '_menu_item_object', true ) ) { update_post_meta( $mid, '_menu_item_object', 'zad_service' ); $log['menu'][ $mid ] = $p->post_type; }
		}
		zad_svcc_yoast_forget( array( $id ) );
		$new_url = get_permalink( $id );
		if ( zad_svcc_new_url( $slug ) !== $new_url && rawurldecode( zad_svcc_new_url( $slug ) ) !== rawurldecode( $new_url ) ) { $report['urlbad'][ $id ] = $new_url; }
		$urlmap[ $old_key ] = $new_url;
		$item['new_url'] = $new_url;
		$log['items'][ $id ] = $item;
		$log['map'][ $old_key ] = $new_url;
		$rid = zad_svcc_red_add( $group, rawurldecode( $old_key ), $new_url );
		if ( $rid ) { $log['red'][] = $rid; }
		$report['done']++;
	}

	// emptied old category archives → their new section
	foreach ( $cat_hits as $tid => $secs ) {
		$default = (int) get_option( 'default_category' );
		if ( (int) $tid === $default ) { continue; }
		$left = get_posts( array( 'post_type' => 'post', 'category' => (int) $tid, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
		if ( $left ) { continue; }
		arsort( $secs );
		$sec_link = get_term_link( (int) key( $secs ), 'service_cat' );
		$old_link = get_term_link( (int) $tid, 'category' );
		if ( is_wp_error( $sec_link ) || is_wp_error( $old_link ) ) { continue; }
		$k = zad_svcc_path_key( $old_link );
		$log['map'][ $k ] = $sec_link;
		$rid = zad_svcc_red_add( $group, rawurldecode( $k ), $sec_link );
		if ( $rid ) { $log['red'][] = $rid; }
	}
	update_option( 'zad_svcc_map', $log['map'], false );

	// internal links in the content of all pages (original content kept in post meta)
	if ( $urlmap ) {
		$rowsC = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache','attachment') AND post_content LIKE '%href%' LIMIT 5000" ); // phpcs:ignore
		foreach ( (array) $rowsC as $c ) {
			list( $new, $n ) = zad_svcc_replace_links( $c->post_content, $urlmap );
			if ( ! $n || $new === $c->post_content ) { continue; }
			if ( '' === (string) get_post_meta( $c->ID, ZAD_SVCC_BACKUP, true ) ) { update_post_meta( $c->ID, ZAD_SVCC_BACKUP, $c->post_content ); }
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => (int) $c->ID ) ); // phpcs:ignore
			clean_post_cache( (int) $c->ID );
			$log['links'][ $c->ID ] = md5( $new );
			$report['links'] += $n;
		}
	}
	update_option( 'zad_svcc_log', $log, false );
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); }
	return $report;
}

function zad_svcc_undo() {
	global $wpdb;
	$log = get_option( 'zad_svcc_log' );
	if ( ! is_array( $log ) || empty( $log['items'] ) ) { return array( 'posts' => 0, 'links' => 0, 'kept' => 0 ); }
	$r = array( 'posts' => 0, 'links' => 0, 'kept' => 0 );
	foreach ( (array) $log['red'] as $rid ) { zad_svcc_red_del( $rid ); }
	delete_option( 'zad_svcc_map' );
	foreach ( (array) $log['links'] as $pid => $hash ) {
		$cur = get_post_field( 'post_content', (int) $pid );
		$bak = get_post_meta( (int) $pid, ZAD_SVCC_BACKUP, true );
		if ( '' !== (string) $bak && md5( $cur ) === $hash ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $bak ), array( 'ID' => (int) $pid ) ); clean_post_cache( (int) $pid ); delete_post_meta( (int) $pid, ZAD_SVCC_BACKUP ); $r['links']++; } // phpcs:ignore
		else { $r['kept']++; } // edited since: left as is
	}
	foreach ( (array) $log['menu'] as $mid => $old ) { update_post_meta( (int) $mid, '_menu_item_object', $old ); }
	foreach ( $log['items'] as $id => $it ) {
		$id = (int) $id;
		if ( 'zad_service' !== get_post_type( $id ) ) { continue; }
		foreach ( (array) ( $it['terms'] ?? array() ) as $tax => $ids ) { if ( $ids ) { wp_remove_object_terms( $id, array_map( 'intval', $ids ), $tax ); } }
		foreach ( (array) ( $it['yoast'] ?? array() ) as $k ) { delete_post_meta( $id, $k ); }
		$wpdb->update( $wpdb->posts, array( 'post_type' => $it['type'], 'post_name' => $it['name'] ), array( 'ID' => $id ) ); // phpcs:ignore
		clean_post_cache( $id );
		$r['posts']++;
	}
	zad_svcc_yoast_forget( array_map( 'intval', array_keys( $log['items'] ) ) );
	foreach ( (array) $log['created'] as $tid ) {
		foreach ( array( 'service_cat', 'service_area' ) as $tax ) {
			$t = get_term( (int) $tid, $tax );
			if ( $t && ! is_wp_error( $t ) && 0 === (int) $t->count ) { wp_delete_term( (int) $tid, $tax ); }
		}
	}
	delete_option( 'zad_svcc_log' );
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); }
	return $r;
}

/* ---------------- admin page ---------------- */
add_action( 'admin_menu', function () {
	add_management_page( 'تحويل صفحات الخدمات', 'تحويل صفحات الخدمات', 'manage_options', 'zad-svcconv', 'zad_svcc_page' );
} );

function zad_svcc_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>تحويل صفحات الخدمات</h1>';
	if ( ! empty( $_POST['zad_sv'] ) && check_admin_referer( 'zad_sv' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['zad_sv'] ) );
		if ( 'run' === $act ) {
			$sel = isset( $_POST['sel'] ) ? array_keys( (array) $_POST['sel'] ) : array();
			$rows = array();
			foreach ( $sel as $id ) {
				$id = (int) $id;
				$rows[ $id ] = array(
					'slug' => isset( $_POST['slug'][ $id ] ) ? trim( sanitize_text_field( wp_unslash( $_POST['slug'][ $id ] ) ) ) : '',
					'sec'  => isset( $_POST['sec'][ $id ] ) ? sanitize_text_field( wp_unslash( $_POST['sec'][ $id ] ) ) : '',
					'city' => isset( $_POST['city'][ $id ] ) ? sanitize_text_field( wp_unslash( $_POST['city'][ $id ] ) ) : '',
				);
			}
			if ( ! $rows ) { echo '<div class="notice notice-warning"><p>لم تحدّد أي صفحة.</p></div>'; }
			else {
				$rep = zad_svcc_run( $rows, ! empty( $_POST['freeze'] ) );
				echo '<div class="notice notice-success"><p>تم تحويل <b>' . (int) $rep['done'] . '</b> صفحة. التحويلات 301: ' . esc_html( $rep['red'] ) . ' + خريطة الثيم. روابط داخلية معدَّلة: <b>' . (int) $rep['links'] . '</b>.</p></div>';
				if ( $rep['skip'] ) { echo '<div class="notice notice-warning"><p>لم تُحوَّل: '; foreach ( $rep['skip'] as $id => $why ) { echo esc_html( $id . ' (' . $why . ') ' ); } echo '</p></div>'; }
				if ( $rep['urlbad'] ) { echo '<div class="notice notice-error"><p>الرابط الجديد مختلف عن المتوقع للأرقام: ' . esc_html( implode( '، ', array_keys( $rep['urlbad'] ) ) ) . ' — راجعها أو اضغط «تراجع».</p></div>'; }
			}
		} elseif ( 'undo' === $act ) {
			$r = zad_svcc_undo();
			echo '<div class="notice notice-warning"><p>تم التراجع: ' . (int) $r['posts'] . ' صفحة أُعيدت، و' . (int) $r['links'] . ' محتوى أُعيدت روابطه' . ( $r['kept'] ? '، و' . (int) $r['kept'] . ' محتوى عُدّل بعد التحويل فتُرك كما هو' : '' ) . '.</p></div>';
		}
	}
	$posts = zad_svcc_sources();
	$log   = get_option( 'zad_svcc_log' );
	echo '<p>ينقل الصفحات التي هي خدمات فعلاً (المقالات العادية) إلى نوع الخدمات <code>zad_service</code>. نفس الرقم والتاريخ والمحتوى والصورة وكل الحقول (ومنها Yoast). الرابط الجديد: <code>/' . esc_html( zad_type_base( 'zad_service' ) ) . '/{الرابط القديم نفسه}/</code>، والقديم يُحوَّل 301. لا يلمس روابط pest_control وcleaning والصفحات العادية.</p>';
	if ( ! post_type_exists( 'zad_service' ) ) { echo '<div class="notice notice-error"><p>نوع <code>zad_service</code> غير مسجّل.</p></div></div>'; return; }
	if ( $posts ) {
		$cities = zad_svcc_known_cities();
		$secs   = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => false ) );
		echo '<datalist id="zadsv-secs">'; foreach ( (array) ( is_wp_error( $secs ) ? array() : $secs ) as $t ) { echo '<option value="' . esc_attr( $t->name ) . '">'; } echo '</datalist><datalist id="zadsv-cities">'; foreach ( $cities as $c ) { echo '<option value="' . esc_attr( $c ) . '">'; } echo '</datalist>';
		echo '<form method="post">'; wp_nonce_field( 'zad_sv' );
		echo '<p><label><input type="checkbox" name="freeze" value="1" checked> ثبّت عنوان ووصف Yoast الحاليين للصفحات التي بلا عنوان/وصف خاص (حتى لا يتغيّر قالبهما بتغيّر النوع)</label></p>';
		echo '<p><button type="button" class="button" onclick="document.querySelectorAll(\'.zadsv-cb\').forEach(function(c){c.checked=true})">تحديد الكل</button> <button type="button" class="button" onclick="document.querySelectorAll(\'.zadsv-cb\').forEach(function(c){c.checked=c.dataset.sug===\'1\'})">المقترح فقط</button> <button type="button" class="button" onclick="document.querySelectorAll(\'.zadsv-cb\').forEach(function(c){c.checked=false})">إلغاء الكل</button></p>';
		echo '<table class="widefat striped"><thead><tr><th></th><th>العنوان</th><th>الرابط القديم</th><th>الرابط الجديد (الاسم)</th><th>التصنيف القديم</th><th>القسم الجديد</th><th>المدينة</th></tr></thead><tbody>';
		$sug = 0;
		foreach ( $posts as $p ) {
			$terms = zad_svcc_old_terms( $p ); $names = wp_list_pluck( $terms, 'name' );
			$on    = zad_svcc_suggest_selected( $p, $names ); if ( $on ) { $sug++; }
			$slug  = zad_svcc_slug_default( $p );
			$clash = (bool) get_posts( array( 'post_type' => 'zad_service', 'name' => $p->post_name, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
			$ycust = ( '' !== trim( (string) get_post_meta( $p->ID, '_yoast_wpseo_title', true ) ) ) ? 'عنوان Yoast مخصّص' : 'قالب Yoast';
			echo '<tr><td><input type="checkbox" class="zadsv-cb" data-sug="' . ( $on ? 1 : 0 ) . '" name="sel[' . (int) $p->ID . ']" value="1"' . ( $on ? ' checked' : '' ) . '></td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( $p->post_title ) . '</a><br><small>' . esc_html( $p->post_type . ' · #' . $p->ID . ' · ' . $p->post_status . ' · ' . $ycust ) . '</small></td>';
			echo '<td><code dir="ltr">' . esc_html( urldecode( (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH ) ) ) . '</code></td>';
			echo '<td><code dir="ltr">/' . esc_html( zad_type_base( 'zad_service' ) ) . '/</code><input type="text" dir="ltr" name="slug[' . (int) $p->ID . ']" value="' . esc_attr( $slug ) . '" style="width:200px">' . ( $clash ? ' <b style="color:#b00">رابط مكرر</b>' : '' ) . '</td>';
			echo '<td>' . esc_html( implode( '، ', $names ) ?: '—' ) . '</td>';
			echo '<td><input type="text" list="zadsv-secs" name="sec[' . (int) $p->ID . ']" value="' . esc_attr( zad_svcc_section_guess( $names, $p->post_title ) ) . '"></td>';
			echo '<td><input type="text" list="zadsv-cities" name="city[' . (int) $p->ID . ']" value="' . esc_attr( zad_svcc_city_guess( $names ) ) . '" style="width:110px"></td></tr>';
		}
		echo '</tbody></table><p>' . count( $posts ) . ' صفحة، المقترح منها: ' . (int) $sug . '. غير المعلَّمة (مثل المقالات الحقيقية) لا تُحوَّل.</p>';
		echo '<p><button class="button button-primary" name="zad_sv" value="run" onclick="return confirm(\'تحويل الصفحات المحددة؟ يمكن التراجع بعده.\');">تنفيذ التحويل للمحدّد</button></p></form>';
	} else { echo '<p><b>لا توجد صفحات من أنواع المصدر.</b></p>'; }
	if ( is_array( $log ) && ! empty( $log['items'] ) ) {
		echo '<hr><form method="post">'; wp_nonce_field( 'zad_sv' );
		echo '<p>المحوَّل حالياً: ' . count( $log['items'] ) . ' صفحة، ' . count( (array) $log['red'] ) . ' تحويل في Redirection، ' . count( (array) $log['links'] ) . ' محتوى عُدّلت روابطه. <button class="button" name="zad_sv" value="undo" onclick="return confirm(\'التراجع عن كل ما حُوِّل؟\');">تراجع عن الكل</button></p></form>';
	}
	echo '</div>';
}
