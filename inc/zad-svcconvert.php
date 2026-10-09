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
 * v2 (see the section «v2» below): each row has an ACTION — none / zad_service / cleaning / pest_control / delete-with-redirect (to the trash, never permanent) — every 301
 * is written to the Redirection plugin (group «تحويل الخدمات»; the run is refused when it is not active), nothing is duplicated and no chain is ever created,
 * a preview screen precedes the run, and «تراجع عن الكل» reverts every part (including pages taken out of the trash).
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


/* ======================================================================================================
 * v2 — one tool, five actions per row (none / zad_service / cleaning / pest_control / delete-with-redirect),
 * every 301 in the Redirection plugin (group «تحويل الخدمات»), a preview screen, and one undo for everything.
 * ====================================================================================================== */

/* ---------------- actions + destinations ---------------- */
function zad_svcc_actions() {
	return array(
		'none'    => 'لا شيء',
		'service' => 'نقل إلى الخدمات (zad_service)',
		'cleaning' => 'نقل إلى صفحات التنظيف (cleaning)',
		'pest'    => 'نقل إلى مكافحة الحشرات (pest_control)',
		'trash'   => 'حذف مع تحويل',
	);
}
/** Destination definition of a move action. */
function zad_svcc_dest( $act ) {
	$d = array(
		'service'  => array( 'type' => 'zad_service', 'tax' => 'service_cat', 'area' => 'service_area', 'hier' => false ),
		'cleaning' => array( 'type' => 'cleaning', 'tax' => 'cleaning-sections', 'area' => '', 'hier' => true ),
		'pest'     => array( 'type' => 'pest_control', 'tax' => 'pest_sections', 'area' => '', 'hier' => true ),
	);
	return $d[ $act ] ?? null;
}
function zad_svcc_dest_taxes() { return array( 'service_cat', 'service_area', 'cleaning-sections', 'pest_sections' ); }
function zad_svcc_target_types() { return array_values( array_filter( array( 'zad_service', 'cleaning', 'pest_control', 'page' ), 'post_type_exists' ) ); }

/* ---------------- suggestions (only suggestions: the owner changes them before running) ---------------- */
function zad_svcc_suggest_action( $post, $cat_names ) {
	if ( 'post' === $post->post_type && ! zad_svcc_suggest_selected( $post, $cat_names ) ) { return 'none'; }
	$t = (string) $post->post_title . ' ' . implode( ' ', $cat_names );
	$rules = apply_filters( 'zad_svcc_action_rules', array(
		'service'  => '/تركيب\s+(?:ال)?مكيفات|فريون|أفران|افران/u',
		'pest'     => '/مكافحة|حشرات|صراصير|صرصور|(?:^|\s)بق(?:\s|$)|نمل|فئران|فأر|(?:^|\s)رش(?:\s|$)|مبيد|طارد\s+الحمام/u',
		'cleaning' => '/تنظيف|غسيل|خزانات|خزان|كنب|مجالس|موكيت|ستائر|مسابح|مسبح/u',
		'service2' => '/نقل|عفش|تخزين|جلي|تسليك|مجاري|بيارات/u',
	) );
	foreach ( array( 'service' => 'service', 'pest' => 'pest', 'cleaning' => 'cleaning', 'service2' => 'service' ) as $k => $act ) { if ( isset( $rules[ $k ] ) && preg_match( $rules[ $k ], $t ) ) { return $act; } }
	return 'service'; // a «خدمات …» page with no keyword keeps the old behaviour
}

/** Arabic text for matching: no tashkeel, hamza forms → ا, leading «ال» dropped. */
function zad_svcc_norm( $s ) {
	$s = preg_replace( '/[\x{064B}-\x{065F}\x{0640}]/u', '', (string) $s );
	$s = str_replace( array( 'أ', 'إ', 'آ', 'ة', 'ى' ), array( 'ا', 'ا', 'ا', 'ه', 'ي' ), $s );
	$o = array();
	foreach ( preg_split( '/[\s\-–—_،,.:؛;()«»"\'\/|]+/u', trim( mb_strtolower( $s ) ), -1, PREG_SPLIT_NO_EMPTY ) as $w ) { $o[] = ( 0 === mb_strpos( $w, 'ال' ) && mb_strlen( $w ) > 3 ) ? mb_substr( $w, 2 ) : $w; }
	return $o;
}
/** Significant words of a title (stop words out). */
function zad_svcc_tokens( $title ) {
	$stop = array( 'شركه', 'مؤسسه', 'موسسه', 'في', 'ب', 'بال', 'و', 'من', 'الى', 'على', 'افضل', 'خدمه', 'خدمات', 'اسعار', 'سعر', 'رخيص', 'رخيصه', 'ارخص', 'احسن', 'جوده', 'مع', 'عن', 'لل', 'ل' );
	$t = array();
	foreach ( zad_svcc_norm( $title ) as $w ) {
		$w = preg_replace( '/^(?:ب|ل|و)(?=.{3,})/u', '', $w ); // بالدمام → الدمام
		if ( 0 === mb_strpos( $w, 'ال' ) && mb_strlen( $w ) > 3 ) { $w = mb_substr( $w, 2 ); } // الدمام → دمام
		if ( mb_strlen( $w ) > 1 && ! in_array( $w, $stop, true ) ) { $t[ $w ] = 1; }
	}
	return array_keys( $t );
}
/** How well a candidate title fits the source title: shared significant words (+1 when both name the same city). */
function zad_svcc_score( $src_title, $cand_title, $city = '' ) {
	$a = zad_svcc_tokens( $src_title ); $b = zad_svcc_tokens( $cand_title ); $s = count( array_intersect( $a, $b ) );
	if ( '' !== $city ) { $c = zad_svcc_norm( $city ); if ( $c && count( array_intersect( $c, $b ) ) === count( $c ) ) { $s += 1; } }
	return $s;
}
/** Best candidate among posts (id, title) with a score of at least 2, else 0. */
function zad_svcc_best( $src_title, $cands, $city = '' ) {
	$best = 0; $bs = 1;
	foreach ( $cands as $id => $title ) { $s = zad_svcc_score( $src_title, $title, $city ); if ( $s > $bs ) { $bs = $s; $best = (int) $id; } }
	return $best;
}
function zad_svcc_candidates( $types, $exclude = 0, $parents_only = false ) {
	$a = array( 'post_type' => $types, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true, 'fields' => 'ids' );
	if ( $parents_only ) { $a['post_parent'] = 0; }
	$o = array();
	foreach ( get_posts( $a ) as $id ) { if ( (int) $id !== (int) $exclude ) { $o[ (int) $id ] = get_the_title( $id ); } }
	return $o;
}

/* ---------------- «تجربة حقول زاد» drafts (read-only) ---------------- */
/**
 * source post id => array( 'new' => id|0, 'title' => text ), read from mu-plugins/zad-lab/*.json (any JSON whose objects carry a source_id; the new page is found by
 * post_id / new_id / draft_id / id / target_id in the same object, else by its title, else by a meta key that holds the source id). Filter: zad_svcc_lab_map.
 */
