<?php defined( 'ABSPATH' ) || exit;
/** Shared public eligibility for all theme SEO projections. */
function zad_seo_url_path( $url ) {
 $path = trim( mb_strtolower( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ), '/' );
 return $path ? '/' . $path . '/' : '/';
}

/** Read existing redirect data; no external plugin is required by the policy. */
function zad_seo_url_redirected( $url ) {
 $path = zad_seo_url_path( $url );
 if ( function_exists( 'zad_redirect_find' ) && zad_redirect_find( $path ) ) { return true; } // theme redirect map
 $map = get_option( 'zad_wm_map', array() );
 $target = is_array( $map ) ? ( $map[ trim( $path, '/' ) ] ?? '' ) : '';
 if ( is_string( $target ) && $target && wp_parse_url( $target, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) && untrailingslashit( $url ) !== untrailingslashit( $target ) ) { return true; }
 if ( function_exists( 'zad_cu_redirected_paths' ) && in_array( $path, array_map( 'zad_seo_url_path', zad_cu_redirected_paths() ), true ) ) { return true; }
 if ( function_exists( 'zad_cu_redirection_sources' ) ) { return isset( zad_cu_redirection_sources()[ $path ] ); }
 static $sources = null;
 if ( null === $sources ) {
  $sources = array();
  $active = in_array( 'redirection/redirection.php', (array) get_option( 'active_plugins', array() ), true ) || isset( get_site_option( 'active_sitewide_plugins', array() )['redirection/redirection.php'] );
  if ( $active ) {
   global $wpdb; $table = $wpdb->prefix . 'redirection_items';
   if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
    foreach ( (array) $wpdb->get_col( "SELECT url FROM {$table} WHERE status='enabled' AND regex=0" ) as $source ) { $sources[ zad_seo_url_path( $source ) ] = true; }
   }
  }
 }
 return isset( $sources[ $path ] );
}

function zad_seo_post_eligible( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) { return false; }
	$type = get_post_type_object( $post->post_type );
	if ( ! $type || ! $type->public || 'attachment' === $post->post_type ) { return false; }
	if ( zad_seo_meta( $post->ID, 'noindex' ) ) { return false; }
	$own = get_permalink( $post ); $canonical = zad_seo_meta( $post->ID, 'canonical' );
	if ( $canonical && untrailingslashit( $canonical ) !== untrailingslashit( $own ) ) { return false; }
	if ( zad_seo_url_redirected( $own ) ) { return false; }
	return true;
}

/** One term policy for XML taxonomies and HTML topic/city links. */
function zad_seo_term_eligible( $term ) {
 $term = get_term( $term );
 if ( ! $term || is_wp_error( $term ) ) { return false; }
 $taxonomy = get_taxonomy( $term->taxonomy );
 if ( ! $taxonomy || ! $taxonomy->public || zad_term_noindexed( $term ) ) { return false; }
 $own = get_term_link( $term );
 if ( is_wp_error( $own ) || ! $own ) { return false; }
 $canonical = zad_seo_term_meta( $term, 'canonical' );
 return ! ( $canonical && untrailingslashit( $canonical ) !== untrailingslashit( $own ) ) && ! zad_seo_url_redirected( $own );
}

