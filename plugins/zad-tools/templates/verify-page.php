<?php defined( 'ABSPATH' ) || exit;
/** Template «التحقق من الضمان»: noindex, never cached, rate-limited. */
get_header();
?>
<main id="main" class="zt-page zt-verify">
	<section class="sec zt-hero"><div class="wrap wrap--narrow"><h1>التحقق من الضمان</h1><p class="zt-lead">اكتب كود الضمان المطبوع على الشهادة لتعرف حالته.</p></div></section>
	<?php zt_render_verify(); ?>
</main>
<?php get_footer();
