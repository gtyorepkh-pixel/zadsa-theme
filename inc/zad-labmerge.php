<?php defined( 'ABSPATH' ) || exit;
/**
 * 3.46.8 — Tools › «نقل محتوى المسودات (زاد)».
 * The drafts of «تجربة حقول زاد» (mu-plugins/zad-lab) carry the new content and theme fields of a published page. This tool puts that
 * content ON the published page itself, so it keeps its ID and its URL:
 *   copy    — same type: title, content, excerpt, theme fields (_zad_*), featured image, SEO title/description/keyword → the published page; the draft goes to the trash.
 *   move    — the published page is an old article (post): the same copy, then the service converter moves it to the service type with the
 *             same ID, a short English URL and a 301 from the old URL (its own «تراجع عن الكل» reverts that part).
 *   publish — a draft that is a new page under its source (the district pages of a city page): published as it is, noindex removed.
 * Every changed page keeps a full backup of what was replaced (_zad_lm_backup), and «تراجع» puts it all back.
 */

const ZAD_LM_BACKUP = '_zad_lm_backup';
const ZAD_LM_LOG    = 'zad_lm_log';

/** Old articles → array( converter action, parent id, slug, city ). */
/**
 * Where an old «post» goes when its draft is a service CPT: post id => array( kind, parent, slug, city ).
 *
 * The lab pack already says all of this (`source_id`, `target_type`, `target_parent`, `post_name`), so the map is
 * built from the packs instead of being typed out one post at a time — a pack and its move can't drift apart.
 * The hand-written rows below stay as overrides for the pages whose destination is not what their pack says.
 */
function zad_lm_moves_from_packs() {
	$kinds = array( 'zad_service' => 'service', 'pest_control' => 'pest', 'cleaning' => 'cleaning' );
	$out   = array();
	foreach ( (array) ( function_exists( 'zad_lab_packs' ) ? zad_lab_packs() : array() ) as $pk ) {
		$sid  = (int) ( $pk['source_id'] ?? 0 );
		$type = (string) ( $pk['target_type'] ?? '' );
		$slug = (string) ( $pk['post_name'] ?? '' );
		if ( ! $sid || '' === $slug || ! isset( $kinds[ $type ] ) ) { continue; }
		if ( 'post' !== get_post_type( $sid ) ) { continue; }   // only an old post needs moving
		$out[ $sid ] = array( $kinds[ $type ], (int) ( $pk['target_parent'] ?? 0 ), $slug, (string) ( $pk['meta']['city'] ?? '' ) );
	}
	return $out;
}

function zad_lm_moves() {
	return apply_filters( 'zad_lm_moves', zad_lm_moves_from_packs() + array(
		390   => array( 'service', 27151, 'riyadh', 'الرياض' ),   // نقل عفش بالرياض
		75    => array( 'service', 27152, 'riyadh', 'الرياض' ),   // تخزين اثاث بالرياض
		81    => array( 'service', 27153, 'riyadh', 'الرياض' ),   // تسليك مجاري بالرياض
		20430 => array( 'service', 27157, 'riyadh', 'الرياض' ),   // جلي بلاط بالرياض
		43    => array( 'cleaning', 25527, 'apartments', '' ),    // تنظيف شقق بالرياض
		21723 => array( 'cleaning', 25527, 'villas', '' ),        // تنظيف فلل بالرياض
		21594 => array( 'cleaning', 25527, 'kitchens', '' ),      // تنظيف مطابخ بالرياض
		44    => array( 'cleaning', 25371, 'majlis-riyadh', '' ), // تنظيف مجالس بالرياض → /cleaning/sofa/majlis-riyadh/
		71    => array( 'cleaning', 25527, 'pools', '' ),         // تنظيف مسابح بالرياض
		22046 => array( 'cleaning', 25527, 'new-houses', '' ),    // تنظيف المنازل الجديدة بالرياض
		22045 => array( 'cleaning', 25527, 'east', '' ),          // تنظيف منازل شرق الرياض
		22047 => array( 'cleaning', 25527, 'north', '' ),         // تنظيف شمال الرياض
	) );
}