function zad_svcc_lab_map( $reset = false ) {
	static $cache = null;
	if ( $reset ) { $cache = null; }
	if ( null !== $cache ) { return $cache; }
	$out = array();
	$dir = trailingslashit( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ) . 'zad-lab';
	$walk = function ( $x ) use ( &$walk, &$out ) {
		if ( ! is_array( $x ) ) { return; }
		if ( isset( $x['source_id'] ) && is_numeric( $x['source_id'] ) ) {
			$new = 0; foreach ( array( 'post_id', 'new_id', 'draft_id', 'target_id', 'id' ) as $k ) { if ( ! empty( $x[ $k ] ) && is_numeric( $x[ $k ] ) && (int) $x[ $k ] !== (int) $x['source_id'] ) { $new = (int) $x[ $k ]; break; } }
			$out[ (int) $x['source_id'] ] = array( 'new' => $new, 'title' => isset( $x['title'] ) ? (string) $x['title'] : '' );
		}
		foreach ( $x as $v ) { if ( is_array( $v ) ) { $walk( $v ); } }
	};
	foreach ( (array) glob( $dir . '/*.json' ) as $f ) { $j = json_decode( (string) file_get_contents( $f ), true ); if ( is_array( $j ) ) { $walk( $j ); } }
	global $wpdb;
	foreach ( $out as $sid => $info ) {
		if ( $info['new'] && get_post( $info['new'] ) ) { continue; }
		$info['new'] = 0;
		$m = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_zad_lab_source_id','_zad_lab_source','zad_lab_source_id','_source_id') AND meta_value = %s LIMIT 1", (string) $sid ) ); // phpcs:ignore
		if ( $m ) { $info['new'] = (int) $m; }
		elseif ( '' !== $info['title'] ) {
			$q = get_posts( array( 'post_type' => zad_svcc_target_types(), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'title' => $info['title'], 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
			if ( $q ) { $info['new'] = (int) $q[0]; }
		}
		$out[ $sid ] = $info;
	}
	$cache = apply_filters( 'zad_svcc_lab_map', $out );
	return $cache;
}

/* ---------------- Redirection plugin: every 301 lives there ---------------- */
function zad_svcc_red_active() { return class_exists( 'Red_Item' ) && class_exists( 'Red_Group' ); }

function zad_svcc_red_group( $name = 'تحويل الخدمات' ) {
	if ( ! class_exists( 'Red_Group' ) ) { return 0; }
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}redirection_groups WHERE name = %s LIMIT 1", $name ) ); // phpcs:ignore
	if ( $id ) { return $id; }
	try { $g = Red_Group::create( $name, 1 ); } catch ( \Throwable $e ) { return 0; }
	return ( is_object( $g ) && method_exists( $g, 'get_id' ) ) ? (int) $g->get_id() : 0;
}
/** Add one 301. Returns the Redirection item id (0 on failure). */
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
/** The spellings under which Redirection may hold a path (with / without the trailing slash, raw / percent-encoded Arabic, lower case). */
function zad_svcc_red_variants( $path ) {
	$p = trim( rawurldecode( (string) $path ), '/' );
	$enc = implode( '/', array_map( 'rawurlencode', explode( '/', $p ) ) );
	$v = array();
	foreach ( array( $p, mb_strtolower( $p ), $enc, strtolower( $enc ) ) as $x ) { $v[] = '/' . $x; $v[] = '/' . $x . '/'; }
	return array_values( array_unique( $v ) );
}
/** The target URL stored in a Redirection row (plain string, serialized array or JSON). */
function zad_svcc_red_target( $row ) {
	$d = (string) ( $row['action_data'] ?? '' );
	if ( '' !== $d && ( 'a:' === substr( $d, 0, 2 ) ) ) { $u = @unserialize( $d ); if ( is_array( $u ) ) { return (string) ( $u['url'] ?? '' ); } } // phpcs:ignore
	if ( '' !== $d && '{' === $d[0] ) { $j = json_decode( $d, true ); if ( is_array( $j ) ) { return (string) ( $j['url'] ?? '' ); } }
	return $d;
}
/** An existing redirect FROM this path (any group, any status) → array( id, group, to, code, status, regex ) or null. Regex rules that match the path count too. */
function zad_svcc_red_lookup( $path ) {
	if ( ! zad_svcc_red_active() ) { return null; }
	global $wpdb;
	$t = $wpdb->prefix . 'redirection_items';
	$vars = zad_svcc_red_variants( $path );
	$ph = implode( ',', array_fill( 0, count( $vars ), '%s' ) );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, group_id, action_data, action_code, action_type, status, regex FROM $t WHERE regex = 0 AND ( url IN ($ph) OR match_url IN ($ph) ) ORDER BY id ASC LIMIT 1", array_merge( $vars, $vars ) ), ARRAY_A ); // phpcs:ignore
	if ( $row ) { return array( 'id' => (int) $row['id'], 'group' => (int) $row['group_id'], 'to' => zad_svcc_red_target( $row ), 'code' => (int) $row['action_code'], 'status' => $row['status'], 'regex' => false, 'type' => $row['action_type'] ); }
	$rx = (array) $wpdb->get_results( "SELECT id, group_id, url, action_data, action_code, action_type, status FROM $t WHERE regex = 1 AND status = 'enabled' LIMIT 500", ARRAY_A ); // phpcs:ignore
	$subject = '/' . trim( rawurldecode( (string) $path ), '/' ) . '/';
	foreach ( $rx as $r ) { if ( '' !== $r['url'] && @preg_match( '@' . str_replace( '@', '\@', $r['url'] ) . '@i', $subject ) ) { return array( 'id' => (int) $r['id'], 'group' => (int) $r['group_id'], 'to' => zad_svcc_red_target( $r ), 'code' => (int) $r['action_code'], 'status' => $r['status'], 'regex' => true, 'type' => $r['action_type'] ); } }
	return null;
}
/** Follow existing URL redirects from $url until a page that is not redirected: array( final_url, hops[], loop(bool) ). Never creates a chain. */
function zad_svcc_red_final( $url ) {
	$hops = array(); $seen = array(); $cur = $url;
	for ( $i = 0; $i < 10; $i++ ) {
		$k = zad_svcc_path_key( $cur );
		if ( isset( $seen[ $k ] ) ) { return array( $cur, $hops, true ); }
		$seen[ $k ] = 1;
		$r = zad_svcc_red_lookup( $k );
		if ( ! $r || 'url' !== $r['type'] || '' === $r['to'] || $r['regex'] || 'enabled' !== $r['status'] ) { return array( $cur, $hops, false ); }
		$hops[] = array( $k, $r['to'] );
		$cur = 0 === strpos( $r['to'], '/' ) && 0 !== strpos( $r['to'], '//' ) ? home_url( $r['to'] ) : $r['to'];
	}
	return array( $cur, $hops, true );
}
/** Redirects that already POINT to this URL (they would become a chain once it is redirected). */
function zad_svcc_red_inbound( $url ) {
	if ( ! zad_svcc_red_active() ) { return array(); }
	global $wpdb;
	$t = $wpdb->prefix . 'redirection_items';
	$k = zad_svcc_path_key( $url ); $out = array();
	$like = '%' . $wpdb->esc_like( rawurldecode( $k ) ) . '%';
	$likeE = '%' . $wpdb->esc_like( implode( '/', array_map( 'rawurlencode', explode( '/', $k ) ) ) ) . '%';
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, url, action_data FROM $t WHERE action_type = 'url' AND ( action_data LIKE %s OR action_data LIKE %s ) LIMIT 50", $like, $likeE ), ARRAY_A ) as $r ) { // phpcs:ignore
		if ( zad_svcc_path_key( zad_svcc_red_target( $r ) ) === $k ) { $out[] = array( (int) $r['id'], (string) $r['url'] ); }
	}
	return $out;
}