function zad_seo_eligible_ids( $type ) {
	$key = 'zad_seo_ids_' . sanitize_key( $type );
	$cached = get_transient( $key ); if ( is_array( $cached ) ) { return $cached; }
	$ids = array(); $page = 1;
	do {
		$batch = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'has_password' => false,
			'posts_per_page' => 200, 'paged' => $page++, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true, 'zad_all' => true ) );
		if ( $batch ) { update_meta_cache( 'post', $batch ); }
		foreach ( $batch as $id ) { if ( zad_seo_post_eligible( $id ) ) { $ids[] = (int) $id; } }
	} while ( count( $batch ) === 200 );
	set_transient( $key, $ids, HOUR_IN_SECONDS ); return $ids;
}
function zad_seo_flush_projections() {
	foreach ( get_post_types( array( 'public' => true ) ) as $type ) { delete_transient( 'zad_seo_ids_' . sanitize_key( $type ) ); }
	foreach ( array( 'zad_sitemap_html', 'zad_seo_llms', 'zad_seo_llms_full', 'zad_auto_price_range', 'zad_wiz_map3', 'zad_cu_llms_txt', 'zad_cu_llms_full' ) as $key ) { delete_transient( $key ); }
	if ( function_exists( 'zad_flush_nx' ) ) { zad_flush_nx(); }
}
add_action( 'save_post', 'zad_seo_flush_projections', 100 );
add_action( 'deleted_post', 'zad_seo_flush_projections' );
add_action( 'trashed_post', 'zad_seo_flush_projections' );
add_action( 'update_option__memo_theme_options', 'zad_seo_flush_projections' );
foreach ( array( 'created_term', 'edited_term', 'delete_term', 'set_object_terms', 'added_term_meta', 'updated_term_meta', 'deleted_term_meta', 'update_option_zad_wm_map', 'update_option_zad_auto_redirects', 'update_option_active_plugins', 'update_option_zad_rw_ver', 'redirection_redirect_updated', 'redirection_redirect_deleted', 'redirection_redirect_enabled', 'redirection_redirect_disabled' ) as $hook ) {
 add_action( $hook, 'zad_seo_flush_projections' );
}
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
	add_action( $hook, function ( $meta_id, $post_id, $key ) { if ( in_array( $key, array( '_zad_seo_noindex', '_zad_seo_canonical' ), true ) ) { zad_seo_flush_projections(); } }, 10, 3 );
}
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) {
	if ( 'theme' === zad_seo_mode() ) { $args['post__in'] = zad_seo_eligible_ids( $type ) ?: array( 0 ); $args['has_password'] = false; $args['zad_all'] = true; }
	return $args;
}, 100, 2 );
/** Legacy term metadata remains readable until a native value is explicitly saved. */
function zad_seo_term_meta( $term, $field ) {
	if ( ! $term || is_wp_error( $term ) ) { return ''; }
	$key = array( 'title' => '_zad_seo_title', 'desc' => '_zad_seo_desc', 'canonical' => '_zad_seo_canonical', 'noindex' => '_zad_noindex' )[ $field ] ?? '';
	if ( $key && metadata_exists( 'term', $term->term_id, $key ) ) { return get_term_meta( $term->term_id, $key, true ); }
	if ( function_exists( 'zad_seo_legacy_on' ) && ! zad_seo_legacy_on() ) { return ''; }
	$legacy = (array) get_option( 'wpseo_taxonomy_meta', array() );
	$name = array( 'title' => 'wpseo_title', 'desc' => 'wpseo_desc', 'canonical' => 'wpseo_canonical', 'noindex' => 'wpseo_noindex' )[ $field ] ?? '';
	$value = $legacy[ $term->taxonomy ][ $term->term_id ][ $name ] ?? '';
	if ( 'noindex' === $field ) { return in_array( (string) $value, array( '1', 'noindex' ), true ) ? '1' : ''; }
	$value = str_replace( array( '%%term_title%%', '%%category%%', '%%tag%%', '%%term_description%%' ), array( $term->name, $term->name, $term->name, wp_strip_all_tags( $term->description ) ), (string) $value );
	return zad_seo_expand_template( $value );
}

/** Archive switches must apply on public requests even when admin audit tools are disabled. */
add_filter( 'wp_robots', function ( $robots ) {
 if ( 'theme' !== zad_seo_mode() ) { return $robots; }
 $hit = ( zad_opt( 'zad_noindex_tag', false ) && is_tag() )
  || ( zad_opt( 'zad_noindex_cat', false ) && is_category() )
  || ( zad_opt( 'zad_noindex_author', false ) && is_author() )
  || ( zad_opt( 'zad_noindex_date', false ) && is_date() )
  || ( zad_opt( 'zad_noindex_paged', false ) && is_paged() && ( is_archive() || is_home() ) );
 if ( $hit ) { $robots['noindex'] = true; $robots['follow'] = true; unset( $robots['index'], $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] ); }
 return $robots;
}, 30 );
add_filter( 'wp_sitemaps_users_query_args', function ( $args ) {
 if ( 'theme' !== zad_seo_mode() ) { return $args; }
 $ids = array(); foreach ( get_users( array( 'fields' => 'ID' ) ) as $id ) { if ( zad_author_has_bio( $id ) ) { $ids[] = (int) $id; } }
 $args['include'] = $ids ?: array( 0 ); return $args;
} );
