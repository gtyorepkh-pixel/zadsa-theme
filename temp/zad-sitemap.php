<?php defined( 'ABSPATH' ) || exit; /* Template Name: خريطة الموقع */
get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'sub'    => wp_kses_post( wpautop( 'كل صفحات وخدمات وتصنيفات الموقع في مكان واحد — تُحدَّث تلقائياً مع أي إضافة جديدة.' ) ),
	'crumbs' => zad_current_crumbs(),
) );
?>
<main id="main" class="sec"><div class="wrap">
	<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) : ?><div class="prose entry-content" style="margin-bottom:24px"><?php while ( have_posts() ) { the_post(); the_content(); } ?></div><?php endif; ?>
	<?php echo zad_sitemap_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<section class="sec sec--tint smx__cta"><div class="wrap wrap--narrow">
		<h2>لم تجد ما تبحث عنه؟</h2>
		<p>اترك لنا رسالة وسنرد عليك بالخدمة المناسبة والموعد.</p>
		<p><button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اترك رسالة</button></p>
	</div></section>
</div></main>
<?php get_footer(); ?>