/* ---------------- data ---------------- */
function zad_svcc_sources() {
	return get_posts( array( 'post_type' => zad_svcc_source_types(), 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
}

function zad_svcc_old_terms( $post ) {
	$taxes = 'post' === $post->post_type ? array( 'category' ) : array_diff( get_object_taxonomies( $post->post_type ), array_merge( array( 'post_format' ), zad_svcc_dest_taxes() ) );
	if ( ! $taxes ) { return array(); }
	$t = wp_get_object_terms( $post->ID, $taxes );
	return is_wp_error( $t ) ? array() : $t;
}

function zad_svcc_new_url( $slug ) { return home_url( '/' . zad_type_base( 'zad_service' ) . '/' . rawurlencode( urldecode( $slug ) ) . '/' ); }

/** Final URL of a page of $type under $parent (hierarchical types: /base/parent/…/slug/). */
function zad_svcc_dest_url( $type, $parent, $slug ) {
	$segs = array( urldecode( $slug ) ); $guard = 0;
	if ( is_post_type_hierarchical( $type ) ) {
		$p = $parent ? get_post( (int) $parent ) : null;
		while ( $p && $guard++ < 12 ) { array_unshift( $segs, urldecode( $p->post_name ) ); $p = $p->post_parent ? get_post( (int) $p->post_parent ) : null; }
	}
	return home_url( '/' . zad_type_base( $type ) . '/' . implode( '/', array_map( 'rawurlencode', $segs ) ) . '/' );
}

function zad_svcc_slug_default( $post ) {
	$map = apply_filters( 'zad_svcc_slug_map', array() );
	$cur = urldecode( $post->post_name );
	return $map[ $post->post_type ][ $cur ] ?? $cur;
}

/* ---------------- the plan: what WOULD happen, row by row (nothing is changed here) ---------------- */
function zad_svcc_row_defaults( $r ) {
	return array(
		'act' => isset( zad_svcc_actions()[ $r['act'] ?? 'none' ] ) ? $r['act'] : 'none', 'slug' => trim( (string) ( $r['slug'] ?? '' ) ), 'sec' => trim( (string) ( $r['sec'] ?? '' ) ),
		'city' => trim( (string) ( $r['city'] ?? '' ) ), 'parent' => (int) ( $r['parent'] ?? 0 ), 'to' => trim( (string) ( $r['to'] ?? '' ) ),
	);
}

/** Resolve the «redirect to» field of a delete row: a post id or an internal URL/path → array( url, post_id ) or a string with the reason it cannot be used. */
function zad_svcc_resolve_target( $to, $self_id, $planned, $trashing ) {
	$to = trim( (string) $to );
	if ( '' === $to ) { return 'اختر الصفحة التي يُحوَّل إليها (ممنوع الحذف بلا تحويل).'; }
	if ( ctype_digit( $to ) ) {
		$id = (int) $to; $p = get_post( $id );
		if ( ! $p || in_array( $p->post_type, array( 'revision', 'nav_menu_item', 'attachment' ), true ) ) { return 'وجهة التحويل غير موجودة (#' . $id . ').'; }
		if ( $id === (int) $self_id ) { return 'لا يمكن التحويل إلى المقالة نفسها.'; }
		if ( isset( $trashing[ $id ] ) ) { return 'وجهة التحويل (#' . $id . ') سيُحذف هي الأخرى في نفس التنفيذ.'; }
		if ( 'publish' !== $p->post_status ) { return 'وجهة التحويل غير منشورة (#' . $id . ' · ' . $p->post_status . ').'; }
		return array( $planned[ $id ] ?? get_permalink( $id ), $id );
	}
	$u = 0 === strpos( $to, '/' ) && 0 !== strpos( $to, '//' ) ? home_url( $to ) : $to;
	if ( 0 !== strpos( $u, home_url( '/' ) ) ) { return 'الوجهة يجب أن تكون صفحة منشورة داخل الموقع.'; }
	$id = url_to_postid( $u );
	if ( ! $id || 'publish' !== get_post_status( $id ) ) { return 'لا توجد صفحة منشورة بهذا الرابط.'; }
	if ( (int) $id === (int) $self_id ) { return 'لا يمكن التحويل إلى المقالة نفسها.'; }
	if ( isset( $trashing[ (int) $id ] ) ) { return 'وجهة التحويل ستُحذف هي الأخرى في نفس التنفيذ.'; }
	return array( $planned[ (int) $id ] ?? get_permalink( $id ), (int) $id );
}

/**
 * $rows = array( post_id => array( act, slug, sec, city, parent, to ) ). Returns array( 'rows' => array( id => result ), 'counts' => array, 'redirs' => array( array( from, to, state, id ) ), 'problems' => array ).
 * result = array( id, title, act, status ok|skip|none, why, warn[], old_url, new_url, dest, slug, parent, target, redir => array( from, to, state, existing ), lab )
 */
function zad_svcc_plan( $rows ) {
	global $wpdb;
	$types = zad_svcc_source_types(); $lab = zad_svcc_lab_map();
	$planned = array(); $trashing = array(); $in = array();
	foreach ( $rows as $id => $r ) {
		$id = (int) $id; $r = zad_svcc_row_defaults( $r ); $p = get_post( $id ); $in[ $id ] = $r;
		if ( ! $p ) { continue; }
		if ( 'trash' === $r['act'] ) { $trashing[ $id ] = 1; continue; }
		$d = zad_svcc_dest( $r['act'] );
		if ( $d && post_type_exists( $d['type'] ) ) {
			$slug = $r['slug'] === urldecode( $p->post_name ) ? $p->post_name : sanitize_title( $r['slug'] );
			if ( '' !== $slug ) { $planned[ $id ] = zad_svcc_dest_url( $d['type'], $d['hier'] ? $r['parent'] : 0, $slug ); }
		}
	}
	$out = array( 'rows' => array(), 'counts' => array_fill_keys( array_keys( zad_svcc_actions() ), 0 ), 'redirs' => array(), 'problems' => array(), 'skipped' => 0 );
	$taken = array(); $redir_seen = array();
	foreach ( $in as $id => $r ) {
		$p = get_post( $id );
		$res = array( 'id' => $id, 'title' => $p ? $p->post_title : '#' . $id, 'act' => $r['act'], 'status' => 'none', 'why' => '', 'warn' => array(), 'old_url' => $p ? get_permalink( $p ) : '', 'new_url' => '', 'dest' => '', 'slug' => $r['slug'], 'parent' => $r['parent'], 'target' => 0, 'redir' => null, 'lab' => $lab[ $id ] ?? null );
		if ( 'none' === $r['act'] ) { $out['rows'][ $id ] = $res; $out['counts']['none']++; continue; }
		$skip = function ( $why ) use ( &$res, &$out ) { $res['status'] = 'skip'; $res['why'] = $why; $out['skipped']++; };
		if ( ! $p || ! in_array( $p->post_type, $types, true ) ) { $skip( 'ليس من أنواع المصدر' ); $out['rows'][ $id ] = $res; continue; }
		if ( 'trash' === $p->post_status ) { $skip( 'في سلة المهملات أصلاً' ); $out['rows'][ $id ] = $res; continue; }
		$old_key = zad_svcc_path_key( $res['old_url'] );

		if ( 'trash' === $r['act'] ) {
			if ( ! defined( 'EMPTY_TRASH_DAYS' ) || (int) EMPTY_TRASH_DAYS < 1 ) { $skip( 'سلة المهملات معطّلة في الموقع (EMPTY_TRASH_DAYS=0): الحذف سيكون نهائياً فلا يُنفَّذ' ); $out['rows'][ $id ] = $res; continue; }
			$t = zad_svcc_resolve_target( $r['to'], $id, $planned, $trashing );
			if ( is_string( $t ) ) {
				$lb = $res['lab'];
				if ( $lb && $lb['new'] && ctype_digit( $r['to'] ) && (int) $r['to'] === (int) $lb['new'] && get_post( $lb['new'] ) && 'publish' !== get_post_status( $lb['new'] ) ) { $t = 'الصفحة الجديدة في «تجربة حقول زاد» (#' . $lb['new'] . ') ما زالت ' . get_post_status( $lb['new'] ) . ' — انشرها أولاً ثم نفّذ.'; }
				$skip( $t ); $out['rows'][ $id ] = $res; continue;
			}
			list( $to_url, $tid ) = $t; $res['target'] = $tid;
			list( $final, $hops, $loop ) = zad_svcc_red_final( $to_url );
			if ( $loop ) { $skip( 'وجهة التحويل داخلة في حلقة تحويلات قائمة' ); $out['rows'][ $id ] = $res; continue; }
			if ( $hops ) { $res['warn'][] = 'وجهة التحويل نفسها محوَّلة، فاستُخدمت وجهتها النهائية مباشرة (بلا سلسلة).'; }
			if ( zad_svcc_path_key( $final ) === $old_key ) { $skip( 'الوجهة النهائية هي نفس الرابط القديم' ); $out['rows'][ $id ] = $res; continue; }
			$res['new_url'] = $final; $res['dest'] = 'trash';
		} else {
			$d = zad_svcc_dest( $r['act'] );
			if ( ! $d || ! post_type_exists( $d['type'] ) ) { $skip( 'نوع الوجهة غير مسجّل في الموقع' ); $out['rows'][ $id ] = $res; continue; }
			$slug = $r['slug'] === urldecode( $p->post_name ) ? $p->post_name : sanitize_title( $r['slug'] );
			if ( '' === $slug ) { $skip( 'رابط فارغ' ); $out['rows'][ $id ] = $res; continue; }
			$parent = 0;
			if ( $d['hier'] && $r['parent'] ) {
				$pp = get_post( $r['parent'] );
				if ( ! $pp || $pp->post_type !== $d['type'] ) { $skip( 'الصفحة الأم يجب أن تكون من نوع ' . $d['type'] ); $out['rows'][ $id ] = $res; continue; }
				if ( 'trash' === $pp->post_status || (int) $pp->ID === $id ) { $skip( 'الصفحة الأم غير صالحة' ); $out['rows'][ $id ] = $res; continue; }
				if ( 'publish' !== $pp->post_status ) { $res['warn'][] = 'الصفحة الأم غير منشورة (' . $pp->post_status . ').'; }
				$parent = (int) $pp->ID;
			}
			$clash = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_parent = %d AND post_status NOT IN ('trash','auto-draft') AND ID <> %d LIMIT 1", $d['type'], $slug, $parent, $id ) ); // phpcs:ignore
			$new_url = zad_svcc_dest_url( $d['type'], $parent, $slug );
			$tk = zad_svcc_path_key( $new_url );
			if ( $clash ) { $skip( 'رابط مكرر: ' . urldecode( $new_url ) . ' موجود (#' . $clash . ')' ); $out['rows'][ $id ] = $res; continue; }
			if ( isset( $taken[ $tk ] ) ) { $skip( 'رابط مكرر مع صف آخر في نفس التنفيذ (#' . $taken[ $tk ] . ')' ); $out['rows'][ $id ] = $res; continue; }
			$taken[ $tk ] = $id; $res['dest'] = $d['type']; $res['slug'] = $slug; $res['parent'] = $parent;
			list( $final, $hops, $loop ) = zad_svcc_red_final( $new_url );
			if ( $loop ) { $skip( 'الرابط الجديد داخل حلقة تحويلات قائمة' ); $out['rows'][ $id ] = $res; continue; }
			if ( $hops ) { $res['warn'][] = 'الرابط الجديد عليه تحويل قائم إلى ' . urldecode( $final ) . ' — حُوِّل الرابط القديم مباشرة للوجهة النهائية، لكن الصفحة الجديدة لن تظهر على رابطها حتى يُحذف ذلك التحويل من Redirection.'; }
			$res['new_url'] = $new_url; $res['final'] = $final;
		}
		// the 301 of the old URL
		$to_url = 'trash' === $r['act'] ? $res['new_url'] : ( $res['final'] ?? $res['new_url'] );
		$res['redir'] = array( 'from' => $old_key, 'to' => $to_url, 'state' => 'add', 'existing' => null );
		if ( zad_svcc_path_key( $to_url ) === $old_key ) { $res['redir']['state'] = 'same'; }
		else {
			$ex = zad_svcc_red_lookup( $old_key );
			if ( $ex ) { $res['redir']['state'] = 'exists'; $res['redir']['existing'] = $ex; $res['warn'][] = 'يوجد تحويل على الرابط القديم في Redirection (#' . $ex['id'] . ' ← ' . ( '' !== $ex['to'] ? urldecode( $ex['to'] ) : '؟' ) . ( $ex['regex'] ? '، قاعدة Regex' : '' ) . ') فلن يُضاف تحويل مكرر' . ( zad_svcc_path_key( (string) $ex['to'] ) !== zad_svcc_path_key( $to_url ) ? ' — وهو يذهب لوجهة مختلفة عن المتوقعة' : '' ) . '.'; }
			elseif ( isset( $redir_seen[ $old_key ] ) ) { $res['redir']['state'] = 'exists'; }
			$redir_seen[ $old_key ] = 1;
		}
		$inb = zad_svcc_red_inbound( $res['old_url'] );
		if ( $inb ) { $res['warn'][] = 'تحويلات سابقة تشير للرابط القديم وستصير سلسلة (#' . implode( '، #', array_column( $inb, 0 ) ) . ') — حدّث وجهتها في Redirection.'; }
		if ( ! empty( $res['lab'] ) && 'trash' !== $r['act'] ) { $res['warn'][] = 'لها مسودة جديدة في «تجربة حقول زاد»' . ( $res['lab']['new'] ? ' (#' . $res['lab']['new'] . ')' : '' ) . ' — هل المطلوب «حذف مع تحويل» لها؟'; }
		$res['status'] = 'ok'; $out['counts'][ $r['act'] ]++;
		if ( 'same' !== $res['redir']['state'] ) { $out['redirs'][] = array( 'from' => $old_key, 'to' => $to_url, 'state' => $res['redir']['state'], 'id' => $id ); }
		$out['rows'][ $id ] = $res;
	}
	foreach ( $out['rows'] as $res ) { if ( 'skip' === $res['status'] ) { $out['problems'][ $res['id'] ] = $res['why']; } }
	return $out;
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
/** Term id (created when missing). New terms are logged as array( taxonomy, id ) so the undo knows where to look. */
function zad_svcc_term_id( $name, $tax, $parent = 0, &$created = array() ) {
	$name = trim( $name );
	if ( '' === $name || ! taxonomy_exists( $tax ) ) { return 0; }
	$ex = term_exists( $name, $tax, $parent ?: null );
	if ( ! $ex ) { $ex = term_exists( $name, $tax ); }
	if ( ! $ex ) {
		$ex = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
		if ( is_wp_error( $ex ) ) { return 0; }
		$created[] = array( $tax, (int) $ex['term_id'] );
	}
	return (int) ( is_array( $ex ) ? $ex['term_id'] : $ex );
}

function zad_svcc_log_get() {
	$log = get_option( 'zad_svcc_log' );
	$def = array( 'items' => array(), 'created' => array(), 'red' => array(), 'map' => array(), 'links' => array(), 'menu' => array(), 'skipped' => array(), 'trash' => array() );
	return is_array( $log ) ? array_merge( $def, $log ) : $def;
}

/**
 * Execute the rows (re-planned here: the plan is authoritative, what the screen showed is not trusted). Refuses to start when Redirection is not active.
 * Everything done is written to zad_svcc_log so «تراجع عن الكل» can revert it.
 */
function zad_svcc_run( $rows, $freeze ) {
	global $wpdb;
	$report = array( 'error' => '', 'done' => 0, 'moved' => 0, 'trashed' => 0, 'skip' => array(), 'urlbad' => array(), 'links' => 0, 'red_added' => 0, 'red_exists' => array(), 'warn' => array(), 'lines' => array(), 'red' => 'Redirection' );
	if ( ! zad_svcc_red_active() ) { $report['error'] = 'إضافة Redirection غير مفعّلة. فعّلها أولاً: كل تحويل 301 يجب أن يُسجَّل فيها، ولن يُنفَّذ شيء قبل ذلك.'; return $report; }
	$group = zad_svcc_red_group();
	if ( ! $group ) { $report['error'] = 'تعذّر إنشاء/إيجاد مجموعة «تحويل الخدمات» في Redirection.'; return $report; }
	$plan = zad_svcc_plan( $rows );
	$log  = zad_svcc_log_get();
	$types = zad_svcc_source_types(); $urlmap = array(); $cat_hits = array();
	foreach ( $plan['rows'] as $id => $res ) {
		if ( 'none' === $res['status'] ) { continue; }
		if ( 'skip' === $res['status'] ) { $report['skip'][ $id ] = $res['why']; $report['lines'][ $id ] = array( $res['title'], $res['act'], 'تخطّى: ' . $res['why'] ); continue; }
		$p = get_post( (int) $id );
		if ( ! $p || ! in_array( $p->post_type, $types, true ) ) { $report['skip'][ $id ] = 'تغيّرت حالة المقالة'; continue; }
		$r = zad_svcc_row_defaults( $rows[ $id ] );
		$old_url = $res['old_url']; $old_key = $res['redir']['from']; $item = array( 'type' => $p->post_type, 'name' => $p->post_name, 'parent' => (int) $p->post_parent, 'act' => $res['act'], 'old_url' => $old_url, 'terms' => array(), 'yoast' => array() );
		if ( 'trash' === $res['act'] ) {
			$log['trash'][ $id ] = array( 'status' => $p->post_status, 'name' => $p->post_name, 'type' => $p->post_type );
			if ( $freeze ) { $item['yoast'] = array(); }
			$ok = wp_trash_post( (int) $id );
			if ( ! $ok || 'trash' !== get_post_status( $id ) ) { unset( $log['trash'][ $id ] ); $report['skip'][ $id ] = 'تعذّر نقلها لسلة المهملات'; continue; }
			zad_svcc_yoast_forget( array( $id ) );
			$report['trashed']++; $final = $res['new_url'];
			$report['lines'][ $id ] = array( $res['title'], 'trash', 'نُقلت لسلة المهملات، وتحويل 301 إلى ' . urldecode( $final ) );
		} else {
			$d = zad_svcc_dest( $res['act'] );
			if ( $freeze ) { $item['yoast'] = zad_svcc_yoast_freeze( $id ); }
			$cats = zad_svcc_old_terms( $p );
			$set = array( 'post_type' => $d['type'], 'post_parent' => (int) $res['parent'] );
			if ( $res['slug'] !== $p->post_name ) { $set['post_name'] = $res['slug']; }
			$wpdb->update( $wpdb->posts, $set, array( 'ID' => $id ) ); // phpcs:ignore — keeps ID, dates, modified time
			clean_post_cache( $id );
			$created = array();
			$item['terms'][ $d['tax'] ] = array();
			$sec = zad_svcc_term_id( $r['sec'], $d['tax'], 0, $created );
			if ( $sec ) { wp_set_object_terms( $id, array( $sec ), $d['tax'], true ); $item['terms'][ $d['tax'] ][] = $sec; }
			if ( $d['area'] ) {
				$item['terms'][ $d['area'] ] = array();
				$city = zad_svcc_term_id( $r['city'], $d['area'], 0, $created );
				if ( $city ) { wp_set_object_terms( $id, array( $city ), $d['area'], true ); $item['terms'][ $d['area'] ][] = $city; }
			}
			$log['created'] = array_merge( $log['created'], $created );
			foreach ( $cats as $c ) { if ( 'category' === $c->taxonomy && $sec ) { $cat_hits[ $c->term_id ][ $d['tax'] . '|' . $sec ] = ( $cat_hits[ $c->term_id ][ $d['tax'] . '|' . $sec ] ?? 0 ) + 1; } }
			$mi = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $id ) ); // phpcs:ignore
			foreach ( (array) $mi as $mid ) {
				if ( 'post_type' === get_post_meta( $mid, '_menu_item_type', true ) && $p->post_type === get_post_meta( $mid, '_menu_item_object', true ) ) { update_post_meta( $mid, '_menu_item_object', $d['type'] ); $log['menu'][ $mid ] = $p->post_type; }
			}
			zad_svcc_yoast_forget( array( $id ) );
			$new_url = get_permalink( $id );
			if ( $res['new_url'] !== $new_url && rawurldecode( $res['new_url'] ) !== rawurldecode( $new_url ) ) { $report['urlbad'][ $id ] = $new_url; }
			$item['new_url'] = $new_url; $final = $res['final'] ?? $new_url; $report['moved']++;
			$report['lines'][ $id ] = array( $res['title'], $res['act'], 'نُقلت إلى ' . $d['type'] . ' → ' . urldecode( $new_url ) );
		}
		$urlmap[ $old_key ] = $final;
		$log['items'][ $id ] = $item + array( 'new_url' => $final );
		// the 301: only when nothing already redirects this URL, and straight to the final destination (never a chain)
		if ( 'add' === $res['redir']['state'] ) {
			$rid = zad_svcc_red_add( $group, rawurldecode( $old_key ), $final );
			if ( $rid ) { $log['red'][] = $rid; $log['map'][ $old_key ] = $final; $report['red_added']++; } else { $report['warn'][ $id ] = 'تعذّر إضافة التحويل في Redirection'; }
		} elseif ( 'exists' === $res['redir']['state'] ) { $report['red_exists'][ $id ] = $old_key; }
		foreach ( $res['warn'] as $w ) { $report['warn'][ $id ] = ( $report['warn'][ $id ] ?? '' ) . $w . ' '; }
		$report['done']++;
	}

	// emptied old category archives → the new section archive (same rules: no duplicate, no chain)
	foreach ( $cat_hits as $tid => $secs ) {
		if ( (int) $tid === (int) get_option( 'default_category' ) ) { continue; }
		$left = get_posts( array( 'post_type' => 'post', 'category' => (int) $tid, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
		if ( $left ) { continue; }
		arsort( $secs ); list( $tax, $term ) = explode( '|', (string) key( $secs ) );
		$sec_link = get_term_link( (int) $term, $tax ); $old_link = get_term_link( (int) $tid, 'category' );
		if ( is_wp_error( $sec_link ) || is_wp_error( $old_link ) ) { continue; }
		$k = zad_svcc_path_key( $old_link );
		if ( zad_svcc_red_lookup( $k ) ) { $report['red_exists'][ 'cat' . $tid ] = $k; continue; }
		list( $fin ) = zad_svcc_red_final( $sec_link );
		$log['map'][ $k ] = $fin;
		$rid = zad_svcc_red_add( $group, rawurldecode( $k ), $fin );
		if ( $rid ) { $log['red'][] = $rid; $report['red_added']++; }
	}
	update_option( 'zad_svcc_map', $log['map'], false );

	// internal links in the content of all pages → the final destination (original content kept in post meta)
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
	$log = zad_svcc_log_get();
	$r = array( 'posts' => 0, 'links' => 0, 'kept' => 0, 'trash' => 0, 'red' => 0 );
	if ( ! $log['items'] && ! $log['trash'] && ! $log['red'] ) { return $r; }
	foreach ( (array) $log['red'] as $rid ) { zad_svcc_red_del( $rid ); $r['red']++; }
	delete_option( 'zad_svcc_map' );
	foreach ( (array) $log['links'] as $pid => $hash ) {
		$cur = get_post_field( 'post_content', (int) $pid );
		$bak = get_post_meta( (int) $pid, ZAD_SVCC_BACKUP, true );
		if ( '' !== (string) $bak && md5( $cur ) === $hash ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $bak ), array( 'ID' => (int) $pid ) ); clean_post_cache( (int) $pid ); delete_post_meta( (int) $pid, ZAD_SVCC_BACKUP ); $r['links']++; } // phpcs:ignore
		else { $r['kept']++; } // edited since: left as is
	}
	foreach ( (array) $log['menu'] as $mid => $old ) { update_post_meta( (int) $mid, '_menu_item_object', $old ); }
	// posts that were sent to the trash come back with the status they had
	foreach ( (array) $log['trash'] as $id => $it ) {
		$id = (int) $id;
		if ( 'trash' !== get_post_status( $id ) ) { continue; }
		$want = (string) $it['status'];
		$f = function () use ( $want ) { return $want; };
		add_filter( 'wp_untrash_post_status', $f, 99 );
		wp_untrash_post( $id );
		remove_filter( 'wp_untrash_post_status', $f, 99 );
		if ( get_post_status( $id ) !== $want ) { $wpdb->update( $wpdb->posts, array( 'post_status' => $want ), array( 'ID' => $id ) ); clean_post_cache( $id ); } // phpcs:ignore
		if ( isset( $it['name'] ) && get_post_field( 'post_name', $id ) !== $it['name'] ) { $wpdb->update( $wpdb->posts, array( 'post_name' => $it['name'] ), array( 'ID' => $id ) ); clean_post_cache( $id ); } // phpcs:ignore
		delete_post_meta( $id, '_wp_trash_meta_status' ); delete_post_meta( $id, '_wp_trash_meta_time' ); delete_post_meta( $id, '_wp_desired_post_slug' );
		$r['trash']++;
	}
	foreach ( $log['items'] as $id => $it ) {
		$id = (int) $id;
		if ( ! empty( $log['trash'][ $id ] ) ) { continue; } // handled above
		if ( get_post_type( $id ) === $it['type'] ) { continue; }
		foreach ( (array) ( $it['terms'] ?? array() ) as $tax => $ids ) { if ( $ids ) { wp_remove_object_terms( $id, array_map( 'intval', $ids ), $tax ); } }
		foreach ( (array) ( $it['yoast'] ?? array() ) as $k ) { delete_post_meta( $id, $k ); }
		$back = array( 'post_type' => $it['type'], 'post_name' => $it['name'] );
		if ( array_key_exists( 'parent', $it ) ) { $back['post_parent'] = (int) $it['parent']; }
		$wpdb->update( $wpdb->posts, $back, array( 'ID' => $id ) ); // phpcs:ignore
		clean_post_cache( $id );
		$r['posts']++;
	}
	zad_svcc_yoast_forget( array_map( 'intval', array_keys( $log['items'] ) ) );
	foreach ( (array) $log['created'] as $c ) {
		$pairs = is_array( $c ) ? array( $c ) : array_map( function ( $tax ) use ( $c ) { return array( $tax, (int) $c ); }, array( 'service_cat', 'service_area' ) ); // legacy log: bare ids
		foreach ( $pairs as $pr ) { $t = get_term( (int) $pr[1], $pr[0] ); if ( $t && ! is_wp_error( $t ) && 0 === (int) $t->count ) { wp_delete_term( (int) $pr[1], $pr[0] ); } }
	}
	delete_option( 'zad_svcc_log' );
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); }
	return $r;
}

