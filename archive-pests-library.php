<?php defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'title'  => post_type_archive_title( '', false ) ?: 'المكتبة',
	'sub'    => wp_kses_post( wpautop( zad_opt( 'memopt_pest_sec1_h' ) ) ),
	'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( 'المكتبة', '' ) ),
) );
?>
<main id="main" class="sec">
	<div class="wrap">
		<div class="sgrid"><?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/post-card' ); } ?></div>
		<?php echo memo_pagination(); // phpcs:ignore ?>
	</div>
</main>
<?php get_footer(); ?>
