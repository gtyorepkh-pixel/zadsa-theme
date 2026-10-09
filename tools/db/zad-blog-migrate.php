<?php
/**
 * Zad Saudi — merge the three old post types (الأقسام «sections», الأدلة «guide», مدونة الحشرات «pests-library») into ONE type
 * «zad_blog» served at /blog/{slug}/, 301 every published old URL, move drafts as they are, then retire the old types.
 *   DRY-RUN BY DEFAULT: nothing is written unless ZAD_APPLY=1.
 *
 * Run from the WordPress root, after: (1) a database backup (bash zad-backup.sh), (2) the theme 3.20.2+ is active and
 * mu-plugins/zad-core-blog.php is copied to wp-content/mu-plugins/ (the type «zad_blog» must exist).
 *
 * STAGE 1 — move (default)
 *   wp eval-file zad-blog-migrate.php                          ← report only: counts, every old → new URL, conflicts, menu items, internal links
 *   ZAD_APPLY=1 wp eval-file zad-blog-migrate.php              ← moves the posts (post_type only; slug, date, status, author, terms, meta are untouched),
 *                                                                 creates the 301s (Redirection plugin) or a CSV to import, fixes menu items
 *   extra switches (all optional):
 *     ZAD_RELINK=1        also rewrite links to the old URLs inside content / meta / menus / theme options
 *     ZAD_RESOLVE=suffix  a slug used twice gets «-<oldtype>» (default: that post is NOT moved and is listed)
 *     ZAD_YOAST_FROM=guide copy Yoast's type settings (title / description template, noindex…) of «guide» to «zad_blog»
 *     ZAD_FROM=sections,guide,pests-library   the types to merge
 * STAGE 2 — retire the old types (only after you tested the redirects: bash zad-redirect-check.sh)
 *   ZAD_STAGE=retire wp eval-file zad-blog-migrate.php         ← report: what would be removed
 *   ZAD_STAGE=retire ZAD_APPLY=1 wp eval-file zad-blog-migrate.php
 *     refuses unless: no post of the old types remains (trash / auto-drafts are deleted by the tool), every published post has its 301,
 *     and a live test of up to 20 posts passes (new URL = 200, old URL = 301 → /blog/…). It then deletes the register_post_type() blocks
 *     of the old types from the mu-plugin / theme file that registers them (a .bak copy is kept, the result is syntax-checked), and
 *     flushes the rewrite rules. Run bash zad-redirect-check.sh again afterwards.
 *   ZAD_UNDO=/path/undo-….json wp eval-file zad-blog-migrate.php   ← restores post types / slugs / menus / redirects / files of a stage
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'ZADBLOG_LIB' ) ) { exit( "Run with: wp eval-file zad-blog-migrate.php\n" ); }

/* ============================ helpers ============================ */

function zb_norm( $path ) { $p = rawurldecode( (string) $path ); return '/' === $p ? '/' : rtrim( $p, '/' ); }
function zb_enc( $path ) { return implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ); }

/** Text with every link to an old URL replaced.  $map: normalised old path => new path.  Returns array( newText, count ). */
function zb_relink_text( $text, $map, $bases, $hosts ) {
	if ( ! is_string( $text ) || '' === $text || false === strpos( $text, '/' ) ) { return array( $text, 0 ); }
	$re = '~(?P<host>https?://[^/\s"\'<>)\\\\]+)?(?P<path>/(?:' . implode( '|', array_map( function ( $b ) { return preg_quote( $b, '~' ); }, $bases ) ) . ')(?:/[^\s"\'<>)\]\\\\#?]*)?)~u';
	$n  = 0;
	$out = preg_replace_callback( $re, function ( $m ) use ( $map, $hosts, &$n ) {
		if ( '' !== $m['host'] && ! in_array( strtolower( preg_replace( '#^https?://#i', '', $m['host'] ) ), $hosts, true ) ) { return $m[0]; }
		$k = zb_norm( $m['path'] );
		if ( ! isset( $map[ $k ] ) ) { return $m[0]; }
		$new = $map[ $k ];
		if ( false !== strpos( $m['path'], '%' ) ) { $new = zb_enc( $new ); }
		$new = '/' === substr( $m['path'], -1 ) ? rtrim( $new, '/' ) . '/' : ( '/' === $new ? $new : rtrim( $new, '/' ) ); // keep the original's trailing-slash style
		$n++;
		return $m['host'] . $new;
	}, $text );
	return array( $out, $n );
}

