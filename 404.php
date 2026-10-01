<?php defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="sec">
	<div class="wrap wrap--narrow nf">
		<span class="nf__n">404</span>
		<h1>عذراً، الصفحة غير موجودة</h1>
		<p>ربما تم نقل الصفحة أو حذفها. يمكنك العودة للرئيسية أو تصفح خدماتنا.</p>
		<p><a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">الصفحة الرئيسية</a> <a class="btn btn--ghost-dark" href="<?php echo esc_url( get_post_type_archive_link( 'zad_service' ) ); ?>">الخدمات</a></p>
	</div>
</main>
<?php get_footer(); ?>
