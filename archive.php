<?php defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'title'  => is_home() ? 'المدونة' : get_the_archive_title(),
	'sub'    => is_archive() ? get_the_archive_description() : '',
	'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( is_home() ? 'المدونة' : wp_strip_all_tags( get_the_archive_title() ), '' ) ),
) );
?>
<main id="main" class="sec">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<div class="sgrid"><?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/post-card' ); } ?></div>
			<?php echo memo_pagination(); // phpcs:ignore ?>
		<?php else : ?>
			<p class="empty">لا يوجد محتوى حالياً.</p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