/* ---------------- admin: search for «redirect to» ---------------- */
add_action( 'wp_ajax_zad_svcc_search', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json( array() ); }
	check_ajax_referer( 'zad_svcc_search', 'n' );
	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$out = array();
	if ( '' !== $q ) {
		$args = array( 'post_type' => zad_svcc_target_types(), 'post_status' => 'publish', 'numberposts' => 15, 'suppress_filters' => true, 'orderby' => 'relevance' );
		if ( ctype_digit( $q ) ) { $args['post__in'] = array( (int) $q ); } else { $args['s'] = $q; }
		foreach ( get_posts( $args ) as $p ) { $out[] = array( 'id' => $p->ID, 'title' => $p->post_title, 'type' => $p->post_type, 'url' => urldecode( get_permalink( $p ) ) ); }
	}
	wp_send_json( $out );
} );

/* ---------------- admin page ---------------- */
add_action( 'admin_menu', function () {
	add_management_page( 'تحويل صفحات الخدمات', 'تحويل صفحات الخدمات', 'manage_options', 'zad-svcconv', 'zad_svcc_page' );
} );

/** The rows as submitted by the form: id => row (only rows whose action is not «none»). */
function zad_svcc_posted_rows() {
	$rows = array();
	foreach ( (array) ( $_POST['act'] ?? array() ) as $id => $act ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked by the caller
		$id = (int) $id; $act = sanitize_key( $act );
		if ( ! $id || 'none' === $act ) { continue; }
		$g = function ( $k ) use ( $id ) { return isset( $_POST[ $k ][ $id ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $k ][ $id ] ) ) ) : ''; }; // phpcs:ignore
		$rows[ $id ] = array( 'act' => $act, 'slug' => $g( 'slug' ), 'sec' => $g( 'sec' ), 'city' => $g( 'city' ), 'parent' => (int) $g( 'parent' ), 'to' => $g( 'to' ) );
	}
	return $rows;
}