/** Same on any value (string / array, serialized or not). */
function zb_relink_value( $v, $map, $bases, $hosts ) {
	if ( is_array( $v ) ) { $c = 0; foreach ( $v as $k => $x ) { list( $nv, $n ) = zb_relink_value( $x, $map, $bases, $hosts ); $v[ $k ] = $nv; $c += $n; } return array( $v, $c ); }
	if ( is_string( $v ) ) { return zb_relink_text( $v, $map, $bases, $hosts ); }
	return array( $v, 0 );
}

/** register_post_type('<type>', …); statements of the given types in a PHP source → array( newSource, array of removed snippets ). */
function zb_strip_register( $src, $types ) {
	$tok = token_get_all( $src ); $n = count( $tok ); $cut = array(); $removed = array();
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! is_array( $tok[ $i ] ) || T_STRING !== $tok[ $i ][0] || 'register_post_type' !== $tok[ $i ][1] ) { continue; }
		$j = $i + 1; while ( $j < $n && is_array( $tok[ $j ] ) && in_array( $tok[ $j ][0], array( T_WHITESPACE, T_COMMENT ), true ) ) { $j++; }
		if ( '(' !== ( $tok[ $j ] ?? null ) ) { continue; }
		$k = $j + 1; while ( $k < $n && is_array( $tok[ $k ] ) && T_WHITESPACE === $tok[ $k ][0] ) { $k++; }
		if ( ! is_array( $tok[ $k ] ) || T_CONSTANT_ENCAPSED_STRING !== $tok[ $k ][0] ) { continue; }
		$type = trim( $tok[ $k ][1], "'\"" );
		if ( ! in_array( $type, $types, true ) ) { continue; }
		$depth = 0; $e = $j;
		for ( ; $e < $n; $e++ ) { if ( '(' === $tok[ $e ] ) { $depth++; } elseif ( ')' === $tok[ $e ] ) { $depth--; if ( 0 === $depth ) { break; } } }
		$e++; while ( $e < $n && is_array( $tok[ $e ] ) && T_WHITESPACE === $tok[ $e ][0] ) { $e++; }
		if ( ';' === ( $tok[ $e ] ?? null ) ) { $e++; }
		$cut[] = array( $i, $e - 1, $type );
	}
	if ( ! $cut ) { return array( $src, array() ); }
	$out = ''; $from = 0;
	foreach ( $cut as list( $a, $b, $type ) ) {
		$piece = '';
		for ( $x = $a; $x <= $b; $x++ ) { $piece .= is_array( $tok[ $x ] ) ? $tok[ $x ][1] : $tok[ $x ]; }
		$removed[ $type ] = $piece;
		for ( $x = $from; $x < $a; $x++ ) { $out .= is_array( $tok[ $x ] ) ? $tok[ $x ][1] : $tok[ $x ]; }
		$out .= '/* retired: "' . $type . '" was merged into "zad_blog" (/blog/) */';
		$from = $b + 1;
	}
	for ( $x = $from; $x < $n; $x++ ) { $out .= is_array( $tok[ $x ] ) ? $tok[ $x ][1] : $tok[ $x ]; }
	return array( $out, $removed );
}

if ( defined( 'ZADBLOG_LIB' ) ) { return; }

/* ============================ runner ============================ */
global $wpdb;
$apply  = (bool) getenv( 'ZAD_APPLY' );
$stage  = getenv( 'ZAD_STAGE' ) ?: 'move';
$relink = (bool) getenv( 'ZAD_RELINK' );
$resolve = (string) getenv( 'ZAD_RESOLVE' );
$yfrom  = (string) getenv( 'ZAD_YOAST_FROM' );
$undo   = (string) getenv( 'ZAD_UNDO' );
$FROM   = array_filter( array_map( 'trim', explode( ',', (string) ( getenv( 'ZAD_FROM' ) ?: 'sections,guide,pests-library' ) ) ) );
$TO     = 'zad_blog';
$dir    = wp_upload_dir()['basedir'] . '/zad-inventory'; wp_mkdir_p( $dir );
$stamp  = gmdate( 'Ymd-Hi' );
$say    = function ( $s ) { echo $s . "\n"; };

