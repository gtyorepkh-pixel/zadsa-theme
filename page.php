<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero', null, array( 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
	?>
<main id="main" class="sec">
	<div class="wrap wrap--narrow">
		<article class="prose entry-content"><?php the_content(); ?></article>
		<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
	</div>
</main>
<?php endwhile; get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