/** Meta keys taken from the draft. */
function zad_lm_key_ok( $k ) {
	if ( in_array( $k, array( '_zad_lab_source', ZAD_LM_BACKUP, '_zad_seo_noindex', '_zad_seo_canonical' ), true ) ) { return false; } // the draft's noindex / canonical belong to the draft
	return 0 === strpos( $k, '_zad_' ) || in_array( $k, array( '_thumbnail_id', '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw' ), true );
}

/** One row per lab draft: array( slug, label, draft, source, mode copy|move|publish|skip, why, new ). */
function zad_lm_rows() {
	$packs  = function_exists( 'zad_lab_packs' ) ? zad_lab_packs() : array();
	$drafts = (array) get_option( 'zad_lab_drafts', array() );
	$moves  = zad_lm_moves();
	$rows   = array();
	foreach ( $packs as $slug => $pk ) {
		$sid = (int) $pk['source_id']; $did = (int) ( $drafts[ $slug ] ?? 0 );
		$r = array( 'slug' => $slug, 'label' => (string) ( $pk['label'] ?? $slug ), 'draft' => $did, 'source' => $sid, 'mode' => 'skip', 'why' => '', 'new' => '' );
		$d = $did ? get_post( $did ) : null; $s = get_post( $sid );
		if ( ! $d ) { $r['why'] = 'المسودة غير موجودة'; }
		elseif ( 'draft' !== $d->post_status ) { $r['why'] = 'المسودة حالتها ' . $d->post_status . ' (نُقلت أو نُشرت قبل كده)'; }
		elseif ( (int) $d->post_parent === $sid ) { $r['mode'] = 'publish'; $r['new'] = zad_svcc_dest_url_safe( $d->post_type, $sid, $d->post_name ); }
		elseif ( ! $s || 'publish' !== $s->post_status ) { $r['why'] = 'الصفحة المنشورة غير موجودة'; }
		elseif ( $s->post_type === $d->post_type ) { $r['mode'] = 'copy'; }
		elseif ( 'post' === $s->post_type && isset( $moves[ $sid ] ) ) {
			$m = $moves[ $sid ]; $r['mode'] = 'move';
			$r['new'] = zad_svcc_dest_url_safe( 'service' === $m[0] ? 'zad_service' : ( 'pest' === $m[0] ? 'pest_control' : 'cleaning' ), $m[1], $m[2] );
		} else { $r['why'] = 'نوع الصفحة المنشورة (' . $s->post_type . ') مختلف عن المسودة (' . $d->post_type . ')'; }
		$rows[] = $r;
	}
	return $rows;
}

function zad_svcc_dest_url_safe( $type, $parent, $slug ) {
	if ( function_exists( 'zad_svcc_dest_url' ) ) { return zad_svcc_dest_url( $type, $parent, $slug ); }
	return trailingslashit( get_permalink( $parent ) ) . $slug . '/';
}

/** Draft fields → published page. Returns '' or the reason it failed. */
function zad_lm_copy( $did, $sid, &$log ) {
	$d = get_post( $did ); $s = get_post( $sid );
	$keys = array();
	foreach ( array_keys( (array) get_post_meta( $did ) ) as $k ) { if ( zad_lm_key_ok( $k ) ) { $keys[] = $k; } }
	if ( '' === (string) get_post_meta( $sid, ZAD_LM_BACKUP, true ) ) {
		$bak = array( 'title' => $s->post_title, 'content' => $s->post_content, 'excerpt' => $s->post_excerpt, 'meta' => array(), 'draft' => $did, 'time' => time() );
		foreach ( $keys as $k ) { $bak['meta'][ $k ] = metadata_exists( 'post', $sid, $k ) ? get_post_meta( $sid, $k, true ) : null; }
		add_post_meta( $sid, ZAD_LM_BACKUP, wp_slash( $bak ), true );
	}
	$res = wp_update_post( wp_slash( array( 'ID' => $sid, 'post_title' => $d->post_title, 'post_content' => $d->post_content, 'post_excerpt' => $d->post_excerpt ) ), true );
	if ( is_wp_error( $res ) ) { return $res->get_error_message(); }
	foreach ( $keys as $k ) { update_post_meta( $sid, $k, wp_slash( get_post_meta( $did, $k, true ) ) ); }
	$log['copied'][ $sid ] = $did;
	if ( wp_trash_post( $did ) ) { $log['trashed'][] = $did; }
	return '';
}

