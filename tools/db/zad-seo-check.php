<?php
/**
 * Zad Saudi — canonical / indexability check (READ-ONLY: writes nothing to the database).
 *
 * Run from the WordPress root (locally, and again on the live site after the move):
 *   wp eval-file zad-seo-check.php
 * Options (environment variables):
 *   ZAD_TYPES=page,pest_control,cleaning,zad_hood,guide   post types to test (default: those, plus the theme's service types)
 *   ZAD_LIMIT=40        only the first N URLs per type (default: all)  — use it for a quick first look
 *   ZAD_FETCH=0         skip the HTTP part, print only the configuration checks
 *
 * Part 1 — configuration: is Yoast active; does the theme remove/replace Yoast's canonical; for every tested post type
 *          Yoast's «noindex-<type>» switch, and how many posts carry a per-post noindex.
 * Part 2 — every published page of those types is requested over HTTP and must show:
 *          exactly ONE <link rel="canonical">, whose address is the page's own URL, status 200, and no noindex
 *          (neither <meta name="robots"> nor the X-Robots-Tag header).
 * Output: summary + problem list on screen, and wp-content/uploads/zad-inventory/seo-check-YYYYmmdd-HHMM.csv
 */
if ( ! defined( 'ABSPATH' ) ) { exit( "Run with: wp eval-file zad-seo-check.php\n" ); }
global $wpdb;

$want  = array_filter( array_map( 'trim', explode( ',', (string) ( getenv( 'ZAD_TYPES' ) ?: 'page,pest_control,cleaning,zad_hood,guide' ) ) ) );
if ( function_exists( 'zad_service_types' ) && ! getenv( 'ZAD_TYPES' ) ) { $want = array_merge( $want, zad_service_types() ); }
$want  = array_values( array_unique( array_filter( $want, 'post_type_exists' ) ) );
$limit = (int) getenv( 'ZAD_LIMIT' );
$fetch = '0' !== (string) getenv( 'ZAD_FETCH' );

echo "== 1) الإعدادات ==\n";
$yoast = defined( 'WPSEO_VERSION' );
echo 'Yoast: ' . ( $yoast ? 'مفعّل ' . WPSEO_VERSION : 'غير مفعّل' ) . "\n";
echo 'وضع السيو في الثيم: ' . ( function_exists( 'zad_seo_mode' ) ? zad_seo_mode() : '؟' ) . ' | الثيم يطبع canonical بنفسه: ' . ( function_exists( 'zad_seo_active' ) ? ( zad_seo_active() ? 'لا (Yoast/إضافة سيو تتولاه)' : 'نعم' ) : '؟' ) . "\n";
$th = get_template_directory();
$hits = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $th, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( 'php' !== $f->getExtension() || false !== strpos( $f->getPathname(), '/tools/' ) ) { continue; }
	$src = (string) file_get_contents( $f->getPathname() );
	foreach ( array( "remove_action( 'wp_head', 'rel_canonical'", "remove_filter( 'wpseo_canonical'", "add_filter( 'wpseo_canonical'", "remove_action( 'wp_head', array( \$GLOBALS['wpseo_front']" ) as $needle ) {
		if ( false !== strpos( $src, $needle ) ) { $hits[] = str_replace( $th . '/', '', $f->getPathname() ) . ' ← ' . $needle; }
	}
}
echo 'أكواد تحذف/تغيّر canonical في الثيم: ' . ( $hits ? "\n   " . implode( "\n   ", $hits ) . "\n   (inc/zad-seo.php يعمل فقط حين لا يوجد Yoast — راجع الشرط zad_seo_active)" : 'لا يوجد' ) . "\n";
$mu = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
foreach ( (array) glob( $mu . '/*.php' ) as $f ) {
	$src = (string) file_get_contents( $f );
	if ( false !== strpos( $src, 'canonical' ) ) { echo '   mu-plugin يذكر canonical: ' . basename( $f ) . "\n"; }
}
$ti = (array) get_option( 'wpseo_titles', array() );
echo "\nفهرسة الأنواع في Yoast (wpseo_titles) وعدد الصفحات المعلّمة noindex لكل صفحة:\n";
printf( "  %-16s %-12s %-10s %s\n", 'النوع', 'منشور', 'Yoast', 'noindex بالصفحة' );
foreach ( $want as $t ) {
	$n   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish'", $t ) );
	$nx  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type=%s AND p.post_status='publish' AND ((m.meta_key='_yoast_wpseo_meta-robots-noindex' AND m.meta_value='1') OR (m.meta_key='_zad_seo_noindex' AND m.meta_value='1'))", $t ) );
	$yn  = isset( $ti[ 'noindex-' . $t ] ) ? ( $ti[ 'noindex-' . $t ] ? 'NOINDEX ✗' : 'index ✓' ) : ( $yoast ? 'index ✓ (افتراضي)' : '—' );
	printf( "  %-16s %-12d %-10s %d%s\n", $t, $n, $yn, $nx, $nx ? '  ✗' : '' );
}
foreach ( array( 'service_cat', 'service_area', 'faq_cat' ) as $tx ) { if ( taxonomy_exists( $tx ) ) { echo "  تصنيف $tx: " . ( ! empty( $ti[ 'noindex-tax-' . $tx ] ) ? 'NOINDEX' : 'index' ) . "\n"; } }
if ( ! $fetch ) { exit( "\n(ZAD_FETCH=0: تم تخطي فحص الصفحات)\n" ); }

