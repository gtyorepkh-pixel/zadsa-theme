<?php defined( 'ABSPATH' ) || exit;
/**
 * City of a service page + the one filter every internal list of services uses.
 *
 *  - Field «المدينة» (post meta _zad_city) only on the service types cleaning / pest_control / zad_service (filter `zad_city_field_types`):
 *    a city slug from zad_city_map(), or «all» (= every city), or empty (= not chosen: the city is discovered, see zad_city_resolve()).
 *  - zad_service_city_scope( $id ) → city slug | 'all' | '' (not determined). The site default («الرياض», source = default in zad_current_city()) is only a
 *    display fallback (title, WhatsApp, address) and counts as «not determined» here.
 *  - zad_city_filter_args( $args, $city ) / zad_city_allowed( $id, $city ): a list on a page of city X shows pages of city X or «all» only;
 *    a page of «all» or of an undetermined city shows «all» pages only. Never filled up from other cities.
 *  - Tools ← مدن الخدمات: review / bulk-set the city of every service page, services × cities matrix, undo.
 * Nothing here touches Schema, the home page, other post types or other tools.
 */

/* ------------------------------------------------------------------ */
/* City list (zad_city_map) + the types that carry the field            */
/* ------------------------------------------------------------------ */

/** Cities added from the tool («مدن الخدمات») join zad_city_map(); keys are decoded, lower-case slugs. */
add_filter( 'zad_city_map', function ( $m ) {
	$x = get_option( 'zad_city_extra', array() );
	if ( is_array( $x ) ) { foreach ( $x as $s => $n ) { $s = strtolower( trim( urldecode( (string) $s ) ) ); if ( '' !== $s && '' !== trim( (string) $n ) && ! isset( $m[ $s ] ) ) { $m[ $s ] = trim( (string) $n ); } } }
	return $m;
}, 20 );

/** The service types that get the city field: «التنظيف» (cleaning), «مكافحة الحشرات» (pest_control) and «الخدمات» (zad_service). */
function zad_city_types() {
	return array_values( apply_filters( 'zad_city_field_types', array_intersect( array( 'cleaning', 'pest_control', 'zad_service' ), zad_service_types() ) ) );
}

function zad_city_label( $scope ) {
	if ( 'all' === $scope ) { return 'كل المدن'; }
	if ( '' === $scope ) { return 'غير محددة'; }
	$m = zad_city_map();
	return $m[ $scope ] ?? rawurldecode( $scope );
}

function zad_city_norm_slug( $val ) {
	$map = zad_city_map();
	$k   = strtolower( trim( urldecode( (string) $val ), " \t\n\r/" ) );
	if ( isset( $map[ $k ] ) ) { return $k; }
	$s = array_search( trim( (string) $val ), $map, true );
	return false !== $s ? $s : sanitize_title( (string) $val );
}

/* ------------------------------------------------------------------ */
/* Resolution                                                           */
/* ------------------------------------------------------------------ */

function zad_city_reset() { $GLOBALS['zad_city_memo'] = array(); }

/**
 * array( scope, source ): scope = city slug | 'all' | '' ; source = meta | ancestor | url | none.
 * Same order as zad_current_city(): the page's own field, then its ancestors (nearest first: an ancestor's city field, else an ancestor that is a city page by slug),
 * then the permalink path / own slug; an ancestor's «all» is inherited last. No hit = '' (the default city is NOT used here).
 */
