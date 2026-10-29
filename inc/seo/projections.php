<?php defined( 'ABSPATH' ) || exit;
/** Paginated media sitemaps and one text export, based on the same published-page policy. */
function zad_seo_media_xml( $page = 1 ) {
	$ids = zad_seo_eligible_ids( 'zad_work' );
	$ids = array_slice( $ids, max( 0, $page - 1 ) * 100, 100 );
	$esc = function ( $s ) { return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); };
	$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
	foreach ( $ids as $id ) {
		$vid = zad_wk_video( $id ); $image = zad_wk_image_id( $id );
		if ( ! $vid['kind'] && ! $image ) { continue; }
		$out .= '<url><loc>' . $esc( get_permalink( $id ) ) . '</loc><lastmod>' . $esc( get_post_modified_time( 'c', true, $id ) ) . '</lastmod>';
		if ( $image ) { $url = wp_get_attachment_image_url( $image, 'full' ); if ( $url ) { $out .= '<image:image><image:loc>' . $esc( $url ) . '</image:loc></image:image>'; } }
		$video_url = 'file' === $vid['kind'] ? $vid['src'] : ( 'yt' === $vid['kind'] ? $vid['embed'] : '' );
		if ( $video_url && $vid['poster'] && $vid['upload_iso'] ) {
			$out .= '<video:video><video:thumbnail_loc>' . $esc( $vid['poster'] ) . '</video:thumbnail_loc><video:title>' . $esc( get_the_title( $id ) ) . '</video:title><video:description>' . $esc( zad_wk_alt( $id ) ?: get_the_title( $id ) ) . '</video:description>';
			$tag = 'file' === $vid['kind'] ? 'content_loc' : 'player_loc';
			$out .= '<video:' . $tag . '>' . $esc( $video_url ) . '</video:' . $tag . '><video:publication_date>' . $esc( $vid['upload_iso'] ) . '</video:publication_date>';
			if ( $vid['duration'] ) { $out .= '<video:duration>' . (int) $vid['duration'] . '</video:duration>'; }
			$out .= '</video:video>';
		}
		$out .= '</url>';
	}
	return $out . '</urlset>';
}
function zad_seo_text_build( $full = false ) {
	$key = $full ? 'zad_seo_llms_full' : 'zad_seo_llms'; $cached = get_transient( $key ); if ( is_string( $cached ) ) { return $cached; }
	$b = zsc_settings()['business']; $out = '# ' . wp_strip_all_tags( $b['name'] ) . "\n\n" . wp_strip_all_tags( $b['description'] ) . "\n\n";
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $obj ) {
		if ( 'attachment' === $type ) { continue; }
		$ids = zad_seo_eligible_ids( $type ); if ( ! $ids ) { continue; }
		$out .= '## ' . $obj->labels->name . "\n\n";
		foreach ( $ids as $id ) {
			$title = preg_replace( '/[\r\n\[\]]/u', ' ', wp_strip_all_tags( get_the_title( $id ) ) );
			$desc = zad_seo_meta( $id, 'desc' ) ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $id ) ) ), 35, '…' );
			$out .= '- [' . $title . '](' . get_permalink( $id ) . ') — ' . preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $desc ) ) . "\n";
			if ( $full ) { $out .= "\n" . wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $id ) ), true ) . "\n\n"; }
		}
	}
	$out .= "\n## خرائط الموقع\n- " . home_url( '/wp-sitemap.xml' ) . "\n- " . home_url( '/zad-media-sitemap.xml' ) . "\n";
	set_transient( $key, $out, HOUR_IN_SECONDS ); return $out;
}
function zad_seo_text_serve() { /* The presence of this function hands ownership over from the legacy MU generator. */ }
add_action( 'parse_request', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	$media = (bool) preg_match( '/^zad-media-sitemap(?:-(\d+))?\.xml$/', $path, $m );
	$text = in_array( $path, array( 'llms.txt', 'llms-full.txt' ), true );
	if ( ! $media && ! $text ) { return; }
	if ( 'theme' !== zad_seo_mode() || ! get_option( 'blog_public' ) || ( $text && ! zad_opt( 'zad_llms_enabled', true ) ) ) { status_header( 404 ); header( 'Content-Type: text/plain; charset=UTF-8' ); echo 'Not found'; exit; }
	header( 'X-Robots-Tag: noindex, follow' );
	if ( $text ) { header( 'Content-Type: text/plain; charset=UTF-8' ); echo zad_seo_text_build( 'llms-full.txt' === $path ); exit; }
	header( 'Content-Type: application/xml; charset=UTF-8' );
	if ( ! empty( $m[1] ) ) { echo zad_seo_media_xml( max( 1, (int) $m[1] ) ); exit; }
	$pages = (int) ceil( count( zad_seo_eligible_ids( 'zad_work' ) ) / 100 );
	if ( $pages <= 1 ) { echo zad_seo_media_xml(); exit; }
	echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
	for ( $i = 1; $i <= $pages; $i++ ) { echo '<sitemap><loc>' . esc_url( home_url( '/zad-media-sitemap-' . $i . '.xml' ) ) . '</loc></sitemap>'; }
	echo '</sitemapindex>'; exit;
}, -5 );
add_filter( 'robots_txt', function ( $txt, $public ) {
	if ( $public && 'theme' === zad_seo_mode() && false === strpos( $txt, 'zad-media-sitemap.xml' ) ) { $txt .= "\nSitemap: " . home_url( '/zad-media-sitemap.xml' ) . "\n"; }
	return $txt;
}, 30, 2 );
