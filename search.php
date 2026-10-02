<?php defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'title'  => 'نتائج البحث عن: ' . get_search_query(),
	'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( 'بحث', '' ) ),
) );
?>
<main id="main" class="sec">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<div class="sgrid">
				<?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/' . ( in_array( get_post_type(), zad_service_types(), true ) ? 'service-card' : 'post-card' ) ); } ?>
			</div>
			<?php echo memo_pagination(); // phpcs:ignore ?>
		<?php else : ?>
			<p class="empty">لم نجد نتائج. جرّب كلمات أخرى أو <a href="<?php echo esc_url( zad_services_url() ); ?>">تصفح الخدمات</a>.</p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