function zad_city_resolve( $id ) {
	$id = (int) $id;
	if ( ! isset( $GLOBALS['zad_city_memo'] ) ) { $GLOBALS['zad_city_memo'] = array(); }
	$memo = &$GLOBALS['zad_city_memo'];
	if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
	$map  = zad_city_map();
	$norm = function ( $v ) { return strtolower( trim( urldecode( (string) $v ), " \t\n\r/" ) ); };
	$val  = function ( $m ) { return 'all' === strtolower( trim( (string) $m ) ) ? 'all' : zad_city_norm_slug( $m ); };
	$own  = trim( (string) get_post_meta( $id, '_zad_city', true ) );
	if ( '' !== $own ) { return $memo[ $id ] = array( $val( $own ), 'meta' ); }
	$inherit_all = false; // «all» on an ancestor (a main page of every city) is inherited only when nothing more specific is found: its city pages keep their own city
	foreach ( (array) get_post_ancestors( $id ) as $aid ) {
		$m = trim( (string) get_post_meta( $aid, '_zad_city', true ) );
		if ( '' !== $m ) { if ( 'all' === strtolower( $m ) ) { $inherit_all = true; continue; } return $memo[ $id ] = array( $val( $m ), 'ancestor' ); }
		$p = get_post( $aid );
		if ( $p && isset( $map[ $norm( $p->post_name ) ] ) ) { return $memo[ $id ] = array( $norm( $p->post_name ), 'ancestor' ); }
	}
	$segs = array_filter( explode( '/', (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ) ) );
	$p    = get_post( $id );
	if ( $p ) { $segs[] = $p->post_name; }
	foreach ( $segs as $seg ) { if ( isset( $map[ $norm( $seg ) ] ) ) { return $memo[ $id ] = array( $norm( $seg ), 'url' ); } }
	if ( $inherit_all ) { return $memo[ $id ] = array( 'all', 'ancestor' ); }
	return $memo[ $id ] = array( '', 'none' );
}

/** City slug of a service page, or 'all', or '' when it is not determined (the site default does not count). */
function zad_service_city_scope( $id ) { return zad_city_resolve( $id )[0]; }

/* ------------------------------------------------------------------ */
/* Cache: id => scope for every published service page                  */
/* ------------------------------------------------------------------ */

function zad_city_ver() { return (int) get_option( 'zad_city_ver', 1 ); }
function zad_city_bump() { update_option( 'zad_city_ver', time(), false ); zad_city_reset(); }
/** Part of every list's cache key: the page's city + the version that changes whenever any service page is saved. */
function zad_city_key( $id ) { return zad_service_city_scope( $id ) . '_' . zad_city_ver(); }

function zad_city_scope_map() {
	static $mem = array();
	$v = zad_city_ver();
	if ( isset( $mem[ $v ] ) ) { return $mem[ $v ]; }
	$key = 'zad_cscope_' . $v;
	$map = get_transient( $key );
	if ( ! is_array( $map ) ) {
		$map = array();
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true, 'no_found_rows' => true, 'zad_all' => true ) ) as $pid ) { $map[ (int) $pid ] = zad_service_city_scope( $pid ); }
		set_transient( $key, $map, 12 * HOUR_IN_SECONDS );
	}
	return $mem[ $v ] = $map;
}

foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $zad_h ) {
	add_action( $zad_h, function ( $id ) { if ( in_array( get_post_type( $id ), zad_service_types(), true ) ) { zad_city_bump(); } }, 99 );
}
add_action( 'update_option_zad_city_extra', 'zad_city_bump' );
add_action( 'add_option_zad_city_extra', 'zad_city_bump' );

/* ------------------------------------------------------------------ */
/* The filter                                                           */
/* ------------------------------------------------------------------ */

/** Can a page of city $city list the service page $id? (same city, or «all»; a page of «all»/undetermined city lists «all» pages only) */
function zad_city_allowed( $id, $city ) {
	$sc = zad_city_scope_map()[ (int) $id ] ?? zad_service_city_scope( $id );
	return 'all' === $sc || ( '' !== $city && 'all' !== $city && $sc === $city );
}

/** ids of the published service pages a page of city $city may list. */
function zad_city_ids_for( $city ) {
	$out = array();
	foreach ( zad_city_scope_map() as $id => $sc ) { if ( 'all' === $sc || ( '' !== $city && 'all' !== $city && $sc === $city ) ) { $out[] = (int) $id; } }
	return $out;
}

/** Adds the city rule to WP_Query / get_posts args (post__in; an existing post__in is intersected). No match = post__in [0] = nothing. */
function zad_city_filter_args( $args, $city ) {
	$ids = zad_city_ids_for( (string) $city );
	if ( empty( $args['zad_all'] ) && function_exists( 'zad_area_page_ids' ) ) { $ids = array_values( array_diff( $ids, (array) zad_area_page_ids() ) ); } // WP ignores post__not_in once post__in is set, so the theme's «hide neighbourhood pages from listings» rule is applied here
	if ( ! empty( $args['post__not_in'] ) ) { $ids = array_values( array_diff( $ids, array_map( 'intval', (array) $args['post__not_in'] ) ) ); unset( $args['post__not_in'] ); }
	if ( ! empty( $args['post__in'] ) ) { $ids = array_values( array_intersect( $ids, array_map( 'intval', (array) $args['post__in'] ) ) ); }
	$args['post__in'] = $ids ? $ids : array( 0 );
	return $args;
}