echo "\n== 2) فحص الصفحات عبر HTTP ==\n";
$dir = wp_upload_dir()['basedir'] . '/zad-inventory'; wp_mkdir_p( $dir );
$csv = $dir . '/seo-check-' . gmdate( 'Ymd-Hi' ) . '.csv';
$fh  = fopen( $csv, 'w' ); fwrite( $fh, "\xEF\xBB\xBF" );
fputcsv( $fh, array( 'status', 'type', 'id', 'url', 'http', 'canonical_count', 'canonical', 'robots', 'x_robots', 'problems' ) );
$norm = function ( $u ) { return rtrim( strtolower( rawurldecode( preg_replace( '#^https?://#i', '', (string) $u ) ) ), '/' ); };
$tot = 0; $bad = array(); $by = array();
foreach ( $want as $t ) {
	$ids = get_posts( array( 'post_type' => $t, 'post_status' => 'publish', 'numberposts' => $limit > 0 ? $limit : -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
	foreach ( $ids as $id ) {
		$url = get_permalink( $id );
		$r   = wp_remote_get( $url, array( 'timeout' => 25, 'redirection' => 0, 'sslverify' => false, 'user-agent' => 'ZadSeoCheck/1.0', 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		$pr  = array(); $http = 0; $cc = 0; $can = ''; $rob = ''; $xr = '';
		if ( is_wp_error( $r ) ) { $pr[] = 'تعذر الجلب: ' . $r->get_error_message(); }
		else {
			$http = (int) wp_remote_retrieve_response_code( $r );
			$html = (string) wp_remote_retrieve_body( $r );
			$xr   = (string) wp_remote_retrieve_header( $r, 'x-robots-tag' );
			$head = preg_replace( '#<body\b.*#is', '', $html );
			preg_match_all( '#<link\b[^>]*\brel=["\']canonical["\'][^>]*>#i', $head, $lm );
			$cc = count( $lm[0] );
			if ( $cc && preg_match( '#href=["\']([^"\']*)["\']#i', $lm[0][0], $hm ) ) { $can = html_entity_decode( $hm[1] ); }
			if ( preg_match( '#<meta\b[^>]*name=["\']robots["\'][^>]*content=["\']([^"\']*)["\']#i', $head, $rm ) || preg_match( '#<meta\b[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']robots["\']#i', $head, $rm ) ) { $rob = $rm[1]; }
			if ( 200 !== $http ) { $pr[] = "HTTP $http"; }
			if ( 1 !== $cc ) { $pr[] = "عدد canonical = $cc"; }
			if ( $can && $norm( $can ) !== $norm( $url ) ) { $pr[] = 'canonical لا يطابق رابط الصفحة'; }
			if ( false !== stripos( $rob, 'noindex' ) ) { $pr[] = 'noindex في meta'; }
			if ( false !== stripos( $xr, 'noindex' ) ) { $pr[] = 'noindex في الترويسة'; }
			if ( false !== stripos( $can . $url, '.local' ) ) { $pr[] = 'نطاق .local'; }
		}
		$tot++; $by[ $t ][ $pr ? 'bad' : 'ok' ] = ( $by[ $t ][ $pr ? 'bad' : 'ok' ] ?? 0 ) + 1;
		fputcsv( $fh, array( $pr ? 'PROBLEM' : 'OK', $t, $id, $url, $http, $cc, $can, $rob, $xr, implode( ' | ', $pr ) ) );
		if ( $pr ) { $bad[] = "#$id [$t] $url\n      " . implode( ' | ', $pr ); }
	}
}
fclose( $fh );
echo "عدد الصفحات المفحوصة: $tot\n";
foreach ( $by as $t => $c ) { printf( "  %-16s سليمة %d  |  بها مشكلة %d\n", $t, $c['ok'] ?? 0, $c['bad'] ?? 0 ); }
echo $bad ? "\n— المشاكل (أول 40) —\n" . implode( "\n", array_slice( $bad, 0, 40 ) ) . "\n" : "\n✓ كل الصفحات: canonical واحد = رابط الصفحة، بلا noindex.\n";
echo "\nCSV: $csv\n";