function zad_lm_run() {
	$out = array( 'copy' => 0, 'move' => 0, 'publish' => 0, 'err' => array(), 'conv' => null );
	$rows  = zad_lm_rows();
	$moves = zad_lm_moves();
	$need_move = array_filter( $rows, function ( $r ) { return 'move' === $r['mode']; } );
	if ( $need_move && ! function_exists( 'zad_svcc_run' ) ) {
		$out['err'][] = 'أداة «تحويل صفحات الخدمات» مش محمّلة (إعدادات الأدوات)، فما اتنفذش أي حاجة.';
		return $out;
	}
	if ( $need_move && ( ! function_exists( 'zad_svcc_red_active' ) || ! zad_svcc_red_active() ) ) {
		$out['err'][] = 'إضافة Redirection غير مفعّلة: التحويلات 301 لازم تتسجل فيها، فما اتنفذش أي حاجة. فعّلها وجرّب تاني.';
		return $out;
	}
	$log = get_option( ZAD_LM_LOG );
	$log = is_array( $log ) ? $log : array( 'copied' => array(), 'trashed' => array(), 'published' => array() );
	$conv = array();
	foreach ( $rows as $r ) {
		if ( 'copy' === $r['mode'] || 'move' === $r['mode'] ) {
			$e = zad_lm_copy( $r['draft'], $r['source'], $log );
			if ( '' !== $e ) { $out['err'][] = $r['label'] . ': ' . $e; continue; }
			if ( 'move' === $r['mode'] ) {
				$m = $moves[ $r['source'] ];
				$conv[ $r['source'] ] = array( 'act' => $m[0], 'slug' => $m[2], 'parent' => $m[1], 'sec' => '', 'city' => $m[3], 'to' => '' );
			} else { $out['copy']++; }
		} elseif ( 'publish' === $r['mode'] ) {
			$res = wp_update_post( array( 'ID' => $r['draft'], 'post_status' => 'publish' ), true );
			if ( is_wp_error( $res ) ) { $out['err'][] = $r['label'] . ': ' . $res->get_error_message(); continue; }
			delete_post_meta( $r['draft'], '_yoast_wpseo_meta-robots-noindex' ); delete_post_meta( $r['draft'], '_zad_seo_noindex' );
			$log['published'][] = $r['draft']; $out['publish']++;
		}
	}
	update_option( ZAD_LM_LOG, $log, false );
	if ( $conv ) {
		$rep = zad_svcc_run( $conv, false );
		$out['conv'] = $rep; $out['move'] = (int) $rep['moved'];
		foreach ( (array) $rep['skip'] as $id => $why ) { $out['err'][] = get_the_title( $id ) . ' (#' . $id . '): المحتوى اتنقل، بس التحويل لخدمة اتخطّى: ' . $why; }
		if ( ! empty( $rep['error'] ) ) { $out['err'][] = $rep['error']; }
	}
	update_option( 'zad_catalog_ver', time(), false );
	return $out;
}