function zad_svcc_status_label( $r ) {
	if ( 'ok' === $r['status'] ) { return '<span style="color:#0a6b3d">✓ جاهز</span>'; }
	if ( 'skip' === $r['status'] ) { return '<span style="color:#b32d2e">✗ ' . esc_html( $r['why'] ) . '</span>'; }
	return '—';
}
function zad_svcc_redir_label( $r ) {
	if ( empty( $r['redir'] ) ) { return ''; }
	$d = $r['redir'];
	$arrow = '<code dir="ltr">/' . esc_html( urldecode( $d['from'] ) ) . '/</code> ← <code dir="ltr">' . esc_html( urldecode( $d['to'] ) ) . '</code>';
	if ( 'exists' === $d['state'] ) { return '<s>' . $arrow . '</s><br><b style="color:#8a5a00">موجود في Redirection — لن يُكرَّر</b>'; }
	if ( 'same' === $d['state'] ) { return 'لا تحويل (نفس الرابط)'; }
	return $arrow;
}

function zad_svcc_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>تحويل صفحات الخدمات</h1>';
	$act  = ! empty( $_POST['zad_sv'] ) && check_admin_referer( 'zad_sv' ) ? sanitize_key( wp_unslash( $_POST['zad_sv'] ) ) : '';
	$rows_posted = $act ? zad_svcc_posted_rows() : array();
	if ( ! post_type_exists( 'zad_service' ) ) { echo '<div class="notice notice-error"><p>نوع <code>zad_service</code> غير مسجّل.</p></div></div>'; return; }
	$red = zad_svcc_red_active();
	if ( ! $red ) { echo '<div class="notice notice-error"><p><b>إضافة Redirection غير مفعّلة.</b> كل تحويلات 301 لهذه الأداة تُسجَّل فيها (مجموعة «تحويل الخدمات») ولن يُنفَّذ أي شيء قبل تفعيلها. خريطة الثيم احتياط فقط وليست بديلاً.</p></div>'; }

	if ( 'undo' === $act ) {
		$r = zad_svcc_undo();
		echo '<div class="notice notice-warning"><p>تم التراجع: ' . (int) $r['posts'] . ' صفحة أُعيدت لنوعها ورابطها، و' . (int) $r['trash'] . ' أُخرجت من سلة المهملات، و' . (int) $r['red'] . ' تحويلاً أُزيل من Redirection، و' . (int) $r['links'] . ' محتوى أُعيدت روابطه' . ( $r['kept'] ? '، و' . (int) $r['kept'] . ' محتوى عُدّل بعد التحويل فتُرك كما هو' : '' ) . '.</p></div>';
	}
	if ( 'run' === $act && $red ) {
		if ( ! $rows_posted ) { echo '<div class="notice notice-warning"><p>لم يُحدَّد أي إجراء.</p></div>'; }
		else { zad_svcc_report_html( zad_svcc_run( $rows_posted, ! empty( $_POST['freeze'] ) ) ); }
	}
	if ( 'preview' === $act && $red ) {
		if ( ! $rows_posted ) { echo '<div class="notice notice-warning"><p>لم يُحدَّد أي إجراء — اختر إجراءً لصف واحد على الأقل.</p></div>'; }
		else { zad_svcc_preview_html( zad_svcc_plan( $rows_posted ), $rows_posted, ! empty( $_POST['freeze'] ) ); echo '</div>'; return; }
	}
	zad_svcc_table_html( $rows_posted, $red );
	$log = zad_svcc_log_get();
	if ( $log['items'] || $log['trash'] ) {
		echo '<hr><form method="post">'; wp_nonce_field( 'zad_sv' );
		echo '<p>المحوَّل حالياً: ' . count( $log['items'] ) . ' صفحة (منها ' . count( $log['trash'] ) . ' في سلة المهملات)، ' . count( (array) $log['red'] ) . ' تحويل في Redirection، ' . count( (array) $log['links'] ) . ' محتوى عُدّلت روابطه. <button class="button" name="zad_sv" value="undo" onclick="return confirm(\'التراجع عن كل ما حُوِّل؟ تُعاد الصفحات (والمحذوف من السلة) وتُزال التحويلات.\');">تراجع عن الكل</button></p></form>';
	}
	echo '</div>';
}