/** Same, for a page: its own city. $strict = false leaves pages of «all» / undetermined city unfiltered (a main page listing its own child pages: the city picker). */
function zad_city_args_for( $args, $page_id, $strict = true ) {
	$sc = zad_service_city_scope( $page_id );
	if ( ! $strict && ( '' === $sc || 'all' === $sc ) ) { return $args; }
	return zad_city_filter_args( $args, $sc );
}

/** Link «كل خدماتنا في {المدينة}» to the city's main page (its service_area archive), when the list is shorter than asked. '' when there is none. */
function zad_city_more_html( $page_id, $shown = 0, $wanted = 3 ) {
	$sc = zad_service_city_scope( $page_id );
	if ( '' === $sc || 'all' === $sc || $shown >= $wanted || $shown < 1 ) { return ''; }
	$map  = zad_city_map();
	$term = get_term_by( 'slug', $sc, 'service_area' ) ?: ( isset( $map[ $sc ] ) ? get_term_by( 'name', $map[ $sc ], 'service_area' ) : false );
	if ( ! $term || is_wp_error( $term ) || 0 !== (int) $term->parent ) { return ''; }
	$u = get_term_link( $term );
	return is_wp_error( $u ) ? '' : '<p class="sec__more"><a class="btn btn--ghost-dark" href="' . esc_url( $u ) . '">' . esc_html( 'كل خدماتنا في ' . zad_city_label( $sc ) ) . '</a></p>';
}

/** Does a text mention a city (from the map) other than $scope? For «all» / undetermined pages: any city at all. */
function zad_city_mentions_other( $text, $scope ) {
	$t = zad_ar_norm( $text );
	foreach ( zad_city_map() as $slug => $name ) {
		if ( $slug === $scope ) { continue; }
		$n = zad_ar_norm( $name );
		if ( '' !== $n && false !== mb_strpos( $t, $n ) ) { return true; }
	}
	return false;
}

/* ------------------------------------------------------------------ */
/* Meta box «المدينة» (side, only on the field types)                    */
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes', function () {
	foreach ( zad_city_types() as $t ) { add_meta_box( 'zad_city_box', 'المدينة', 'zad_city_box', $t, 'side', 'high' ); }
} );

function zad_city_source_label( $s ) { return array( 'meta' => 'الحقل', 'ancestor' => 'الصفحة الأم', 'url' => 'الرابط', 'none' => 'لم تُحدَّد' )[ $s ] ?? $s; }

function zad_city_box( $post ) {
	wp_nonce_field( 'zad_city_save', 'zad_city_nonce' );
	$own = trim( (string) get_post_meta( $post->ID, '_zad_city', true ) );
	$sel = '' === $own ? '' : ( 'all' === strtolower( $own ) ? 'all' : zad_city_norm_slug( $own ) );
	$map = zad_city_map();
	echo '<p><select name="zad_city" style="width:100%"><option value="">— تلقائي (من الصفحة الأم أو الرابط) —</option>';
	foreach ( $map as $slug => $name ) { echo '<option value="' . esc_attr( $slug ) . '"' . selected( $sel, $slug, false ) . '>' . esc_html( $name ) . '</option>'; }
	echo '<option value="all"' . selected( $sel, 'all', false ) . '>كل المدن</option>';
	if ( '' !== $sel && 'all' !== $sel && ! isset( $map[ $sel ] ) ) { echo '<option value="' . esc_attr( $sel ) . '" selected>' . esc_html( $own ) . ' (غير مضافة للقائمة)</option>'; }
	echo '</select></p>';
	list( $sc, $src ) = zad_city_resolve( $post->ID );
	echo '<p class="description">المعتمد الآن: <b>' . esc_html( zad_city_label( $sc ) ) . '</b> (المصدر: ' . esc_html( zad_city_source_label( $src ) ) . '). ' . ( '' === $sc ? 'ما دامت المدينة غير محددة فلن تعرض قوائم هذه الصفحة إلا الخدمات المعلَّمة «كل المدن».' : 'قوائم هذه الصفحة (الجانبية وذات الصلة وقد تحتاج أيضاً) تعرض خدمات هذه المدينة والخدمات المعلَّمة «كل المدن» فقط.' ) . '</p>';
}