/* ---------- undo ---------- */
if ( '' !== $undo ) {
	$j = json_decode( (string) file_get_contents( $undo ), true );
	if ( ! is_array( $j ) ) { exit( "Undo file not readable: $undo\n" ); }
	$n = 0;
	foreach ( (array) ( $j['types'] ?? array() ) as $id => $t ) { $wpdb->update( $wpdb->posts, array( 'post_type' => $t ), array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); $n++; }
	foreach ( (array) ( $j['slugs'] ?? array() ) as $id => $s ) { $wpdb->update( $wpdb->posts, array( 'post_name' => $s ), array( 'ID' => (int) $id ) ); $n++; }
	foreach ( (array) ( $j['meta'] ?? array() ) as $mid => $v ) { $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $v ), array( 'meta_id' => (int) $mid ) ); $n++; }
	foreach ( (array) ( $j['cols'] ?? array() ) as $id => $cols ) { $wpdb->update( $wpdb->posts, $cols, array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); $n++; }
	foreach ( (array) ( $j['options'] ?? array() ) as $name => $v ) { update_option( $name, maybe_unserialize( $v ) ); $n++; }
	foreach ( (array) ( $j['redirects'] ?? array() ) as $rid ) { $wpdb->delete( $wpdb->prefix . 'redirection_items', array( 'id' => (int) $rid ) ); $n++; }
	foreach ( (array) ( $j['files'] ?? array() ) as $f => $bak ) { if ( is_readable( $bak ) ) { copy( $bak, $f ); $n++; } }
	flush_rewrite_rules( false );
	exit( "Restored $n items from $undo (rewrite rules flushed)\n" );
}

$yoast_reset = function ( $id ) use ( $wpdb ) {
	$t = $wpdb->prefix . 'yoast_indexable';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) { $wpdb->delete( $t, array( 'object_id' => (int) $id, 'object_type' => 'post' ) ); }
};
$base_of = function ( $pt ) { $o = get_post_type_object( $pt ); return ( $o && is_array( $o->rewrite ) && ! empty( $o->rewrite['slug'] ) ) ? trim( $o->rewrite['slug'], '/' ) : $pt; };
$redirection_on = class_exists( 'Red_Item' ) && class_exists( 'Red_Group' );

/* ---------- collect ---------- */
$ph    = implode( ',', array_fill( 0, count( $FROM ), '%s' ) );
$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_name, post_parent, post_title FROM {$wpdb->posts} WHERE post_type IN ($ph) AND post_status NOT IN ('auto-draft','inherit') ORDER BY post_type, ID", $FROM ) );
$left  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($ph) AND post_status NOT IN ('trash','auto-draft','inherit')", $FROM ) );

