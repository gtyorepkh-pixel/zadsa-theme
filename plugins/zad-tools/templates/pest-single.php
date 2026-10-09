<?php defined( 'ABSPATH' ) || exit;
/** Single pest page. A theme may override it with  zad-tools/pest-single.php . */
get_header();
while ( have_posts() ) :
	the_post(); ?>
<main id="main" class="zt-page">
	<section class="sec zt-hero"><div class="wrap wrap--narrow">
		<?php if ( function_exists( 'zad_render_crumbs' ) && function_exists( 'zad_current_crumbs' ) ) { zad_render_crumbs( zad_current_crumbs() ); } ?>
		<h1><?php the_title(); ?></h1>
		<?php if ( has_excerpt() ) { echo '<p class="zt-lead">' . esc_html( get_the_excerpt() ) . '</p>'; } ?>
	</div></section>
	<?php zt_pest_body( get_the_ID() ); ?>
</main>
<?php
endwhile;
get_footer();