function zad_lm_undo() {
	$out = array( 'pages' => 0, 'drafts' => 0, 'unpub' => 0 );
	$log = get_option( ZAD_LM_LOG );
	if ( ! is_array( $log ) ) { return $out; }
	foreach ( (array) $log['copied'] as $sid => $did ) {
		$bak = get_post_meta( (int) $sid, ZAD_LM_BACKUP, true );
		if ( ! is_array( $bak ) ) { continue; }
		wp_update_post( wp_slash( array( 'ID' => (int) $sid, 'post_title' => $bak['title'], 'post_content' => $bak['content'], 'post_excerpt' => $bak['excerpt'] ) ) );
		foreach ( (array) $bak['meta'] as $k => $v ) {
			if ( null === $v ) { delete_post_meta( (int) $sid, $k ); } else { update_post_meta( (int) $sid, $k, wp_slash( $v ) ); }
		}
		delete_post_meta( (int) $sid, ZAD_LM_BACKUP );
		$out['pages']++;
	}
	foreach ( (array) $log['trashed'] as $did ) {
		if ( 'trash' !== get_post_status( (int) $did ) ) { continue; }
		$f = function () { return 'draft'; };
		add_filter( 'wp_untrash_post_status', $f, 99 ); wp_untrash_post( (int) $did ); remove_filter( 'wp_untrash_post_status', $f, 99 );
		$out['drafts']++;
	}
	foreach ( (array) $log['published'] as $did ) {
		wp_update_post( array( 'ID' => (int) $did, 'post_status' => 'draft' ) );
		update_post_meta( (int) $did, '_yoast_wpseo_meta-robots-noindex', '1' ); update_post_meta( (int) $did, '_zad_seo_noindex', '1' );
		$out['unpub']++;
	}
	delete_option( ZAD_LM_LOG );
	update_option( 'zad_catalog_ver', time(), false );
	return $out;
}

/** 3.46.8 fix: the first run also copied the draft's own noindex (_zad_seo_noindex) and canonical. Put back what the page had. */
function zad_lm_fix_robots() {
	$log = get_option( ZAD_LM_LOG ); $n = 0;
	if ( ! is_array( $log ) ) { return 0; }
	foreach ( (array) $log['copied'] as $sid => $did ) {
		$bak = get_post_meta( (int) $sid, ZAD_LM_BACKUP, true );
		foreach ( array( '_zad_seo_noindex', '_zad_seo_canonical' ) as $k ) {
			$was = is_array( $bak ) && array_key_exists( $k, (array) $bak['meta'] ) ? $bak['meta'][ $k ] : null;
			if ( is_array( $bak ) && array_key_exists( $k, (array) $bak['meta'] ) ) { // it was copied: back to the page's own value
				if ( null === $was ) { delete_post_meta( (int) $sid, $k ); } else { update_post_meta( (int) $sid, $k, wp_slash( $was ) ); }
				$n++;
			}
		}
	}
	foreach ( (array) $log['published'] as $did ) { if ( delete_post_meta( (int) $did, '_zad_seo_noindex' ) ) { $n++; } }
	return $n;
}

/* ---------------- 3.46.10: Bukayriyah and Ar-Rass removed: their pages → trash + 301 to their section ---------------- */
const ZAD_LM_DROP_LOG = 'zad_lm_drop_log';

/** post id => target (post id, or 'archive:{type}'). */
function zad_lm_drop_map() {
	return apply_filters( 'zad_lm_drop_map', array(
		567 => 'archive:cleaning', 599 => 'archive:cleaning',          // شركة تنظيف بالرس / بالبكيرية → /cleaning/
		19889 => 25371, 19891 => 25371, 19887 => 25371, 19885 => 25371, // كنب وموكيت → /cleaning/sofa/
		19895 => 25311, 19893 => 25311,                                  // خزانات → /cleaning/tanks/
	) );
}
function zad_lm_drop_target( $t ) {
	if ( is_string( $t ) && 0 === strpos( $t, 'archive:' ) ) { return (string) get_post_type_archive_link( substr( $t, 8 ) ); }
	return 'publish' === get_post_status( (int) $t ) ? (string) get_permalink( (int) $t ) : '';
}
function zad_lm_drop_pending() {
	$o = array();
	foreach ( zad_lm_drop_map() as $id => $t ) { $st = get_post_status( (int) $id ); if ( $st && 'trash' !== $st ) { $o[ (int) $id ] = $t; } }
	return $o;
}

