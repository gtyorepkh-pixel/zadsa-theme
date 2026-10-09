<?php defined( 'ABSPATH' ) || exit;
/**
 * Template «أداة زاد»: header and footer come from the active theme; the page's text is server-rendered (no JS needed to read it).
 * A theme may override this file by providing  zad-tools/tool-page.php .
 */
get_header();
while ( have_posts() ) :
	the_post();
	$ctx = zt_ctx( get_the_ID() );
	?>
<main id="main" class="zt-page">
	<section class="sec zt-hero"><div class="wrap wrap--narrow">
		<?php if ( function_exists( 'zad_render_crumbs' ) && function_exists( 'zad_current_crumbs' ) ) { zad_render_crumbs( zad_current_crumbs() ); } ?>
		<h1><?php the_title(); ?></h1>
		<?php
		$lead = '' !== $ctx['intro'] ? $ctx['intro'] : (string) ( $ctx['def']['desc'] ?? '' );
		if ( '' !== $lead ) { echo '<p class="zt-lead">' . esc_html( $lead ) . '</p>'; }
		$extra = trim( (string) get_the_content() );
		if ( '' !== $extra ) { echo '<div class="zt-extra">'; the_content(); echo '</div>'; }
		?>
	</div></section>
	<?php zt_render_body( $ctx ); ?>
</main>
<?php
endwhile;
get_footer();