if ( 'retire' !== $stage ) {
	/* ===================================================== STAGE 1: MOVE ===================================================== */
	if ( ! post_type_exists( $TO ) ) { exit( "The post type «{$TO}» is not registered. Copy mu-plugins/zad-core-blog.php to wp-content/mu-plugins/ (or activate theme 3.20.2+) and run again.\n" ); }
	$new_base = $base_of( $TO );
	$say( ( $apply ? '== APPLY: move ' : '== DRY-RUN: move ' ) . count( $rows ) . " posts from [" . implode( ', ', $FROM ) . "] to «{$TO}» (/$new_base/) ==" );
	$old_bases = array(); foreach ( $FROM as $t ) { $old_bases[ $t ] = $base_of( $t ); }
	$plan = array(); $seen = array(); $conf = array();
	$page_blog = get_page_by_path( 'blog' );
	if ( $page_blog ) { $say( "!! A page with the slug «blog» exists (#{$page_blog->ID}). It would collide with the /blog/ archive: rename its slug first." ); }
	foreach ( $rows as $r ) {
		$url = get_permalink( (int) $r->ID );
		$old = ( $url && false === strpos( $url, '?' ) ) ? zb_norm( wp_parse_url( $url, PHP_URL_PATH ) ) : '';
		if ( '' === $old ) { // the old type is no longer registered: rebuild its path from the base + the slug chain
			$chain = array( $r->post_name ); $p = (int) $r->post_parent; $g = 0;
			while ( $p && $g++ < 10 ) { $chain[] = get_post_field( 'post_name', $p ); $p = (int) get_post_field( 'post_parent', $p ); }
			$old = '/' . $old_bases[ $r->post_type ] . '/' . implode( '/', array_reverse( $chain ) );
		}
		$name = $r->post_name;
		$anc  = array(); foreach ( get_post_ancestors( (int) $r->ID ) as $a ) { $anc[] = get_post_field( 'post_name', $a ); }
		$mk   = function ( $nm ) use ( $new_base, $anc ) { return '/' . $new_base . '/' . implode( '/', array_merge( array_reverse( $anc ), array( $nm ) ) ); };
		$new  = $mk( $name );
		$slugchg = '';
		if ( isset( $seen[ $new ] ) || $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_name=%s AND post_parent=%d AND post_status NOT IN ('trash','auto-draft') LIMIT 1", $TO, $name, (int) $r->post_parent ) ) ) {
			if ( 'suffix' === $resolve ) { $slugchg = $name . '-' . $r->post_type; $name = $slugchg; $new = $mk( $name ); }
			else { $conf[] = array( $r, $new, $seen[ $new ] ?? 'existing' ); continue; }
		}
		$seen[ $new ] = $r->ID;
		$plan[ $r->ID ] = array( 'id' => (int) $r->ID, 'type' => $r->post_type, 'status' => $r->post_status, 'title' => $r->post_title, 'old' => $old, 'new' => $new, 'slug' => $slugchg, 'redirect' => 'publish' === $r->post_status );
	}
	$cnt = array(); foreach ( $plan as $p ) { $cnt[ $p['type'] ][ $p['status'] ] = ( $cnt[ $p['type'] ][ $p['status'] ] ?? 0 ) + 1; }
	foreach ( $cnt as $t => $st ) { $say( "  $t: " . implode( ', ', array_map( function ( $k, $v ) { return "$k=$v"; }, array_keys( $st ), $st ) ) ); }
	$red = array_filter( $plan, function ( $p ) { return $p['redirect']; } );
	$say( '301 redirects (published posts): ' . count( $red ) . ' + old archives: ' . count( $old_bases ) . ' | moved as they are (no redirect): ' . ( count( $plan ) - count( $red ) ) );
	if ( $conf ) {
		$say( "!! Slug used twice — these are NOT moved (re-run with ZAD_RESOLVE=suffix to rename them «slug-<oldtype>», or rename them by hand):" );
		foreach ( $conf as $c ) { $say( "   #{$c[0]->ID} [{$c[0]->post_type}/{$c[0]->post_status}] {$c[1]}  (taken by " . $c[2] . ')' ); }
	}

	/* menus + internal links + Yoast (read first, written below when applying) */
	$ids  = array_keys( $plan );
	$menu = array();
	if ( $ids ) {
		$in = implode( ',', array_map( 'intval', $ids ) );
		$menu = $wpdb->get_results( "SELECT m.meta_id, m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->postmeta} t ON t.post_id=m.post_id AND t.meta_key='_menu_item_type' AND t.meta_value='post_type' JOIN {$wpdb->postmeta} o ON o.post_id=m.post_id AND o.meta_key='_menu_item_object_id' WHERE m.meta_key='_menu_item_object' AND o.meta_value IN ($in)" );
	}
	$arch_menu = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_id, m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->postmeta} t ON t.post_id=m.post_id AND t.meta_key='_menu_item_type' AND t.meta_value='post_type_archive' WHERE m.meta_key='_menu_item_object' AND m.meta_value IN ($ph)", $FROM ) );
	$tx = array();
	if ( $ids ) { $tx = $wpdb->get_results( "SELECT tt.taxonomy, COUNT(DISTINCT tr.term_taxonomy_id) n FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') GROUP BY tt.taxonomy' ); }
	$say( 'classifications used by these posts (kept; attached to «zad_blog» through the option zad_blog_taxonomies): ' . ( $tx ? implode( ', ', array_map( function ( $r ) { return $r->taxonomy . '=' . $r->n . ' terms'; }, $tx ) ) : 'none' ) );
	$say( 'menu items pointing to these posts: ' . count( $menu ) . ' | to the old archives: ' . count( $arch_menu ) . ' (they are re-pointed to the new type)' );

	$map = array(); foreach ( $plan as $p ) { $map[ $p['old'] ] = $p['new']; }
	foreach ( $old_bases as $b ) { $map[ '/' . $b ] = '/' . $new_base . '/'; }
	$hosts = array_unique( array_filter( array( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), 'zadksa.com', 'www.zadksa.com', 'zadksa.local' ) ) );
	$base_list = array_values( $old_bases );
	$link_hits = 0; $link_posts = 0; $relink_cols = array(); $relink_meta = array(); $relink_undo_cols = array(); $relink_undo_meta = array();
	$all = $wpdb->get_results( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_status NOT IN ('auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item','attachment')" );
	foreach ( $all as $p ) {
		$h = 0; $cols = array();
		foreach ( array( 'post_content', 'post_excerpt' ) as $c ) { list( $nv, $n ) = zb_relink_text( $p->$c, $map, $base_list, $hosts ); if ( $n ) { $h += $n; $cols[ $c ] = $nv; } }
		if ( $h ) { $link_hits += $h; $link_posts++; if ( $relink ) { $relink_cols[ $p->ID ] = $cols; $relink_undo_cols[ $p->ID ] = array_intersect_key( array( 'post_content' => $p->post_content, 'post_excerpt' => $p->post_excerpt ), $cols ); } }
	}
	$mrows = $wpdb->get_results( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE (meta_key LIKE '\\_zad\\_%' OR meta_key LIKE '\\_yoast\\_wpseo\\_%' OR meta_key IN ('_menu_item_url')) AND meta_key <> '_zad_faqmig_backup' AND meta_value LIKE '%/%'" );
	foreach ( $mrows as $m ) {
		$v = maybe_unserialize( $m->meta_value ); list( $nv, $n ) = zb_relink_value( $v, $map, $base_list, $hosts );
		if ( $n ) { $link_hits += $n; $link_posts++; if ( $relink ) { $relink_meta[ (int) $m->meta_id ] = is_string( $nv ) ? $nv : maybe_serialize( $nv ); $relink_undo_meta[ (int) $m->meta_id ] = $m->meta_value; } }
	}
	$opt_new = array(); $opt_old = array();
	foreach ( array( '_memo_theme_options' ) as $on ) { $ov = get_option( $on, null ); if ( null === $ov ) { continue; } list( $nv, $n ) = zb_relink_value( $ov, $map, $base_list, $hosts ); if ( $n ) { $link_hits += $n; if ( $relink ) { $opt_new[ $on ] = $nv; $opt_old[ $on ] = maybe_serialize( $ov ); } } }
	$say( "internal links to the old URLs: $link_hits in $link_posts places " . ( $relink ? '(will be rewritten: ZAD_RELINK=1)' : '(left as they are; the 301s cover them. ZAD_RELINK=1 rewrites them)' ) );

	/* Yoast type settings */
	$ti = (array) get_option( 'wpseo_titles', array() );
	if ( $ti ) {
		$say( 'Yoast type settings (title / description templates, noindex):' );
		foreach ( array_merge( $FROM, array( $TO ) ) as $t ) { $say( sprintf( '   %-14s title=%s | desc=%s | noindex=%s', $t, mb_substr( (string) ( $ti[ 'title-' . $t ] ?? '—' ), 0, 40 ), ( isset( $ti[ 'metadesc-' . $t ] ) && '' !== $ti[ 'metadesc-' . $t ] ) ? 'set' : '—', ! empty( $ti[ 'noindex-' . $t ] ) ? 'YES' : 'no' ) ); }
		$say( '   (a post without its own Yoast title uses its type template: pass ZAD_YOAST_FROM=<type> to copy one type\'s settings to zad_blog)' );
	}

	/* redirects: CSV (always) */
	$csv = "$dir/blog-redirects-$stamp.csv"; $fh = fopen( $csv, 'w' ); fwrite( $fh, "\xEF\xBB\xBF" ); fputcsv( $fh, array( 'source', 'target', 'regex', 'code' ) );
	$redirs = array();
	foreach ( $red as $p ) { $redirs[] = array( $p['old'], $p['new'] ); if ( false !== strpos( zb_enc( $p['old'] ), '%' ) ) { $redirs[] = array( zb_enc( $p['old'] ), $p['new'] ); } }
	foreach ( $old_bases as $b ) { $redirs[] = array( '/' . $b . '/', '/' . $new_base . '/' ); }
	foreach ( $redirs as $r2 ) { fputcsv( $fh, array( $r2[0], $r2[1], 0, 301 ) ); }
	fclose( $fh );
	$pl = "$dir/blog-plan-$stamp.csv"; $fh = fopen( $pl, 'w' ); fwrite( $fh, "\xEF\xBB\xBF" ); fputcsv( $fh, array( 'id', 'old_type', 'status', 'title', 'old_path', 'new_path', 'slug_renamed', 'redirect' ) );
	foreach ( $plan as $p ) { fputcsv( $fh, array( $p['id'], $p['type'], $p['status'], $p['title'], $p['old'], $p['new'], $p['slug'], $p['redirect'] ? '301' : '-' ) ); }
	fclose( $fh );
	$say( 'redirect plugin: ' . ( $redirection_on ? 'Redirection found — entries are created in the group «Zad blog migration»' : 'Redirection NOT found — import the CSV (Tools → Import) or install the plugin' ) );
	$say( "Plan CSV: $pl\nRedirects CSV (Redirection import format): $csv" );
	foreach ( array_slice( $plan, 0, 8 ) as $p ) { $say( "   [{$p['status']}] {$p['old']}  →  {$p['new']}" . ( $p['redirect'] ? '  (301)' : '  (no redirect)' ) ); }

	if ( ! $apply ) { $say( "\nTo apply: ZAD_APPLY=1 wp eval-file zad-blog-migrate.php   (add ZAD_RELINK=1 / ZAD_RESOLVE=suffix / ZAD_YOAST_FROM=guide if you want them)" ); exit; }

	/* ---- apply ---- */
	$u = array( 'types' => array(), 'slugs' => array(), 'meta' => array(), 'cols' => array(), 'options' => array(), 'redirects' => array() );
	foreach ( $plan as $p ) {
		$wpdb->update( $wpdb->posts, array( 'post_type' => $TO ), array( 'ID' => $p['id'] ) ); $u['types'][ $p['id'] ] = $p['type'];
		if ( '' !== $p['slug'] ) { $u['slugs'][ $p['id'] ] = get_post_field( 'post_name', $p['id'] ); $wpdb->update( $wpdb->posts, array( 'post_name' => $p['slug'] ), array( 'ID' => $p['id'] ) ); }
		clean_post_cache( $p['id'] ); $yoast_reset( $p['id'] );
		$cv = (string) get_post_meta( $p['id'], '_yoast_wpseo_canonical', true ); // a custom canonical that names the old URL
		if ( '' !== $cv ) { list( $nc, $n ) = zb_relink_text( $cv, $map, $base_list, $hosts ); if ( $n ) { $mid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_canonical'", $p['id'] ) ); $u['meta'][ $mid ] = $cv; $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $nc ), array( 'meta_id' => $mid ) ); } }
	}
	foreach ( $menu as $m ) { $u['meta'][ (int) $m->meta_id ] = get_post_meta( $m->post_id, '_menu_item_object', true ); $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $TO ), array( 'meta_id' => (int) $m->meta_id ) ); }
	foreach ( $arch_menu as $m ) { $u['meta'][ (int) $m->meta_id ] = get_post_meta( $m->post_id, '_menu_item_object', true ); $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $TO ), array( 'meta_id' => (int) $m->meta_id ) ); }
	if ( $relink ) {
		foreach ( $relink_cols as $id => $cols ) { $wpdb->update( $wpdb->posts, $cols, array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); }
		foreach ( $relink_meta as $mid => $v ) { $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $v ), array( 'meta_id' => (int) $mid ) ); }
		foreach ( $opt_new as $name => $v ) { update_option( $name, $v ); }
		$u['cols'] += $relink_undo_cols; $u['meta'] += $relink_undo_meta; $u['options'] += $opt_old;
	}
	if ( '' !== $yfrom && $ti ) {
		$u['options']['wpseo_titles'] = maybe_serialize( $ti ); $new_ti = $ti;
		foreach ( $ti as $k => $v ) { if ( preg_match( '/^(.*)-' . preg_quote( $yfrom, '/' ) . '$/', $k, $mm ) ) { $new_ti[ $mm[1] . '-' . $TO ] = $v; } }
		update_option( 'wpseo_titles', $new_ti ); $say( "Yoast: settings of «$yfrom» copied to «{$TO}»." );
	}
	$u['options']['zad_blog_taxonomies'] = maybe_serialize( get_option( 'zad_blog_taxonomies', array() ) );
	update_option( 'zad_blog_taxonomies', array_values( array_unique( array_merge( (array) get_option( 'zad_blog_taxonomies', array() ), wp_list_pluck( $tx, 'taxonomy' ) ) ) ) );
	/* redirects */
	$made = 0; $viaplugin = false;
	if ( $redirection_on ) {
		try {
			$gt = $wpdb->prefix . 'redirection_groups'; $gid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $gt WHERE name=%s LIMIT 1", 'Zad blog migration' ) );
			if ( ! $gid ) { $g = Red_Group::create( 'Zad blog migration', 1 ); $gid = is_wp_error( $g ) ? 0 : (int) $g->get_id(); }
			if ( $gid ) {
				$it = $wpdb->prefix . 'redirection_items';
				foreach ( $redirs as $r2 ) {
					if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $it WHERE url=%s LIMIT 1", $r2[0] ) ) ) { continue; } // already redirected: never overwrite
					$item = Red_Item::create( array( 'url' => $r2[0], 'match_type' => 'url', 'action_type' => 'url', 'action_code' => 301, 'action_data' => array( 'url' => $r2[1] ), 'group_id' => $gid, 'regex' => false ) );
					if ( ! is_wp_error( $item ) && $item ) { $made++; $u['redirects'][] = (int) $item->get_id(); }
				}
				$viaplugin = true;
			}
		} catch ( \Throwable $e ) { $say( 'Redirection API failed (' . $e->getMessage() . ') — import the CSV instead.' ); }
	}
	$say( $viaplugin ? "Redirection: $made new 301 entries (group «Zad blog migration»)." : "Redirects NOT created in a plugin: import $csv in Redirection (Tools → Import)." );
	flush_rewrite_rules( false );
	$bad = 0; foreach ( $plan as $p ) { $g = wp_parse_url( get_permalink( $p['id'] ), PHP_URL_PATH ); if ( $g && zb_norm( $g ) !== $p['new'] && 'publish' === $p['status'] ) { $bad++; if ( $bad <= 5 ) { $say( "   path differs: #{$p['id']} expected {$p['new']} got " . zb_norm( $g ) ); } } }
	$say( 'moved: ' . count( $plan ) . ( $bad ? " | path mismatches: $bad (check Settings → Permalinks → Save)" : ' | all paths as planned' ) );
	file_put_contents( "$dir/blog-undo-$stamp.json", wp_json_encode( $u, JSON_UNESCAPED_UNICODE ) );
	$say( "UNDO: $dir/blog-undo-$stamp.json\nNext: purge LiteSpeed, Settings → Permalinks → Save, then: bash zad-redirect-check.sh $pl" );
	exit;
}