add_action( 'save_post', function ( $id ) {
	if ( ! in_array( get_post_type( $id ), zad_city_types(), true ) || ! isset( $_POST['zad_city_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_city_nonce'] ) ), 'zad_city_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$v = isset( $_POST['zad_city'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST['zad_city'] ) ) ) ) : '';
	if ( '' === $v ) { delete_post_meta( $id, '_zad_city' ); return; }
	if ( 'all' === $v || isset( zad_city_map()[ $v ] ) || $v === zad_city_norm_slug( (string) get_post_meta( $id, '_zad_city', true ) ) ) { update_post_meta( $id, '_zad_city', $v ); }
}, 5 );

/* list screens: «المدينة» column + filter */
add_action( 'admin_init', function () {
	foreach ( zad_city_types() as $pt ) {
		add_filter( "manage_{$pt}_posts_columns", function ( $c ) { $c['zad_city'] = 'المدينة'; return $c; } );
		add_action( "manage_{$pt}_posts_custom_column", function ( $col, $id ) {
			if ( 'zad_city' !== $col ) { return; }
			list( $sc, $src ) = zad_city_resolve( $id );
			echo esc_html( zad_city_label( $sc ) ) . ( 'meta' === $src ? '' : ' <small style="color:#787c82">(' . esc_html( zad_city_source_label( $src ) ) . ')</small>' );
		}, 10, 2 );
	}
} );
add_action( 'restrict_manage_posts', function ( $pt ) {
	if ( ! in_array( $pt, zad_city_types(), true ) ) { return; }
	$cur = isset( $_GET['zad_city_f'] ) ? sanitize_text_field( wp_unslash( $_GET['zad_city_f'] ) ) : ''; // phpcs:ignore
	echo '<select name="zad_city_f"><option value="">كل المدن (عرض)</option><option value="_none"' . selected( $cur, '_none', false ) . '>غير محددة</option>';
	foreach ( zad_city_map() as $slug => $name ) { echo '<option value="' . esc_attr( $slug ) . '"' . selected( $cur, $slug, false ) . '>' . esc_html( $name ) . '</option>'; }
	echo '<option value="all"' . selected( $cur, 'all', false ) . '>المعلَّمة «كل المدن»</option></select>';
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || empty( $_GET['zad_city_f'] ) || ! in_array( $q->get( 'post_type' ), zad_city_types(), true ) ) { return; } // phpcs:ignore
	$f   = sanitize_text_field( wp_unslash( $_GET['zad_city_f'] ) ); // phpcs:ignore
	$ids = get_posts( array( 'post_type' => $q->get( 'post_type' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
	$ids = array_values( array_filter( $ids, function ( $i ) use ( $f ) { $sc = zad_service_city_scope( $i ); return '_none' === $f ? '' === $sc : $sc === $f; } ) );
	$q->set( 'post__in', $ids ? $ids : array( 0 ) );
} );

/* ------------------------------------------------------------------ */
/* Tool: Tools ← مدن الخدمات                                            */
/* ------------------------------------------------------------------ */

/** Service-area (top-level term) names of a page. */
function zad_city_area_terms( $id ) {
	$out = array();
	$t   = get_the_terms( $id, 'service_area' );
	if ( $t && ! is_wp_error( $t ) ) { foreach ( $t as $x ) { if ( 0 === (int) $x->parent ) { $out[] = $x; } } }
	return $out;
}

/** The city (name) the page's Schema declares today: zad_current_city() unless it is the site default, else its first top-level service_area term. '' = none. */
function zad_city_schema_city( $id ) {
	$cc = zad_current_city( $id );
	if ( 'default' !== $cc['source'] ) { return $cc['name']; }
	$t = zad_city_area_terms( $id );
	return $t ? $t[0]->name : '';
}

/** One row of the tool. */
function zad_city_row( $p ) {
	$map   = zad_city_map();
	$saved = trim( (string) get_post_meta( $p->ID, '_zad_city', true ) );
	list( $sc, $src ) = zad_city_resolve( $p->ID );
	$sug = ''; $why = ''; $unknown = '';
	if ( '' !== $saved ) { $sug = $sc; $why = 'محفوظ'; }
	elseif ( in_array( $src, array( 'ancestor', 'url' ), true ) ) { $sug = $sc; $why = 'ancestor' === $src ? 'الصفحة الأم' : 'الرابط'; }
	else {
		$at = zad_city_area_terms( $p->ID );
		if ( $at ) {
			$sl = zad_city_norm_slug( $at[0]->name );
			if ( isset( $map[ $sl ] ) ) { $sug = $sl; $why = 'تصنيف المنطقة'; } else { $unknown = $at[0]->name; $why = 'تصنيف المنطقة (مدينة غير مضافة)'; }
		} elseif ( 0 === (int) $p->post_parent && is_post_type_hierarchical( $p->post_type ) ) {
			foreach ( get_posts( array( 'post_type' => $p->post_type, 'post_parent' => $p->ID, 'post_status' => 'any', 'numberposts' => 40, 'suppress_filters' => true, 'zad_all' => true ) ) as $k ) { if ( isset( $map[ strtolower( urldecode( $k->post_name ) ) ] ) ) { $sug = 'all'; $why = 'صفحة أم لمدن'; break; } }
		}
	}
	$sch  = zad_city_schema_city( $p->ID );
	$flag = array();
	if ( '' !== $sc && 'all' !== $sc ) {
		$nm = zad_city_label( $sc );
		if ( '' === $sch ) { $flag[] = 'areaServed غير موجود'; } elseif ( $sch !== $nm ) { $flag[] = 'areaServed = ' . $sch; }
		foreach ( zad_city_area_terms( $p->ID ) as $t ) { if ( $t->name !== $nm ) { $flag[] = 'تصنيف المنطقة = ' . $t->name; } }
	} elseif ( 'all' === $sc && '' !== $sch ) { $flag[] = 'كل المدن لكن areaServed = ' . $sch; }
	return array( 'id' => (int) $p->ID, 'title' => $p->post_title, 'type' => $p->post_type, 'status' => $p->post_status, 'saved' => $saved, 'scope' => $sc, 'src' => $src, 'sug' => $sug, 'why' => $why, 'unknown' => $unknown, 'review' => ( '' === $saved && 'none' === $src ), 'schema' => $sch, 'flags' => $flag );
}

function zad_city_rows() {
	$rows = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true, 'no_found_rows' => true, 'zad_all' => true ) ) as $p ) { $rows[] = zad_city_row( $p ); }
	usort( $rows, function ( $a, $b ) { return array( ! $a['review'], empty( $a['flags'] ), $a['title'] ) <=> array( ! $b['review'], empty( $b['flags'] ), $b['title'] ); } );
	return $rows;
}

