<?php defined( 'ABSPATH' ) || exit;
/**
 * Page templates renamed from the previous theme's names (3.45.0): temp/memo-about.php → temp/zad-about.php, and contact, gallery, services.
 * Pages saved with an old name keep working (the new file is used) until Tools → «قوالب الصفحات القديمة» rewrites their template field.
 */
function zad_tpl_old_names() {
	return array(
		'temp/zad-about.php'    => 'temp/memo-about.php',
		'temp/zad-contact.php'  => 'temp/memo-contact.php',
		'temp/zad-gallery.php'  => 'temp/memo-gallery.php',
		'temp/zad-services.php' => 'temp/memo-services.php',
	);
}
/** Old name → new name (any other value unchanged). */
function zad_tpl_norm( $tpl ) {
	$f = array_flip( zad_tpl_old_names() );
	return $f[ $tpl ] ?? $tpl;
}
/** A template and its old name, for meta queries. */
function zad_tpl_names( $tpl ) {
	$o = zad_tpl_old_names();
	return isset( $o[ $tpl ] ) ? array( $tpl, $o[ $tpl ] ) : array( $tpl );
}

add_filter( 'template_include', function ( $template ) {
	if ( ! is_page() ) { return $template; }
	$slug = (string) get_page_template_slug();
	$new  = zad_tpl_norm( $slug );
	if ( $new !== $slug ) { $file = locate_template( $new ); if ( $file ) { return $file; } }
	return $template;
}, 5 );

/** Keep an old name selectable on the page that still uses it, so saving the page in the editor does not drop its template. */
add_filter( 'theme_page_templates', function ( $templates, $theme = null, $post = null ) {
	$cur = $post ? (string) get_post_meta( $post->ID, '_wp_page_template', true ) : '';
	if ( $cur && zad_tpl_norm( $cur ) !== $cur && ! isset( $templates[ $cur ] ) ) {
		$new = zad_tpl_norm( $cur );
		$templates[ $cur ] = ( $templates[ $new ] ?? basename( $new ) ) . ' (الاسم القديم)';
	}
	return $templates;
}, 10, 3 );