function zad_lm_drop_run() {
	global $wpdb;
	$out = array( 'trashed' => 0, 'red' => 0, 'links' => 0, 'err' => array() );
	if ( ! function_exists( 'zad_svcc_red_add' ) || ! zad_svcc_red_active() ) { $out['err'][] = 'Redirection غير مفعّلة، فما اتنفذش حاجة.'; return $out; }
	$group = zad_svcc_red_group();
	$log = get_option( ZAD_LM_DROP_LOG ); $log = is_array( $log ) ? $log : array( 'trash' => array(), 'red' => array(), 'links' => array() );
	$map = array();
	foreach ( zad_lm_drop_pending() as $id => $t ) {
		$to = zad_lm_drop_target( $t );
		if ( '' === $to ) { $out['err'][] = '#' . $id . ': وجهة التحويل غير منشورة'; continue; }
		$p = get_post( $id ); $was = $p->post_status;
		$old = 'publish' === $was ? get_permalink( $id ) : '';
		if ( ! wp_trash_post( $id ) ) { $out['err'][] = get_the_title( $id ) . ': تعذّر نقلها للسلة'; continue; }
		$log['trash'][ $id ] = $was; $out['trashed']++;
		if ( '' !== $old ) {
			$key = zad_svcc_path_key( $old );
			if ( ! zad_svcc_red_lookup( $key ) ) {
				$rid = zad_svcc_red_add( $group, rawurldecode( $key ), $to );
				if ( $rid ) { $log['red'][] = $rid; $out['red']++; }
			}
			$map[ mb_strtolower( urldecode( trim( (string) wp_parse_url( $old, PHP_URL_PATH ), '/' ) ) ) ] = $to;
		}
	}
	if ( $map ) {
		$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item') AND post_content LIKE '%href%' LIMIT 5000" ); // phpcs:ignore
		foreach ( (array) $rows as $c ) {
			list( $nc, $n ) = zad_svcc_replace_links( $c->post_content, $map );
			if ( ! $n ) { continue; }
			if ( ! isset( $log['links'][ $c->ID ] ) ) { $log['links'][ $c->ID ] = $c->post_content; }
			$wpdb->update( $wpdb->posts, array( 'post_content' => $nc ), array( 'ID' => (int) $c->ID ) ); // phpcs:ignore
			clean_post_cache( (int) $c->ID ); $out['links'] += $n;
		}
	}
	update_option( ZAD_LM_DROP_LOG, $log, false );
	update_option( 'zad_catalog_ver', time(), false );
	return $out;
}

function zad_lm_drop_undo() {
	global $wpdb;
	$log = get_option( ZAD_LM_DROP_LOG ); $n = 0;
	if ( ! is_array( $log ) ) { return 0; }
	foreach ( (array) $log['red'] as $rid ) { zad_svcc_red_del( $rid ); }
	foreach ( (array) $log['links'] as $pid => $orig ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $orig ), array( 'ID' => (int) $pid ) ); clean_post_cache( (int) $pid ); } // phpcs:ignore
	foreach ( (array) $log['trash'] as $id => $was ) {
		if ( 'trash' !== get_post_status( (int) $id ) ) { continue; }
		$f = function () use ( $was ) { return $was; };
		add_filter( 'wp_untrash_post_status', $f, 99 ); wp_untrash_post( (int) $id ); remove_filter( 'wp_untrash_post_status', $f, 99 );
		$n++;
	}
	delete_option( ZAD_LM_DROP_LOG );
	update_option( 'zad_catalog_ver', time(), false );
	return $n;
}

/* ---------------- relocate a page that was already moved (new parent / slug), keeping the 301s and links straight ---------------- */
/** id => array( new parent, new slug ). */
function zad_lm_relocations() {
	return apply_filters( 'zad_lm_relocations', array( 44 => array( 25371, 'majlis-riyadh' ) ) );
}

/** Pending relocations (page not yet at its place). */
function zad_lm_reloc_pending() {
	$o = array();
	foreach ( zad_lm_relocations() as $id => $to ) {
		$p = get_post( (int) $id );
		if ( ! $p || 'post' === $p->post_type ) { continue; }
		if ( (int) $p->post_parent === (int) $to[0] && $p->post_name === $to[1] ) { continue; }
		$o[ (int) $id ] = $to;
	}
	return $o;
}

