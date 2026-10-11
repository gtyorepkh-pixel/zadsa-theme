<?php defined( 'ABSPATH' ) || exit;
get_header();
$cur  = isset( $_GET['svc'] ) ? sanitize_title( wp_unslash( $_GET['svc'] ) ) : ''; // phpcs:ignore
$all  = zad_works_url();
$ids  = get_posts( array( 'post_type' => ZAD_WK, 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids', 'suppress_filters' => true ) );
$cats = $ids ? get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'object_ids' => $ids ) ) : array();
get_template_part( 'template-parts/page-hero', null, array( 'title' => 'أعمالنا', 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( 'أعمالنا', '' ) ), 'sub' => '<p>قصص شغلانات حقيقية من الميدان: فيديو يشرح الحالة، وفحص، ونتيجة بالصور قبل وبعد.</p>' ) );
?>
<main id="main" class="sec zw-archive"><div class="wrap">
	<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
	<nav class="chips chips--filter" aria-label="نوع الخدمة"><a<?php echo '' === $cur ? ' class="is-on"' : ''; ?> href="<?php echo esc_url( $all ); ?>">الكل</a><?php foreach ( $cats as $t ) : ?><a<?php echo $cur === $t->slug ? ' class="is-on"' : ''; ?> href="<?php echo esc_url( add_query_arg( 'svc', $t->slug, $all ) ); ?>"><?php echo esc_html( $t->name ); ?></a><?php endforeach; ?></nav>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="sgrid"><?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/work-card' ); } ?></div>
		<?php echo zad_pagination(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php else : ?><p class="empty">لا توجد أعمال منشورة بعد.</p><?php endif; ?>
</div></main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
