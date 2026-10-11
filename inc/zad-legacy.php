<?php defined( 'ABSPATH' ) || exit;
/**
 * Safety net for live sites whose custom post types were registered by the OLD theme (or code that disappears
 * when this theme is activated). Content stays in the database even when a type is unregistered, but the type
 * and its URLs vanish. At init we read the post types actually stored in the database; any type that matches the
 * configured service/FAQ/article slugs and is NOT registered by anything else gets registered here with the same
 * key and a URL base derived from the key (pest_control → /pest-control/). Nothing is registered twice.
 */

/** Post type keys present in the database but not registered. */
function zad_db_orphan_types() {
	global $wpdb;
	$keys = $wpdb->get_col( "SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_status NOT IN ('auto-draft','inherit','trash') AND post_type NOT IN ('post','page','attachment','revision','nav_menu_item','custom_css','customize_changeset','oembed_cache','user_request','wp_block','wp_template','wp_template_part','wp_global_styles','wp_navigation')" ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $keys as $k ) {
		if ( ! post_type_exists( $k ) && 0 !== strpos( $k, 'zad_' ) ) {
			$out[] = $k;
		}
	}
	return $out;
}

add_action( 'init', function () {
	if ( ! zad_opt( 'zad_legacy_register', true ) ) {
		return;
	}
	$want = array_merge( zad_role_slugs()['service'], zad_role_slugs()['faq'], zad_role_slugs()['article'] );
	$done = array();
	foreach ( zad_db_orphan_types() as $k ) {
		$norm = str_replace( '_', '-', strtolower( $k ) );
		if ( in_array( $norm, array( 'moving', 'drain-cleaning' ), true ) ) { continue; } // retired for good: never brought back (see inc/zad-purge-types.php)
		if ( ! in_array( $norm, $want, true ) || ( function_exists( 'zad_faqconv_retired' ) && zad_faqconv_retired() && $k === zad_faqconv_cfg( 'from' ) ) ) {
			continue;
		}
		$lbl = ( apply_filters( 'zad_legacy_type_labels', array( 'pests-library' => 'مدونة الحشرات', 'guide' => 'الأدلة', 'cleaning' => 'التنظيف', 'pest_control' => 'مكافحة الحشرات', 'faq' => 'الأسئلة القديمة' ) )[ $k ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $k ) ) ) . ' (مؤقت)';
		global $wpdb;
		$hier = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent > 0 AND post_status NOT IN ('trash','auto-draft','inherit') LIMIT 1", $k ) ); // nested pages keep their nested URLs (/pests-library/parent/child/)
		register_post_type( $k, array(
			'labels'        => array( 'name' => $lbl, 'singular_name' => $lbl, 'menu_name' => $lbl ),
			'public'        => true,
			'hierarchical'  => $hier,
			'has_archive'   => $norm,
			'rewrite'       => array( 'slug' => $norm, 'with_front' => false, 'hierarchical' => $hier ),
			'show_in_rest'  => true,
			'menu_position' => 6,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions', 'author', 'custom-fields' ),
		) );
		$done[] = $k;
	}
	$GLOBALS['zad_legacy_registered'] = $done;
}, 5 );

/** Their taxonomies (categories of the old types), if stored but unregistered. */
add_action( 'init', function () {
	global $wpdb, $zad_legacy_registered;
	if ( empty( $zad_legacy_registered ) ) {
		return;
	}
	// only the taxonomies the pages of these types are actually assigned to (not every orphan taxonomy in the database)
	$in  = "'" . implode( "','", array_map( 'esc_sql', $zad_legacy_registered ) ) . "'";
	$tax = $wpdb->get_col( "SELECT DISTINCT tt.taxonomy FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE p.post_type IN ($in) AND tt.taxonomy NOT IN ('category','post_tag','nav_menu','link_category','post_format','wp_theme','wp_template_part_area')" ); // phpcs:ignore
	foreach ( (array) $tax as $t ) {
		if ( taxonomy_exists( $t ) || 0 === strpos( $t, 'service_' ) || 0 === strpos( $t, 'faq_' ) || ( function_exists( 'zad_faqconv_retired' ) && zad_faqconv_retired() && $t === zad_faqconv_cfg( 'tax' ) ) ) {
			continue;
		}
		register_taxonomy( $t, $zad_legacy_registered, array(
			'labels' => array( 'name' => ucwords( str_replace( array( '-', '_' ), ' ', $t ) ) ),
			'hierarchical' => true, 'public' => true, 'show_admin_column' => true, 'show_in_rest' => true,
			'rewrite' => array( 'slug' => str_replace( '_', '-', $t ), 'with_front' => false ),
		) );
	}
}, 6 );

add_action( 'admin_notices', function () {
	global $zad_legacy_registered;
	if ( empty( $zad_legacy_registered ) || ! current_user_can( 'manage_options' ) || ( isset( $_GET['page'] ) && 'zad-adopt' === $_GET['page'] ) ) { // phpcs:ignore
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Zad Pro:</strong> سُجّلت أنواع المحتوى التالية تلقائياً لأنها موجودة في قاعدة البيانات وغير مسجّلة (على الأرجح كان الثيم القديم يسجّلها): <code>' . esc_html( implode( '، ', $zad_legacy_registered ) ) . '</code>. للتثبيت الدائم سجّلها في إضافة أو mu-plugin، ثم راجع «الأدوات ← تبنّي المحتوى الحالي».</p></div>';
} );

/** «نقل وتخزين الأثاث» و«تسليك المجاري» أُلغيا نهائياً: لو سجّلهما أي ملف خارج القالب (mu-plugin قديم) يُلغى تسجيلهما هنا فلا يظهران في اللوحة ولا الروابط. */
add_action( 'init', function () {
	foreach ( array( 'moving', 'drain_cleaning', 'drain-cleaning' ) as $t ) { if ( post_type_exists( $t ) ) { unregister_post_type( $t ); } }
}, 9999 );