function zad_svcc_report_html( $rep ) {
	if ( $rep['error'] ) { echo '<div class="notice notice-error"><p>' . esc_html( $rep['error'] ) . '</p></div>'; return; }
	echo '<div class="notice notice-success"><p><b>اكتمل التنفيذ.</b> ' . (int) $rep['moved'] . ' صفحة نُقلت، ' . (int) $rep['trashed'] . ' نُقلت لسلة المهملات. تحويلات أُضيفت في Redirection: <b>' . (int) $rep['red_added'] . '</b>' . ( $rep['red_exists'] ? '، وموجودة مسبقاً فلم تُكرَّر: <b>' . count( $rep['red_exists'] ) . '</b>' : '' ) . '. روابط داخلية معدَّلة: <b>' . (int) $rep['links'] . '</b>. تخطّى: <b>' . count( $rep['skip'] ) . '</b>.</p></div>';
	if ( $rep['lines'] ) {
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>#</th><th>المقالة</th><th>الإجراء</th><th>ما حدث</th></tr></thead><tbody>';
		foreach ( $rep['lines'] as $id => $l ) { echo '<tr><td>' . (int) $id . '</td><td>' . esc_html( $l[0] ) . '</td><td>' . esc_html( zad_svcc_actions()[ $l[1] ] ?? $l[1] ) . '</td><td>' . esc_html( $l[2] ) . ( ! empty( $rep['warn'][ $id ] ) ? '<br><small style="color:#8a5a00">' . esc_html( $rep['warn'][ $id ] ) . '</small>' : '' ) . '</td></tr>'; }
		echo '</tbody></table>';
	}
	if ( $rep['urlbad'] ) { echo '<div class="notice notice-error"><p>الرابط الجديد مختلف عن المتوقع للأرقام: ' . esc_html( implode( '، ', array_keys( $rep['urlbad'] ) ) ) . ' — راجعها أو اضغط «تراجع عن الكل».</p></div>'; }
	echo '<p>بعد التنفيذ: امسح كاش LiteSpeed، وراجع التحويلات في <b>Redirection ← مجموعة «تحويل الخدمات»</b>.</p>';
}