/** «Service» key for the matrix: the page's base name, city phrases removed («مكافحة النمل في الدمام» → «مكافحة النمل»). */
function zad_city_service_key( $id ) {
	$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( zad_service_base_name( $id ) ) ) );
	foreach ( zad_city_map() as $name ) { $t = preg_replace( '/\s*(?:في\s+|بـ?)' . preg_quote( $name, '/' ) . '/u', '', $t ); }
	return trim( $t );
}

function zad_city_apply( $set ) { // id => value ('' = clear) → number changed; the previous values are kept for «تراجع»
	$snap = array(); $n = 0; $map = zad_city_map();
	foreach ( $set as $id => $v ) {
		$id = (int) $id; $v = strtolower( trim( (string) $v ) );
		if ( ! in_array( get_post_type( $id ), zad_service_types(), true ) || ! current_user_can( 'edit_post', $id ) || ! ( '' === $v || 'all' === $v || isset( $map[ $v ] ) ) ) { continue; }
		$old = metadata_exists( 'post', $id, '_zad_city' ) ? (string) get_post_meta( $id, '_zad_city', true ) : null;
		if ( ( null === $old && '' === $v ) || $old === $v ) { continue; }
		$snap[ $id ] = $old;
		if ( '' === $v ) { delete_post_meta( $id, '_zad_city' ); } else { update_post_meta( $id, '_zad_city', $v ); }
		$n++;
	}
	if ( $snap ) { update_option( 'zad_city_undo', array( 't' => time(), 'rows' => $snap ), false ); }
	zad_city_bump();
	return $n;
}