function zad_lm_relocate() {
	global $wpdb;
	$done = array();
	foreach ( zad_lm_reloc_pending() as $id => $to ) {
		$p = get_post( $id ); $pp = get_post( (int) $to[0] );
		if ( ! $pp || $pp->post_type !== $p->post_type ) { $done[] = '#' . $id . ': الصفحة الأم غير صالحة'; continue; }
		$clash = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_parent = %d AND post_status NOT IN ('trash','auto-draft') AND ID <> %d LIMIT 1", $p->post_type, $to[1], (int) $to[0], $id ) ); // phpcs:ignore
		if ( $clash ) { $done[] = '#' . $id . ': الرابط مستخدم (#' . $clash . ')'; continue; }
		$old = get_permalink( $id );
		$wpdb->update( $wpdb->posts, array( 'post_parent' => (int) $to[0], 'post_name' => $to[1] ), array( 'ID' => $id ) ); // phpcs:ignore
		clean_post_cache( $id );
		$new = get_permalink( $id );
		// 301s that pointed to the old address now go straight to the new one (no chain)
		$red = 0;
		if ( function_exists( 'zad_svcc_red_plugin' ) && zad_svcc_red_plugin() ) {
			$vars = array_unique( array( $old, rawurldecode( $old ), wp_make_link_relative( $old ), rawurldecode( wp_make_link_relative( $old ) ) ) );
			foreach ( $vars as $v ) { $red += (int) $wpdb->update( $wpdb->prefix . 'redirection_items', array( 'action_data' => $new ), array( 'action_data' => $v ) ); } // phpcs:ignore
		}
		// the converter's log keeps the right final address (its undo still works)
		$log = function_exists( 'zad_svcc_log_get' ) ? zad_svcc_log_get() : null;
		if ( $log ) {
			if ( isset( $log['items'][ $id ] ) ) { $log['items'][ $id ]['new_url'] = $new; }
			foreach ( $log['map'] as $k => $v ) { if ( rawurldecode( $v ) === rawurldecode( $old ) ) { $log['map'][ $k ] = $new; } }
		}
		// internal links
		$key = mb_strtolower( urldecode( trim( (string) wp_parse_url( $old, PHP_URL_PATH ), '/' ) ) );
		$links = 0;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item') AND post_content LIKE %s LIMIT 2000", '%' . $wpdb->esc_like( $p->post_name ) . '%' ) ); // phpcs:ignore
		foreach ( (array) $rows as $c ) {
			list( $nc, $n ) = zad_svcc_replace_links( $c->post_content, array( $key => $new ) );
			if ( ! $n ) { continue; }
			if ( '' === (string) get_post_meta( $c->ID, ZAD_SVCC_BACKUP, true ) ) { update_post_meta( $c->ID, ZAD_SVCC_BACKUP, wp_slash( $c->post_content ) ); }
			$wpdb->update( $wpdb->posts, array( 'post_content' => $nc ), array( 'ID' => (int) $c->ID ) ); // phpcs:ignore
			clean_post_cache( (int) $c->ID );
			if ( $log ) { $log['links'][ $c->ID ] = md5( $nc ); }
			$links += $n;
		}
		if ( $log ) { update_option( 'zad_svcc_log', $log, false ); update_option( 'zad_svcc_map', $log['map'], false ); }
		$done[] = get_the_title( $id ) . ': ' . urldecode( wp_make_link_relative( $old ) ) . ' → ' . urldecode( wp_make_link_relative( $new ) ) . ' · تحويلات اتعدّلت: ' . $red . ' · روابط داخلية: ' . $links;
	}
	update_option( 'zad_catalog_ver', time(), false );
	return $done;
}

add_action( 'admin_menu', function () {
	add_management_page( 'نقل محتوى المسودات', 'نقل محتوى المسودات (زاد)', 'manage_options', 'zad-labmerge', 'zad_lm_page' );
} );

