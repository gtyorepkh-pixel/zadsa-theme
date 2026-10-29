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