function zad_city_undo() {
	$u = get_option( 'zad_city_undo' ); $n = 0;
	if ( ! is_array( $u ) || empty( $u['rows'] ) ) { return 0; }
	foreach ( $u['rows'] as $id => $old ) { if ( null === $old ) { delete_post_meta( (int) $id, '_zad_city' ); } else { update_post_meta( (int) $id, '_zad_city', $old ); } $n++; }
	delete_option( 'zad_city_undo' );
	zad_city_bump();
	return $n;
}

add_action( 'admin_menu', function () { add_management_page( 'مدن الخدمات', 'مدن الخدمات', 'manage_options', 'zad-city-scope', 'zad_city_tool_page' ); } );

function zad_city_tool_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>مدن الخدمات</h1>';
	echo '<p>كل صفحة خدمة (التنظيف، مكافحة الحشرات، الخدمات) تُحدَّد لها مدينة، وقوائمها الداخلية تعرض خدمات نفس المدينة (والمعلَّمة «كل المدن») فقط. ما حُدِّد افتراضياً «الرياض» (للعنوان والواتساب) <b>لا يُحتسب مدينة</b>؛ تلك الصفحات تظهر أولاً بعلامة «راجع». خُذ نسخة احتياطية من قاعدة البيانات قبل التطبيق الجماعي، وبعده امسح كاش LiteSpeed.</p>';

	if ( isset( $_POST['zad_city_do'] ) ) {
		check_admin_referer( 'zad_city_tool' );
		$do = sanitize_key( wp_unslash( $_POST['zad_city_do'] ) );
		$sel = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		if ( 'addcities' === $do ) {
			$extra = (array) get_option( 'zad_city_extra', array() );
			foreach ( (array) ( $_POST['newcity'] ?? array() ) as $pair ) { $pair = explode( '|', sanitize_text_field( wp_unslash( $pair ) ), 2 ); if ( 2 === count( $pair ) && '' !== trim( $pair[0] ) && '' !== trim( $pair[1] ) ) { $extra[ strtolower( urldecode( trim( $pair[0] ) ) ) ] = trim( $pair[1] ); } }
			update_option( 'zad_city_extra', $extra, false );
			echo '<div class="notice notice-success"><p>أُضيفت المدن المحددة إلى القائمة.</p></div>';
		} elseif ( 'undo' === $do ) {
			echo '<div class="notice notice-success"><p>تم التراجع عن آخر تطبيق: ' . (int) zad_city_undo() . ' صفحة.</p></div>';
		} elseif ( 'save' === $do || 'accept' === $do ) {
			$set = array();
			$byid = array(); foreach ( zad_city_rows() as $r ) { $byid[ $r['id'] ] = $r; }
			foreach ( $sel as $id ) {
				if ( 'save' === $do ) { if ( isset( $_POST['val'][ $id ] ) ) { $set[ $id ] = sanitize_text_field( wp_unslash( $_POST['val'][ $id ] ) ); } }
				elseif ( isset( $byid[ $id ] ) && '' !== $byid[ $id ]['sug'] ) { $set[ $id ] = $byid[ $id ]['sug']; }
			}
			echo '<div class="notice notice-success"><p>تم تحديث <b>' . (int) zad_city_apply( $set ) . '</b> صفحة من ' . count( $sel ) . ' محددة. امسح كاش LiteSpeed. يمكنك «تراجع» عن هذا التطبيق.</p></div>';
		}
	}

	$rows = zad_city_rows();
	$map  = zad_city_map();
	$cnt  = array();
	foreach ( $rows as $r ) { $cnt[ $r['scope'] ] = ( $cnt[ $r['scope'] ] ?? 0 ) + 1; }
	$review = count( array_filter( $rows, function ( $r ) { return $r['review']; } ) );
	echo '<h2>الملخص — ' . count( $rows ) . ' صفحة</h2><ul style="list-style:disc;margin-inline-start:22px">';
	foreach ( $cnt as $sc => $n ) { echo '<li><b>' . (int) $n . '</b> — ' . esc_html( zad_city_label( (string) $sc ) ) . '</li>'; }
	echo '</ul><p><b>' . (int) $review . '</b> صفحة «راجع» (مدينتها غير محددة بحقل ولا أم ولا رابط).</p>';

	// cities found in the data but not in the list
	$new = array();
	foreach ( (array) get_terms( array( 'taxonomy' => 'service_area', 'parent' => 0, 'hide_empty' => false ) ) as $t ) { if ( $t instanceof WP_Term ) { $sl = strtolower( urldecode( $t->slug ) ); if ( ! isset( $map[ $sl ] ) && ! in_array( zad_city_norm_slug( $t->name ), array_keys( $map ), true ) ) { $new[ $sl ] = $t->name; } } }
	foreach ( $rows as $r ) { if ( '' !== $r['saved'] && 'all' !== strtolower( $r['saved'] ) && ! isset( $map[ zad_city_norm_slug( $r['saved'] ) ] ) ) { $new[ zad_city_norm_slug( $r['saved'] ) ] = $r['saved']; } }
	if ( $new ) {
		echo '<form method="post" class="notice notice-info inline" style="padding:8px 12px">';
		wp_nonce_field( 'zad_city_tool' );
		echo '<p><b>مدن وُجدت في الموقع وليست في قائمة المدن:</b> حدّد ما تريد إضافته ليظهر في الحقل والقوائم.</p>';
		foreach ( $new as $sl => $nm ) { echo '<label style="margin-inline-end:16px"><input type="checkbox" name="newcity[]" value="' . esc_attr( $sl . '|' . $nm ) . '" checked> ' . esc_html( $nm ) . ' <code>' . esc_html( $sl ) . '</code></label>'; }
		echo '<p><button class="button" name="zad_city_do" value="addcities">أضف المحدد</button></p></form>';
	}

	echo '<form method="post">';
	wp_nonce_field( 'zad_city_tool' );
	echo '<p><label>عرض: <select id="zad-ct-f"><option value="">الكل</option><option value="review">راجع فقط</option><option value="flags">فيها تعارض areaServed/التصنيف</option></select></label> <button type="button" class="button" data-ct="review">حدّد «راجع»</button> <button type="button" class="button" data-ct="sug">حدّد ذات اقتراح</button> <button type="button" class="button" data-ct="none">ألغِ التحديد</button></p>';
	echo '<table class="widefat striped" id="zad-ct"><thead><tr><th style="width:28px"></th><th>الصفحة</th><th>المحفوظ</th><th>المعتمد الآن</th><th>المقترح (المصدر)</th><th>المدينة الجديدة</th><th>الـ Schema (areaServed) / تعارض</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr data-review="' . (int) $r['review'] . '" data-flags="' . (int) ! empty( $r['flags'] ) . '" data-sug="' . (int) ( '' !== $r['sug'] && ( '' === $r['saved'] ) ) . '"><td><input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '"></td>';
		echo '<td>' . ( $r['review'] ? '<b style="color:#b32d2e">راجع</b> ' : '' ) . '<a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html( $r['title'] ) . '</a> <small>(' . esc_html( $r['type'] ) . ' · ' . esc_html( $r['status'] ) . ')</small><br><a href="' . esc_url( get_permalink( $r['id'] ) ) . '" dir="ltr" style="font-size:11px">' . esc_html( urldecode( (string) wp_parse_url( (string) get_permalink( $r['id'] ), PHP_URL_PATH ) ) ) . '</a></td>';
		echo '<td>' . ( '' === $r['saved'] ? '—' : esc_html( $r['saved'] ) ) . '</td><td>' . esc_html( zad_city_label( $r['scope'] ) ) . ' <small>(' . esc_html( zad_city_source_label( $r['src'] ) ) . ')</small></td>';
		echo '<td>' . ( '' !== $r['sug'] ? esc_html( zad_city_label( $r['sug'] ) ) : ( '' !== $r['unknown'] ? esc_html( $r['unknown'] ) : '—' ) ) . ( $r['why'] ? ' <small>(' . esc_html( $r['why'] ) . ')</small>' : '' ) . '</td>';
		echo '<td><select name="val[' . (int) $r['id'] . ']"><option value="">— تلقائي —</option>';
		$cur = '' !== $r['saved'] ? ( 'all' === strtolower( $r['saved'] ) ? 'all' : zad_city_norm_slug( $r['saved'] ) ) : ( '' !== $r['sug'] ? $r['sug'] : '' );
		foreach ( $map as $sl => $nm ) { echo '<option value="' . esc_attr( $sl ) . '"' . selected( $cur, $sl, false ) . '>' . esc_html( $nm ) . '</option>'; }
		echo '<option value="all"' . selected( $cur, 'all', false ) . '>كل المدن</option></select></td>';
		echo '<td>' . ( '' !== $r['schema'] ? esc_html( $r['schema'] ) : '—' ) . ( $r['flags'] ? '<br><span style="color:#b32d2e">⚠ ' . esc_html( implode( ' · ', $r['flags'] ) ) . '</span>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table><p style="margin-top:12px"><button class="button button-primary" name="zad_city_do" value="save" onclick="return confirm(\'تطبيق المدينة المختارة في العمود على الصفحات المحددة؟\');">احفظ المحدد (بالقيم المختارة)</button> <button class="button" name="zad_city_do" value="accept" onclick="return confirm(\'تطبيق المقترح على الصفحات المحددة؟\');">اقبل المقترح على المحدد</button>';
	$u = get_option( 'zad_city_undo' );
	echo ' <button class="button" name="zad_city_do" value="undo"' . ( is_array( $u ) && ! empty( $u['rows'] ) ? '' : ' disabled' ) . ' onclick="return confirm(\'التراجع عن آخر تطبيق؟\');">تراجع عن آخر تطبيق' . ( is_array( $u ) && ! empty( $u['rows'] ) ? ' (' . count( $u['rows'] ) . ')' : '' ) . '</button></p></form>';

	// services × cities matrix
	$mx = array(); $cols = array();
	foreach ( $rows as $r ) {
		if ( 'publish' !== $r['status'] ) { continue; }
		$k = zad_city_service_key( $r['id'] ); if ( '' === $k ) { continue; }
		$mx[ $k ][ $r['scope'] ] = ( $mx[ $k ][ $r['scope'] ] ?? 0 ) + 1;
		if ( '' !== $r['scope'] && 'all' !== $r['scope'] ) { $cols[ $r['scope'] ] = zad_city_label( $r['scope'] ); }
	}
	if ( $mx && $cols ) {
		ksort( $mx );
		echo '<h2>الخدمات × المدن (المنشور فقط)</h2><p>كل خدمة موجودة في أي مدينة وأين ناقصة. الخدمة هي «الاسم الأساسي» للصفحة بعد حذف اسم المدينة منه؛ والمعلَّمة «كل المدن» تُحتسب في كل مدينة.</p><table class="widefat striped" style="max-width:900px"><thead><tr><th>الخدمة</th>';
		foreach ( $cols as $nm ) { echo '<th>' . esc_html( $nm ) . '</th>'; }
		echo '<th>غير محددة</th></tr></thead><tbody>';
		foreach ( $mx as $k => $c ) {
			echo '<tr><td>' . esc_html( $k ) . '</td>';
			foreach ( $cols as $sl => $nm ) { $n = ( $c[ $sl ] ?? 0 ) + ( $c['all'] ?? 0 ); echo '<td>' . ( $n ? '<span style="color:#00a32a">✔ ' . (int) $n . '</span>' : '<span style="color:#b32d2e">✘</span>' ) . '</td>'; }
			echo '<td>' . ( ! empty( $c[''] ) ? (int) $c[''] : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	?>
<script>
(function () {
	var t = document.getElementById('zad-ct'); if (!t) { return; }
	var rows = [].slice.call(t.tBodies[0].rows), f = document.getElementById('zad-ct-f');
	f.addEventListener('change', function () { rows.forEach(function (r) { r.style.display = (!f.value || (f.value === 'review' && r.dataset.review === '1') || (f.value === 'flags' && r.dataset.flags === '1')) ? '' : 'none'; }); });
	[].forEach.call(document.querySelectorAll('[data-ct]'), function (b) { b.addEventListener('click', function () { var k = b.getAttribute('data-ct'); rows.forEach(function (r) { var c = r.querySelector('input[type=checkbox]'); c.checked = k === 'review' ? r.dataset.review === '1' : (k === 'sug' ? r.dataset.sug === '1' : false); }); }); });
})();
</script></div>
	<?php
}