function zad_lm_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	echo '<div class="wrap" dir="rtl"><h1>نقل محتوى المسودات إلى الصفحات المنشورة</h1>';
	if ( isset( $_POST['zad_lm'] ) && check_admin_referer( 'zad_lm' ) ) {
		if ( 'run' === $_POST['zad_lm'] ) {
			$o = zad_lm_run();
			echo '<div class="notice notice-success"><p>تم: نُقل محتوى <b>' . (int) $o['copy'] . '</b> صفحة على نفس رابطها، و<b>' . (int) $o['move'] . '</b> مقال اتحوّل لخدمة برابط جديد وتحويل 301، و<b>' . (int) $o['publish'] . '</b> صفحة جديدة اتنشرت.</p></div>';
			if ( ! empty( $o['conv']['red_added'] ) ) { echo '<div class="notice notice-info"><p>تحويلات 301 اتضافت في Redirection: ' . (int) $o['conv']['red_added'] . ' · روابط داخلية اتحدّثت: ' . (int) $o['conv']['links'] . '</p></div>'; }
			if ( $o['err'] ) { echo '<div class="notice notice-error"><p>' . implode( '<br>', array_map( 'esc_html', $o['err'] ) ) . '</p></div>'; }
		} elseif ( 'drop' === $_POST['zad_lm'] ) {
			$o = zad_lm_drop_run();
			echo '<div class="notice notice-success"><p>البكيرية والرس: ' . (int) $o['trashed'] . ' صفحة في سلة المهملات · ' . (int) $o['red'] . ' تحويل 301 · ' . (int) $o['links'] . ' رابط داخلي اتحدّث.</p></div>';
			if ( $o['err'] ) { echo '<div class="notice notice-error"><p>' . implode( '<br>', array_map( 'esc_html', $o['err'] ) ) . '</p></div>'; }
		} elseif ( 'dropundo' === $_POST['zad_lm'] ) {
			echo '<div class="notice notice-success"><p>رجعت ' . (int) zad_lm_drop_undo() . ' صفحة من البكيرية والرس، واتشالت تحويلاتها.</p></div>';
		} elseif ( 'fixrobots' === $_POST['zad_lm'] ) {
			echo '<div class="notice notice-success"><p>رجعت حالة الفهرسة الأصلية: ' . (int) zad_lm_fix_robots() . ' حقل.</p></div>';
		} elseif ( 'reloc' === $_POST['zad_lm'] ) {
			echo '<div class="notice notice-success"><p>' . implode( '<br>', array_map( 'esc_html', zad_lm_relocate() ) ) . '</p></div>';
		} elseif ( 'undo' === $_POST['zad_lm'] ) {
			$o = zad_lm_undo();
			echo '<div class="notice notice-success"><p>تم التراجع: رجع المحتوى القديم لـ ' . (int) $o['pages'] . ' صفحة، ورجعت ' . (int) $o['drafts'] . ' مسودة، و' . (int) $o['unpub'] . ' صفحة رجعت مسودة.</p><p>تحويل المقالات لخدمات (الروابط والـ 301) بيترجع من زر «تراجع عن الكل» في <a href="' . esc_url( admin_url( 'tools.php?page=zad-svcconv' ) ) . '">تحويل صفحات الخدمات</a> — اعمله <b>قبل</b> التراجع هنا أو بعده، الترتيب مش فارق.</p></div>';
		}
	}
	$rows = zad_lm_rows();
	$n = array_count_values( array_column( $rows, 'mode' ) );
	$modes = array( 'copy' => 'نقل المحتوى (نفس الرابط)', 'move' => 'نقل المحتوى + تحويل لخدمة (رابط جديد + 301)', 'publish' => 'نشر كصفحة جديدة', 'skip' => 'تخطّي' );
	echo '<p>المسودات بتاعة «تجربة حقول زاد» فيها المحتوى الجديد. الأداة دي بتحطه <b>على الصفحة المنشورة نفسها</b>، فالـ ID والرابط بيفضلوا زي ما هم. بيتنقل: العنوان، والمحتوى، والمقتطف، وحقول الثيم، والصورة البارزة، وعنوان ووصف السيو. قبل التغيير بتتحفظ نسخة كاملة من الصفحة، وزر «تراجع» بيرجّعها.</p>';
	echo '<p><b>' . (int) ( $n['copy'] ?? 0 ) . '</b> نقل على نفس الرابط · <b>' . (int) ( $n['move'] ?? 0 ) . '</b> مقال يتحوّل لخدمة · <b>' . (int) ( $n['publish'] ?? 0 ) . '</b> صفحة جديدة · <b>' . (int) ( $n['skip'] ?? 0 ) . '</b> تخطّي</p>';
	echo '<table class="widefat striped"><thead><tr><th>المسودة</th><th>الصفحة المنشورة</th><th>الإجراء</th><th>الرابط بعد التنفيذ</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$src = $r['source'] ? get_permalink( $r['source'] ) : '';
		$after = 'copy' === $r['mode'] ? $src : $r['new'];
		echo '<tr><td>' . esc_html( $r['label'] ) . ' <small>#' . (int) $r['draft'] . '</small></td>';
		echo '<td>' . ( $src ? '<a href="' . esc_url( $src ) . '" target="_blank">' . esc_html( urldecode( wp_make_link_relative( $src ) ) ) . '</a> <small>#' . (int) $r['source'] . ' · ' . esc_html( get_post_type( $r['source'] ) ) . '</small>' : '—' ) . '</td>';
		echo '<td>' . esc_html( $modes[ $r['mode'] ] ) . ( $r['why'] ? '<br><small style="color:#b32d2e">' . esc_html( $r['why'] ) . '</small>' : '' ) . '</td>';
		echo '<td>' . ( 'skip' !== $r['mode'] && $after ? '<code>' . esc_html( urldecode( wp_make_link_relative( $after ) ) ) . '</code>' : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$todo = count( $rows ) - (int) ( $n['skip'] ?? 0 );
	echo '<form method="post" style="margin-top:16px;display:flex;gap:10px">';
	wp_nonce_field( 'zad_lm' );
	if ( is_array( get_option( ZAD_LM_LOG ) ) ) { echo '<button class="button button-primary button-hero" name="zad_lm" value="fixrobots">إصلاح الفهرسة (شيل noindex اللي اتنقل من المسودات)</button>'; }
	$dp = zad_lm_drop_pending();
	if ( $dp ) {
		echo '</form><h2>حذف البكيرية والرس</h2><table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $dp as $id => $t ) { echo '<tr><td>' . esc_html( get_the_title( $id ) ) . ' <small>#' . (int) $id . ' · ' . esc_html( get_post_status( $id ) ) . '</small></td><td>→ <code>' . esc_html( urldecode( wp_make_link_relative( zad_lm_drop_target( $t ) ) ) ) . '</code></td></tr>'; }
		echo '</tbody></table><form method="post" style="margin-top:10px;display:flex;gap:10px">'; wp_nonce_field( 'zad_lm' );
		echo '<button class="button button-primary" name="zad_lm" value="drop">احذف ' . (int) count( $dp ) . ' صفحة وحوّلها 301</button>';
	}
	if ( is_array( get_option( ZAD_LM_DROP_LOG ) ) ) { echo '<button class="button" name="zad_lm" value="dropundo">تراجع عن حذف البكيرية والرس</button>'; }
	$rp = zad_lm_reloc_pending();
	if ( $rp ) { echo '<button class="button button-primary button-hero" name="zad_lm" value="reloc">انقل ' . (int) count( $rp ) . ' صفحة لمكانها الجديد (' . esc_html( implode( '، ', array_map( function ( $id ) { return get_the_title( $id ); }, array_keys( $rp ) ) ) ) . ')</button>'; }
	if ( $todo ) { echo '<button class="button button-primary button-hero" name="zad_lm" value="run">نفّذ النقل (' . (int) $todo . ' صفحة)</button>'; }
	if ( is_array( get_option( ZAD_LM_LOG ) ) ) { echo '<button class="button button-hero" name="zad_lm" value="undo">تراجع عن النقل</button>'; }
	echo '</form></div>';
}

/** The converter no longer warns «لها مسودة جديدة» for pages whose draft content is already on them. */
add_filter( 'zad_svcc_lab_map', function ( $map ) {
	foreach ( (array) $map as $sid => $info ) { if ( metadata_exists( 'post', (int) $sid, ZAD_LM_BACKUP ) ) { unset( $map[ $sid ] ); } }
	return $map;
} );
