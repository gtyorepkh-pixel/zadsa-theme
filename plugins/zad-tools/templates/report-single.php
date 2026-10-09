<?php defined( 'ABSPATH' ) || exit;
/** Single monthly report. A theme may override it with  zad-tools/report-single.php . */
get_header();
while ( have_posts() ) :
	the_post(); ?>
<main id="main" class="zt-page">
	<section class="sec zt-hero"><div class="wrap wrap--narrow">
		<?php if ( function_exists( 'zad_render_crumbs' ) && function_exists( 'zad_current_crumbs' ) ) { zad_render_crumbs( zad_current_crumbs() ); } ?>
		<h1><?php the_title(); ?></h1>
	</div></section>
	<?php zt_rep_body( get_the_ID() ); ?>
</main>
<?php
endwhile;
get_footer();