/* ===================================================== STAGE 2: RETIRE ===================================================== */
$say( ( $apply ? '== APPLY: retire ' : '== DRY-RUN: retire ' ) . 'the old types [' . implode( ', ', $FROM ) . '] ==' );
$problems = array();
if ( $left > 0 ) { $problems[] = "$left post(s) still have an old type (not moved yet: slug conflicts? run stage 1 again)."; }
$trash = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($ph) AND post_status IN ('trash','auto-draft')", $FROM ) );
$say( 'left in the old types: ' . $left . ' live | ' . count( $trash ) . ' in trash / auto-draft (deleted by this stage)' );
$new_pub = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type='zad_blog' AND post_status='publish' ORDER BY ID" );
$say( 'published posts in «zad_blog»: ' . count( $new_pub ) );
if ( $redirection_on ) {
	$it = $wpdb->prefix . 'redirection_items'; $have = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $it WHERE action_code=301 AND action_data LIKE '%/blog/%'" );
	$say( "301 entries to /blog/ in Redirection: $have" );
	if ( $have < count( $new_pub ) && ! getenv( 'ZAD_FORCE' ) ) { $problems[] = "Redirection has $have entries for " . count( $new_pub ) . ' published posts.'; }
} elseif ( ! getenv( 'ZAD_ACK_REDIRECTS' ) ) { $problems[] = 'Redirection plugin not found: set ZAD_ACK_REDIRECTS=1 only after you imported the CSV and tested.'; }
/* live sample */
$sample = array(); foreach ( array_slice( $new_pub, 0, 20 ) as $p ) { $sample[] = $p; }
$say( 'live test of ' . count( $sample ) . ' posts:' );
$bad = 0;
foreach ( $sample as $p ) {
	$u1 = get_permalink( (int) $p->ID ); $r1 = wp_remote_get( $u1, array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => false ) );
	$c1 = is_wp_error( $r1 ) ? 0 : (int) wp_remote_retrieve_response_code( $r1 );
	if ( 200 !== $c1 ) { $bad++; $say( "   ✗ new URL $u1 → HTTP $c1" ); }
}
$say( $bad ? "   $bad of " . count( $sample ) . ' new URLs do not answer 200.' : '   ✓ all new URLs answer 200 (the old URLs are tested by zad-redirect-check.sh)' );
if ( $bad && ! getenv( 'ZAD_FORCE' ) ) { $problems[] = 'the live test failed.'; }
/* files that register the old types */
$roots = array_unique( array_filter( array( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins', get_template_directory(), get_stylesheet_directory() ) ) );
$edits = array();
foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) { continue; }
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
		if ( 'php' !== $f->getExtension() || false !== strpos( $f->getPathname(), '/tools/' ) || false !== strpos( $f->getPathname(), '.bak-' ) ) { continue; }
		$src = (string) file_get_contents( $f->getPathname() );
		if ( false === strpos( $src, 'register_post_type' ) ) { continue; }
		list( $new, $rm ) = zb_strip_register( $src, $FROM );
		if ( $rm ) {
			try { token_get_all( $new, TOKEN_PARSE ); } catch ( \Throwable $e ) { $problems[] = 'removing from ' . $f->getPathname() . ' would break the file (' . $e->getMessage() . ') — edit it by hand.'; continue; }
			$edits[ $f->getPathname() ] = array( $new, $rm );
		}
	}
}
foreach ( $edits as $path => $e ) { $say( "will edit: $path — removes register_post_type for: " . implode( ', ', array_keys( $e[1] ) ) ); }
$found = array(); foreach ( $edits as $e ) { $found = array_merge( $found, array_keys( $e[1] ) ); }
$notfound = array_diff( $FROM, $found );
if ( $notfound ) { $say( 'not found in mu-plugins / theme files: ' . implode( ', ', $notfound ) . ' — registered somewhere else (a plugin such as Custom Post Type UI?): remove them there by hand.' ); }
foreach ( array( 'cptui_post_types' ) as $on ) { $ov = get_option( $on, null ); if ( is_array( $ov ) ) { $hit = array_intersect( array_keys( $ov ), $FROM ); if ( $hit ) { $say( "option $on holds: " . implode( ', ', $hit ) . ' (removed by this stage)' ); } } }
if ( $problems ) { $say( "\n✗ Not ready:\n  - " . implode( "\n  - ", $problems ) ); if ( $apply ) { exit( "Nothing was changed.\n" ); } }
if ( ! $apply ) { $say( "\nTo apply: ZAD_STAGE=retire ZAD_APPLY=1 wp eval-file zad-blog-migrate.php" ); exit; }

$u = array( 'files' => array(), 'options' => array() );
foreach ( $trash as $id ) { wp_delete_post( (int) $id, true ); }
foreach ( $edits as $path => $e ) { $bak = $path . '.bak-' . $stamp; copy( $path, $bak ); file_put_contents( $path, $e[0] ); $u['files'][ $path ] = $bak; $say( "edited $path (backup: $bak)" ); }
foreach ( array( 'cptui_post_types' ) as $on ) { $ov = get_option( $on, null ); if ( is_array( $ov ) && array_intersect( array_keys( $ov ), $FROM ) ) { $u['options'][ $on ] = maybe_serialize( $ov ); foreach ( $FROM as $t ) { unset( $ov[ $t ] ); } update_option( $on, $ov ); } }
flush_rewrite_rules( false );
file_put_contents( "$dir/blog-retire-undo-$stamp.json", wp_json_encode( $u, JSON_UNESCAPED_UNICODE ) );
$say( "Old types retired. UNDO: $dir/blog-retire-undo-$stamp.json\nNow: purge LiteSpeed, Settings → Permalinks → Save, and run bash zad-redirect-check.sh again (old URLs must still answer 301)." );