/** The summary screen: counts per action, every redirect (from ← to), duplicates and problems; then the real run button. */
function zad_svcc_preview_html( $plan, $rows, $freeze ) {
	$acts = zad_svcc_actions();
	echo '<div class="notice notice-info"><p><b>معاينة فقط — لم يتغيّر شيء بعد.</b></p></div><h2>الملخص</h2><table class="widefat" style="max-width:520px"><tbody>';
	foreach ( $acts as $k => $l ) { if ( 'none' !== $k ) { echo '<tr><th>' . esc_html( $l ) . '</th><td>' . (int) $plan['counts'][ $k ] . '</td></tr>'; } }
	$add = count( array_filter( $plan['redirs'], function ( $x ) { return 'add' === $x['state']; } ) ); $ex = count( $plan['redirs'] ) - $add;
	echo '<tr><th>تحويلات 301 ستُضاف في Redirection</th><td><b>' . (int) $add . '</b></td></tr><tr><th>تحويلات موجودة مسبقاً (لن تُكرَّر)</th><td>' . (int) $ex . '</td></tr><tr><th>صفوف لن تُنفَّذ (مشاكل)</th><td><b style="color:#b32d2e">' . (int) $plan['skipped'] . '</b></td></tr></tbody></table>';
	if ( $plan['problems'] ) { echo '<h3>مشاكل (هذه الصفوف ستُتخطّى)</h3><ul style="list-style:disc;padding-inline-start:20px">'; foreach ( $plan['problems'] as $id => $why ) { echo '<li>#' . (int) $id . ' ' . esc_html( $plan['rows'][ $id ]['title'] ) . ' — ' . esc_html( $why ) . '</li>'; } echo '</ul>'; }
	echo '<h3>كل التحويلات (من ← إلى)</h3><table class="widefat striped"><thead><tr><th>#</th><th>المقالة</th><th>الإجراء</th><th>التحويل</th><th>تنبيهات</th></tr></thead><tbody>';
	foreach ( $plan['rows'] as $id => $r ) {
		if ( 'none' === $r['status'] ) { continue; }
		echo '<tr><td>' . (int) $id . '</td><td>' . esc_html( $r['title'] ) . '<br>' . zad_svcc_status_label( $r ) . '</td><td>' . esc_html( $acts[ $r['act'] ] ) . '</td><td>' . zad_svcc_redir_label( $r ) . ( $r['new_url'] && 'trash' !== $r['act'] ? '<br><small>الرابط الجديد: <code dir="ltr">' . esc_html( urldecode( $r['new_url'] ) ) . '</code></small>' : '' ) . '</td><td>';
		foreach ( $r['warn'] as $w ) { echo '<small style="color:#8a5a00">' . esc_html( $w ) . '</small><br>'; }
		echo '</td></tr>';
	}
	echo '</tbody></table><form method="post" style="margin-top:16px">'; wp_nonce_field( 'zad_sv' );
	foreach ( $rows as $id => $r ) { foreach ( array( 'act', 'slug', 'sec', 'city', 'parent', 'to' ) as $k ) { echo '<input type="hidden" name="' . esc_attr( $k ) . '[' . (int) $id . ']" value="' . esc_attr( $r[ $k ] ) . '">'; } }
	echo '<p><label><input type="checkbox" name="freeze" value="1"' . ( $freeze ? ' checked' : '' ) . '> ثبّت عنوان ووصف Yoast الحاليين للصفحات المنقولة التي بلا عنوان/وصف خاص</label></p>';
	echo '<p><button class="button button-primary button-hero" name="zad_sv" value="run" onclick="return confirm(\'تنفيذ فعلي: نقل ' . ( (int) array_sum( array_diff_key( $plan['counts'], array( 'none' => 1 ) ) ) ) . ' صفحة وإضافة تحويلات 301؟ يمكن التراجع عن الكل بعده.\');">تنفيذ فعلي</button> <button class="button" name="zad_sv" value="back">رجوع للتعديل</button></p></form>';
}

function zad_svcc_table_html( $posted, $red ) {
	$posts = zad_svcc_sources();
	echo '<p>أداة واحدة لنقل الصفحات التي هي خدمات فعلاً (المقالات العادية) إلى <code>zad_service</code> أو <code>cleaning</code> أو <code>pest_control</code>، أو حذفها (إلى سلة المهملات) مع تحويل 301. الإجراء المقترح <b>اقتراح فقط</b> وأنت تغيّره. كل التحويلات في Redirection (مجموعة «تحويل الخدمات»)، وأي شيء تفعله مُسجَّل ويُرجَع بـ«تراجع عن الكل». <b>خذ نسخة احتياطية من قاعدة البيانات قبل أي تنفيذ.</b></p>';
	if ( ! $posts ) { echo '<p><b>لا توجد صفحات من أنواع المصدر.</b></p>'; return; }
	$cities = zad_svcc_known_cities(); $lab = zad_svcc_lab_map(); $acts = zad_svcc_actions();
	$parents = array( 'cleaning' => array(), 'pest' => array() );
	foreach ( array( 'cleaning' => 'cleaning', 'pest' => 'pest_control' ) as $k => $t ) { if ( post_type_exists( $t ) ) { foreach ( zad_svcc_candidates( $t ) as $pid => $title ) { $parents[ $k ][] = array( $pid, urldecode( (string) get_page_uri( $pid ) ) ?: $title ); } } }
	$ptitles = array(); foreach ( $parents as $k => $l ) { foreach ( $l as $x ) { $ptitles[ $k ][ $x[0] ] = get_the_title( $x[0] ); } }
	$tt = zad_svcc_candidates( zad_svcc_target_types() );
	$secs = array();
	foreach ( array( 'service' => 'service_cat', 'cleaning' => 'cleaning-sections', 'pest' => 'pest_sections' ) as $k => $tax ) { $t = taxonomy_exists( $tax ) ? get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : array(); $secs[ $k ] = is_wp_error( $t ) ? array() : wp_list_pluck( $t, 'name' ); }
	foreach ( $secs as $k => $names ) { echo '<datalist id="zadsv-secs-' . esc_attr( $k ) . '">'; foreach ( $names as $n ) { echo '<option value="' . esc_attr( $n ) . '">'; } echo '</datalist>'; }
	echo '<datalist id="zadsv-cities">'; foreach ( $cities as $c ) { echo '<option value="' . esc_attr( $c ) . '">'; } echo '</datalist>';
	// build the state of every row: posted values win, else the suggestion
	$state = array(); $plan_in = array();
	foreach ( $posts as $p ) {
		$names = wp_list_pluck( zad_svcc_old_terms( $p ), 'name' );
		$sug = zad_svcc_suggest_action( $p, $names ); $lb = $lab[ $p->ID ] ?? null; $to = '';
		if ( $lb ) { $sug = 'trash'; $to = $lb['new'] ? (string) $lb['new'] : ''; }
		$city = zad_svcc_city_guess( $names ); $sec = zad_svcc_section_guess( $names, $p->post_title ); $parent = 0;
		if ( in_array( $sug, array( 'cleaning', 'pest' ), true ) ) { $parent = zad_svcc_best( $p->post_title, $ptitles[ $sug ] ?? array(), $city ); }
		if ( 'trash' === $sug && '' === $to ) { $b = zad_svcc_best( $p->post_title, $tt, $city ); $to = $b ? (string) $b : ''; }
		$row = array( 'act' => $sug, 'slug' => zad_svcc_slug_default( $p ), 'sec' => $sec, 'city' => $city, 'parent' => $parent, 'to' => $to );
		if ( isset( $posted[ $p->ID ] ) ) { $row = array_merge( $row, $posted[ $p->ID ] ); } elseif ( $posted ) { $row['act'] = 'none'; }
		$state[ $p->ID ] = $row; if ( 'none' !== $row['act'] ) { $plan_in[ $p->ID ] = $row; }
	}
	$plan = $red ? zad_svcc_plan( $plan_in ) : array( 'rows' => array() );
	echo '<form method="post" id="zadsv-form">'; wp_nonce_field( 'zad_sv' );
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:10px 0"><b>فلتر:</b><select id="zadsv-f-act"><option value="">كل الإجراءات المقترحة</option>'; foreach ( $acts as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>'; } echo '</select>';
	echo '<select id="zadsv-f-city"><option value="">كل المدن</option>'; foreach ( array_unique( array_filter( array_column( $state, 'city' ) ) ) as $c ) { echo '<option>' . esc_html( $c ) . '</option>'; } echo '</select><label><input type="checkbox" id="zadsv-f-lab"> ليها صفحة جديدة فقط</label></div>';
	echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0"><b>على المحدّد (☐):</b><select id="zadsv-bulk">'; foreach ( $acts as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>'; } echo '</select> <button type="button" class="button" id="zadsv-apply">تطبيق الإجراء على المحدد</button> <button type="button" class="button" id="zadsv-all">تحديد الظاهر</button> <button type="button" class="button" id="zadsv-none">إلغاء التحديد</button></div>';
	echo '<table class="widefat striped" id="zadsv-tbl"><thead><tr><th></th><th>العنوان</th><th>الرابط القديم</th><th>الإجراء</th><th>الاسم (الرابط)</th><th>الصفحة الأم</th><th>القسم</th><th>المدينة</th><th>التحويل إلى</th><th>المعاينة (من ← إلى)</th></tr></thead><tbody>';
	$cnt = array_fill_keys( array_keys( $acts ), 0 );
	foreach ( $posts as $p ) {
		$s = $state[ $p->ID ]; $id = (int) $p->ID; $cnt[ $s['act'] ]++; $names = wp_list_pluck( zad_svcc_old_terms( $p ), 'name' ); $lb = $lab[ $id ] ?? null;
		$ycust = '' !== trim( (string) get_post_meta( $id, '_yoast_wpseo_title', true ) ) ? 'عنوان Yoast مخصّص' : 'قالب Yoast';
		$r = $plan['rows'][ $id ] ?? null; $tlabel = ctype_digit( $s['to'] ) && $s['to'] ? ( '#' . $s['to'] . ' ' . get_the_title( (int) $s['to'] ) ) : $s['to'];
		echo '<tr data-act="' . esc_attr( $s['act'] ) . '" data-city="' . esc_attr( $s['city'] ) . '" data-lab="' . ( $lb ? 1 : 0 ) . '"><td><input type="checkbox" class="zadsv-sel"></td>';
		echo '<td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $p->post_title ) . '</a>' . ( $lb ? ' <span style="background:#e3f5f1;border:1px solid #0d7f70;border-radius:10px;padding:1px 8px;font-size:11px">ليها صفحة جديدة' . ( $lb['new'] ? ' #' . (int) $lb['new'] . ' (' . esc_html( get_post_status( $lb['new'] ) ?: '؟' ) . ')' : '' ) . '</span>' : '' ) . '<br><small>' . esc_html( $p->post_type . ' · #' . $id . ' · ' . $p->post_status . ' · ' . $ycust . ( $names ? ' · ' . implode( '، ', $names ) : '' ) ) . '</small></td>';
		echo '<td><code dir="ltr">' . esc_html( urldecode( (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH ) ) ) . '</code></td>';
		echo '<td><select class="zadsv-act" name="act[' . $id . ']">'; foreach ( $acts as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['act'], $k, false ) . '>' . esc_html( $l ) . '</option>'; } echo '</select></td>';
		echo '<td class="zadsv-c-move"><input type="text" dir="ltr" name="slug[' . $id . ']" value="' . esc_attr( $s['slug'] ) . '" style="width:170px"></td>';
		echo '<td class="zadsv-c-parent"><select name="parent[' . $id . ']" class="zadsv-parent" data-val="' . (int) $s['parent'] . '" style="max-width:210px"><option value="0">— بلا أم —</option></select></td>';
		echo '<td class="zadsv-c-move"><input type="text" class="zadsv-sec" name="sec[' . $id . ']" value="' . esc_attr( $s['sec'] ) . '" style="width:130px"></td>';
		echo '<td class="zadsv-c-city"><input type="text" list="zadsv-cities" name="city[' . $id . ']" value="' . esc_attr( $s['city'] ) . '" style="width:95px"></td>';
		echo '<td class="zadsv-c-to"><input type="text" class="zadsv-to-q" value="' . esc_attr( $tlabel ) . '" placeholder="ابحث بالاسم أو الرقم" style="width:200px"><input type="hidden" class="zadsv-to" name="to[' . $id . ']" value="' . esc_attr( $s['to'] ) . '"><div class="zadsv-res"></div></td>';
		echo '<td class="zadsv-prev">' . ( $r ? zad_svcc_status_label( $r ) . '<br>' . zad_svcc_redir_label( $r ) : '' ) . '</td></tr>';
	}
	echo '</tbody></table><p>' . count( $posts ) . ' صفحة. الاقتراح: ' . implode( '، ', array_map( function ( $k ) use ( $cnt, $acts ) { return $acts[ $k ] . ' ' . (int) $cnt[ $k ]; }, array_keys( $cnt ) ) ) . '. المعاينة في الجدول بحسب الاقتراح الحالي؛ بعد تعديلك اضغط «معاينة قبل التنفيذ» لتحديثها.</p>';
	echo '<p><label><input type="checkbox" name="freeze" value="1" checked> ثبّت عنوان ووصف Yoast الحاليين للصفحات المنقولة التي بلا عنوان/وصف خاص</label></p>';
	echo '<p><button class="button button-primary" name="zad_sv" value="preview"' . ( $red ? '' : ' disabled' ) . '>معاينة قبل التنفيذ</button></p></form>';
	$data = array( 'parents' => $parents, 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'zad_svcc_search' ) );
	echo '<script>var ZADSV=' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . ';' . zad_svcc_admin_js() . '</script>';
}

function zad_svcc_admin_js() {
	return <<<'JS'
(function(){
 var $=function(s,c){return (c||document).querySelector(s)},$$=function(s,c){return [].slice.call((c||document).querySelectorAll(s))};
 function fillParent(tr){var a=$('.zadsv-act',tr).value,sel=$('.zadsv-parent',tr),want=sel.getAttribute('data-val')||sel.value,key=a==='cleaning'?'cleaning':(a==='pest'?'pest':'');
  sel.innerHTML='<option value="0">— بلا أم —</option>';(key?ZADSV.parents[key]||[]:[]).forEach(function(p){var o=document.createElement('option');o.value=p[0];o.textContent=p[1]+' (#'+p[0]+')';sel.appendChild(o)});sel.value=want;if(sel.value!==want){sel.value='0'}}
 function apply(tr){var a=$('.zadsv-act',tr).value,mv=['service','cleaning','pest'].indexOf(a)>=0;
  $$('.zadsv-c-move',tr).forEach(function(c){c.style.visibility=mv?'visible':'hidden'});
  $('.zadsv-c-parent',tr).style.visibility=(a==='cleaning'||a==='pest')?'visible':'hidden';
  $('.zadsv-c-city',tr).style.visibility=a==='service'?'visible':'hidden';
  $('.zadsv-c-to',tr).style.visibility=a==='trash'?'visible':'hidden';
  var inp=$('.zadsv-sec',tr);inp.setAttribute('list','zadsv-secs-'+(a==='cleaning'?'cleaning':(a==='pest'?'pest':'service')));
  tr.setAttribute('data-act',a);fillParent(tr)}
 $$('#zadsv-tbl tbody tr').forEach(function(tr){apply(tr);$('.zadsv-act',tr).addEventListener('change',function(){$('.zadsv-parent',tr).setAttribute('data-val','0');apply(tr)})});
 function flt(){var a=$('#zadsv-f-act').value,c=$('#zadsv-f-city').value,l=$('#zadsv-f-lab').checked;$$('#zadsv-tbl tbody tr').forEach(function(tr){tr.style.display=((!a||tr.getAttribute('data-act')===a)&&(!c||tr.getAttribute('data-city')===c)&&(!l||tr.getAttribute('data-lab')==='1'))?'':'none'})}
 ['#zadsv-f-act','#zadsv-f-city','#zadsv-f-lab'].forEach(function(s){$(s).addEventListener('change',flt)});
 $('#zadsv-all').onclick=function(){$$('#zadsv-tbl tbody tr').forEach(function(tr){if(tr.style.display!=='none'){$('.zadsv-sel',tr).checked=true}})};
 $('#zadsv-none').onclick=function(){$$('.zadsv-sel').forEach(function(c){c.checked=false})};
 $('#zadsv-apply').onclick=function(){var v=$('#zadsv-bulk').value;$$('#zadsv-tbl tbody tr').forEach(function(tr){if($('.zadsv-sel',tr).checked){$('.zadsv-act',tr).value=v;$('.zadsv-parent',tr).setAttribute('data-val','0');apply(tr)}});flt()};
 var timer;$$('.zadsv-to-q').forEach(function(q){q.addEventListener('input',function(){var box=q.parentNode.querySelector('.zadsv-res'),hid=q.parentNode.querySelector('.zadsv-to');hid.value=/^\d+$/.test(q.value.trim())?q.value.trim():hid.value;clearTimeout(timer);
  if(q.value.trim().length<2){box.innerHTML='';return}
  timer=setTimeout(function(){fetch(ZADSV.ajax+'?action=zad_svcc_search&n='+ZADSV.nonce+'&q='+encodeURIComponent(q.value.trim()),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(list){box.innerHTML='';list.forEach(function(p){var b=document.createElement('button');b.type='button';b.className='button button-small';b.style.cssText='display:block;margin:2px 0;text-align:start';b.textContent=p.title+' · '+p.type+' #'+p.id;b.title=p.url;b.onclick=function(){hid.value=p.id;q.value='#'+p.id+' '+p.title;box.innerHTML=''};box.appendChild(b)})})},250)})});
 $('#zadsv-form').addEventListener('submit',function(e){if(e.submitter&&e.submitter.value==='preview'){var bad=0;$$('#zadsv-tbl tbody tr').forEach(function(tr){if($('.zadsv-act',tr).value==='trash'&&!/^\d+$/.test($('.zadsv-to',tr).value.trim())&&$('.zadsv-to',tr).value.trim()===''){bad++}});if(bad&&!confirm(bad+' صف بإجراء «حذف مع تحويل» بلا وجهة وسيُتخطّى. متابعة المعاينة؟')){e.preventDefault()}}});
})();
JS;
}
